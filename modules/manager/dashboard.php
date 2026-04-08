<?php
ob_start();
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    logAction('access_denied', 'Unauthorized access to manager dashboard', 'error');
    die("Access denied!");
}

// Log dashboard access
logAction('manager_dashboard_access', 'Manager accessed the dashboard', 'info');

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info');

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Handle form submission for chart filters
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$date_filter = '';
$bind_params = [];
$param_types = '';

if ($start_date && $end_date) {
    // Validate dates
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($start && $end && $start <= $end) {
        $date_filter = " WHERE created_at BETWEEN ? AND ?";
        $bind_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        $param_types = 'ss';
    } else {
        logAction('invalid_date_filter', 'Invalid date range provided: ' . $start_date . ' to ' . $end_date, 'warning');
        $start_date = $end_date = null; // Reset invalid dates
    }
}
// Fetch count of rejected cases
$sql = "SELECT COUNT(c.id) AS rejected_count 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Rejected'";
$result = $conn->query($sql);
$rejected_count = 0;

if ($result) {
    $row = $result->fetch_assoc();
    $rejected_count = $row['rejected_count'];
    $result->free();
} else {
    error_log("Rejected cases query failed: " . $conn->error);
    $error = $translations[$lang]['fetch_error'];
}
// Fetch count of approved cases
$sql = "SELECT COUNT(c.id) AS approved_count 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Approved'";
$result = $conn->query($sql);
$approved_count = 0;

if ($result) {
    $row = $result->fetch_assoc();
    $approved_count = $row['approved_count'];
    $result->free();
} else {
    error_log("Approved cases query failed: " . $conn->error);
    $error = $translations[$lang]['fetch_error'];
}

// Fetch count of pending split requests (UPDATED)
$pending_split_requests = 0;
$split_requests_error = null;
try {
    // Check if split_requests table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'split_requests'");
    if ($table_check->num_rows === 0) {
        throw new Exception("Table split_requests does not exist");
    }
    $table_check->free();
    
    // Use simple query without date filter to isolate issues
    $sql = "SELECT COUNT(*) as count FROM split_requests WHERE status = 'pending'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $pending_split_requests = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    $split_requests_error = $translations[$lang]['fetch_error'] ?? 'Error fetching split requests';
    logAction('query_failed', 'Pending split requests query failed: ' . $e->getMessage(), 'error');
}

// Fetch status counts
$statuses = ['Confirmed', 'Approved', 'Rejected', 'Pending'];
$status_counts = ['confirmed' => 0, 'approved' => 0, 'rejected' => 0, 'pending' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM cases WHERE status = ?" . ($date_filter ? $date_filter : '');
    $stmt = $conn->prepare($sql);
    foreach ($statuses as $status) {
        $params = array_merge([$status], $bind_params);
        $types = 's' . $param_types;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $status_counts[strtolower($status)] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Status count query failed: ' . $e->getMessage(), 'error');
}

// Total Cases
$total_cases = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM cases" . ($date_filter ? $date_filter : '');
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $total_cases = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Total cases query failed: ' . $e->getMessage(), 'error');
}

// Recent Cases (last 7 days, unaffected by form filter)
$recent_cases = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_cases = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Recent cases query failed: ' . $e->getMessage(), 'error');
}

// Assigned Cases
$assigned_count = 0;
try {
    $sql = "SELECT COUNT(*) as count FROM cases WHERE assigned_to IS NOT NULL" . ($date_filter ? $date_filter : '');
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $assigned_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Assigned cases query failed: ' . $e->getMessage(), 'error');
}

