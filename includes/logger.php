<?php
require_once __DIR__ . '/db.php';

if (!function_exists('logAction')) {
    function logAction($action, $details = '', $severity = 'info', $user_id = null) {
        $conn = getDBConnection();
        if ($conn->connect_error) {
            error_log("Logger DB connection failed: " . $conn->connect_error);
            return false;
        }
        $conn->set_charset('utf8mb4');

        // Sanitize inputs
        $action = $conn->real_escape_string(trim($action));
        $details = $conn->real_escape_string(trim($details));
        $severity = in_array($severity, ['info', 'warning', 'error', 'critical']) ? $severity : 'info';
        $ip_address = $conn->real_escape_string($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Use session user_id if not provided
        if ($user_id === null && isset($_SESSION['user']['id'])) {
            $user_id = (int)$_SESSION['user']['id'];
        } elseif ($user_id === null) {
            $user_id = NULL;
        }

        // Insert log entry
        $sql = "INSERT INTO system_logs (user_id, action, details, severity, ip_address)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Logger prepare failed: " . $conn->error);
            $conn->close();
            return false;
        }
        $stmt->bind_param("issss", $user_id, $action, $details, $severity, $ip_address);
        
        $result = $stmt->execute();
        if (!$result) {
            error_log("Logger query failed: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
        return $result;
    }
}
?>