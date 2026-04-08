<?php
require '../../includes/init.php';

redirectIfNotLoggedIn();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }

    $action = $input['action'] ?? '';
    $details = $input['details'] ?? '';
    $severity = $input['severity'] ?? 'info';
    $user_id = $_SESSION['user']['id'] ?? null;

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'Action required']);
        exit;
    }

    // Log the event
    if (logAction($action, $details, $severity, $user_id)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to log event']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>