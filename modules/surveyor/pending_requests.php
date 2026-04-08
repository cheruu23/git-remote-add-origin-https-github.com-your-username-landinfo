<?php
ob_start(); // Start output buffering
require_once '../../includes/init.php';

$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING) ?: 'en';
$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_STRING);
$debug_log = __DIR__ . '/debug.log';

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: ../login.php?lang=$lang");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user']['role'] ?? 'surveyor';

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Placeholder query for pending split requests
    $sql = "SELECT sr.id, sr.original_land_id, sr.status, sr.created_at, lr.owner_name 
            FROM split_requests sr 
            JOIN land_registration lr ON sr.original_land_id = lr.id 
            WHERE sr.surveyor_id = :user_id AND sr.status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = $translations[$lang]['db_error'] ?? "Database error occurred.";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['pending_requests'] ?? 'Pending Requests'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="main-content" id="main-content">
        <div class="container-fluid">
            <h1 class="h3 mb-4"><?php echo $translations[$lang]['pending_requests'] ?? 'Pending Requests'; ?></h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <!-- Toast Notification for Success -->
            <?php if ($success): ?>
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="successToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Requests Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['request_id'] ?? 'Request ID'; ?></th>
                                <th><?php echo $translations[$lang]['land_id'] ?? 'Land ID'; ?></th>
                                <th><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></th>
                                <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                                <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <?php echo $translations[$lang]['no_requests'] ?? 'No pending requests found.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['original_land_id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['status']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Sidebar toggle
            const toggleBtn = document.getElementById('toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            if (toggleBtn && sidebar && mainContent) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('collapsed');
                });
            }

            // Notification bell
            const bell = document.getElementById('notification-bell');
            const notifDropdown = document.getElementById('notification-dropdown');
            const badge = document.getElementById('notification-badge');
            const markAllReadBtn = document.getElementById('mark-all-read');

            if (bell && notifDropdown) {
                bell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('show');
                    if (notifDropdown.classList.contains('show')) {
                        fetchNotifications();
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!bell.contains(e.target) && !notifDropdown.contains(e.target)) {
                        notifDropdown.classList.remove('show');
                    }
                });
            }

            // Profile dropdown
            const profileBtn = document.getElementById('profile-btn');
            const profileDropdown = profileBtn?.nextElementSibling;
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });
            }

            // Show success toast if present
            const successToast = document.getElementById('successToast');
            if (successToast) {
                const toast = new bootstrap.Toast(successToast, {
                    delay: 5000 // Auto-dismiss after 5 seconds
                });
                toast.show();

                // Remove success parameter from URL to prevent re-display on refresh
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, '', url);
            }

            // Fetch notifications
            function fetchNotifications(retryCount = 0) {
                const maxRetries = 3;
                const content = document.getElementById('notification-content');
                if (!content) return;
                content.innerHTML = '<div class="notification-loading"><?php echo $translations[$lang]['loading'] ?? 'Loading...'; ?></div>';

                fetch('<?php echo BASE_URL; ?>/includes/fetch_notification.php?lang=<?php echo htmlspecialchars($lang); ?>', {
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error(`Invalid JSON: ${e.message}`);
                            }
                        });
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Invalid response from server');
                        }
                        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                        badge.textContent = data.unread_count > 0 ? data.unread_count : '';
                        markAllReadBtn.disabled = data.unread_count === 0;

                        content.innerHTML = '';
                        if (!data.notifications.length) {
                            content.innerHTML = '<div class="notification-item text-center text-muted"><?php echo $translations[$lang]['no_notifications'] ?? 'No notifications'; ?></div>';
                        } else {
                            data.notifications.forEach(notif => {
                                const isRead = notif.is_read || 0;
                                const link = notif.case_id
                                    ? `<?php echo BASE_URL; ?>/modules/<?php echo $role; ?>/managercase_view.php?case_id=${notif.case_id}&mark_read=${notif.id}&lang=<?php echo htmlspecialchars($lang); ?>`
                                    : `<?php echo BASE_URL; ?>/modules/profile.php?lang=<?php echo htmlspecialchars($lang); ?>`;
                                content.innerHTML += `
                                    <div class="notification-item ${isRead ? '' : 'unread'}">
                                        <a href="${link}">
                                            <span class="notification-message">${notif.message}</span>
                                            <span class="notification-time">${new Date(notif.created_at).toLocaleString('<?php echo $lang === 'om' ? 'om-ET' : 'en-US'; ?>', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</span>
                                        </a>
                                    </div>
                                `;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch notifications error:', error.message);
                        if (retryCount < maxRetries) {
                            setTimeout(() => fetchNotifications(retryCount + 1), 1000 * (retryCount + 1));
                        } else {
                            badge.style.display = <?php echo $unread_count > 0 ? "'flex'" : "'none'"; ?>;
                            badge.textContent = <?php echo $unread_count > 0 ? $unread_count : "''"; ?>;
                            content.innerHTML = `
                                <div class="notification-error">
                                    <?php echo $translations[$lang]['error_notifications'] ?? 'Failed to load notifications'; ?>
                                    <br><small>${error.message}</small>
                                </div>
                            `;
                        }
                    });
            }

            // Mark all notifications as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', () => {
                    fetch('<?php echo BASE_URL; ?>/includes/fetch_notification.php?mark_all_read=1&lang=<?php echo htmlspecialchars($lang); ?>', {
                        method: 'POST',
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                fetchNotifications();
                            } else {
                                console.error('Mark all read failed:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Mark all read error:', error);
                        });
                });
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>