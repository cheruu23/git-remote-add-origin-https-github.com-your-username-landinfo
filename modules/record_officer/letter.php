<?php
ob_start();
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Redirect if not logged in or not authorized
function isAuthorizedUser() {
    return isset($_SESSION['user']['role']) && 
           in_array($_SESSION['user']['role'], ['manager', 'record_officer', 'surveyor']);
}
if (!isAuthorizedUser()) {
    die("Access denied!");
}

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

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
$case_id = (int)($_GET['id'] ?? 0);
$debug_messages = [];
$debug_log = __DIR__ . '/debug.log';

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Fetch case and land details
$case_data = null;
$land_data = null;
if ($case_id > 0) {
    try {
        $sql = "SELECT c.id, c.title, c.case_type, c.land_id, c.description, c.status 
                FROM cases c 
                WHERE c.id = :case_id AND c.status = 'Approved'";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
        $stmt->execute();
        $case_data = $stmt->fetch();
        if ($case_data) {
            $debug_messages[] = "Case ID $case_id found, status: {$case_data['status']}";
            if ($case_data['land_id']) {
                $sql = "SELECT owner_name, first_name, middle_name, village, parcel_number 
                        FROM land_registration 
                        WHERE id = :land_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':land_id', $case_data['land_id'], PDO::PARAM_INT);
                $stmt->execute();
                $land_data = $stmt->fetch();
                if ($land_data) {
                    $debug_messages[] = "Land ID {$case_data['land_id']} found";
                } else {
                    $debug_messages[] = "Land ID {$case_data['land_id']} not found";
                }
            } else {
                $debug_messages[] = "No land ID associated with case ID $case_id";
            }
        } else {
            $debug_messages[] = "Case ID $case_id not found or not approved";
            $error_message = $translations[$lang]['case_not_found'] ?? "Case not found or not approved for ID: $case_id";
        }
    } catch (PDOException $e) {
        $debug_messages[] = "Query failed: " . $e->getMessage();
        error_log("Query failed: " . $e->getMessage());
        $error_message = $translations[$lang]['query_error'] ?? "Error fetching case details: Please try again later.";
    }
} else {
    $debug_messages[] = "Invalid or missing case ID";
    $error_message = $translations[$lang]['invalid_case_id'] ?? "Invalid or missing case ID.";
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

// Prepare letter content
$recipient_name = 'N/A';
$letter_subject = $case_data ? ($case_data['title'] ?? 'Untitled') : 'Untitled';
$letter_body = $translations[$lang]['letter_default_body'] ?? 'No case details available.';
if ($case_data) {
    if ($land_data) {
        $recipient_name = trim(($land_data['first_name'] ?? '') . ' ' . ($land_data['middle_name'] ?? '') . ' ' . ($land_data['owner_name'] ?? ''));
        $recipient_name = $recipient_name ?: 'Landowner';
        $letter_body = sprintf(
            $translations[$lang]['letter_body'] ?? 'Dear %s, We are pleased to inform you that your case (%s, %s) regarding the land parcel (%s) in %s has been approved.',
            htmlspecialchars($recipient_name),
            htmlspecialchars($case_data['title'] ?? 'Untitled'),
            htmlspecialchars($translations[$lang]['case_' . $case_data['case_type']] ?? $case_data['case_type'] ?? 'N/A'),
            htmlspecialchars($land_data['parcel_number'] ?? 'N/A'),
            htmlspecialchars($land_data['village'] ?? 'N/A')
        );
    } else {
        $desc_data = $case_data['description'] && json_decode($case_data['description'], true) ? json_decode($case_data['description'], true) : [];
        $recipient_name = $desc_data['full_name'] ?? 'Landowner';
        $letter_body = sprintf(
            $translations[$lang]['letter_body_no_land'] ?? 'Dear %s, We are pleased to inform you that your case (%s, %s) has been approved.',
            htmlspecialchars($recipient_name),
            htmlspecialchars($case_data['title'] ?? 'Untitled'),
            htmlspecialchars($translations[$lang]['case_' . $case_data['case_type']] ?? $case_data['case_type'] ?? 'N/A')
        );
    }
}

// Handle form submission
$preview_recipient = $recipient_name;
$preview_subject = $letter_subject;
$preview_body = $letter_body;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preview_recipient = trim($_POST['recipient'] ?? $recipient_name);
    $preview_subject = trim($_POST['subject'] ?? $letter_subject);
    $preview_body = trim($_POST['body'] ?? $letter_body);
    if (empty($preview_recipient) || empty($preview_subject)) {
        $error_message = $translations[$lang]['form_validation_error'] ?? "Recipient and subject are required.";
    } else {
        $debug_messages[] = "Form submitted: recipient=$preview_recipient, subject=$preview_subject";
    }
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - letter.php: case_id=$case_id, messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['letter_title'] ?? 'Formal Letter'; ?> #<?php echo htmlspecialchars($case_id); ?> - LIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: rgb(233, 245, 236);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .print-button, .preview-button {
            margin-bottom: 20px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button {
            background-color: #4CAF50;
            color: white;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        .preview-button {
            background-color: #007bff;
            color: white;
        }
        .preview-button:hover {
            background-color: #0056b3;
        }
        .letter-container {
            width: 800px;
            border: 2px solid #000;
            background-color: rgb(255, 255, 255);
            padding: 40px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            page-break-after: always;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(255, 0, 0, 0.3);
            pointer-events: none;
            text-transform: uppercase;
            font-weight: bold;
        }
        .company-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.9;
            pointer-events: none;
        }
        .company-stamp img {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            position: relative;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }
        .header h2 {
            font-size: 16px;
            font-weight: bold;
            color: #c0392b;
            margin-top: 5px;
        }
        .logo {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 80px;
            height: 80px;
            border-radius: 50px;
        }
        .section {
            margin-bottom: 20px;
            max-width: 100%;
            overflow: hidden;
        }
        .section h2 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .section p#previewBody {
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            margin: 0;
        }
        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            color: #721c24;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #856404;
        }
        .letter-meta {
            margin-bottom: 20px;
        }
        .letter-meta p {
            font-size: 14px;
            color: #2c3e50;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .letter-footer {
            margin-top: 40px;
            text-align: right;
        }
        .letter-footer p {
            font-size: 14px;
            color: #2c3e50;
        }
        .signature-line {
            width: 200px;
            border-bottom: 1px solid #000;
            margin-top: 20px;
        }
        .form-container {
            width: 800px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .print-button, .preview-button, .debug-info, .back-button, .form-container {
                display: none;
            }
            .letter-container {
                border: 2px solid #000;
                box-shadow: none;
                width: 100%;
                max-width: 800px;
                margin: 20px auto;
                padding: 40px;
                box-sizing: border-box;
                page-break-after: avoid;
                position: relative;
            }
            .section {
                page-break-inside: avoid;
                max-width: 720px;
                overflow: hidden;
            }
            .section p#previewBody {
                word-wrap: break-word;
                overflow-wrap: break-word;
                max-width: 720px;
                margin: 0;
            }
            .letter-meta p, .section h2 {
                word-wrap: break-word;
                overflow-wrap: break-word;
                max-width: 720px;
            }
            .company-stamp {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                opacity: 0.3;
                pointer-events: none;
            }
            @page {
                margin: 20mm;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' && !empty($debug_messages)): ?>
        <div class="debug-info">
            <h4><?php echo $translations[$lang]['debug_info'] ?? 'Debug Information'; ?></h4>
            <?php foreach ($debug_messages as $msg): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <a href="approved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-secondary back-button">
            <?php echo $translations[$lang]['back'] ?? 'Back'; ?>
        </a>
    <?php else: ?>
        <div class="form-container">
            <h3><?php echo $translations[$lang]['edit_letter'] ?? 'Edit Letter'; ?></h3>
            <form method="POST" id="letterForm">
                <div class="mb-3">
                    <label for="recipient" class="form-label"><?php echo $translations[$lang]['to'] ?? 'To'; ?></label>
                    <input type="text" class="form-control" id="recipient" name="recipient" value="<?php echo htmlspecialchars($preview_recipient); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label"><?php echo $translations[$lang]['subject'] ?? 'Subject'; ?></label>
                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($preview_subject); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="body" class="form-label"><?php echo $translations[$lang]['letter_body_label'] ?? 'Letter Body'; ?></label>
                    <textarea class="form-control" id="body" name="body" rows="6" required><?php echo htmlspecialchars($preview_body); ?></textarea>
                </div>
                <button type="submit" class="preview-button"><?php echo $translations[$lang]['save_changes'] ?? 'Save Changes'; ?></button>
            </form>
        </div>
        <button class="print-button" onclick="window.print()"><?php echo $translations[$lang]['print_letter'] ?? 'Print Letter'; ?></button>
        <div class="letter-container" id="letterPreview">
            <div class="watermark">OFFICIAL</div>
            <div class="company-stamp">
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($company_stamp); ?>" alt="Company Stamp">
            </div>
            <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo of Oromia Regional Government">
            <div class="header">
                <h1><?php echo $translations[$lang]['letter_header'] ?? 'Bulchinsa Mootummaa N/Oromoiyaatti'; ?></h1>
                <h2><?php echo $translations[$lang]['letter_header_sub'] ?? 'Wajjiraa Lafa Bulchinsaa Magaala Mattu'; ?></h2>
            </div>
            <div class="letter-meta">
                <p><strong><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?>:</strong> <?php echo htmlspecialchars($case_data['id']); ?></p>
                <p><strong><?php echo $translations[$lang]['date'] ?? 'Date'; ?>:</strong> <?php echo date('F j, Y'); ?></p>
                <p><strong><?php echo $translations[$lang]['to'] ?? 'To'; ?>:</strong> <span id="previewRecipient"><?php echo htmlspecialchars($preview_recipient); ?></span></p>
            </div>
            <div class="section">
                <h2><?php echo $translations[$lang]['subject'] ?? 'Subject'; ?>: <span id="previewSubject"><?php echo htmlspecialchars($preview_subject); ?></span></h2>
                <p id="previewBody"><?php echo htmlspecialchars($preview_body); ?></p>
            </div>
            <div class="letter-footer">
                <p><strong><?php echo $translations[$lang]['signature'] ?? 'Authorized by'; ?>:</strong></p>
                <div class="signature-line"></div>
                <p><?php echo $translations[$lang]['land_officer'] ?? 'Land Management Officer'; ?></p>
            </div>
        </div>
        <a href="approved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-secondary back-button mt-3">
            <?php echo $translations[$lang]['back'] ?? 'Back'; ?>
        </a>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('letterForm');
            const recipientInput = document.getElementById('recipient');
            const subjectInput = document.getElementById('subject');
            const bodyInput = document.getElementById('body');
            const previewRecipient = document.getElementById('previewRecipient');
            const previewSubject = document.getElementById('previewSubject');
            const previewBody = document.getElementById('previewBody');

            function updatePreview() {
                previewRecipient.textContent = recipientInput.value || '<?php echo htmlspecialchars($recipient_name); ?>';
                previewSubject.textContent = subjectInput.value || '<?php echo htmlspecialchars($letter_subject); ?>';
                previewBody.textContent = bodyInput.value || '<?php echo htmlspecialchars($letter_body); ?>';
            }

            recipientInput.addEventListener('input', updatePreview);
            subjectInput.addEventListener('input', updatePreview);
            bodyInput.addEventListener('input', updatePreview);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>