<?php
require '../../includes/init.php';
require '../../includes/languages.php';

redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    $_SESSION['error'] = $translations['en']['access_denied'];
    header("Location: " . BASE_URL . "/public/login.php");
    exit;
}

// Language handling
$lang = $_GET['lang'] ?? 'en';


// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    $_SESSION['error'] = $translations[$lang]['db_error'];
    header("Location: " . BASE_URL . "/public/index.php?lang=$lang");
    exit;
}
$conn->set_charset('utf8mb4');

// Fetch finalized cases
$sql = "SELECT c.id, c.land_id, c.updated_at, lr.owner_name, lr.first_name, lr.middle_name
        FROM cases c
        LEFT JOIN land_registration lr ON c.land_id = lr.id
        WHERE c.status = 'Serviced'
        ORDER BY c.updated_at DESC";
$result = $conn->query($sql);
$finalized_cases = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $finalized_cases[] = $row;
    }
} else {
    error_log("Fetch finalized cases query failed: " . $conn->error);
    $_SESSION['error'] = $translations[$lang]['fetch_error'];
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['finalized_cases_title']; ?> - Land Information System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/styles.css">
    <style>
      
        .table {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .table th {
            background-color:rgb(9, 190, 54);
            color: #fff;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-language {
            margin-left: 10px;
        }
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><?php echo $translations[$lang]['finalized_cases_title']; ?></h1>
                 >
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($finalized_cases)): ?>
                    <p class="text-muted"><?php echo $translations[$lang]['no_finalized_cases']; ?></p>
                <?php else: ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['case_id']; ?></th>
                                <th><?php echo $translations[$lang]['land_id']; ?></th>
                                <th><?php echo $translations[$lang]['owner_name']; ?></th>
                                <th><?php echo $translations[$lang]['finalized_date']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finalized_cases as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['id']); ?></td>
                                    <td><?php echo htmlspecialchars($case['land_id'] ?? 'N/A'); ?></td>
                                    <td><?php 
                                        $owner_name = trim(($case['first_name'] ?? '') . ' ' . ($case['middle_name'] ?? '') . ' ' . ($case['owner_name'] ?? ''));
                                        echo htmlspecialchars($owner_name ?: 'N/A'); 
                                    ?></td>
                                    <td><?php 
                                        echo htmlspecialchars($case['updated_at'] ? date('Y-m-d H:i:s', strtotime($case['updated_at'])) : 'N/A'); 
                                    ?></td>
                                
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
</body>
</html>