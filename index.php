<?php
ob_start();
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/languages.php';

// Centralized error logging
function logError($action, $message) {
    logAction($action, $message, 'error');
}

// Language handling
$valid_langs = ['en', 'om'];
$lang = $_SESSION['lang'] = ($_GET['lang'] ?? $_SESSION['lang'] ?? 'om');
if (!in_array($lang, $valid_langs)) $lang = $_SESSION['lang'] = 'om';

// Build language switcher URL
function buildLanguageUrl($lang, $query = []) {
    $query['lang'] = $lang;
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($query);
}

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logError('db_connection_error', 'Database connection failed: ' . $conn->connect_error);
    die("Database error.");
}
$conn->set_charset('utf8mb4');

// Fetch content with centralized error handling
function fetchQuery($conn, $query, $key = null) {
    try {
        $result = $conn->query($query);
        $data = [];
        if ($result && $result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                $data[$key ? $row[$key] : count($data)] = $row;
            }
        }
        return $data;
    } catch (Exception $e) {
        logError('fetch_failed', 'Query failed: ' . $e->getMessage());
        return [];
    }
}

// Fetch content
$page_contents = fetchQuery($conn, "SELECT section, content FROM pages WHERE section IN ('home_slogan1', 'about_content')", 'section');
$navbar_logo = fetchQuery($conn, "SELECT setting_value FROM settings WHERE setting_key = 'navbar_logo'")[0]['setting_value'] ?? 'assets/images/default_logo.png';
$gallery_images = fetchQuery($conn, "SELECT image_path, caption FROM gallery_images ORDER BY created_at DESC LIMIT 6");
$announcements = fetchQuery($conn, "SELECT title, content, created_at FROM announcements WHERE expiry_date IS NULL OR expiry_date >= CURDATE() ORDER BY created_at DESC LIMIT 3");

$conn->close();

