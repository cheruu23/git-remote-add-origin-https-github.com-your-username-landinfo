<?php
ob_start();
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

// Check if user is a surveyor
if (!isSurveyor()) {
    $translations = [
        'en' => ['access_denied' => 'Access denied! Only surveyors can view ownership changes.'],
        'om' => ['access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa jijjiirra abbaa qabeenyaa ilaalu danda’u.']
    ];
    $lang = $_GET['lang'] ?? 'en';
    $_SESSION['error'] = $translations[$lang]['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    ob_end_flush();
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'en';
$translations = [
    'en' => [
        'ownership_changes_title' => 'Ownership Changes',
        'no_changes_found' => 'No ownership changes found.',
        'id' => 'ID',
        'land_id' => 'Land ID',
        'old_owner_name' => 'Old Owner Name',
        'new_owner_name' => 'New Owner Name',
        'old_gender' => 'Old Gender',
        'new_gender' => 'New Gender',
        'changed_by' => 'Changed By',
        'change_date' => 'Change Date',
        'access_denied' => 'Access denied! Only surveyors can view ownership changes.'
    ],
    'om' => [
        'ownership_changes_title' => 'Jijjiirra Abbaa Qabeenyaa',
        'no_changes_found' => 'Jijjiirra abbaa qabeenyaa hin argamne.',
        'id' => 'Lakkoofsa',
        'land_id' => 'Lakkoofsa Lafa',
        'old_owner_name' => 'Maqaa Abbaa Qabeessaa Durii',
        'new_owner_name' => 'Maqaa Abbaa Qabeessaa Haaraa',
        'old_gender' => 'Saala Durii',
        'new_gender' => 'Saala Haaraa',
        'changed_by' => 'Kan Jijjiire',
        'change_date' => 'Guyyaa Jijjiirama',
        'access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa jijjiirra abbaa qabeenyaa ilaalu danda’u.'
    ]
];

// Database connection
$conn = getDBConnection();
$debug_log = __DIR__ . '/debug.log';

// Log viewing ownership changes
file_put_contents($debug_log, "Surveyor viewed ownership changes at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Fetch ownership changes
$sql = "
    SELECT ot.id, ot.land_id, ot.old_owner_name, ot.new_owner_name, ot.old_gender, ot.new_gender, ot.change_date, u.username
    FROM ownership_transfers ot
    LEFT JOIN users u ON ot.changed_by = u.id
    ORDER BY ot.change_date DESC
";
$stmt = $conn->prepare($sql);
$changes = [];
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $changes[] = $row;
        }
        if (empty($changes)) {
            file_put_contents($debug_log, "No ownership changes found\n", FILE_APPEND);
        }
    } else {
        file_put_contents($debug_log, "Query failed: " . $stmt->error . "\n", FILE_APPEND);
    }
    $stmt->close();
} else {
    file_put_contents($debug_log, "Query prepare failed: " . $conn->error . "\n", FILE_APPEND);
}
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
    <title><?php echo $translations[$lang]['ownership_changes_title']; ?> - LIMS</title>
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
            <h2 class="text-center"><?php echo $translations[$lang]['ownership_changes_title']; ?></h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($changes)): ?>
                        <div class="alert alert-info"><?php echo $translations[$lang]['no_changes_found']; ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['id']; ?></th>
                                        <th><?php echo $translations[$lang]['land_id']; ?></th>
                                        <th><?php echo $translations[$lang]['old_owner_name']; ?></th>
                                        <th><?php echo $translations[$lang]['new_owner_name']; ?></th>
                                        <th><?php echo $translations[$lang]['gender']; ?></th>
                                        <th><?php echo $translations[$lang]['gender']; ?></th>
                                        <th><?php echo $translations[$lang]['changed_by']; ?></th>
                                        <th><?php echo $translations[$lang]['change_date']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($changes as $change): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($change['id']); ?></td>
                                            <td><?php echo htmlspecialchars($change['land_id'] ?? 'N/A'); ?></td>
                                            <td class="full-name-column"><?php echo htmlspecialchars($change['old_owner_name'] ?? 'N/A'); ?></td>
                                            <td class="full-name-column"><?php echo htmlspecialchars($change['new_owner_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($change['old_gender'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($change['new_gender'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($change['username'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($change['change_date'] ? date('m/d/Y H:i:s', strtotime($change['change_date'])) : 'N/A'); ?></td>
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