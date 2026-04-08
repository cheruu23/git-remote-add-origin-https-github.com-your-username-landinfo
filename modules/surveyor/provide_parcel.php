<?php
// Initialize session and required files
require_once '../../includes/init.php';

// Redirect if not logged in or unauthorized
redirectIfNotLoggedIn();
if (!in_array($_SESSION['user']['role'], ['manager', 'surveyor', 'record_officer'])) {
    die("Access denied! Only managers, surveyors, or record officers can provide parcels.");
}

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die($translations['om']['db_error'] ?? "Database connection failed. Please try again later.");
}

// Get user details
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$land_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$debug_log = __DIR__ . '/debug.log';

// Initialize variables
$error_message = '';
$success_message = '';
$landowner_data = null;

// Fetch landowner data if land_id is provided
if ($land_id) {
    try {
        $stmt = $conn->prepare("
            SELECT id, owner_name, first_name, middle_name, gender, owner_phone, land_type, village, zone,
                   block_number, parcel_number, effective_date, group_category, land_grade, land_service,
                   neighbor_east, neighbor_west, neighbor_south, neighbor_north, id_front, id_back,
                   owner_photo, agreement_number, duration, area, purpose, plot_number, coordinates,
                   surveyor_name, head_surveyor_name, land_officer_name, has_parcel, parcel_lease_date,
                   parcel_agreement_number, parcel_lease_duration, coord1_x, coord1_y, coord2_x, coord2_y,
                   coord3_x, coord3_y, coord4_x, coord4_y, parcel_village, parcel_block_number,
                   parcel_land_grade, parcel_land_area, parcel_land_service, parcel_registration_number,
                   building_height_allowed, prepared_by_name, prepared_by_role, approved_by_name,
                   approved_by_role, authorized_by_name, authorized_by_role, status
            FROM land_registration 
            WHERE id = :land_id
        ");
        $stmt->execute(['land_id' => $land_id]);
        $landowner_data = $stmt->fetch();
        if ($landowner_data) {
            $landowner_data['full_name'] = htmlspecialchars(trim(
                ($landowner_data['owner_name'] ?? '') . ' ' . 
                ($landowner_data['first_name'] ?? '') . ' ' . 
                ($landowner_data['middle_name'] ?? '')
            ));
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Fetched landowner data for ID $land_id: " . json_encode($landowner_data) . "\n", FILE_APPEND);
        } else {
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - No land record found for ID $land_id\n", FILE_APPEND);
            $error_message = $translations['om']['no_land_record'] ?? "No land record found for Land ID: " . htmlspecialchars($land_id);
        }
    } catch (PDOException $e) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error fetching landowner data for ID $land_id: " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = $translations['om']['query_error'] ?? "Error fetching land details: " . $e->getMessage();
    }
}

// Fetch landowner data if lakk_addaa is provided via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lakk_addaa']) && !empty(trim($_POST['lakk_addaa']))) {
    $lakk_addaa = trim($_POST['lakk_addaa']);
    if (!is_numeric($lakk_addaa)) {
        $error_message = $translations['om']['invalid_land_id'] ?? "Invalid Land ID: Must be a number.";
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Invalid Land ID format ($lakk_addaa)\n", FILE_APPEND);
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT id, owner_name, first_name, middle_name, gender, owner_phone, land_type, village, zone,
                       block_number, parcel_number, effective_date, group_category, land_grade, land_service,
                       neighbor_east, neighbor_west, neighbor_south, neighbor_north, id_front, id_back,
                       owner_photo, agreement_number, duration, area, purpose, plot_number, coordinates,
                       surveyor_name, head_surveyor_name, land_officer_name, has_parcel, parcel_lease_date,
                       parcel_agreement_number, parcel_lease_duration, coord1_x, coord1_y, coord2_x, coord2_y,
                       coord3_x, coord3_y, coord4_x, coord4_y, parcel_village, parcel_block_number,
                       parcel_land_grade, parcel_land_area, parcel_land_service, parcel_registration_number,
                       building_height_allowed, prepared_by_name, prepared_by_role, approved_by_name,
                       approved_by_role, authorized_by_name, authorized_by_role, status
                FROM land_registration 
                WHERE id = :land_id
            ");
            $stmt->execute(['land_id' => $lakk_addaa]);
            $landowner_data = $stmt->fetch();
            if ($landowner_data) {
                $land_id = (int)$lakk_addaa;
                $landowner_data['full_name'] = htmlspecialchars(trim(
                    ($landowner_data['owner_name'] ?? '') . ' ' . 
                    ($landowner_data['first_name'] ?? '') . ' ' . 
                    ($landowner_data['middle_name'] ?? '')
                ));
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Fetched landowner data for ID $lakk_addaa: " . json_encode($landowner_data) . "\n", FILE_APPEND);
            } else {
                $error_message = $translations['om']['no_land_record'] ?? "No record found for Land ID: " . htmlspecialchars($lakk_addaa);
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - No land record found for ID $lakk_addaa\n", FILE_APPEND);
            }
        } catch (PDOException $e) {
            $error_message = $translations['om']['query_error'] ?? "Error fetching land details: " . $e->getMessage();
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error querying Land ID $lakk_addaa: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lakk_addaa'])) {
    $error_message = $translations['om']['land_id_required'] ?? "Land ID (lakk_addaa) is required.";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: lakk_addaa is empty\n", FILE_APPEND);
}

