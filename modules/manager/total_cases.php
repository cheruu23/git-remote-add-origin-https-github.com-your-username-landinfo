<?php
require_once '../../includes/init.php';
// Initialize database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die($translations[$lang]['db_connection_failed']);
}
$conn->set_charset('utf8mb4');

// Initialize variables
$cases = [];
$error = null;
$date_filter = isset($date_filter) ? $date_filter : '';
$bind_params = isset($bind_params) ? $bind_params : [];
$param_types = isset($param_types) ? $param_types : '';

// Fetch all cases
try {
    $sql = "SELECT c.id, c.title, c.status, u.username AS reported_by 
            FROM cases c 
            LEFT JOIN users u ON c.reported_by = u.id" . ($date_filter ? $date_filter : '');
    $stmt = $conn->prepare($sql);
    if ($bind_params && $param_types) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Total cases query failed: ' . $e->getMessage(), 'error');
    $error = $translations[$lang]['fetch_error'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['total_cases'] ?? 'Total Cases'; ?> LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
      
        .table {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table th {
            background: #2F4F4F;
            color: #F5F5DC;
        }
        .btn-view {
            background: linear-gradient(90deg, #2F4F4F, #8B4513);
            color: #F5F5DC;
            border: none;
        }
        .btn-view:hover {
            background: linear-gradient(90deg, #8B4513, #2F4F4F);
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <h2><?php echo $translations[$lang]['total_cases'] ?? 'Total Cases'; ?></h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (empty($cases)): ?>
                <p><?php echo $translations[$lang]['no_cases'] ?? 'No recent cases available.'; ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?></th>
                                <th><?php echo $translations[$lang]['case_title'] ?? 'Title'; ?></th>
                                <th><?php echo $translations[$lang]['case_status'] ?? 'Status'; ?></th>
                                <th><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['id']); ?></td>
                                    <td><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td><?php echo htmlspecialchars($case['status']); ?></td>
                                    <td><?php echo htmlspecialchars($case['reported_by'] ?? 'N/A'); ?></td>
                                
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>