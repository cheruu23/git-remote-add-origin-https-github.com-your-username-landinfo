<?php
require '../../includes/auth.php';
redirectIfNotLoggedIn();
if (!function_exists('isManager')) {
    function isManager() {
        return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'manager';
    }
}
if (!isManager()) {
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

// Mark notification as read if mark_read parameter is set
if (isset($_GET['mark_read'])) {
    $notification_id = (int)$_GET['mark_read'];
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
}

// Fetch reported cases
$sql = "SELECT c.id, c.title, c.description, c.status, u.username AS reported_by 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Reported'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Cases</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #f8f9fa; overflow-x: hidden; }
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
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
   

    <!-- Main Content -->
    <div class="content" id="main-content">
        <div class="container mt-4">
            <h2>Reported Cases</h2>
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Reported By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php 
                                $details = json_decode($row['description'], true);
                                $description_display = "Landowner: " . ($details['full_name'] ?? 'N/A') . 
                                                      ", Zone: " . ($details['zone'] ?? 'N/A') . 
                                                      ", Village: " . ($details['village'] ?? 'N/A') . 
                                                      ", Block: " . ($details['block_number'] ?? 'N/A');
                                if (!empty($details['other_case'])) {
                                    $description_display .= ", Other: " . $details['other_case'];
                                }
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($description_display); ?></td>
                                <td><?php echo htmlspecialchars($row['reported_by'] ?? 'Unknown'); ?></td>
                                <td><?php echo $row['status']; ?></td>
                                <td>
                                    <a href="receive_cases.php?case_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No reported cases found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

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

            if (toggleSidebar && sidebar && content && navbar) {
                toggleSidebar.addEventListener('click', function () {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('collapsed');
                    navbar.classList.toggle('collapsed');
                });
            }

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

            logoutLinks.forEach(link => {
                link.addEventListener('click', function (event) {
                    if (!confirm("Are you sure you want to logout?")) {
                        event.preventDefault();
                    }
                });
            });

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
