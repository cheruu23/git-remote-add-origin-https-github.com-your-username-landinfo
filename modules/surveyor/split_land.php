<?php
ob_start(); // Start output buffering
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

$land_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$case_id = filter_input(INPUT_GET, 'case_id', FILTER_VALIDATE_INT);
$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING) ?: 'en';
$debug_log = __DIR__ . '/debug.log';

if (!$land_id || !isset($_SESSION['user_id'])) {
    ob_end_clean();
    header("Location: ../login.php?lang=$lang");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = "SELECT * FROM land_registration WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['id' => $land_id]);
    $land = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$land) {
        $error = $translations[$lang]['land_not_found'] ?? "Land not found.";
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Land ID $land_id not found\n", FILE_APPEND);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = $translations[$lang]['db_error'] ?? "Database error occurred.";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Database error for land ID $land_id: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Initialize form variables with defaults
$total_area = $land ? floatval($land['has_parcel'] ? ($land['parcel_land_area'] ?? $land['area']) : $land['area']) : 0;
$former_area = '';
$new_area = '';
$new_owner_name = '';
$new_first_name = '';
$new_middle_name = '';
$new_gender = '';
$new_owner_phone = '';
$new_owner_photo = $land['owner_photo'] ?? '';
$former_land_type = $land['land_type'] ?? '';
$former_block_number = $land['block_number'] ?? '';
$former_parcel_number = $land['parcel_number'] ?? '';
$former_effective_date = $land['effective_date'] ?? '';
$former_land_grade = $land['land_grade'] ?? '';
$former_land_service = $land['land_service'] ?? '';
$former_neighbor_east = $land['neighbor_east'] ?? '';
$former_neighbor_west = $land['neighbor_west'] ?? '';
$former_neighbor_south = $land['neighbor_south'] ?? '';
$former_neighbor_north = $land['neighbor_north'] ?? '';
$former_coordinates = $land['coordinates'] ?? '';
$former_coord1_x = $land['coord1_x'] ?? '';
$former_coord1_y = $land['coord1_y'] ?? '';
$former_coord2_x = $land['coord2_x'] ?? '';
$former_coord2_y = $land['coord2_y'] ?? '';
$former_coord3_x = $land['coord3_x'] ?? '';
$former_coord3_y = $land['coord3_y'] ?? '';
$former_coord4_x = $land['coord4_x'] ?? '';
$former_coord4_y = $land['coord4_y'] ?? '';
$former_approved_by_name = $land['approved_by_name'] ?? '';
$former_approved_by_role = $land['approved_by_role'] ?? '';
$former_authorized_by_name = $land['authorized_by_name'] ?? '';
$former_authorized_by_role = $land['authorized_by_role'] ?? '';
$new_land_type = $land['land_type'] ?? '';
$new_block_number = $land['block_number'] ?? '';
$new_parcel_number = $land['parcel_number'] ?? '';
$new_effective_date = $land['effective_date'] ?? '';
$new_land_grade = $land['land_grade'] ?? '';
$new_land_service = $land['land_service'] ?? '';
$new_neighbor_east = $land['neighbor_east'] ?? '';
$new_neighbor_west = $land['neighbor_west'] ?? '';
$new_neighbor_south = $land['neighbor_south'] ?? '';
$new_neighbor_north = $land['neighbor_north'] ?? '';
$new_coordinates = $land['coordinates'] ?? '';
$new_coord1_x = $land['coord1_x'] ?? '';
$new_coord1_y = $land['coord1_y'] ?? '';
$new_coord2_x = $land['coord2_x'] ?? '';
$new_coord2_y = $land['coord2_y'] ?? '';
$new_coord3_x = $land['coord3_x'] ?? '';
$new_coord3_y = $land['coord3_y'] ?? '';
$new_coord4_x = $land['coord4_x'] ?? '';
$new_coord4_y = $land['coord4_y'] ?? '';
$new_approved_by_name = $land['approved_by_name'] ?? '';
$new_approved_by_role = $land['approved_by_role'] ?? '';
$new_authorized_by_name = $land['authorized_by_name'] ?? '';
$new_authorized_by_role = $land['authorized_by_role'] ?? '';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_owner_name = trim($_POST['new_owner_name'] ?? '');
    $new_first_name = trim($_POST['new_first_name'] ?? '');
    $new_middle_name = trim($_POST['new_middle_name'] ?? '');
    $new_gender = trim($_POST['new_gender'] ?? '');
    $new_owner_phone = trim($_POST['new_owner_phone'] ?? '');
    $former_area = floatval($_POST['former_area'] ?? 0);
    $new_area = floatval($_POST['new_area'] ?? 0);

    if (empty($new_owner_name)) {
        $errors[] = $translations[$lang]['owner_name_required'] ?? "New owner name is required.";
    }
    if (empty($new_gender)) {
        $errors[] = $translations[$lang]['gender_required'] ?? "New owner gender is required.";
    }
    if ($former_area <= 0 || $new_area <= 0) {
        $errors[] = $translations[$lang]['areas_positive'] ?? "Both areas must be greater than zero.";
    }
    if (abs(($former_area + $new_area) - $total_area) > 0.01) {
        $errors[] = sprintf($translations[$lang]['areas_sum_error'] ?? "Sum of areas must equal original area (%s m²).", $total_area);
    }

    if (isset($_FILES['new_owner_photo']) && $_FILES['new_owner_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../Uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = 'owner_' . time() . '_' . basename($_FILES['new_owner_photo']['name']);
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['new_owner_photo']['tmp_name'], $file_path)) {
            $new_owner_photo = 'Uploads/' . $file_name;
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - New owner photo uploaded: $new_owner_photo\n", FILE_APPEND);
        } else {
            $errors[] = $translations[$lang]['photo_upload_failed'] ?? "Failed to upload new owner photo.";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: Failed to upload new owner photo\n", FILE_APPEND);
        }
    }

    $former_land_type = trim($_POST['former_land_type'] ?? $land['land_type'] ?? '');
    $former_block_number = trim($_POST['former_block_number'] ?? $land['block_number'] ?? '');
    $former_parcel_number = trim($_POST['former_parcel_number'] ?? $land['parcel_number'] ?? '');
    $former_effective_date = trim($_POST['former_effective_date'] ?? $land['effective_date'] ?? '');
    $former_land_grade = trim($_POST['former_land_grade'] ?? $land['land_grade'] ?? '');
    $former_land_service = trim($_POST['former_land_service'] ?? $land['land_service'] ?? '');
    $former_neighbor_east = trim($_POST['former_neighbor_east'] ?? $land['neighbor_east'] ?? '');
    $former_neighbor_west = trim($_POST['former_neighbor_west'] ?? $land['neighbor_west'] ?? '');
    $former_neighbor_south = trim($_POST['former_neighbor_south'] ?? $land['neighbor_south'] ?? '');
    $former_neighbor_north = trim($_POST['former_neighbor_north'] ?? $land['neighbor_north'] ?? '');
    $former_coordinates = trim($_POST['former_coordinates'] ?? $land['coordinates'] ?? '');
    $former_coord1_x = floatval($_POST['former_coord1_x'] ?? $land['coord1_x'] ?? 0);
    $former_coord1_y = floatval($_POST['former_coord1_y'] ?? $land['coord1_y'] ?? 0);
    $former_coord2_x = floatval($_POST['former_coord2_x'] ?? $land['coord2_x'] ?? 0);
    $former_coord2_y = floatval($_POST['former_coord2_y'] ?? $land['coord2_y'] ?? 0);
    $former_coord3_x = floatval($_POST['former_coord3_x'] ?? $land['coord3_x'] ?? 0);
    $former_coord3_y = floatval($_POST['former_coord3_y'] ?? $land['coord3_y'] ?? 0);
    $former_coord4_x = floatval($_POST['former_coord4_x'] ?? $land['coord4_x'] ?? 0);
    $former_coord4_y = floatval($_POST['former_coord4_y'] ?? $land['coord4_y'] ?? 0);
    $former_approved_by_name = trim($_POST['former_approved_by_name'] ?? $land['approved_by_name'] ?? '');
    $former_approved_by_role = trim($_POST['former_approved_by_role'] ?? $land['approved_by_role'] ?? '');
    $former_authorized_by_name = trim($_POST['former_authorized_by_name'] ?? $land['authorized_by_name'] ?? '');
    $former_authorized_by_role = trim($_POST['former_authorized_by_role'] ?? $land['authorized_by_role'] ?? '');

    $new_land_type = trim($_POST['new_land_type'] ?? $land['land_type'] ?? '');
    $new_block_number = trim($_POST['new_block_number'] ?? $land['block_number'] ?? '');
    $new_parcel_number = trim($_POST['new_parcel_number'] ?? $land['parcel_number'] ?? '');
    $new_effective_date = trim($_POST['new_effective_date'] ?? $land['effective_date'] ?? '');
    $new_land_grade = trim($_POST['new_land_grade'] ?? $land['land_grade'] ?? '');
    $new_land_service = trim($_POST['new_land_service'] ?? $land['land_service'] ?? '');
    $new_neighbor_east = trim($_POST['new_neighbor_east'] ?? $land['neighbor_east'] ?? '');
    $new_neighbor_west = trim($_POST['new_neighbor_west'] ?? $land['neighbor_west'] ?? '');
    $new_neighbor_south = trim($_POST['new_neighbor_south'] ?? $land['neighbor_south'] ?? '');
    $new_neighbor_north = trim($_POST['new_neighbor_north'] ?? $land['neighbor_north'] ?? '');
    $new_coordinates = trim($_POST['new_coordinates'] ?? $land['coordinates'] ?? '');
    $new_coord1_x = floatval($_POST['new_coord1_x'] ?? $land['coord1_x'] ?? 0);
    $new_coord1_y = floatval($_POST['new_coord1_y'] ?? $land['coord1_y'] ?? 0);
    $new_coord2_x = floatval($_POST['new_coord2_x'] ?? $land['coord2_x'] ?? 0);
    $new_coord2_y = floatval($_POST['new_coord2_y'] ?? $land['coord2_y'] ?? 0);
    $new_coord3_x = floatval($_POST['new_coord3_x'] ?? $land['coord3_x'] ?? 0);
    $new_coord3_y = floatval($_POST['new_coord3_y'] ?? $land['coord3_y'] ?? 0);
    $new_coord4_x = floatval($_POST['new_coord4_x'] ?? $land['coord4_x'] ?? 0);
    $new_coord4_y = floatval($_POST['new_coord4_y'] ?? $land['coord4_y'] ?? 0);
    $new_approved_by_name = trim($_POST['new_approved_by_name'] ?? $land['approved_by_name'] ?? '');
    $new_approved_by_role = trim($_POST['new_approved_by_role'] ?? $land['approved_by_role'] ?? '');
    $new_authorized_by_name = trim($_POST['new_authorized_by_name'] ?? $land['authorized_by_name'] ?? '');
    $new_authorized_by_role = trim($_POST['new_authorized_by_role'] ?? $land['authorized_by_role'] ?? '');

    if (!empty($former_coordinates) && !preg_match('/^(\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*)$/', $former_coordinates)) {
        $errors[] = $translations[$lang]['coordinates_format_error'] ?? "Former land coordinates must be in format 'x1,y1;x2,y2;x3,y3;x4,y4'.";
    }
    if (!empty($new_coordinates) && !preg_match('/^(\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*;\d+\.?\d*,\d+\.?\d*)$/', $new_coordinates)) {
        $errors[] = $translations[$lang]['coordinates_format_error_new'] ?? "New land coordinates must be in format 'x1,y1;x2,y2;x3,y3;x4,y4'.";
    }
    $former_coords_provided = $former_coord1_x && $former_coord1_y && $former_coord2_x && $former_coord2_y && 
                             $former_coord3_x && $former_coord3_y && $former_coord4_x && $former_coord4_y;
    $new_coords_provided = $new_coord1_x && $new_coord1_y && $new_coord2_x && $new_coord2_y && 
                           $new_coord3_x && $new_coord3_y && $new_coord4_x && $new_coord4_y;
    if ($former_coords_provided) {
        $former_coordinates = "$former_coord1_x,$former_coord1_y;$former_coord2_x,$former_coord2_y;$former_coord3_x,$former_coord3_y;$former_coord4_x,$former_coord4_y";
    }
    if ($new_coords_provided) {
        $new_coordinates = "$new_coord1_x,$new_coord1_y;$new_coord2_x,$new_coord2_y;$new_coord3_x,$new_coord3_y;$new_coord4_x,$new_coord4_y";
    }

    if (!empty($former_effective_date) && !DateTime::createFromFormat('Y-m-d', $former_effective_date)) {
        $errors[] = $translations[$lang]['date_format_error'] ?? "Former land effective date must be in YYYY-MM-DD format.";
    }
    if (!empty($new_effective_date) && !DateTime::createFromFormat('Y-m-d', $new_effective_date)) {
        $errors[] = $translations[$lang]['date_format_error_new'] ?? "New land effective date must be in YYYY-MM-DD format.";
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE 'split_requests'");
            if ($stmt->rowCount() === 0) {
                $errors[] = $translations[$lang]['table_missing'] ?? "Error: split_requests table does not exist in the database.";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Error: split_requests table not found in database " . DB_NAME . "\n", FILE_APPEND);
            }

            $stmt = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
            $has_type_column = $stmt->rowCount() > 0;

            if (empty($errors)) {
                $conn->beginTransaction();

                $former_block_number = $former_block_number ?: ($land['has_parcel'] ? ($land['block_number'] ?? '') : $land['block_number']) . '-A';
                $former_parcel_number = $former_parcel_number ?: ($land['has_parcel'] ? ($land['parcel_number'] ?? '') : $land['block_number']) . '-A';
                $new_block_number = $new_block_number ?: ($land['has_parcel'] ? ($land['block_number'] ?? '') : $land['block_number']) . '-B';
                $new_parcel_number = $new_parcel_number ?: ($land['has_parcel'] ? ($land['parcel_number'] ?? '') : $land['block_number']) . '-B';

                $former_data = [
                    'owner_name' => $land['owner_name'],
                    'first_name' => $land['first_name'],
                    'middle_name' => $land['middle_name'],
                    'gender' => $land['gender'],
                    'owner_phone' => $land['owner_phone'],
                    'owner_photo' => $land['owner_photo'],
                    'area' => $former_area,
                    'land_type' => $former_land_type,
                    'block_number' => $former_block_number,
                    'parcel_number' => $former_parcel_number,
                    'effective_date' => $former_effective_date,
                    'land_grade' => $former_land_grade,
                    'land_service' => $former_land_service,
                    'neighbor_east' => $former_neighbor_east,
                    'neighbor_west' => $former_neighbor_west,
                    'neighbor_south' => $former_neighbor_south,
                    'neighbor_north' => $former_neighbor_north,
                    'coordinates' => $former_coordinates,
                    'coord1_x' => $former_coord1_x,
                    'coord1_y' => $former_coord1_y,
                    'coord2_x' => $former_coord2_x,
                    'coord2_y' => $former_coord2_y,
                    'coord3_x' => $former_coord3_x,
                    'coord3_y' => $former_coord3_y,
                    'coord4_x' => $former_coord4_x,
                    'coord4_y' => $former_coord4_y,
                    'approved_by_name' => $former_approved_by_name,
                    'approved_by_role' => $former_approved_by_role,
                    'authorized_by_name' => $former_authorized_by_name,
                    'authorized_by_role' => $former_authorized_by_role,
                    'has_parcel' => $land['has_parcel'],
                    'parcel_land_area' => $land['has_parcel'] ? $former_area : null,
                    'parcel_block_number' => $land['has_parcel'] ? $former_block_number : null,
                    'parcel_number' => $land['has_parcel'] ? $former_parcel_number : null,
                    'parcel_registration_number' => $land['has_parcel'] ? $former_parcel_number : null,
                    'parcel_land_grade' => $land['has_parcel'] ? $former_land_grade : null,
                    'parcel_land_service' => $land['has_parcel'] ? $former_land_service : null,
                    'status' => 'Pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $new_data = [
                    'owner_name' => $new_owner_name,
                    'first_name' => $new_first_name,
                    'middle_name' => $new_middle_name,
                    'gender' => $new_gender,
                    'owner_phone' => $new_owner_phone,
                    'owner_photo' => $new_owner_photo,
                    'area' => $new_area,
                    'land_type' => $new_land_type,
                    'block_number' => $new_block_number,
                    'parcel_number' => $new_parcel_number,
                    'effective_date' => $new_effective_date,
                    'land_grade' => $new_land_grade,
                    'land_service' => $new_land_service,
                    'neighbor_east' => $new_neighbor_east,
                    'neighbor_west' => $new_neighbor_west,
                    'neighbor_south' => $new_neighbor_south,
                    'neighbor_north' => $new_neighbor_north,
                    'coordinates' => $new_coordinates,
                    'coord1_x' => $new_coord1_x,
                    'coord1_y' => $new_coord1_y,
                    'coord2_x' => $new_coord2_x,
                    'coord2_y' => $new_coord2_y,
                    'coord3_x' => $new_coord3_x,
                    'coord3_y' => $new_coord3_y,
                    'coord4_x' => $new_coord4_x,
                    'coord4_y' => $new_coord4_y,
                    'approved_by_name' => $new_approved_by_name,
                    'approved_by_role' => $new_approved_by_role,
                    'authorized_by_name' => $new_authorized_by_name,
                    'authorized_by_role' => $new_authorized_by_role,
                    'has_parcel' => $land['has_parcel'],
                    'parcel_land_area' => $land['has_parcel'] ? $new_area : null,
                    'parcel_block_number' => $land['has_parcel'] ? $new_block_number : null,
                    'parcel_number' => $land['has_parcel'] ? $new_parcel_number : null,
                    'parcel_registration_number' => $land['has_parcel'] ? $new_parcel_number : null,
                    'parcel_land_grade' => $land['has_parcel'] ? $new_land_grade : null,
                    'parcel_land_service' => $land['has_parcel'] ? $new_land_service : null,
                    'status' => 'Pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $sql = "SELECT id FROM users WHERE role = 'manager' AND is_locked = 0 LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $manager = $stmt->fetch();
                if (!$manager) {
                    $errors[] = $translations[$lang]['no_manager_found'] ?? "No manager found to assign the request.";
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - No manager found for split request\n", FILE_APPEND);
                }

                if (empty($errors)) {
                    $sql = "INSERT INTO split_requests (original_land_id, case_id, surveyor_id, manager_id, former_data, new_data, status) 
                            VALUES (:original_land_id, :case_id, :surveyor_id, :manager_id, :former_data, :new_data, 'Pending')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':original_land_id', $land_id, PDO::PARAM_INT);
                    $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
                    $stmt->bindParam(':surveyor_id', $user_id, PDO::PARAM_INT);
                    $stmt->bindParam(':manager_id', $manager['id'], PDO::PARAM_INT);
                    $stmt->bindValue(':former_data', json_encode($former_data));
                    $stmt->bindValue(':new_data', json_encode($new_data));
                    $stmt->execute();
                    $request_id = $conn->lastInsertId();

                    if ($case_id) {
                        $sql = "UPDATE cases SET status = 'Pending', assigned_to = :manager_id WHERE id = :case_id AND assigned_to = :surveyor_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':manager_id', $manager['id'], PDO::PARAM_INT);
                        $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
                        $stmt->bindParam(':surveyor_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case ID $case_id updated to Pending for manager {$manager['id']}\n", FILE_APPEND);
                    }

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

                    $message = $translations[$lang]['notification_split'] ?? "New split request (ID: $request_id) for land ID $land_id awaits your approval.";
                    if ($has_type_column) {
                        $sql = "INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (:user_id, :message, 'Info', 0, NOW())";
                    } else {
                        $sql = "INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())";
                    }
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':user_id', $manager['id'], PDO::PARAM_INT);
                    $stmt->bindValue(':message', $message);
                    $stmt->execute();
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Notification sent to manager ID {$manager['id']} for split request $request_id\n", FILE_APPEND);

                    $conn->commit();

                    $translation = $translations[$lang]['land_split_success'] ?? "Split request sent to manager for approval. Request ID: %s.";
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Translation for land_split_success: '$translation'\n", FILE_APPEND);
                    $placeholder_count = substr_count($translation, '%s');
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Placeholder count: $placeholder_count\n", FILE_APPEND);
                    try {
                        if ($placeholder_count >= 3) {
                            $success = sprintf($translation, $request_id, $land_id, $manager['id']);
                        } else {
                            $success = sprintf($translation, $request_id);
                        }
                    } catch (ArgumentCountError $e) {
                        $success = "Split request sent to manager for approval. Request ID: $request_id.";
                        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - ArgumentCountError in sprintf: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Split request ID $request_id created for land ID $land_id\n", FILE_APPEND);
                    ob_end_clean();
                    header("Location: pending_requests.php?success=" . urlencode($success) . "&lang=$lang");
                    exit;
                }
                $conn->rollBack();
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Split request failed: " . $e->getMessage());
            $errors[] = $translations[$lang]['split_request_error'] ?? "Error creating split request: " . $e->getMessage();
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Split request failed for land ID $land_id: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['split_land'] ?? 'Split Land'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/public/css/sidebar.css" rel="stylesheet">
</head>
<body>
    <div class="main-content" id="main-content">
        <div class="container-fluid">
            <h1 class="h3 mb-4"><?php echo $translations[$lang]['split_land'] ?? 'Split Land'; ?></h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($land): ?>
                <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
                    <h2 class="h5 mb-4"><?php echo $translations[$lang]['split_details'] ?? 'Split Details'; ?></h2>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['original_area'] ?? 'Original Area'; ?> (m²):</label>
                        <input type="text" value="<?php echo htmlspecialchars($total_area); ?>" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['former_area'] ?? 'Former Land Area (m²)'; ?>:</label>
                        <input type="number" step="0.01" name="former_area" value="<?php echo htmlspecialchars($former_area); ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['new_area'] ?? 'New Land Area (m²)'; ?>:</label>
                        <input type="number" step="0.01" name="new_area" value="<?php echo htmlspecialchars($new_area); ?>" class="form-control" required>
                    </div>

                    <h3 class="h6 mb-3"><?php echo $translations[$lang]['new_owner_details'] ?? 'New Owner Details'; ?></h3>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?>:</label>
                        <input type="text" name="new_owner_name" value="<?php echo htmlspecialchars($new_owner_name); ?>" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?>:</label>
                        <input type="text" name="new_first_name" value="<?php echo htmlspecialchars($new_first_name); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?>:</label>
                        <input type="text" name="new_middle_name" value="<?php echo htmlspecialchars($new_middle_name); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?>:</label>
                        <select name="new_gender" class="form-select" required>
                            <option value=""><?php echo $translations[$lang]['select_gender'] ?? 'Select Gender'; ?></option>
                            <option value="Male" <?php echo ($new_gender === 'Male') ? 'selected' : ''; ?>><?php echo $translations[$lang]['male'] ?? 'Male'; ?></option>
                            <option value="Female" <?php echo ($new_gender === 'Female') ? 'selected' : ''; ?>><?php echo $translations[$lang]['female'] ?? 'Female'; ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['owner_phone'] ?? 'Owner Phone'; ?>:</label>
                        <input type="text" name="new_owner_phone" value="<?php echo htmlspecialchars($new_owner_phone); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['owner_photo'] ?? 'Owner Photo'; ?>:</label>
                        <input type="file" name="new_owner_photo" class="form-control">
                    </div>

                    <h3 class="h6 mb-3"><?php echo $translations[$lang]['former_land_details'] ?? 'Former Land Details'; ?></h3>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?>:</label>
                        <input type="text" name="former_land_type" value="<?php echo htmlspecialchars($former_land_type); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</label>
                        <input type="text" name="former_block_number" value="<?php echo htmlspecialchars($former_block_number); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?>:</label>
                        <input type="text" name="former_parcel_number" value="<?php echo htmlspecialchars($former_parcel_number); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?>:</label>
                        <input type="date" name="former_effective_date" value="<?php echo htmlspecialchars($former_effective_date); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['coordinates'] ?? 'Coordinates'; ?>:</label>
                        <input type="text" name="former_coordinates" value="<?php echo htmlspecialchars($former_coordinates); ?>" class="form-control" placeholder="x1,y1;x2,y2;x3,y3;x4,y4">
                    </div>

                    <h3 class="h6 mb-3"><?php echo $translations[$lang]['new_land_details'] ?? 'New Land Details'; ?></h3>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?>:</label>
                        <input type="text" name="new_land_type" value="<?php echo htmlspecialchars($new_land_type); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</label>
                        <input type="text" name="new_block_number" value="<?php echo htmlspecialchars($new_block_number); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?>:</label>
                        <input type="text" name="new_parcel_number" value="<?php echo htmlspecialchars($new_parcel_number); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?>:</label>
                        <input type="date" name="new_effective_date" value="<?php echo htmlspecialchars($new_effective_date); ?>" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $translations[$lang]['coordinates'] ?? 'Coordinates'; ?>:</label>
                        <input type="text" name="new_coordinates" value="<?php echo htmlspecialchars($new_coordinates); ?>" class="form-control" placeholder="x1,y1;x2,y2;x3,y3;x4,y4">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?php echo $translations[$lang]['submit'] ?? 'Submit'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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
            const badge = document.getElementById('notification-badge');
            const markAllReadBtn = document.getElementById('mark-all-read');

            console.log('Initial unread count:', <?php echo $unread_count; ?>);

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

            function fetchNotifications(retryCount = 0) {
                const maxRetries = 3;
                const content = document.getElementById('notification-content');
                content.innerHTML = '<div class="notification-loading"><?php echo $translations[$lang]['loading'] ?? 'Loading...'; ?></div>';

                fetch('<?php echo BASE_URL; ?>/includes/fetch_notification.php?lang=<?php echo htmlspecialchars($lang); ?>', {
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text().then(text => {
                            console.log('Raw response:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error(`Invalid JSON: ${e.message}`);
                            }
                        });
                    })
                    .then(data => {
                        console.log('Parsed response:', data);
                        if (!data.success) {
                            throw new Error(data.message || 'Invalid response from server');
                        }
                        badge.style.display = data.unread_count > 0 ? 'flex' : 'none';
                        badge.textContent = data.unread_count > 0 ? data.unread_count : '';
                        console.log('Updating badge:', data.unread_count);
                        markAllReadBtn.disabled = data.unread_count === 0;

                        content.innerHTML = '';
                        if (!data.notifications.length) {
                            content.innerHTML = '<div class="notification-item text-center text-muted"><?php echo $translations[$lang]['no_notifications'] ?? 'No notifications'; ?></div>';
                        } else {
                            data.notifications.forEach(notif => {
                                const isRead = notif.is_read || 0;
                                const link = notif.case_id
                                    ? `<?php echo BASE_URL; ?>/modules/<?php echo $role; ?>/managercase_view.php?case_id=${notif.case_id}&mark_read=${notif.id}&lang=<?php echo htmlspecialchars($lang); ?>`
                                    : `<?php echo BASE_URL; ?>/modules/profile.php?lang=<?php echo htmlspecialchars($lang); ?>`;
                                content.innerHTML += `
                                    <div class="notification-item ${isRead ? '' : 'unread'}">
                                        <a href="${link}">
                                            <span class="notification-message">${notif.message}</span>
                                            <span class="notification-time">${new Date(notif.created_at).toLocaleString('<?php echo $lang === 'om' ? 'om-ET' : 'en-US'; ?>', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</span>
                                        </a>
                                    </div>
                                `;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch notifications error:', error.message);
                        if (retryCount < maxRetries) {
                            setTimeout(() => fetchNotifications(retryCount + 1), 1000 * (retryCount + 1));
                        } else {
                            badge.style.display = <?php echo $unread_count > 0 ? "'flex'" : "'none'"; ?>;
                            badge.textContent = <?php echo $unread_count > 0 ? $unread_count : "''"; ?>;
                            console.log('Falling back to initial unread count:', <?php echo $unread_count; ?>);
                            content.innerHTML = `
                                <div class="notification-error">
                                    <?php echo $translations[$lang]['error_notifications'] ?? 'Failed to load notifications'; ?>
                                    <br><small>${error.message}</small>
                                </div>
                            `;
                        }
                    });
            }

            markAllReadBtn.addEventListener('click', () => {
                fetch('<?php echo BASE_URL; ?>/includes/fetch_notification.php?mark_all_read=1&lang=<?php echo htmlspecialchars($lang); ?>', {
                    method: 'POST',
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text().then(text => {
                            console.log('Mark all read raw response:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error(`Invalid JSON: ${e.message}`);
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            fetchNotifications();
                        } else {
                            console.error('Mark all read failed:', data.message);
                            content.innerHTML = `
                                <div class="notification-error">
                                    <?php echo $translations[$lang]['error_notifications'] ?? 'Failed to load notifications'; ?>
                                    <br><small>${data.message || 'Failed to mark all as read'}</small>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Mark all read error:', error);
                        content.innerHTML = `
                            <div class="notification-error">
                                <?php echo $translations[$lang]['error_notifications'] ?? 'Failed to load notifications'; ?>
                                <br><small>${error.message}</small>
                            </div>
                        `;
                    });
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>