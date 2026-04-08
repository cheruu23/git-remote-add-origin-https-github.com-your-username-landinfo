<?php
require '../../includes/auth.php';
require '../../includes/db.php';
redirectIfNotLoggedIn();
include '../../templates/sidebar.php';
if (!isSurveyor()) {
    die("Access denied!");
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$sql = "
    SELECT c.id, c.land_id, lr.parcel_number, c.investigation_status 
    FROM cases c 
    JOIN land_registration lr ON c.land_id = lr.id 
    WHERE c.assigned_to = ? AND c.investigation_status = 'Assigned' 
    AND c.investigation_status NOT IN ('Finalized', 'Provided') 
    AND lr.area IS NOT NULL
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cases = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Cases</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .content { margin-left: 250px; padding: 20px; }
        @media (max-width: 992px) { .content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="content">
        <div class="container mt-3">
            <h2>Assigned Cases</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Case ID</th>
                        <th>Land ID</th>
                        <th>Parcel Number</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="5">No assigned cases found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cases as $case): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($case['id']); ?></td>
                                <td><?php echo htmlspecialchars($case['land_id']); ?></td>
                                <td><?php echo htmlspecialchars($case['parcel_number']); ?></td>
                                <td><?php echo htmlspecialchars($case['investigation_status']); ?></td>
                                <td><a href="<?php echo BASE_URL; ?>/modules/surveyor/view_case.php?id=<?php echo $case['id']; ?>" class="btn btn-primary btn-sm">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>