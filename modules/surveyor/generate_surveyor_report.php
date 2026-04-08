<?php
ob_start();
require_once '../../includes/init.php';
require_once '../../vendor/autoload.php'; // Composer autoloader for TCPDF

// Redirect if not logged in or not surveyor
redirectIfNotLoggedIn();
if (!isSurveyor()) {
    logAction('unauthorized_access', 'Attempted access to surveyor report generation', 'warning', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

// Validate and sanitize lang parameter
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['db_connection_failed'] ?? "Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);

$user_id = $_SESSION['user']['id'];
$debug_messages = [];
$debug_log = dirname(__FILE__) . '/debug.log';

// Log database name
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$debug_messages[] = "Connected to database: $db_name";

// Check table schemas
$tables = ['cases', 'land_registration', 'ownership_transfers', 'split_requests'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW COLUMNS FROM $table");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $debug_messages[] = "Schema for $table: " . implode(', ', $columns);
}

// Check for GD or Imagick extension
$has_gd = extension_loaded('gd');
$has_imagick = extension_loaded('imagick');
$debug_messages[] = "GD extension: " . ($has_gd ? "enabled" : "disabled");
$debug_messages[] = "Imagick extension: " . ($has_imagick ? "enabled" : "disabled");
if (!$has_gd && !$has_imagick) {
    $debug_messages[] = "Warning: Neither GD nor Imagick is enabled. PNG images with alpha channels will be skipped.";
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Handle filter form submission
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$date_filter = ''; // For lr.created_at (land_registration)
$date_filter_transfers = ''; // For change_date (ownership_transfers)
$bind_params = [];
$param_types = '';

if ($start_date && $end_date) {
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($start && $end && $start <= $end) {
        $date_filter = " AND lr.created_at BETWEEN ? AND ?";
        $date_filter_transfers = " AND change_date BETWEEN ? AND ?";
        $bind_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        $param_types = 'ss';
    } else {
        logAction('invalid_date_filter', 'Invalid date range: ' . $start_date . ' to ' . $end_date, 'warning', ['user_id' => $user_id]);
        $start_date = $end_date = null;
    }
}
$debug_messages[] = "Form data: start_date=$start_date, end_date=$end_date";

// Fetch dashboard metrics
$total_cases = 0;
$sql_total = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ?" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Total cases SQL: $sql_total";
$stmt_total = $conn->prepare($sql_total);
$params = array_merge([$user_id], $bind_params);
$stmt_total->bind_param('i' . $param_types, ...$params);
if ($stmt_total && $stmt_total->execute()) {
    $total_cases = $stmt_total->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Total cases: $total_cases";
} else {
    $debug_messages[] = "Total cases query failed: " . $conn->error;
}
$stmt_total->close();

$recent_cases = 0;
$sql_recent = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND created_at >= NOW() - INTERVAL 30 DAY" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Recent cases SQL: $sql_recent";
$stmt_recent = $conn->prepare($sql_recent);
$stmt_recent->bind_param('i' . $param_types, ...$params);
if ($stmt_recent && $stmt_recent->execute()) {
    $recent_cases = $stmt_recent->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Recent cases: $recent_cases";
} else {
    $debug_messages[] = "Recent cases query failed: " . $conn->error;
}
$stmt_recent->close();

$assigned_cases = 0;
$sql_assigned = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND status = 'Assigned'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Assigned cases SQL: $sql_assigned";
$stmt_assigned = $conn->prepare($sql_assigned);
$stmt_assigned->bind_param('i' . $param_types, ...$params);
if ($stmt_assigned && $stmt_assigned->execute()) {
    $assigned_cases = $stmt_assigned->get_result()->fetch_assoc()['count'] ?? 0; // Fixed: Use $stmt_assigned
    $debug_messages[] = "Assigned cases: $assigned_cases";
} else {
    $debug_messages[] = "Assigned cases query failed: " . $conn->error;
}
$stmt_assigned->close();

$new_cases = 0;
$sql_new = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND viewed = '0'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "New cases SQL: $sql_new";
$stmt_new = $conn->prepare($sql_new);
$stmt_new->bind_param('i' . $param_types, ...$params);
if ($stmt_new && $stmt_new->execute()) {
    $new_cases = $stmt_new->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "New cases: $new_cases";
} else {
    $debug_messages[] = "New cases query failed: " . $conn->error;
}
$stmt_new->close();

$pending_cases = 0;
$sql_pending = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND investigation_status = 'InProgress'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Pending cases SQL: $sql_pending";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param('i' . $param_types, ...$params);
if ($stmt_pending && $stmt_pending->execute()) {
    $pending_cases = $stmt_pending->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Pending cases: $pending_cases";
} else {
    $debug_messages[] = "Pending cases query failed: " . $conn->error;
}
$stmt_pending->close();

$completed_cases = 0;
$sql_completed = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND investigation_status = 'Approved'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Completed cases SQL: $sql_completed";
$stmt_completed = $conn->prepare($sql_completed);
$stmt_completed->bind_param('i' . $param_types, ...$params);
if ($stmt_completed && $stmt_completed->execute()) {
    $completed_cases = $stmt_completed->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Completed cases: $completed_cases";
} else {
    $debug_messages[] = "Completed cases query failed: " . $conn->error;
}
$stmt_completed->close();

$provided_parcels = 0;
$sql_parcels = "SELECT COUNT(*) as count FROM land_registration lr JOIN cases c ON lr.id = c.land_id WHERE lr.has_parcel = 1 AND c.assigned_to = ?" . $date_filter;
$debug_messages[] = "Provided parcels SQL: $sql_parcels";
$stmt_parcels = $conn->prepare($sql_parcels);
$stmt_parcels->bind_param('i' . $param_types, ...$params);
if ($stmt_parcels && $stmt_parcels->execute()) {
    $provided_parcels = $stmt_parcels->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Provided parcels: $provided_parcels";
} else {
    $debug_messages[] = "Provided parcels query failed: " . $conn->error;
}
$stmt_parcels->close();

$ownership_changes = 0;
$sql_changes = "SELECT COUNT(*) as count FROM ownership_transfers WHERE changed_by = ?" . $date_filter_transfers;
$debug_messages[] = "Ownership changes SQL: $sql_changes";
$stmt_changes = $conn->prepare($sql_changes);
$stmt_changes->bind_param('i' . $param_types, ...$params);
if ($stmt_changes && $stmt_changes->execute()) {
    $ownership_changes = $stmt_changes->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Ownership changes: $ownership_changes";
} else {
    $debug_messages[] = "Ownership changes query failed: " . $conn->error;
}
$stmt_changes->close();

$pending_requests = 0;
$sql_pending_requests = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = 'Pending'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Pending split requests SQL: $sql_pending_requests";
$stmt_pending_requests = $conn->prepare($sql_pending_requests);
$stmt_pending_requests->bind_param('i' . $param_types, ...$params);
if ($stmt_pending_requests && $stmt_pending_requests->execute()) {
    $pending_requests = $stmt_pending_requests->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Pending split requests: $pending_requests";
} else {
    $debug_messages[] = "Pending split requests query failed: " . $conn->error;
}
$stmt_pending_requests->close();

$approved_requests = 0;
$sql_approved_requests = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = 'Approved'" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
$debug_messages[] = "Approved split requests SQL: $sql_approved_requests";
$stmt_approved_requests = $conn->prepare($sql_approved_requests);
$stmt_approved_requests->bind_param('i' . $param_types, ...$params);
if ($stmt_approved_requests && $stmt_approved_requests->execute()) {
    $approved_requests = $stmt_approved_requests->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Approved split requests: $approved_requests";
} else {
    $debug_messages[] = "Approved split requests query failed: " . $conn->error;
}
$stmt_approved_requests->close();

// Fetch chart data
$status_counts = ['assigned' => 0, 'pending' => 0, 'approved' => 0];
$statuses = ['Assigned', 'InProgress', 'Approved'];
try {
    $sql = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND (status = ? OR investigation_status = ?)" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
    $debug_messages[] = "Status counts SQL: $sql";
    $stmt = $conn->prepare($sql);
    foreach ($statuses as $index => $status) {
        $params = array_merge([$user_id, $status, $status], $bind_params);
        $stmt->bind_param('iss' . $param_types, ...$params);
        $stmt->execute();
        $status_counts[$index === 0 ? 'assigned' : ($index === 1 ? 'pending' : 'approved')] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    $debug_messages[] = "Status count query failed: " . $e->getMessage();
}

$request_status_counts = ['pending_requests' => 0, 'approved_requests' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = ?" . ($start_date && $end_date ? " AND created_at BETWEEN ? AND ?" : "");
    $debug_messages[] = "Request status counts SQL: $sql";
    $stmt = $conn->prepare($sql);
    foreach (['Pending', 'Approved'] as $index => $status) {
        $params = array_merge([$user_id, $status], $bind_params);
        $stmt->bind_param('is' . $param_types, ...$params);
        $stmt->execute();
        $request_status_counts[$index === 0 ? 'pending_requests' : 'approved_requests'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    $debug_messages[] = "Split request status count query failed: " . $e->getMessage();
}

$parcel_counts = ['provided' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM land_registration lr JOIN cases c ON lr.id = c.land_id WHERE lr.has_parcel = 1 AND c.assigned_to = ?" . $date_filter;
    $debug_messages[] = "Parcel counts SQL: $sql";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$user_id], $bind_params);
    $stmt->bind_param('i' . $param_types, ...$params);
    $stmt->execute();
    $parcel_counts['provided'] = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    $debug_messages[] = "Parcel count query failed: " . $e->getMessage();
}

$debug_messages[] = "Chart data: Case Status Counts: " . json_encode($status_counts);
$debug_messages[] = "Chart data: Split Request Status Counts: " . json_encode($request_status_counts);
$debug_messages[] = "Chart data: Parcel Counts: " . json_encode($parcel_counts);

// Letters directory
$letters_dir = realpath(dirname(__FILE__) . '/../../letters');
if (!is_dir($letters_dir)) {
    mkdir($letters_dir, 0755, true);
    $debug_messages[] = "Created letters directory: $letters_dir";
}
if (!is_writable($letters_dir)) {
    $debug_messages[] = "Directory not writable: $letters_dir";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Directory not writable: $letters_dir\n", FILE_APPEND);
    die($translations[$lang]['directory_error'] ?? "Directory not writable: $letters_dir");
}
$debug_messages[] = "Letters directory is writable: $letters_dir";

// Get logo and stamp
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.jpg';
$logo_source = realpath(dirname(__FILE__) . '/../../' . $navbar_logo);
if (!$logo_source || !file_exists($logo_source)) {
    $debug_messages[] = "Logo not found: path=" . ($logo_source ?? $navbar_logo);
    $navbar_logo = 'assets/images/default_navbar_logo.jpg';
    $logo_source = realpath(dirname(__FILE__) . '/../../' . $navbar_logo);
}
$logo_ext = strtolower(pathinfo($navbar_logo, PATHINFO_EXTENSION));
if ($logo_ext === 'png' && !$has_gd && !$has_imagick) {
    $debug_messages[] = "Logo skipped: PNG ($navbar_logo) requires GD or Imagick for alpha channel";
    $navbar_logo = 'assets/images/default_navbar_logo.jpg';
    $logo_source = realpath(dirname(__FILE__) . '/../../' . $navbar_logo);
}
$debug_messages[] = "Logo set to: $navbar_logo";

$company_stamp = 'assets/images/stamp-placeholder.jpg'; // Fallback to JPEG
$sql = "SELECT image_path FROM company_stamps ORDER BY uploaded_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt && $stmt->execute()) {
    $stamp = $stmt->get_result()->fetch_assoc();
    $stamp_path = $stamp ? $stamp['image_path'] : null;
    $full_stamp_path = $stamp_path ? realpath(dirname(__FILE__) . '/../../' . $stamp_path) : null;
    if ($stamp_path && file_exists($full_stamp_path)) {
        $stamp_ext = strtolower(pathinfo($stamp_path, PATHINFO_EXTENSION));
        if ($stamp_ext === 'png' && !$has_gd && !$has_imagick) {
            $debug_messages[] = "Stamp skipped: PNG ($stamp_path) requires GD or Imagick for alpha channel";
            $company_stamp = 'assets/images/stamp-placeholder.jpg';
        } else {
            $company_stamp = $stamp_path;
            $debug_messages[] = "Stamp found: $company_stamp";
        }
    } else {
        $debug_messages[] = "Stamp not found: path=" . ($stamp_path ?? 'none');
    }
} else {
    $debug_messages[] = "Stamp query failed: " . $conn->error;
}
$stmt->close();

// Generate PDF if form submitted
$error_message = null;
$pdf_path = "$letters_dir/surveyor_dashboard_report_" . time() . ".pdf";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_messages[] = "POST request received, attempting PDF generation";
    try {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LIMS');
        $pdf->SetTitle($translations[$lang]['surveyor_dashboard_report'] ?? 'Surveyor Dashboard Report');
        $pdf->SetMargins(15, 40, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Header: Logo
        if ($logo_source && file_exists($logo_source)) {
            try {
                $pdf->Image($logo_source, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                $debug_messages[] = "Logo added to header: $logo_source";
            } catch (Exception $e) {
                $debug_messages[] = "Failed to add logo to header: " . $e->getMessage();
            }
        } else {
            $debug_messages[] = "Logo skipped: File missing: $logo_source";
        }

        // Header: Stamp
        $stamp_source = realpath(dirname(__FILE__) . '/../../' . $company_stamp);
        if ($stamp_source && file_exists($stamp_source)) {
            try {
                $pdf->StartTransform();
                $pdf->Rotate(-45, 170, 30);
                $pdf->Image($stamp_source, 150, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                $pdf->StopTransform();
                $debug_messages[] = "Stamp added to header: $stamp_source";
            } catch (Exception $e) {
                $debug_messages[] = "Failed to add stamp to header: " . $e->getMessage();
            }
        } else {
            $debug_messages[] = "Stamp skipped: File missing: $stamp_source";
        }

        // Header: Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(192, 57, 43);
        $pdf->SetXY(15, 45);
        $pdf->MultiCell(180, 0, $translations[$lang]['oromia_regional_government'] ?? 'Bulchinsa Mootummaa Naannoo Oromiyaatti', 0, 'C');
        $pdf->SetXY(15, 55);
        $pdf->MultiCell(180, 0, $translations[$lang]['surveyor_dashboard_report'] ?? 'Surveyor Dashboard Report', 0, 'C');

        // Watermark
        $pdf->StartTransform();
        $pdf->SetAlpha(0.3);
        $pdf->SetFont('helvetica', 'B', 60);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Rotate(45, 105, 148.5);
        $pdf->Text(105, 148.5, 'COPY');
        $pdf->StopTransform();
        $pdf->SetAlpha(1.0);

        // Filters Summary
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->SetXY(15, 65);
        $filter_text = $translations[$lang]['filters_applied'] ?? 'Filters Applied:';
        if ($start_date && $end_date) {
            $filter_text .= " " . ($translations[$lang]['date_range'] ?? 'Date Range') . ": $start_date - $end_date";
        } else {
            $filter_text .= " " . ($translations[$lang]['no_filters'] ?? 'None');
        }
        $pdf->MultiCell(180, 0, $filter_text, 0, 'L');

        // Metrics Table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, 75);
        $pdf->Write(0, $translations[$lang]['dashboard_metrics'] ?? 'Dashboard Metrics');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, 85);
        $pdf->Cell(100, 10, $translations[$lang]['metric'] ?? 'Metric', 1, 0, 'C');
        $pdf->Cell(80, 10, $translations[$lang]['value'] ?? 'Value', 1, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $y = 95;
        $metrics = [
            ['name' => $translations[$lang]['total_cases'] ?? 'Total Cases', 'value' => $total_cases],
            ['name' => $translations[$lang]['recent_cases'] ?? 'Recent Cases', 'value' => $recent_cases],
            ['name' => $translations[$lang]['assigned_cases'] ?? 'Assigned Cases', 'value' => $assigned_cases],
            ['name' => $translations[$lang]['new_cases'] ?? 'New Cases', 'value' => $new_cases],
            ['name' => $translations[$lang]['pending_cases'] ?? 'Pending Cases', 'value' => $pending_cases],
            ['name' => $translations[$lang]['completed_cases'] ?? 'Completed Cases', 'value' => $completed_cases],
            ['name' => $translations[$lang]['provided_parcels'] ?? 'Provided Parcels', 'value' => $provided_parcels],
            ['name' => $translations[$lang]['ownership_changes'] ?? 'Ownership Changes', 'value' => $ownership_changes],
            ['name' => $translations[$lang]['pending_requests'] ?? 'Pending Split Requests', 'value' => $pending_requests],
            ['name' => $translations[$lang]['approved_requests'] ?? 'Approved Split Requests', 'value' => $approved_requests]
        ];
        foreach ($metrics as $metric) {
            if ($y > 250) {
                $pdf->AddPage();
                if ($logo_source && file_exists($logo_source)) {
                    try {
                        $pdf->Image($logo_source, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                        $debug_messages[] = "Logo added to new page header: $logo_source";
                    } catch (Exception $e) {
                        $debug_messages[] = "Failed to add logo to new page header: " . $e->getMessage();
                    }
                }
                if ($stamp_source && file_exists($stamp_source)) {
                    try {
                        $pdf->StartTransform();
                        $pdf->Rotate(-45, 170, 30);
                        $pdf->Image($stamp_source, 150, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                        $pdf->StopTransform();
                        $debug_messages[] = "Stamp added to new page header: $stamp_source";
                    } catch (Exception $e) {
                        $debug_messages[] = "Failed to add stamp to new page header: " . $e->getMessage();
                    }
                }
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->SetTextColor(192, 57, 43);
                $pdf->SetXY(15, 45);
                $pdf->MultiCell(180, 0, $translations[$lang]['oromia_regional_government'] ?? 'Bulchinsa Mootummaa Naannoo Oromiyaatti', 0, 'C');
                $pdf->SetXY(15, 55);
                $pdf->MultiCell(180, 0, $translations[$lang]['surveyor_dashboard_report'] ?? 'Surveyor Dashboard Report', 0, 'C');
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetXY(15, 85);
                $pdf->Cell(100, 10, $translations[$lang]['metric'] ?? 'Metric', 1, 0, 'C');
                $pdf->Cell(80, 10, $translations[$lang]['value'] ?? 'Value', 1, 1, 'C');
                $pdf->SetFont('helvetica', '', 10);
            }
            $pdf->SetXY(15, $y);
            $pdf->Cell(100, 10, htmlspecialchars($metric['name']), 1, 0, 'L');
            $pdf->Cell(80, 10, htmlspecialchars($metric['value']), 1, 1, 'C');
            $y += 10;
        }

        // Chart Data Summary
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, $y + 10);
        $pdf->Write(0, $translations[$lang]['chart_data'] ?? 'Chart Data Summary');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, $y + 20);
        $pdf->Cell(100, 10, $translations[$lang]['category'] ?? 'Category', 1, 0, 'C');
        $pdf->Cell(80, 10, $translations[$lang]['count'] ?? 'Count', 1, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $y += 30;
        $chart_data = [
            ['name' => $translations[$lang]['assigned_cases'] ?? 'Assigned Cases', 'count' => $status_counts['assigned']],
            ['name' => $translations[$lang]['pending_cases'] ?? 'Pending Cases', 'count' => $status_counts['pending']],
            ['name' => $translations[$lang]['completed_cases'] ?? 'Approved Cases', 'count' => $status_counts['approved']],
            ['name' => $translations[$lang]['pending_requests'] ?? 'Pending Split Requests', 'count' => $request_status_counts['pending_requests']],
            ['name' => $translations[$lang]['approved_requests'] ?? 'Approved Split Requests', 'count' => $request_status_counts['approved_requests']],
            ['name' => $translations[$lang]['provided_parcels'] ?? 'Provided Parcels', 'count' => $parcel_counts['provided']]
        ];
        foreach ($chart_data as $data) {
            if ($y > 250) {
                $pdf->AddPage();
                if ($logo_source && file_exists($logo_source)) {
                    try {
                        $pdf->Image($logo_source, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                        $debug_messages[] = "Logo added to new page header: $logo_source";
                    } catch (Exception $e) {
                        $debug_messages[] = "Failed to add logo to new page header: " . $e->getMessage();
                    }
                }
                if ($stamp_source && file_exists($stamp_source)) {
                    try {
                        $pdf->StartTransform();
                        $pdf->Rotate(-45, 170, 30);
                        $pdf->Image($stamp_source, 150, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                        $pdf->StopTransform();
                        $debug_messages[] = "Stamp added to new page header: $stamp_source";
                    } catch (Exception $e) {
                        $debug_messages[] = "Failed to add stamp to new page header: " . $e->getMessage();
                    }
                }
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->SetTextColor(192, 57, 43);
                $pdf->SetXY(15, 45);
                $pdf->MultiCell(180, 0, $translations[$lang]['oromia_regional_government'] ?? 'Bulchinsa Mootummaa Naannoo Oromiyaatti', 0, 'C');
                $pdf->SetXY(15, 55);
                $pdf->MultiCell(180, 0, $translations[$lang]['surveyor_dashboard_report'] ?? 'Surveyor Dashboard Report', 0, 'C');
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetXY(15, 85);
                $pdf->Cell(100, 10, $translations[$lang]['category'] ?? 'Category', 1, 0, 'C');
                $pdf->Cell(80, 10, $translations[$lang]['count'] ?? 'Count', 1, 1, 'C');
                $pdf->SetFont('helvetica', '', 10);
                $y = 95;
            }
            $pdf->SetXY(15, $y);
            $pdf->Cell(100, 10, htmlspecialchars($data['name']), 1, 0, 'L');
            $pdf->Cell(80, 10, htmlspecialchars($data['count']), 1, 1, 'C');
            $y += 10;
        }

        // Signatures
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, $y + 20);
        $pdf->Write(0, $translations[$lang]['signatures'] ?? 'Signatures');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(15, $y + 30);
        $pdf->MultiCell(180, 0, ($translations[$lang]['prepared_by'] ?? 'Prepared By') . ': ' . ($translations[$lang]['surveyor'] ?? 'Surveyor'), 0, 'L');
        $pdf->SetXY(15, $y + 40);
        $pdf->MultiCell(180, 0, ($translations[$lang]['authorized_by'] ?? 'Authorized By') . ': ' . ($translations[$lang]['land_management'] ?? 'Land Management Department'), 0, 'L');

        // Output PDF
        $debug_messages[] = "Attempting to save PDF to: $pdf_path";
        $pdf->Output($pdf_path, 'F');

        if (!file_exists($pdf_path)) {
            $debug_messages[] = "Failed to generate PDF: File not found at $pdf_path";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to generate PDF\n", FILE_APPEND);
            $error_message = $translations[$lang]['pdf_generation_failed'] ?? "Failed to generate report.";
        } else {
            $debug_messages[] = "PDF generated successfully: $pdf_path";
        }
    } catch (Exception $e) {
        $debug_messages[] = "PDF generation failed: " . $e->getMessage();
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - PDF generation failed: " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = ($translations[$lang]['pdf_generation_failed'] ?? "Failed to generate report: ") . htmlspecialchars($e->getMessage());
    }
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - generate_surveyor_report.php: messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['surveyor_dashboard_report'] ?? 'Surveyor Dashboard Report'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            width: 100%;
            max-width: 800px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .btn-primary {
            background: #1e40af;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: #495057;
        }
        .pdf-container {
            width: 100%;
            max-width: 800px;
            height: 80vh;
            border: 1px solid #ccc;
            margin-top: 20px;
            display: <?php echo file_exists($pdf_path) ? 'block' : 'none'; ?>;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #856404;
            width: 100%;
            max-width: 800px;
        }
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            color: #721c24;
            width: 100%;
            max-width: 800px;
            border-radius: 5px;
        }
        @media print {
            .form-container, .debug-info, .error-message, .btn-primary, .btn-secondary {
                display: none;
            }
            .pdf-container {
                border: none;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' && !empty($debug_messages)): ?>
        <div class="debug-info">
            <h4><?php echo $translations[$lang]['debug_info'] ?? 'Debug Information'; ?></h4>
            <?php foreach ($debug_messages as $msg): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="error-message">
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <h2><?php echo $translations[$lang]['generate_surveyor_report'] ?? 'Generate Surveyor Dashboard Report'; ?></h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?lang=' . $lang); ?>">
            <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
            <div class="row">
                <div class="col-md-6">
                    <label for="start_date" class="form-label"><?php echo $translations[$lang]['start_date'] ?? 'Start Date'; ?></label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label for="end_date" class="form-label"><?php echo $translations[$lang]['end_date'] ?? 'End Date'; ?></label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                </div>
                <div class="col-md-12 mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> <?php echo $translations[$lang]['generate'] ?? 'Generate'; ?></button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?lang=' . $lang); ?>'"><i class="fas fa-undo"></i> <?php echo $translations[$lang]['reset'] ?? 'Reset'; ?></button>
                </div>
            </div>
        </form>
    </div>

    <?php if (file_exists($pdf_path)): ?>
        <div class="pdf-container">
            <embed src="<?php echo BASE_URL . '/letters/' . basename($pdf_path); ?>" type="application/pdf" width="100%" height="100%">
        </div>
        <button class="btn btn-primary mt-3" onclick="printPDF()"><i class="fas fa-print"></i> <?php echo $translations[$lang]['print'] ?? 'Print'; ?></button>
    <?php endif; ?>

    <script>
        function printPDF() {
            const pdfUrl = '<?php echo file_exists($pdf_path) ? BASE_URL . '/letters/' . basename($pdf_path) : ''; ?>';
            if (pdfUrl) {
                const win = window.open(pdfUrl, '_blank');
                if (win) {
                    win.focus();
                    win.onload = function() {
                        win.print();
                    };
                } else {
                    alert('<?php echo $translations[$lang]['allow_popups'] ?? 'Please allow popups for this website.'; ?>');
                }
            }
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>