// Handle parcel submission or approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_parcel']) || isset($_POST['approve_parcel'])) && $landowner_data) {
    $required_fields = [
        'lakk_addaa', 'parcel_number', 'parcel_land_area', 'status', 'parcel_lease_date', 
        'parcel_agreement_number', 'parcel_lease_duration', 'coord1_x', 'coord1_y', 
        'coord2_x', 'coord2_y', 'coord3_x', 'coord3_y', 'coord4_x', 'coord4_y', 
        'parcel_village', 'parcel_block_number', 'parcel_land_grade', 'parcel_land_service', 
        'parcel_registration_number', 'building_height_allowed', 'prepared_by_name', 
        'prepared_by_role', 'approved_by_name', 'approved_by_role', 'authorized_by_name', 
        'authorized_by_role'
    ];
    $all_filled = true;
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $all_filled = false;
            $error_message = $translations['om']['required_fields'] ?? "Please fill all required fields.";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Missing required field $field\n", FILE_APPEND);
            break;
        }
    }

    $lease_date = $_POST['parcel_lease_date'] ?? '';
    if ($all_filled && !preg_match('/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])\/(19|20)\d{2}$/', $lease_date)) {
        $all_filled = false;
        $error_message = $translations['om']['invalid_date_format'] ?? "Invalid lease agreement date format. Use mm/dd/yyyy.";
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Invalid lease date format ($lease_date)\n", FILE_APPEND);
    }

    $coords = [
        'x1' => $_POST['coord1_x'] ?? '', 'y1' => $_POST['coord1_y'] ?? '',
        'x2' => $_POST['coord2_x'] ?? '', 'y2' => $_POST['coord2_y'] ?? '',
        'x3' => $_POST['coord3_x'] ?? '', 'y3' => $_POST['coord3_y'] ?? '',
        'x4' => $_POST['coord4_x'] ?? '', 'y4' => $_POST['coord4_y'] ?? ''
    ];
    foreach ($coords as $key => $value) {
        if ($all_filled && !is_numeric($value)) {
            $all_filled = false;
            $error_message = $translations['om']['invalid_coordinate'] ?? "Invalid coordinate value for $key.";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Invalid coordinate $key ($value)\n", FILE_APPEND);
            break;
        }
    }

    // Handle photo upload
    $owner_photo = $landowner_data['owner_photo'] ?? '';
    if ($all_filled && isset($_FILES['owner_photo']) && $_FILES['owner_photo']['size'] > 0) {
        $file = $_FILES['owner_photo'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $upload_dir = __DIR__ . '/../../Uploads/photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (!in_array($file['type'], $allowed_types)) {
            $all_filled = false;
            $error_message = $translations['om']['invalid_photo_format'] ?? "Invalid photo format. Only JPEG or PNG allowed.";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Invalid photo format (" . $file['type'] . ")\n", FILE_APPEND);
        } elseif ($file['size'] > $max_size) {
            $all_filled = false;
            $error_message = $translations['om']['photo_size_exceeded'] ?? "Photo size exceeds 2MB.";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Photo size too large (" . $file['size'] . " bytes)\n", FILE_APPEND);
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = "owner_{$land_id}_" . time() . ".$ext";
            $upload_path = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $owner_photo = "Uploads/photos/$new_filename";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Photo uploaded: $owner_photo\n", FILE_APPEND);
            } else {
                $all_filled = false;
                $error_message = $translations['om']['photo_upload_failed'] ?? "Failed to upload photo.";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Photo upload failed\n", FILE_APPEND);
            }
        }
    }

    if ($all_filled) {
        try {
            $conn->beginTransaction();

            $land_id = (int)$_POST['lakk_addaa'];
            $parcel_number = $_POST['parcel_number'];
            $parcel_land_area = (float)$_POST['parcel_land_area'];
            $status = isset($_POST['approve_parcel']) ? 'Approved' : $_POST['status'];
            $parcel_agreement_number = $_POST['parcel_agreement_number'];
            $parcel_lease_duration = (int)$_POST['parcel_lease_duration'];
            $parcel_village = $_POST['parcel_village'];
            $parcel_block_number = $_POST['parcel_block_number'];
            $parcel_land_grade = $_POST['parcel_land_grade'];
            $parcel_land_service = $_POST['parcel_land_service'];
            $parcel_registration_number = $_POST['parcel_registration_number'];
            $building_height_allowed = $_POST['building_height_allowed'];
            $prepared_by_name = $_POST['prepared_by_name'];
            $prepared_by_role = $_POST['prepared_by_role'];
            $approved_by_name = $_POST['approved_by_name'];
            $approved_by_role = $_POST['approved_by_role'];
            $authorized_by_name = $_POST['authorized_by_name'];
            $authorized_by_role = $_POST['authorized_by_role'];

            $lease_date_obj = DateTime::createFromFormat('m/d/Y', $lease_date);
            $parcel_lease_date = $lease_date_obj ? $lease_date_obj->format('Y-m-d') : null;

            $coordinates = [
                ['x' => (float)$coords['x1'], 'y' => (float)$coords['y1']],
                ['x' => (float)$coords['x2'], 'y' => (float)$coords['y2']],
                ['x' => (float)$coords['x3'], 'y' => (float)$coords['y3']],
                ['x' => (float)$coords['x4'], 'y' => (float)$coords['y4']]
            ];
            $coordinates_json = json_encode($coordinates);

            // Update land_registration
            $stmt = $conn->prepare("
                UPDATE land_registration 
                SET 
                    parcel_number = :parcel_number,
                    parcel_land_area = :parcel_land_area,
                    status = :status,
                    parcel_lease_date = :parcel_lease_date,
                    parcel_agreement_number = :parcel_agreement_number,
                    parcel_lease_duration = :parcel_lease_duration,
                    coord1_x = :coord1_x,
                    coord1_y = :coord1_y,
                    coord2_x = :coord2_x,
                    coord2_y = :coord2_y,
                    coord3_x = :coord3_x,
                    coord3_y = :coord3_y,
                    coord4_x = :coord4_x,
                    coord4_y = :coord4_y,
                    parcel_village = :parcel_village,
                    parcel_block_number = :parcel_block_number,
                    parcel_land_grade = :parcel_land_grade,
                    parcel_land_service = :parcel_land_service,
                    parcel_registration_number = :parcel_registration_number,
                    building_height_allowed = :building_height_allowed,
                    prepared_by_name = :prepared_by_name,
                    prepared_by_role = :prepared_by_role,
                    approved_by_name = :approved_by_name,
                    approved_by_role = :approved_by_role,
                    authorized_by_name = :authorized_by_name,
                    authorized_by_role = :authorized_by_role,
                    has_parcel = 1,
                    owner_photo = :owner_photo,
                    coordinates = :coordinates
                WHERE id = :land_id
            ");
            $stmt->execute([
                'parcel_number' => $parcel_number,
                'parcel_land_area' => $parcel_land_area,
                'status' => $status,
                'parcel_lease_date' => $parcel_lease_date,
                'parcel_agreement_number' => $parcel_agreement_number,
                'parcel_lease_duration' => $parcel_lease_duration,
                'coord1_x' => (float)$coords['x1'],
                'coord1_y' => (float)$coords['y1'],
                'coord2_x' => (float)$coords['x2'],
                'coord2_y' => (float)$coords['y2'],
                'coord3_x' => (float)$coords['x3'],
                'coord3_y' => (float)$coords['y3'],
                'coord4_x' => (float)$coords['x4'],
                'coord4_y' => (float)$coords['y4'],
                'parcel_village' => $parcel_village,
                'parcel_block_number' => $parcel_block_number,
                'parcel_land_grade' => $parcel_land_grade,
                'parcel_land_service' => $parcel_land_service,
                'parcel_registration_number' => $parcel_registration_number,
                'building_height_allowed' => $building_height_allowed,
                'prepared_by_name' => $prepared_by_name,
                'prepared_by_role' => $prepared_by_role,
                'approved_by_name' => $approved_by_name,
                'approved_by_role' => $approved_by_role,
                'authorized_by_name' => $authorized_by_name,
                'authorized_by_role' => $authorized_by_role,
                'owner_photo' => $owner_photo,
                'coordinates' => $coordinates_json,
                'land_id' => $land_id
            ]);

            // Update investigation_status in cases table
            $stmt = $conn->prepare("SELECT id FROM cases WHERE land_id = :land_id LIMIT 1");
            $stmt->execute(['land_id' => $land_id]);
            $case = $stmt->fetch();
            if ($case) {
                $stmt = $conn->prepare("
                    UPDATE cases 
                    SET investigation_status = 'Approved', updated_at = CURRENT_TIMESTAMP 
                    WHERE land_id = :land_id AND investigation_status != 'Approved'
                ");
                $stmt->execute(['land_id' => $land_id]);
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Updated investigation_status to Approved for case with land_id $land_id\n", FILE_APPEND);
            } else {
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - No case found for land_id $land_id\n", FILE_APPEND);
            }

            // Send notifications
            $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'manager' AND is_locked = 0 LIMIT 1");
            $stmt->execute();
            $manager = $stmt->fetch();
            if ($manager) {
                $message = $translations['om']['notification_parcel'] ?? "Parcel '$parcel_number' for Land ID: $land_id has been " . (isset($_POST['approve_parcel']) ? "approved" : "updated") . " and requires your review.";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())");
                $stmt->execute(['user_id' => $manager['id'], 'message' => $message]);
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Notification sent to manager for parcel $parcel_number\n", FILE_APPEND);
            }

            $conn->commit();
            $success_message = isset($_POST['approve_parcel']) ? ($translations['om']['parcel_approved'] ?? "Parcel approved successfully!") : ($translations['om']['parcel_updated'] ?? "Parcel updated successfully!");
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Land ID $land_id updated with parcel data, Status: $status\n", FILE_APPEND);
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = $translations['om']['update_error'] ?? "Error updating parcel: " . $e->getMessage();
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Transaction Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
?>

<!DOCTYPE html>
<html lang="om">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kennuu Qabiyyee Lafa - Oromia Land Administration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 20px;
        }
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            border: 2px solid #000;
            background-color: #fffdf5;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1, .header h2 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }
        .logo {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 80px;
            height: 80px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            font-weight: 500;
            font-size: 14px;
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .coordinates-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .error-message {
            color: #c0392b;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
        }
        .text-success {
            color: #27ae60;
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-primary {
            background: #3498db;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #7f8c8d;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #6c7a89;
        }
        .btn-success {
            background: #28a745;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
        }
        .btn-success:hover {
            background: #218838;
        }
        .owner-photo img {
            max-width: 150px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .footer-images {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .logo-bottom {
            width: 80px;
            height: 80px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .form-container {
                padding: 15px;
            }
            .coordinates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="form-container">
            <!-- Header -->
            <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo">
            <div class="header">
                <h1><?php echo $translations['om']['header'] ?? 'Bulchinsa Mootummaa Naannoo Oromiyaa'; ?></h1>
                <h2><?php echo $translations['om']['header_sub'] ?? 'Oromia Land Administration and Use Bureau'; ?></h2>
            </div>

            <!-- Messages -->
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="text-success"><?php echo htmlspecialchars($success_message); ?></p>
                <div class="d-flex justify-content-between">
                    <a href="<?php echo BASE_URL . '/modules/' . htmlspecialchars($role) . '/dashboard.php'; ?>" class="btn btn-secondary"><?php echo $translations['om']['back_to_dashboard'] ?? 'Back to Dashboard'; ?></a>
                    <a href="<?php echo BASE_URL . '/modules/surveyor/provide_parcels.php'; ?>" class="btn btn-primary"><?php echo $translations['om']['provide_another'] ?? 'Provide Another Parcel'; ?></a>
                </div>
            <?php else: ?>
                <!-- Parcel Form -->
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="submit_parcel" value="1">

                    <!-- 1. Landowner Information -->
                    <div class="section">
                        <h3>1. <?php echo $translations['om']['landowner_info'] ?? 'Odeeffannoo Abbaa Qabiyyee'; ?></h3>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="lakk_addaa"><?php echo $translations['om']['land_id'] ?? 'ID'; ?>:</label>
                                <input type="text" name="lakk_addaa" id="lakk_addaa" class="form-control" value="<?php echo htmlspecialchars($land_id ?? ($_POST['lakk_addaa'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_land_id'] ?? 'Please enter a valid Land ID.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations['om']['full_name'] ?? 'Maqaa Guutuu'; ?>:</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($landowner_data['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations['om']['zone'] ?? 'Tessoon - Godina'; ?>:</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($landowner_data['zone'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations['om']['village'] ?? 'Ganda'; ?>:</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($landowner_data['village'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations['om']['block_number'] ?? 'Lakk. Manaa'; ?>:</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($landowner_data['block_number'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Lease Details -->
                    <div class="section">
                        <h3>2. <?php echo $translations['om']['lease_details'] ?? 'Lease Details'; ?></h3>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="parcel_lease_date"><?php echo $translations['om']['lease_date'] ?? 'Guyyaa Waligaltee Lease'; ?>:</label>
                                <input type="text" name="parcel_lease_date" id="parcel_lease_date" class="form-control" placeholder="mm/dd/yyyy" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_lease_date']) ? $_POST['parcel_lease_date'] : ($landowner_data['parcel_lease_date'] ? date('m/d/Y', strtotime($landowner_data['parcel_lease_date'])) : '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_date_format'] ?? 'Please enter a valid date (mm/dd/yyyy).'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_agreement_number"><?php echo $translations['om']['agreement_number'] ?? 'Lakkoofsa Waligaltee Lease'; ?>:</label>
                                <input type="text" name="parcel_agreement_number" id="parcel_agreement_number" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_agreement_number']) ? $_POST['parcel_agreement_number'] : ($landowner_data['parcel_agreement_number'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the lease agreement number.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_lease_duration"><?php echo $translations['om']['lease_duration'] ?? 'Muddama Waggaa Lease'; ?>:</label>
                                <input type="number" name="parcel_lease_duration" id="parcel_lease_duration" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_lease_duration']) ? $_POST['parcel_lease_duration'] : ($landowner_data['parcel_lease_duration'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the lease duration in years.'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. XY Coordinates -->
                    <div class="section">
                        <h3>3. <?php echo $translations['om']['coordinates'] ?? 'XY Coordinates (Qaxxaamura)'; ?></h3>
                        <div class="coordinates-grid">
                            <?php
                            $existing_coords = [];
                            if ($landowner_data && $landowner_data['coordinates']) {
                                $coords_array = json_decode($landowner_data['coordinates'], true);
                                $existing_coords = is_array($coords_array) ? $coords_array : [];
                            }
                            ?>
                            <div class="form-group">
                                <label for="coord1_x"><?php echo $translations['om']['coord_x1'] ?? 'Qaphxii 1 - X'; ?>:</label>
                                <input type="number" name="coord1_x" id="coord1_x" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord1_x']) ? $_POST['coord1_x'] : ($landowner_data['coord1_x'] ?? ($existing_coords[0]['x'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid X coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord1_y"><?php echo $translations['om']['coord_y1'] ?? 'Qaphxii 1 - Y'; ?>:</label>
                                <input type="number" name="coord1_y" id="coord1_y" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord1_y']) ? $_POST['coord1_y'] : ($landowner_data['coord1_y'] ?? ($existing_coords[0]['y'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid Y coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord2_x"><?php echo $translations['om']['coord_x2'] ?? 'Qaphxii 2 - X'; ?>:</label>
                                <input type="number" name="coord2_x" id="coord2_x" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord2_x']) ? $_POST['coord2_x'] : ($landowner_data['coord2_x'] ?? ($existing_coords[1]['x'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid X coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord2_y"><?php echo $translations['om']['coord_y2'] ?? 'Qaphxii 2 - Y'; ?>:</label>
                                <input type="number" name="coord2_y" id="coord2_y" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord2_y']) ? $_POST['coord2_y'] : ($landowner_data['coord2_y'] ?? ($existing_coords[1]['y'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid Y coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord3_x"><?php echo $translations['om']['coord_x3'] ?? 'Qaphxii 3 - X'; ?>:</label>
                                <input type="number" name="coord3_x" id="coord3_x" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord3_x']) ? $_POST['coord3_x'] : ($landowner_data['coord3_x'] ?? ($existing_coords[2]['x'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid X coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord3_y"><?php echo $translations['om']['coord_y3'] ?? 'Qaphxii 3 - Y'; ?>:</label>
                                <input type="number" name="coord3_y" id="coord3_y" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord3_y']) ? $_POST['coord3_y'] : ($landowner_data['coord3_y'] ?? ($existing_coords[2]['y'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid Y coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord4_x"><?php echo $translations['om']['coord_x4'] ?? 'Qaphxii 4 - X'; ?>:</label>
                                <input type="number" name="coord4_x" id="coord4_x" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord4_x']) ? $_POST['coord4_x'] : ($landowner_data['coord4_x'] ?? ($existing_coords[3]['x'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid X coordinate.'; ?></div>
                            </div>
                            <div class="form-group">
                                <label for="coord4_y"><?php echo $translations['om']['coord_y4'] ?? 'Qaphxii 4 - Y'; ?>:</label>
                                <input type="number" name="coord4_y" id="coord4_y" class="form-control" step="0.000001" 
                                       value="<?php echo htmlspecialchars(isset($_POST['coord4_y']) ? $_POST['coord4_y'] : ($landowner_data['coord4_y'] ?? ($existing_coords[3]['y'] ?? ''))); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['invalid_coordinate'] ?? 'Please enter a valid Y coordinate.'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Specific Land Information -->
                    <div class="section">
                        <h3>4. <?php echo $translations['om']['land_info'] ?? 'Odeeffannoo Lafa Addaa'; ?></h3>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="parcel_village"><?php echo $translations['om']['village'] ?? 'Ganda'; ?>:</label>
                                <input type="text" name="parcel_village" id="parcel_village" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_village']) ? $_POST['parcel_village'] : ($landowner_data['parcel_village'] ?? $landowner_data['village'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the village.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_block_number"><?php echo $translations['om']['block_number'] ?? 'Lak. Adda Bilookii'; ?>:</label>
                                <input type="text" name="parcel_block_number" id="parcel_block_number" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_block_number']) ? $_POST['parcel_block_number'] : ($landowner_data['parcel_block_number'] ?? $landowner_data['block_number'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the block number.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_number"><?php echo $translations['om']['parcel_number'] ?? 'Lak. Adda Parcelii'; ?>:</label>
                                <input type="text" name="parcel_number" id="parcel_number" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_number']) ? $_POST['parcel_number'] : ($landowner_data['parcel_number'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the parcel number.'; ?></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="parcel_land_grade"><?php echo $translations['om']['land_grade'] ?? 'Sadarkaa Lafaa'; ?>:</label>
                                <select name="parcel_land_grade" id="parcel_land_grade" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select'] ?? '-- Filadhu --'; ?></option>
                                    <option value="Level 1" <?php echo (isset($_POST['parcel_land_grade']) && $_POST['parcel_land_grade'] === 'Level 1' || $landowner_data['parcel_land_grade'] === 'Level 1') ? 'selected' : ''; ?>>Level 1</option>
                                    <option value="Level 2" <?php echo (isset($_POST['parcel_land_grade']) && $_POST['parcel_land_grade'] === 'Level 2' || $landowner_data['parcel_land_grade'] === 'Level 2') ? 'selected' : ''; ?>>Level 2</option>
                                    <option value="Level 3" <?php echo (isset($_POST['parcel_land_grade']) && $_POST['parcel_land_grade'] === 'Level 3' || $landowner_data['parcel_land_grade'] === 'Level 3') ? 'selected' : ''; ?>>Level 3</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select the land grade.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_land_area"><?php echo $translations['om']['land_area'] ?? 'Ballina Lafa (m²)'; ?>:</label>
                                <input type="number" name="parcel_land_area" id="parcel_land_area" class="form-control" step="0.01" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_land_area']) ? $_POST['parcel_land_area'] : ($landowner_data['parcel_land_area'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter a valid area.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="parcel_land_service"><?php echo $translations['om']['land_service'] ?? 'Tajaajila Lafaa'; ?>:</label>
                                <select name="parcel_land_service" id="parcel_land_service" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select'] ?? '-- Filadhu --'; ?></option>
                                    <option value="lafa daldalaa" <?php echo (isset($_POST['parcel_land_service']) && $_POST['parcel_land_service'] === 'lafa daldalaa' || $landowner_data['parcel_land_service'] === 'lafa daldalaa') ? 'selected' : ''; ?>>lafa daldalaa</option>
                                    <option value="lafa mana jireenyaa" <?php echo (isset($_POST['parcel_land_service']) && $_POST['parcel_land_service'] === 'lafa mana jireenyaa' || $landowner_data['parcel_land_service'] === 'lafa mana jireenyaa') ? 'selected' : ''; ?>>lafa mana jireenyaa</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select the land service.'; ?></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="parcel_registration_number"><?php echo $translations['om']['registration_number'] ?? 'Lak. Galmee Lafti'; ?>:</label>
                                <input type="text" name="parcel_registration_number" id="parcel_registration_number" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['parcel_registration_number']) ? $_POST['parcel_registration_number'] : ($landowner_data['parcel_registration_number'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the land record number.'; ?></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="building_height_allowed"><?php echo $translations['om']['building_height'] ?? 'Dheerina Gamoo Eeyyamamu (Mitta)'; ?>:</label>
                                <input type="text" name="building_height_allowed" id="building_height_allowed" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['building_height_allowed']) ? $_POST['building_height_allowed'] : ($landowner_data['building_height_allowed'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the permitted building height.'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Personnel Information -->
                    <div class="section">
                        <h3>5. <?php echo $translations['om']['personnel_info'] ?? 'Hojii Irratti Hojjettoota'; ?></h3>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="prepared_by_name"><?php echo $translations['om']['prepared_by'] ?? 'Qopheesse: Maqaa Guutuu'; ?>:</label>
                                <input type="text" name="prepared_by_name" id="prepared_by_name" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['prepared_by_name']) ? $_POST['prepared_by_name'] : ($landowner_data['prepared_by_name'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the preparer\'s name.'; ?></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="prepared_by_role"><?php echo $translations['om']['prepared_role'] ?? 'Gahee Hojii Qopheesse'; ?>:</label>
                                <select name="prepared_by_role" id="prepared_by_role" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select'] ?? '-- Filadhu --'; ?></option>
                                    <option value="Surveyor" <?php echo (isset($_POST['prepared_by_role']) && $_POST['prepared_by_role'] === 'Surveyor' || $landowner_data['prepared_by_role'] === 'Surveyor') ? 'selected' : ''; ?>>Surveyor</option>
                                    <option value="Manager" <?php echo (isset($_POST['prepared_by_role']) && $_POST['prepared_by_role'] === 'Manager' || $landowner_data['prepared_by_role'] === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select the preparer\'s role.'; ?></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="approved_by_name"><?php echo $translations['om']['approved_by'] ?? 'Eeyyame: Maqaa Guutuu'; ?>:</label>
                                <input type="text" name="approved_by_name" id="approved_by_name" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['approved_by_name']) ? $_POST['approved_by_name'] : ($landowner_data['approved_by_name'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the approver\'s name.'; ?></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="approved_by_role"><?php echo $translations['om']['approved_role'] ?? 'Gahee Hojii Eeyyame'; ?>:</label>
                                <select name="approved_by_role" id="approved_by_role" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select'] ?? '-- Filadhu --'; ?></option>
                                    <option value="Manager" <?php echo (isset($_POST['approved_by_role']) && $_POST['approved_by_role'] === 'Manager' || $landowner_data['approved_by_role'] === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                    <option value="Senior Manager" <?php echo (isset($_POST['approved_by_role']) && $_POST['approved_by_role'] === 'Senior Manager' || $landowner_data['approved_by_role'] === 'Senior Manager') ? 'selected' : ''; ?>>Senior Manager</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select the approver\'s role.'; ?></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="authorized_by_name"><?php echo $translations['om']['authorized_by'] ?? 'Mirkanesse: Maqaa Guutuu'; ?>:</label>
                                <input type="text" name="authorized_by_name" id="authorized_by_name" class="form-control" 
                                       value="<?php echo htmlspecialchars(isset($_POST['authorized_by_name']) ? $_POST['authorized_by_name'] : ($landowner_data['authorized_by_name'] ?? '')); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please enter the authorizer\'s name.'; ?></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="authorized_by_role"><?php echo $translations['om']['authorized_role'] ?? 'Gahee Hojii Mirkanesse'; ?>:</label>
                                <select name="authorized_by_role" id="authorized_by_role" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select'] ?? '-- Filadhu --'; ?></option>
                                    <option value="Surveyor" <?php echo (isset($_POST['authorized_by_role']) && $_POST['authorized_by_role'] === 'Surveyor' || $landowner_data['authorized_by_role'] === 'Surveyor') ? 'selected' : ''; ?>>Surveyor</option>
                                    <option value="Manager" <?php echo (isset($_POST['authorized_by_role']) && $_POST['authorized_by_role'] === 'Manager' || $landowner_data['authorized_by_role'] === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select the authorizer\'s role.'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Parcel Status -->
                    <div class="section">
                        <h3>6. <?php echo $translations['om']['parcel_status'] ?? 'Odeeffannoo Qabiyyee'; ?></h3>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="status"><?php echo $translations['om']['status'] ?? 'Haala'; ?>:</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value=""><?php echo $translations['om']['select_status'] ?? '-- Haala Filadhu --'; ?></option>
                                    <option value="Pending" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Pending' || $landowner_data['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Approved" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Approved' || $landowner_data['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Rejected' || $landowner_data['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <div class="invalid-feedback"><?php echo $translations['om']['required_field'] ?? 'Please select a status.'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 7. Owner Photo -->
                    <div class="section">
                        <h3>7. <?php echo $translations['om']['owner_photo'] ?? 'Owner Photo'; ?></h3>
                        <?php if ($landowner_data && $landowner_data['owner_photo'] && file_exists(__DIR__ . '/../../' . $landowner_data['owner_photo'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($landowner_data['owner_photo']); ?>" alt="Owner Photo" class="owner-photo">
                        <?php else: ?>
                            <p><?php echo $translations['om']['no_photo'] ?? 'No photo available'; ?></p>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="owner_photo"><?php echo $translations['om']['upload_photo'] ?? 'Upload New Photo (JPEG/PNG, max 2MB)'; ?>:</label>
                            <input type="file" name="owner_photo" id="owner_photo" class="form-control" accept="image/jpeg,image/png">
                            <div class="invalid-feedback"><?php echo $translations['om']['invalid_photo_format'] ?? 'Please upload a valid photo.'; ?></div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo BASE_URL . '/modules/' . htmlspecialchars($role) . '/dashboard.php'; ?>" class="btn btn-secondary"><?php echo $translations['om']['back_to_dashboard'] ?? 'Back to Dashboard'; ?></a>
                        <div>
                            <?php if ($role !== 'manager'): ?>
                                <button type="submit" name="approve_parcel" class="btn btn-success"><?php echo $translations['om']['approve_parcel'] ?? 'Approve Parcel'; ?></button>
                            <?php endif; ?>
                            <button type="submit" name="submit_parcel" class="btn btn-primary"><?php echo $translations['om']['submit_parcel'] ?? 'Proceed Parcel'; ?></button>
                        </div>
                    </div>

                    <!-- Footer Images -->
                    <div class="footer-images">
                        <img class="logo-bottom" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo">
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form validation
        (function () {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>