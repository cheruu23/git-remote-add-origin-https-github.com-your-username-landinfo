<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'record_officer') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['case_id'])) {
    $case_id = $_GET['case_id'];
    $query = "SELECT rc.*, lh.*, u.username as reported_by_username 
              FROM reported_cases rc 
              JOIN land_holding lh ON rc.land_holding_id = lh.id 
              JOIN users u ON rc.reported_by_user_id = u.id 
              WHERE rc.id = $case_id";
    $result = mysqli_query($conn, $query);
    $case_details = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Case Details</title>
</head>

<body>
    <h1>Case Details</h1>
    <p><strong>Land Holder Name:</strong> <?php echo $case_details['full_name_of_landholder']; ?></p>
    <p><strong>Issue Description:</strong> <?php echo $case_details['issue_description']; ?></p>
    <p><strong>Reported By:</strong> <?php echo $case_details['reported_by_username']; ?></p>
    <p><strong>Reported Date:</strong> <?php echo $case_details['reported_date']; ?></p>
    <p><strong>Status:</strong> <?php echo $case_details['status']; ?></p>
    <a href="view_reported_cases.php">Back to Reported Cases</a>
</body>

</html>