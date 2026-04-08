<?php
ob_start();
require_once '../../includes/init.php';
require_once '../../vendor/autoload.php'; // Composer autoloader for TCPDF

// Redirect if not logged in or not authorized
function isAuthorizedUser() {
    return isset($_SESSION['user']['role']) && 
           in_array($_SESSION['user']['role'], ['manager', 'record_officer', 'surveyor']);
}
if (!isAuthorizedUser()) {
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}
// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $debug_messages[] = "Database connection successful";
} catch (PDOException $e) {
    $debug_messages[] = "Database connection failed: " . $e->getMessage();
    error_log("Database connection failed: " . $e->getMessage());
    die($translations[$lang]['db_connection_failed'] ?? "Database connection error: Please try again later.");
}

$user_id = $_SESSION['user']['id'];
$debug_messages = [];
$debug_log = dirname(__FILE__) . '/debug.log';

// Check for GD or Imagick extension
$has_gd = extension_loaded('gd');
$has_imagick = extension_loaded('imagick');
$debug_messages[] = "GD extension: " . ($has_gd ? "enabled" : "disabled");
$debug_messages[] = "Imagick extension: " . ($has_imagick ? "enabled" : "disabled");
if (!$has_gd && !$has_imagick) {
    $debug_messages[] = "Warning: Neither GD nor Imagick is enabled. PNG images with alpha channels may be skipped.";
}

// Handle filter form submission or URL parameters
$start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? $_GET['end_date'] ?? null;
$land_type = $_POST['land_type'] ?? null;
$gender = $_POST['gender'] ?? null;
$village = $_POST['village'] ?? null;
$land_service = $_POST['land_service'] ?? null;

$debug_messages[] = "Form data: start_date=$start_date, end_date=$end_date, land_type=$land_type, gender=$gender, village=$village, land_service=$land_service";

$where_clauses = ["status = 'Approved'"];
$bind_params = [];
$param_types = '';

if ($start_date && $end_date) {
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($start && $end && $start <= $end) {
        $where_clauses[] = "created_at BETWEEN ? AND ?";
        $bind_params[] = $start_date . ' 00:00:00';
        $bind_params[] = $end_date . ' 23:59:59';
        $param_types .= 'ss';
    } else {
        $debug_messages[] = "Invalid date range: $start_date to $end_date";
        $start_date = $end_date = null;
    }
}

if ($land_type) {
    $where_clauses[] = "land_type = ?";
    $bind_params[] = $land_type;
    $param_types .= 's';
}
if ($gender) {
    $where_clauses[] = "gender = ?";
    $bind_params[] = $gender;
    $param_types .= 's';
}
if ($village) {
    $where_clauses[] = "village = ?";
    $bind_params[] = $village;
    $param_types .= 's';
}
if ($land_service) {
    $where_clauses[] = "parcel_land_service = ?";
    $bind_params[] = $land_service;
    $param_types .= 's';
}

$where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
$debug_messages[] = "SQL query: SELECT ... FROM land_registration$where_sql";

