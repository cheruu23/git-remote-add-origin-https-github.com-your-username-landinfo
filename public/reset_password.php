<?php
ob_start();
session_start();
require __DIR__ . "/../includes/config.php";
require __DIR__ . "/../includes/db.php";
require __DIR__ . "/../includes/languages.php";
$lang = $_GET['lang'] ?? 'om'; // Default to Afaan Oromo

$mysqli = getDBConnection();
if ($mysqli->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $mysqli->connect_error, 'error');
    die("Database connection error.");
}
$mysqli->set_charset('utf8mb4');

// Fetch navbar logo from settings table
$navbar_logo = 'assets/images/default_logo.png';
try {
    $result = $mysqli->query("SELECT setting_value FROM settings WHERE setting_key = 'navbar_logo'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $navbar_logo = $row['setting_value'];
    }
} catch (Exception $e) {
    logAction('fetch_navbar_logo_failed', 'Failed to fetch navbar logo: ' . $e->getMessage(), 'error');
}

$token = $_GET["token"];
$token_hash = hash("sha256", $token);

$sql = "SELECT user_id, expires_at FROM password_resets WHERE token = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();

if ($reset === null) {
    $_SESSION['error'] = $translations[$lang]['token_not_found'] ?? "Token not found";
    header("Location: forgot_password.php?lang=" . urlencode($lang));
    exit;
}

