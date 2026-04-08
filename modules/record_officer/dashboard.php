<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    logAction('access_denied', 'Unauthorized access to record officer dashboard', 'error');
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die($translations[$lang]['db_connection_failed'] ?? "Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info');

// Fetch dashboard metrics
$sql_files = "SELECT COUNT(*) as count FROM land_registration";
$stmt_files = $conn->prepare($sql_files);
$total_files = 0;
if ($stmt_files && $stmt_files->execute()) {
    $total_files = $stmt_files->get_result()->fetch_assoc()['count'] ?? 0;
} else {
    error_log("Total files query failed: " . $conn->error);
}
$stmt_files->close();

$sql_recent = "SELECT COUNT(*) as count FROM land_registration WHERE created_at >= NOW() - INTERVAL 30 DAY";
$stmt_recent = $conn->prepare($sql_recent);
$recent_files = 0;
if ($stmt_recent && $stmt_recent->execute()) {
    $recent_files = $stmt_recent->get_result()->fetch_assoc()['count'] ?? 0;
} else {
    error_log("Recent files query failed: " . $conn->error);
}
$stmt_recent->close();

$sql_cases = "SELECT COUNT(*) as count FROM cases";
$stmt_cases = $conn->prepare($sql_cases);
$reported_cases = 0;
if ($stmt_cases && $stmt_cases->execute()) {
    $reported_cases = $stmt_cases->get_result()->fetch_assoc()['count'] ?? 0;
} else {
    error_log("Reported cases query failed: " . $conn->error);
}
$stmt_cases->close();

$sql_approved = "SELECT COUNT(*) as count FROM cases WHERE status = 'Approved'";
$stmt_approved = $conn->prepare($sql_approved);
$approved_cases = 0;
if ($stmt_approved && $stmt_approved->execute()) {
    $approved_cases = $stmt_approved->get_result()->fetch_assoc()['count'] ?? 0;
} else {
    error_log("Approved cases query failed: " . $conn->error);
}
$stmt_approved->close();

$sql_finalized = "SELECT COUNT(*) as count FROM cases WHERE status = 'Serviced'";
$stmt_finalized = $conn->prepare($sql_finalized);
$finalized_cases = 0;
if ($stmt_finalized && $stmt_finalized->execute()) {
    $finalized_cases = $stmt_finalized->get_result()->fetch_assoc()['count'] ?? 0;
} else {
    error_log("Finalized cases query failed: " . $conn->error);
}
$stmt_finalized->close();

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
    } else {
        logAction('invalid_date_filter', 'Invalid date range provided: ' . $start_date . ' to ' . $end_date, 'warning');
        $start_date = $end_date = null;
    }
}

