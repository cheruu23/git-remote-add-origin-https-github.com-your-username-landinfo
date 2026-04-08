<?php
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host = 'sandbox.smtp.mailtrap.io';
    $mail->SMTPAuth = true;
    $mail->Username = '65998e3342389e';
    $mail->Password = '8d7f2d516ea739';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 2525;

    $mail->setFrom('from@example.com', 'LIMS Test');
    $mail->addAddress('mamushe710@gmail.com');
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body = 'This is a test email from PHPMailer.';
    ob_start();
    $mail->send();
    $debug_output = ob_get_clean();
    echo "Email sent successfully!<br>Debug: <pre>$debug_output</pre>";
} catch (Exception $e) {
    $debug_output = ob_get_clean();
    echo "Failed to send email: " . $mail->ErrorInfo . "<br>Debug: <pre>$debug_output</pre>";
}
?>