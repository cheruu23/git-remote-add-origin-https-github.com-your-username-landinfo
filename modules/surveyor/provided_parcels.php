<?php
ob_start();
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

// Check if user is a surveyor
if (!isSurveyor()) {
    $translations = [
        'en' => ['access_denied' => 'Access denied! Only surveyors can view provided parcels.'],
        'om' => ['access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa lafa kennaman ilaalu danda’u.']
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
        'provided_parcels_title' => 'Provided Parcels',
        'no_parcels' => 'No provided parcels found.',
        'land_id' => 'Land ID',
        'owner_name' => 'Owner Name',
        'village' => 'Village',
        'parcel_number' => 'Parcel Number',
        'land_service' => 'Land Service',
        'land_area' => 'Land Area (m²)',
        'lease_date' => 'Lease Date',
        'status' => 'Status',
        'action' => 'Action',
        'total_parcels' => 'Total Provided Parcels',
        'approved_parcels' => 'Approved Parcels',
        'pending_parcels' => 'Pending Parcels',
        'rejected_parcels' => 'Rejected Parcels',
        'commercial_parcels' => 'Commercial Parcels',
        'residential_parcels' => 'Residential Parcels',
        'access_denied' => 'Access denied! Only surveyors can view provided parcels.',
        'view' => 'View'
    ],
    'om' => [
        'provided_parcels_title' => 'Lafa Kennaman',
        'no_parcels' => 'Lafa kennaman hin argamne.',
        'land_id' => 'Lakkoofsa Lafa',
        'owner_name' => 'Maqaa Abbaa Qabeessaa',
        'village' => 'Ganda',
        'parcel_number' => 'Lakkoofsa Paarsela',
        'land_service' => 'Tajaajila Lafa',
        'land_area' => 'Hammamtaa Lafa (m²)',
        'lease_date' => 'Guyyaa Kirayyee',
        'status' => 'Haala',
        'action' => 'Gocha',
        'total_parcels' => 'Lafa Kennaman Waligalaa',
        'approved_parcels' => 'Lafa Mirkanaa’an',
        'pending_parcels' => 'Lafa Hafe',
        'rejected_parcels' => 'Lafa Dida’an',
        'commercial_parcels' => 'Lafa Daldalaa',
        'residential_parcels' => 'Lafa Mana Jireenyaa',
        'access_denied' => 'Seensa dhabuu! Sagantoota qajeelchaa qofa lafa kennaman ilaalu danda’u.',
        'view' => 'Ilaali'
    ]
];

$conn = getDBConnection();

// Initialize messages
$success = null;
$error = null;

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Fetch provided parcels
$sql = "SELECT lr.id, lr.owner_name, lr.village, lr.parcel_number, lr.status, lr.parcel_land_service, 
        lr.parcel_land_area, lr.parcel_lease_date
        FROM land_registration lr
        WHERE lr.has_parcel = 1";
$stmt = $conn->prepare($sql);
$parcels = [];
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $parcels[] = $row;
        }
    } else {
        error_log("Provided parcels query execution failed: " . $stmt->error);
        $error = $translations[$lang]['error_fetch'] ?? "Failed to fetch provided parcels: " . $stmt->error;
    }
    $stmt->close();
} else {
    error_log("Provided parcels query prepare failed: " . $conn->error);
    $error = $translations[$lang]['error_prepare'] ?? "Failed to prepare provided parcels query: " . $conn->error;
}

// Fetch dashboard metrics
$total_parcels = count($parcels);
$status_counts = [
    'Approved' => 0,
    'Pending' => 0,
    'Rejected' => 0
];
$service_counts = [
    'lafa daldalaa' => 0,
    'lafa mana jireenyaa' => 0,
    'Other' => 0
];

foreach ($parcels as $parcel) {
    if (isset($status_counts[$parcel['status']])) {
        $status_counts[$parcel['status']]++;
    }
    $service = $parcel['parcel_land_service'] ?? 'Other';
    if (isset($service_counts[$service])) {
        $service_counts[$service]++;
    } else {
        $service_counts['Other']++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['provided_parcels_title']; ?> - LIMS</title>
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
        .dashboard-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            flex: 1;
            min-width: 200px;
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .dashboard-card h4 {
            font-size: 1.2rem;
            color: #1e40af;
            margin-bottom: 10px;
        }
        .dashboard-card p {
            font-size: 2rem;
            font-weight: 600;
            color: #007bff;
            margin: 0;
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
            .dashboard-cards {
                flex-direction: column;
            }
            .btn-primary {
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
            <h2 class="text-center"><?php echo $translations[$lang]['provided_parcels_title']; ?></h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['total_parcels']; ?></h4>
                    <p><?php echo $total_parcels; ?></p>
                </div>
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['approved_parcels']; ?></h4>
                    <p><?php echo $status_counts['Approved']; ?></p>
                </div>
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['pending_parcels']; ?></h4>
                    <p><?php echo $status_counts['Pending']; ?></p>
                </div>
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['rejected_parcels']; ?></h4>
                    <p><?php echo $status_counts['Rejected']; ?></p>
                </div>
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['commercial_parcels']; ?></h4>
                    <p><?php echo $service_counts['lafa daldalaa']; ?></p>
                </div>
                <div class="dashboard-card">
                    <h4><?php echo $translations[$lang]['residential_parcels']; ?></h4>
                    <p><?php echo $service_counts['lafa mana jireenyaa']; ?></p>
                </div>
            </div>

            <!-- Parcels Table -->
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($parcels)): ?>
                        <p class="text-center text-muted m-3"><?php echo $translations[$lang]['no_parcels']; ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['land_id']; ?></th>
                                        <th><?php echo $translations[$lang]['owner_name']; ?></th>
                                        <th><?php echo $translations[$lang]['village']; ?></th>
                                        <th><?php echo $translations[$lang]['parcel_number']; ?></th>
                                        <th><?php echo $translations[$lang]['land_service']; ?></th>
                                        <th><?php echo $translations[$lang]['land_area']; ?></th>
                                        <th><?php echo $translations[$lang]['lease_date']; ?></th>
                                        <th><?php echo $translations[$lang]['status']; ?></th>
                                        <th><?php echo $translations[$lang]['action']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parcels as $parcel): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($parcel['id']); ?></td>
                                            <td class="full-name-column" title="<?php echo htmlspecialchars($parcel['owner_name'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($parcel['owner_name'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="full-name-column" title="<?php echo htmlspecialchars($parcel['village'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($parcel['village'] ?? 'N/A'); ?>
                                            </td>
                                            <td title="<?php echo htmlspecialchars($parcel['parcel_number'] ?? 'N/A'); ?>">
                                                <?php echo htmlspecialchars($parcel['parcel_number'] ?? 'N/A'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($parcel['parcel_land_service'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['parcel_land_area'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['parcel_lease_date'] ? date('m/d/Y', strtotime($parcel['parcel_lease_date'])) : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['status'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>/modules/surveyor/view_parcel.php?id=<?php echo htmlspecialchars($parcel['id']); ?>&lang=<?php echo $lang; ?>" 
                                                   class="btn btn-primary">
                                                    <i class="fas fa-eye"></i> <?php echo $translations[$lang]['view']; ?>
                                                </a>
                                            </td>
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
</body>
</html>