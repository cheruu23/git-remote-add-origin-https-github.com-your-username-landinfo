<?php
ob_start();
require_once '../../includes/init.php';

redirectIfNotLoggedIn();

// Check if user is a surveyor
if (!isSurveyor()) {
    $_SESSION['error'] = $translations[$lang]['access_denied'];
    logAction('unauthorized_access', 'Attempted access to completed cases', 'warning');
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

$user_id = $_SESSION['user']['id'];
$debug_log = __DIR__ . '/debug.log';

// Log viewing completed cases
logAction('view_completed_cases', 'Surveyor viewed completed cases', 'info');

// Fetch completed cases
$sql = "
    SELECT c.id, c.title, c.investigation_status, c.created_at
    FROM cases c
    WHERE c.assigned_to = ? AND c.investigation_status = 'Approved'
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$cases = [];
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
    if (empty($cases)) {
        file_put_contents($debug_log, "No completed cases found for user_id: $user_id\n", FILE_APPEND);
    }
} else {
    logAction('query_failed', 'Failed to fetch completed cases: ' . $stmt->error, 'error');
    file_put_contents($debug_log, "Query failed: " . $stmt->error . "\n", FILE_APPEND);
}
$stmt->close();
$conn->close();

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['completed_cases_title']; ?> - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background: #fff;
        }
        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            font-weight: 600;
            padding: 12px;
        }
        .table td {
            vertical-align: middle;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 10px;
        }
        .full-name-column {
            max-width: 250px;
            white-space: normal;
            word-wrap: break-word;
        }
        .alert {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 6px;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .table {
                font-size: 0.9rem;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .table td {
                max-width: 150px;
            }
            .full-name-column {
                max-width: 200px;
            }
        }
        @media (max-width: 576px) {
            .table td {
                max-width: 100px;
            }
            .full-name-column {
                max-width: 150px;
            }
        }
        @media print {
            .alert, .sidebar {
                display: none;
            }
            .content {
                margin-left: 0;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['completed_cases_title']; ?></h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <div class="alert alert-info"><?php echo $translations[$lang]['no_completed_cases']; ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['case_id']; ?></th>
                                        <th><?php echo $translations[$lang]['case_title']; ?></th>
                                        <th><?php echo $translations[$lang]['status']; ?></th>
                                        <th><?php echo $translations[$lang]['created_at']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($case['id']); ?></td>
                                            <td><?php echo htmlspecialchars($case['title'] ?? 'Untitled'); ?></td>
                                            <td><?php echo htmlspecialchars($case['investigation_status']); ?></td>
                                            <td><?php echo htmlspecialchars($case['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php ob_end_flush(); ?>
</body>
</html>