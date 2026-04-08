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

// Fetch case details
$case_data = null;
if ($case_id > 0) {
    try {
        $sql = "SELECT c.id, c.title, c.land_id, lr.village 
                FROM cases c 
                LEFT JOIN land_registration lr ON c.land_id = lr.id 
                WHERE c.id = :case_id AND c.status = 'Approved'";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
        $stmt->execute();
        $case_data = $stmt->fetch();
        if (!$case_data) {
            $debug_messages[] = "Case ID $case_id not found or not approved";
            $error_message = $translations[$lang]['case_not_found'] ?? "Case not found or not approved for ID: $case_id";
        } else {
            $debug_messages[] = "Case ID $case_id found";
        }
    } catch (PDOException $e) {
        $debug_messages[] = "Case query failed: " . $e->getMessage();
        error_log("Case query failed: " . $e->getMessage());
        $error_message = $translations[$lang]['query_error'] ?? "Error fetching case details: Please try again later.";
    }
} else {
    $debug_messages[] = "Invalid or missing case ID";
    $error_message = $translations[$lang]['invalid_case_id'] ?? "Invalid or missing case ID.";
}

// Fetch evidence
$documents = [];
if ($case_data) {
    try {
        $sql = "SELECT file_path, evidence_type 
                FROM case_evidence 
                WHERE case_id = :case_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
        $stmt->execute();
        $documents = $stmt->fetchAll();
        $debug_messages[] = "Fetched " . count($documents) . " documents for case ID $case_id";
        // Validate file paths
        foreach ($documents as $index => $doc) {
            $file_path = trim($doc['file_path']);
            if (!preg_match('/^[a-zA-Z0-9\/_\-\.]+$/', $file_path) || 
                str_contains($file_path, '..') || 
                !file_exists(__DIR__ . '/../../' . $file_path)) {
                $debug_messages[] = "Invalid or missing file: $file_path";
                unset($documents[$index]);
            }
        }
        $documents = array_values($documents); // Reindex
    } catch (PDOException $e) {
        $debug_messages[] = "Evidence query failed: " . $e->getMessage();
        error_log("Evidence query failed: " . $e->getMessage());
        $error_message = $translations[$lang]['query_error'] ?? "Error fetching documents: Please try again later.";
    }
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - document.php: case_id=$case_id, messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['view_documents'] ?? 'View Landowner Documents'; ?> #<?php echo htmlspecialchars($case_id); ?> - LIMS</title>
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
        .document-container {
            width: 800px;
            border: 2px solid #000;
            background-color: rgb(255, 255, 255);
            padding: 20px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            page-break-after: always;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }
        .document-item {
            margin-bottom: 20px;
        }
        .document-item img, .document-item embed {
            max-width: 100%;
            height: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
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
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .print-button, .debug-info, .back-button {
                display: none;
            }
            .document-container {
                border: none;
                box-shadow: none;
                width: 100%;
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
        <button class="print-button" onclick="window.print()"><?php echo $translations[$lang]['print_documents'] ?? 'Print Documents'; ?></button>
        <div class="document-container">
            <div class="header">
                <h1><?php echo $translations[$lang]['landowner_documents'] ?? 'Landowner Documents'; ?> - Case ID: <?php echo htmlspecialchars($case_id); ?></h1>
            </div>
            <div class="section">
                <p><strong><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?>:</strong> <?php echo htmlspecialchars($case_data['id']); ?></p>
                <p><strong><?php echo $translations[$lang]['case_title'] ?? 'Title'; ?>:</strong> <?php echo htmlspecialchars($case_data['title'] ?? 'Untitled'); ?></p>
                <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> <?php echo htmlspecialchars($case_data['village'] ?? 'N/A'); ?></p>
            </div>
            <div class="section">
                <h2><?php echo $translations[$lang]['documents'] ?? 'Documents'; ?></h2>
                <?php if (empty($documents)): ?>
                    <p><?php echo $translations[$lang]['no_documents'] ?? 'No documents found for this case.'; ?></p>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <p><strong><?php echo $translations[$lang]['evidence_type_' . $doc['evidence_type']] ?? htmlspecialchars($doc['evidence_type']); ?>:</strong></p>
                            <?php
                            $file_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            $file_url = BASE_URL . '/' . htmlspecialchars($doc['file_path']) . '?v=' . time();
                            ?>
                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars(basename($doc['file_path'])); ?>">
                            <?php elseif ($file_ext === 'pdf'): ?>
                                <embed src="<?php echo $file_url; ?>" type="application/pdf" width="100%" height="600px">
                            <?php else: ?>
                                <p><a href="<?php echo $file_url; ?>" target="_blank"><?php echo htmlspecialchars(basename($doc['file_path'])); ?></a></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="approved_cases.php?lang=<?php echo $lang; ?>" class="btn btn-secondary back-button mt-3">
            <?php echo $translations[$lang]['back'] ?? 'Back'; ?>
        </a>
    <?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>