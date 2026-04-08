<?php
ob_start();
require_once '../../includes/init.php';
redirectIfNotLoggedIn();

if (!isRecordOfficer()) {
    die("Access denied!");
}

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die($translations[$lang]['db_error'] ?? "Dhaabbata database hin dandeenye: Connection error. Please try again later.");
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$debug_messages = [];
$debug_log = __DIR__ . '/debug.log';
$error_message = '';
$success_message = '';
$requests = [];
$reported_requests = [];

// Create directories
$upload_dir = __DIR__ . '/../../uploads/';
$letter_dir = __DIR__ . '/../../letters/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    $debug_messages[] = "Created uploads directory: $upload_dir";
}
if (!is_dir($letter_dir)) {
    mkdir($letter_dir, 0755, true);
    $debug_messages[] = "Created letters directory: $letter_dir";
}

// Get logo and stamp
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$company_stamp = 'assets/images/stamp-placeholder.png';
try {
    $sql = "SELECT image_path FROM company_stamps ORDER BY uploaded_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stamp = $stmt->fetch();
    $stamp_path = $stamp ? $stamp['image_path'] : null;
    $full_path = $stamp_path ? __DIR__ . '/../../' . $stamp_path : null;
    if ($stamp_path && file_exists($full_path)) {
        $company_stamp = $stamp_path;
        $debug_messages[] = "Stamp found: $company_stamp";
    } else {
        $debug_messages[] = "Stamp not found: path=" . ($stamp_path ?? 'none');
    }
} catch (PDOException $e) {
    $debug_messages[] = "Stamp query failed: " . $e->getMessage();
}

// Handle request submission (record officer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && $role === 'record_officer') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $attachment = '';

    if (empty($name) || empty($email) || empty($reason)) {
        $error_message = $translations[$lang]['required_fields'] ?? 'Please fill all required fields.';
        $debug_messages[] = "Submission failed: Missing required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = $translations[$lang]['invalid_email'] ?? 'Invalid email address.';
        $debug_messages[] = "Submission failed: Invalid email=$email";
    } else {
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid('attachment_') . '.' . $ext;
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $attachment = 'uploads/' . $filename;
                    $debug_messages[] = "File uploaded: $attachment";
                } else {
                    $error_message = $translations[$lang]['upload_failed'] ?? 'Failed to upload attachment.';
                    $debug_messages[] = "File upload failed: $destination";
                }
            } else {
                $error_message = $translations[$lang]['invalid_file'] ?? 'Invalid file type or size exceeds 5MB.';
                $debug_messages[] = "Invalid file: type={$file['type']}, size={$file['size']}";
            }
        }

        if (empty($error_message)) {
            try {
                $sql = "INSERT INTO support_letter_requests (requester_name, requester_email, requester_phone, reason, attachment, status, officer_id) 
                        VALUES (:name, :email, :phone, :reason, :attachment, 'Pending', :officer_id)";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':reason', $reason);
                $stmt->bindParam(':attachment', $attachment);
                $stmt->bindParam(':officer_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $success_message = $translations[$lang]['request_submitted'] ?? 'Support letter request submitted successfully.';
                $debug_messages[] = "Request submitted: name=$name, email=$email, officer_id=$user_id";
            } catch (PDOException $e) {
                $error_message = $translations[$lang]['submission_failed'] ?? 'Failed to submit request.';
                $debug_messages[] = "Submission failed: " . $e->getMessage();
                error_log("Request submission failed: " . $e->getMessage());
            }
        }
    }
}

// Handle officer report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report']) && $role === 'record_officer') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $report = trim($_POST['report'] ?? '');

    if ($request_id <= 0) {
        $error_message = $translations[$lang]['invalid_request'] ?? 'Invalid request ID.';
        $debug_messages[] = "Report submission failed: Invalid request_id=$request_id";
    } elseif (empty($report)) {
        $error_message = $translations[$lang]['report_required'] ?? 'Report is required.';
        $debug_messages[] = "Report submission failed: Missing report";
    } else {
        try {
            $sql = "UPDATE support_letter_requests 
                    SET status = 'Reported', officer_report = :report, updated_at = NOW() 
                    WHERE id = :request_id AND status = 'Pending' AND officer_id = :officer_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':report', $report);
            $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            $stmt->bindParam(':officer_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $success_message = $translations[$lang]['report_submitted'] ?? 'Report submitted successfully.';
                $debug_messages[] = "Report submitted: request_id=$request_id";
            } else {
                $error_message = $translations[$lang]['already_processed'] ?? 'Request already processed or invalid.';
                $debug_messages[] = "Report submission failed: No rows affected, request_id=$request_id";
            }
        } catch (PDOException $e) {
            $error_message = $translations[$lang]['submission_failed'] ?? 'Failed to submit report.';
            $debug_messages[] = "Report submission failed: " . $e->getMessage();
            error_log("Report submission failed: " . $e->getMessage());
        }
    }
}

