<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    die("Access denied!");
}

// Log dashboard access
logAction('manager reveiw cases', 'Manager accessed the dashboard', 'info');

// Debug log
$debug_log = __DIR__ . '/debug.log';
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';
// Initialize database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    file_put_contents($debug_log, "Connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    die("Connection failed: " . $conn->connect_error);
}

// Fetch cases for review with reported_by, assigned_to full_name, and evidence count
$stmt = $conn->prepare("
    SELECT 
        c.id, 
        c.title, 
        c.description, 
        c.status, 
        c.investigation_status, 
        c.created_at, 
        u1.full_name AS reported_by,
        u2.full_name AS assigned_to,
        (SELECT COUNT(*) FROM case_evidence ce WHERE ce.case_id = c.id) AS evidence_count
    FROM cases c 
    LEFT JOIN users u1 ON c.reported_by = u1.id 
    LEFT JOIN users u2 ON c.assigned_to = u2.id 
    WHERE c.status IN ('Reported', 'Received')  -- Exclude Approved and Declined
    ORDER BY c.created_at DESC
");
if (!$stmt) {
    file_put_contents($debug_log, "Prepare failed: " . $conn->error . "\n", FILE_APPEND);
    die("Query preparation failed.");
}
$stmt->execute();
$result = $stmt->get_result();
$cases = [];
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ilaalcha Faayilii - Ofiisii Bulchiinsa Lafa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .card {
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background: #fff;
        }

        .card-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            font-weight: 600;
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }

        .card-title {
            margin: 0;
            font-size: 1.5rem;
        }

        .table {
            font-size: 0.9rem;
        }

        .table thead {
            background: #1a3c6d;
            color: #fff;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f3f5;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }

        .no-cases {
            text-align: center;
            color: #6c757d;
            padding: 20px;
        }

        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }

            .card-title {
                font-size: 1.25rem;
            }

            .table {
                font-size: 0.85rem;
            }

            .truncate {
                max-width: 100px;
            }
        }
    </style>
</head>

<body>
    <div class="content">
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Ilaalcha Faayilii</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Akaakuu</th>
                                <th>Ibsa</th>
                                <th>Ibsame</th>
                                <th>Kenname</th>
                                <th>Haala</th>
                                <th>Haala Qorannoo</th>
                                <th>Ragaa</th>
                                <th>Guyyaa</th>
                                <th>Baballi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cases)): ?>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo $case['id']; ?></td>
                                        <td><?php echo htmlspecialchars($case['title']); ?></td>
                                        <td class="truncate"><?php echo htmlspecialchars($case['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($case['assigned_to'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($case['status']); ?></td>
                                        <td><?php echo htmlspecialchars($case['investigation_status'] ?? 'NotStarted'); ?></td>
                                        <td><?php echo $case['evidence_count']; ?></td>
                                        <td><?php echo htmlspecialchars($case['created_at']); ?></td>
                                        <td>
                                            <a href="mirkaneessa_sirrumma_waraqaa_ragaa.php?case_id=<?php echo $case['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Ilaali
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-cases">Faayilii ilaalamu hin jiru.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh the page every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>

</html>