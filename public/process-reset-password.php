<?php
session_start();
require __DIR__ . "/../includes/db.php";
$mysqli = getDBConnection();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["token"];
    $token_hash = hash("sha256", $token);
    $password = $_POST["password"];
    $password_confirmation = $_POST["password_confirmation"];

    // Validate token
    $sql = "SELECT user_id, expires_at FROM password_resets WHERE token = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();

    if ($reset === null) {
        $_SESSION['error'] = "Invalid token.";
        header("Location: forgot_password.php");
        exit;
    }

    if (strtotime($reset["expires_at"]) <= time()) {
        $_SESSION['error'] = "Token has expired.";
        header("Location: forgot_password.php");
        exit;
    }

    // Validate password
    if ($password !== $password_confirmation) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    // Update password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $reset["user_id"]);
    $stmt->execute();

    // Delete token
    $sql = "DELETE FROM password_resets WHERE user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $reset["user_id"]);
    $stmt->execute();

    $_SESSION['success'] = "Password reset successfully. Please login.";
    header("Location: login.php");
    exit;
}
?>