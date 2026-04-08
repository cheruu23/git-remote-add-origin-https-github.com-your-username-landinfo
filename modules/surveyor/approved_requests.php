<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isSurveyor()) {
    die("Access denied!");
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch approved split requests assigned to the surveyor
$sql = "SELECT sr.id, sr.original_land_id, sr.case_id, sr.status, 
        sr.created_at, sr.updated_at,
        lr.owner_name, lr.first_name, lr.middle_name, lr.block_number 
        FROM split_requests sr 
        LEFT JOIN land_registration lr ON sr.original_land_id = lr.id 
        WHERE sr.surveyor_id = ? AND sr.status = 'Approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$requests = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $requests = $result->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Approved split requests query failed: " . $conn->error);
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Split Requests</title>
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
        }
        .btn-info:hover {
            background: linear-gradient(135deg, #117a8b, #0c5460);
        }
        .notes-column {
            max-width: 250px;
            white-space: normal;
            word-wrap: break-word;
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
            .notes-column {
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
            .notes-column {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Approved Split Requests</h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($requests)): ?>
                        <p class="text-center text-muted m-3">No approved split requests found.</p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Case ID</th>
                                    <th>Owner Name</th>
                                    <th>Block Number</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['case_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($request['first_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['owner_name'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($request['block_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($request['status']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($request['created_at']))); ?></td>
                                     
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