if (strtotime($reset["expires_at"]) <= time()) {
    $_SESSION['error'] = $translations[$lang]['token_expired'] ?? "Token has expired";
    header("Location: forgot_password.php?lang=" . urlencode($lang));
    exit;
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['reset_password_title'] ?? 'Reset Password'; ?> - LIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #14b8a6;
            --dark: #1e2a44;
            --gradient: linear-gradient(90deg, #4f46e5, #7c3aed);
            --navbar-gradient: linear-gradient(90deg, rgb(27, 19, 180), rgb(69, 14, 163));
            --transition: all 0.3s ease;
            --shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-size: cover;
            color: var(--dark);
            line-height: 1.8;
            letter-spacing: 0.02em;
            position: relative;
        }
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
            background: var(--navbar-gradient);
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
        }
        .navbar-brand img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #fff;
            transition: var(--transition);
        }
        .navbar-brand img:hover {
            transform: scale(1.2) rotate(5deg);
        }
        .navbar-text {
            color: #fff;
            font-size: 1.4rem;
            font-weight: 700;
            margin-left: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            animation: fadeIn 1s ease-in;
        }
        .nav-link {
            color: #fff !important;
            font-weight: 600;
            margin: 0 15px;
            padding: 10px 20px !important;
            border-radius: 10px;
            position: relative;
            text-transform: uppercase;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 0;
            left: 50%;
            background: var(--primary);
            transition: var(--transition);
            transform: translateX(-50%);
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 70%;
        }
        .btn-login {
            background: linear-gradient(90deg, var(--primary), #06b6d4);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            overflow: hidden;
        }
        .btn-login:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow);
        }
        .btn-login::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .btn-login:hover::before {
            width: 200px;
            height: 200px;
        }
        section {
            min-height: 100vh;
            padding: 80px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .card {
            border: none;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow);
            transition: var(--transition);
            max-width: 500px;
            width: 100%;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        .card-body {
            padding: 30px;
            position: relative;
        }
        .card-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background: var(--gradient);
          background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: slideInLeft 0.8s ease-out;
            text-align: center;
        }
        .card-text {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 2;
            font-weight: 400;
            animation: fadeInUp 1s ease-out;
        }
        .form-label {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
            animation: fadeInUp 1s ease-out;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.2);
            color: var(--dark);
            border-radius: 10px;
            padding: 10px;
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(20, 184, 166, 0.3);
        }
        .form-control::placeholder {
            color: #94a3b8;
        }
        .alert {
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease-out;
        }
        .alert-success {
            background: rgba(20, 184, 166, 0.2);
            color: var(--dark);
            border: 1px solid var(--primary);
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--dark);
            border: 1px solid #ef4444;
        }
        .btn-primary {
            background: var(--gradient);
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 700;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #7c3aed, #4f46e5);
            transform: scale(1.1);
            box-shadow: var(--shadow);
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .btn-primary:hover::before {
            width: 200px;
            height: 200px;
        }
        footer {
            background: linear-gradient(90deg, var(--dark), #2d3e66);
            color: #fff;
            padding: 80px 20px;
            position: relative;
        }
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 40px;
        }
        .footer-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid var(--primary);
            transition: var(--transition);
        }
        .footer-logo:hover {
            transform: scale(1.15) rotate(5deg);
        }
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .footer-link {
            color: #d1d5db;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        .footer-link:hover {
            color: var(--primary);
            transform: translateX(8px);
        }
        .footer-link i {
            font-size: 1.3rem;
            color: var(--primary);
        }
        .social-icons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        .social-icon {
            color: #fff;
            font-size: 1.8rem;
            transition: var(--transition);
        }
        .social-icon:hover {
            color: var(--primary);
            transform: scale(1.3) rotate(10deg);
        }
        .language-btn {
            background: transparent;
            border: 3px solid var(--primary);
            color: #fff;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            transition: var(--transition);
        }
        .language-btn.active, .language-btn:hover {
            background: var(--primary);
            color: #fff;
            transform: scale(1.1);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @media (max-width: 992px) {
            .card-title { font-size: 1.8rem; }
            .card-text { font-size: 1rem; }
        }
        @media (max-width: 768px) {
            section { min-height: auto; padding: 50px 15px; }
            .navbar-text { display: none; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
            .card { padding: 20px; }
        }
        @media (max-width: 576px) {
            .card-title { font-size: 1.5rem; }
            .btn-primary, .btn-login { font-size: 0.9rem; padding: 10px 20px; }
            .footer-logo { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
    <div class="bg-overlay"></div>

    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Navbar Logo">
            </a>
            <span class="navbar-text d-none d-lg-block">
                <?php echo $translations[$lang]['organization_name']; ?>
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars" style="color: #fff;"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-language"></i> <?php echo $translations[$lang]['language_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?lang=om&token=<?php echo urlencode($token); ?>">Afaan Oromoo</a></li>
                            <li><a class="dropdown-item" href="?lang=en&token=<?php echo urlencode($token); ?>">English</a></li>
                        </ul>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="login.php" class="btn btn-login"><?php echo $translations[$lang]['login']; ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section id="reset-password">
        <div class="container">
            <div class="row g-5 justify-content-center">
                <div class="col-md-6 card" data-aos="fade-up">
                    <div class="card-body">
                        <h1 class="card-title"><?php echo $translations[$lang]['reset_password_title'] ?? 'Reset Password'; ?></h1>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form method="post" action="process-reset-password.php">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <?php echo $translations[$lang]['new_password_label'] ?? 'New Password'; ?>
                                </label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">
                                    <?php echo $translations[$lang]['confirm_password_label'] ?? 'Confirm Password'; ?>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $translations[$lang]['submit_button'] ?? 'Reset Password'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo" class="footer-logo">
                <p><?php echo $translations[$lang]['organization_name']; ?></p>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div>
                <h5 style="color: var(--primary); font-weight: 700;">Quick Links</h5>
                <div class="footer-links">
                    <?php foreach (['home', 'about', 'gallery', 'announcements'] as $section): ?>
                        <a href="#" class="footer-link"><i class="fas fa-<?php echo $section === 'home' ? 'home' : ($section === 'about' ? 'info-circle' : ($section === 'gallery' ? 'image' : 'bullhorn')); ?>"></i> <?php echo $translations[$lang][$section]; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h5 style="color: var(--primary); font-weight: 700;">Contact</h5>
                <p><?php echo $translations[$lang]['address']; ?></p>
                <p><?php echo $translations[$lang]['contact']; ?></p>
                <div class="language-switcher">
                    <a href="?lang=om&token=<?php echo urlencode($token); ?>" class="language-btn <?php echo $lang === 'om' ? 'active' : ''; ?>">Afaan Oromoo</a>
                    <a href="?lang=en&token=<?php echo urlencode($token); ?>" class="language-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">English</a>
                </div>
            </div>
        </div>
        <p style="text-align: center; margin-top: 40px; font-size: 1rem; font-weight: 400;"><?php echo $translations[$lang]['copyright']; ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true, easing: 'ease-out-cubic' });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>