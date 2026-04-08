<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
$conn = getDBConnection();
logAction('add user', 'Admin accessed add user page', 'info');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
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
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Arial', sans-serif; }
        .container { margin-top: 20px; }
        .card { background: rgba(255, 255, 255, 0.9); border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        h3 { color: #203a43; }
        .alert-success { background: rgba(0, 255, 0, 0.2); border: none; }
        .alert-danger { background: rgba(255, 0, 0, 0.2); border: none; }
        .form-control, .form-select { border-radius: 5px; }
        .btn-primary { background: #203a43; border: none; }
        .btn-primary:hover { background: #1a2f36; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add New User</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
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
    </div>
</body>
</html>