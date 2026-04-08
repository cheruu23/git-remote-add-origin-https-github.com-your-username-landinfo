<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    die("Access denied!");
}

// Log dashboard access
logAction('manager view case', '  manager view unapproved case ', 'info');

$conn = new mysqli("localhost", "root", "", "landinfo_new");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch unapproved (rejected) cases with owner's name from users
$sql = "SELECT c.id, c.title, c.status, u.username AS reported_by, u.username AS description 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Rejected'";
$result = $conn->query($sql);
if (!$result) {
    error_log("Unapproved cases query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unapproved Cases</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Unapproved Cases</h2>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Reported By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['description'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['reported_by'] ?? 'Unknown'); ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td>
                                <a href="managercase_view.php?case_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No unapproved cases found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div> <!-- Close main-content div from sidebar.php -->
</body>
</html>