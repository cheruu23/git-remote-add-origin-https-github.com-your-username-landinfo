<?php
ob_start();
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

// Check if user is a surveyor
if (!isSurveyor()) {
    $translations = [
        'en' => ['access_denied' => 'Access denied! Only surveyors can transfer ownership.'],
        'om' => ['access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa abbaa qabeenyaa jijjiiru danda’u.']
    ];
    $lang = $_GET['lang'] ?? 'en';
    $_SESSION['error'] = $translations[$lang]['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'en';
$translations = [
    'en' => [
        'transfer_ownership_title' => 'Transfer Ownership',
        'current_landowner_details' => 'Current Landowner Details',
        'update_landowner_details' => 'Update Landowner Details',
        'land_id' => 'Land ID',
        'owner_name' => 'Owner Name (Surname)',
        'first_name' => 'First Name',
        'middle_name' => 'Middle Name (Optional)',
        'gender' => 'Gender',
        'phone_number' => 'Phone Number (Optional)',
        'id_front' => 'ID Front (Optional, JPEG/PNG, max 5MB)',
        'id_back' => 'ID Back (Optional, JPEG/PNG, max 5MB)',
        'owner_photo' => 'Owner Photo (Optional, JPEG/PNG, max 5MB)',
        'zone' => 'Zone',
        'village' => 'Village',
        'block_number' => 'Block Number',
        'no_land_record' => 'No land record available.',
        'success_update' => 'Landowner details updated successfully.',
        'error_invalid_land_id' => 'Invalid land ID.',
        'error_land_not_found' => 'Land record not found.',
        'error_fetch_land' => 'Error fetching land details.',
        'error_invalid_owner_name' => 'Owner name, first name, and gender are required.',
        'error_invalid_gender' => 'Invalid gender selected.',
        'error_invalid_phone' => 'Invalid phone number format.',
        'error_upload_id_front' => 'Invalid ID Front file. Must be JPEG/PNG, max 5MB.',
        'error_upload_id_back' => 'Invalid ID Back file. Must be JPEG/PNG, max 5MB.',
        'error_upload_owner_photo' => 'Invalid Owner Photo file. Must be JPEG/PNG, max 5MB.',
        'error_failed_upload_id_front' => 'Failed to upload ID Front.',
        'error_failed_upload_id_back' => 'Failed to upload ID Back.',
        'error_failed_upload_owner_photo' => 'Failed to upload Owner Photo.',
        'error_update_land' => 'Failed to update landowner details.',
        'error_audit_log' => 'Failed to log ownership transfer.',
        'back_to_case' => 'Back to Case',
        'update_details' => 'Update Details',
        'male' => 'Dhiira (Male)',
        'female' => 'Dubartii (Female)'
    ],
    'om' => [
        'transfer_ownership_title' => 'Abbaa Qabeenyaa Jijjiiri',
        'current_landowner_details' => 'Odeeffannoo Abbaa Qabeessaa Ammaa',
        'update_landowner_details' => 'Odeeffannoo Abbaa Qabeessaa Haaromsi',
        'land_id' => 'Lakkoofsa Lafa',
        'owner_name' => 'Maqaa Abbaa Qabeessaa (Maqaa Abaa)',
        'first_name' => 'Maqaa Jalqabaa',
        'middle_name' => 'Maqaa Abaa (Filanno)',
        'gender' => 'Saala',
        'phone_number' => 'Lakkoofsa Bilbilaa (Filanno)',
        'id_front' => 'ID Fuula Durii (Filanno, JPEG/PNG, max 5MB)',
        'id_back' => 'ID Duubaa (Filanno, JPEG/PNG, max 5MB)',
        'owner_photo' => 'Suuraa Abbaa Qabeessaa (Filanno, JPEG/PNG, max 5MB)',
        'zone' => 'Aanaa',
        'village' => 'Ganda',
        'block_number' => 'Lakkoofsa Buufata',
        'no_land_record' => 'Galmee lafa hin argamne.',
        'success_update' => 'Odeeffannoo abbaa qabeessaa milkiin haaromfame.',
        'error_invalid_land_id' => 'Lakkoofsa lafa sirrii miti.',
        'error_land_not_found' => 'Galmee lafa hin argamne.',
        'error_fetch_land' => 'Odeeffannoo lafa argachuun hin danda’amne.',
        'error_invalid_owner_name' => 'Maqaa abbaa qabeessaa, maqaa jalqabaa, fi saala barbaachisadha.',
        'error_invalid_gender' => 'Saala filatame sirrii miti.',
        'error_invalid_phone' => 'Foormaa lakkoofsa bilbilaa sirrii miti.',
        'error_upload_id_front' => 'Fayilii ID Fuula Durii sirrii miti. JPEG/PNG ta’uu qaba, max 5MB.',
        'error_upload_id_back' => 'Fayilii ID Duubaa sirrii miti. JPEG/PNG ta’uu qaba, max 5MB.',
        'error_upload_owner_photo' => 'Fayilii Suuraa Abbaa Qabeessaa sirrii miti. JPEG/PNG ta’uu qaba, max 5MB.',
        'error_failed_upload_id_front' => 'ID Fuula Durii fe’achuun hin danda’amne.',
        'error_failed_upload_id_back' => 'ID Duubaa fe’achuun hin danda’amne.',
        'error_failed_upload_owner_photo' => 'Suuraa Abbaa Qabeessaa fe’achuun hin danda’amne.',
        'error_update_land' => 'Odeeffannoo abbaa qabeessaa haaromsuun hin danda’amne.',
        'error_audit_log' => 'Jijjiirra abbaa qabeenyaa galmeessuun hin danda’amne.',
        'back_to_case' => 'Keesiitti Deebii',
        'update_details' => 'Odeeffannoo Haaromsi',
        'male' => 'Dhiira',
        'female' => 'Dubartii'
    ]
];

$conn = getDBConnection();

// Initialize messages
$success = null;
$error = null;
$land = null;
$debug_log = __DIR__ . '/debug.log';

// Define BASE_URL and UPLOAD_DIR
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
$upload_dir = __DIR__ . '/../../Uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get land_id
$land_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch current land details
if ($land_id) {
    try {
        $stmt = $conn->prepare("
            SELECT id, owner_name, first_name, middle_name, gender, owner_phone, zone, village, block_number, owner_photo, id_front, id_back
            FROM land_registration
            WHERE id = ?
        ");
        $stmt->bind_param('i', $land_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $land = $result->fetch_assoc();
        if (!$land) {
            file_put_contents($debug_log, "No land found for Land ID $land_id\n", FILE_APPEND);
            $error = $translations[$lang]['error_land_not_found'];
        } else {
            file_put_contents($debug_log, "Fetched land data for Land ID $land_id: " . json_encode($land) . "\n", FILE_APPEND);
        }
        $stmt->close();
    } catch (Exception $e) {
        file_put_contents($debug_log, "Error fetching land ID $land_id: " . $e->getMessage() . "\n", FILE_APPEND);
        $error = $translations[$lang]['error_fetch_land'] . ": " . $e->getMessage();
    }
} else {
    $error = $translations[$lang]['error_invalid_land_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $land) {
    $new_owner_name = trim($_POST['owner_name'] ?? '');
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_middle_name = trim($_POST['middle_name'] ?? '');
    $new_gender = trim($_POST['gender'] ?? '');
    $new_owner_phone = trim($_POST['owner_phone'] ?? '');

    // Initialize file paths with current values
    $new_id_front = $land['id_front'];
    $new_id_back = $land['id_back'];
    $new_owner_photo = $land['owner_photo'];

    // Handle file uploads
    $allowed_types = ['image/jpeg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Process id_front
    if (!empty($_FILES['id_front']['name'])) {
        if ($_FILES['id_front']['error'] === UPLOAD_ERR_OK && $_FILES['id_front']['size'] <= $max_size && in_array($_FILES['id_front']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION);
            $new_id_front = "Uploads/id_front_{$land_id}_" . time() . ".$ext";
            if (!move_uploaded_file($_FILES['id_front']['tmp_name'], __DIR__ . '/../../' . $new_id_front)) {
                $error = $translations[$lang]['error_failed_upload_id_front'];
            }
        } else {
            $error = $translations[$lang]['error_upload_id_front'];
        }
    }

    // Process id_back
    if (!empty($_FILES['id_back']['name']) && !$error) {
        if ($_FILES['id_back']['error'] === UPLOAD_ERR_OK && $_FILES['id_back']['size'] <= $max_size && in_array($_FILES['id_back']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['id_back']['name'], PATHINFO_EXTENSION);
            $new_id_back = "Uploads/id_back_{$land_id}_" . time() . ".$ext";
            if (!move_uploaded_file($_FILES['id_back']['tmp_name'], __DIR__ . '/../../' . $new_id_back)) {
                $error = $translations[$lang]['error_failed_upload_id_back'];
            }
        } else {
            $error = $translations[$lang]['error_upload_id_back'];
        }
    }

    // Process owner_photo
    if (!empty($_FILES['owner_photo']['name']) && !$error) {
        if ($_FILES['owner_photo']['error'] === UPLOAD_ERR_OK && $_FILES['owner_photo']['size'] <= $max_size && in_array($_FILES['owner_photo']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['owner_photo']['name'], PATHINFO_EXTENSION);
            $new_owner_photo = "Uploads/owner_photo_{$land_id}_" . time() . ".$ext";
            if (!move_uploaded_file($_FILES['owner_photo']['tmp_name'], __DIR__ . '/../../' . $new_owner_photo)) {
                $error = $translations[$lang]['error_failed_upload_owner_photo'];
            }
        } else {
            $error = $translations[$lang]['error_upload_owner_photo'];
        }
    }

    // Validation for non-file fields
    if (!$error) {
        if (empty($new_owner_name) || empty($new_first_name) || empty($new_gender)) {
            $error = $translations[$lang]['error_invalid_owner_name'];
        } elseif (!in_array($new_gender, ['Dhiira', 'Dubartii'])) {
            $error = $translations[$lang]['error_invalid_gender'];
        } elseif (!empty($new_owner_phone) && !preg_match('/^\+?\d{9,15}$/', $new_owner_phone)) {
            $error = $translations[$lang]['error_invalid_phone'];
        }
    }

    // Update database if no errors
    if (!$error) {
        try {
            // Store old values for audit
            $old_owner_name = $land['owner_name'];
            $old_gender = $land['gender'];
            $changed_by = $_SESSION['user_id']; // Assumes auth.php sets user_id

            // Update land_registration
            $stmt = $conn->prepare("
                UPDATE land_registration 
                SET owner_name = ?, first_name = ?, middle_name = ?, gender = ?, owner_phone = ?, id_front = ?, id_back = ?, owner_photo = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssssssssi', $new_owner_name, $new_first_name, $new_middle_name, $new_gender, $new_owner_phone, $new_id_front, $new_id_back, $new_owner_photo, $land_id);
            if ($stmt->execute()) {
                // Log audit entry in ownership_transfers
                $stmt = $conn->prepare("
                    INSERT INTO ownership_transfers (land_id, old_owner_name, new_owner_name, old_gender, new_gender, changed_by, change_date)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param('issssi', $land_id, $old_owner_name, $new_owner_name, $old_gender, $new_gender, $changed_by);
                if ($stmt->execute()) {
                    $success = $translations[$lang]['success_update'];
                    file_put_contents($debug_log, "Updated land ID $land_id with new owner: $new_owner_name, $new_first_name, $new_middle_name, $new_gender, $new_owner_phone, id_front: $new_id_front, id_back: $new_id_back, owner_photo: $new_owner_photo\n", FILE_APPEND);
                    file_put_contents($debug_log, "Audit logged for land ID $land_id: Old owner: $old_owner_name ($old_gender), New owner: $new_owner_name ($new_gender), Changed by: $changed_by\n", FILE_APPEND);
                } else {
                    $error = $translations[$lang]['error_audit_log'] . ": " . $stmt->error;
                    file_put_contents($debug_log, "Failed to log audit for land ID $land_id: " . $stmt->error . "\n", FILE_APPEND);
                }
                $stmt->close();

                // Refresh land data
                $stmt = $conn->prepare("
                    SELECT id, owner_name, first_name, middle_name, gender, owner_phone, zone, village, block_number, owner_photo, id_front, id_back
                    FROM land_registration
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $land_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $land = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = $translations[$lang]['error_update_land'];
                file_put_contents($debug_log, "Failed to update land ID $land_id: " . $stmt->error . "\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            $error = $translations[$lang]['error_update_land'] . ": " . $e->getMessage();
            file_put_contents($debug_log, "Error updating land ID $land_id: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['transfer_ownership_title']; ?> - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background: #fff;
            padding: 20px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            font-size: 1.4rem;
            color: #c0392b;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .section p {
            font-size: 1rem;
            color: #2c3e50;
            margin: 5px 0;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px;
        }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px 16px;
            color: #fff;
            text-decoration: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px 16px;
            color: #fff;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        .alert {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 6px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .card {
                padding: 15px;
            }
            .image-preview {
                max-width: 100px;
                max-height: 100px;
            }
            .form-control, .form-select {
                font-size: 0.8rem;
            }
            .btn-primary, .btn-secondary {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }
        @media print {
            .alert, .sidebar {
                display: none;
            }
            .content {
                margin-left: 0;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <div class="card">
                <div class="card-body">
                    <h2 class="text-center"><?php echo $translations[$lang]['transfer_ownership_title']; ?></h2>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($land): ?>
                        <div class="section">
                            <h3><?php echo $translations[$lang]['current_landowner_details']; ?></h3>
                            <p><strong><?php echo $translations[$lang]['land_id']; ?>:</strong> <?php echo htmlspecialchars($land['id']); ?></p>
                            <p><strong><?php echo $translations[$lang]['owner_name']; ?>:</strong> <?php echo htmlspecialchars($land['owner_name'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['first_name']; ?>:</strong> <?php echo htmlspecialchars($land['first_name'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['middle_name']; ?>:</strong> <?php echo htmlspecialchars($land['middle_name'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['gender']; ?>:</strong> <?php echo htmlspecialchars($land['gender'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['phone_number']; ?>:</strong> <?php echo htmlspecialchars($land['owner_phone'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['id_front']; ?>:</strong> 
                                <?php 
                                if ($land['id_front'] && file_exists(__DIR__ . '/../../' . $land['id_front'])) {
                                    echo '<img src="' . BASE_URL . '/' . htmlspecialchars($land['id_front']) . '" alt="ID Front" class="image-preview">';
                                } else {
                                    echo htmlspecialchars($land['id_front'] ?? 'N/A');
                                }
                                ?>
                            </p>
                            <p><strong><?php echo $translations[$lang]['id_back']; ?>:</strong> 
                                <?php 
                                if ($land['id_back'] && file_exists(__DIR__ . '/../../' . $land['id_back'])) {
                                    echo '<img src="' . BASE_URL . '/' . htmlspecialchars($land['id_back']) . '" alt="ID Back" class="image-preview">';
                                } else {
                                    echo htmlspecialchars($land['id_back'] ?? 'N/A');
                                }
                                ?>
                            </p>
                            <p><strong><?php echo $translations[$lang]['owner_photo']; ?>:</strong> 
                                <?php 
                                if ($land['owner_photo'] && file_exists(__DIR__ . '/../../' . $land['owner_photo'])) {
                                    echo '<img src="' . BASE_URL . '/' . htmlspecialchars($land['owner_photo']) . '" alt="Owner Photo" class="image-preview">';
                                } else {
                                    echo htmlspecialchars($land['owner_photo'] ?? 'N/A');
                                }
                                ?>
                            </p>
                            <p><strong><?php echo $translations[$lang]['zone']; ?>:</strong> <?php echo htmlspecialchars($land['zone'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['village']; ?>:</strong> <?php echo htmlspecialchars($land['village'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo $translations[$lang]['block_number']; ?>:</strong> <?php echo htmlspecialchars($land['block_number'] ?? 'N/A'); ?></p>
                        </div>

                        <div class="section">
                            <h3><?php echo $translations[$lang]['update_landowner_details']; ?></h3>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="owner_name" class="form-label"><?php echo $translations[$lang]['owner_name']; ?></label>
                                    <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($land['owner_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="first_name" class="form-label"><?php echo $translations[$lang]['first_name']; ?></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($land['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="middle_name" class="form-label"><?php echo $translations[$lang]['middle_name']; ?></label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($land['middle_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="gender" class="form-label"><?php echo $translations[$lang]['gender']; ?></label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="Dhiira" <?php echo ($land['gender'] === 'Dhiira') ? 'selected' : ''; ?>><?php echo $translations[$lang]['male']; ?></option>
                                        <option value="Dubartii" <?php echo ($land['gender'] === 'Dubartii') ? 'selected' : ''; ?>><?php echo $translations[$lang]['female']; ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="owner_phone" class="form-label"><?php echo $translations[$lang]['phone_number']; ?></label>
                                    <input type="text" class="form-control" id="owner_phone" name="owner_phone" value="<?php echo htmlspecialchars($land['owner_phone'] ?? ''); ?>" placeholder="+251123456789">
                                </div>
                                <div class="mb-3">
                                    <label for="id_front" class="form-label"><?php echo $translations[$lang]['id_front']; ?></label>
                                    <input type="file" class="form-control" id="id_front" name="id_front" accept="image/jpeg,image/png">
                                </div>
                                <div class="mb-3">
                                    <label for="id_back" class="form-label"><?php echo $translations[$lang]['id_back']; ?></label>
                                    <input type="file" class="form-control" id="id_back" name="id_back" accept="image/jpeg,image/png">
                                </div>
                                <div class="mb-3">
                                    <label for="owner_photo" class="form-label"><?php echo $translations[$lang]['owner_photo']; ?></label>
                                    <input type="file" class="form-control" id="owner_photo" name="owner_photo" accept="image/jpeg,image/png">
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="<?php echo BASE_URL; ?>/modules/manager/view_case.php?id=<?php echo htmlspecialchars($_GET['case_id'] ?? 0); ?>&lang=<?php echo $lang; ?>" 
                                       class="btn btn-secondary"><?php echo $translations[$lang]['back_to_case']; ?></a>
                                    <button type="submit" class="btn btn-primary"><?php echo $translations[$lang]['update_details']; ?></button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted"><?php echo $translations[$lang]['no_land_record']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>