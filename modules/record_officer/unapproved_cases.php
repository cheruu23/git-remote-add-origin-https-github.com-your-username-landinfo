<?php
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();

if (!isRecordOfficer()) {
    die("Access denied!");
}

$query = "SELECT * FROM cases WHERE status = 'Pending'";
$result = query($query);
?>

<!DOCTYPE html>
<html lang="en">
<?php include '../../templates/header.php'; ?>

<body>
    <div class="container mt-4">
        <h2>Unapproved Cases</h2>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Case ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Reported By</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= $row['title']; ?></td>
                        <td><?= $row['description']; ?></td>
                        <td><?= $row['reported_by']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>

</html>