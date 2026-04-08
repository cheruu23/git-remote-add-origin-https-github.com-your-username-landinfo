<?php
ob_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/config.php';
require_once '../../includes/languages.php';
redirectIfNotLoggedIn();

if (!isSurveyor()) {
    $_SESSION['error'] = $translations['en']['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'om';

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

// Fetch navbar logo from settings table
$navbar_logo = 'assets/images/default_logo.png';
try {
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'navbar_logo'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $navbar_logo = $row['setting_value'];
    }
} catch (Exception $e) {
    logAction('fetch_navbar_logo_failed', 'Failed to fetch navbar logo: ' . $e->getMessage(), 'error');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['report_case_title']; ?> - LIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
      
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }
        .navbar {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(122, 120, 120, 0.2);
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid rgb(242, 243, 243);
            transition: transform 0.3s;
        }
        .navbar-brand img:hover {
            transform: scale(1.1);
        }
        .navbar-text {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-left: 20px;
        }
        .nav-link {
            color: #F5F5DC !important;
            font-weight: 500;
            margin: 0 5px;
            padding: 8px 15px !important;
            border-radius: 20px;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(47, 79, 79, 0.5);
            color: #8B4513 !important;
        }
        .btn-login {
            background: linear-gradient(90deg, #2F4F4F, #8B4513);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            color: #F5F5DC;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(90deg, #8B4513, #2F4F4F);
            box-shadow: 0 5px 15px rgba(47, 79, 79, 0.5);
            transform: translateY(-2px);
        }
        .content {
            margin-left: 250px;
            padding: 80px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
        }
        .section-content {
            background: rgba(245, 245, 220, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 40px;
            backdrop-filter: blur(10px);
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 1s ease-in-out;
        }
        h2 {
            color: #2F4F4F;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
            font-size: 2.5rem;
            text-align: center;
        }
        .form-label {
            color: #F5F5DC;
            font-weight: 500;
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #F5F5DC;
            border-radius: 10px;
            padding: 10px;
        }
        .form-control::placeholder {
            color: rgba(245, 245, 220, 0.7);
        }
        .form-select option {
            background: #2F4F4F;
            color: #F5F5DC;
        }
        .alert {
            border-radius: 10px;
            background: rgba(47, 79, 79, 0.5);
            color: #F5F5DC;
            border: none;
        }
        .alert-success {
            background: rgba(72, 133, 72, 0.5);
        }
        .alert-danger {
            background: rgba(133, 72, 72, 0.5);
        }
      
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 992px) {
            .content {
                margin-left: 0;
            }
            .section-content {
                padding: 30px;
            }
            h2 {
                font-size: 1.8rem;
            }
            .form-control, .form-select {
                font-size: 0.9rem;
            }
        }
        @media (max-width: 768px) {
            .navbar-text {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>

    <div class="content">
        <section id="report-case" class="section-content">
            <h2><?php echo $translations[$lang]['report_case_title']; ?></h2>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php elseif (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            <form action="<?php echo BASE_URL; ?>/modules/surveyor/submit_reports.php" method="POST">
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                <div class="mb-3">
                    <label for="land_id" class="form-label">
                        <?php echo $translations[$lang]['land_id_label']; ?>
                    </label>
                    <input type="number" class="form-control" id="land_id" name="land_id" 
                           placeholder="<?php echo $translations[$lang]['land_id_placeholder']; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">
                        <?php echo $translations[$lang]['title_label']; ?>
                    </label>
                    <input type="text" class="form-control" id="title" name="title" 
                           placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="case_type" class="form-label">
                        <?php echo $translations[$lang]['case_type_label']; ?>
                    </label>
                    <select class="form-select" id="case_type" name="case_type" required>
                        <option value="" disabled selected><?php echo $translations[$lang]['case_type_placeholder']; ?></option>
                        <option value="Boundary Dispute">Boundary Dispute</option>
                        <option value="Ownership Dispute">Ownership Dispute</option>
                        <option value="Land Use Violation">Land Use Violation</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">
                        <?php echo $translations[$lang]['description_label']; ?>
                    </label>
                    <textarea class="form-control" id="description" name="description" rows="5" 
                              placeholder="<?php echo $translations[$lang]['description_placeholder']; ?>" required></textarea>
                </div>
                <button type="submit" class="btn btn-login w-100">
                    <?php echo $translations[$lang]['submit_report_button']; ?>
                </button>
                <a href="<?php echo BASE_URL; ?>/modules/surveyor/dashboard.php?lang=<?php echo $lang; ?>" 
                   class="btn btn-secondary w-100 mt-2">
                    <?php echo $translations[$lang]['cancel_button']; ?>
                </a>
            </form>
        </section>
    </div>

   

</body>
</html>
<?php ob_end_flush(); ?>