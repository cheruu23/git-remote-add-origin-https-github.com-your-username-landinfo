<?php
session_start();
require '../includes/config.php';
require '../includes/db.php';
require '../includes/languages.php';

// Language handling
$valid_langs = ['en', 'om'];
// Set language: Use session if set, otherwise check GET parameter, default to 'om'
if (isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = isset($_SESSION['lang']) && in_array($_SESSION['lang'], $valid_langs) ? $_SESSION['lang'] : 'om';

// Function to build language switcher URLs
function buildLanguageUrl($lang, $current_query, $current_page)
{
    $query = array_merge($current_query, ['lang' => $lang]);
    return $current_page . '?' . http_build_query($query);
}

// Get current query parameters and page
$current_query = $_GET;
$current_page = basename($_SERVER['PHP_SELF']);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $conn->prepare("SELECT id, username, full_name, email, role, photo, password, is_locked FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            if ($user['is_locked']) {
                $error = $translations[$lang]['account_locked'];
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'photo' => $user['photo']
                ];
                $_SESSION['user_id'] = $user['id'];
                header("Location: " . BASE_URL . "/modules/" . $user['role'] . "/dashboard.php");
                exit();
            } else {
                $error = $translations[$lang]['invalid_credentials'];
            }
        } else {
            $error = $translations[$lang]['invalid_credentials'];
        }
    } catch (Exception $e) {
        logAction('login_failed', 'Failed to process login for username ' . $username . ': ' . $e->getMessage(), 'error');
        $error = $translations[$lang]['invalid_credentials'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['login']; ?> - LIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4ff, #e0e7ff);
            color: #1e2a44;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .navbar {
            background-color: rgb(5, 60, 158);
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(44, 6, 133, 0.85);
            transition: all 0.3s ease;
        }
        .navbar-brand img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #fff;
            transition: transform 0.3s ease;
        }
        .navbar-brand img:hover {
            transform: scale(1.15);
        }
        .navbar-text {
            color: #fff;
            font-size: 1.3rem;
            font-weight: 600;
            margin-left: 15px;
            letter-spacing: 0.5px;
        }
        .nav-link {
            color: #fff !important;
            font-weight: 500;
            margin: 0 12px;
            padding: 8px 16px !important;
            border-radius: 8px;
            position: relative;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: #fff !important;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background: #14b8a6;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 50%;
        }
        .btn-home {
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-home:hover {
            transform: scale(1.05);
        }
        .login-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            margin: 100px auto;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .login-container:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }
        h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e2a44;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        h2::after {
            content: '';
            width: 60px;
            height: 4px;
            background: #14b8a6;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }
        .form-control {
            background: rgba(243, 244, 246, 0.8);
            border: 1px solid #d1d5db;
            color: #1e2a44;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: #fff;
            border-color: #14b8a6;
            box-shadow: 0 0 10px rgba(20, 184, 166, 0.3);
            color: #1e2a44;
        }
        .form-control::placeholder {
            color: #9ca3af;
        }
        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #14b8a6;
            transition: color 0.3s ease;
        }
        .form-control.with-icon {
            padding-left: 40px;
        }
        .form-control:focus + .input-icon {
            color: #4f46e5;
        }
        .btn-login {
            background-color: rgb(5, 60, 158);
            border: none;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background-color: rgb(8, 80, 202);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
        }
        .alert {
            background: rgba(255, 75, 75, 0.1);
            border: 1px solid #f87171;
            color: #b91c1c;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .forgot-link {
            color: #14b8a6;
            text-decoration: none;
            display: block;
            margin-top: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .forgot-link:hover {
            color: #06b6d4;
            text-decoration: underline;
            transform: translateY(-2px);
        }
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-check-input {
            background: #fff;
            border: 1px solid #d1d5db;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #14b8a6;
            border-color: #14b8a6;
        }
        .form-check-label {
            color: #1e2a44;
            margin-left: 10px;
            font-weight: 500;
        }
        footer {
            background: linear-gradient(90deg, #1e2a44, #2d3e66);
            color: #fff;
            padding: 60px 20px;
            margin-top: 60px;
        }
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        .footer-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid #14b8a6;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .footer-logo:hover {
            transform: scale(1.1);
        }
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .footer-link {
            color: #d1d5db;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .footer-link:hover {
            color: #14b8a6;
            transform: translateX(5px);
        }
        .footer-link i {
            font-size: 1.2rem;
            color: #14b8a6;
        }
        .social-icons {
            display: flex;
            gap: 16px;
            justify-content: center;
        }
        .social-icon {
            color: #fff;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        .social-icon:hover {
            color: #14b8a6;
            transform: scale(1.2);
        }
        .language-switcher {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .language-btn {
            background: transparent;
            border: 2px solid #14b8a6;
            color: #fff;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .language-btn.active, .language-btn:hover {
            background: #14b8a6;
            color: #fff;
            transform: scale(1.05);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        [data-aos="fade-up"] {
            animation: fadeIn 0.6s ease-out;
        }
        @media (max-width: 992px) {
            h2 {
                font-size: 1.8rem;
            }
            .login-container {
                padding: 30px;
            }
        }
        @media (max-width: 768px) {
            .navbar-text {
                display: none;
            }
            .login-container {
                margin: 80px 20px;
            }
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
        @media (max-width: 576px) {
            .btn-login, .btn-home {
                font-size: 0.9rem;
                padding: 10px 16px;
            }
            h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Navbar Logo">
            </a>
            <span class="navbar-text d-none d-lg-block">
                <?php echo $translations[$lang]['organization_name']; ?>
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"><i class="fas fa-bars" style="color: #fff;"></i></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link btn-home" href="<?php echo BASE_URL; ?>">
                            <i class="fas fa-home"></i> <?php echo $translations[$lang]['home']; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-language"></i> <?php echo $translations[$lang]['language_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo buildLanguageUrl('om', $current_query, $current_page); ?>">Afaan Oromoo</a></li>
                            <li><a class="dropdown-item" href="<?php echo buildLanguageUrl('en', $current_query, $current_page); ?>">English</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="login-container" data-aos="fade-up">
        <h2><?php echo $translations[$lang]['LIMS']; ?></h2>
        <?php if (isset($error)): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <div class="position-relative">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control with-icon" placeholder="<?php echo $translations[$lang]['username']; ?>" required>
                </div>
            </div>
            <div class="form-group">
                <div class="position-relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control with-icon" placeholder="<?php echo $translations[$lang]['password']; ?>" required>
                </div>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember"><?php echo $translations[$lang]['remember_me']; ?></label>
            </div>
            <button type="submit" class="btn-login"><?php echo $translations[$lang]['login']; ?></button>
            <a href="<?php echo BASE_URL; ?>/public/forgot_password.php" class="forgot-link"><?php echo $translations[$lang]['forgot_password']; ?></a>
        </form>
    </div>

    <footer data-aos="fade-up">
        <div class="footer-content">
            <div>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Footer Logo" class="footer-logo">
                <p><?php echo $translations[$lang]['organization_name']; ?></p>
                <div class="social-icons">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div>
                <h5 style="color: #14b8a6; font-weight: 600;">Quick Links</h5>
                <div class="footer-links">
                    <a href="<?php echo BASE_URL; ?>" class="footer-link"><i class="fas fa-home"></i> <?php echo $translations[$lang]['home']; ?></a>
                    <a href="<?php echo BASE_URL; ?>#about" class="footer-link"><i class="fas fa-info-circle"></i> <?php echo $translations[$lang]['about']; ?></a>
                    <a href="<?php echo BASE_URL; ?>#notices" class="footer-link"><i class="fas fa-bell"></i> <?php echo $translations[$lang]['notices']; ?></a>
                    <a href="<?php echo BASE_URL; ?>#gallery" class="footer-link"><i class="fas fa-image"></i> <?php echo $translations[$lang]['gallery']; ?></a>
                    <a href="<?php echo BASE_URL; ?>#announcements" class="footer-link"><i class="fas fa-bullhorn"></i> <?php echo $translations[$lang]['announcements']; ?></a>
                </div>
            </div>
            <div>
                <h5 style="color: #14b8a6; font-weight: 600;">Contact</h5>
                <p><?php echo $translations[$lang]['address']; ?></p>
                <p><?php echo $translations[$lang]['contact']; ?></p>
                <div class="language-switcher">
                    <a href="<?php echo buildLanguageUrl('om', $current_query, $current_page); ?>" class="language-btn <?php echo $lang === 'om' ? 'active' : ''; ?>">Afaan Oromoo</a>
                    <a href="<?php echo buildLanguageUrl('en', $current_query, $current_page); ?>" class="language-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">English</a>
                </div>
            </div>
        </div>
        <p style="text-align: center; margin-top: 30px; font-size: 0.9rem;"><?php echo $translations[$lang]['copyright']; ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
    </script>
</body>
</html>