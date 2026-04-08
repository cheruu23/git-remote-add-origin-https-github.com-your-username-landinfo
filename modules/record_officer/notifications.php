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

// Define role-specific case view paths
$case_view_paths = [
    'admin' => 'managercase_view.php',
    'manager' => 'managercase_view.php',
    'record_officer' => 'notifications.php',
    'surveyor' => 'view_case.php' // Changed from assigned_cases.php to ensure consistency
];

// Handle mark read via GET (when clicking notification link)
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

// Handle mark as read actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = $translations[$lang]['all_notifications_marked_read'] ?? "All notifications marked as read.";
                logAction('all_notifications_marked_read', "All notifications marked as read for user $user_id", 'info');
            } else {
                $error = $translations[$lang]['no_unread_notifications'] ?? "No unread notifications to mark.";
            }
        } else {
            $error = $translations[$lang]['mark_all_read_failed'] ?? "Failed to mark all notifications as read: " . $stmt->error;
            error_log("Mark all read failed for user $user_id: " . $stmt->error);
        }
        $stmt->close();
    }
}

// Fetch notifications
$notifications = [];
try {
    $stmt = $conn->prepare("SELECT id, case_id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error = $translations[$lang]['fetch_notifications_failed'] ?? "Failed to fetch notifications: " . $e->getMessage();
    error_log("Fetch notifications failed for user $user_id: " . $e->getMessage());
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['notifications'] ?? 'Notifications'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        h2.text-center {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            max-width: 300px;
            white-space: normal;
        }
        .table tr.unread {
            background: #dbeafe;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-secondary {
            background: #6b7280;
            border: none;
            border-radius: 6px;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .alert {
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            h2.text-center {
                font-size: 1.6rem;
            }
            .table {
                font-size: 0.9rem;
            }
            .table td {
                max-width: 200px;
            }
            .btn-sm {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['notifications'] ?? 'Notifications'; ?></h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card mt-4">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-end p-3">
                        <form action="" method="POST" onsubmit="return confirm('<?php echo $translations[$lang]['confirm_mark_all_read'] ?? 'Mark all notifications as read?'; ?>');">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-check-circle"></i> <?php echo $translations[$lang]['mark_all_read'] ?? 'Mark All as Read'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <p class="text-center text-muted m-3"><?php echo $translations[$lang]['no_notifications'] ?? 'No notifications found.'; ?></p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                    <th><?php echo $translations[$lang]['message'] ?? 'Message'; ?></th>
                                    <th><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?></th>
                                    <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                                    <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                                    <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr class="<?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                        <td><?php echo htmlspecialchars($notification['id']); ?></td>
                                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                        <td>
                                            <?php if ($notification['case_id'] && isset($case_view_paths[$role])): ?>
                                                <?php
                                                $case_view_url = BASE_URL . '/modules/' . htmlspecialchars($role) . '/' . $case_view_paths[$role] . '?case_id=' . htmlspecialchars($notification['case_id']) . '&mark_read=' . htmlspecialchars($notification['id']) . '&lang=' . htmlspecialchars($lang);
                                                // Check if the case view file exists
                                                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/landinfo/modules/' . $role . '/' . $case_view_paths[$role];
                                                if (file_exists($file_path)): ?>
                                                    <a href="<?php echo $case_view_url; ?>">
                                                        <?php echo htmlspecialchars($notification['case_id']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($notification['case_id']); ?>
                                                    <span class="text-muted small">(<?php echo $translations[$lang]['case_view_unavailable'] ?? 'Case view unavailable'; ?>)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo $translations[$lang]['n_a'] ?? 'N/A'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $notification['is_read'] ? ($translations[$lang]['read'] ?? 'Read') : ($translations[$lang]['unread'] ?? 'Unread'); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($notification['created_at'])); ?></td>
                                        <td>
                                            <?php if (!$notification['is_read']): ?>
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('<?php echo $translations[$lang]['confirm_mark_read'] ?? 'Mark this notification as read?'; ?>');">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo htmlspecialchars($notification['id']); ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-check"></i> <?php echo $translations[$lang]['mark_read'] ?? 'Mark as Read'; ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted"><?php echo $translations[$lang]['read'] ?? 'Read'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>