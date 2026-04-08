<?php
ob_start();
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/config.php';
require_once '../../includes/languages.php';
redirectIfNotLoggedIn();

if (!isSurveyor()) {
    $_SESSION['error'] = $translations['en']['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'om';
if (!in_array($lang, $supported_languages)) {
    $lang = 'om';
}

if (!isset($_GET['case_id']) || !filter_var($_GET['case_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = $translations[$lang]['error_mark_read_failed'];
    header("Location: " . BASE_URL . "/modules/surveyor/new_cases.php?lang=$lang");
    ob_end_flush();
    exit;
}

$case_id = $_GET['case_id'];
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    $_SESSION['error'] = $translations[$lang]['error_mark_read_failed'];
    header("Location: " . BASE_URL . "/modules/surveyor/new_cases.php?lang=$lang");
    ob_end_flush();
    exit;
}
$conn->set_charset('utf8mb4');

// Verify the case is assigned to the surveyor
$sql = "SELECT id FROM cases WHERE id = ? AND assigned_to = ? AND viewed = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $case_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    $_SESSION['error'] = $translations[$lang]['error_mark_read_failed'];
    header("Location: " . BASE_URL . "/modules/surveyor/new_cases.php?lang=$lang");
    ob_end_flush();
    exit;
}
$stmt->close();

// Update viewed status
$sql = "UPDATE cases SET viewed = 1 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $case_id);
if ($stmt->execute()) {
    logAction('mark_case_read', "Case ID $case_id marked as read by user ID $user_id", 'info');
    $_SESSION['success'] = $translations[$lang]['success_marked_read'];
} else {
    logAction('mark_case_read_failed', 'Failed to mark case ID $case_id as read: ' . $stmt->error, 'error');
    $_SESSION['error'] = $translations[$lang]['error_mark_read_failed'];
}
$stmt->close();
$conn->close();

header("Location: " . BASE_URL . "/modules/surveyor/new_cases.php?lang=$lang");
ob_end_flush();
?>