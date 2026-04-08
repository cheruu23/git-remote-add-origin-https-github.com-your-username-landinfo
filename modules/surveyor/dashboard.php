<?php
ob_start();
require_once '../../includes/init.php';

redirectIfNotLoggedIn();
if (!isSurveyor()) {
    logAction('unauthorized_access', 'Attempted access to surveyor dashboard', 'warning', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

// Validate and sanitize lang parameter
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

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Fetch dashboard metrics
$sql_total = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param('i', $user_id);
$total_cases = 0;
if ($stmt_total && $stmt_total->execute()) {
    $total_cases = $stmt_total->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_total_cases', 'Fetched total cases count: ' . $total_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Total cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_total->close();

$sql_recent = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND created_at >= NOW() - INTERVAL 30 DAY";
$stmt_recent = $conn->prepare($sql_recent);
$stmt_recent->bind_param('i', $user_id);
$recent_cases = 0;
if ($stmt_recent && $stmt_recent->execute()) {
    $recent_cases = $stmt_recent->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_recent_cases', 'Fetched recent cases count: ' . $recent_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Recent cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_recent->close();

$sql_assigned = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND status = 'Assigned'";
$stmt_assigned = $conn->prepare($sql_assigned);
$stmt_assigned->bind_param('i', $user_id);
$assigned_cases = 0;
if ($stmt_assigned && $stmt_assigned->execute()) {
    $assigned_cases = $stmt_assigned->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_assigned_cases', 'Fetched assigned cases count: ' . $assigned_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Assigned cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_assigned->close();

$sql_new = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND viewed = '0'";
$stmt_new = $conn->prepare($sql_new);
$stmt_new->bind_param('i', $user_id);
$new_cases = 0;
if ($stmt_new && $stmt_new->execute()) {
    $new_cases = $stmt_new->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_new_cases', 'Fetched new cases count: ' . $new_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'New cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_new->close();

$sql_pending = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND investigation_status = 'InProgress'";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param('i', $user_id);
$pending_cases = 0;
if ($stmt_pending && $stmt_pending->execute()) {
    $pending_cases = $stmt_pending->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_pending_cases', 'Fetched pending cases count: ' . $pending_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Pending cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_pending->close();

$sql_completed = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND investigation_status = 'Approved'";
$stmt_completed = $conn->prepare($sql_completed);
$stmt_completed->bind_param('i', $user_id);
$completed_cases = 0;
if ($stmt_completed && $stmt_completed->execute()) {
    $completed_cases = $stmt_completed->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_completed_cases', 'Fetched completed cases count: ' . $completed_cases, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Completed cases query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_completed->close();

$sql_parcels = "SELECT COUNT(*) as count FROM land_registration lr JOIN cases c ON lr.id = c.land_id WHERE lr.has_parcel = 1 AND c.assigned_to = ?";
$stmt_parcels = $conn->prepare($sql_parcels);
$stmt_parcels->bind_param('i', $user_id);
$provided_parcels = 0;
if ($stmt_parcels && $stmt_parcels->execute()) {
    $provided_parcels = $stmt_parcels->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_provided_parcels', 'Fetched provided parcels count: ' . $provided_parcels, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Provided parcels count query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_parcels->close();

$sql_changes = "SELECT COUNT(*) as count FROM ownership_transfers WHERE changed_by = ?";
$stmt_changes = $conn->prepare($sql_changes);
$stmt_changes->bind_param('i', $user_id);
$ownership_changes = 0;
if ($stmt_changes && $stmt_changes->execute()) {
    $ownership_changes = $stmt_changes->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_ownership_changes', 'Fetched ownership changes count: ' . $ownership_changes, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Ownership changes query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_changes->close();

$sql_pending_requests = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = 'Pending'";
$stmt_pending_requests = $conn->prepare($sql_pending_requests);
$stmt_pending_requests->bind_param('i', $user_id);
$pending_requests = 0;
if ($stmt_pending_requests && $stmt_pending_requests->execute()) {
    $pending_requests = $stmt_pending_requests->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_pending_requests', 'Fetched pending split requests count: ' . $pending_requests, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Pending split requests query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_pending_requests->close();

$sql_approved_requests = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = 'Approved'";
$stmt_approved_requests = $conn->prepare($sql_approved_requests);
$stmt_approved_requests->bind_param('i', $user_id);
$approved_requests = 0;
if ($stmt_approved_requests && $stmt_approved_requests->execute()) {
    $approved_requests = $stmt_approved_requests->get_result()->fetch_assoc()['count'] ?? 0;
    logAction('fetch_approved_requests', 'Fetched approved split requests count: ' . $approved_requests, 'info', ['user_id' => $user_id]);
} else {
    logAction('query_failed', 'Approved split requests query failed: ' . $conn->error, 'error', ['user_id' => $user_id]);
}
$stmt_approved_requests->close();

// Handle chart filter form submission
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$date_filter = '';
$bind_params = [];
$param_types = '';

if ($start_date && $end_date) {
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($start && $end && $start <= $end) {
        $date_filter = " AND created_at BETWEEN ? AND ?";
        $bind_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        $param_types = 'ss';
        $debug_messages[] = "Date filter applied: $start_date to $end_date";
    } else {
        logAction('invalid_date_filter', 'Invalid date range provided: ' . $start_date . ' to ' . $end_date, 'warning', ['user_id' => $user_id]);
        $start_date = $end_date = null;
        $debug_messages[] = "Invalid date range, filter ignored";
    }
} else {
    $debug_messages[] = "No date filter applied";
}

// Fetch status counts for Case Status Chart
$statuses = ['Assigned', 'InProgress', 'Approved'];
$status_counts = ['assigned' => 0, 'pending' => 0, 'approved' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM cases WHERE assigned_to = ? AND (status = ? OR investigation_status = ?)" . $date_filter;
    $debug_messages[] = "Case status counts SQL: $sql";
    $stmt = $conn->prepare($sql);
    foreach ($statuses as $index => $status) {
        $params = array_merge([$user_id, $status, $status], $bind_params);
        $types = 'iss' . $param_types;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $key = $index === 0 ? 'assigned' : ($index === 1 ? 'pending' : 'approved');
        $status_counts[$key] = $count;
        $debug_messages[] = "Case status count for $status: $count";
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Status count query failed: ' . $e->getMessage(), 'error', ['user_id' => $user_id]);
    $debug_messages[] = "Case status count query failed: " . $e->getMessage();
}

// Fetch parcels for Provided Parcels Chart
$parcel_counts = ['provided' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM land_registration lr JOIN cases c ON lr.id = c.land_id WHERE lr.has_parcel = 1 AND c.assigned_to = ?" . $date_filter;
    $debug_messages[] = "Parcel counts SQL: $sql";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$user_id], $bind_params);
    $types = 'i' . $param_types;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $parcel_counts['provided'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $debug_messages[] = "Parcel count: " . $parcel_counts['provided'];
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Parcel count query failed: ' . $e->getMessage(), 'error', ['user_id' => $user_id]);
    $debug_messages[] = "Parcel count query failed: " . $e->getMessage();
}

// Fetch split request status counts (unchanged, not used in charts per request)
$request_status_counts = ['pending_requests' => 0, 'approved_requests' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM split_requests WHERE surveyor_id = ? AND status = ?" . $date_filter;
    $stmt = $conn->prepare($sql);
    foreach (['Pending', 'Approved'] as $index => $status) {
        $params = array_merge([$user_id, $status], $bind_params);
        $types = 'is' . $param_types;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $request_status_counts[$index === 0 ? 'pending_requests' : 'approved_requests'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Split request status count query failed: ' . $e->getMessage(), 'error', ['user_id' => $user_id]);
}

// Debug chart data
logAction('chart_data', 'Case Status Counts: ' . json_encode($status_counts), 'info', ['user_id' => $user_id]);
logAction('chart_data', 'Parcel Counts: ' . json_encode($parcel_counts), 'info', ['user_id' => $user_id]);

// Log debug info to file
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - surveyor_dashboard.php: messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['surveyor_dashboard_title'] ?? 'Surveyor Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: #fff;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            font-size: 1.2rem;
            font-weight: 600;
            background: linear-gradient(90deg, #1e40af, #3b82f6);
            color: #fff;
            padding: 15px;
            border-radius: 15px 15px 0 0;
        }
        .card-body {
            padding: 20px;
            text-align: center;
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .card-text {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #1e40af;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .chart-container {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
            min-height: 300px;
        }
        canvas {
            width: 100% !important;
            max-width: 600px;
            margin: 0 auto;
        }
        .no-data-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            color: #6b7280;
            text-align: center;
        }
        .form-container {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 5px;
            padding: 8px;
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
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
            border-radius: 5px;
        }
        @media (max-width: 992px) {
            .content.collapsed {
                margin-left: 60px;
            }
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .card-body {
                padding: 15px;
            }
            .card-title {
                font-size: 1.4rem;
            }
            canvas {
                max-width: 100%;
            }
            .chart-container {
                min-height: 250px;
            }
        }
        @media (max-width: 576px) {
            .card-text {
                font-size: 0.9rem;
            }
            .btn-primary {
                font-size: 0.9rem;
                padding: 6px 12px;
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

    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['surveyor_dashboard_title'] ?? 'Surveyor Dashboard'; ?></h2>
            <div class="row mt-4 g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['recent_cases'] ?? 'Recent Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-file-alt fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($recent_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['recent_cases'] ?? 'View cases from last 30 days.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/recent_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['assigned_cases'] ?? 'Assigned Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-tasks fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($assigned_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_cases'] ?? 'View cases assigned to you.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/assigned_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['new_cases'] ?? 'New Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-plus-circle fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($new_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_cases'] ?? 'View new cases assigned to you.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/new_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['pending_cases'] ?? 'Pending Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-hourglass-half fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($pending_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_cases'] ?? 'View pending cases assigned to you.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/pending_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['completed_cases'] ?? 'Completed Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($completed_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_cases'] ?? 'View completed cases.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/completed_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['provided_parcels'] ?? 'Provided Parcels'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-map fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($provided_parcels); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['provided_parcels'] ?? 'View parcels you provided.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/provided_parcels.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['ownership_changes'] ?? 'Ownership Changes'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-exchange-alt fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($ownership_changes); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_ownership_changes'] ?? 'View ownership changes you made.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/ownership_changes.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['generate_report'] ?? 'Generate Report'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-file-pdf fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo $translations[$lang]['land_registration'] ?? 'Land Registration'; ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['generate_report'] ?? 'Generate report.'; ?></p>
                            <a href="generate_surveyor_report.php?lang=<?php echo htmlspecialchars($lang); ?>&start_date=<?php echo htmlspecialchars($start_date ?? ''); ?>&end_date=<?php echo htmlspecialchars($end_date ?? ''); ?>" class="btn btn-primary"><i class="fas fa-download"></i> <?php echo $translations[$lang]['generate'] ?? 'Generate'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['pending_requests'] ?? 'Pending Split Requests'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-hourglass-start fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($pending_requests); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['pending_requests'] ?? 'View pending split requests.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/pending_requests.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['approved_requests'] ?? 'Approved Split Requests'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-check-double fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($approved_requests); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['approved_requests'] ?? 'View approved split requests.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/surveyor/approved_requests.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-container">
                <h3><?php echo $translations[$lang]['filter_charts'] ?? 'Filter Charts'; ?></h3>
                <form method="POST">
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                    <div class="row">
                        <div class="col-md-5">
                            <label for="start_date" class="form-label"><?php echo $translations[$lang]['start_date'] ?? 'Start Date'; ?></label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="end_date" class="form-label"><?php echo $translations[$lang]['end_date'] ?? 'End Date'; ?></label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> <?php echo $translations[$lang]['apply'] ?? 'Apply'; ?></button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?lang=' . $lang); ?>'"><i class="fas fa-undo"></i> <?php echo $translations[$lang]['reset'] ?? 'Reset'; ?></button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['case_status_distribution'] ?? 'Case Status Distribution'; ?></h3>
                        <canvas id="caseStatusChart"></canvas>
                        <?php if (array_sum($status_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No case status data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['provided_parcels'] ?? 'Provided Parcels'; ?></h3>
                        <canvas id="parcelChart"></canvas>
                        <?php if ($parcel_counts['provided'] === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No parcel data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Chart.js configuration
        const chartColors = ['#b91d47', '#00aba9', '#2b5797'];

        // Case Status Pie Chart
        const statusXValues = [
            '<?php echo $translations[$lang]['assigned_cases'] ?? 'Assigned'; ?>',
            '<?php echo $translations[$lang]['pending_cases'] ?? 'Pending'; ?>',
            '<?php echo $translations[$lang]['completed_cases'] ?? 'Approved'; ?>'
        ];
        const statusYValues = [
            <?php echo $status_counts['assigned']; ?>,
            <?php echo $status_counts['pending']; ?>,
            <?php echo $status_counts['approved']; ?>
        ];
        const statusTotal = statusYValues.reduce((a, b) => a + b, 0);
        const statusConfig = {
            labels: statusTotal === 0 ? ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'] : statusXValues,
            datasets: [{
                data: statusTotal === 0 ? [1] : statusYValues,
                backgroundColor: statusTotal === 0 ? ['#cccccc'] : chartColors,
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        };

        new Chart(document.getElementById('caseStatusChart'), {
            type: 'pie',
            data: statusConfig,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 12 }
                        }
                    },
                    title: {
                        display: true,
                        text: '<?php echo $translations[$lang]['case_status_distribution'] ?? 'Case Status Distribution'; ?>',
                        font: { size: 16 }
                    }
                }
            }
        });

        // Provided Parcels Pie Chart
        const parcelXValues = ['<?php echo $translations[$lang]['provided_parcels'] ?? 'Provided Parcels'; ?>'];
        const parcelYValues = [<?php echo $parcel_counts['provided']; ?>];
        const parcelTotal = parcelYValues[0];
        const parcelConfig = {
            labels: parcelTotal === 0 ? ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'] : parcelXValues,
            datasets: [{
                data: parcelTotal === 0 ? [1] : parcelYValues,
                backgroundColor: parcelTotal === 0 ? ['#cccccc'] : [chartColors[0]],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        };

        new Chart(document.getElementById('parcelChart'), {
            type: 'pie',
            data: parcelConfig,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 12 }
                        }
                    },
                    title: {
                        display: true,
                        text: '<?php echo $translations[$lang]['provided_parcels'] ?? 'Provided Parcels'; ?>',
                        font: { size: 16 }
                    }
                }
            }
        });

        // Debug chart data
        console.log('Status Chart Data:', statusConfig);
        console.log('Parcel Chart Data:', parcelConfig);
    </script>
</body>
</html>
<?php
ob_end_flush();
?>