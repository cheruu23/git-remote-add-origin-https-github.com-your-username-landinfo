<?php
ob_start();
session_start();
require '../includes/config.php';
require '../includes/db.php';
require '../includes/languages.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = getDBConnection();
if ($conn->connect_error) {
    $_SESSION['error'] = $translations['en']['error_generic'];
    header("Location: forgot_password.php");
    ob_end_flush();
    exit;
}
$conn->set_charset('utf8mb4');

$lang = $_GET['lang'] ?? 'om'; // Default to Afaan Oromo

// Log directory and file
$log_dir = 'C:\xampp\htdocs\landinfo\logs';
$log_file = $log_dir . '\smtp_debug.log';

// Ensure log directory exists
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);

    // Check if email exists 
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = $translations[$lang]['error_generic'];
        header("Location: forgot_password.php?lang=$lang");
        ob_end_flush();
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user === null) {
        $_SESSION['error'] = $translations[$lang]['error_email_not_found'];
        header("Location: forgot_password.php?lang=$lang");
        ob_end_flush();
        exit;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $token_hash = hash("sha256", $token);
    $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Store token
    $sql = "INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = $translations[$lang]['error_generic'];
        header("Location: forgot_password.php?lang=$lang");
        ob_end_flush();
        exit;
    }
    $stmt->bind_param("issss", $user["id"], $token_hash, $expires_at, $token_hash, $expires_at);
    $stmt->execute();

    // Prepare reset link
    $reset_link = BASE_URL . "/public/reset_password.php?token=$token";

    // Send email
    $mail = new PHPMailer(true);
    try {
        // Enable verbose debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use ($log_file) {
            if (is_writable($log_file) || !file_exists($log_file)) {
                @file_put_contents(
                    $log_file,
                    date('Y-m-d H:i:s') . " [$level] $str\n",
                    FILE_APPEND
                );
            }
        };
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cheruuasrat30@gmail.com';
        $mail->Password = 'zjrb rrwe psmq vzvl';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // SSL options
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => 'C:\xampp\apache\bin\curl-ca-bundle.crt',
            ]
        ];

        $mail->setFrom('cheruuasrat30@gmail.com', 'LIMS');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $translations[$lang]['email_subject'];
        $mail->Body = str_replace('{reset_link}', $reset_link, $translations[$lang]['email_body']);
        $mail->AltBody = str_replace('{reset_link}', $reset_link, $translations[$lang]['email_alt_body']);

        $mail->send();
        $_SESSION['success'] = true;
    } catch (Exception $e) {
        $_SESSION['error'] = $translations[$lang]['error_generic'];
        header("Location: forgot_password.php?lang=$lang");
        ob_end_flush();
        exit;
    }

    header("Location: forgot_password.php?lang=$lang");
    ob_end_flush();
    exit;
}
ob_end_flush();
?>