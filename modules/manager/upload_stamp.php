<?php
ob_start();
require_once '../../includes/init.php';
require_once '../../includes/config.php';

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'en';

// Translations
$translations = [
    'en' => [
        'title' => 'Upload Company Stamp',
        'password_label' => 'Your Password',
        'stamp_image_label' => 'Stamp Image (PNG/JPG, max 2MB)',
        'upload_button' => 'Upload Stamp',
        'back_to_dashboard' => 'Back to Dashboard',
        'access_denied' => 'Access denied! Only managers can upload stamps.',
        'database_error' => 'Database connection error.',
        'invalid_csrf' => 'Invalid CSRF token.',
        'invalid_password' => 'Incorrect password.',
        'invalid_file_type' => 'Only PNG or JPG images are allowed.',
        'file_too_large' => 'Image size must not exceed 2MB.',
        'no_file' => 'No image file uploaded.',
        'upload_failed' => 'Failed to upload image.',
        'db_update_failed' => 'Failed to update database.',
        'success_message' => 'Stamp image uploaded successfully.'
    ],
    'om' => [
        'title' => 'Suuraa Shaamboo Kompaniitti Maxxansuu',
        'password_label' => 'Jecha Iccitii Keessan',
        'stamp_image_label' => 'Suuraa Shaamboo (PNG/JPG, max 2MB)',
        'upload_button' => 'Shaamboo Maxxansuu',
        'back_to_dashboard' => 'Garba Daashboorditti Deebii',
        'access_denied' => 'Seenuu hin danda’amu! Maaneejaroota qofa shaamboo maxxansuu danda’u.',
        'database_error' => 'Dhaabbata database hin danda’amu.',
        'invalid_csrf' => 'Tookanii CSRF sirrii miti.',
        'invalid_password' => 'Jecha iccitii sirrii miti.',
        'invalid_file_type' => 'Suuraa PNG ykn JPG qofa danda’ama.',
        'file_too_large' => 'Hamma suuraa 2MB hin darbanu.',
        'no_file' => 'Suuraa maxxanfame hin jiru.',
        'upload_failed' => 'Suuraa maxxansuun hin danda’amu.',
        'db_update_failed' => 'Database haaromsuun hin danda’amu.',
        'success_message' => 'Suuraa shaamboo milkaa’inaan maxxanfame.'
    ]
];

// Redirect if not logged in or not a manager
redirectIfNotLoggedIn();
if (!function_exists('isManager') || !isManager()) {
    $_SESSION['error'] = $translations[$lang]['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php?lang=$lang");
    ob_end_flush();
    exit;
}

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $_SESSION['error'] = $translations[$lang]['database_error'];
    header("Location: " . BASE_URL . "/public/login.php?lang=$lang");
    ob_end_flush();
    exit;
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = $translations[$lang]['invalid_csrf'];
    } else {
        // Validate password
        $input_password = $_POST['password'] ?? '';
        try {
            $sql = "SELECT password FROM users WHERE id = :user_id AND role = 'manager'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':user_id' => $_SESSION['user']['id']]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($input_password, $user['password'])) {
                $errors[] = $translations[$lang]['invalid_password'];
            }
        } catch (PDOException $e) {
            error_log("Password query failed: " . $e->getMessage());
            $errors[] = $translations[$lang]['database_error'];
        }

        // Validate file
        if (empty($errors) && isset($_FILES['stamp_image']) && $_FILES['stamp_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['stamp_image'];
            $allowed_types = ['image/png', 'image/jpeg'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = $translations[$lang]['invalid_file_type'];
            } elseif ($file['size'] > $max_size) {
                $errors[] = $translations[$lang]['file_too_large'];
            } else {
                // Define upload path
                $upload_dir = __DIR__ . '/../../asssets/images/';
                $file_name = 'stamp.png'; // Overwrite existing stamp
                $upload_path = $upload_dir . $file_name;

                // Ensure directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update database
                    try {
                        $sql = "INSERT INTO company_stamps (image_path, uploaded_by) 
                                VALUES (:image_path, :uploaded_by)
                                ON DUPLICATE KEY UPDATE 
                                image_path = :image_path, 
                                uploaded_by = :uploaded_by, 
                                uploaded_at = CURRENT_TIMESTAMP";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':image_path' => 'asssets/images/stamp.png',
                            ':uploaded_by' => $_SESSION['user']['id']
                        ]);
                        $success = $translations[$lang]['success_message'];
                    } catch (PDOException $e) {
                        error_log("Database update failed: " . $e->getMessage());
                        $errors[] = $translations[$lang]['db_update_failed'];
                    }
                } else {
                    $errors[] = $translations[$lang]['upload_failed'];
                }
            }
        } else {
            $errors[] = $translations[$lang]['no_file'];
        }
    }

    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['title']; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/bootstrap.min.css">
    <style>     
        .error {
            color: #c0392b;
            margin-bottom: 10px;
        }
        .success {
            color: #2ecc71;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
        }
        .btn-back {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center"><?php echo $translations[$lang]['title']; ?></h2>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="password"><?php echo $translations[$lang]['password_label']; ?></label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="stamp_image"><?php echo $translations[$lang]['stamp_image_label']; ?></label>
                <input type="file" class="form-control" id="stamp_image" name="stamp_image" accept="image/png,image/jpeg" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?php echo $translations[$lang]['upload_button']; ?></button>
        </form>

        <a href="<?php echo BASE_URL; ?>/modules/manager/dashboard.php?lang=<?php echo $lang; ?>" class="btn btn-secondary btn-back"><?php echo $translations[$lang]['back_to_dashboard']; ?></a>
    </div>

    <script src="<?php echo BASE_URL; ?>/assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>