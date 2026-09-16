<?php
/**
 * Luxora Media – Helper Functions
 */

// Load PHPMailer
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';
require_once __DIR__ . '/phpmailer/src/Exception.php';

// Load mail configuration
require_once __DIR__ . '/mail.php';

function generateToken() {
    return bin2hex(random_bytes(32));
}

function sendResetEmail($email, $token) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings - Same as working test
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Disable debug output for production
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Luxora Media';
        
        $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 40px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: rgba(16,16,16,0.95); border-radius: 16px; padding: 40px; border: 1px solid rgba(212,175,55,0.15); }
                .logo { font-size: 28px; font-weight: 900; color: #d4af37; text-align: center; margin-bottom: 20px; }
                .logo span { color: #fff; font-weight: 300; }
                .btn { display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #d4af37, #f5c84c); color: #0a0a0a !important; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 20px 0; }
                .btn:hover { background: linear-gradient(135deg, #c19b2e, #e8b830); }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px; }
                .text-center { text-align: center; }
                .text-muted { color: #888; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>LUXORA<span>.</span></div>
                <h2 style='text-align:center;'>Reset Your Password</h2>
                <p style='text-align:center; color: #aaa;'>We received a request to reset your password. Click the button below to set a new password.</p>
                <div style='text-align:center;'>
                    <a href='{$resetLink}' class='btn'>Reset Password</a>
                </div>
                <p style='text-align:center; color: #888; font-size: 14px;'>This link will expire in <strong>1 hour</strong>.</p>
                <p style='text-align:center; color: #888; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
                <div class='footer'>
                    &copy; " . date('Y') . " Luxora Media. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Reset your password at: " . $resetLink . "\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>