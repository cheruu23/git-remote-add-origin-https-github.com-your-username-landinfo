<?php
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

if (!isSurveyor()) {
    die("Access denied!");
}

// Database Connection
$conn = new mysqli("localhost", "root", "", "landinfo");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the logged-in surveyor's ID
$surveyor_id = $_SESSION['user_id'];

// Fetch cases assigned to this surveyor
$sql = "SELECT id, land_holding_id, issue_description, status FROM reported_cases WHERE assigned_to_user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $surveyor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<?php include '../../templates/sidebar.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveyor Assigned Cases</title>
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Assigned Cases</h1>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Land Holding ID</th>
                    <th>Issue Description</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']); ?></td>
                        <td><?= htmlspecialchars($row['land_holding_id']); ?></td>
                        <td><?= htmlspecialchars($row['issue_description']); ?></td>
                        <td>
                            <span class="badge badge-<?= $row['status'] === 'Pending' ? 'warning' : 'success' ?>">
                                <?= htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Pending') : ?>
                                <a href="complete_case.php?id=<?= $row['id']; ?>" class="btn btn-success btn-sm">
                                    Submit Report
                                </a>
                            <?php else : ?>
                                <span class="text-muted">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>