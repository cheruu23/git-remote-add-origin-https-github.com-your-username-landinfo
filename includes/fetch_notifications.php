<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/logger.php';

redirectIfNotLoggedIn();

$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'en';
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$debug_log = __DIR__ . '/../modules/surveyor/debug.log';

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $user_id, 'role' => $role]);
    echo json_encode(['success' => false, 'message' => $translations[$lang]['db_connection_failed'] ?? 'Database connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

$response = ['success' => false, 'message' => '', 'notifications' => [], 'unread_count' => 0];

try {
    // Mark all notifications as read
    if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        logAction('mark_all_read', "Marked $affected_rows notifications as read", 'info', ['user_id' => $user_id, 'role' => $role]);
    }

    // Mark a single notification as read
    if (isset($_GET['mark_read']) && (int)$_GET['mark_read'] > 0) {
        $notification_id = (int)$_GET['mark_read'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        logAction('mark_notification_read', "Marked notification ID $notification_id as read", 'info', ['user_id' => $user_id, 'role' => $role, 'affected_rows' => $affected_rows]);
    }

    // Determine if only unread notifications should be fetched
    $only_unread = isset($_GET['only_unread']) && $_GET['only_unread'] == 1;
    $where_clause = $only_unread ? "AND is_read = 0" : "";

    // Fetch notifications
    $stmt = $conn->prepare("
        SELECT id, type, case_id, request_id, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? $where_clause
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'case_id' => $row['case_id'] ? (int)$row['case_id'] : null,
            'request_id' => $row['request_id'] ? (int)$row['request_id'] : null,
            'message' => htmlspecialchars($row['message']),
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();

    // Fetch unread count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
    $stmt->close();

    $response = [
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ];
} catch (Exception $e) {
    logAction('fetch_notifications_failed', 'Failed to fetch notifications: ' . $e->getMessage(), 'error', ['user_id' => $user_id, 'role' => $role]);
    $response['message'] = $translations[$lang]['error_notifications'] ?? 'Failed to load notifications';
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to fetch notifications for user $user_id: " . $e->getMessage() . "\n", FILE_APPEND);
}

$conn->close();
echo json_encode($response);
?>