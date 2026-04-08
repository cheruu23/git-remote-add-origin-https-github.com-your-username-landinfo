<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
redirectIfNotLoggedIn();
if (!function_exists('isRecordOfficer') || !isRecordOfficer()) {
    die("Access denied!");
}

$conn = getDBConnection();

// Function to handle file uploads
function uploadFile($file, $uploadDir = 'Uploads/') {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Invalid file type for {$file['name']}: {$file['type']}");
        return null;
    }
    $fileName = basename($file['name']);
    $filePath = $uploadDir . uniqid() . '_' . $fileName;
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    }
    error_log("Failed to move uploaded file: {$file['name']}");
    return null;
}

// Fetch table columns dynamically
$columnsResult = $conn->query("SHOW COLUMNS FROM land_registration");
if (!$columnsResult) {
    error_log("Failed to fetch columns: (" . $conn->errno . ") " . $conn->error);
    $_SESSION['error_message'] = "Error fetching table schema: " . htmlspecialchars($conn->error);
    header("Location: registration.php");
    exit;
}
$columns = [];
while ($row = $columnsResult->fetch_assoc()) {
    $columns[] = $row['Field'];
}
$columnsResult->free();

// Remove 'id' (auto-incremented)
$columns = array_diff($columns, ['id']);
$columnCount = count($columns);
error_log("Table columns: " . implode(', ', $columns));
error_log("Column count: $columnCount");

// Verify has_parcel column exists
if (!in_array('has_parcel', $columns)) {
    error_log("Column 'has_parcel' not found in land_registration table");
    $_SESSION['error_message'] = "Database schema error: 'has_parcel' column missing.";
    header("Location: registration.php");
    exit;
}

// Expected fields from form
$expectedFields = [
    'owner_name' => ['post', 'required'],
    'first_name' => ['post', 'required'],
    'middle_name' => ['post'],
    'gender' => ['post', 'required'],
    'owner_phone' => ['post'],
    'land_type' => ['post', 'required'],
    'village' => ['post', 'required'],
    'zone' => ['post', 'required'],
    'block_number' => ['post', 'required'],
    'parcel_number' => ['post'],
    'effective_date' => ['post', 'required'],
    'group_category' => ['post'],
    'land_grade' => ['default', null],
    'land_service' => ['default', null],
    'neighbor_east' => ['post'],
    'neighbor_west' => ['post'],
    'neighbor_south' => ['post'],
    'neighbor_north' => ['post'],
    'id_front' => ['file', 'required'],
    'id_back' => ['file', 'required'],
    'xalayaa_miritii' => ['file'],
    'nagaee_gibiraa' => ['file'],
    'waligaltee_lease' => ['file'],
    'tax_receipt' => ['file'],
    'miriti_paper' => ['file'],
    'caalbaasii_agreement' => ['file'],
    'bita_fi_gurgurtaa_agreement' => ['file'],
    'bita_fi_gurgurtaa_receipt' => ['file'],
    'owner_photo' => ['file', 'required'],
    'registration_date' => ['now'],
    'created_at' => ['now'],
    'agreement_number' => ['default', null],
    'duration' => ['default', null],
    'area' => ['default', null],
    'purpose' => ['post'],
    'plot_number' => ['post'],
    'coordinates' => ['computed'],
    'surveyor_name' => ['default', null],
    'head_surveyor_name' => ['default', null],
    'land_officer_name' => ['default', null],
    'has_parcel' => ['post', 'checkbox'],
    'parcel_lease_date' => ['post'],
    'parcel_agreement_number' => ['post'],
    'parcel_lease_duration' => ['post'],
    'coord1_x' => ['post'],
    'coord1_y' => ['post'],
    'coord2_x' => ['post'],
    'coord2_y' => ['post'],
    'coord3_x' => ['post'],
    'coord3_y' => ['post'],
    'coord4_x' => ['post'],
    'coord4_y' => ['post'],
    'parcel_village' => ['post'],
    'parcel_block_number' => ['post'],
    'parcel_land_grade' => ['post'],
    'parcel_land_area' => ['post'],
    'parcel_land_service' => ['post'],
    'parcel_registration_number' => ['post'],
    'building_height_allowed' => ['post'],
    'prepared_by_name' => ['post'],
    'prepared_by_role' => ['post'],
    'approved_by_name' => ['post'],
    'approved_by_role' => ['post'],
    'authorized_by_name' => ['post'],
    'authorized_by_role' => ['post'],
    'status' => ['default', 'Pending'],
    'user_id' => ['session', 'required']
];