// Fetch status counts for charts
$statuses = ['Reported', 'Approved'];
$status_counts = ['reported' => 0, 'approved' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM cases c LEFT JOIN users u ON c.reported_by = u.id WHERE c.status = ? AND u.role = 'record_officer'" . $date_filter;
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

// Fetch title distribution
$title_data = [];
try {
    $sql = "SELECT c.title, COUNT(*) as count FROM cases c LEFT JOIN users u ON c.reported_by = u.id WHERE c.status IN ('Reported', 'Approved') AND u.role = 'record_officer'" . $date_filter . " GROUP BY c.title ORDER BY count DESC LIMIT 5";
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

// Fetch land type distribution
$land_type_data = [];
try {
    $sql = "SELECT land_type, COUNT(*) as count FROM land_registration WHERE 1=1" . $date_filter . " GROUP BY land_type";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $land_type_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Land type query failed: ' . $e->getMessage(), 'error');
}

// Fetch gender distribution
$gender_data = [];
try {
    $sql = "SELECT gender, COUNT(*) as count FROM land_registration WHERE 1=1" . $date_filter . " GROUP BY gender";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $gender_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Gender query failed: ' . $e->getMessage(), 'error');
}

// Fetch village distribution
$village_data = [];
try {
    $sql = "SELECT village, COUNT(*) as count FROM land_registration WHERE village IS NOT NULL AND village != ''" . $date_filter . " GROUP BY village ORDER BY count DESC";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $village_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Village query failed: ' . $e->getMessage(), 'error');
}

// Fetch land service distribution
$land_service_data = [];
try {
    $sql = "SELECT parcel_land_service, COUNT(*) as count FROM land_registration WHERE parcel_land_service IS NOT NULL AND parcel_land_service != ''" . $date_filter . " GROUP BY parcel_land_service";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $land_service_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Land service query failed: ' . $e->getMessage(), 'error');
}

$title_labels = array_column($title_data, 'title') ?: ['No Data'];
$title_counts = array_column($title_data, 'count') ?: [0];

// Prepare chart data with translations
$land_type_labels = [];
$land_type_counts = [];
foreach ($land_type_data as $item) {
    switch ($item['land_type']) {
        case 'dhaala':
            $land_type_labels[] = $translations[$lang]['dhaala'] ?? 'Inheritance';
            break;
        case 'lease_land':
            $land_type_labels[] = $translations[$lang]['lease_land'] ?? 'Lease Land';
            break;
        case 'bita_fi_gurgurtaa':
            $land_type_labels[] = $translations[$lang]['bita_fi_gurgurtaa'] ?? 'Purchase and Sale';
            break;
        case 'miritti':
            $land_type_labels[] = $translations[$lang]['miritti'] ?? 'Right';
            break;
        case 'caalbaasii':
            $land_type_labels[] = $translations[$lang]['caalbaasii'] ?? 'Employment';
            break;
        default:
            $land_type_labels[] = $item['land_type'];
    }
    $land_type_counts[] = $item['count'];
}

$gender_labels = [];
$gender_counts = [];
foreach ($gender_data as $item) {
    switch ($item['gender']) {
        case 'Dhiira':
            $gender_labels[] = $translations[$lang]['male'] ?? 'Male';
            break;
        case 'Dubartii':
            $gender_labels[] = $translations[$lang]['female'] ?? 'Female';
            break;
        default:
            $gender_labels[] = $item['gender'];
    }
    $gender_counts[] = $item['count'];
}

$village_labels = array_column($village_data, 'village') ?: ['No Data'];
$village_counts = array_column($village_data, 'count') ?: [0];

$land_service_labels = [];
$land_service_counts = [];
foreach ($land_service_data as $item) {
    switch ($item['parcel_land_service']) {
        case 'lafa daldalaa':
            $land_service_labels[] = $translations[$lang]['commercial'] ?? 'Commercial';
            break;
        case 'lafa mana jireenyaa':
            $land_service_labels[] = $translations[$lang]['residential'] ?? 'Residential';
            break;
        default:
            $land_service_labels[] = $item['parcel_land_service'];
    }
    $land_service_counts[] = $item['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['dashboard_title'] ?? 'Record Officer Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
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
            height: 400px;
        }
        canvas {
            width: 100% !important;
            max-width: 600px;
            height: 350px !important;
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
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        @media (max-width: 992px) {
            .content {
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
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['welcome_record_officer'] ?? 'Welcome, Record Officer'; ?></h2>
           
            <div class="row mt-4 g-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['reported_cases'] ?? 'Reported Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-exclamation-circle fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($reported_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_reported_cases'] ?? 'View all reported cases.'; ?></p>
                            <a href="reported_cases.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['approved_cases'] ?? 'Approved Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($approved_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_approved_cases'] ?? 'View approved cases.'; ?></p>
                            <a href="approved_cases.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['finalized_cases'] ?? 'Finalized Cases'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-clipboard-check fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($finalized_cases); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_finalized_cases'] ?? 'View finalized cases.'; ?></p>
                            <a href="finalized_cases.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['recent_files'] ?? 'Recent Files'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-file-alt fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($recent_files); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_recent_files'] ?? 'View files from last 30 days.'; ?></p>
                            <a href="files.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card h-100">
                        <div class="card-header"><?php echo $translations[$lang]['files'] ?? 'Files'; ?></div>
                        <div class="card-body">
                            <i class="fas fa-folder-open fa-2x mb-3" style="color: #1e40af;"></i>
                            <div class="card-title"><?php echo htmlspecialchars($total_files); ?></div>
                            <p class="card-text"><?php echo $translations[$lang]['view_all_files'] ?? 'View all files.'; ?></p>
                            <a href="files.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
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
                        <h3><?php echo $translations[$lang]['case_title_distribution'] ?? 'Case Title Distribution'; ?></h3>
                        <canvas id="caseTitleChart"></canvas>
                        <?php if (empty($title_counts) || array_sum($title_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No case title data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['land_type_distribution'] ?? 'Land Type Distribution'; ?></h3>
                        <canvas id="landTypeChart"></canvas>
                        <?php if (empty($land_type_counts) || array_sum($land_type_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No land type data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['gender_distribution'] ?? 'Gender Distribution'; ?></h3>
                        <canvas id="genderChart"></canvas>
                        <?php if (empty($gender_counts) || array_sum($gender_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No gender data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['village_distribution'] ?? 'Village Distribution'; ?></h3>
                        <canvas id="villageChart"></canvas>
                        <?php if (empty($village_counts) || array_sum($village_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No village data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['land_service_distribution'] ?? 'Land Service Distribution'; ?></h3>
                        <canvas id="landServiceChart"></canvas>
                        <?php if (empty($land_service_counts) || array_sum($land_service_counts) === 0): ?>
                            <div class="no-data-message"><?php echo $translations[$lang]['no_data'] ?? 'No land service data available.'; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Chart colors
        const chartColors = [
            "#3b82f6", "#ef4444", "#10b981", "#f59e0b", "#8b5cf6", 
            "#ec4899", "#14b8a6", "#f97316", "#64748b", "#84cc16"
        ];

        // Case Status Pie Chart
        var statusXValues = [
            '<?php echo $translations[$lang]['reported'] ?? 'Reported'; ?>',
            '<?php echo $translations[$lang]['approved'] ?? 'Approved'; ?>'
        ];
        var statusYValues = [
            <?php echo $status_counts['reported']; ?>,
            <?php echo $status_counts['approved']; ?>
        ];
        var statusTotal = statusYValues.reduce(function(a, b) { return a + b; }, 0);
        if (statusTotal === 0) {
            statusXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            statusYValues = [1];
        }

        new Chart("caseStatusChart", {
            type: "pie",
            data: {
                labels: statusXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: statusYValues
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['case_status_distribution'] ?? 'Case Status Distribution'; ?>'
                }
            }
        });

        // Case Title Pie Chart
        var titleXValues = <?php echo json_encode($title_labels); ?>;
        var titleYValues = <?php echo json_encode($title_counts); ?>;
        var titleTotal = titleYValues.reduce(function(a, b) { return a + b; }, 0);
        if (titleTotal === 0) {
            titleXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            titleYValues = [1];
        }

        new Chart("caseTitleChart", {
            type: "pie",
            data: {
                labels: titleXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: titleYValues
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['case_title_distribution'] ?? 'Case Title Distribution'; ?>'
                }
            }
        });

        // Land Type Bar Chart
        var landTypeXValues = <?php echo json_encode($land_type_labels); ?>;
        var landTypeYValues = <?php echo json_encode($land_type_counts); ?>;
        var landTypeTotal = landTypeYValues.reduce(function(a, b) { return a + b; }, 0);
        if (landTypeTotal === 0) {
            landTypeXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            landTypeYValues = [1];
        }

        new Chart("landTypeChart", {
            type: "bar",
            data: {
                labels: landTypeXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: landTypeYValues,
                    label: '<?php echo $translations[$lang]['count'] ?? 'Count'; ?>'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['land_type_distribution'] ?? 'Land Type Distribution'; ?>'
                },
                legend: {
                    display: false
                }
            }
        });

        // Gender Pie Chart
        var genderXValues = <?php echo json_encode($gender_labels); ?>;
        var genderYValues = <?php echo json_encode($gender_counts); ?>;
        var genderTotal = genderYValues.reduce(function(a, b) { return a + b; }, 0);
        if (genderTotal === 0) {
            genderXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            genderYValues = [1];
        }

        new Chart("genderChart", {
            type: "pie",
            data: {
                labels: genderXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: genderYValues
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['gender_distribution'] ?? 'Gender Distribution'; ?>'
                }
            }
        });

        // Village Bar Chart
        var villageXValues = <?php echo json_encode($village_labels); ?>;
        var villageYValues = <?php echo json_encode($village_counts); ?>;
        var villageTotal = villageYValues.reduce(function(a, b) { return a + b; }, 0);
        if (villageTotal === 0) {
            villageXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            villageYValues = [1];
        }

        new Chart("villageChart", {
            type: "bar",
            data: {
                labels: villageXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: villageYValues,
                    label: '<?php echo $translations[$lang]['count'] ?? 'Count'; ?>'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                },
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['village_distribution'] ?? 'Village Distribution'; ?>'
                },
                legend: {
                    display: false
                }
            }
        });

        // Land Service Pie Chart
        var landServiceXValues = <?php echo json_encode($land_service_labels); ?>;
        var landServiceYValues = <?php echo json_encode($land_service_counts); ?>;
        var landServiceTotal = landServiceYValues.reduce(function(a, b) { return a + b; }, 0);
        if (landServiceTotal === 0) {
            landServiceXValues = ['<?php echo $translations[$lang]['no_data'] ?? 'No Data'; ?>'];
            landServiceYValues = [1];
        }

        new Chart("landServiceChart", {
            type: "pie",
            data: {
                labels: landServiceXValues,
                datasets: [{
                    backgroundColor: chartColors,
                    data: landServiceYValues
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    display: true,
                    text: '<?php echo $translations[$lang]['land_service_distribution'] ?? 'Land Service Distribution'; ?>'
                }
            }
        });
    </script>
</body>
</html>