// Handle manager approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $role === 'manager') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';

    if ($request_id <= 0) {
        $error_message = $translations[$lang]['invalid_request'] ?? 'Invalid request ID.';
        $debug_messages[] = "Action failed: Invalid request_id=$request_id";
    } else {
        try {
            $sql = "UPDATE support_letter_requests 
                    SET status = :status, manager_id = :manager_id, updated_at = NOW() 
                    WHERE id = :request_id AND status = 'Reported'";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':status', $action);
            $stmt->bindParam(':manager_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $success_message = $translations[$lang][$action === 'Approved' ? 'request_approved' : 'request_rejected'] ?? "Support letter request $action successfully.";
                $debug_messages[] = "Action successful: request_id=$request_id, action=$action";
                if ($action === 'Approved') {
                    // Fetch request details
                    $sql = "SELECT requester_name, reason, updated_at FROM support_letter_requests WHERE id = :request_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $request = $stmt->fetch();
                    if ($request) {
                        // Generate LaTeX file
                        $recipient_name = htmlspecialchars($request['requester_name']);
                        $letter_body = sprintf(
                            $translations[$lang]['letter_body'] ?? 'Dear %s, We are pleased to inform you that your request for a support letter regarding: %s has been approved.',
                            $recipient_name,
                            htmlspecialchars($request['reason'])
                        );
                        $letter_date = date('F j, Y', strtotime($request['updated_at'] ?: 'now'));

                        $latex_content = "\\documentclass{article}\n";
                        $latex_content .= "\\usepackage[utf8]{inputenc}\n";
                        $latex_content .= "\\usepackage{geometry}\n";
                        $latex_content .= "\\geometry{a4paper, margin=1in}\n";
                        $latex_content .= "\\begin{document}\n";
                        $latex_content .= "\\begin{center}\n";
                        $latex_content .= "\\textbf{Bulchinsa Mootummaa N/Oromoiyaatti}\\\\\n";
                        $latex_content .= "Wajjiraa Lafa Bulchinsaa Magaala Mattu\n";
                        $latex_content .= "\\end{center}\n\n";
                        $latex_content .= "Date: $letter_date\n\n";
                        $latex_content .= "To: $recipient_name\n\n";
                        $latex_content .= "\\textbf{Subject: Support Letter Approval}\n\n";
                        $latex_content .= "$letter_body\n\n";
                        $latex_content .= "Sincerely,\\\\\n";
                        $latex_content .= "Land Management Officer\\\\\n";
                        $latex_content .= "Wajjiraa Lafa Bulchinsaa Magaala Mattu\n";
                        $latex_content .= "\\end{document}\n";

                        $letter_file = $letter_dir . "support_letter_$request_id.tex";
                        file_put_contents($letter_file, $latex_content);
                        $debug_messages[] = "LaTeX file generated: $letter_file";
                    }
                }
            } else {
                $error_message = $translations[$lang]['already_processed'] ?? 'Request already processed or invalid.';
                $debug_messages[] = "Action failed: No rows affected, request_id=$request_id";
            }
        } catch (PDOException $e) {
            $error_message = $translations[$lang]['action_failed'] ?? 'Failed to process request.';
            $debug_messages[] = "Action failed: " . $e->getMessage();
            error_log("Action failed: " . $e->getMessage());
        }
    }
}

