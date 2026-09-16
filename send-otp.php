<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get user email
    $stmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Generate OTP (6 digits)
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Save OTP to database
    $stmt = $db->prepare("INSERT INTO password_resets (user_id, otp, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $otp, $expires_at]);
    
    // Send email with PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // Your email
        $mail->Password   = 'your-app-password'; // Your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Luxora Media');
        $mail->addAddress($user['email'], $user['name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Change Verification - Luxora Media';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #050505; color: #ffffff; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: #0a0a0a; padding: 30px; border-radius: 16px; border: 1px solid rgba(212,175,55,0.1); }
                    .logo { font-size: 24px; font-weight: 900; color: #d4af37; text-align: center; margin-bottom: 20px; }
                    .otp-code { font-size: 36px; font-weight: 700; color: #d4af37; text-align: center; padding: 15px; background: rgba(212,175,55,0.05); border-radius: 12px; margin: 20px 0; letter-spacing: 8px; }
                    .text { color: #a5a5a5; text-align: center; }
                    .expiry { color: #ff4757; font-size: 12px; text-align: center; margin-top: 15px; }
                    .divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(212,175,55,0.1), transparent); margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='logo'>LUXORA<span style='color:#ffffff;'>.</span></div>
                    <h2 style='text-align:center;'>Password Change Verification</h2>
                    <p class='text'>You requested to change your password. Use the following OTP code to verify your identity:</p>
                    <div class='otp-code'>$otp</div>
                    <p class='text'>This OTP is valid for <strong>15 minutes</strong>.</p>
                    <div class='divider'></div>
                    <p class='text' style='font-size:12px;'>If you didn't request this, please ignore this email.</p>
                    <p class='expiry'>⚠️ Never share your OTP with anyone.</p>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Password Change Verification\n\nYour OTP code is: $otp\n\nThis OTP is valid for 15 minutes.\n\nIf you didn't request this, please ignore this email.";
        
        $mail->send();
        
        // Store OTP in session for verification
        $_SESSION['otp_verified'] = false;
        $_SESSION['otp_user_id'] = $user_id;
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent to your email',
            'otp' => $otp // Remove in production, only for testing
        ]);
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>