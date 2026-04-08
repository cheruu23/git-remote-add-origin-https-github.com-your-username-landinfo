<?php
ob_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/config.php';
redirectIfNotLoggedIn();

// Check if user is a surveyor
if (!isSurveyor()) {
    $translations = [
        'en' => ['access_denied' => 'Access denied! Only surveyors can view pending cases.'],
        'om' => ['access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa keesoo hafe ilaalu danda’u.']
    ];
    $lang = $_GET['lang'] ?? 'om';
    $_SESSION['error'] = $translations[$lang]['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'om';
$translations = [
    'en' => [
        'pending_cases_title' => 'Pending Cases',
        'no_pending_cases' => 'No pending cases found.',
        'case_id' => 'Case ID',
        'case_title' => 'Title',
        'case_description' => 'Description',
        'case_type' => 'Case Type',
        'land_id' => 'Land ID',
        'created_at' => 'Created At',
        'investigation_status' => 'Investigation Status',
        'access_denied' => 'Access denied! Only surveyors can view pending cases.'
    ],
    'om' => [
        'pending_cases_title' => 'Keesoowwan Hafe',
        'no_pending_cases' => 'Keesoowwan hafe hin argamne.',
        'case_id' => 'Lakkoofsa Keesii',
        'case_title' => 'Mata-duree',
        'case_description' => 'Ibsa',
        'case_type' => 'Gosa Keesii',
        'land_id' => 'Lakkoofsa Lafa',
        'created_at' => 'Yeroo Uumamaa',
        'investigation_status' => 'Haala Qorannoo',
        'access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa keesoo hafe ilaalu danda’u.'
    ]
];

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

// Fetch pending cases
$cases = [];
try {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT id, title, description, case_type, land_id, created_at, investigation_status 
            FROM cases 
            WHERE investigation_status = 'InProgress' AND assigned_to = ? 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('fetch_cases_failed', 'Failed to fetch pending cases: ' . $e->getMessage(), 'error');
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['pending_cases_title']; ?> - LIMS</title>
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
    <?php include '../../templates/sidebar.php'; ?>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['pending_cases_title']; ?></h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <div class="alert alert-info"><?php echo $translations[$lang]['no_pending_cases']; ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['case_id']; ?></th>
                                        <th><?php echo $translations[$lang]['case_title']; ?></th>
                                        <th><?php echo $translations[$lang]['case_description']; ?></th>
                                        <th><?php echo $translations[$lang]['case_type']; ?></th>
                                        <th><?php echo $translations[$lang]['land_id']; ?></th>
                                        <th><?php echo $translations[$lang]['created_at']; ?></th>
                                        <th><?php echo $translations[$lang]['investigation_status']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cases as $case): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($case['id']); ?></td>
                                            <td><?php echo htmlspecialchars($case['title'] ?? 'Untitled'); ?></td>
                                            <td class="full-name-column"><?php echo htmlspecialchars(substr($case['description'], 0, 100)) . (strlen($case['description']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo htmlspecialchars($case['case_type']); ?></td>
                                            <td><?php echo htmlspecialchars($case['land_id'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($case['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($case['investigation_status']); ?></td>
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