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
$lang = $_POST['lang'] ?? $_GET['lang'] ?? 'om';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $land_id = filter_var($_POST['land_id'], FILTER_VALIDATE_INT);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $case_type = filter_var($_POST['case_type'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $reported_by = $_SESSION['user_id']; // Assumed to be the surveyor's user ID
    $status = 'Reported';
    $report_submitted = 1;
    $viewed = 0;

    // Validate inputs
    if (!$land_id || $land_id <= 0) {
        $_SESSION['error'] = $translations[$lang]['error_invalid_land_id'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }

    if (empty($title) || empty($case_type) || empty($description)) {
        $_SESSION['error'] = $translations[$lang]['error_submission_failed'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }

    // Verify land_id exists in lands table (assumed table)
    $conn = getDBConnection();
    if ($conn->connect_error) {
        logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
        $_SESSION['error'] = $translations[$lang]['error_submission_failed'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }
    $conn->set_charset('utf8mb4');

    $sql = "SELECT id FROM land_registration WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $land_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        $_SESSION['error'] = $translations[$lang]['error_invalid_land_id'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }
    $stmt->close();

    // Insert case
    $sql = "INSERT INTO cases (land_id, title, case_type, description, reported_by, status, report_submitted, viewed, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logAction('prepare_statement_failed', 'Failed to prepare statement: ' . $conn->error, 'error');
        $conn->close();
        $_SESSION['error'] = $translations[$lang]['error_submission_failed'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }
    $stmt->bind_param("isssisii", $land_id, $title, $case_type, $description, $reported_by, $status, $report_submitted, $viewed);
    if ($stmt->execute()) {
        logAction('case_reported', "Case reported: $title by user ID $reported_by", 'info');
        $_SESSION['success'] = $translations[$lang]['success_report_submitted'];
        $stmt->close();
        $conn->close();
        header("Location: " . BASE_URL . "/modules/surveyor/dashboard.php?lang=$lang");
        ob_end_flush();
        exit;
    } else {
        logAction('case_report_failed', 'Failed to report case: ' . $stmt->error, 'error');
        $stmt->close();
        $conn->close();
        $_SESSION['error'] = $translations[$lang]['error_submission_failed'];
        header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
        ob_end_flush();
        exit;
    }
}

$conn->close();
header("Location: " . BASE_URL . "/modules/surveyor/report_case.php?lang=$lang");
ob_end_flush();
?>