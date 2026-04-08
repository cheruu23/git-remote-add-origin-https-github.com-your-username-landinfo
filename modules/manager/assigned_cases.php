<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    die("Access denied!");
}

// Log dashboard access
logAction('manager view case', '  manager view unassigned approved case ', 'info');

$conn = getDBConnection();

// Initialize messages
$success = null;
$error = null;

// Fetch assigned cases with land registration details
$sql = "SELECT c.id, c.title, JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.full_name')) AS full_name, 
        JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.notes')) AS notes, 
        u1.username AS reported_by, u2.username AS assigned_to, c.status,
        lr.owner_name, lr.village, lr.parcel_number, lr.status AS land_status
        FROM cases c 
        LEFT JOIN users u1 ON c.reported_by = u1.id 
        LEFT JOIN users u2 ON c.assigned_to = u2.id 
        LEFT JOIN land_registration lr ON c.land_id = lr.id
        WHERE c.status = 'Assigned' AND c.assigned_to IS NOT NULL";
$stmt = $conn->prepare($sql);
$cases = [];
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
    } else {
        error_log("Assigned cases query execution failed: " . $stmt->error);
        $error = "Failed to fetch assigned cases: " . $stmt->error;
    }
    $stmt->close();
} else {
    error_log("Assigned cases query prepare failed: " . $conn->error);
    $error = "Failed to prepare assigned cases query: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Cases</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    
        h2.text-center {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 20px;
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
            max-width: 300px;
            white-space: normal;
            overflow: visible;
            text-overflow: initial;
        }
        .table td[title] {
            cursor: pointer;
        }
        .alert {
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            h2.text-center {
                font-size: 1.6rem;
            }
            .table {
                font-size: 0.9rem;
            }
            .table td {
                max-width: 200px;
            }
        }
        @media (max-width: 576px) {
            .table td {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Assigned Cases</h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3">No assigned cases found.</p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Case Title</th>
                                    <th>Land Owner</th>
                                    <th>Reported By</th>
                                    <th>Assigned To</th>
                                    <th>Case Status</th>
                                    <th>Village</th>
                                    <th>Parcel Number</th>
                                    <th>Land Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td title="<?php echo htmlspecialchars($case['title']); ?>">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($case['owner_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($case['owner_name'] ?? 'N/A'); ?>
                                        </td>
                                      
                                        <td><?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($case['assigned_to'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($case['status']); ?></td>
                                      
                                        <td title="<?php echo htmlspecialchars($case['village'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($case['village'] ?? 'N/A'); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($case['parcel_number'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($case['parcel_number'] ?? 'N/A'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['land_status'] ?? 'N/A'); ?></td>
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