// Fetch land registration records
$records = [];
try {
    $sql = "SELECT id, owner_name, first_name, middle_name, gender, owner_phone, land_type, village, zone, 
                   block_number, parcel_number, effective_date, area, purpose, status, parcel_land_area, 
                   parcel_land_service, parcel_registration_number
            FROM land_registration" . $where_sql;
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        foreach ($bind_params as $index => $value) {
            $stmt->bindValue($index + 1, $value, strpos($param_types, 's', $index) !== false ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $debug_messages[] = "Bound parameters: " . json_encode($bind_params);
    }
    $stmt->execute();
    $records = $stmt->fetchAll();
    if (empty($records)) {
        $debug_messages[] = "No records found for filters";
    } else {
        $debug_messages[] = "Found " . count($records) . " records";
    }
} catch (PDOException $e) {
    $debug_messages[] = "Records query failed: " . $e->getMessage();
    error_log("Records query failed: " . $e->getMessage());
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Records query failed: " . $e->getMessage() . "\n", FILE_APPEND);
    die($translations[$lang]['query_failed'] ?? "Error fetching records: Please try again later.");
}

// Fetch available filter options
$land_types = ['dhaala', 'lease_land', 'bita_fi_gurgurtaa', 'miritti', 'caalbaasii'];
$genders = ['Dhiira', 'Dubartii'];
$villages = [];
$land_services = ['lafa daldalaa', 'lafa mana jireenyaa'];
try {
    $sql = "SELECT DISTINCT village FROM land_registration WHERE village IS NOT NULL AND village != '' ORDER BY village";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $villages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $debug_messages[] = "Fetched " . count($villages) . " villages";
} catch (PDOException $e) {
    $debug_messages[] = "Villages query failed: " . $e->getMessage();
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Villages query failed: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Define BASE_URL and letters directory
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
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

// Get logo and stamp (aligned with view_parcel.php, using dirname(__FILE__))
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$logo_source = realpath(dirname(__FILE__) . '/../../' . $navbar_logo);
if (!$logo_source || !file_exists($logo_source)) {
    $debug_messages[] = "Logo not found: path=" . ($logo_source ?? $navbar_logo);
    $navbar_logo = 'assets/images/default_navbar_logo.png';
    $logo_source = realpath(dirname(__FILE__) . '/../../' . $navbar_logo);
}
$debug_messages[] = "Logo set to: $navbar_logo";

$company_stamp = 'assets/images/stamp-placeholder.png';
try {
    $sql = "SELECT image_path FROM company_stamps ORDER BY uploaded_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stamp = $stmt->fetch();
    $stamp_path = $stamp ? $stamp['image_path'] : null;
    $full_stamp_path = $stamp_path ? realpath(dirname(__FILE__) . '/../../' . $stamp_path) : null;
    if ($stamp_path && file_exists($full_stamp_path)) {
        $company_stamp = $stamp_path;
        $debug_messages[] = "Stamp found: $company_stamp";
    } else {
        $debug_messages[] = "Stamp not found: path=" . ($stamp_path ?? 'none');
    }
} catch (PDOException $e) {
    $debug_messages[] = "Stamp query failed: " . $e->getMessage();
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Stamp query failed: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Generate PDF if form submitted
$error_message = null;
$pdf_path = "$letters_dir/land_registration_report_" . time() . ".pdf";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_messages[] = "POST request received, attempting PDF generation";
    try {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('LIMS');
        $pdf->SetTitle($translations[$lang]['land_registration_report'] ?? 'Land Registration Report');
        $pdf->SetMargins(15, 40, 15); // Increased top margin for header
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        // Header: Logo
        if ($logo_source && file_exists($logo_source) && ($has_gd || $has_imagick)) {
            try {
                $pdf->Image($logo_source, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                $debug_messages[] = "Logo added to header: $logo_source";
            } catch (Exception $e) {
                $debug_messages[] = "Failed to add logo to header: " . $e->getMessage();
            }
        } else {
            $debug_messages[] = "Logo skipped: " . ($logo_source ? "Missing GD/Imagick for PNG" : "File missing: $logo_source");
        }

        // Header: Stamp
        $stamp_source = realpath(dirname(__FILE__) . '/../../' . $company_stamp);
        if ($stamp_source && file_exists($stamp_source) && ($has_gd || $has_imagick)) {
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
            $debug_messages[] = "Stamp skipped: " . ($stamp_source ? "Missing GD/Imagick for PNG" : "File missing: $stamp_source");
        }

        // Header: Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(192, 57, 43);
        $pdf->SetXY(15, 45);
        $pdf->MultiCell(180, 0, $translations[$lang]['oromia_regional_government'] ?? 'Bulchinsa Mootummaa Naannoo Oromiyaatti', 0, 'C');
        $pdf->SetXY(15, 55);
        $pdf->MultiCell(180, 0, $translations[$lang]['land_registration_report'] ?? 'Land Registration Report', 0, 'C');

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
        }
        if ($land_type) {
            $filter_text .= ", " . ($translations[$lang]['land_type'] ?? 'Land Type') . ": " . ($translations[$lang][$land_type] ?? $land_type);
        }
        if ($gender) {
            $filter_text .= ", " . ($translations[$lang]['gender'] ?? 'Gender') . ": " . ($translations[$lang][$gender] ?? $gender);
        }
        if ($village) {
            $filter_text .= ", " . ($translations[$lang]['village'] ?? 'Village') . ": $village";
        }
        if ($land_service) {
            $filter_text .= ", " . ($translations[$lang]['land_service'] ?? 'Land Service') . ": " . ($translations[$lang][$land_service] ?? $land_service);
        }
        $pdf->MultiCell(180, 0, $filter_text, 0, 'L');

        // Table Header
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(15, 75);
        $pdf->Cell(20, 10, $translations[$lang]['id'] ?? 'ID', 1, 0, 'C');
        $pdf->Cell(50, 10, $translations[$lang]['owner_name'] ?? 'Owner Name', 1, 0, 'C');
        $pdf->Cell(30, 10, $translations[$lang]['land_type'] ?? 'Land Type', 1, 0, 'C');
        $pdf->Cell(30, 10, $translations[$lang]['village'] ?? 'Village', 1, 0, 'C');
        $pdf->Cell(25, 10, $translations[$lang]['area'] ?? 'Area (m²)', 1, 0, 'C');
        $pdf->Cell(25, 10, $translations[$lang]['status'] ?? 'Status', 1, 1, 'C');

        // Table Data
        $pdf->SetFont('helvetica', '', 10);
        $y = 85;
        if (empty($records)) {
            $pdf->SetXY(15, $y);
            $pdf->MultiCell(180, 10, $translations[$lang]['no_records_found'] ?? 'No records found for the selected filters.', 1, 'C');
            $y += 10;
            $debug_messages[] = "No records to display in PDF";
        } else {
            foreach ($records as $record) {
                if ($y > 250) {
                    $pdf->AddPage();
                    // Re-add header on new page
                    if ($logo_source && file_exists($logo_source) && ($has_gd || $has_imagick)) {
                        try {
                            $pdf->Image($logo_source, 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
                            $debug_messages[] = "Logo added to new page header: $logo_source";
                        } catch (Exception $e) {
                            $debug_messages[] = "Failed to add logo to new page header: " . $e->getMessage();
                        }
                    }
                    if ($stamp_source && file_exists($stamp_source) && ($has_gd || $has_imagick)) {
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
                    $pdf->MultiCell(180, 0, $translations[$lang]['land_registration_report'] ?? 'Land Registration Report', 0, 'C');
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetXY(15, 75);
                    $pdf->Cell(20, 10, $translations[$lang]['id'] ?? 'ID', 1, 0, 'C');
                    $pdf->Cell(50, 10, $translations[$lang]['owner_name'] ?? 'Owner Name', 1, 0, 'C');
                    $pdf->Cell(30, 10, $translations[$lang]['land_type'] ?? 'Land Type', 1, 0, 'C');
                    $pdf->Cell(30, 10, $translations[$lang]['village'] ?? 'Village', 1, 0, 'C');
                    $pdf->Cell(25, 10, $translations[$lang]['area'] ?? 'Area (m²)', 1, 0, 'C');
                    $pdf->Cell(25, 10, $translations[$lang]['status'] ?? 'Status', 1, 1, 'C');
                    $pdf->SetFont('helvetica', '', 10);
                    $y = 85;
                }
                $pdf->SetXY(15, $y);
                $pdf->Cell(20, 10, htmlspecialchars($record['id']), 1, 0, 'C');
                $full_name = trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['owner_name'] ?? ''));
                $pdf->Cell(50, 10, htmlspecialchars($full_name), 1, 0, 'L');
                $land_type_label = $translations[$lang][$record['land_type']] ?? $record['land_type'];
                $pdf->Cell(30, 10, htmlspecialchars($land_type_label), 1, 0, 'C');
                $pdf->Cell(30, 10, htmlspecialchars($record['village'] ?? 'N/A'), 1, 0, 'C');
                $area = $record['parcel_land_area'] ?? $record['area'] ?? 'N/A';
                $pdf->Cell(25, 10, htmlspecialchars($area), 1, 0, 'C');
                $pdf->Cell(25, 10, htmlspecialchars($record['status'] ?? 'N/A'), 1, 1, 'C');
                $y += 10;
            }
        }

        // Signatures
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(15, $y + 20);
        $pdf->Write(0, $translations[$lang]['signatures'] ?? 'Signatures');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(15, $y + 30);
        $pdf->MultiCell(180, 0, ($translations[$lang]['prepared_by'] ?? 'Prepared By') . ': ' . ($translations[$lang]['record_officer'] ?? 'Record Officer'), 0, 'L');
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
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - generate_land_report.php: messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['land_registration_report'] ?? 'Land Registration Report'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: rgb(233, 245, 236);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: Arial, sans-serif;
        }
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
        .form-control, select {
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
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'manager' && !empty($debug_messages)): ?>
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
        <h2><?php echo $translations[$lang]['generate_land_report'] ?? 'Generate Land Registration Report'; ?></h2>
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
                <div class="col-md-6">
                    <label for="land_type" class="form-label"><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?></label>
                    <select id="land_type" name="land_type" class="form-control">
                        <option value=""><?php echo $translations[$lang]['all'] ?? 'All'; ?></option>
                        <?php foreach ($land_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $land_type === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($translations[$lang][$type] ?? $type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label"><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?></label>
                    <select id="gender" name="gender" class="form-control">
                        <option value=""><?php echo $translations[$lang]['all'] ?? 'All'; ?></option>
                        <?php foreach ($genders as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $gender === $g ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($translations[$lang][$g] ?? $g); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="village" class="form-label"><?php echo $translations[$lang]['village'] ?? 'Village'; ?></label>
                    <select id="village" name="village" class="form-control">
                        <option value=""><?php echo $translations[$lang]['all'] ?? 'All'; ?></option>
                        <?php foreach ($villages as $v): ?>
                            <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $village === $v ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="land_service" class="form-label"><?php echo $translations[$lang]['land_service'] ?? 'Land Service'; ?></label>
                    <select id="land_service" name="land_service" class="form-control">
                        <option value=""><?php echo $translations[$lang]['all'] ?? 'All'; ?></option>
                        <?php foreach ($land_services as $service): ?>
                            <option value="<?php echo htmlspecialchars($service); ?>" <?php echo $land_service === $service ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($translations[$lang][$service] ?? $service); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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