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
    die("Dhaabbata database hin dandeenye: Connection error. Please try again later.");
}

$user_id = $_SESSION['user']['id'];
$request_id = (int)($_GET['id'] ?? 0);
$debug_messages = [];
$debug_log = __DIR__ . '/debug.log';

// Fetch support letter request
$request = null;
if ($request_id > 0) {
    try {
        $sql = "SELECT id, requester_name, reason, status, created_at 
                FROM support_letter_requests 
                WHERE id = :request_id AND status IN ('Approved', 'Serviced')";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
        $stmt->execute();
        $request = $stmt->fetch();
        if ($request) {
            $debug_messages[] = "Request ID $request_id found, status=" . $request['status'];
        } else {
            $debug_messages[] = "Request ID $request_id not found or not Approved/Serviced";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request ID $request_id not found or not Approved/Serviced\n", FILE_APPEND);
            die("Request not found or not approved for ID: $request_id");
        }
    } catch (PDOException $e) {
        $debug_messages[] = "Request query failed: " . $e->getMessage();
        error_log("Request query failed: " . $e->getMessage());
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request query failed: " . $e->getMessage() . "\n", FILE_APPEND);
        die("Error fetching request details: Please try again later.");
    }
} else {
    $debug_messages[] = "Invalid or missing request ID";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Invalid or missing request ID\n", FILE_APPEND);
    die("Invalid or missing request ID.");
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Fetch logo from settings table
$navbar_logo = 'assets/images/default_navbar_logo.png';
try {
    $sql = "SELECT setting_value FROM settings WHERE setting_key = 'navbar_logo'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $logo = $stmt->fetch();
    $logo_path = $logo ? $logo['setting_value'] : null;
    $full_logo_path = $logo_path ? realpath(__DIR__ . '/../../' . $logo_path) : null;
    if ($logo_path && file_exists($full_logo_path)) {
        $navbar_logo = $logo_path;
        $debug_messages[] = "Logo found: $navbar_logo";
    } else {
        $debug_messages[] = "Logo not found: path=" . ($logo_path ?? 'none');
    }
} catch (PDOException $e) {
    $debug_messages[] = "Logo query failed: " . $e->getMessage();
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Logo query failed: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Fetch stamp from company_stamps table
$company_stamp = 'assets/images/stamp-placeholder.png';
try {
    $sql = "SELECT image_path FROM company_stamps ORDER BY uploaded_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stamp = $stmt->fetch();
    $stamp_path = $stamp ? $stamp['image_path'] : null;
    $full_stamp_path = $stamp_path ? realpath(__DIR__ . '/../../' . $stamp_path) : null;
    if ($stamp_path && file_exists($full_stamp_path)) {
        $company_stamp = $stamp_path;
        $debug_messages[] = "Stamp found: $company_stamp";
    } else {
        $debug_messages[] = "Stamp not found: path=" . ($stamp_path ?? 'none');
    }
} catch (PDOException $e) {
    $debug_messages[] = "Stamp query failed: " . $e->getMessage();
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Stamp query failed: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - print_support_letter.php: request_id=$request_id, messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Letter #<?php echo htmlspecialchars($request_id); ?></title>
    <style>
        body {
            background-color: rgb(233, 245, 236);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .print-button {
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        .certificate {
            width: 800px;
            border: 2px solid #000;
            background-color: rgb(255, 255, 255);
            padding: 20px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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
            top: 60%;
            left: 70%;
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
        .header h1, .header h2, .header h3 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }
        .header .certificate-number {
            font-size: 16px;
            color: #000;
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
        }
        .section p {
            margin: 5px 0;
            font-size: 14px;
            color: #2c3e50;
        }
        .section h2 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .signatures div {
            text-align: center;
            width: 30%;
        }
        .signatures p {
            margin: 5px 0;
            color: #2c3e50;
        }
        .signatures strong {
            color: #c0392b;
        }
        .signature-line {
            border-top: 1px dashed #000;
            margin-top: 20px;
            padding-top: 5px;
            font-style: italic;
            color: #7f8c8d;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #856404;
        }
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .print-button, .debug-info {
                display: none;
            }
            .certificate {
                border: none;
                box-shadow: none;
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 20px;
                page-break-after: avoid;
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'manager' && !empty($debug_messages)): ?>
        <div class="debug-info">
            <h4>Debug Information</h4>
            <?php foreach ($debug_messages as $msg): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <button class="print-button" onclick="window.print()">Print Letter</button>
    
    <div class="certificate">
        <div class="watermark">COPY</div>
        <div class="company-stamp">
            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($company_stamp); ?>" alt="Company Stamp">
        </div>
        <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo of Oromia Regional Government">
        <div class="header">
            <h1>Bulchinsa Mootummaa Naannoo Oromiyaatti</h1>
            <h2>Support Letter</h2>
            <p class="certificate-number">Request ID: <?php echo htmlspecialchars($request_id); ?></p>
        </div>

        <div class="section">
            <p><strong>To Whom It May Concern,</strong></p>
            <?php
                $approval_date = $request['created_at'] ? date('F j, Y', strtotime($request['created_at'])) : 'N/A';
                $body_text = "This letter is issued by the Oromia Regional Government Land Information Management System (LIMS) to confirm that " . htmlspecialchars($request['requester_name']) . " has been approved for support on " . htmlspecialchars($approval_date) . ".";
            ?>
            <p><?php echo $body_text; ?></p>
        </div>

        <div class="section">
            <h2>Reason</h2>
            <p><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <p>For verification or inquiries, please contact LIMS at lims@oromia.gov or visit our office at Oromia Regional Government, Land Management Department.</p>
        </div>

        <div class="section">
            <p><strong>Sincerely,</strong></p>
            <p>LIMS Record Officer</p>
        </div>

        <div class="signatures">
            <div>
                <p><strong>Prepared By:</strong></p>
                <p><strong>Name:</strong> LIMS Officer</p>
                <p><strong>Gahee Hojii:</strong> Record Officer</p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Approved By:</strong></p>
                <p><strong>Name:</strong> LIMS Manager</p>
                <p><strong>Gahee Hojii:</strong> Manager</p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Authorized By:</strong></p>
                <p><strong>Name:</strong> LIMS Admin</p>
                <p><strong>Gahee Hojii:</strong> Administrator</p>
                <p class="signature-line">[Signature]</p>
            </div>
        </div>
    </div>

    <script>
        function beforePrint() {
            document.body.style.height = 'auto';
        }
        function afterPrint() {
            document.body.style.height = '';
        }
        if (window.matchMedia) {
            window.matchMedia('print').addListener(function(mql) {
                if (mql.matches) {
                    beforePrint();
                } else {
                    afterPrint();
                }
            });
        }
        window.onbeforeprint = beforePrint;
        window.onafterprint = afterPrint;
    </script>
</body>
</html>
<?php ob_end_flush(); ?>