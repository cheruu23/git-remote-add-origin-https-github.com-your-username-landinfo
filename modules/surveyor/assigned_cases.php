<?php
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isSurveyor()) {
    die("Access denied!");
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch assigned cases with notes where status is 'Assigned'
$sql = "SELECT c.id, c.title, c.status, 
        JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.notes')) AS notes,
        lr.owner_name, lr.first_name, lr.middle_name, lr.block_number 
        FROM cases c 
        LEFT JOIN land_registration lr ON c.land_id = lr.id 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.assigned_to = ? AND c.status = 'Assigned'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$cases = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Assigned cases query failed: " . $conn->error);
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Cases</title>
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
            <h2 class="text-center">Assigned Cases</h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3">No assigned cases found.</p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Owner Name</th>
                                    <th>Block Number</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td><?php echo htmlspecialchars($case['title']); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($case['first_name'] ?? '') . ' ' . ($case['middle_name'] ?? '') . ' ' . ($case['owner_name'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($case['block_number'] ?? 'N/A'); ?></td>
                                        <td class="notes-column"><?php echo htmlspecialchars($case['notes'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['status']); ?></td>
                                        <td>
                                            <a href="view_case.php?id=<?php echo htmlspecialchars($case['id']); ?>" class="btn btn-info">
                                                <i class="fas fa-eye"></i> View Case
                                            </a>
                                            <a href="generate_certificate.php?case_id=<?php echo htmlspecialchars($case['id']); ?>" class="btn btn-primary">
                                                <i class="fas fa-certificate"></i> <?php echo $case['status'] === 'Approved' ? 'View Certificate' : 'Edit & Approve'; ?>
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