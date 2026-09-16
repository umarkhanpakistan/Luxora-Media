<?php
// test-mail.php - Test SMTP connection

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

echo "<h2>Testing SMTP Connection...</h2>";

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';
    
    // Enable debug output
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        echo "<pre>" . htmlspecialchars($str) . "</pre>";
    };
    
    // Recipients
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress('test@example.com'); // Any email address
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Luxora Media';
    $mail->Body    = 'This is a test email to verify SMTP connection is working.';
    $mail->AltBody = 'This is a test email to verify SMTP connection is working.';
    
    echo "<h3>Sending test email...</h3>";
    
    if ($mail->send()) {
        echo "<p style='color: green;'>✅ Test email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to send test email.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>SMTP Error: " . $mail->ErrorInfo . "</p>";
}
?>