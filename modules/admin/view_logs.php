<?php
ob_start();
require_once '../../includes/init.php';
require_once '../../includes/languages.php';

redirectIfNotLoggedIn();

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Check if user is admin
if (!isAdmin()) {
    logAction('unauthorized_access', 'Attempted access to log viewer', 'warning', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['access_denied'] ?? 'Access denied!');
}

// Log access to log viewer
logAction('view_logs', 'Accessed system log viewer', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Database connection failed: " . $conn->connect_error);
    die($translations[$lang]['db_connection_failed'] ?? 'Database connection error.');
}
$conn->set_charset('utf8mb4');

// Handle clear logs request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    try {
        $stmt = $conn->prepare("DELETE FROM system_logs");
        $stmt->execute();
        logAction('clear_logs', 'Cleared all system logs', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);
        $stmt->close();
        header("Location: view_logs.php?lang=$lang&cleared=1");
        exit;
    } catch (Exception $e) {
        logAction('clear_logs_failed', 'Failed to clear logs: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
        error_log("Failed to clear logs: " . $e->getMessage());
        header("Location: view_logs.php?lang=$lang&error=clear_failed");
        exit;
    }
}

// Fetch all users for dropdown
$users = [];
try {
    $user_sql = "SELECT id, username FROM users ORDER BY username";
    $user_result = $conn->query($user_sql);
    if ($user_result) {
        $users = $user_result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
}

// Handle filters
$user_id_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$severity_filter = isset($_GET['severity']) ? $conn->real_escape_string($_GET['severity']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$sql = "SELECT l.id, l.user_id, l.action, l.details, l.severity, l.created_at, l.ip_address, 
               u.username
        FROM system_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE 1=1";
$params = [];
$types = '';

if ($user_id_filter) {
    $sql .= " AND l.user_id = ?";
    $params[] = $user_id_filter;
    $types .= 'i';
}
if ($severity_filter && in_array($severity_filter, ['info', 'warning', 'error', 'critical'])) {
    $sql .= " AND l.severity = ?";
    $params[] = $severity_filter;
    $types .= 's';
}
if ($search) {
    $sql .= " AND (l.action LIKE ? OR l.details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Count total logs for pagination
$count_sql = "SELECT COUNT(*) FROM system_logs l WHERE 1=1";
$count_params = [];
$count_types = '';
if ($user_id_filter) {
    $count_sql .= " AND l.user_id = ?";
    $count_params[] = $user_id_filter;
    $count_types .= 'i';
}
if ($severity_filter && in_array($severity_filter, ['info', 'warning', 'error', 'critical'])) {
    $count_sql .= " AND l.severity = ?";
    $count_params[] = $severity_filter;
    $count_types .= 's';
}
if ($search) {
    $count_sql .= " AND (l.action LIKE ? OR l.details LIKE ?)";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_types .= 'ss';
}
try {
    $count_stmt = $conn->prepare($count_sql);
    if ($count_types) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $total_logs = $count_stmt->get_result()->fetch_row()[0];
    $count_stmt->close();
    $total_pages = ceil($total_logs / $per_page);
} catch (Exception $e) {
    error_log("Failed to count logs: " . $e->getMessage());
    $total_logs = 0;
    $total_pages = 1;
}

// Fetch logs
try {
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $logs = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Failed to fetch logs: " . $e->getMessage());
}
$conn->close();

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Pagination range
$max_page_links = 5;
$half_range = floor($max_page_links / 2);
$start_page = max(1, $page - $half_range);
$end_page = min($total_pages, $start_page + $max_page_links - 1);
if ($end_page - $start_page + 1 < $max_page_links) {
    $start_page = max(1, $end_page - $max_page_links + 1);
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['system_logs_title'] ?? 'System Logs'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .content.collapsed { margin-left: 60px; }
        h2.text-center { font-size: 2rem; font-weight: 600; color: #1e40af; margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); padding: 20px; background: #fff; }
        .table-responsive { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #3498db; color: #fff; font-weight: 500; }
        tr:hover { background: #f1f5f9; }
        .form-group { margin-bottom: 15px; }
        .btn-primary { background: #3498db; border: none; padding: 8px 16px; border-radius: 5px; color: #fff; }
        .btn-danger { background: #dc3545; border: none; padding: 8px 16px; border-radius: 5px; color: #fff; }
        .btn-container { margin-bottom: 20px; text-align: right; }
        .pagination { margin-top: 20px; display: flex; justify-content: center; }
        .pagination a { margin: 0 5px; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 5px; text-decoration: none; color: #3498db; }
        .pagination a.active, .pagination a:hover { background: #3498db; color: #fff; }
        .alert { margin-bottom: 20px; padding: 10px; border-radius: 6px; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        @media (max-width: 768px) {
            .content { margin-left: 0; padding: 15px; }
            th, td { font-size: 14px; padding: 8px; }
            .btn-container { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['system_logs_title'] ?? 'System Logs'; ?></h2>
            <div class="card">
                <!-- Alerts -->
                <?php if (isset($_GET['cleared'])): ?>
                    <div class="alert alert-success"><?php echo $translations[$lang]['logs_cleared_success'] ?? 'Logs cleared successfully.'; ?></div>
                <?php elseif (isset($_GET['error']) && $_GET['error'] === 'clear_failed'): ?>
                    <div class="alert alert-danger"><?php echo $translations[$lang]['logs_clear_failed'] ?? 'Failed to clear logs. Please try again.'; ?></div>
                <?php endif; ?>

                <!-- Clear Logs Button -->
                <div class="btn-container">
                    <button type="button" class="btn btn-danger" onclick="confirmClearLogs()"><?php echo $translations[$lang]['clear_logs'] ?? 'Clear All Logs'; ?></button>
                </div>

                <!-- Filter Form -->
                <form method="GET" class="form-group">
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                    <div class="row">
                        <div class="col-md-4">
                            <label><?php echo $translations[$lang]['user'] ?? 'User'; ?></label>
                            <select name="user_id" class="form-control">
                                <option value=""><?php echo $translations[$lang]['all_users'] ?? 'All Users'; ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_id_filter === $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label><?php echo $translations[$lang]['severity'] ?? 'Severity'; ?></label>
                            <select name="severity" class="form-control">
                                <option value=""><?php echo $translations[$lang]['all_severities'] ?? 'All'; ?></option>
                                <option value="info" <?php echo $severity_filter === 'info' ? 'selected' : ''; ?>><?php echo $translations[$lang]['info'] ?? 'Info'; ?></option>
                                <option value="warning" <?php echo $severity_filter === 'warning' ? 'selected' : ''; ?>><?php echo $translations[$lang]['warning'] ?? 'Warning'; ?></option>
                                <option value="error" <?php echo $severity_filter === 'error' ? 'selected' : ''; ?>><?php echo $translations[$lang]['error'] ?? 'Error'; ?></option>
                                <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>><?php echo $translations[$lang]['critical'] ?? 'Critical'; ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label><?php echo $translations[$lang]['search'] ?? 'Search'; ?></label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="<?php echo $translations[$lang]['search_placeholder'] ?? 'Search action or details'; ?>">
                        </div>
                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary"><?php echo $translations[$lang]['filter'] ?? 'Filter'; ?></button>
                        </div>
                    </div>
                </form>

                <?php if (empty($logs)): ?>
                    <div class="alert alert-info"><?php echo $translations[$lang]['no_logs_found'] ?? 'No logs found.'; ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                    <th><?php echo $translations[$lang]['user'] ?? 'User'; ?></th>
                                    <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                                    <th><?php echo $translations[$lang]['details'] ?? 'Details'; ?></th>
                                    <th><?php echo $translations[$lang]['severity'] ?? 'Severity'; ?></th>
                                    <th><?php echo $translations[$lang]['timestamp'] ?? 'Timestamp'; ?></th>
                                    <th><?php echo $translations[$lang]['ip_address'] ?? 'IP Address'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['id']); ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($log['severity'])); ?></td>
                                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?lang=<?php echo urlencode($lang); ?>&page=<?php echo $page - 1; ?>&user_id=<?php echo urlencode($user_id_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $translations[$lang]['previous'] ?? 'Previous'; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($start_page > 1): ?>
                            <a href="?lang=<?php echo urlencode($lang); ?>&page=1&user_id=<?php echo urlencode($user_id_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&search=<?php echo urlencode($search); ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?lang=<?php echo urlencode($lang); ?>&page=<?php echo $i; ?>&user_id=<?php echo urlencode($user_id_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?lang=<?php echo urlencode($lang); ?>&page=<?php echo $total_pages; ?>&user_id=<?php echo urlencode($user_id_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?lang=<?php echo urlencode($lang); ?>&page=<?php echo $page + 1; ?>&user_id=<?php echo urlencode($user_id_filter); ?>&severity=<?php echo urlencode($severity_filter); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $translations[$lang]['next'] ?? 'Next'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmClearLogs() {
            if (confirm('<?php echo addslashes($translations[$lang]['clear_logs_confirm'] ?? 'Are you sure you want to clear all system logs? This action cannot be undone.'); ?>')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'clear_logs';
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>