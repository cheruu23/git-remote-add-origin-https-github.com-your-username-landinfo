<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/languages.php';
require_once dirname(__DIR__) . '/includes/language_switcher.php';
redirectIfNotLoggedIn();
include dirname(__DIR__) . '/templates/sidebar.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die($translations[$lang]['db_connection_failed'] ?? "Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info');

$lang = $_GET['lang'] ?? 'en'; // Default to English
$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {
    logAction('session_error', 'User ID not found in session', 'error');
    die($translations[$lang]['session_error'] ?? "Session error: User not found.");
}
error_log("User ID: $user_id");

// Fetch current user data
$stmt = $conn->prepare("SELECT username, full_name, email, password, photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    logAction('user_not_found', "User ID $user_id not found in database", 'error');
    die($translations[$lang]['user_not_found'] ?? "User not found in database.");
}
error_log("User fetched: Username: {$user['username']}, Photo: {$user['photo']}");

// Handle profile update (excluding password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $photo = $user['photo'];

    // Validate inputs
    if (empty($full_name)) {
        $error = $translations[$lang]['full_name_required'] ?? "Full name is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = $translations[$lang]['invalid_email'] ?? "Invalid email format.";
    } else {
        // Handle photo upload
        if (!empty($_FILES['photo']['name'])) {
            $file = $_FILES['photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                $error = $translations[$lang]['invalid_photo_type'] ?? "Only JPEG, PNG, or GIF images are allowed.";
                error_log("Invalid photo type: {$file['type']} for user $user_id");
            } else {
                $target_dir = dirname(__DIR__) . '/assets/images/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $target_file = $target_dir . 'user_' . $user_id . '_' . time() . '_' . basename($file['name']);
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $photo = 'assets/images/user_' . $user_id . '_' . time() . '_' . basename($file['name']);
                    logAction('photo_uploaded', "Photo uploaded: $photo for user $user_id", 'info');
                } else {
                    $error = $translations[$lang]['photo_upload_failed'] ?? "Failed to upload photo.";
                    error_log("Photo upload failed: {$file['error']} for user $user_id");
                }
            }
        }

        if (!isset($error)) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, photo = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $photo, $user_id);
            if ($stmt->execute()) {
                $success = $translations[$lang]['profile_updated'] ?? "Profile updated successfully!";
                $_SESSION['user']['full_name'] = $full_name;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['photo'] = $photo;
                logAction('profile_updated', "Profile updated: Full Name: $full_name, Email: $email, Photo: $photo for user $user_id", 'info');
            } else {
                $error = $translations[$lang]['profile_update_failed'] ?? "Failed to update profile: " . $conn->error;
                error_log("Profile update failed: {$conn->error} for user $user_id");
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    error_log("Password change POST received: " . print_r($_POST, true));

    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    error_log("Form data - Current: [hidden], New: [hidden], Confirm: [hidden]");

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = $translations[$lang]['password_fields_required'] ?? "All password fields are required.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $password_error = $translations[$lang]['current_password_incorrect'] ?? "Current password is incorrect.";
        error_log("Password verification failed for user $user_id");
    } elseif ($new_password !== $confirm_password) {
        $password_error = $translations[$lang]['passwords_not_match'] ?? "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = $translations[$lang]['password_too_short'] ?? "New password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $password_success = $translations[$lang]['password_changed'] ?? "Password changed successfully!";
            logAction('password_changed', "Password updated for user $user_id", 'info');
            // Update stored user data
            $user['password'] = $hashed_password;
        } else {
            $password_error = $translations[$lang]['password_update_failed'] ?? "Failed to update password: " . $conn->error;
            error_log("Password update failed: {$conn->error} for user $user_id");
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['edit_profile'] ?? 'Edit Profile'; ?> - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
      
        .content.collapsed {
            margin-left: 60px;
        }
        .container {
            margin-top: 20px;
        }
        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            background: #fff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .alert-success {
            background: rgba(0, 255, 0, 0.2);
            border: none;
        }
        .alert-danger {
            background: rgba(255, 0, 0, 0.2);
            border: none;
        }
        .profile-pic {
            max-width: 100px;
            border-radius: 50%;
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
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 5px;
        }
        .modal-content {
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            h2 {
                font-size: 1.6rem;
            }
            .profile-pic {
                max-width: 80px;
            }
            .card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container">
            <h2><?php echo $translations[$lang]['edit_profile'] ?? 'Edit Profile'; ?></h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['username'] ?? 'Username'; ?></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['full_name'] ?? 'Full Name'; ?></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['email'] ?? 'Email'; ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['current_photo'] ?? 'Current Photo'; ?></label><br>
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['photo']); ?>" alt="<?php echo $translations[$lang]['profile_photo'] ?? 'Profile Photo'; ?>" class="profile-pic" onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default.png';">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['upload_photo'] ?? 'Upload New Photo'; ?></label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $translations[$lang]['update_profile'] ?? 'Update Profile'; ?></button>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="fas fa-key"></i> <?php echo $translations[$lang]['change_password'] ?? 'Change Password'; ?></button>
                </form>
            </div>

            <!-- Change Password Modal -->
            <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="changePasswordModalLabel"><?php echo $translations[$lang]['change_password'] ?? 'Change Password'; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if (isset($password_success)): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($password_success); ?></div>
                            <?php endif; ?>
                            <?php if (isset($password_error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($password_error); ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <input type="hidden" name="change_password" value="1">
                                <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo $translations[$lang]['current_password'] ?? 'Current Password'; ?></label>
                                    <input type="password" name="current_password" class="form-control" placeholder="<?php echo $translations[$lang]['enter_current_password'] ?? 'Enter current password'; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo $translations[$lang]['new_password'] ?? 'New Password'; ?></label>
                                    <input type="password" name="new_password" class="form-control" placeholder="<?php echo $translations[$lang]['enter_new_password'] ?? 'Enter new password (min 8 chars)'; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo $translations[$lang]['confirm_password'] ?? 'Confirm New Password'; ?></label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="<?php echo $translations[$lang]['confirm_new_password'] ?? 'Confirm new password'; ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> <?php echo $translations[$lang]['change_password'] ?? 'Change Password'; ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (isset($password_success)): ?>
        <script>
            // Auto-close modal after success
            setTimeout(() => {
                var modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                modal.hide();
            }, 2000); // Close after 2 seconds
        </script>
    <?php endif; ?>
</body>
</html>