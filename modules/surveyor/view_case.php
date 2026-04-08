<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
include '../../templates/sidebar.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
if ($role !== 'surveyor') {
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
    die("Database connection failed: " . $e->getMessage());
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Get logo
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';

// Get case ID
$case_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
$debug_log = __DIR__ . '/debug.log';

if ($case_id <= 0) {
    die("Invalid case ID.");
}

// Update viewed status
$update_success = false;
try {
    $stmt_update = $conn->prepare("
        UPDATE cases 
        SET viewed = 1 
        WHERE id = :case_id AND assigned_to = :user_id
    ");
    $stmt_update->bindParam(':case_id', $case_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_update->execute();
    if ($stmt_update->rowCount() > 0) {
        $update_success = true;
    } else {
        file_put_contents($debug_log, "No case updated for Case ID $case_id, user_id: $user_id. Case may not exist or already viewed.\n", FILE_APPEND);
    }
} catch (PDOException $e) {
    file_put_contents($debug_log, "Update viewed status failed for Case ID $case_id: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Fetch case details
$case = null;
try {
    $stmt = $conn->prepare("
        SELECT c.*, 
               JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.notes')) AS notes,
               ur.username AS reported_by_username, ur.role AS reported_by_role,
               ua.username AS assigned_to_username, ua.role AS assigned_to_role,
               lr.id AS land_id, lr.owner_name, lr.first_name, lr.middle_name, lr.zone, lr.village, lr.block_number, lr.owner_photo
        FROM cases c
        INNER JOIN users ur ON c.reported_by = ur.id
        INNER JOIN users ua ON c.assigned_to = ua.id
        INNER JOIN land_registration lr ON c.land_id = lr.id
        WHERE c.id = :case_id AND c.assigned_to = :user_id
    ");
    $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $case = $stmt->fetch();
    if (!$case) {
        file_put_contents($debug_log, "No case found for Case ID $case_id, user_id: $user_id\n", FILE_APPEND);
        die("Case not found or you do not have permission to view it.");
    }
    // Construct full name for owner
    $case['full_name'] = htmlspecialchars(trim(($case['owner_name'] ?? '') . ' ' . ($case['first_name'] ?? '') . ' ' . ($case['middle_name'] ?? '')));
} catch (PDOException $e) {
    file_put_contents($debug_log, "Error fetching case ID $case_id: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Error fetching case details: " . $e->getMessage());
}

// Determine the Proceed URL based on case title
$proceed_url = '#';
$proceed_label = 'Proceed to Approval';
if ($case['land_id']) {
    file_put_contents($debug_log, "Case ID $case_id: case_type=" . ($case['case_type'] ?? 'N/A') . ", title=" . ($case['title'] ?? 'N/A') . "\n", FILE_APPEND);
    if ($case['title'] === 'jijjirra_maqaa') {
        $proceed_url = BASE_URL . '/modules/surveyor/transfer_ownership.php?id=' . htmlspecialchars($case['land_id']) . '&case_id=' . htmlspecialchars($case_id);
    } elseif ($case['title'] === 'qabiyyee_qooduu') {
        $proceed_url = BASE_URL . '/modules/surveyor/split_land.php?id=' . htmlspecialchars($case['land_id']) . '&case_id=' . htmlspecialchars($case_id);
    } else {
        $proceed_url = BASE_URL . '/modules/surveyor/provide_parcel.php?id=' . htmlspecialchars($case['land_id']);
    }
}
file_put_contents($debug_log, "Generated Proceed URL for Case ID $case_id: $proceed_url\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="om">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .case-container {
            width: 800px;
            border: 2px solid #000;
            background-color: #fffdf5;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1, .header h2 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }
        .logo {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 80px;
            height: 80px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }
        .section p {
            font-size: 14px;
            color: #2c3e50;
            margin: 5px 0;
        }
        .btn-primary {
            background: #3498db;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .btn-secondary {
            background: #7f8c8d;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: #6c7a89;
        }
        .btn-disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        .owner-photo img {
            width: 100px;
            height: 100px;
            border-radius: 5px;
        }
        .alert {
            margin-bottom: 20px;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .case-container {
                width: 100%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="case-container">
            <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo">
            <div class="header">
                <h1>Bulchinsa Mootummaa Naannoo Oromiyaa</h1>
                <h2>Oromia Land Administration and Use Bureau</h2>
            </div>
            <?php if ($update_success): ?>
                <div class="alert alert-success">Case viewed successfully.</div>
            <?php else: ?>
                <div class="alert alert-danger">Failed to mark case as viewed. It may already be viewed or not exist.</div>
            <?php endif; ?>
            <div class="section">
                <h3>Case Information</h3>
                <p><strong>Case ID:</strong> <?php echo htmlspecialchars($case['id']); ?></p>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($case['title'] ?? 'N/A'); ?></p>
                <p><strong>Notes:</strong> <?php echo htmlspecialchars($case['notes'] ?? 'N/A'); ?></p>
                <p><strong>Reported By:</strong> <?php echo htmlspecialchars($case['reported_by_username'] ? $case['reported_by_username'] . ' (' . ucfirst($case['reported_by_role']) . ')' : 'N/A'); ?></p>
                <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($case['assigned_to_username'] ? $case['assigned_to_username'] . ' (' . ucfirst($case['assigned_to_role']) . ')' : 'Not assigned'); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($case['status'] ?? 'N/A'); ?></p>
                <p><strong>Investigation Status:</strong> <?php echo htmlspecialchars($case['investigation_status'] ?? 'N/A'); ?></p>
                <p><strong>Case Type:</strong> <?php echo htmlspecialchars($case['case_type'] ?? 'N/A'); ?></p>
                <p><strong>Land ID:</strong> <?php echo htmlspecialchars($case['land_id'] ?? 'N/A'); ?></p>
            </div>
            <?php if ($case['land_id']): ?>
                <div class="section">
                    <h3>Odeeffannoo Abbaa Qabiyyee</h3>
                    <p><strong>Land ID:</strong> <?php echo htmlspecialchars($case['land_id'] ?? 'N/A'); ?></p>
                    <p><strong>Maqaa Guutuu:</strong> <?php echo htmlspecialchars($case['full_name'] ?? 'N/A'); ?></p>
                    <p><strong>Owner Photo:</strong> 
                        <?php 
                        if ($case['owner_photo'] && file_exists(__DIR__ . '/../../' . $case['owner_photo'])) {
                            echo '<img src="' . BASE_URL . '/' . htmlspecialchars($case['owner_photo']) . '" alt="Owner Photo" class="owner-photo">';
                        } else {
                            echo 'No photo available';
                        }
                        ?>
                    </p>
                    <p><strong>Tessoon - Godina:</strong> <?php echo htmlspecialchars($case['zone'] ?? 'N/A'); ?></p>
                    <p><strong>Ganda:</strong> <?php echo htmlspecialchars($case['village'] ?? 'N/A'); ?></p>
                    <p><strong>Lakk. Manaa:</strong> <?php echo htmlspecialchars($case['block_number'] ?? 'N/A'); ?></p>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>/modules/surveyor/assigned_cases.php" class="btn btn-secondary">Back to Received Cases</a>
                <?php if ($case['land_id']): ?>
                    <a href="<?php echo htmlspecialchars($proceed_url); ?>" class="btn btn-primary"><?php echo htmlspecialchars($proceed_label); ?></a>
                <?php else: ?>
                    <a href="#" class="btn btn-primary btn-disabled" onclick="alert('No Land ID associated with this case.'); return false;">Proceed to Approval</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>