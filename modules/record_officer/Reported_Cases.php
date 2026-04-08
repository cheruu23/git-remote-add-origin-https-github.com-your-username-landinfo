<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    die("Access denied!");
}

$conn = getDBConnection();

// Assume $date_filter, $bind_params, and $param_types are defined (e.g., in init.php or via GET/POST)
// If not using date filters, set defaults
$date_filter = isset($date_filter) ? $date_filter : "";
$bind_params = isset($bind_params) ? $bind_params : [];
$param_types = isset($param_types) ? $param_types : "";

// Fetch status counts for cards
$statuses = ['Reported', 'Approved'];
$status_counts = ['reported' => 0, 'approved' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM cases c LEFT JOIN users u ON c.reported_by = u.id WHERE c.status = ? AND u.role = 'record_officer'" . $date_filter;
    $stmt = $conn->prepare($sql);
    foreach ($statuses as $status) {
        $params = array_merge([$status], $bind_params);
        $types = 's' . $param_types;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $status_counts[strtolower($status)] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Status count query failed: ' . $e->getMessage(), 'error');
}

// Query reported cases (aligned with count query)
$sql = "SELECT c.id, c.title, JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.full_name')) AS description,
        c.created_at, c.case_type, lr.owner_name, lr.block_number
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        LEFT JOIN land_registration lr ON c.land_id = lr.id
        WHERE c.status = 'Reported' AND u.role = 'record_officer'" . $date_filter;
$stmt = $conn->prepare($sql);
if (!empty($bind_params)) {
    $stmt->bind_param($param_types, ...$bind_params);
}
$cases = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Minimize description: truncate to 50 characters
        $row['description'] = strlen($row['description']) > 50 ? substr($row['description'], 0, 50) . '...' : $row['description'];
        $cases[] = $row;
    }
} else {
    error_log("Reported cases query failed: " . $conn->error);
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Cases</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1a3c6d;
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
        }
        .table td {
            vertical-align: middle;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
            border: none;
            border-radius: 6px;
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #117a8b, #0c5460);
        }
        .status-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .status-card {
            flex: 1;
            padding: 20px;
            border-radius: 8px;
            color: #fff;
            text-align: center;
        }
        .status-card.reported {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        .status-card.approved {
            background: linear-gradient(135deg, #4caf50, #388e3c);
        }
        .status-card h3 {
            margin: 0;
            font-size: 1.5rem;
        }
        .status-card p {
            margin: 5px 0 0;
            font-size: 1rem;
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
            }
            .table td {
                max-width: 150px;
            }
            .status-cards {
                flex-direction: column;
            }
        }
        @media (max-width: 576px) {
            .table td {
                max-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Reported Cases</h2>
            <div class="status-cards">
                <div class="status-card reported">
                    <h3><?php echo htmlspecialchars($status_counts['reported']); ?></h3>
                    <p>Reported Cases</p>
                </div>
                <div class="status-card approved">
                    <h3><?php echo htmlspecialchars($status_counts['approved']); ?></h3>
                    <p>Approved Cases</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3">No reported cases found.</p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Owner Name</th>
                                    <th>Block Number</th>
                                    <th>Case Type</th>
                                    <th>Description</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td><?php echo htmlspecialchars($case['title']); ?></td>
                                        <td><?php echo htmlspecialchars($case['owner_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['block_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['case_type']); ?></td>
                                        <td><?php echo htmlspecialchars($case['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($case['created_at']))); ?></td>
                                     
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