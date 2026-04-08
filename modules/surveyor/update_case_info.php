<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
redirectIfNotLoggedIn();
if (!isSurveyor()) {
    die("Access denied!");
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch case details by ID
if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    die("No case ID provided or invalid ID.");
}
$case_id = (int)$_GET['case_id'];

$sql = "SELECT c.id, c.title, c.status, c.land_id, c.investigation_status 
        FROM cases c 
        WHERE c.id = ? AND c.assigned_to = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $case_id, $user_id);
$case = null;
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $case = $result->fetch_assoc();
} else {
    error_log("Fetch case query failed: " . $conn->error);
}
$stmt->close();

if (!$case) {
    die("Case not found or you are not assigned to this case.");
}

// Fetch available land registrations
$sql = "SELECT id, block_number, owner_name FROM land_registration";
$land_results = $conn->query($sql);
$lands = $land_results ? $land_results->fetch_all(MYSQLI_ASSOC) : [];

// Fetch land registration details if land_id is set
$land = null;
$existing_coordinates = [];
if ($case['land_id']) {
    $sql = "SELECT owner_name, block_number, has_parcel, coordinates, owner_photo 
            FROM land_registration WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $case['land_id']);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $land = $result->fetch_assoc();
        // Parse coordinates
        if ($land && $land['coordinates']) {
            $pairs = explode(';', trim($land['coordinates'], ';'));
            foreach ($pairs as $pair) {
                $parts = explode(',', $pair);
                if (count($parts) == 2) {
                    $existing_coordinates[] = ['x' => trim($parts[0]), 'y' => trim($parts[1])];
                }
            }
        }
    } else {
        error_log("Fetch land_registration query failed: " . $conn->error);
    }
    $stmt->close();
}

// Ensure at least one coordinate row if none exist
if (empty($existing_coordinates)) {
    $existing_coordinates[] = ['x' => '', 'y' => ''];
}