// Process form data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: registration.php");
    exit;
}

$errors = [];
$values = [];
$bindTypes = '';
$bindParams = [];

// Handle file uploads
$filePaths = [];
foreach (array_keys($expectedFields) as $field) {
    if ($expectedFields[$field][0] === 'file' && !empty($_FILES[$field]['name'])) {
        $filePaths[$field] = uploadFile($_FILES[$field]);
        if (!$filePaths[$field] && in_array('required', $expectedFields[$field])) {
            $errors[] = "Failed to upload required file: $field.";
        }
    } else if ($expectedFields[$field][0] === 'file') {
        $filePaths[$field] = null;
    }
}

// Handle has_parcel
$hasParcel = isset($_POST['has_parcel']) && $_POST['has_parcel'] === '1' ? 1 : 0;
error_log("has_parcel value: $hasParcel");

// Validate required fields
foreach ($expectedFields as $field => $config) {
    if (in_array('required', $config)) {
        if ($config[0] === 'post' && empty($_POST[$field])) {
            $errors[] = "Field '$field' is required.";
        } else if ($config[0] === 'file' && empty($_FILES[$field]['name'])) {
            $errors[] = "File '$field' is required.";
        } else if ($config[0] === 'session' && empty($_SESSION['user_id'])) {
            $errors[] = "User ID is required.";
        }
    }
}

// Validate enum fields
$validGenders = ['Dhiira', 'Dubartii'];
$validLandTypes = ['dhaala', 'lease_land', 'bita_fi_gurgurtaa', 'miritti', 'caalbaasii'];
$validLandServices = ['lafa daldalaa', 'lafa mana jireenyaa'];
if (!in_array(isset($_POST['gender']) ? $_POST['gender'] : '', $validGenders)) {
    $errors[] = "Invalid gender value.";
}
if (!in_array(isset($_POST['land_type']) ? $_POST['land_type'] : '', $validLandTypes)) {
    $errors[] = "Invalid land type value.";
}
if (!empty($_POST['parcel_land_service']) && !in_array($_POST['parcel_land_service'], $validLandServices)) {
    $errors[] = "Invalid land service value.";
}

// Validate parcel fields if has_parcel is checked
if ($hasParcel) {
    $parcelRequiredFields = [
        'parcel_lease_date', 'parcel_agreement_number', 'parcel_lease_duration',
        'parcel_village', 'parcel_block_number', 'parcel_number', 'parcel_land_grade',
        'parcel_land_area', 'parcel_land_service', 'parcel_registration_number',
        'building_height_allowed', 'coord1_x', 'coord1_y', 'coord2_x', 'coord2_y',
        'coord3_x', 'coord3_y', 'coord4_x', 'coord4_y'
    ];
    foreach ($parcelRequiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Parcel field '$field' is required.";
        }
    }
}

// Validate land-type-specific documents
$landType = isset($_POST['land_type']) ? $_POST['land_type'] : '';
$docFields = [
    'dhaala' => ['xalayaa_miritii', 'nagaee_gibiraa'],
    'lease_land' => ['waligaltee_lease', 'tax_receipt'],
    'miritti' => ['miriti_paper'],
    'caalbaasii' => ['caalbaasii_agreement'],
    'bita_fi_gurgurtaa' => ['bita_fi_gurgurtaa_agreement', 'bita_fi_gurgurtaa_receipt']
];
if (isset($docFields[$landType])) {
    foreach ($docFields[$landType] as $doc) {
        if (empty($_FILES[$doc]['name']) || !$filePaths[$doc]) {
            $errors[] = "Document '$doc' is required for land type '$landType'.";
        }
    }
}

// Handle coordinates
$coordinates = '';
if ($hasParcel) {
    $coords = [];
    for ($i = 1; $i <= 4; $i++) {
        $x = isset($_POST["coord{$i}_x"]) ? $_POST["coord{$i}_x"] : '';
        $y = isset($_POST["coord{$i}_y"]) ? $_POST["coord{$i}_y"] : '';
        if ($x !== '' && $y !== '') {
            $coords[] = "$x,$y";
        }
    }
    $coordinates = implode(';', $coords);
}