// Set defaults
$home_slogan1 = $page_contents['home_slogan1']['content'] ?? $translations[$lang]['welcome'];
$about_content = $page_contents['about_content']['content'] ?? $translations[$lang]['about_content'];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang === 'om' ? 'Seensa - LIMS' : 'Home - LIMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #14b8a6;
            --dark: #1e2a44;
            --gradient: linear-gradient(90deg, #4f46e5, #7c3aed);
          /* --navbar-gradient: linear-gradient(90deg, rgb(5, 60, 158), rgb(5, 60, 158));*/
            --transition: all 0.3s ease;
            --shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4ff, #e0e7ff);
            color: var(--dark);
            line-height: 1.8;
            letter-spacing: 0.02em;
        }
        .navbar {
            background-color: rgb(5, 60, 158);
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
            background-color: rgb(5, 60, 158);
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
        }
        .card-text {
            font-size: 1.1rem;
            color: #64748b;
            line-height: 2;
            font-weight: 400;
            animation: fadeInUp 1s ease-out;
        }
        .section-img {
            width: 100%;
            height: 550px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .card:hover .section-img {
            transform: scale(1.08);
            filter: brightness(1.1);
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
        .gallery-carousel .carousel-item img {
            height: 550px;
            object-fit: cover;
            border-radius: 25px;
            box-shadow: var(--shadow);
        }
        .gallery-carousel .carousel-caption {
            background: rgba(30, 42, 68, 0.85);
            color: #fff;
            padding: 15px;
            border-radius: 10px;
            bottom: 40px;
            transform: translateY(30px);
            opacity: 0;
            transition: var(--transition);
        }
        .gallery-carousel .carousel-item.active .carousel-caption {
            transform: translateY(0);
            opacity: 1;
        }
        .announcement-item {
            background: rgba(255, 255, 255, 0.95);
            border-left: 6px solid var(--primary);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 15px;
            transition: var(--transition);
            animation: slideInRight 0.8s ease-out;
        }
        .announcement-item:hover {
            background: #f1f5f9;
            transform: translateX(8px);
            box-shadow: var(--shadow);
        }
        .announcement-date {
            color: var(--primary);
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .announcement-title {
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--dark);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .scroll-container {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 15px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) #e0e7ff;
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
        .mission-statement {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.05);
            animation: fadeInUp 1s ease-out;
        }
        .mission-statement h6 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            background: var(--gradient);
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .mission-statement p {
            font-style: italic;
            font-weight: 400;
            font-size: 1.1rem;
            color: #64748b;
            line-height: 2;
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
            .section-img, .gallery-carousel .carousel-item img { height: 350px; }
            .card-title { font-size: 1.8rem; }
            .card-text { font-size: 1rem; }
        }
        @media (max-width: 768px) {
            section { min-height: auto; padding: 50px 15px; }
            .navbar-text { display: none; }
            .section-img { height: 300px; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
        @media (max-width: 576px) {
            .card-title { font-size: 1.5rem; }
            .btn-primary, .btn-login { font-size: 0.9rem; padding: 10px 20px; }
            .footer-logo { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#home">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo">
            </a>
            <span class="navbar-text d-none d-lg-block"><?php echo $translations[$lang]['organization_name']; ?></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars" style="color: #fff;"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php foreach (['home', 'about', 'gallery', 'announcements'] as $section): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $section === 'home' ? 'active' : ''; ?>" href="#<?php echo $section; ?>">
                                <?php echo $translations[$lang][$section]; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-language"></i> <?php echo $translations[$lang]['language_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo buildLanguageUrl('om'); ?>">Afaan Oromoo</a></li>
                            <li><a class="dropdown-item" href="<?php echo buildLanguageUrl('en'); ?>">English</a></li>
                        </ul>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a href="public/login.php" class="btn btn-login"><?php echo $translations[$lang]['login']; ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Home Section -->
    <section id="home">
        <div class="container">
            <div class="row g-5">
                <div class="col-md-6 card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($translations[$lang]['welcome'] ?? ' welcome');?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($translations[$lang]['land_management_desc'] ?? 'Sustainable land use and dispute resolution.'); ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <img src="<?php echo BASE_URL; ?>/asssets/images/mattu.jpg" class="section-img" alt="Land Management">
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about">
        <div class="container">
            <div class="row g-5">
                <div class="col-md-6">
                    <img src="<?php echo BASE_URL; ?>/asssets/images/AB.jpg" class="section-img" alt="About">
                </div>
                <div class="col-md-6 card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $translations[$lang]['about']; ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($about_content); ?></p>
                        <div class="extra-content d-none">
                            <p><?php echo htmlspecialchars($translations[$lang]['about_extra1'] ?? 'Promoting sustainable land use.'); ?></p>
                            <p><?php echo htmlspecialchars($translations[$lang]['about_extra2'] ?? 'Services include land registration and dispute resolution.'); ?></p>
                        </div>
                        <div class="mission-statement">
                            <h6><?php echo $translations[$lang]['mission'] ?? 'Mission'; ?></h6>
                            <p><?php echo $translations[$lang]['mission_statement'] ?? 'To organize and implement an effective and sustainable land administration and utilization system in our city, ensuring consistent leadership and coordination to promote the prosperity and development of the city.'; ?></p>
                        </div>
                        <button id="readMoreBtn" class="btn btn-primary" 
                                data-read-more="<?php echo $translations[$lang]['read_more']; ?>" 
                                data-read-less="<?php echo $translations[$lang]['read_less'] ?? 'Read Less'; ?>">
                            <?php echo $translations[$lang]['read_more']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery">
        <div class="container">
            <div class="row g-5">
                <div class="col-md-6 card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $translations[$lang]['gallery']; ?></h5>
                        <p class="card-text"><?php echo $translations[$lang]['gallery_desc'] ?? 'Images of our land management initiatives.'; ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <?php if ($gallery_images): ?>
                        <div class="carousel slide gallery-carousel" id="galleryCarousel" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($gallery_images as $index => $image): ?>
                                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($image['image_path']); ?>" class="d-block w-100" alt="Gallery">
                                        <?php if ($image['caption']): ?>
                                            <div class="carousel-caption"><?php echo htmlspecialchars($image['caption']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" data-bs-target="#galleryCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" data-bs-target="#galleryCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="card-text text-center"><?php echo $translations[$lang]['no_gallery']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcements Section -->
    <section id="announcements">
        <div class="container">
            <div class="row g-5">
                <div class="col-md-6">
                    <div class="scroll-container">
                        <?php if ($announcements): ?>
                            <?php foreach ($announcements as $index => $announcement): ?>
                                <div class="announcement-item">
                                    <div class="announcement-date"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></div>
                                    <h6 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="card-text"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="card-text text-center"><?php echo $translations[$lang]['no_announcements']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <img src="<?php echo BASE_URL; ?>/asssets/images/MT.jpg" class="section-img" alt="Announcements">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
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
                        <a href="#<?php echo $section; ?>" class="footer-link"><i class="fas fa-<?php echo $section === 'home' ? 'home' : ($section === 'about' ? 'info-circle' : ($section === 'gallery' ? 'image' : 'bullhorn')); ?>"></i> <?php echo $translations[$lang][$section]; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h5 style="color: var(--primary); font-weight: 700;">Contact</h5>
                <p><?php echo $translations[$lang]['address']; ?></p>
                <p><?php echo $translations[$lang]['contact']; ?></p>
                <div class="language-switcher">
                    <a href="<?php echo buildLanguageUrl('om'); ?>" class="language-btn <?php echo $lang === 'om' ? 'active' : ''; ?>">Afaan Oromoo</a>
                    <a href="<?php echo buildLanguageUrl('en'); ?>" class="language-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">English</a>
                </div>
            </div>
        </div>
        <p style="text-align: center; margin-top: 40px; font-size: 1rem; font-weight: 400;"><?php echo $translations[$lang]['copyright']; ?></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true, easing: 'ease-out-cubic' });

        // Smooth scrolling and active nav link
        const navLinks = document.querySelectorAll('.nav-link');
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', e => {
                if (anchor.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(anchor.getAttribute('href'));
                    if (target) {
                        window.scrollTo({
                            top: target.offsetTop - document.querySelector('.navbar').offsetHeight,
                            behavior: 'smooth'
                        });
                        navLinks.forEach(link => link.classList.remove('active'));
                        anchor.classList.add('active');
                    }
                }
            });
        });

        window.addEventListener('scroll', () => {
            const scrollPos = window.scrollY + document.querySelector('.navbar').offsetHeight;
            document.querySelectorAll('section').forEach(section => {
                if (scrollPos >= section.offsetTop && scrollPos < section.offsetTop + section.offsetHeight) {
                    navLinks.forEach(link => {
                        link.classList.toggle('active', link.getAttribute('href') === `#${section.id}`);
                    });
                }
            });
        });

        // Toggle extra content
        document.getElementById('readMoreBtn').addEventListener('click', function() {
            const extra = document.querySelector('.extra-content');
            extra.classList.toggle('d-none');
            this.textContent = extra.classList.contains('d-none') ? this.dataset.readMore : this.dataset.readLess;
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>