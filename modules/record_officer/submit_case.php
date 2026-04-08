<?php
session_start();
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

if (!isRecordOfficer()) {
    die("Access denied!");
}

// Initialize variables
$error_message = '';
$user_id = $_SESSION['user']['id'] ?? null;
$debug_log = __DIR__ . '/upload_debug.log';

// Uploads directory
$upload_dir = 'Uploads/';
$fs_upload_dir = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $upload_dir);
$web_upload_dir = '/Uploads/';

// Ensure Uploads directory exists
if (!is_dir($fs_upload_dir)) {
    mkdir($fs_upload_dir, 0755, true);
    @chmod($fs_upload_dir, 0755);
    file_put_contents($debug_log, "Created Uploads directory: $fs_upload_dir\n", FILE_APPEND);
}

if (!$user_id) {
    $error_message = "User session not properly initialized.";
    die($error_message);
}

$conn = getDBConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_type']) && isset($_POST['recipient']) && isset($_POST['submit_case'])) {
    $required_fields = ['lakk_addaa', 'case_type', 'recipient'];
    $all_filled = true;
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $all_filled = false;
            $error_message = "Please fill all required fields.";
            file_put_contents($debug_log, "Error: Missing required field $field\n", FILE_APPEND);
            break;
        }
    }

    if ($all_filled && $user_id) {
        // Validate files
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $file_errors = [];
        $uploaded_files = [];

        // Prepare case details
        $case_details = [
            'full_name' => $_POST['full_name'] ?? '',
            'zone' => $_POST['godina'] ?? '',
            'village' => $_POST['ganda'] ?? '',
            'block_number' => $_POST['lakk_manaa'] ?? '',
            'other_case' => $_POST['other_case'] ?? '',
            'kutaa_hojii' => $_POST['kutaa_hojii'] ?? '',
            'maqaa_qaama' => $_POST['maqaa_qaama'] ?? '',
            'guyyaa' => $_POST['guyyaa'] ?? ''
        ];

        // Handle evidence uploads
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'nagaee_gibiraa_bara_sadii_file_') === 0) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    if (!in_array($file['type'], $allowed_types)) {
                        $file_errors[] = "Invalid file type for $key. Only JPG, PNG, PDF allowed.";
                        continue;
                    }
                    if ($file['size'] > $max_size) {
                        $file_errors[] = "File $key exceeds 5MB limit.";
                        continue;
                    }
                    $index = substr($key, -1);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_name = uniqid() . '_nagaee_' . $index . '.' . $ext;
                    $dest_path = $fs_upload_dir . $file_name;
                    $web_path = $web_upload_dir . $file_name;

                    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                        $uploaded_files[] = [
                            'path' => $web_path,
                            'type' => 'nagaee_gibiraa_bara_sadii_' . $index
                        ];
                        file_put_contents($debug_log, "Uploaded $key to $dest_path, stored as $web_path\n", FILE_APPEND);
                    } else {
                        $file_errors[] = "Failed to upload $key.";
                        file_put_contents($debug_log, "Failed to move $key to $dest_path\n", FILE_APPEND);
                    }
                }
            } else {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    if (!in_array($file['type'], $allowed_types)) {
                        $file_errors[] = "Invalid file type for $key. Only JPG, PNG, PDF allowed.";
                        continue;
                    }
                    if ($file['size'] > $max_size) {
                        $file_errors[] = "File $key exceeds 5MB limit.";
                        continue;
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_name = uniqid() . '_' . str_replace('_file', '', $key) . '.' . $ext;
                    $dest_path = $fs_upload_dir . $file_name;
                    $web_path = $web_upload_dir . $file_name;

                    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                        $uploaded_files[] = [
                            'path' => $web_path,
                            'type' => str_replace('_file', '', $key)
                        ];
                        file_put_contents($debug_log, "Uploaded $key to $dest_path, stored as $web_path\n", FILE_APPEND);
                    } else {
                        $file_errors[] = "Failed to upload $key.";
                        file_put_contents($debug_log, "Failed to move $key to $dest_path\n", FILE_APPEND);
                    }
                }
            }
        }

        // Validate nagaee_gibiraa_bara_sadii for mirkaneessa_abbaa_qabiyyumma
        if ($_POST['case_type'] === 'mirkaneessa_abbaa_qabiyyumma') {
            $has_nagaee = false;
            foreach ($uploaded_files as $file) {
                if (strpos($file['type'], 'nagaee_gibiraa_bara_sadii_') === 0) {
                    $has_nagaee = true;
                    break;
                }
            }
            if (!$has_nagaee) {
                $file_errors[] = "At least one tax receipt is required for Nagaee Gibiraa Kan Bara Sadii.";
            }
        }

        if (!empty($file_errors)) {
            $error_message = implode(' ', $file_errors);
            file_put_contents($debug_log, "File errors: " . $error_message . "\n", FILE_APPEND);
        } else {
            // Start transaction
            $conn->begin_transaction();
            try {
                // Validate land_id exists in land_registration
                $land_id = $conn->real_escape_string($_POST['lakk_addaa']);
                $stmt = $conn->prepare("SELECT id FROM land_registration WHERE id = ?");
                $stmt->bind_param('i', $land_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $conn->rollback();
                    $error_message = "Invalid Land ID: " . htmlspecialchars($land_id) . " does not exist.";
                    file_put_contents($debug_log, "Error: Invalid Land ID $land_id\n", FILE_APPEND);
                    throw new Exception($error_message);
                }
                $stmt->close();

                // Get assigned_to user ID based on recipient role
                $recipient_role = $conn->real_escape_string($_POST['recipient']);
                $stmt = $conn->prepare("SELECT id FROM users WHERE role = ? LIMIT 1");
                $stmt->bind_param('s', $recipient_role);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $conn->rollback();
                    $error_message = "No user found with role: " . htmlspecialchars($recipient_role);
                    file_put_contents($debug_log, "Error: No user found with role $recipient_role\n", FILE_APPEND);
                    throw new Exception($error_message);
                }
                $assigned_to = $result->fetch_assoc()['id'];
                $stmt->close();

                // Insert into cases table
                $stmt = $conn->prepare("INSERT INTO cases (title, case_type, description, reported_by, land_id, assigned_to, status, investigation_status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Reported', 'NotStarted', NOW())");
                $case_type = $conn->real_escape_string($_POST['case_type']);
                $title = $case_type; // Use case_type as title for consistency
                $description = json_encode($case_details);
                $stmt->bind_param('sssiii', $title, $case_type, $description, $user_id, $land_id, $assigned_to);
                if (!$stmt->execute()) {
                    throw new Exception("Error submitting case: " . $stmt->error);
                }
                $case_id = $conn->insert_id;
                $stmt->close();

                // Insert evidence into case_evidence table
                foreach ($uploaded_files as $file) {
                    $stmt = $conn->prepare("INSERT INTO case_evidence (case_id, file_path, evidence_type) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', $case_id, $file['path'], $file['type']);
                    if (!$stmt->execute()) {
                        throw new Exception("Error saving evidence: " . $stmt->error);
                    }
                    $stmt->close();
                }

                // Notify recipients
                $stmt = $conn->prepare("SELECT id FROM users WHERE role = ?");
                $stmt->bind_param('s', $recipient_role);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $recipient_id = $row['id'];
                    $message = "New case reported: '$title' for landowner ID: " . $_POST['lakk_addaa'];
                    $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, case_id, message) VALUES (?, ?, ?)");
                    $notify_stmt->bind_param("iis", $recipient_id, $case_id, $message);
                    if (!$notify_stmt->execute()) {
                        throw new Exception("Error sending notification: " . $notify_stmt->error);
                    }
                    $notify_stmt->close();
                }
                $stmt->close();

                // Commit transaction
                $conn->commit();
                echo "Case successfully reported!";
                file_put_contents($debug_log, "Case ID $case_id created with " . count($uploaded_files) . " evidence files\n", FILE_APPEND);
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
                file_put_contents($debug_log, "Error: " . $error_message . "\n", FILE_APPEND);
                echo "Error: " . htmlspecialchars($error_message);
            }
        }
    } else {
        echo "Error: " . htmlspecialchars($error_message);
    }
}

$conn->close();
?>