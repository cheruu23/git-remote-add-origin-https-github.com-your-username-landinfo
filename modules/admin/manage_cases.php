<?php
ob_start();
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/logger.php';
redirectIfNotLoggedIn();
include '../../templates/sidebar.php';

if (!isAdmin()) {
    logAction('unauthorized_access', 'Attempted access to manage cases', 'warning');
    die("Access denied!");
}

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');
logAction('admin manage user ', 'Admin accessed dashboard', 'info');

// Handle case update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_case'])) {
    $case_id = (int)$_POST['case_id'];
    $status = $conn->real_escape_string($_POST['investigation_status']);
    $surveyor_id = (int)$_POST['assigned_to'];

    $sql = "UPDATE cases SET investigation_status = ?, assigned_to = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $surveyor_id, $case_id);

    if ($stmt->execute()) {
        logAction('update_case', "Updated case ID: $case_id to status: $status, assigned to surveyor ID: $surveyor_id", 'info');
        $success = "Case updated successfully.";
    } else {
        logAction('update_case_failed', "Failed to update case ID: $case_id. Error: " . $stmt->error, 'error');
        $error = "Failed to update case.";
    }
    $stmt->close();
}

// Fetch cases for display
$sql = "SELECT c.id, c.title, c.investigation_status, c.assigned_to, u.username
        FROM cases c
        LEFT JOIN users u ON c.assigned_to = u.id";
$cases = [];
$result = $conn->query($sql);
if ($result) {
    $cases = $result->fetch_all(MYSQLI_ASSOC);
}
$conn->close();

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cases</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        .content.collapsed {
            margin-left: 60px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            background: #fff;
        }
        .table-responsive {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #3498db;
            color: #fff;
            font-weight: 500;
        }
        tr:hover {
            background: #f1f5f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #3498db;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            color: #fff;
        }
        .alert {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 6px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            th, td {
                font-size: 14px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Manage Cases</h2>
            <div class="card">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($case['id']); ?></td>
                                    <td><?php echo htmlspecialchars($case['title'] ?? 'Untitled'); ?></td>
                                    <td><?php echo htmlspecialchars($case['investigation_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($case['username'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                            <select name="investigation_status" class="form-control d-inline w-auto">
                                                <option value="New" <?php echo $case['investigation_status'] === 'New' ? 'selected' : ''; ?>>New</option>
                                                <option value="Assigned" <?php echo $case['investigation_status'] === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                                <option value="Pending" <?php echo $case['investigation_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Approved" <?php echo $case['investigation_status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                            </select>
                                            <input type="number" name="assigned_to" value="<?php echo $case['assigned_to'] ?? ''; ?>" placeholder="Surveyor ID" class="form-control d-inline w-auto">
                                            <button type="submit" name="update_case" class="btn btn-primary btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php ob_end_flush(); ?>
</body>
</html>