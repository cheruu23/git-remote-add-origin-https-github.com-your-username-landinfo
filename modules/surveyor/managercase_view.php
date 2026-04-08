<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info');

$lang = $_GET['lang'] ?? 'en';
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// Initialize messages
$success = null;
$error = null;

// Handle mark read via GET
if (isset($_GET['mark_read'])) {
    $notification_id = filter_input(INPUT_GET, 'mark_read', FILTER_VALIDATE_INT);
    if ($notification_id) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $notification_id, $user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = $translations[$lang]['notification_marked_read'] ?? "Notification marked as read.";
                logAction('notification_marked_read', "Notification $notification_id marked as read for user $user_id", 'info');
            } else {
                $error = $translations[$lang]['notification_already_read'] ?? "Notification already read or not found.";
            }
        } else {
            $error = $translations[$lang]['mark_read_failed'] ?? "Failed to mark notification as read: " . $stmt->error;
            error_log("Mark read failed for notification $notification_id: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error = $translations[$lang]['invalid_notification_id'] ?? "Invalid notification ID.";
    }
}

// Fetch case details
$case_id = filter_input(INPUT_GET, 'case_id', FILTER_VALIDATE_INT);
$case = null;
if ($case_id) {
    $sql = "SELECT c.id, c.title, c.description, c.status, c.created_at, u.username AS reported_by
            FROM cases c
            LEFT JOIN users u ON c.reported_by = u.id
            WHERE c.id = ? AND (c.assigned_to = ? OR c.reported_by = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $case_id, $user_id, $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $case = $result->fetch_assoc();
            $case['description'] = json_decode($case['description'], true) ?: [];
        } else {
            $error = $translations[$lang]['case_not_found'] ?? "Case not found or you lack permission.";
            error_log("Case $case_id not found or inaccessible for user $user_id");
        }
    } else {
        $error = $translations[$lang]['fetch_failed'] ?? "Failed to fetch case: " . $stmt->error;
        error_log("Fetch failed for case $case_id: " . $stmt->error);
    }
    $stmt->close();
} else {
    $error = $translations[$lang]['invalid_case_id'] ?? "Invalid case ID.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['view_case'] ?? 'View Case'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border-radius: 10px 10px 0 0;
        }
        .alert {
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['view_case'] ?? 'View Case'; ?></h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($case): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $translations[$lang]['case_details'] ?? 'Case Details'; ?> (ID: <?php echo htmlspecialchars($case['id']); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <p><strong><?php echo $translations[$lang]['title'] ?? 'Title'; ?>:</strong> <?php echo htmlspecialchars($case['title']); ?></p>
                        <p><strong><?php echo $translations[$lang]['status'] ?? 'Status'; ?>:</strong> <?php echo htmlspecialchars($translations[$lang][strtolower($case['status'])] ?? $case['status']); ?></p>
                        <p><strong><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?>:</strong> <?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></p>
                        <p><strong><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?>:</strong> <?php echo date('Y-m-d H:i:s', strtotime($case['created_at'])); ?></p>
                        <p><strong><?php echo $translations[$lang]['description'] ?? 'Description'; ?>:</strong></p>
                        <ul>
                            <?php foreach ($case['description'] as $key => $value): ?>
                                <li><strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>