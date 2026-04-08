<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

redirectIfNotLoggedIn();

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

$user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notification_id > 0) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        logAction('notification_marked_read', 'Notification ' . $notification_id . ' marked as read by user ' . $user_id, 'info');
    } catch (Exception $e) {
        logAction('mark_read_failed', 'Failed to mark notification ' . $notification_id . ' as read: ' . $e->getMessage(), 'error');
    }
}

$conn->close();
header("Location: " . BASE_URL . "/modules/includes/notifications.php");
exit;
?>