// Recent Case Entries (last 5, unaffected by form filter)
$recent_case_entries = [];
try {
    $stmt = $conn->prepare("
        SELECT c.title, c.status, c.created_at, c.assigned_to, u.full_name
        FROM cases c
        LEFT JOIN users u ON c.assigned_to = u.id
        ORDER BY c.created_at DESC LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_case_entries[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Recent case entries query failed: ' . $e->getMessage(), 'error');
}

// Case Title Distribution
$title_data = [];
try {
    $sql = "SELECT title, COUNT(*) as count FROM cases" . ($date_filter ? $date_filter : '') . " GROUP BY title ORDER BY count DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $title_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Case title query failed: ' . $e->getMessage(), 'error');
}

$title_labels = array_column($title_data, 'title') ?: ['No Data'];
$title_counts = array_column($title_data, 'count') ?: [0];

// Debug logging for chart data
logAction('chart_data', 'Status Counts: ' . json_encode($status_counts), 'info');
logAction('chart_data', 'Title Labels: ' . json_encode($title_labels) . ', Counts: ' . json_encode($title_counts), 'info');

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['manager_dashboard'] ?? 'Manager Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
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
            background: #6b7280;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .chart-container {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        canvas {
            width: 100% !important;
            max-width: 600px;
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
        .table-container {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background: #f1f5f9;
            color: #1f2937;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
        }
        .status-confirmed { color: #14b8a6; }
        .status-approved { color: #22c55e; }
        .status-rejected { color: #ef4444; }
        .status-pending { color: #6b7280; }
        .form-container {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 5px;
            padding: 8px;
            width: 100%;
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .error-text {
            color: #ef4444;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .card {
                margin-bottom: 20px;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .chart-container, .table-container, .form-container {
                padding: 15px;
            }
            .table {
                font-size: 0.9rem;
            }
            canvas {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-4">
            <h2 class="text-center"><?php echo $translations[$lang]['welcome_manager'] ?? 'Welcome, Manager'; ?></h2>
            <div class="row mt-4">
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['total_cases'] ?? 'Total Cases'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $total_cases; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['view_cases_desc'] ?? 'View all cases in the system.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/total_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['assigned_cases'] ?? 'Assigned Cases'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $assigned_count; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['assigned_cases_desc'] ?? 'Cases assigned to team members.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/assigned_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['manage'] ?? 'Manage'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['recent_cases'] ?? 'Recent Cases'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $recent_cases; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['recent_cases_desc'] ?? 'Cases created in the last 7 days.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/view_case.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['rejected_cases'] ?? 'Rejected Cases'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $rejected_count; ?></h5>
                            <p class="card-text"><?php echo $rejected_count > 0 ? ($translations[$lang]['rejected_cases_desc'] ?? 'Total cases rejected by the system.') : ($translations[$lang]['no_cases'] ?? 'No recent cases available.'); ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/unapproved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['approved_cases'] ?? 'Approved Cases'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $approved_count; ?></h5>
                            <p class="card-text"><?php echo $approved_count > 0 ? ($translations[$lang]['approved_cases_desc'] ?? 'Total cases approved by the system.') : ($translations[$lang]['no_cases'] ?? 'No recent cases available.'); ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/approved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <!-- Updated Pending Split Requests Card -->
                <div class="col-md-2">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['pending_split_requests'] ?? 'Pending Split Requests'; ?></div>
                        <div class="card-body">
                            <?php if ($split_requests_error): ?>
                                <h5 class="card-title error-text"><?php echo htmlspecialchars($split_requests_error); ?></h5>
                            <?php else: ?>
                                <h5 class="card-title"><?php echo $pending_split_requests; ?></h5>
                            <?php endif; ?>
                            <p class="card-text"><?php echo $pending_split_requests > 0 && !$split_requests_error ? ($translations[$lang]['pending_split_requests_desc'] ?? 'Split requests awaiting review.') : ($translations[$lang]['no_cases'] ?? 'No pending split requests.'); ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/manager/split_requests_view.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['review'] ?? 'Review'; ?></a>
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
                            <button type="submit" class="btn btn-primary me-2"><?php echo $translations[$lang]['apply'] ?? 'Apply'; ?></button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo BASE_URL; ?>/modules/manager/dashboard.php?lang=<?php echo $lang; ?>'"><?php echo $translations[$lang]['reset'] ?? 'Reset'; ?></button>
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
                        <h3><?php echo $translations[$lang]['case_title_distribution'] ?? 'Case Title Distribution'; ?></h3>
                        <canvas id="caseTitleChart"></canvas>
                        <?php if (empty($title_counts) || array_sum($title_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No case title data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <h3><?php echo $translations[$lang]['recent_case_entries'] ?? 'Recent Case Entries'; ?></h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo $translations[$lang]['title'] ?? 'Title'; ?></th>
                            <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                            <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                            <th><?php echo $translations[$lang]['assigned_to'] ?? 'Assigned To'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_case_entries)): ?>
                            <tr>
                                <td colspan="4" class="text-center"><?php echo $translations[$lang]['no_cases'] ?? 'No recent cases available.'; ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_case_entries as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td class="status-<?php echo strtolower($case['status']); ?>">
                                        <?php echo $translations[$lang][strtolower($case['status'])] ?? ucfirst($case['status']); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($case['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($case['full_name'] ?: 'Unassigned'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        // Case Status Bar Chart
        var statusXValues = [
            '<?php echo $translations[$lang]['confirmed'] ?? 'Confirmed'; ?>',
            '<?php echo $translations[$lang]['approved'] ?? 'Approved'; ?>',
            '<?php echo $translations[$lang]['rejected'] ?? 'Rejected'; ?>',
            '<?php echo $translations[$lang]['pending'] ?? 'Pending'; ?>'
        ];
        var statusYValues = [
            <?php echo $status_counts['confirmed']; ?>,
            <?php echo $status_counts['approved']; ?>,
            <?php echo $status_counts['rejected']; ?>,
            <?php echo $status_counts['pending']; ?>
        ];
        var barColors = ["red", "green", "blue", "orange"];

        new Chart("caseStatusChart", {
            type: "bar",
            data: {
                labels: statusXValues,
                datasets: [{
                    backgroundColor: barColors,
                    data: statusYValues
                }]
            },
            options: {
                legend: { display: false },
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['case_status_distribution'] ?? 'Case Status Distribution'; ?>'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });

        // Case Title Bar Chart
        var titleXValues = <?php echo json_encode($title_labels); ?>;
        var titleYValues = <?php echo json_encode($title_counts); ?>;
        var titleTotal = titleYValues.reduce(function(a, b) { return a + b; }, 0);
        if (titleTotal === 0) {
            titleXValues = ['No Data'];
            titleYValues = [0];
        }

        new Chart("caseTitleChart", {
            type: "bar",
            data: {
                labels: titleXValues,
                datasets: [{
                    backgroundColor: barColors,
                    data: titleYValues
                }]
            },
            options: {
                legend: { display: false },
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['case_title_distribution'] ?? 'Case Title Distribution'; ?>'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }],
                    xAxes: [{
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }]
                }
            }
        });

        // Debug chart data
        console.log('Status Chart Data:', statusXValues, statusYValues);
        console.log('Title Chart Data:', titleXValues, titleYValues);
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>