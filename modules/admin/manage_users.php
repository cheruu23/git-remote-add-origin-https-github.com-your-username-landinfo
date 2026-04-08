<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
$conn = getDBConnection();
logAction('manage user', 'Admin accessed manage user page', 'info');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        $photo = 'assets/images/default_profile.png'; // Default photo

        // Validate inputs
        if (empty($username) || empty($full_name) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check for duplicate username or email
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                // Handle photo upload
                if (!empty($_FILES['photo']['name'])) {
                    $file = $_FILES['photo'];
                    $target_dir = dirname(__DIR__, 2) . '/assets/images/';
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $target_file = $target_dir . 'user_' . time() . '_' . basename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        $photo = 'assets/images/user_' . time() . '_' . basename($file['name']);
                    }
                }

                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user into database
                $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, photo, is_locked) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->bind_param("ssssss", $username, $full_name, $email, $hashed_password, $role, $photo);
                if ($stmt->execute()) {
                    $success = "User added successfully!";
                } else {
                    $error = "Failed to add user: " . $conn->error;
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['lock_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET is_locked = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User account locked successfully!";
        } else {
            $error = "Failed to lock user: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['unlock_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET is_locked = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User account unlocked successfully!";
        } else {
            $error = "Failed to unlock user: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        // Validate inputs
        if (empty($username) || empty($full_name) || empty($email)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check for duplicate username or email (excluding current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Username or email already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $username, $full_name, $email, $role, $user_id);
                if ($stmt->execute()) {
                    $success = "User updated successfully!";
                } else {
                    $error = "Failed to update user: " . $conn->error;
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['remove_user'])) {
        $user_id = $_POST['user_id'];
        // Delete related records in password_resets first
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Now delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "User removed successfully!";
            } else {
                $error = "Failed to remove user: " . $conn->error;
            }
        } else {
            $error = "Failed to remove related password reset records: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all users
$users = query("SELECT id, username, full_name, email, role, photo, is_locked FROM users")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Arial', sans-serif; }
        .container { margin-top: 20px; }
        .card { background: rgba(255, 255, 255, 0.9); border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        h3 { color: #203a43; }
        .alert-success { background: rgba(0, 255, 0, 0.2); border: none; }
        .alert-danger { background: rgba(255, 0, 0, 0.2); border: none; }
        .profile-pic { max-width: 50px; border-radius: 50%; }
        .form-control, .form-select { border-radius: 5px; }
        .btn-primary { background: #203a43; border: none; }
        .btn-primary:hover { background: #1a2f36; }
        .btn-sm { margin: 2px; }
        .table th, .table td { vertical-align: middle; }
        .btn-success { background: #28a745; border: none; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; border: none; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; border: none; }
        .btn-danger:hover { background: #c82333; }
        .nav-buttons { margin-bottom: 20px; }
        .nav-buttons .btn { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>User Management</h2>
        <div class="nav-buttons">
            <button class="btn btn-primary" onclick="scrollToSection('add-user-section')">Add User</button>
            <button class="btn btn-primary" onclick="scrollToSection('manage-users-section')">Manage User</button>
        </div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add User -->
        <div class="card" id="add-user-section">
            <h3>Add New User</h3>
            <form method="POST" enctype="multipart/form-data" class="mb-4">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="record_officer">Record Officer</option>
                        <option value="surveyor">Surveyor</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
            </form>
        </div>

        <!-- Current Users -->
        <div class="card" id="manage-users-section">
            <h3>Current Users</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Photo</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['photo']); ?>" alt="User Photo" class="profile-pic" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default_profile.png'"></td>
                            <td><?php echo $user['is_locked'] ? 'Locked' : 'Active'; ?></td>
                            <td>
                                <!-- Lock/Unlock Button -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <?php if ($user['is_locked']): ?>
                                        <button type="submit" name="unlock_user" class="btn btn-success btn-sm">Unlock</button>
                                    <?php else: ?>
                                        <button type="submit" name="lock_user" class="btn btn-warning btn-sm">Lock</button>
                                    <?php endif; ?>
                                </form>
                                <!-- Update Button -->
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $user['id']; ?>">Update</button>
                                <!-- Remove Button -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="remove_user" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Update Modal -->
                        <div class="modal fade" id="updateModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="updateModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="updateModalLabel<?php echo $user['id']; ?>">Update User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <select name="role" class="form-control">
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                    <option value="record_officer" <?php echo $user['role'] === 'record_officer' ? 'selected' : ''; ?>>Record Officer</option>
                                                    <option value="surveyor" <?php echo $user['role'] === 'surveyor' ? 'selected' : ''; ?>>Surveyor</option>
                                                </select>
                                            </div>
                                            <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function scrollToSection(sectionId) {
            document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>