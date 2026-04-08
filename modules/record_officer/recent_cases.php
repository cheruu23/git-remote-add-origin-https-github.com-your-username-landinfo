<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    die("Access denied!");
}

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Database connection
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch recent cases
$sql = "SELECT c.id, c.title, c.status, c.case_type, c.land_id, 
        u.username AS reported_by, 
        JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.full_name')) AS full_name,
        c.created_at
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        LEFT JOIN land_registration lr ON c.land_id = lr.id 
        WHERE c.created_at >= NOW() - INTERVAL 30 DAY
        ORDER BY c.created_at DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$cases = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Recent cases query failed: " . $conn->error);
}
$stmt->close();
$conn->close();

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['title']; ?>recent_cases</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" async>
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
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 6px 12px;
            margin-right: 5px;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            padding: 6px 12px;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #117a8b, #0c5460);
        }
        .full-name-column {
            max-width: 250px;
            white-space: normal;
            word-wrap: break-word;
        }
        .language-toggle {
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .language-toggle a {
            color: #007bff;
            text-decoration: none;
            margin-right: 10px;
        }
        .language-toggle a:hover {
            text-decoration: underline;
        }
        .back-link {
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
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
            .btn-primary, .btn-info {
                font-size: 0.8rem;
                padding: 4px 8px;
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
            .language-toggle, .back-link {
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
            <div class="back-link">
                <a href="<?php echo BASE_URL; ?>/modules/surveyor/surveyor_dashboard.php"><?php echo $translations[$lang]['back_to_dashboard']; ?></a>
            </div>
            <h2 class="text-center"><?php echo $translations[$lang]['header']; ?></h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3"><?php echo $translations[$lang]['no_cases']; ?></p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id']; ?></th>
                                    <th><?php echo $translations[$lang]['title_col']; ?></th>
                                    <th><?php echo $translations[$lang]['case_type']; ?></th>
                                    <th><?php echo $translations[$lang]['status']; ?></th>
                                    <th><?php echo $translations[$lang]['land_id']; ?></th>
                                    <th><?php echo $translations[$lang]['reported_by']; ?></th>
                                    <th><?php echo $translations[$lang]['full_name']; ?></th>
                                    <th><?php echo $translations[$lang]['created_at']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['case_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['status'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($case['land_id']): ?>
                                                <a href="<?php echo BASE_URL; ?>/modules/record_officer/view_parcel.php?id=<?php echo htmlspecialchars($case['land_id']); ?>&lang=<?php echo $lang; ?>" class="btn btn-info">
                                                    <i class="fas fa-eye"></i> <?php echo $translations[$lang]['view_parcel']; ?>
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['reported_by'] ?? 'N/A'); ?></td>
                                        <td class="full-name-column"><?php echo htmlspecialchars($case['full_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['created_at'] ?? 'N/A'); ?></td>
                                        <td>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>