<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
redirectIfNotLoggedIn();
if (!function_exists('isSurveyor') || !isSurveyor()) {
    die("Access denied!");
}

$conn = getDBConnection();
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($case_id <= 0) {
    $_SESSION['error_message'] = "Invalid case ID.";
    header("Location: view_case.php?id=$case_id");
    exit;
}

// Fetch case details
$sql = "SELECT c.*, lr.owner_name, lr.first_name, lr.middle_name
        FROM cases c
        LEFT JOIN land_registration lr ON c.land_id = lr.id
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $case_id);
$stmt->execute();
$case = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$case) {
    $_SESSION['error_message'] = "Case not found.";
    header("Location: view_case.php?id=$case_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update case status and assign to manager
    $manager_id = 1; // Replace with actual manager user_id
    $case_update_sql = "UPDATE cases SET investigation_status = 'Completed', assigned_to = ? WHERE id = ?";
    $case_update_stmt = $conn->prepare($case_update_sql);
    $case_update_stmt->bind_param('ii', $manager_id, $case_id);

    try {
        if ($case_update_stmt->execute()) {
            $_SESSION['success_message'] = "Case investigation completed and sent to manager.";
            header("Location: view_case.php?id=$case_id");
            exit;
        } else {
            throw new Exception("Failed to update case status.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . htmlspecialchars($e->getMessage());
        header("Location: generic_approval.php?id=$case_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Case</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-section h2 {
            background-color: #007bff;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>
    <div class="form-container">
        <h1 class="text-center mb-4" style="color: #007bff;">Approve Case: <?php echo htmlspecialchars($case['title']); ?></h1>
        <div class="form-section">
            <h2>Case Details</h2>
            <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($case['owner_name'] . ' ' . $case['first_name'] . ' ' . $case['middle_name']); ?></p>
            <p><strong>Title:</strong> <?php echo htmlspecialchars($case['title']); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars(json_decode($case['description'])->notes ?? 'No notes'); ?></p>
        </div>
        <form action="generic_approval.php?id=<?php echo $case_id; ?>" method="post">
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">Complete Investigation and Send to Manager</button>
            </div>
        </form>
    </div>
</body>
</html>
