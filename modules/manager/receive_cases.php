<?php
ob_start();
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
restrictAccess(['manager'], 'receive cases');

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

// Log viewing receive cases
logAction('view_receive_cases', 'Manager viewed assigned cases', 'info');

// Fetch cases assigned to the logged-in manager
$user_id = $_SESSION['user']['id'];
$stmt = $conn->prepare("
    SELECT c.*, u.username AS reported_by_username
    FROM cases c
    LEFT JOIN users u ON c.reported_by = u.id
    WHERE c.received_by = ?
");
if (!$stmt) {
    logAction('query_prepare_failed', 'Failed to prepare query for receive cases: ' . $conn->error, 'error');
    die("Query preparation error.");
}
$stmt->bind_param("i", $user_id);
$cases = [];
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
    
    // Mark cases as viewed
    $update_stmt = $conn->prepare("UPDATE cases SET viewed = 1 WHERE received_by = ? AND id = ?");
    if ($update_stmt) {
        foreach ($cases as $case) {
            if (!$case['viewed']) {
                $update_stmt->bind_param("ii", $user_id, $case['id']);
                $update_stmt->execute();
            }
        }
        $update_stmt->close();
    }
} else {
    logAction('query_failed', 'Failed to fetch receive cases: ' . $stmt->error, 'error');
}
$stmt->close();
$conn->close();

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
?>

<!DOCTYPE html>
<html lang="om">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager - Odeeffannoo Gaaffatame Ilaaluu</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        
        .content.collapsed {
            margin-left: 60px;
        }
        h1.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
        }
        .case-container {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background: #fff;
            max-width: 900px;
            margin: 20px auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .case-section {
            margin-bottom: 20px;
        }
        .case-section h2 {
            background: #3498db;
            color: #fff;
            padding: 8px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
        }
        .case-section p {
            margin: 5px 0;
            font-size: 14px;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-radius: 6px;
            padding: 10px;
            margin: 20px auto;
            max-width: 900px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            .case-container {
                padding: 15px;
                margin: 15px;
            }
            h1.text-center {
                font-size: 1.8rem;
            }
            .case-section h2 {
                font-size: 14px;
            }
            .case-section p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-5">
            <h1 class="text-center mb-4">Manager - Odeeffannoo Gaaffatame Ilaaluu</h1>
            <?php if (empty($cases)): ?>
                <p class="alert alert-info text-center">Gaaffii manaajeraaf ramadame hin jiru.</p>
            <?php else: ?>
                <?php foreach ($cases as $case): ?>
                    <div class="case-container">
                        <div class="case-section">
                            <h2>1. Odeeffannoo Gaaffii</h2>
                            <p><strong>ID Gaaffii:</strong> <?php echo htmlspecialchars($case['id']); ?></p>
                            <p><strong>Maqaa Gaaffii:</strong> <?php echo htmlspecialchars($case['title'] ?? 'N/A'); ?></p>
                            <p><strong>Ibsa:</strong> <?php echo htmlspecialchars($case['description'] ?? 'N/A'); ?></p>
                            <p><strong>Gosa Gaaffii:</strong> <?php echo htmlspecialchars($case['case_type'] ?? 'N/A'); ?></p>
                        </div>

                        <div class="case-section">
                            <h2>2. Saala Gaaffii</h2>
                            <p><strong>Saala:</strong> <?php echo htmlspecialchars($case['status'] ?? 'N/A'); ?></p>
                            <p><strong>Saala Qorannoo:</strong> <?php echo htmlspecialchars($case['investigation_status'] ?? 'N/A'); ?></p>
                            <p><strong>Raga Galmee:</strong> <?php echo htmlspecialchars($case['report_submitted'] ? 'Ee' : 'Lakki'); ?></p>
                            <p><strong>Ilaalame:</strong> <?php echo htmlspecialchars($case['viewed'] ? 'Ee' : 'Lakki'); ?></p>
                        </div>

                        <div class="case-section">
                            <h2>3. Odeeffannoo Dabalataa</h2>
                            <p><strong>Kan Galmeesse:</strong> <?php echo htmlspecialchars($case['reported_by_username'] ?? 'N/A'); ?></p>
                            <p><strong>Guyyaa Galmee:</strong> <?php echo htmlspecialchars($case['created_at'] ?? 'N/A'); ?></p>
                            <p><strong>ID Qilleensa:</strong> <?php echo htmlspecialchars($case['land_id'] ?? 'N/A'); ?></p>
                            <p><strong>Kan Ramadame:</strong> <?php echo htmlspecialchars($case['assigned_to'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php ob_end_flush(); ?>
</body>
</html>