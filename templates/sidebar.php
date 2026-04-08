<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/languages.php';
require_once dirname(__DIR__) . '/includes/language_switcher.php';

redirectIfNotLoggedIn();

// Language handling
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

$user = $_SESSION['user'];
$user_id = $_SESSION['user_id'] ?? $user['id'];
$role = $user['role'];

// Log session data for debugging
error_log('Sidebar session data: ' . print_r($_SESSION, true));

// Cache settings in session
if (!isset($_SESSION['settings'])) {
    try {
        $settings_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('navbar_color', 'navbar_logo', 'sidebar_color', 'sidebar_logo')";
        $settings_result = $conn->query($settings_sql);
        $_SESSION['settings'] = [];
        while ($row = $settings_result->fetch_assoc()) {
            $_SESSION['settings'][$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        logAction('fetch_settings_failed', 'Failed to fetch settings: ' . $e->getMessage(), 'error');
    }
}
$navbar_color = $_SESSION['settings']['navbar_color'] ?? '#1e40af';
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$sidebar_color = $_SESSION['settings']['sidebar_color'] ?? '#1e3a8a';
$sidebar_logo = $_SESSION['settings']['sidebar_logo'] ?? 'assets/images/default_logo.png';

// Fetch unread notification count
$unread_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
    $stmt->close();
    logAction('fetch_unread_count', 'Fetched unread count: ' . $unread_count, 'info', ['user_id' => $user_id, 'role' => $role]);
} catch (Exception $e) {
    logAction('fetch_unread_count_failed', 'Failed to fetch notification count for user ' . $user_id . ': ' . $e->getMessage(), 'error', ['role' => $role]);
}

$conn->close();

// Define menus with translated labels
$menus = [
    'admin' => [
        [$translations[$lang]['dashboard'], BASE_URL . '/modules/admin/dashboard.php?lang=' . $lang, 'fa-tachometer-alt'],
        [$translations[$lang]['manage_users'], BASE_URL . '/modules/admin/manage_user.php?lang=' . $lang, 'fa-users'],
        [$translations[$lang]['system_settings'], BASE_URL . '/modules/admin/systemsettings.php?lang=' . $lang, 'fa-cog'],
        [$translations[$lang]['logs'], BASE_URL . '/modules/admin/view_logs.php?lang=' . $lang, 'fa-history'],
        [$translations[$lang]['profile'], BASE_URL . '/modules/profile.php?lang=' . $lang, 'fa-user'],
        [$translations[$lang]['logout'], BASE_URL . '/public/logout.php?lang=' . $lang, 'fa-sign-out-alt']
    ],
    'manager' => [
        [$translations[$lang]['dashboard'], BASE_URL . '/modules/manager/dashboard.php?lang=' . $lang, 'fa-tachometer-alt'],
        [$translations[$lang]['review_cases'], BASE_URL . '/modules/manager/review_cases.php?lang=' . $lang, 'fa-file-alt'],
        [$translations[$lang]['assign_case'], BASE_URL . '/modules/manager/assign_case.php?lang=' . $lang, 'fa-tasks'],
        [$translations[$lang]['stamp'], BASE_URL . '/modules/manager/upload_stamp.php?lang=' . $lang, 'fa-stamp'],
        [$translations[$lang]['support_requeest'], BASE_URL . '/modules/manager/support_letter_approval.php?lang=' . $lang, 'fa-stamp'],
        [$translations[$lang]['profile'], BASE_URL . '/modules/profile.php?lang=' . $lang, 'fa-user'],
        [$translations[$lang]['logout'], BASE_URL . '/public/logout.php?lang=' . $lang, 'fa-sign-out-alt']
    ],
    'record_officer' => [
        [$translations[$lang]['dashboard'], BASE_URL . '/modules/record_officer/dashboard.php?lang=' . $lang, 'fa-tachometer-alt'],
        [$translations[$lang]['register_land'], BASE_URL . '/modules/record_officer/registration.php?lang=' . $lang, 'fa-plus-circle'],
        [$translations[$lang]['report_case'], BASE_URL . '/modules/record_officer/Report_Case.php?lang=' . $lang, 'fa-file-alt'],
        [$translations[$lang]['new user cases'], BASE_URL . '/modules/record_officer/support_letter_request.php?lang=' . $lang, 'fa-briefcase'],
        [$translations[$lang]['profile'], BASE_URL . '/modules/profile.php?lang=' . $lang, 'fa-user'],
        [$translations[$lang]['logout'], BASE_URL . '/public/logout.php?lang=' . $lang, 'fa-sign-out-alt']
    ],
    'surveyor' => [
        [$translations[$lang]['dashboard'], BASE_URL . '/modules/surveyor/dashboard.php?lang=' . $lang, 'fa-tachometer-alt'],
        [$translations[$lang]['assigned_cases'], BASE_URL . '/modules/surveyor/assigned_cases.php?lang=' . $lang, 'fa-tasks'],
        [$translations[$lang]['profile'], BASE_URL . '/modules/profile.php?lang=' . $lang, 'fa-user'],
        [$translations[$lang]['logout'], BASE_URL . '/public/logout.php?lang=' . $lang, 'fa-sign-out-alt']
    ]
];
$active_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" async>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" async>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
            overflow-x: hidden;
        }

        .navbar {
            background: <?php echo htmlspecialchars($navbar_color); ?> !important;
            padding: 10px 20px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 60px;
            display: flex;
            align-items: center;
        }

        .navbar-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-right: 10px;
        }

        .navbar-brand {
            color: #fff;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
        }

        .navbar-brand:hover {
            color: #e2e8f0;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 15px;
        }

        .notification-bell {
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            position: relative;
            margin-right: 20px;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc2626;
            color: #fff;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 20px;
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 10px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
            position: relative;
        }

        .notification-item:hover {
            background: #f3f4f6;
        }

        .notification-item.unread {
            background: #dbeafe;
            font-weight: 600;
        }

        .notification-item a {
            text-decoration: none;
            color: #374151;
            display: flex;
            flex-direction: column;
        }

        .notification-message {
            font-size: 0.9rem;
            word-break: break-word;
        }

        .notification-time {
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        .notification-loading {
            padding: 10px;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .notification-error {
            padding: 10px;
            text-align: center;
            color: #dc2626;
            font-size: 0.9rem;
        }

        .mark-all-read,
        .toggle-unread {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .mark-all-read:hover,
        .toggle-unread:hover {
            background: #1e40af;
        }

        .mark-all-read:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .profile-btn {
            background: none;
            border: none;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .profile-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .profile-info {
            text-align: left;
        }

        .profile-info span {
            display: block;
            font-size: 0.9rem;
        }

        .profile-info .role {
            font-size: 0.7rem;
            color: #e2e8f0;
        }

        .dropdown-menu {
            top: 50px !important;
            right: 0;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            width: 250px;
            background: <?php echo htmlspecialchars($sidebar_color); ?>;
            color: #fff;
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            z-index: 900;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar-header {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #3b82f6;
        }

        .sidebar-profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-bottom: 10px;
        }

        .sidebar-header h5 {
            font-size: 1rem;
            color: #fff;
            margin: 0;
        }

        .sidebar-header small {
            color: #e2e8f0;
            font-size: 0.8rem;
        }

        .sidebar.collapsed .sidebar-header h5,
        .sidebar.collapsed .sidebar-header small {
            display: none;
        }

        .nav-link {
            color: #e2e8f0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            background: #3b82f6;
            color: #fff;
        }

        .nav-link.active {
            background: #2563eb;
        }

        .nav-link i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .nav-link span {
            display: inline-block;
        }

        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px 0;
        }

        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
        }

        .main-content.collapsed {
            margin-left: 60px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: fixed;
                top: 60px;
                left: -100%;
                transition: left 0.3s ease;
            }

            .sidebar.show {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .toggle-btn {
                display: block;
            }

            .notification-dropdown {
                width: 90%;
                right: 5%;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="toggle-btn" id="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/modules/<?php echo $role; ?>/dashboard.php?lang=<?php echo htmlspecialchars($lang); ?>">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo" class="navbar-logo">
                <?php echo $translations[$lang]['system_name'] ?? 'Land Management'; ?> - <?php echo $translations[$lang][$role] ?? ucfirst($role); ?>
            </a>
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Notifications -->
                <li class="nav-item">
                    <div class="notification-bell" id="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" id="notification-badge" style="display: <?php echo $unread_count > 0 ? 'flex' : 'none'; ?>;">
                            <?php echo $unread_count > 0 ? htmlspecialchars($unread_count) : ''; ?>
                        </span>
                    </div>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">
                            <?php echo $translations[$lang]['notifications'] ?? 'Notifications'; ?>
                            <div>
                                <button class="toggle-unread" id="toggle-unread"><?php echo $translations[$lang]['show_all'] ?? 'Show All'; ?></button>
                                <button class="mark-all-read" id="mark-all-read" <?php echo $unread_count == 0 ? 'disabled' : ''; ?>>
                                    <?php echo $translations[$lang]['mark_all_read'] ?? 'Mark All as Read'; ?>
                                </button>
                            </div>
                        </div>
                        <div id="notification-content" class="notification-loading">
                            <?php echo $translations[$lang]['loading'] ?? 'Loading...'; ?>
                        </div>
                    </div>
                </li>
                <!-- Profile Section -->
                <li class="nav-item profile-dropdown ms-3">
                    <button class="profile-btn" id="profile-btn">
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['photo']); ?>" alt="Profile" class="profile-pic" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default.png';">
                        <div class="profile-info">
                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                            <span class="role"><?php echo $translations[$lang][$role] ?? ucfirst($role); ?></span>
                        </div>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/profile.php?lang=<?php echo htmlspecialchars($lang); ?>"><?php echo $translations[$lang]['profile'] ?? 'Profile'; ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/public/logout.php?lang=<?php echo htmlspecialchars($lang); ?>"><?php echo $translations[$lang]['logout'] ?? 'Logout'; ?></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['photo']); ?>" alt="Profile" class="sidebar-profile-pic" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default.png';">
            <h5><?php echo htmlspecialchars($user['full_name']); ?></h5>
            <small><?php echo $translations[$lang][$role] ?? ucfirst($role); ?></small>
        </div>
        <ul class="nav flex-column">
            <?php
            if (isset($menus[$role]) && is_array($menus[$role])) {
                foreach ($menus[$role] as $menu): ?>
                    <li class="nav-item">
                        <a href="<?php echo $menu[1]; ?>" class="nav-link <?php echo $active_page === basename(parse_url($menu[1], PHP_URL_PATH)) ? 'active' : ''; ?>">
                            <i class="fas <?php echo $menu[2]; ?>"></i>
                            <span><?php echo $menu[0]; ?></span>
                        </a>
                    </li>
                <?php endforeach;
            } else { ?>
                <li class="nav-item">
                    <a href="#" class="nav-link"><?php echo $translations[$lang]['no_menu'] ?? 'No Menu Available'; ?></a>
                </li>
            <?php } ?>
        </ul>
    </aside>

    <div class="main-content" id="main-content">

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" async></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const toggleBtn = document.getElementById('toggle-sidebar');
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('main-content');
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    sidebar.classList.toggle('show');
                    mainContent.classList.toggle('collapsed');
                });

                const bell = document.getElementById('notification-bell');
                const notifDropdown = document.getElementById('notification-dropdown');
                const badge = document.getElementById('notification-badge');
                const markAllReadBtn = document.getElementById('mark-all-read');
                const toggleUnreadBtn = document.getElementById('toggle-unread');
                let showOnlyUnread = true;

                console.log('Initial unread count:', <?php echo $unread_count; ?>);

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

                const profileBtn = document.getElementById('profile-btn');
                const profileDropdown = profileBtn.nextElementSibling;
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });

                function fetchNotifications(retryCount = 0) {
                    const maxRetries = 3;
                    const content = document.getElementById('notification-content');
                    const fetchUrl = `<?php echo BASE_URL; ?>/includes/fetch_notifications.php?lang=<?php echo htmlspecialchars($lang); ?>&only_unread=${showOnlyUnread ? 1 : 0}`;
                    console.log('Fetching notifications from:', fetchUrl); // Debug URL
                    content.innerHTML = '<div class="notification-loading"><?php echo $translations[$lang]['loading'] ?? 'Loading...'; ?></div>';

                    fetch(fetchUrl, {
                            credentials: 'same-origin'
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.text().then(text => {
                                console.log('Raw response:', text); // Debug raw response
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    throw new Error(`Invalid JSON: ${e.message}`);
                                }
                            });
                        })
                        .then(data => {
                            console.log('Parsed response:', data); // Debug parsed data
                            if (!data.success) {
                                throw new Error(data.message || 'Invalid response from server');
                            }
                            badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                            badge.textContent = data.unread_count > 0 ? data.unread_count : '';
                            console.log('Updating badge:', data.unread_count);
                            markAllReadBtn.disabled = data.unread_count === 0;

                            content.innerHTML = '';
                            if (!data.notifications.length) {
                                content.innerHTML = '<div class="notification-item text-center text-muted"><?php echo $translations[$lang]['no_notifications'] ?? 'No notifications'; ?></div>';
                            } else {
                                data.notifications.forEach(notif => {
                                    const isRead = notif.is_read || 0;
                                    let link = '#'; // Default: no redirect
                                    if (notif.type === 'case' && notif.case_id) {
                                        link = `<?php echo BASE_URL; ?>/modules/<?php echo $role; ?>/managercase_view.php?case_id=${notif.case_id}&mark_read=${notif.id}&lang=<?php echo htmlspecialchars($lang); ?>`;
                                    } else if (notif.type === 'split_request' && notif.request_id) {
                                        link = `<?php echo BASE_URL; ?>/modules/<?php echo $role; ?>/split_request_view.php?request_id=${notif.request_id}&mark_read=${notif.id}&lang=<?php echo htmlspecialchars($lang); ?>`;
                                    }
                                    content.innerHTML += `
                        <div class="notification-item ${isRead ? '' : 'unread'}" data-notification-id="${notif.id}">
                            <a href="${link}" class="notification-link">
                                <span class="notification-message">${notif.message}</span>
                                <span class="notification-time">${new Date(notif.created_at).toLocaleString('<?php echo $lang === 'om' ? 'om-ET' : 'en-US'; ?>', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</span>
                            </a>
                        </div>
                    `;
                                });

                                document.querySelectorAll('.notification-link').forEach(link => {
                                    link.addEventListener('click', (e) => {
                                        const notificationItem = link.closest('.notification-item');
                                        const notificationId = notificationItem.dataset.notificationId;
                                        console.log('Notification clicked:', {
                                            id: notificationId,
                                            link: link.getAttribute('href')
                                        }); // Debug click
                                        if (link.getAttribute('href') === '#') {
                                            e.preventDefault();
                                            markNotificationAsRead(notificationId, notificationItem);
                                        }
                                    });
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
                                console.log('Falling back to initial unread count:', <?php echo $unread_count; ?>);
                                content.innerHTML = `
                    <div class="notification-error">
                        <?php echo $translations[$lang]['error_notifications'] ?? 'Failed to load notifications'; ?>
                        <br><small>${error.message} (URL: ${fetchUrl})</small>
                    </div>
                `;
                            }
                        });
                }

                function markNotificationAsRead(notificationId, notificationItem) {
                    fetch(`<?php echo BASE_URL; ?>/includes/fetch_notifications.php?lang=<?php echo htmlspecialchars($lang); ?>&mark_read=${notificationId}`, {
                            method: 'GET',
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationItem.remove();
                                fetchNotifications();
                            } else {
                                console.error('Failed to mark notification as read:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error marking notification as read:', error);
                        });
                }

                markAllReadBtn.addEventListener('click', () => {
                    fetch('<?php echo BASE_URL; ?>/includes/fetch_notifications.php?mark_all_read=1&lang=<?php echo htmlspecialchars($lang); ?>', {
                            method: 'GET',
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
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

                if (toggleUnreadBtn) {
                    toggleUnreadBtn.addEventListener('click', () => {
                        showOnlyUnread = !showOnlyUnread;
                        toggleUnreadBtn.textContent = showOnlyUnread ? '<?php echo $translations[$lang]['show_all'] ?? 'Show All'; ?>' : '<?php echo $translations[$lang]['show_unread'] ?? 'Show Unread'; ?>';
                        fetchNotifications();
                    });
                }
            });
        </script>
</body>

</html>