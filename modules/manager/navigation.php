<?php
require_once '../../includes/auth.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    die("Access denied!");
}

$conn = new mysqli("localhost", "root", "", "landinfo_new");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch unread count and notifications
$user_id = $_SESSION['user']['id'];
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($unread_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];

$notifications_query = "SELECT n.id, n.message, n.created_at, n.case_id, n.is_read, u.username as user_name 
                        FROM notifications n 
                        LEFT JOIN users u ON n.user_id = u.id 
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
$conn->close();
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="profile-sidebar">
        <img src="../../assets/images/profile.png" class="profile-pic" id="profile-pic" alt="Profile Picture">
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
        <li><a href="review_cases.php"><i class="fas fa-clipboard-list"></i> Review Cases</a></li>
        <li><a href="assign_case.php"><i class="fas fa-check-circle"></i> Assign Case</a></li>
        <li><a href="../../public/logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <button class="btn btn-outline-light" id="toggle-sidebar"><i class="fas fa-bars"></i></button>
    <a class="navbar-brand ms-3" href="#">
        <img src="/assets/images/images.jpg" alt="Logo" class="navbar-logo">
        LIMS - Manager
    </a>
    <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger rounded-pill" id="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-notifications" aria-labelledby="notificationDropdown" id="notification-list">
                <?php if (empty($notifications)): ?>
                    <li><span class="dropdown-item">No new notifications</span></li>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <li>
                            <a class="dropdown-item <?php echo isset($notification['is_read']) && $notification['is_read'] ? '' : 'fw-bold'; ?>" 
                               href="view_case.php?id=<?php echo $notification['case_id']; ?>&mark_read=<?php echo $notification['id']; ?>">
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

<!-- Styles -->
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
        z-index: 1001; /* Higher than sidebar to stay on top */
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
        margin-top: 60px; /* Space for fixed navbar */
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

<!-- Audio for Notification Sound -->
<audio id="notification-sound" src="../../assets/sounds/notification.mp3"></audio>