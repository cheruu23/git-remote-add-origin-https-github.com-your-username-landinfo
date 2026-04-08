<?php
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/languages.php';
redirectIfNotLoggedIn();

if (!isRecordOfficer()) {
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

// Set language
$lang = in_array($_GET['lang'] ?? 'en', ['en', 'om']) ? $_GET['lang'] : 'en';

// Initialize variables
$error_message = '';
$success_message = '';
$landowner_data = null;
$user_id = $_SESSION['user']['id'] ?? null;
$user_role = '';
$user_full_name = '';
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
    $error_message = $translations[$lang]['error_session'] ?? "User session not properly initialized.";
} else {
    // Fetch user details
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT role, full_name FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user_data = $result->fetch_assoc()) {
        $user_role = $user_data['role'];
        $user_full_name = $user_data['full_name'];
    } else {
        $error_message = $translations[$lang]['error_user_data'] ?? "User data not found.";
    }
    $stmt->close();
}

// Fetch landowner data if ID is provided
if (isset($_POST['lakk_addaa']) && !empty($_POST['lakk_addaa'])) {
    $id = $conn->real_escape_string($_POST['lakk_addaa']);
    $stmt = $conn->prepare("SELECT owner_name, first_name, middle_name, zone, village, block_number, owner_photo FROM land_registration WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $landowner_data = $result->fetch_assoc();
    if (!$landowner_data) {
        $error_message = $translations[$lang]['error_no_record'] ?? "No record found for ID: " . htmlspecialchars($id);
    } else {
        $landowner_data['full_name'] = trim($landowner_data['owner_name'] . ' ' . $landowner_data['first_name'] . ' ' . ($landowner_data['middle_name'] ?? ''));
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_type']) && isset($_POST['recipient']) && isset($_POST['submit_case'])) {
    $required_fields = ['lakk_addaa', 'case_type', 'recipient'];
    $all_filled = true;
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $all_filled = false;
            $error_message = $translations[$lang]['error_required_fields'] ?? "Please fill all required fields.";
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
            'full_name' => $landowner_data['full_name'] ?? '',
            'zone' => $landowner_data['zone'] ?? '',
            'village' => $landowner_data['village'] ?? '',
            'block_number' => $landowner_data['block_number'] ?? '',
            'other_case' => $_POST['other_case'] ?? '',
            'kutaa_hojii' => $_POST['kutaa_hojii'] ?? '',
            'maqaa_qaama' => $_POST['maqaa_qaama'] ?? '',
            'guyyaa' => $_POST['guyyaa'] ?? ''
        ];

        // Handle evidence uploads (unchanged)
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'nagaee_gibiraa_bara_sadii_file_') === 0) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    if (!in_array($file['type'], $allowed_types)) {
                        $file_errors[] = $translations[$lang]['error_invalid_file_type'] ?? "Invalid file type for $key. Only JPG, PNG, PDF allowed.";
                        continue;
                    }
                    if ($file['size'] > $max_size) {
                        $file_errors[] = $translations[$lang]['error_file_size'] ?? "File $key exceeds 5MB limit.";
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
                        $file_errors[] = $translations[$lang]['error_upload_failed'] ?? "Failed to upload $key.";
                        file_put_contents($debug_log, "Failed to move $key to $dest_path\n", FILE_APPEND);
                    }
                }
            } else {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    if (!in_array($file['type'], $allowed_types)) {
                        $file_errors[] = $translations[$lang]['error_invalid_file_type'] ?? "Invalid file type for $key. Only JPG, PNG, PDF allowed.";
                        continue;
                    }
                    if ($file['size'] > $max_size) {
                        $file_errors[] = $translations[$lang]['error_file_size'] ?? "File $key exceeds 5MB limit.";
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
                        $file_errors[] = $translations[$lang]['error_upload_failed'] ?? "Failed to upload $key.";
                        file_put_contents($debug_log, "Failed to move $key to $dest_path\n", FILE_APPEND);
                    }
                }
            }
        }

        // Validate nagaee_gibiraa_bara_sadii for mirkaneessa
        if ($_POST['case_type'] === 'mirkaneessa') {
            $has_nagaee = false;
            foreach ($uploaded_files as $file) {
                if (strpos($file['type'], 'nagaee_gibiraa_bara_sadii_') === 0) {
                    $has_nagaee = true;
                    break;
                }
            }
            if (!$has_nagaee) {
                $file_errors[] = $translations[$lang]['error_tax_receipt_required'] ?? "At least one tax receipt is required for Nagaee Gibiraa Kan Bara Sadii.";
            }
        }

        if (!empty($file_errors)) {
            $error_message = implode(' ', $file_errors);
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
                    $error_message = $translations[$lang]['error_invalid_land_id'] ?? "Invalid Land ID: " . htmlspecialchars($land_id) . " does not exist.";
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
                    $error_message = $translations[$lang]['error_no_recipient'] ?? "No user found with role: " . htmlspecialchars($recipient_role);
                    file_put_contents($debug_log, "Error: No user found with role $recipient_role\n", FILE_APPEND);
                    throw new Exception($error_message);
                }
                $assigned_to = $result->fetch_assoc()['id'];
                $stmt->close();

                // Insert into cases table
                $stmt = $conn->prepare("INSERT INTO cases (title, description, reported_by, land_id, assigned_to, status) VALUES (?, ?, ?, ?, ?, 'Reported')");
                $title = $conn->real_escape_string($_POST['case_type']);
                $description = json_encode($case_details);
                $stmt->bind_param('ssiis', $title, $description, $user_id, $land_id, $assigned_to);
                if (!$stmt->execute()) {
                    throw new Exception($translations[$lang]['error_submit_case'] ?? "Error submitting case: " . $stmt->error);
                }
                $case_id = $conn->insert_id;
                $stmt->close();

                // Insert evidence into case_evidence table
                foreach ($uploaded_files as $file) {
                    $stmt = $conn->prepare("INSERT INTO case_evidence (case_id, file_path, evidence_type) VALUES (?, ?, ?)");
                    $stmt->bind_param('iss', $case_id, $file['path'], $file['type']);
                    if (!$stmt->execute()) {
                        throw new Exception($translations[$lang]['error_save_evidence'] ?? "Error saving evidence: " . $stmt->error);
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
                    $message = $translations[$lang]['notification_new_case'] ?? "New case reported: '$title' for landowner ID: " . $_POST['lakk_addaa'];
                    $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, case_id, message) VALUES (?, ?, ?)");
                    $notify_stmt->bind_param("iis", $recipient_id, $case_id, $message);
                    if (!$notify_stmt->execute()) {
                        throw new Exception($translations[$lang]['error_notification'] ?? "Error sending notification: " . $notify_stmt->error);
                    }
                    $notify_stmt->close();
                }
                $stmt->close();

                // Commit transaction
                $conn->commit();
                $success_message = $translations[$lang]['success_case_reported'] ?? "Case successfully reported!";
                file_put_contents($debug_log, "Case ID $case_id created with " . count($uploaded_files) . " evidence files\n", FILE_APPEND);
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
                file_put_contents($debug_log, "Error: " . $error_message . "\n", FILE_APPEND);
            }
        }
    }
}

