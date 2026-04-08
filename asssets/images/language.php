<!DOCTYPE html>
<!-- saved from url=(0062)http://localhost/landinfo/modules/record_officer/dashboard.php -->
<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><style>body {transition: opacity ease-in 0.2s; } 
body[unresolved] {opacity: 0; display: block; overflow: hidden; position: relative; } 
</style>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="./language_files/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" async="">
    <link href="./language_files/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" async="">
    <link href="./language_files/css2" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
            overflow-x: hidden;
        }
        .navbar {
            background: #7509ae !important;
            padding: 10px 20px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 60px;
            display: flex;
            align-items: center;
        }
        .navbar-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-right: 10px;
        }
        .navbar-brand {
            color: #fff;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
        }
        .navbar-brand:hover {
            color: #e2e8f0;
        }
        .toggle-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 15px;
        }
        .notification-bell {
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            position: relative;
            margin-right: 20px;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc2626;
            color: #fff;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 60px;
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid #e5e7eb;
            z-index: 1000;
            display: none;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 10px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .notification-item:hover {
            background: #f3f4f6;
        }
        .notification-item.unread {
            background: #dbeafe;
            font-weight: 600;
        }
        .notification-item a {
            text-decoration: none;
            color: #374151;
            display: flex;
            flex-direction: column;
        }
        .notification-message {
            font-size: 0.9rem;
        }
        .notification-time {
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        .profile-btn {
            background: none;
            border: none;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .profile-pic {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .profile-info {
            text-align: left;
        }
        .profile-info span {
            display: block;
            font-size: 0.9rem;
        }
        .profile-info .role {
            font-size: 0.7rem;
            color: #e2e8f0;
        }
        .dropdown-menu {
            top: 50px !important;
            right: 0;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar {
            width: 250px;
            background: #242424;
            color: #fff;
            position: fixed;
            top: 60px;
            left: 0;
            bottom: 0;
            z-index: 900;
            overflow-y: auto;
        }
        .sidebar.collapsed {
            width: 60px;
        }
        .sidebar-header {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #3b82f6;
        }
        .sidebar-profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #fff;
            margin-bottom: 10px;
        }
        .sidebar-header h5 {
            font-size: 1rem;
            color: #fff;
            margin: 0;
        }
        .sidebar-header small {
            color: #e2e8f0;
            font-size: 0.8rem;
        }
        .sidebar.collapsed .sidebar-header h5,
        .sidebar.collapsed .sidebar-header small {
            display: none;
        }
        .nav-link {
            color: #e2e8f0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        .nav-link:hover {
            background: #3b82f6;
            color: #fff;
        }
        .nav-link.active {
            background: #2563eb;
        }
        .nav-link i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        .nav-link span {
            display: inline-block;
        }
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 12px 0;
        }
        .main-content {
            margin-left: 250px;
            margin-top: 60px;
            padding: 20px;
        }
        .main-content.collapsed {
            margin-left: 60px;
        }
        .language-switcher {
            padding: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
            border-top: 1px solid #3b82f6;
        }
        .language-dropdown {
            width: 100%;
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #e2e8f0;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .language-dropdown:focus {
            outline: none;
            background: #3b82f6;
            color: #fff;
        }
        .sidebar.collapsed .language-switcher {
            display: none;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: fixed;
                top: 60px;
                left: -100%;
                transition: left 0.3s ease;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .toggle-btn {
                display: block;
            }
            .language-switcher {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="toggle-btn" id="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <a class="navbar-brand" href="http://localhost/landinfo/modules/record_officer/dashboard.php?lang=en">
                <img src="./language_files/navbar_logo_laofo.jpg" alt="Logo" class="navbar-logo">
                Land Management System - Record Officer            </a>
            <ul class="navbar-nav ms-auto align-items-center">
                <!-- Notifications -->
                <li class="nav-item">
                    <div class="notification-bell" id="notification-bell">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" style="display: none;">
                                                    </span>
                    </div>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">Notifications</div>
                        <div id="notification-content" class="text-center text-muted">No new notifications</div>
                    </div>
                </li>
                <!-- Profile Section -->
                <li class="nav-item profile-dropdown ms-3">
                    <button class="profile-btn" id="profile-btn">
                        <img src="./language_files/user_3_1746191048_images.jpg" alt="Profile" class="profile-pic" onerror="this.src=&#39;http://localhost/landinfo/assets/images/default.png&#39;;">
                        <div class="profile-info">
                            <span>record_officer</span>
                            <span class="role">Record Officer</span>
                        </div>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="http://localhost/landinfo/modules/profile.php?lang=en">Profile</a></li>
                        <li><a class="dropdown-item" href="http://localhost/landinfo/public/logout.php?lang=en">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="./language_files/user_3_1746191048_images.jpg" alt="Profile" class="sidebar-profile-pic" onerror="this.src=&#39;http://localhost/landinfo/assets/images/default.png&#39;;">
            <h5>record_officer</h5>
            <small>Record Officer</small>
        </div>
        <ul class="nav flex-column">
                                <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/dashboard.php?lang=en" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/registration.php?lang=en" class="nav-link ">
                            <i class="fas fa-plus-circle"></i>
                            <span>Register Land</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/Report_Case.php?lang=en" class="nav-link ">
                            <i class="fas fa-file-alt"></i>
                            <span>Report Case</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/reported_cases.php?lang=en" class="nav-link ">
                            <i class="fas fa-briefcase"></i>
                            <span>Reported Cases</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/assign_cases.php?lang=en" class="nav-link ">
                            <i class="fas fa-tasks"></i>
                            <span>Assigned Cases</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/record_officer/approved_cases.php?lang=en" class="nav-link ">
                            <i class="fas fa-check-circle"></i>
                            <span>Approved Cases</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/modules/profile.php?lang=en" class="nav-link ">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                                    <li class="nav-item">
                        <a href="http://localhost/landinfo/public/logout.php?lang=en" class="nav-link ">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                        </ul>
        <div class="language-switcher">
            <select class="language-dropdown" onchange="window.location.href=&#39;?lang=&#39; + this.value">
                <option value="en" selected="">English</option>
                <option value="om">Afaan Oromoo</option>
            </select>
        </div>
    </aside>

    <div class="main-content" id="main-content">

    <script src="./language_files/bootstrap.bundle.min.js.download" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous" async=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('toggle-sidebar');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('collapsed');
            });

            const bell = document.getElementById('notification-bell');
            const notifDropdown = document.getElementById('notification-dropdown');
            bell.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('show');
                if (notifDropdown.classList.contains('show')) {
                    fetchNotifications();
                }
            });
            document.addEventListener('click', (e) => {
                if (!bell.contains(e.target) && !notifDropdown.contains(e.target)) {
                    notifDropdown.classList.remove('show');
                }
            });

            const profileBtn = document.getElementById('profile-btn');
            const profileDropdown = profileBtn.nextElementSibling;
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
            document.addEventListener('click', (e) => {
                if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });

            function fetchNotifications() {
                fetch('http://localhost/landinfo/includes/fetch_notification.php')
                    .then(response => response.json())
                    .then(data => {
                        const content = document.getElementById('notification-content');
                        const badge = bell.querySelector('.notification-badge');
                        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                        if (data.unread_count > 0) badge.textContent = data.unread_count;

                        content.innerHTML = '';
                        if (!data.notifications.length) {
                            content.innerHTML = '<div class="notification-item text-center text-muted">No new notifications</div>';
                        } else {
                            data.notifications.forEach(notif => {
                                const isRead = notif.is_read || 0;
                                content.innerHTML += `
                                    <div class="notification-item ${isRead ? '' : 'unread'}">
                                        <a href="http://localhost/landinfo/modules/record_officer/managercase_view.php?case_id=${notif.case_id}&mark_read=${notif.id}&lang=en">
                                            <span class="notification-message">${notif.message}</span>
                                            <span class="notification-time">${new Date(notif.created_at).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</span>
                                        </a>
                                    </div>
                                `;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        content.innerHTML = '<div class="notification-item text-center text-danger">Error loading notifications</div>';
                    });
            }
        });
    </script>





    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Officer Dashboard</title>
    <link href="./language_files/all.min.css" rel="stylesheet">
    <link href="./language_files/css2" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 10px;
        }
        .card.bg-primary { background: linear-gradient(135deg, #007bff, #0056b3); }
        .card.bg-secondary { background: linear-gradient(135deg, #6c757d, #495057); }
        .card.bg-info { background: linear-gradient(135deg, #17a2b8, #117a8b); }
        .card.bg-warning { background: linear-gradient(135deg, #ffc107, #d39e00); }
        .card.bg-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .card-icon {
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.95);
        }
        .card-count {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }
        .card-title {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #fff;
        }
        .card-text {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
        }
        .btn-light {
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 6px;
        }
        .modal-content {
            border-radius: 10px;
            border: none;
        }
        .modal-header.bg-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-bottom: none;
        }
        .form-section h4 {
            font-size: 1.1rem;
            color: #1a3c6d;
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .card-body {
                min-height: 160px;
            }
            .card-count {
                font-size: 1.6rem;
            }
            .card-icon {
                font-size: 1.8rem;
            }
            .card-title {
                font-size: 1.1rem;
            }
        }
        @media (max-width: 576px) {
            .card-body {
                min-height: 140px;
            }
            .card-text {
                font-size: 0.85rem;
            }
            .btn-light {
                font-size: 0.85rem;
                padding: 5px 10px;
            }
        }
    </style>


    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Welcome, Record Officer</h2>
            <div class="row mt-3 g-3">
                <div class="col-md-3">
                    <div class="card bg-secondary text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-plus-circle card-icon"></i>
                            <h5 class="card-title">Register</h5>
                            <p class="card-text">Register new land.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/registration.php" class="btn btn-light">Register</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-flag card-icon"></i>
                            <h5 class="card-title">Report Case</h5>
                            <p class="card-text">Report new cases.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/Report_Case.php" class="btn btn-light">Report</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-exclamation-circle card-icon"></i>
                            <h5 class="card-title">Reported Cases</h5>
                            <div class="card-count">28</div>
                            <p class="card-text">View all reported cases.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/reported_cases.php" class="btn btn-light">View</a>
                        </div>
                    </div>
                </div>
                
            <div class="row mt-3 g-3">
                <div class="col-md-3">
                    <div class="card bg-success text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-check-circle card-icon"></i>
                            <h5 class="card-title">Approved Cases</h5>
                            <div class="card-count">4</div>
                            <p class="card-text">View approved cases.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/approved_cases.php" class="btn btn-light">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-file-alt card-icon"></i>
                            <h5 class="card-title">Recent Files</h5>
                            <div class="card-count">23</div>
                            <p class="card-text">View files from last 30 days.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/files.php" class="btn btn-light">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-folder-open card-icon"></i>
                            <h5 class="card-title">Files</h5>
                            <div class="card-count">23</div>
                            <p class="card-text">View all files.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/files.php" class="btn btn-light">View</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-primary text-white h-100 shadow">
                        <div class="card-body">
                            <i class="fas fa-calculator card-icon"></i>
                            <h5 class="card-title">Tax</h5>
                            <p class="card-text">Calculate tax.</p>
                            <a href="http://localhost/landinfo/modules/record_officer/tax/tax.php" class="btn btn-light">Calculate</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="registrationModalLabel">Land Registration Form</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="http://localhost/landinfo/modules/record_officer/submit_form.php" id="registrationForm" enctype="multipart/form-data">
                            <div class="form-section mb-4">
                                <h4 class="mb-3">Personal Information</h4>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="first_name" placeholder="First Name" required="">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="middle_name" placeholder="Middle Name">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="last_name" placeholder="Last Name" required="">
                                    </div>
                                </div>
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <select class="form-select" name="gender" required="">
                                            <option value="">Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="number" class="form-control" name="age" placeholder="Age" required="">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section mb-4">
                                <h4 class="mb-3">Land Details</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="registration_number" placeholder="Registration Number" required="">
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="village" required="">
                                            <option value="">Select Village</option>
                                            <option value="Abba Sayyyaa">Abba Sayyyaa</option>
                                            <option value="Soor">Soor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="zone" placeholder="Zone" required="">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" name="land_grade" placeholder="Land Grade" required="">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="land_service" required="">
                                            <option value="">Land Service</option>
                                            <option value="residential">Residential</option>
                                            <option value="commercial">Commercial</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section mb-4">
                                <h4 class="mb-3">Neighbor Information</h4>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="neighbor_east" placeholder="East Neighbor" required="">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="neighbor_west" placeholder="West Neighbor" required="">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="neighbor_south" placeholder="South Neighbor" required="">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="neighbor_north" placeholder="North Neighbor" required="">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section mb-4">
                                <h4 class="mb-3">Document Uploads</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Xalayaa Miritii Wajjira</label>
                                        <input type="file" class="form-control" name="xalayaa_miritii" accept=".pdf,.jpg,.png">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Naga'ee Gibiraa</label>
                                        <input type="file" class="form-control" name="nagaee_gibiraa" accept=".pdf,.jpg,.png">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section mb-4">
                                <h4 class="mb-3">Photo Uploads</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Owner Photo</label>
                                        <input type="file" class="form-control" name="owner_photo" accept="image/*" onchange="previewPhoto(event, &#39;ownerPreview&#39;)">
                                        <img id="ownerPreview" src="./language_files/placeholder.jpg" class="img-thumbnail mt-2" style="max-width: 120px;">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Spouse Photo</label>
                                        <input type="file" class="form-control" name="spouse_photo" accept="image/*" onchange="previewPhoto(event, &#39;spousePreview&#39;)">
                                        <img id="spousePreview" src="./language_files/placeholder.jpg" class="img-thumbnail mt-2" style="max-width: 120px;">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Submit Registration</button>
                            </div>
                        </form>
                        <div id="formMessage" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function previewPhoto(event, previewId) {
            const reader = new FileReader();
            reader.onload = function () {
                document.getElementById(previewId).src = reader.result;
            };
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
        document.getElementById('registrationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const messageDiv = document.getElementById('formMessage');
            try {
                submitBtn.disabled = true;
                messageDiv.innerHTML = '';
                const response = await fetch('submit_form.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Submission failed');
                messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        Registration successful! Redirecting...
                    </div>
                `;
                form.reset();
                document.querySelectorAll('.img-thumbnail').forEach(img => {
                    img.src = 'placeholder.jpg';
                });
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('registrationModal')).hide();
                    location.reload();
                }, 1500);
            } catch (error) {
                messageDiv.innerHTML = `
                    <div class="alert alert-danger">
                        Error: ${error.message}
                    </div>
                `;
            } finally {
                submitBtn.disabled = false;
            }
        });
    </script>

</div></div><template id="auto-clicker-autofill-popup"></template>
<template id="auto-clicker-autofill-popup-tr"></template>
</body></html>