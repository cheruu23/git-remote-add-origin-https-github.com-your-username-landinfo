<?php
// Start output buffering to capture page content
ob_start();
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/languages.php';
require_once '../../includes/language_switcher.php';

redirectIfNotLoggedIn();

// Restrict access to admins
restrictAccess(['admin'], 'system settings', $lang);

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    error_log("Database connection failed: " . $conn->connect_error);
    die($translations[$lang]['db_connection_failed'] ?? 'Database connection error.');
}
$conn->set_charset('utf8mb4');

// Log access to system settings
logAction('system_settings_access', 'Admin accessed system settings', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $site_title = trim($_POST['site_title']);
        $theme = $_POST['theme'];
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $sidebar_color = trim($_POST['sidebar_color']);
        $navbar_color = trim($_POST['navbar_color']);

        try {
            $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);

            $key = 'site_title'; $value = $site_title; $stmt->execute();
            $key = 'theme'; $value = $theme; $stmt->execute();
            $key = 'maintenance_mode'; $value = $maintenance_mode; $stmt->execute();
            $key = 'sidebar_color'; $value = $sidebar_color; $stmt->execute();
            $key = 'navbar_color'; $value = $navbar_color; $stmt->execute();

            $success = $translations[$lang]['settings_updated'] ?? "Settings updated successfully!";
        } catch (Exception $e) {
            $error = $translations[$lang]['settings_update_failed'] ?? "Failed to update settings: " . $e->getMessage();
            logAction('settings_update_failed', 'Failed to update settings: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
        }
        $stmt->close();
    } elseif (isset($_POST['update_sidebar_logo'])) {
        $file = $_FILES['sidebar_logo'];
        $target_dir = dirname(__DIR__, 2) . '/assets/images/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . 'sidebar_logo_' . time() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $relative_path = 'assets/images/sidebar_logo_' . time() . '_' . basename($file['name']);
            try {
                $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('sidebar_logo', ?)");
                $stmt->bind_param("s", $relative_path);
                $stmt->execute();
                $success = $translations[$lang]['sidebar_logo_updated'] ?? "Sidebar logo updated successfully!";
            } catch (Exception $e) {
                $error = $translations[$lang]['sidebar_logo_update_failed'] ?? "Failed to update sidebar logo: " . $e->getMessage();
                logAction('sidebar_logo_update_failed', 'Failed to update sidebar logo: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
            }
            $stmt->close();
        } else {
            $error = $translations[$lang]['sidebar_logo_upload_failed'] ?? "Failed to upload sidebar logo.";
        }
    } elseif (isset($_POST['update_navbar_logo'])) {
        $file = $_FILES['navbar_logo'];
        $target_dir = dirname(__DIR__, 2) . '/assets/images/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . 'navbar_logo_' . time() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $relative_path = 'assets/images/navbar_logo_' . time() . '_' . basename($file['name']);
            try {
                $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('navbar_logo', ?)");
                $stmt->bind_param("s", $relative_path);
                $stmt->execute();
                $success = $translations[$lang]['navbar_logo_updated'] ?? "Navbar logo updated successfully!";
            } catch (Exception $e) {
                $error = $translations[$lang]['navbar_logo_update_failed'] ?? "Failed to update navbar logo: " . $e->getMessage();
                logAction('navbar_logo_update_failed', 'Failed to update navbar logo: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
            }
            $stmt->close();
        } else {
            $error = $translations[$lang]['navbar_logo_upload_failed'] ?? "Failed to upload navbar logo.";
        }
    }
}

// Fetch settings
$site_title = query("SELECT setting_value FROM settings WHERE setting_key = 'site_title' LIMIT 1")->fetch_assoc()['setting_value'] ?? 'LIMS';
$theme = query("SELECT setting_value FROM settings WHERE setting_key = 'theme' LIMIT 1")->fetch_assoc()['setting_value'] ?? 'dark';
$maintenance_mode = query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1")->fetch_assoc()['setting_value'] ?? '0';
$sidebar_color = query("SELECT setting_value FROM settings WHERE setting_key = 'sidebar_color' LIMIT 1")->fetch_assoc()['setting_value'] ?? '#1e3a8a';
$sidebar_logo = query("SELECT setting_value FROM settings WHERE setting_key = 'sidebar_logo' LIMIT 1")->fetch_assoc()['setting_value'] ?? 'assets/images/default_logo.png';
$navbar_color = query("SELECT setting_value FROM settings WHERE setting_key = 'navbar_color' LIMIT 1")->fetch_assoc()['setting_value'] ?? '#1e40af';
$navbar_logo = query("SELECT setting_value FROM settings WHERE setting_key = 'navbar_logo' LIMIT 1")->fetch_assoc()['setting_value'] ?? 'assets/images/default_navbar_logo.png';

$conn->close();
?>

<!-- Page-specific content -->
<div class="container">
    <h2><?php echo $translations[$lang]['system_settings'] ?? 'System Settings'; ?></h2>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- General Settings -->
    <div class="card">
        <h3><?php echo $translations[$lang]['general_settings'] ?? 'General Settings'; ?></h3>
        <form method="POST">
            <div class="mb-3">
                <label><?php echo $translations[$lang]['site_title'] ?? 'Site Title'; ?></label>
                <input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars($site_title); ?>" required>
            </div>
            <div class="mb-3">
                <label><?php echo $translations[$lang]['sidebar_color'] ?? 'Sidebar Color'; ?></label>
                <input type="color" name="sidebar_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebar_color); ?>">
            </div>
            <div class="mb-3">
                <label><?php echo $translations[$lang]['navbar_color'] ?? 'Navbar Color'; ?></label>
                <input type="color" name="navbar_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($navbar_color); ?>">
            </div>
            <button type="submit" name="update_settings" class="btn btn-primary"><?php echo $translations[$lang]['save_settings'] ?? 'Save Settings'; ?></button>
        </form>
    </div>

    <!-- Sidebar Logo -->
    <div class="card">
        <h3><?php echo $translations[$lang]['sidebar_logo'] ?? 'Sidebar Logo'; ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label><?php echo $translations[$lang]['upload_sidebar_logo'] ?? 'Upload Sidebar Logo'; ?></label>
                <input type="file" name="sidebar_logo" class="form-control" accept="image/*" required>
            </div>
            <button type="submit" name="update_sidebar_logo" class="btn btn-primary"><?php echo $translations[$lang]['update_logo'] ?? 'Update Logo'; ?></button>
        </form>
        <h4><?php echo $translations[$lang]['current_sidebar_logo'] ?? 'Current Sidebar Logo'; ?></h4>
        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($sidebar_logo); ?>" alt="<?php echo $translations[$lang]['sidebar_logo'] ?? 'Sidebar Logo'; ?>" style="max-width: 100px;">
    </div>

    <!-- Navbar Logo -->
    <div class="card">
        <h3><?php echo $translations[$lang]['navbar_logo'] ?? 'Navbar Logo'; ?></h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label><?php echo $translations[$lang]['upload_navbar_logo'] ?? 'Upload Navbar Logo'; ?></label>
                <input type="file" name="navbar_logo" class="form-control" accept="image/*" required>
            </div>
            <button type="submit" name="update_navbar_logo" class="btn btn-primary"><?php echo $translations[$lang]['update_logo'] ?? 'Update Logo'; ?></button>
        </form>
        <h4><?php echo $translations[$lang]['current_navbar_logo'] ?? 'Current Navbar Logo'; ?></h4>
        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="<?php echo $translations[$lang]['navbar_logo'] ?? 'Navbar Logo'; ?>" style="max-width: 100px;">
    </div>
</div>
