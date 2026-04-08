<?php
require '../../includes/init.php';

redirectIfNotLoggedIn();
if (!isManager()) {
    die("Access denied!");
}

// Get database connection
$conn = getDBConnection();

// Fetch approved cases with owner's name
$sql = "SELECT c.id, c.title, c.status, u.username AS reported_by 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Approved'";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Cases</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Approved Cases</h2>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Reported By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['reported_by'] ?? 'Unknown'); ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td>
                                <a href="managercase_view.php?case_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No approved cases found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
// Close the connection
$conn->close();
?>