// Get navbar logo and owner photo from session
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$owner_photo = $_SESSION['settings']['owner_photo'] ?? ($land['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $land_id = !empty($_POST['land_id']) ? (int)$_POST['land_id'] : null;
    $investigation_status = $_POST['investigation_status'] ?? '';
    $owner_name = $_POST['owner_name'] ?? '';
    $block_number = $_POST['block_number'] ?? '';
    $has_parcel = isset($_POST['has_parcel']) ? 1 : 0;
    $coord_x = $_POST['coord_x'] ?? [];
    $coord_y = $_POST['coord_y'] ?? [];

    // Handle owner photo upload
    $owner_photo_path = $land['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg';
    if (!empty($_FILES['owner_photo']['name']) && $_FILES['owner_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'landinfo' . DIRECTORY_SEPARATOR . 'Uploads' . DIRECTORY_SEPARATOR;
        
        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $errors[] = "Failed to create upload directory.";
                error_log("Failed to create directory: $upload_dir");
            }
        }

        // Sanitize file name
        $original_name = $_FILES['owner_photo']['name'];
        $sanitized_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $original_name);
        $file_name = time() . '_' . $sanitized_name;
        $target_file = $upload_dir . $file_name;

        // Validate file size and type
        $max_size = 2 * 1024 * 1024; // 2MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if ($_FILES['owner_photo']['size'] > $max_size) {
            $errors[] = "Owner photo must be less than 2MB.";
        } elseif (!in_array($_FILES['owner_photo']['type'], $allowed_types)) {
            $errors[] = "Only JPEG, PNG, or GIF images are allowed.";
        } else {
            // Attempt to move the file
            if (move_uploaded_file($_FILES['owner_photo']['tmp_name'], $target_file)) {
                $owner_photo_path = '/Uploads/' . $file_name;
                $_SESSION['settings']['owner_photo'] = $owner_photo_path;
            } else {
                $errors[] = "Failed to upload owner photo.";
                error_log("move_uploaded_file failed: From {$_FILES['owner_photo']['tmp_name']} to $target_file");
                error_log("Upload directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));
                error_log("Target file path: $target_file");
            }
        }
    }

    // Validate inputs
    $errors = [];
    if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        $errors[] = "Invalid status selected.";
    }
    if (!$land_id) {
        $errors[] = "Land ID is required.";
    }
    if (!in_array($investigation_status, ['NotStarted', 'InProgress', 'Completed'])) {
        $errors[] = "Invalid investigation status selected.";
    }
    if (!$owner_name) {
        $errors[] = "Owner name is required.";
    }
    if (!$block_number) {
        $errors[] = "Block number is required.";
    }
    // Validate coordinates
    $coordinates = '';
    for ($i = 0; $i < count($coord_x); $i++) {
        $x = trim($coord_x[$i]);
        $y = trim($coord_y[$i]);
        if ($x !== '' && $y !== '') {
            if (!is_numeric($x) || !is_numeric($y)) {
                $errors[] = "Coordinates must be numeric (Point " . ($i + 1) . ").";
            } else {
                $coordinates .= "$x,$y;";
            }
        }
    }
    $coordinates = rtrim($coordinates, ';');

    if (empty($errors)) {
        // Check if land_id already exists
        $existing_land = null;
        $sql = "SELECT id FROM land_registration WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $land_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $existing_land = $result->fetch_assoc();
        } else {
            error_log("Check existing land query failed: " . $conn->error);
        }
        $stmt->close();

        if ($existing_land) {
            // Update existing record
            $sql = "UPDATE land_registration SET owner_name = ?, block_number = ?, has_parcel = ?, coordinates = ?, owner_photo = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssissi", $owner_name, $block_number, $has_parcel, $coordinates, $owner_photo_path, $land_id);
            if (!$stmt->execute()) {
                $errors[] = "Failed to update land registration: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Insert new record
            $sql = "INSERT INTO land_registration (owner_name, block_number, has_parcel, coordinates, owner_photo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiss", $owner_name, $block_number, $has_parcel, $coordinates, $owner_photo_path);
            if ($stmt->execute()) {
                $land_id = $conn->insert_id;
            } else {
                $errors[] = "Failed to insert land registration: " . $conn->error;
            }
            $stmt->close();
        }

        // Update case
        if (empty($errors)) {
            $sql = "UPDATE cases SET status = ?, land_id = ?, investigation_status = ? WHERE id = ? AND assigned_to = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisis", $status, $land_id, $investigation_status, $case_id, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                header("Location: generate_certificate.php?case_id=$case_id");
                exit;
            } else {
                $errors[] = "Failed to update case: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Case Information #<?php echo htmlspecialchars($case_id); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        .content.collapsed {
            margin-left: 60px;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .form-container h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            font-weight: 500;
            color: #1e40af;
            display: block;
            margin-bottom: 5px;
        }
        .form-control {
            border-radius: 6px;
            font-size: 0.9rem;
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
        }
        .coordinates-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .coordinates-table th, .coordinates-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            font-size: 0.9rem;
            text-align: center;
        }
        .coordinates-table th {
            background: #e9ecef;
            color: #1e40af;
            font-weight: 500;
        }
        .coordinates-table input {
            width: 100%;
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 6px;
            font-size: 0.9rem;
        }
        .add-coordinate-btn {
            background: #17a2b8;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .add-coordinate-btn:hover {
            background: #138496;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #545b62, #3d4449);
        }
        .alert {
            border-radius: 6px;
        }
        .header-images {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .navbar-logo {
            width: 100px;
            height: auto;
        }
        .owner-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .form-container h2 {
                font-size: 1.5rem;
            }
            .form-control, .coordinates-table input {
                font-size: 0.8rem;
            }
            .coordinates-table th, .coordinates-table td {
                padding: 6px;
            }
            .btn-success, .btn-secondary, .add-coordinate-btn {
                font-size: 0.8rem;
                padding: 6px 12px;
            }
            .header-images {
                flex-direction: column;
                align-items: center;
            }
            .navbar-logo, .owner-photo {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>
    <div class="content" id="main-content">
        <div class="form-container">
            <div class="header-images">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo" class="navbar-logo">
                <img class="owner-photo" src="<?php echo BASE_URL . '/' . htmlspecialchars($owner_photo); ?>" alt="Suuraa Abbaa Lafti">
            </div>
            <h2>Update Case Information #<?php echo htmlspecialchars($case_id); ?></h2>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="" disabled <?php echo !$case['status'] ? 'selected' : ''; ?>>Select Status</option>
                        <option value="Pending" <?php echo $case['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $case['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $case['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="land_id">Land ID</label>
                    <select name="land_id" id="land_id" class="form-control" required>
                        <option value="" disabled <?php echo !$case['land_id'] ? 'selected' : ''; ?>>Select Land</option>
                        <?php foreach ($lands as $land_option): ?>
                            <option value="<?php echo htmlspecialchars($land_option['id']); ?>" <?php echo $case['land_id'] == $land_option['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($land_option['block_number'] . ' (ID: ' . $land_option['id'] . ', Owner: ' . $land_option['owner_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="investigation_status">Investigation Status</label>
                    <select name="investigation_status" id="investigation_status" class="form-control" required>
                        <option value="" disabled <?php echo !$case['investigation_status'] ? 'selected' : ''; ?>>Select Investigation Status</option>
                        <option value="NotStarted" <?php echo $case['investigation_status'] === 'NotStarted' ? 'selected' : ''; ?>>Not Started</option>
                        <option value="InProgress" <?php echo $case['investigation_status'] === 'InProgress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Completed" <?php echo $case['investigation_status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="owner_name">Owner Name</label>
                    <input type="text" name="owner_name" id="owner_name" class="form-control" value="<?php echo htmlspecialchars($land['owner_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="block_number">Block Number</label>
                    <input type="text" name="block_number" id="block_number" class="form-control" value="<?php echo htmlspecialchars($land['block_number'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="has_parcel">Has Parcel</label>
                    <input type="checkbox" name="has_parcel" id="has_parcel" <?php echo ($land['has_parcel'] ?? 0) ? 'checked' : ''; ?>>
                </div>
                <div class="form-group">
                    <label for="owner_photo">Owner Photo</label>
                    <input type="file" name="owner_photo" id="owner_photo" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Coordinates (XY Koordineetii)</label>
                    <table class="coordinates-table">
                        <thead>
                            <tr>
                                <th>Point</th>
                                <th>X</th>
                                <th>Y</th>
                            </tr>
                        </thead>
                        <tbody id="coordinates-tbody">
                            <?php foreach ($existing_coordinates as $index => $coord): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><input type="text" name="coord_x[]" value="<?php echo htmlspecialchars($coord['x']); ?>" placeholder="X"></td>
                                    <td><input type="text" name="coord_y[]" value="<?php echo htmlspecialchars($coord['y']); ?>" placeholder="Y"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="add-coordinate-btn" onclick="addCoordinateRow()">Add Coordinate</button>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save and Proceed
                    </button>
                    <a href="view_case.php?id=<?php echo htmlspecialchars($case_id); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <script>
        function addCoordinateRow() {
            const tbody = document.getElementById('coordinates-tbody');
            const rowCount = tbody.children.length + 1;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${rowCount}</td>
                <td><input type="text" name="coord_x[]" placeholder="X"></td>
                <td><input type="text" name="coord_y[]" placeholder="Y"></td>
            `;
            tbody.appendChild(row);
        }
    </script>
</body>
</html>