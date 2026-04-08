<?php
require '../../includes/auth.php';
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    die("Access denied!");
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "landinfo_new";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch unread notification count for the current user
$user_id = $_SESSION['user_id']; // Assuming user_id is stored in session after login
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($unread_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];

// Fetch recent notifications
$notifications_query = "SELECT n.id, n.message, n.created_at, n.case_id 
                        FROM notifications n 
                        WHERE n.user_id = ? 
                        ORDER BY n.created_at DESC 
                        LIMIT 5";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$notifications = [];
while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Officer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #343a40;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
        }
        .profile-sidebar {
            text-align: center;
            padding: 40px;
            border-bottom: 1px solid #4b545c;
        }
        .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #007bff;
            cursor: pointer;
        }
        .profile-dropdown {
            display: none;
            background-color: #2c3136;
            margin-top: 10px;
            border-radius: 5px;
            padding: 10px;
        }
        .profile-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .profile-dropdown ul li {
            margin: 10px 0;
        }
        .profile-dropdown ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 5px;
            border-radius: 5px;
        }
        .profile-dropdown ul li a:hover {
            background-color: #007bff;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 15px 20px;
            border-bottom: 1px solid #4b545c;
        }
        .sidebar ul li a:hover {
            background-color: #007bff;
        }
        .sidebar ul li a i {
            margin-right: 10px;
        }
        .navbar {
            padding: 10px 20px;
            background-color: #343a40 !important;
            position: fixed;
            top: 0;
            left: 250px;
            width: calc(100% - 250px);
            z-index: 999;
            transition: left 0.3s ease, width 0.3s ease;
        }
        .navbar.collapsed {
            left: 0;
            width: 100%;
        }
        #toggle-sidebar {
            cursor: pointer;
        }
        .content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        .content.collapsed {
            margin-left: 0;
        }
        .navbar-logo {
            width: 30px;
            height: 30px;
            margin-right: 10px;
        }
        .dropdown-menu-notifications {
            max-height: 300px;
            overflow-y: auto;
            width: 300px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="profile-sidebar">
            <img src="../../assets/images/profile.png" class="profile-pic" id="profile-pic">
            <div class="profile-dropdown" id="profile-dropdown">
                <ul>
                    <li><a href="#"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="../../public/logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_landowners.php"><i class="fas fa-users"></i> Register Land</a></li>
            <li><a href="Report_Case.php"><i class="fas fa-file-alt"></i> Report Cases</a></li>
            <li><a href="reported_cases.php"><i class="fas fa-file-alt"></i> Reported Cases</a></li>
            <li><a href="assigned_cases.php"><i class="fas fa-tasks"></i> Assigned Cases</a></li>
            <li><a href="approved_cases.php"><i class="fas fa-check-circle"></i> Approved Cases</a></li>
            <li><a href="unapproved_cases.php"><i class="fas fa-times-circle"></i> Unapproved Cases</a></li>
            <li><a href="../../public/logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <button class="btn btn-outline-light" id="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <a class="navbar-brand ms-3" href="#">
            <img src="/assets/images/images.jpg" alt="Logo" class="navbar-logo">
            LIMS - Record Officer
        </a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-notifications" aria-labelledby="notificationDropdown">
                    <?php if (empty($notifications)): ?>
                        <li><span class="dropdown-item">No new notifications</span></li>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <li>
                                <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'fw-bold'; ?>" 
                                   href="reported_cases.php?case_id=<?php echo $notification['case_id']; ?>&mark_read=<?php echo $notification['id']; ?>">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                    <small class="text-muted d-block"><?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </li>
        </ul>
    </nav>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleSidebar = document.getElementById('toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('main-content');
            const navbar = document.querySelector('.navbar');
            const profilePic = document.getElementById('profile-pic');
            const profileDropdown = document.getElementById('profile-dropdown');
            const logoutLinks = document.querySelectorAll('#logout-link');

            // Toggle Sidebar
            if (toggleSidebar && sidebar && content && navbar) {
                toggleSidebar.addEventListener('click', function () {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('collapsed');
                    navbar.classList.toggle('collapsed');
                });
            }

            // Profile Dropdown Toggle
            if (profilePic && profileDropdown) {
                profilePic.addEventListener('click', function (event) {
                    event.stopPropagation();
                    profileDropdown.style.display = profileDropdown.style.display === 'block' ? 'none' : 'block';
                });
                document.addEventListener('click', function (event) {
                    if (!profilePic.contains(event.target)) {
                        profileDropdown.style.display = 'none';
                    }
                });
            }

            // Logout Confirmation
            logoutLinks.forEach(link => {
                link.addEventListener('click', function (event) {
                    if (!confirm("Are you sure you want to logout?")) {
                        event.preventDefault();
                    }
                });
            });

            // Refresh notifications every 10 seconds
            setInterval(function() {
                fetchNotifications();
            }, 10000);

            function fetchNotifications() {
                fetch('fetch_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('#notificationDropdown .badge');
                        const dropdown = document.querySelector('.dropdown-menu-notifications');
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                        dropdown.innerHTML = '';
                        if (data.notifications.length === 0) {
                            dropdown.innerHTML = '<li><span class="dropdown-item">No new notifications</span></li>';
                        } else {
                            data.notifications.forEach(notif => {
                                const item = document.createElement('li');
                                item.innerHTML = `<a class="dropdown-item ${notif.is_read ? '' : 'fw-bold'}" 
                                    href="reported_cases.php?case_id=${notif.case_id}&mark_read=${notif.id}">
                                    ${notif.message}
                                    <small class="text-muted d-block">${new Date(notif.created_at).toLocaleString()}</small>
                                </a>`;
                                dropdown.appendChild(item);
                            });
                        }
                    });
            }
        });
    </script>
</body>
</html>