// Include sidebar.php AFTER all logic
include '../../templates/sidebar.php';
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['page_title'] ?? 'Report Case'; ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .form-section h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a3c6d;
            background: linear-gradient(135deg, #f1f1f1, #e0e0e0);
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 500;
            font-size: 0.9rem;
            color: #333;
        }

        .form-control,
        .form-select {
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 6px rgba(0, 123, 255, 0.2);
        }

        .evidence-upload {
            display: none;
            margin-top: 5px;
        }

        #evidence-section,
        #other_case_input {
            display: none;
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .text-success {
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 6px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .btn-secondary {
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 6px;
        }

        .sub-upload {
            margin-top: 10px;
        }

        .file-preview {
            margin-top: 8px;
            max-width: 100px;
        }

        .file-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .file-preview .pdf-preview {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: #333;
        }

        .file-preview .pdf-preview i {
            margin-right: 8px;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }

            .form-container {
                padding: 15px;
            }

            h1,
            h2.text-center {
                font-size: 1.5rem;
            }

            .file-preview {
                max-width: 80px;
            }
        }
    </style>
</head>

<body>
    <div class="content">
        <div class="container mt-4">
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="modal fade show" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" style="display: block;" aria-modal="true" role="dialog">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="successModalLabel"><?php echo $translations[$lang]['modal_success_title'] ?? 'Success'; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                            <div class="modal-footer">
                                <a href="dashboard.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-primary"><?php echo $translations[$lang]['button_go_dashboard'] ?? 'Go to Dashboard'; ?></a>
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?lang=' . $lang); ?>'"><?php echo $translations[$lang]['button_report_another'] ?? 'Report Another Case'; ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop fade show"></div>
            <?php endif; ?>
            <div class="form-container">
                <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="caseForm">
                    <input type="hidden" name="submit_case" value="1">
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                    <div class="form-section text-center">
                        <p><?php echo $translations[$lang]['form_intro_2'] ?? 'Service Request Form'; ?></p>
                    </div>

                    <div class="form-section">
                        <h2><?php echo $translations[$lang]['section_tajaajilaamaa'] ?? '1. Applicant Information'; ?></h2>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="lakk_addaa"><?php echo $translations[$lang]['label_lakk_addaa'] ?? 'ID:'; ?></label>
                                <input type="text" name="lakk_addaa" id="lakk_addaa" class="form-control" value="<?php echo htmlspecialchars($_POST['lakk_addaa'] ?? ''); ?>" required>
                                <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_id'] ?? 'Please enter a valid ID.'; ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations[$lang]['label_full_name'] ?? 'Full Name:'; ?></label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($landowner_data['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations[$lang]['label_owner_photo'] ?? 'Owner Photo:'; ?></label>
                                <?php if (!empty($landowner_data['owner_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($landowner_data['owner_photo']); ?>" alt="<?php echo $translations[$lang]['alt_owner_photo'] ?? 'Owner Photo'; ?>" class="img-thumbnail" style="max-width: 80px; height: auto;">
                                <?php else: ?>
                                    <p class="text-muted"><?php echo $translations[$lang]['no_photo'] ?? 'No photo available'; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations[$lang]['label_zone'] ?? 'Zone:'; ?></label>
                                <input type="text" name="godina" class="form-control" value="<?php echo htmlspecialchars($landowner_data['zone'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations[$lang]['label_village'] ?? 'Village:'; ?></label>
                                <input type="text" name="ganda" class="form-control" value="<?php echo htmlspecialchars($landowner_data['village'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label><?php echo $translations[$lang]['label_block_number'] ?? 'Block Number:'; ?></label>
                                <input type="text" name="lakk_manaa" class="form-control" value="<?php echo htmlspecialchars($landowner_data['block_number'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2><?php echo $translations[$lang]['section_tajaajila'] ?? '2. Requested Service'; ?></h2>
                        <div class="form-group">
                            <label for="case_type"><?php echo $translations[$lang]['label_case_type'] ?? 'Service Type:'; ?></label>
                            <select name="case_type" id="case_type" class="form-select" required>
                                <option value=""><?php echo $translations[$lang]['option_select_service'] ?? '-- Select Service --'; ?></option>
                                <option value="mirkaneessa_abbaa_qabiyyumma"><?php echo $translations[$lang]['case_mirkaneessa_abbaa_qabiyyumma'] ?? '1. Confirmation of City Land Ownership'; ?></option>
                                <option value="mirkaneessa_sirrumma_waraqa_ragaa"><?php echo $translations[$lang]['case_mirkaneessa_sirrumma_waraqa_ragaa'] ?? '2. Verification of Ownership Document Authenticity'; ?></option>
                                <option value="waraqa_bade_bakka_buusu"><?php echo $translations[$lang]['case_waraqa_bade_bakka_buusu'] ?? '3. Replacement of Lost Ownership Document'; ?></option>
                                <option value="qabiye_walitti_makuu"><?php echo $translations[$lang]['case_qabiye_walitti_makuu'] ?? '4. Merging Properties'; ?></option>
                                <option value="qabiyyee_qooduu"><?php echo $translations[$lang]['case_qabiyyee_qooduu'] ?? '5. Dividing Property'; ?></option>
                                <option value="jijjirra_maqaa"><?php echo $translations[$lang]['case_jijjirra_maqaa'] ?? '6. Change of Ownership Name'; ?></option>
                                <option value="galmeessa_dhorkaa"><?php echo $translations[$lang]['case_galmeessa_dhorkaa'] ?? '7. Restricted Registry/Registry Confirmation'; ?></option>
                                <option value="kooppi_ragaa"><?php echo $translations[$lang]['case_kooppi_ragaa'] ?? '8. Copy of Registry Document'; ?></option>
                                <option value="walitti_buiinsa_daangaa"><?php echo $translations[$lang]['case_walitti_buiinsa_daangaa'] ?? '9. Boundary Dispute'; ?></option>
                                <option value="ajaja_mana_murtii"><?php echo $translations[$lang]['case_ajaja_mana_murtii'] ?? '10. Court Order'; ?></option>
                                <option value="eeyyama_ijaarsaa"><?php echo $translations[$lang]['case_eeyyama_ijaarsaa'] ?? '11. Construction Permit'; ?></option>
                                <option value="baballifannaa"><?php echo $translations[$lang]['case_baballifannaa'] ?? '12. Investment Expansion'; ?></option>
                                <option value="qophii_sayitii"><?php echo $translations[$lang]['case_qophii_sayitii'] ?? '13. Site Plan'; ?></option>
                                <option value="kan_biroo"><?php echo $translations[$lang]['case_kan_biroo'] ?? '14. Other'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['error_select_case_type'] ?? 'Please select a case type.'; ?></div>
                            <div id="other_case_input" class="mt-2">
                                <label for="other_case"><?php echo $translations[$lang]['label_other_case'] ?? 'Details:'; ?></label>
                                <input type="text" name="other_case" id="other_case" class="form-control" placeholder="<?php echo $translations[$lang]['placeholder_other_case'] ?? 'Enter details'; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section" id="evidence-section">
                        <h2><?php echo $translations[$lang]['section_ragaa'] ?? '3. Supporting Documents'; ?></h2>
                        <div id="evidence-options"></div>
                    </div>

                    <div class="form-section">
                        <h2><?php echo $translations[$lang]['section_qaama'] ?? '4. Reporting Entity'; ?></h2>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="kutaa_hojii"><?php echo $translations[$lang]['label_kutaa_hojii'] ?? 'Department:'; ?></label>
                                <input type="text" name="kutaa_hojii" id="kutaa_hojii" class="form-control" value="<?php echo $user_role === 'record_officer' ? ($translations[$lang]['value_kutaa_hojii'] ?? 'Qondaala Galmeessaa') : ''; ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="maqaa_qaama"><?php echo $translations[$lang]['label_maqaa_qaama'] ?? 'Name:'; ?></label>
                                <input type="text" name="maqaa_qaama" id="maqaa_qaama" class="form-control" value="<?php echo htmlspecialchars($user_full_name); ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="guyyaa"><?php echo $translations[$lang]['label_guyyaa'] ?? 'Date:'; ?></label>
                                <input type="date" name="guyyaa" id="guyyaa" class="form-control" value="<?php echo htmlspecialchars($_POST['guyyaa'] ?? ''); ?>">
                                <div class="invalid-feedback"><?php echo $translations[$lang]['error_select_date'] ?? 'Please select a date.'; ?></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="recipient"><?php echo $translations[$lang]['label_recipient'] ?? 'Recipient:'; ?></label>
                            <select name="recipient" id="recipient" class="form-select" required>
                                <option value=""><?php echo $translations[$lang]['option_select_recipient'] ?? '-- Select Recipient --'; ?></option>
                                <option value="manager"><?php echo $translations[$lang]['recipient_manager'] ?? 'Manager'; ?></option>
                                <option value="surveyor"><?php echo $translations[$lang]['recipient_surveyor'] ?? 'Surveyor'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['error_select_recipient'] ?? 'Please select a recipient.'; ?></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php?lang=<?php echo htmlspecialchars($lang); ?>" class="btn btn-secondary"><?php echo $translations[$lang]['button_back_dashboard'] ?? 'Back to Dashboard'; ?></a>
                        <button type="submit" class="btn btn-primary"><?php echo $translations[$lang]['button_submit'] ?? 'Submit Case'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const evidenceRequirements = {
            mirkaneessa_abbaa_qabiyyumma: [
                'qaboo_garee_fi_gooxii',
                'xalayaa_koree_hawasummaa_ganda',
                'enyumessaa_ganda',
                'nagaee_gibiraa_bara_sadii'
            ],
            mirkaneessa_sirrumma_waraqa_ragaa: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa', 'sanada_waliigalteen_lizii_itti_galame'],
            biyyaa: ['waraqaa_eenyummaa', 'waraqa_ragaa_abba_qabiyyummaa'],
            waraqa_bade_bakka_buusu: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa', 'ragaa_kaartichi_baduu_isaa_qaama_seera_irra_kenname'],
            qabiyyee_qooduu: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa', 'waraqa_ragaa_abba_qabiyyummaa'],
            jijjirra_maqaa: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa', 'waraqa_ragaa_abba_qabiyyummaa', 'sanada_waliigalteen_lizii_itti_galame', 'suuraa_3x4_lama_yeroo_dhiyoo'],
            galmeessa_dhorkaa: ['ragaa_bakka_buummaa', 'waraqaa_eenyummaa', 'waraqa_ragaa_abba_qabiyyummaa', 'xalayaa_idaa_fi_dhorka_ittin_galmeessu'],
            kooppi_ragaa: ['waraqaa_eenyummaa'],
            walitti_buiinsa_daangaa: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa', 'waraqa_ragaa_abba_qabiyyummaa'],
            ajaja_mana_murtii: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa'],
            eeyyama_ijaarsaa: ['waraqaa_eenyummaa', 'ragaa_bakka_buummaa'],
            baballifanna: ['waraqaa_eenyummaa', 'waraqa_ragaa_abba_qabiyyummaa'],
            qophii_sayitii: ['waraqaa_eenyummaa'],
            kan_biroo: ['waraqaa_eenyummaa']
        };

        const evidenceLabels = {
            qaboo_garee_fi_gooxii: '<?php echo $translations[$lang]['evidence_qaboo_garee_fi_gooxii'] ?? '1. Team and Cluster Agreement'; ?>',
            xalayaa_koree_hawasummaa_ganda: '<?php echo $translations[$lang]['evidence_xalayaa_koree_hawasummaa_ganda'] ?? '2. Community Committee Letter'; ?>',
            enyumessaa_ganda: '<?php echo $translations[$lang]['evidence_enyumessaa_ganda'] ?? '3. Community ID (ID Card)'; ?>',
            nagaee_gibiraa_bara_sadii: '<?php echo $translations[$lang]['evidence_nagaee_gibiraa_bara_sadii'] ?? '4. Tax Receipt of Three Years'; ?>',
            waraqaa_eenyummaa: '<?php echo $translations[$lang]['evidence_waraqaa_eenyummaa'] ?? 'Identity Document'; ?>',
            ragaa_bakka_buummaa: '<?php echo $translations[$lang]['evidence_ragaa_bakka_buummaa'] ?? 'Proof of Representation'; ?>',
            waraqa_ragaa_abba_qabiyyummaa: '<?php echo $translations[$lang]['evidence_waraqa_ragaa_abba_qabiyyummaa'] ?? 'Ownership Document'; ?>',
            sanada_waliigalteen_lizii_itti_galame: '<?php echo $translations[$lang]['evidence_sanada_waliigalteen_lizii_itti_galame'] ?? 'Lease Agreement Document'; ?>',
            suuraa_3x4_lama_yeroo_dhiyoo: '<?php echo $translations[$lang]['evidence_suuraa_3x4_lama_yeroo_dhiyoo'] ?? 'Recent 3x4 Photos (4)'; ?>',
            xalayaa_idaa_fi_dhorka_ittin_galmeessu: '<?php echo $translations[$lang]['evidence_xalayaa_idaa_fi_dhorka_ittin_galmeessu'] ?? 'Letter of Addition and Restriction for Registration'; ?>',
            ragaa_kaartichi_baduu_isaa_qaama_seera_irra_kenname: '<?php echo $translations[$lang]['evidence_ragaa_kaartichi_baduu_isaa_qaama_seera_irra_kenname'] ?? 'Proof of Map Loss Issued by Legal Authority'; ?>'
        };

        document.getElementById('lakk_addaa').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('case_type').addEventListener('change', function() {
            document.getElementById('other_case_input').style.display = this.value === 'kan_biroo' ? 'block' : 'none';
            updateEvidenceOptions();
        });

        function updateEvidenceOptions() {
            const caseType = document.getElementById('case_type').value;
            const evidenceSection = document.getElementById('evidence-section');
            const evidenceOptions = document.getElementById('evidence-options');

            evidenceSection.style.display = caseType ? 'block' : 'none';
            evidenceOptions.innerHTML = '';

            if (caseType) {
                const evidences = evidenceRequirements[caseType] || [];
                evidences.forEach(evidence => {
                    const isOptional = evidence === 'ragaa_bakka_buummaa';
                    if (evidence === 'nagaee_gibiraa_bara_sadii') {
                        evidenceOptions.innerHTML += `
                            <div class="form-group mb-3" id="evidence_${evidence}">
                                <label class="form-label">
                                    <input type="checkbox" name="evidence[]" value="${evidence}" onchange="toggleUpload('${evidence}')" checked>
                                    ${evidenceLabels[evidence]}
                                </label>
                                <div class="evidence-upload" id="upload_${evidence}" style="display: block;">
                                    <div class="sub-upload">
                                        <label class="form-label"><?php echo $translations[$lang]['label_year_1'] ?? 'Year 1:'; ?></label>
                                        <input type="file" name="${evidence}_file_1" class="form-control" accept=".pdf,.jpg,.png" onchange="previewFile(this, '${evidence}_preview_1')">
                                        <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_file'] ?? 'Please upload a valid file (JPG, PNG, PDF, max 5MB).'; ?></div>
                                        <div class="file-preview" id="${evidence}_preview_1"></div>
                                    </div>
                                    <div class="sub-upload">
                                        <label class="form-label"><?php echo $translations[$lang]['label_year_2'] ?? 'Year 2:'; ?></label>
                                        <input type="file" name="${evidence}_file_2" class="form-control" accept=".pdf,.jpg,.png" onchange="previewFile(this, '${evidence}_preview_2')">
                                        <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_file'] ?? 'Please upload a valid file (JPG, PNG, PDF, max 5MB).'; ?></div>
                                        <div class="file-preview" id="${evidence}_preview_2"></div>
                                    </div>
                                    <div class="sub-upload">
                                        <label class="form-label"><?php echo $translations[$lang]['label_year_3'] ?? 'Year 3:'; ?></label>
                                        <input type="file" name="${evidence}_file_3" class="form-control" accept=".pdf,.jpg,.png" onchange="previewFile(this, '${evidence}_preview_3')">
                                        <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_file'] ?? 'Please upload a valid file (JPG, PNG, PDF, max 5MB).'; ?></div>
                                        <div class="file-preview" id="${evidence}_preview_3"></div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (isOptional) {
                        evidenceOptions.innerHTML += `
                            <div class="form-group mb-3" id="evidence_${evidence}">
                                <label class="form-label">
                                    <input type="checkbox" name="evidence[]" value="${evidence}" id="checkbox_${evidence}" style="display: none;">
                                    ${evidenceLabels[evidence]}
                                    <button type="button" class="btn btn-sm btn-success ml-2" onclick="enableUpload('${evidence}')"><?php echo $translations[$lang]['button_enable'] ?? '✓'; ?></button>
                                    <button type="button" class="btn btn-sm btn-danger ml-2" onclick="disableUpload('${evidence}')"><?php echo $translations[$lang]['button_disable'] ?? '✗'; ?></button>
                                </label>
                                <div class="evidence-upload" id="upload_${evidence}" style="display: none;">
                                    <input type="file" name="${evidence}_file" class="form-control" accept=".pdf,.jpg,.png" onchange="previewFile(this, '${evidence}_preview')">
                                    <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_file'] ?? 'Please upload a valid file (JPG, PNG, PDF, max 5MB).'; ?></div>
                                    <div class="file-preview" id="${evidence}_preview"></div>
                                </div>
                            </div>
                        `;
                    } else {
                        evidenceOptions.innerHTML += `
                            <div class="form-group mb-3" id="evidence_${evidence}">
                                <label class="form-label">
                                    <input type="checkbox" name="evidence[]" value="${evidence}" onchange="toggleUpload('${evidence}')" checked>
                                    ${evidenceLabels[evidence]}
                                </label>
                                <div class="evidence-upload" id="upload_${evidence}" style="display: block;">
                                    <input type="file" name="${evidence}_file" class="form-control" accept=".pdf,.jpg,.png" required onchange="previewFile(this, '${evidence}_preview')">
                                    <div class="invalid-feedback"><?php echo $translations[$lang]['error_invalid_file'] ?? 'Please upload a valid file (JPG, PNG, PDF, max 5MB).'; ?></div>
                                    <div class="file-preview" id="${evidence}_preview"></div>
                                </div>
                            </div>
                        `;
                    }
                });
            }
        }

        function toggleUpload(evidenceId) {
            const uploadDiv = document.getElementById(`upload_${evidenceId}`);
            const checkbox = document.querySelector(`input[value="${evidenceId}"]`);
            uploadDiv.style.display = checkbox.checked ? 'block' : 'none';
            const fileInputs = uploadDiv.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.required = checkbox.checked && evidenceId !== 'nagaee_gibiraa_bara_sadii';
            });
        }

        function enableUpload(evidenceId) {
            console.log(`Enabling upload for ${evidenceId}`);
            const checkbox = document.getElementById(`checkbox_${evidenceId}`);
            const uploadDiv = document.getElementById(`upload_${evidenceId}`);
            checkbox;
            checkbox.checked = true;
            uploadDiv.style.display = 'block';
            const fileInput = uploadDiv.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.required = true;
            }
        }

        function disableUpload(evidenceId) {
            console.log(`Disabling upload for ${evidenceId}`);
            const evidenceDiv = document.getElementById(`evidence_${evidenceId}`);
            if (evidenceDiv) {
                evidenceDiv.remove(); // Remove the entire evidence container
            }
        }

        function previewFile(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            const file = input.files[0];
            if (file) {
                const fileType = file.type;
                const fileName = file.name;
                if (fileType === 'application/pdf') {
                    preview.innerHTML = `
                        <div class="pdf-preview">
                            <i class="fas fa-file-pdf fa-lg"></i>
                            <span>${fileName}</span>
                        </div>
                    `;
                } else if (fileType === 'image/jpeg' || fileType === 'image/png') {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="<?php echo $translations[$lang]['alt_file_preview'] ?? 'File Preview'; ?>">`;
                    };
                    reader.readAsDataURL(file);
                }
            }
        }

        document.getElementById('caseForm').addEventListener('submit', function(e) {
            const files = document.querySelectorAll('input[type="file"]');
            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            const maxSize = 5 * 1024 * 1024;
            let valid = true;
            let nagaeeFiles = 0;

            files.forEach(fileInput => {
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (!allowedTypes.includes(file.type)) {
                        fileInput.classList.add('is-invalid');
                        valid = false;
                    } else if (file.size > maxSize) {
                        fileInput.classList.add('is-invalid');
                        valid = false;
                    } else {
                        fileInput.classList.remove('is-invalid');
                        if (fileInput.name.includes('nagaee_gibiraa_bara_sadii_file_')) {
                            nagaeeFiles++;
                        }
                    }
                }
            });

            const caseType = document.getElementById('case_type').value;
            const nagaeeCheckbox = document.querySelector('input[value="nagaee_gibiraa_bara_sadii"]');
            if (caseType === 'mirkaneessa' && nagaeeCheckbox && nagaeeCheckbox.checked && nagaeeFiles === 0) {
                document.querySelectorAll('input[name^="nagaee_gibiraa_bara_sadii_file_"]').forEach(input => {
                    input.classList.add('is-invalid');
                });
                valid = false;
            }

            if (!valid || !this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });

        updateEvidenceOptions();
    </script>
</body>

</html>