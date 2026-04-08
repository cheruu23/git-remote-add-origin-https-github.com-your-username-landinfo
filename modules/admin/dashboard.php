<?php
ob_start();
require_once '../../includes/init.php';

redirectIfNotLoggedIn();

// Restrict access to admins
restrictAccess(['admin'], 'admin dashboard', $lang);

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Database connection failed: " . $conn->connect_error);
    die($translations[$lang]['db_connection_failed'] ?? 'Database connection error.');
}
$conn->set_charset('utf8mb4');

// Log admin dashboard access
logAction('admin_dashboard_access', 'Admin accessed dashboard', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Fetch data for charts and cards
// Total Users
$total_users = 0;
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_users = $result->fetch_assoc()['total'];
} catch (Exception $e) {
    logAction('fetch_users_failed', 'Failed to fetch total users: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Failed to fetch total users: " . $e->getMessage());
}

// Recent Logs (last 7 days)
$recent_logs = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_logs = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
} catch (Exception $e) {
    logAction('fetch_logs_failed', 'Failed to fetch recent logs: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Failed to fetch recent logs: " . $e->getMessage());
}

// Recent Log Entries (last 5 for table display)
$recent_log_entries = [];
try {
    $stmt = $conn->prepare("SELECT action, severity, created_at, ip_address FROM system_logs ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_log_entries[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('fetch_log_entries_failed', 'Failed to fetch recent log entries: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Failed to fetch recent log entries: " . $e->getMessage());
}

// User Roles Distribution
$user_roles = ['admin' => 0, 'manager' => 0, 'record_officer' => 0, 'surveyor' => 0];
try {
    $result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    while ($row = $result->fetch_assoc()) {
        if (isset($user_roles[$row['role']])) {
            $user_roles[$row['role']] = $row['count'];
        }
    }
} catch (Exception $e) {
    logAction('fetch_roles_failed', 'Failed to fetch user roles: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Failed to fetch user roles: " . $e->getMessage());
}

// Log Severity Distribution
$log_severity = ['info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0];
try {
    $result = $conn->query("SELECT severity, COUNT(*) as count FROM system_logs GROUP BY severity");
    while ($row = $result->fetch_assoc()) {
        if (isset($log_severity[$row['severity']])) {
            $log_severity[$row['severity']] = $row['count'];
        }
    }
} catch (Exception $e) {
    logAction('fetch_severity_failed', 'Failed to fetch log severity: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Failed to fetch log severity: " . $e->getMessage());
}

$conn->close();

// Check if chart data is empty
$user_roles_empty = array_sum($user_roles) === 0;
$log_severity_empty = array_sum($log_severity) === 0;
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['admin_dashboard'] ?? 'Admin Dashboard'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        .content.collapsed { margin-left: 60px; }
        h2.text-center { font-size: 2rem; font-weight: 600; color: #1e40af; margin-bottom: 25px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; background: #fff; overflow: hidden; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15); }
        .card-header { font-size: 1.2rem; font-weight: 600; background: linear-gradient(90deg, #1e40af, #3b82f6); color: #fff; padding: 15px; border-radius: 15px 15px 0 0; }
        .card-body { padding: 20px; }
        .card-title { font-size: 1.5rem; font-weight: 500; color: #1f2937; margin-bottom: 10px; }
        .card-text { font-size: 0.95rem; color: #6b7280; margin-bottom: 15px; }
        .btn-primary { background: #1e40af; border: none; padding: 8px 16px; border-radius: 5px; transition: background 0.3s; }
        .btn-primary:hover { background: #2563eb; }
        .chart-container { margin-top: 30px; padding: 20px; background: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .chart { width: 100%; max-width: 600px; height: 400px; margin: 0 auto; }
        .table-container { margin-top: 30px; padding: 20px; background: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .table { margin-bottom: 0; }
        .table th { background: #f1f5f9; color: #1f2937; font-weight: 600; }
        .table td { vertical-align: middle; }
        .severity-info { color: #10b981; }
        .severity-warning { color: #f59e0b; }
        .severity-error { color: #ef4444; }
        .severity-critical { color: #b91c1c; }
        .no-data { text-align: center; color: #6b7280; padding: 20px; }
        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 15px; }
            .card { margin-bottom: 20px; }
            h2.text-center { font-size: 1.8rem; }
            .chart-container, .table-container { padding: 15px; }
            .chart { max-width: 100%; height: 300px; }
            .table { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-4">
            <h2 class="text-center"><?php echo $translations[$lang]['welcome_admin'] ?? 'Welcome, System Admin'; ?></h2>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['total_users'] ?? 'Total Users'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $total_users; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['manage_users_desc'] ?? 'View, add, or remove system users.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/admin/manage_user.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['manage'] ?? 'Manage'; ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['recent_logs'] ?? 'Recent Logs'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $recent_logs; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['view_logs_desc'] ?? 'Logs from the last 7 days.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/admin/view_logs.php?lang=<?php echo $lang; ?>" class="btn btn-primary" id="view-logs-link"><?php echo $translations[$lang]['view_logs'] ?? 'View Logs'; ?></a>
                        </div>
                    </div>
                </div>
               
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header"><?php echo $translations[$lang]['home_page_posts'] ?? 'Home Page Posts'; ?></div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $translations[$lang]['manage_content'] ?? 'Manage Content'; ?></h5>
                            <p class="card-text"><?php echo $translations[$lang]['home_page_posts_desc'] ?? 'Edit home page content.'; ?></p>
                            <a href="<?php echo BASE_URL; ?>/modules/admin/home_content.php?lang=<?php echo $lang; ?>" class="btn btn-primary"><?php echo $translations[$lang]['manage'] ?? 'Manage'; ?></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['user_roles_distribution'] ?? 'User Roles Distribution'; ?></h3>
                        <?php if ($user_roles_empty): ?>
                            <div class="no-data"><?php echo $translations[$lang]['no_chart_data'] ?? 'No data available for this chart.'; ?></div>
                        <?php else: ?>
                            <div id="userRolesChart" class="chart"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h3><?php echo $translations[$lang]['log_severity_distribution'] ?? 'Log Severity Distribution'; ?></h3>
                        <?php if ($log_severity_empty): ?>
                            <div class="no-data"><?php echo $translations[$lang]['no_chart_data'] ?? 'No data available for this chart.'; ?></div>
                        <?php else: ?>
                            <div id="logSeverityChart" class="chart"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-container">
                <h3><?php echo $translations[$lang]['recent_log_entries'] ?? 'Recent Log Entries'; ?></h3>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                            <th><?php echo $translations[$lang]['severity'] ?? 'Severity'; ?></th>
                            <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                            <th><?php echo $translations[$lang]['ip_address'] ?? 'IP Address'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_log_entries)): ?>
                            <tr>
                                <td colspan="4" class="text-center"><?php echo $translations[$lang]['no_logs'] ?? 'No recent logs available.'; ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_log_entries as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td class="severity-<?php echo strtolower($log['severity']); ?>">
                                        <?php echo $translations[$lang][strtolower($log['severity'])] ?? ucfirst($log['severity']); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?: 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // User Roles Bar Chart
            <?php if (!$user_roles_empty): ?>
            const userRolesData = google.visualization.arrayToDataTable([
                ['<?php echo $translations[$lang]['role'] ?? 'Role'; ?>', '<?php echo $translations[$lang]['number_of_users'] ?? 'Number of Users'; ?>'],
                ['<?php echo $translations[$lang]['admin'] ?? 'Admin'; ?>', <?php echo $user_roles['admin']; ?>],
                ['<?php echo $translations[$lang]['manager'] ?? 'Manager'; ?>', <?php echo $user_roles['manager']; ?>],
                ['<?php echo $translations[$lang]['record_officer'] ?? 'Record Officer'; ?>', <?php echo $user_roles['record_officer']; ?>],
                ['<?php echo $translations[$lang]['surveyor'] ?? 'Surveyor'; ?>', <?php echo $user_roles['surveyor']; ?>]
            ]);

            const userRolesOptions = {
                title: '<?php echo $translations[$lang]['user_roles_distribution'] ?? 'User Roles Distribution'; ?>',
                colors: ['#1e40af'],
                vAxis: { title: '<?php echo $translations[$lang]['role'] ?? 'Role'; ?>' },
                hAxis: { title: '<?php echo $translations[$lang]['count'] ?? 'Count'; ?>' },
                legend: { position: 'none' }
            };

            const userRolesChart = new google.visualization.BarChart(document.getElementById('userRolesChart'));
            userRolesChart.draw(userRolesData, userRolesOptions);
            <?php endif; ?>

            // Log Severity Pie Chart
            <?php if (!$log_severity_empty): ?>
            const logSeverityData = google.visualization.arrayToDataTable([
                ['<?php echo $translations[$lang]['severity'] ?? 'Severity'; ?>', '<?php echo $translations[$lang]['count'] ?? 'Count'; ?>'],
                ['<?php echo $translations[$lang]['info'] ?? 'Info'; ?>', <?php echo $log_severity['info']; ?>],
                ['<?php echo $translations[$lang]['warning'] ?? 'Warning'; ?>', <?php echo $log_severity['warning']; ?>],
                ['<?php echo $translations[$lang]['error'] ?? 'Error'; ?>', <?php echo $log_severity['error']; ?>],
                ['<?php echo $translations[$lang]['critical'] ?? 'Critical'; ?>', <?php echo $log_severity['critical']; ?>]
            ]);

            const logSeverityOptions = {
                title: '<?php echo $translations[$lang]['log_severity_distribution'] ?? 'Log Severity Distribution'; ?>',
                colors: ['#10b981', '#f59e0b', '#ef4444', '#b91c1c'],
                pieHole: 0.4,
                legend: { position: 'bottom' }
            };

            const logSeverityChart = new google.visualization.PieChart(document.getElementById('logSeverityChart'));
            logSeverityChart.draw(logSeverityData, logSeverityOptions);
            <?php endif; ?>
        }

        // Log view logs click
        document.getElementById('view-logs-link').addEventListener('click', function(e) {
            fetch('<?php echo BASE_URL; ?>/modules/admin/log_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'navigate_view_logs',
                    details: 'Clicked to view system logs',
                    severity: 'info'
                })
            }).catch(error => console.error('Error logging event:', error));
        });
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>