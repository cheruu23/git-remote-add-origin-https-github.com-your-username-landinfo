<?php
ob_start(); // Start output buffering to prevent headers issues

// Log inclusion of auth.php
$auth_path = realpath(__DIR__ . '/../../includes/auth.php') ?: __DIR__ . '/../../includes/auth.php';
error_log("Attempting to include auth.php from: $auth_path");

require '../../includes/init.php';


// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Validate user and role
if (!function_exists('isLoggedIn') || !function_exists('isRecordOfficer')) {
    error_log("Authentication functions missing: isLoggedIn=" . (function_exists('isLoggedIn') ? 'defined' : 'undefined') . ", isRecordOfficer=" . (function_exists('isRecordOfficer') ? 'defined' : 'undefined'));
    // Fallback: Check session manually
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'record_officer') {
        $_SESSION['error'] = $translations[$lang]['access_denied'];
        header("Location: " . BASE_URL . "/public/login.php");
        ob_end_flush();
        exit;
    }
} else {
    if (!isLoggedIn() || !isRecordOfficer()) {
        $_SESSION['error'] = $translations[$lang]['access_denied'];
        header("Location: " . BASE_URL . "/public/login.php");
        ob_end_flush();
        exit;
    }
}

// Validate file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    $_SESSION['error'] = $translations[$lang]['invalid_file'];
    header("Location: " . BASE_URL . "/modules/record_officer/approved_cases.php?lang=$lang");
    ob_end_flush();
    exit;
}

// Sanitize and validate PDF file
$file = basename(urldecode($_GET['file']));
$pdf_path = realpath(__DIR__ . '/../../temp') . DIRECTORY_SEPARATOR . $file;

if (!file_exists($pdf_path) || pathinfo($pdf_path, PATHINFO_EXTENSION) !== 'pdf') {
    $_SESSION['error'] = $translations[$lang]['file_not_found'];
    header("Location: " . BASE_URL . "/modules/record_officer/approved_cases.php?lang=$lang");
    ob_end_flush();
    exit;
}


?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['preview_parcel']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $translations[$lang]['preview_parcel']; ?></h1>
                </div>
                <div class="card">
                    <div class="card-body">
                        <iframe id="pdfViewer" src="<?php echo BASE_URL . '/temp/' . htmlspecialchars($file); ?>" type="application/pdf" width="100%" height="600px"></iframe>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" onclick="document.getElementById('pdfViewer').contentWindow.print()">
                            <i class="fas fa-print"></i> <?php echo $translations[$lang]['print']; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>/modules/record_officer/approved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?php echo $translations[$lang]['back']; ?>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
ob_end_flush(); // Flush output buffer
?>