// Build values for existing columns
foreach ($columns as $column) {
    if (!isset($expectedFields[$column])) {
        $errors[] = "Column '$column' not handled by form.";
        continue;
    }
    $config = $expectedFields[$column];
    if ($config[0] === 'post') {
        $values[$column] = isset($_POST[$column]) ? $_POST[$column] : null;
        $bindTypes .= 's';
    } else if ($config[0] === 'file') {
        $values[$column] = isset($filePaths[$column]) ? $filePaths[$column] : null;
        $bindTypes .= 's';
    } else if ($config[0] === 'now') {
        $values[$column] = 'NOW()';
    } else if ($config[0] === 'computed') {
        $values[$column] = $coordinates;
        $bindTypes .= 's';
    } else if ($config[0] === 'default') {
        $values[$column] = $config[1];
        $bindTypes .= 's';
    } else if ($config[0] === 'checkbox') {
        $values[$column] = $hasParcel;
        $bindTypes .= 'i';
    } else if ($config[0] === 'session') {
        $values[$column] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $bindTypes .= 'i';
    }
    if ($values[$column] !== 'NOW()') {
        $bindParams[] = &$values[$column];
    }
}

if (!empty($errors)) {
    error_log("Validation errors: " . implode('; ', $errors));
    $_SESSION['error_message'] = implode('; ', $errors);
    header("Location: registration.php");
    exit;
}

// Build SQL query
$insertColumns = [];
$placeholders = [];
$bindValues = [];
$bindTypes = '';
$bindParams = [];

foreach ($columns as $column) {
    if (isset($values[$column])) {
        $insertColumns[] = $column;
        if ($values[$column] === 'NOW()') {
            $placeholders[] = 'NOW()';
        } else {
            $placeholders[] = '?';
            $bindValues[] = $values[$column];
            $bindTypes .= $expectedFields[$column][0] === 'checkbox' || $expectedFields[$column][0] === 'session' ? 'i' : 's';
            $bindParams[] = &$values[$column];
        }
    }
}

$sql = "INSERT INTO land_registration (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
error_log("Generated SQL: $sql");
error_log("Insert Columns Count: " . count($insertColumns));
error_log("Placeholders Count: " . count($placeholders));
error_log("Bind Values Count: " . count($bindValues));

// Prepare statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    $_SESSION['error_message'] = "Error preparing query: " . htmlspecialchars($conn->error);
    header("Location: registration.php");
    exit;
}

// Bind parameters
if (!empty($bindValues)) {
    $bindResult = call_user_func_array([$stmt, 'bind_param'], array_merge([$bindTypes], $bindParams));
    if (!$bindResult) {
        error_log("Bind failed: (" . $stmt->errno . ") " . $stmt->error);
        $_SESSION['error_message'] = "Error binding parameters: " . htmlspecialchars($stmt->error);
        header("Location: registration.php");
        exit;
    }
}

// Execute query
$conn->begin_transaction();
try {
    if ($stmt->execute()) {
        $landId = $conn->insert_id;
        error_log("Inserted land registration with ID: $landId, has_parcel: $hasParcel");

        // Create a case for ownership verification
        $caseSql = "INSERT INTO cases (title, status, description, land_id, assigned_to, investigation_status, created_at)
                    VALUES (?, 'Pending', ?, ?, ?, 'NotStarted', NOW())";
        $caseStmt = $conn->prepare($caseSql);
        if (!$caseStmt) {
            throw new Exception("Case prepare failed: " . $conn->error);
        }
        $caseTitle = 'mirkaneessa_abbaa_qabiyyumma';
        $caseDesc = json_encode(['notes' => 'New land registration']);
        $assignedTo = $_SESSION['user_id'];
        $caseStmt->bind_param('ssii', $caseTitle, $caseDesc, $landId, $assignedTo);
        if (!$caseStmt->execute()) {
            throw new Exception("Case insert failed: " . $caseStmt->error);
        }

        $conn->commit();
        $_SESSION['success_message'] = "Land details have been registered successfully!";
        header("Location: registration.php");
        exit;
    } else {
        throw new Exception("Failed to insert land registration: " . $stmt->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Submit form error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error submitting form: " . htmlspecialchars($e->getMessage());
    header("Location: registration.php");
    exit;
}

$stmt->close();
$caseStmt->close();
$conn->close();
?>