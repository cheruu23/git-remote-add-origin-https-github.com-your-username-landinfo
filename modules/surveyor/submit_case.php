<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "landinfo";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect form data
$land_holding_id = $_POST['land_holding_id'];
$issue_description = $_POST['issue_description'];
$assigned_to_user_id = $_POST['assigned_to_user_id']; // Assigned user (Manager or Surveyor)
$reported_by_user_id = 1; // Replace with the logged-in Record Officer's ID (from session)

// Insert data into reported_cases table
$sql = "INSERT INTO reported_cases (land_holding_id, reported_by_user_id, issue_description, assigned_to_user_id) 
        VALUES ('$land_holding_id', '$reported_by_user_id', '$issue_description', '$assigned_to_user_id')";

if ($conn->query($sql) === TRUE) {
    echo "Case reported and assigned successfully!";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