// Fetch requests
try {
    $sql = "SHOW TABLES LIKE 'support_letter_requests'";
    $stmt = $conn->query($sql);
    if ($stmt->rowCount() === 0) {
        $error_message = $translations[$lang]['table_missing'] ?? 'Error: support_letter_requests table does not exist';
        $debug_messages[] = "Table support_letter_requests not found";
    } else {
        if ($role === 'record_officer') {
            $sql = "SELECT id, requester_name, requester_email, requester_phone, reason, attachment, created_at 
                    FROM support_letter_requests 
                    WHERE status = 'Pending' AND officer_id = :officer_id 
                    ORDER BY created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':officer_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $requests = $stmt->fetchAll();
            $debug_messages[] = "Fetched " . count($requests) . " pending requests for officer_id=$user_id";
        }
        if ($role === 'manager') {
            $sql = "SELECT slr.id, slr.requester_name, slr.requester_email, slr.requester_phone, slr.reason, 
                           slr.attachment, slr.officer_report, slr.created_at, u.full_name as officer_name 
                    FROM support_letter_requests slr 
                    LEFT JOIN users u ON slr.officer_id = u.id 
                    WHERE slr.status = 'Reported' 
                    ORDER BY slr.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $reported_requests = $stmt->fetchAll();
            $debug_messages[] = "Fetched " . count($reported_requests) . " reported requests";
        }
    }
} catch (PDOException $e) {
    $error_message = $translations[$lang]['query_error'] ?? 'Error fetching requests.';
    $debug_messages[] = "Fetch failed: " . $e->getMessage();
    error_log("Fetch failed: " . $e->getMessage());
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - support_letter_request.php: user_id=" . ($user_id ?? 'none') . ", role=$role, messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['support_letter_request'] ?? 'Support Letter Request'; ?> - LIMS</title>
    <style>

        .form-container,
        .requests-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            color: #721c24;
        }

        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-bottom: 20px;
            color: #155724;
        }

        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #856404;
        }

        .table {
            margin-bottom: 0;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-approve {
            background-color: #4CAF50;
            border: none;
        }

        .btn-approve:hover {
            background-color: #45a049;
        }

        .btn-reject {
            background-color: #dc3545;
            border: none;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        .attachment-link {
            color: #007bff;
            text-decoration: none;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        textarea.form-control {
            resize: vertical;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .form-container,
            .requests-container {
                padding: 15px;
            }

            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <?php if ($role === 'admin' && !empty($debug_messages)): ?>
        <div class="debug-info">
            <h4><?php echo $translations[$lang]['debug_info'] ?? 'Debug Information'; ?></h4>
            <?php foreach ($debug_messages as $msg): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="main-content">
        <!-- Record Officer: Submission Form -->
        <?php if ($role === 'record_officer'): ?>
            <div class="form-container">
                <h3><?php echo $translations[$lang]['submit_request'] ?? 'Submit Support Letter Request'; ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label"><?php echo $translations[$lang]['name'] ?? 'Full Name'; ?> *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo $translations[$lang]['email'] ?? 'Email'; ?> *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label"><?php echo $translations[$lang]['phone'] ?? 'Phone'; ?></label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label"><?php echo $translations[$lang]['reason'] ?? 'Reason for Support Letter'; ?> *</label>
                        <textarea class="form-control" id="reason" name="reason" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="attachment" class="form-label"><?php echo $translations[$lang]['attachment'] ?? 'Attachment (PDF, JPG, PNG, max 5MB)'; ?></label>
                        <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <button type="submit" name="submit_request" class="btn btn-primary">
                        <?php echo $translations[$lang]['submit'] ?? 'Submit'; ?>
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Record Officer: Pending Requests -->
        <?php if ($role === 'record_officer' && !empty($requests)): ?>
            <div class="requests-container">
                <h3><?php echo $translations[$lang]['pending_requests'] ?? 'Pending Support Letter Requests'; ?></h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                <th><?php echo $translations[$lang]['name'] ?? 'Name'; ?></th>
                                <th><?php echo $translations[$lang]['email'] ?? 'Email'; ?></th>
                                <th><?php echo $translations[$lang]['phone'] ?? 'Phone'; ?></th>
                                <th><?php echo $translations[$lang]['reason'] ?? 'Reason'; ?></th>
                                <th><?php echo $translations[$lang]['attachment'] ?? 'Attachment'; ?></th>
                                <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                                <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_email']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($request['attachment']): ?>
                                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($request['attachment']); ?>" class="attachment-link" target="_blank"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                                        <?php else: ?>
                                            <?php echo $translations[$lang]['none'] ?? 'None'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <div class="mb-2">
                                                <textarea class="form-control" name="report" rows="2" placeholder="<?php echo $translations[$lang]['report_placeholder'] ?? 'Enter your report'; ?>" required></textarea>
                                            </div>
                                            <button type="submit" name="submit_report" class="btn btn-primary btn-sm">
                                                <?php echo $translations[$lang]['submit_report'] ?? 'Submit Report'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Manager: Reported Requests -->
        <?php if ($role === 'manager' && !empty($reported_requests)): ?>
            <div class="requests-container">
                <h3><?php echo $translations[$lang]['reported_requests'] ?? 'Reported Support Letter Requests'; ?></h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                <th><?php echo $translations[$lang]['name'] ?? 'Name'; ?></th>
                                <th><?php echo $translations[$lang]['email'] ?? 'Email'; ?></th>
                                <th><?php echo $translations[$lang]['reason'] ?? 'Reason'; ?></th>
                                <th><?php echo $translations[$lang]['officer_report'] ?? 'Officer Report'; ?></th>
                                <th><?php echo $translations[$lang]['officer'] ?? 'Officer'; ?></th>
                                <th><?php echo $translations[$lang]['attachment'] ?? 'Attachment'; ?></th>
                                <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                                <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reported_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['id']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requester_email']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['reason'], 0, 50)) . (strlen($request['reason']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars(substr($request['officer_report'], 0, 50)) . (strlen($request['officer_report']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars($request['officer_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($request['attachment']): ?>
                                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($request['attachment']); ?>" class="attachment-link" target="_blank"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>
                                        <?php else: ?>
                                            <?php echo $translations[$lang]['none'] ?? 'None'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-approve btn-sm">
                                                <?php echo $translations[$lang]['approve'] ?? 'Approve'; ?>
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-reject btn-sm">
                                                <?php echo $translations[$lang]['reject'] ?? 'Reject'; ?>
                                            </button>
                                        </form>
                                        <?php if (file_exists($letter_dir . "support_letter_{$request['id']}.tex")): ?>
                                            <a href="<?php echo BASE_URL . "/letters/support_letter_{$request['id']}.pdf"; ?>" class="btn btn-primary btn-sm mt-1" target="_blank">
                                                <?php echo $translations[$lang]['download_letter'] ?? 'Download Letter'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
<?php ob_end_flush(); ?>