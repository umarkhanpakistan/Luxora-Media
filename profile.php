<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: sign-in.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

$message = '';
$message_type = '';
$show_otp_form = false;
$otp_verified = false;
$user_data = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_name') {
        $new_name = trim($_POST['name'] ?? '');
        
        if (strlen($new_name) < 2) {
            $message = 'Name must be at least 2 characters.';
            $message_type = 'danger';
        } elseif (strlen($new_name) > 100) {
            $message = 'Name is too long.';
            $message_type = 'danger';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_name, $user_id]);
                
                $_SESSION['user_name'] = $new_name;
                $user_name = $new_name;
                
                $message = 'Name updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $message = 'An error occurred. Please try again.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'send_otp') {
        // Send OTP for password change
        try {
            $db = getDB();
            
            // Generate OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store OTP in password_resets table
            $stmt = $db->prepare("
                INSERT INTO password_resets (email, token, expires_at, used) 
                VALUES (?, ?, ?, FALSE)
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at),
                used = FALSE,
                created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$user_email, $otp, $expires_at]);
            
            // Send OTP email
            if (sendOTPEmail($user_email, $otp)) {
                $_SESSION['otp_sent'] = true;
                $_SESSION['otp_email'] = $user_email;
                $show_otp_form = true;
                $message = 'OTP sent to your email. Please check your inbox and verify to change password.';
                $message_type = 'success';
            } else {
                $message = 'Failed to send OTP. Please try again.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            error_log("OTP send error: " . $e->getMessage());
            $message = 'An error occurred. Please try again.';
            $message_type = 'danger';
        }
    } elseif ($action === 'verify_otp') {
        // Verify OTP
        $otp_code = $_POST['otp_code'] ?? '';
        
        if (empty($otp_code) || strlen($otp_code) !== 6) {
            $message = 'Please enter a valid 6-digit OTP.';
            $message_type = 'danger';
            $show_otp_form = true;
        } else {
            try {
                $db = getDB();
                
                // Verify OTP
                $stmt = $db->prepare("
                    SELECT id FROM password_resets 
                    WHERE email = ? AND token = ? AND used = FALSE AND expires_at > NOW()
                ");
                $stmt->execute([$user_email, $otp_code]);
                $otp_valid = $stmt->fetch();
                
                if ($otp_valid) {
                    // Mark OTP as used
                    $stmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE email = ? AND token = ?");
                    $stmt->execute([$user_email, $otp_code]);
                    
                    $_SESSION['otp_verified'] = true;
                    $_SESSION['otp_verified_email'] = $user_email;
                    $otp_verified = true;
                    $show_otp_form = false;
                    $message = 'OTP verified successfully! You can now change your password.';
                    $message_type = 'success';
                } else {
                    $message = 'Invalid or expired OTP. Please request a new one.';
                    $message_type = 'danger';
                    $show_otp_form = true;
                }
            } catch (PDOException $e) {
                error_log("OTP verification error: " . $e->getMessage());
                $message = 'An error occurred. Please try again.';
                $message_type = 'danger';
                $show_otp_form = true;
            }
        }
    } elseif ($action === 'change_password') {
        // Check if OTP is verified
        if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
            $message = 'Please verify your identity with OTP first.';
            $message_type = 'warning';
            $show_otp_form = true;
        } else {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'Please fill in all password fields.';
                $message_type = 'danger';
            } elseif (strlen($new_password) < 8) {
                $message = 'New password must be at least 8 characters.';
                $message_type = 'danger';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $message_type = 'danger';
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $message = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
                $message_type = 'danger';
            } else {
                try {
                    $db = getDB();
                    
                    // Verify current password
                    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user || !password_verify($current_password, $user['password'])) {
                        $message = 'Current password is incorrect.';
                        $message_type = 'danger';
                    } else {
                        // Update password
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed, $user_id]);
                        
                        // Clear OTP session
                        $_SESSION['otp_verified'] = false;
                        $otp_verified = false;
                        
                        $message = 'Password updated successfully!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    error_log("Password update error: " . $e->getMessage());
                    $message = 'An error occurred. Please try again.';
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Function to send OTP email
function sendOTPEmail($email, $otp) {
    require_once __DIR__ . '/mail.php';
    require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        $mail->isHTML(true);
        $mail->Subject = 'OTP for Password Change - Luxora Media';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; background: #0a0a0a; color: #fff; padding: 40px; margin: 0; }
                .container { max-width: 600px; margin: 0 auto; background: rgba(16,16,16,0.95); border-radius: 16px; padding: 40px; border: 1px solid rgba(212,175,55,0.15); }
                .logo { font-size: 28px; font-weight: 900; color: #d4af37; text-align: center; margin-bottom: 20px; }
                .logo span { color: #fff; font-weight: 300; }
                .otp-box { background: rgba(212, 175, 55, 0.05); border: 1px solid rgba(212, 175, 55, 0.15); border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
                .otp-code { font-size: 36px; font-weight: 700; color: #d4af37; letter-spacing: 8px; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px; }
                .text-muted { color: #888; }
                .warning { color: #ff6b6b; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>LUXORA<span>.</span></div>
                <h2 style='text-align:center;'>Password Change Verification</h2>
                <p style='text-align:center; color: #aaa;'>Enter the following OTP to verify your identity and change your password.</p>
                <div class='otp-box'>
                    <div class='otp-code'>$otp</div>
                    <p style='color: #888; font-size: 14px; margin-top: 10px;'>This OTP will expire in 15 minutes</p>
                </div>
                <p style='text-align:center; color: #888; font-size: 14px;'>If you didn't request this, please ignore this email.</p>
                <div class='footer'>
                    &copy; " . date('Y') . " Luxora Media. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Your OTP for password change is: $otp\n\nThis OTP will expire in 15 minutes.\n\nIf you didn't request this, please ignore this email.";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("OTP email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Get user data with better error handling
try {
    $db = getDB();
    
    // Check if users table exists
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        $tableExists = $stmt->rowCount() > 0;
        if (!$tableExists) {
            $db->exec("CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                profile_pic VARCHAR(255) DEFAULT NULL,
                bio TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                last_login DATETIME DEFAULT NULL
            )");
            $message = 'User table was created. Please try again.';
            $message_type = 'warning';
        }
    } catch (PDOException $e) {
        error_log("Table check error: " . $e->getMessage());
    }
    
    // Get user data
    $stmt = $db->prepare("SELECT id, name, email, created_at, updated_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    if (!$user_data) {
        $stmt = $db->prepare("SELECT id, name, email, created_at, updated_at, last_login FROM users WHERE email = ?");
        $stmt->execute([$user_email]);
        $user_data = $stmt->fetch();
        
        if (!$user_data) {
            session_destroy();
            header('Location: sign-in.php');
            exit;
        }
    }
} catch (PDOException $e) {
    error_log("Profile data fetch error: " . $e->getMessage());
    $message = 'Error loading profile data. Please try again later.';
    $message_type = 'danger';
    
    $user_data = [
        'id' => $user_id,
        'name' => $user_name,
        'email' => $user_email,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
}

// Calculate account age
$account_age_string = 'New';
$now = new DateTime();

if ($user_data && isset($user_data['created_at']) && !empty($user_data['created_at'])) {
    try {
        $created_at = new DateTime($user_data['created_at']);
        $account_age = $created_at->diff($now);
        
        $age_parts = [];
        if ($account_age->y > 0) {
            $age_parts[] = $account_age->y . ' year' . ($account_age->y > 1 ? 's' : '');
        }
        if ($account_age->m > 0) {
            $age_parts[] = $account_age->m . ' month' . ($account_age->m > 1 ? 's' : '');
        }
        if ($account_age->d > 0 && $account_age->y < 1) {
            $age_parts[] = $account_age->d . ' day' . ($account_age->d > 1 ? 's' : '');
        }
        if (empty($age_parts)) {
            $age_parts[] = 'Today';
        }
        $account_age_string = implode(', ', $age_parts);
    } catch (Exception $e) {
        $account_age_string = 'New';
    }
}

// Update last login (only once per session)
if (!isset($_SESSION['last_login_updated'])) {
    try {
        if ($user_data && isset($user_data['id'])) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_data['id']]);
            $_SESSION['last_login_updated'] = true;
        }
    } catch (PDOException $e) {
        error_log("Last login update error: " . $e->getMessage());
    }
}

// Check if OTP is already verified in session
if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
    $otp_verified = true;
    $show_otp_form = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile – Luxora Media</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="luxora.css" />
    <style>
        /* ===== PROFILE DROPDOWN ===== */
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        .profile-dropdown .dropdown-toggle {
            color: #d4af37 !important;
            border: 1px solid rgba(212, 175, 55, 0.2) !important;
            border-radius: 50px !important;
            padding: 0.4rem 1.2rem !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            background: transparent !important;
            text-decoration: none !important;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            position: relative;
            z-index: 1001;
            user-select: none;
            border: none;
        }
        .profile-dropdown .dropdown-toggle:hover {
            background: rgba(212, 175, 55, 0.08) !important;
            border-color: #d4af37 !important;
            transform: translateY(-2px) !important;
        }
        .profile-dropdown .dropdown-toggle i {
            font-size: 1rem;
        }
        .profile-dropdown .dropdown-toggle .fa-chevron-down {
            font-size: 0.6rem;
            opacity: 0.5;
            transition: transform 0.3s ease;
            margin-left: 0.2rem;
        }
        .profile-dropdown.active .dropdown-toggle .fa-chevron-down {
            transform: rotate(180deg);
        }
        
        .profile-dropdown .dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 200px;
            background: rgba(10, 10, 10, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 12px;
            padding: 0.5rem 0;
            z-index: 99999 !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.9);
            list-style: none;
            margin: 0;
            display: none !important;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.25s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: none;
        }
        .profile-dropdown.active .dropdown-menu {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) scale(1) !important;
            pointer-events: auto !important;
        }
        .profile-dropdown .dropdown-menu li {
            list-style: none;
            display: block;
            padding: 0;
            margin: 0;
        }
        .profile-dropdown .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 1.2rem;
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            border-bottom: none;
            background: transparent;
            cursor: pointer;
            white-space: nowrap;
        }
        .profile-dropdown .dropdown-menu a:hover {
            background: rgba(212, 175, 55, 0.08);
            color: #d4af37;
        }
        .profile-dropdown .dropdown-menu a i {
            width: 20px;
            color: #d4af37;
            font-size: 0.9rem;
            text-align: center;
        }
        .profile-dropdown .dropdown-menu .dropdown-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212,175,55,0.12), transparent);
            margin: 0.3rem 0.8rem;
            padding: 0;
            border: none;
            display: block;
        }
        .profile-dropdown .dropdown-menu .logout-item {
            color: #ff4757 !important;
        }
        .profile-dropdown .dropdown-menu .logout-item i {
            color: #ff4757 !important;
        }
        .profile-dropdown .dropdown-menu .logout-item:hover {
            background: rgba(255, 71, 87, 0.08) !important;
            color: #ff4757 !important;
        }
        .mobile-profile-btn {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .mobile-profile-btn a {
            text-align: center;
            padding: 0.7rem;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
            display: block;
        }
        .mobile-profile-btn .profile-link {
            color: #d4af37;
            border-color: rgba(212, 175, 55, 0.2);
        }
        .mobile-profile-btn .profile-link:hover {
            background: rgba(212, 175, 55, 0.08);
        }
        .mobile-profile-btn .logout-link {
            color: #ff4757;
            border-color: rgba(255, 71, 87, 0.2);
        }
        .mobile-profile-btn .logout-link:hover {
            background: rgba(255, 71, 87, 0.08);
        }

        /* ===== AUTH BUTTONS ===== */
        .auth-buttons {
            display: flex !important;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
        }
        .auth-buttons .btn-sm {
            padding: 0.4rem 1.2rem;
            font-size: 0.85rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .btn-outline-gold {
            border: 1.5px solid #d4af37 !important;
            color: #d4af37 !important;
            background: transparent !important;
        }
        .btn-outline-gold:hover {
            background: #d4af37 !important;
            color: #0a0a0a !important;
            border-color: #d4af37 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.25);
        }
        .btn-gold-solid {
            background: #d4af37 !important;
            color: #000000 !important;
            border: 1.5px solid #d4af37 !important;
        }
        .btn-gold-solid:hover {
            background: #c19b2e !important;
            border-color: #c19b2e !important;
            color: #000000 !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
        }

        .mobile-auth {
            margin-top: 1.5rem;
            display: flex !important;
            flex-direction: column;
            gap: 0.75rem;
            padding: 0 1.5rem;
        }
        .mobile-auth .btn {
            border-radius: 50px;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            width: 100%;
        }
        .mobile-auth .btn-outline-gold {
            border: 1.5px solid #d4af37 !important;
            color: #d4af37 !important;
            background: transparent !important;
        }
        .mobile-auth .btn-outline-gold:hover {
            background: #d4af37 !important;
            color: #0a0a0a !important;
        }
        .mobile-auth .btn-gold-solid {
            background: #d4af37 !important;
            color: #000000 !important;
            border: 1.5px solid #d4af37 !important;
        }
        .mobile-auth .btn-gold-solid:hover {
            background: #c19b2e !important;
            border-color: #c19b2e !important;
            color: #000000 !important;
        }

        @media (min-width: 993px) {
            .mobile-auth { display: none !important; }
        }
        @media (max-width: 992px) {
            .auth-buttons { display: none !important; }
            .mobile-auth { display: flex !important; }
        }

        /* ===== PROFILE PAGE STYLES ===== */
        .profile-wrapper {
            min-height: 100vh;
            padding: 100px 1.5rem 2rem;
            background: radial-gradient(ellipse at 30% 40%, rgba(212,175,55,0.06), transparent 60%),
                        radial-gradient(ellipse at 70% 60%, rgba(212,175,55,0.04), transparent 50%);
            position: relative;
            z-index: 1;
        }
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
            padding: 2.5rem;
            border-radius: 1.5rem;
            background: rgba(16, 16, 16, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.12);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            position: relative;
            z-index: 2;
        }
        .profile-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(212, 175, 55, 0.03), transparent 60%);
            pointer-events: none;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #050505;
            margin: 0 auto 1rem;
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.2);
        }
        .profile-name {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .profile-email {
            text-align: center;
            color: #a5a5a5;
            font-size: 0.95rem;
        }
        .profile-joined {
            text-align: center;
            color: #a5a5a5;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }
        .profile-joined i {
            color: #d4af37;
        }
        .form-control-gold {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 0.9rem 1.2rem;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
            width: 100%;
        }
        .form-control-gold:focus {
            border-color: rgba(212, 175, 55, 0.3);
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.04);
        }
        .form-control-gold::placeholder {
            color: rgba(255,255,255,0.25);
        }
        .form-control-gold.error {
            border-color: #ff4757;
            box-shadow: 0 0 30px rgba(255, 71, 87, 0.1);
        }
        .form-control-gold.success {
            border-color: #2ed573;
            box-shadow: 0 0 30px rgba(46, 213, 115, 0.08);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255,255,255,0.5);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
            display: block;
        }
        .form-label i {
            color: #d4af37;
            margin-right: 0.3rem;
        }
        .btn-gold-full {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            color: #050505;
            font-weight: 600;
            padding: 0.7rem 1.5rem;
            border-radius: 100px;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
        }
        .btn-gold-full:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.25);
            color: #050505;
        }
        .btn-gold-full:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .btn-gold-outline-small {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: #d4af37 !important;
            font-weight: 500;
            padding: 0.6rem 1.5rem;
            border-radius: 100px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-gold-outline-small:hover {
            background: rgba(212, 175, 55, 0.08);
            border-color: #d4af37;
            transform: translateY(-2px);
            color: #d4af37 !important;
        }
        .btn-danger-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: #ff4757 !important;
            font-weight: 500;
            padding: 0.6rem 1.5rem;
            border-radius: 100px;
            border: 1px solid rgba(255, 71, 87, 0.3);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-danger-outline:hover {
            background: rgba(255, 71, 87, 0.08);
            border-color: #ff4757;
            transform: translateY(-2px);
            color: #ff4757 !important;
        }
        .alert-custom-danger {
            background: rgba(255, 71, 87, 0.08);
            border: 1px solid rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
        }
        .alert-custom-success {
            background: rgba(46, 213, 115, 0.08);
            border: 1px solid rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
        }
        .alert-custom-warning {
            background: rgba(255, 165, 0, 0.08);
            border: 1px solid rgba(255, 165, 0, 0.15);
            color: #ffa502;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
        }
        .alert-custom-info {
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.15);
            color: #d4af37;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
        }
        .section-title-small {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .section-title-small i {
            color: #d4af37;
            margin-right: 0.5rem;
        }
        .error-message {
            color: #ff4757;
            font-size: 0.8rem;
            margin-top: 0.3rem;
            display: none;
        }
        .error-message.show {
            display: block;
        }
        .spinner-gold {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(5,5,5,0.1);
            border-top-color: #050505;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212,175,55,0.1), transparent);
            margin: 1.5rem 0;
        }
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1rem 0 1.5rem;
        }
        .profile-stats .stat {
            text-align: center;
        }
        .profile-stats .stat .number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #d4af37;
        }
        .profile-stats .stat .label {
            font-size: 0.7rem;
            color: #a5a5a5;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .otp-section {
            background: rgba(212, 175, 55, 0.03);
            border: 1px solid rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
            animation: fadeIn 0.3s ease;
        }
        .otp-verified-badge {
            background: rgba(46, 213, 115, 0.08);
            border: 1px solid rgba(46, 213, 115, 0.15);
            color: #2ed573;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .otp-verified-badge i {
            font-size: 1.2rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .otp-input-group {
            display: flex;
            gap: 0.5rem;
        }
        .otp-input-group .form-control-gold {
            flex: 1;
            text-align: center;
            font-size: 1.2rem;
            letter-spacing: 0.5rem;
        }
        .btn-otp-verify {
            background: #d4af37;
            color: #050505;
            border: none;
            border-radius: 12px;
            padding: 0.9rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-otp-verify:hover {
            background: #f5c84c;
            transform: translateY(-2px);
        }
        .btn-otp-verify:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-otp-send {
            background: transparent;
            color: #d4af37;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .btn-otp-send:hover {
            background: rgba(212, 175, 55, 0.08);
            border-color: #d4af37;
        }
        .otp-timer {
            color: #a5a5a5;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .otp-timer span {
            color: #ff4757;
            font-weight: 600;
        }
        .otp-status {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            display: none;
        }
        .otp-status.show {
            display: block;
        }
        .otp-status.success {
            background: rgba(46, 213, 115, 0.08);
            color: #2ed573;
        }
        .otp-status.error {
            background: rgba(255, 71, 87, 0.08);
            color: #ff4757;
        }
        .otp-status.info {
            background: rgba(212, 175, 55, 0.08);
            color: #d4af37;
        }
        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #666;
        }
        .password-requirements .req {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.15rem 0;
        }
        .password-requirements .req i {
            font-size: 0.6rem;
            width: 14px;
        }
        .password-requirements .req.valid {
            color: #2ed573;
        }
        .password-requirements .req.invalid {
            color: #666;
        }
        .account-age {
            display: inline-block;
            background: rgba(212, 175, 55, 0.08);
            padding: 0.2rem 0.8rem;
            border-radius: 50px;
            font-size: 0.7rem;
            color: #d4af37;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }
        .password-change-container {
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 768px) {
            .profile-card { padding: 1.5rem; }
            .profile-stats { gap: 1rem; }
            .otp-input-group { flex-direction: column; }
        }
    </style>
</head>
<body>
    <?php include '3d-scene.php'; ?>

    <!-- LOADING SCREEN -->
    <div id="loader">
        <div class="loader-ring"></div>
        <div class="loader-text">LUXORA</div>
        <div class="loader-bar"><div class="loader-bar-fill"></div></div>
    </div>

    <!-- BACK TO TOP -->
    <button id="backTop" class="back-top" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>

<?php include 'user-nav.php'; ?>

    <div class="profile-wrapper">
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user_email) ?></div>
            <div class="profile-joined">
                <i class="fas fa-calendar-alt me-1"></i> 
                Joined <?= isset($user_data['created_at']) && !empty($user_data['created_at']) ? date('F Y', strtotime($user_data['created_at'])) : date('F Y') ?>
                <span class="account-age ms-2">
                    <i class="fas fa-clock me-1"></i> <?= $account_age_string ?> old
                </span>
                <?php if (isset($user_data['last_login']) && !empty($user_data['last_login'])): ?>
                    <span class="mx-1">•</span>
                    <i class="fas fa-clock me-1"></i> 
                    Last login: <?= date('M d, Y h:i A', strtotime($user_data['last_login'])) ?>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="profile-stats">
                <div class="stat">
                    <div class="number"><i class="fas fa-check-circle" style="color:#2ed573;font-size:1.2rem;"></i></div>
                    <div class="label">Verified</div>
                </div>
                <div class="stat">
                    <div class="number"><?= date('Y') - 2022 + 1 ?></div>
                    <div class="label">Years</div>
                </div>
                <div class="stat">
                    <div class="number"><i class="fas fa-shield-alt" style="color:#d4af37;font-size:1.2rem;"></i></div>
                    <div class="label">Secure</div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-custom-<?= $message_type ?> mb-3">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Update Name Section -->
            <div class="divider"></div>
            <div class="section-title-small"><i class="fas fa-user-edit"></i> Update Name</div>
            <form method="POST" action="" id="nameForm" autocomplete="off">
                <input type="hidden" name="action" value="update_name">
                <div class="mb-3">
                    <label for="profileName" class="form-label"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" id="profileName" class="form-control-gold" value="<?= htmlspecialchars($user_name) ?>" placeholder="Enter your full name" autocomplete="name" required />
                    <div class="error-message" id="nameError">Name must be at least 2 characters</div>
                </div>
                <button type="submit" id="nameSubmit" class="btn-gold-full">
                    <i class="fas fa-save me-2"></i> Update Name
                </button>
            </form>

            <!-- Change Password Section -->
            <div class="divider"></div>
            <div class="section-title-small"><i class="fas fa-key"></i> Change Password</div>
            
            <?php if ($otp_verified): ?>
                <!-- OTP Verified - Show password change form -->
                <div class="otp-verified-badge">
                    <i class="fas fa-check-circle"></i> 
                    <span>Identity Verified! You can now change your password.</span>
                </div>
                
                <!-- Password Change Form (Only shown after OTP verification) -->
                <div class="password-change-container">
                    <form method="POST" action="" id="passwordForm" autocomplete="off">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                            <input type="password" name="current_password" id="currentPassword" class="form-control-gold" placeholder="Enter current password" autocomplete="current-password" required />
                            <div class="error-message" id="currentError">Please enter your current password</div>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label"><i class="fas fa-key"></i> New Password</label>
                            <input type="password" name="new_password" id="newPassword" class="form-control-gold" placeholder="Enter new password (min 8 characters)" autocomplete="new-password" required />
                            <div class="password-requirements" id="passwordRequirements">
                                <div class="req invalid" data-rule="length">
                                    <i class="fas fa-circle"></i> At least 8 characters
                                </div>
                                <div class="req invalid" data-rule="uppercase">
                                    <i class="fas fa-circle"></i> At least one uppercase letter
                                </div>
                                <div class="req invalid" data-rule="lowercase">
                                    <i class="fas fa-circle"></i> At least one lowercase letter
                                </div>
                                <div class="req invalid" data-rule="number">
                                    <i class="fas fa-circle"></i> At least one number
                                </div>
                            </div>
                            <div class="error-message" id="newError">Password must be at least 8 characters with uppercase, lowercase, and number</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control-gold" placeholder="Confirm new password" autocomplete="new-password" required />
                            <div class="error-message" id="confirmError">Passwords do not match</div>
                        </div>
                        <button type="submit" id="passwordSubmit" class="btn-gold-full">
                            <i class="fas fa-key me-2"></i> Change Password
                        </button>
                    </form>
                </div>
                
            <?php elseif ($show_otp_form): ?>
                <!-- OTP Form -->
                <div class="otp-section">
                    <p class="text-secondary small mb-2">
                        <i class="fas fa-shield-alt text-gold me-1"></i> 
                        A verification code has been sent to your email. Enter it below to verify your identity.
                    </p>
                    <form method="POST" action="" id="otpForm">
                        <input type="hidden" name="action" value="verify_otp">
                        <div class="otp-input-group">
                            <input type="text" name="otp_code" id="otpInput" class="form-control-gold" placeholder="Enter 6-digit OTP" maxlength="6" autocomplete="one-time-code" required />
                            <button type="submit" id="verifyOtpBtn" class="btn-otp-verify">
                                <i class="fas fa-check me-2"></i> Verify OTP
                            </button>
                        </div>
                        <div id="otpStatus" class="otp-status info">
                            <i class="fas fa-info-circle me-1"></i> 
                            <span id="otpStatusText">Enter the OTP sent to your email</span>
                        </div>
                        <div class="otp-timer mt-2">
                            <i class="fas fa-clock me-1"></i> 
                            OTP expires in <span id="otpTimer">15:00</span>
                            <button type="button" id="resendOtpBtn" class="btn btn-sm" style="color:#d4af37;background:transparent;border:none;margin-left:0.5rem;text-decoration:underline;">
                                Resend OTP
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Send OTP Button -->
                <div class="text-center mb-3">
                    <p class="text-secondary small mb-3">
                        <i class="fas fa-info-circle text-gold me-1"></i>
                        For security, you must verify your identity with OTP before changing your password.
                    </p>
                    <form method="POST" action="" id="sendOtpForm">
                        <input type="hidden" name="action" value="send_otp">
                        <button type="submit" id="sendOtpBtn" class="btn-gold-full" style="background:transparent;border:1px solid rgba(212,175,55,0.3);color:#d4af37;">
                            <i class="fas fa-shield-alt me-2"></i> Send OTP to Verify
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Logout Section -->
            <div class="divider"></div>
            <div class="d-flex gap-3">
                <a href="logout.php" class="btn-danger-outline w-50 justify-content-center">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
                <a href="index.php" class="btn-gold-outline-small w-50 justify-content-center">
                    <i class="fas fa-arrow-left me-2"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="border-top border-white/5 bg-card/40 py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3"><a href="index.php" class="logo fs-2">LUXORA<span>.</span></a><p class="text-secondary small mt-2">Premium digital agency.</p><div class="d-flex gap-2 mt-2"><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-x-twitter"></i></a><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-linkedin-in"></i></a><a href="#" class="text-secondary text-decoration-none hover-gold"><i class="fab fa-youtube"></i></a></div></div>
                <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-link me-1"></i> Quick Links</h6><ul class="list-unstyled mt-2 small"><li><a href="index.php" class="footer-link">Home</a></li><li><a href="about.php" class="footer-link">About</a></li><li><a href="services.php" class="footer-link">Services</a></li><li><a href="pricing.php" class="footer-link">Pricing</a></li><li><a href="faq.php" class="footer-link">FAQ</a></li><li><a href="contact.php" class="footer-link">Contact</a></li></ul></div>
                <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-cog me-1"></i> Services</h6><ul class="list-unstyled mt-2 small"><li><a href="web-development.php" class="footer-link">Web Dev</a></li><li><a href="ai-chatbots.php" class="footer-link">AI Chatbots</a></li><li><a href="graphic-design.php" class="footer-link">Graphic Design</a></li></ul></div>
                <div class="col-md-3"><h6 class="fw-bold text-uppercase small text-secondary"><i class="fas fa-envelope me-1"></i> Newsletter</h6><form class="d-flex gap-2" onsubmit="event.preventDefault();showToast('✓ Subscribed!')"><input type="email" placeholder="Your email" class="form-input flex-1" style="flex:1;" required /><button type="submit" class="btn btn-primary-custom px-3 py-2"><i class="fas fa-arrow-right"></i></button></form></div>
            </div>
            <div class="border-top border-white/5 mt-4 pt-3 d-flex flex-wrap justify-content-between small text-secondary"><span><i class="far fa-copyright me-1"></i> 2026 Luxora Media. All Rights Reserved.</span><span>Made with <i class="fas fa-heart text-gold"></i> in Berlin</span></div>
        </div>
    </footer>

    <div id="toast" class="toast-notification"><i class="fas fa-check-circle text-gold me-2"></i> Message sent! We'll <span class="gold">get back to you</span> soon.</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="luxora.js"></script>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Name form validation
        const nameForm = document.getElementById('nameForm');
        const profileName = document.getElementById('profileName');
        const nameError = document.getElementById('nameError');
        const nameSubmit = document.getElementById('nameSubmit');

        if (profileName) {
            profileName.addEventListener('input', function() {
                const val = this.value.trim();
                if (val.length < 2) {
                    this.classList.add('error');
                    this.classList.remove('success');
                    nameError.classList.add('show');
                    return false;
                } else {
                    this.classList.remove('error');
                    this.classList.add('success');
                    nameError.classList.remove('show');
                    return true;
                }
            });

            nameForm.addEventListener('submit', function(e) {
                const val = profileName.value.trim();
                if (val.length < 2) {
                    e.preventDefault();
                    profileName.classList.add('error');
                    nameError.classList.add('show');
                    profileName.focus();
                } else {
                    nameSubmit.disabled = true;
                    nameSubmit.innerHTML = '<span class="spinner-gold me-2"></span> Updating...';
                }
            });
        }

        // Password requirements validation (only if password form exists)
        const newPassword = document.getElementById('newPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        
        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const val = this.value;
                const rules = {
                    length: val.length >= 8,
                    uppercase: /[A-Z]/.test(val),
                    lowercase: /[a-z]/.test(val),
                    number: /[0-9]/.test(val)
                };
                
                document.querySelectorAll('.req').forEach(function(req) {
                    const rule = req.getAttribute('data-rule');
                    if (rules[rule] !== undefined) {
                        req.classList.remove('invalid', 'valid');
                        req.classList.add(rules[rule] ? 'valid' : 'invalid');
                        req.querySelector('i').className = rules[rule] ? 'fas fa-check-circle' : 'fas fa-circle';
                    }
                });
                
                const allValid = Object.values(rules).every(v => v === true);
                if (allValid && val.length > 0) {
                    this.classList.remove('error');
                    this.classList.add('success');
                    document.getElementById('newError').classList.remove('show');
                } else if (val.length > 0) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else {
                    this.classList.remove('error', 'success');
                }
                
                if (confirmPassword && confirmPassword.value.trim()) {
                    validateConfirm();
                }
            });
        }

        if (confirmPassword) {
            confirmPassword.addEventListener('input', validateConfirm);
        }

        function validateConfirm() {
            if (!confirmPassword) return;
            const val = confirmPassword.value.trim();
            const pass = newPassword ? newPassword.value.trim() : '';
            
            if (val && val !== pass) {
                confirmPassword.classList.add('error');
                confirmPassword.classList.remove('success');
                document.getElementById('confirmError').classList.add('show');
                return false;
            } else if (val && val === pass) {
                confirmPassword.classList.remove('error');
                confirmPassword.classList.add('success');
                document.getElementById('confirmError').classList.remove('show');
                return true;
            } else {
                confirmPassword.classList.remove('error', 'success');
                document.getElementById('confirmError').classList.remove('show');
                return false;
            }
        }

        // OTP Timer Functions
        let otpTimerInterval = null;
        let otpTimeLeft = 900;
        const otpInput = document.getElementById('otpInput');
        const verifyOtpBtn = document.getElementById('verifyOtpBtn');
        const resendOtpBtn = document.getElementById('resendOtpBtn');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const otpStatus = document.getElementById('otpStatus');
        const otpStatusText = document.getElementById('otpStatusText');
        const otpTimer = document.getElementById('otpTimer');

        function startOtpTimer() {
            otpTimeLeft = 900;
            updateOtpTimerDisplay();
            if (otpTimerInterval) clearInterval(otpTimerInterval);
            otpTimerInterval = setInterval(function() {
                otpTimeLeft--;
                updateOtpTimerDisplay();
                if (otpTimeLeft <= 0) {
                    clearInterval(otpTimerInterval);
                    otpTimer.textContent = 'Expired';
                    if (resendOtpBtn) resendOtpBtn.style.display = 'inline';
                }
            }, 1000);
        }

        function updateOtpTimerDisplay() {
            const mins = Math.floor(otpTimeLeft / 60);
            const secs = otpTimeLeft % 60;
            if (otpTimer) otpTimer.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        function showOtpStatus(message, type) {
            if (otpStatus) {
                otpStatus.className = `otp-status ${type} show`;
                if (otpStatusText) otpStatusText.textContent = message;
            }
        }

        // Send OTP button
        const sendOtpForm = document.getElementById('sendOtpForm');
        if (sendOtpForm) {
            sendOtpForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('sendOtpBtn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
            });
        }

        // Resend OTP
        if (resendOtpBtn) {
            resendOtpBtn.addEventListener('click', function() {
                this.disabled = true;
                this.textContent = 'Sending...';
                showOtpStatus('Sending OTP to your email...', 'info');
                
                const formData = new FormData();
                formData.append('action', 'send_otp');

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    location.reload();
                })
                .catch(error => {
                    showOtpStatus('Network error. Please try again.', 'error');
                    this.textContent = 'Resend OTP';
                    this.disabled = false;
                });
            });
        }

        // Auto-start OTP timer if OTP section is visible
        if (document.querySelector('.otp-section')) {
            startOtpTimer();
            showOtpStatus('Enter the OTP sent to your email', 'info');
        }

        // Password form submission (only if password form exists)
        const passwordForm = document.getElementById('passwordForm');
        const passwordSubmit = document.getElementById('passwordSubmit');
        
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                const currentPassword = document.getElementById('currentPassword');
                const newPassword = document.getElementById('newPassword');
                const confirmPassword = document.getElementById('confirmPassword');
                const currentError = document.getElementById('currentError');
                const newError = document.getElementById('newError');
                const confirmError = document.getElementById('confirmError');
                
                if (!currentPassword || !currentPassword.value.trim()) {
                    if (currentPassword) currentPassword.classList.add('error');
                    if (currentError) currentError.classList.add('show');
                    isValid = false;
                }
                
                if (newPassword) {
                    const newVal = newPassword.value.trim();
                    if (newVal.length < 8 || !/[A-Z]/.test(newVal) || !/[a-z]/.test(newVal) || !/[0-9]/.test(newVal)) {
                        newPassword.classList.add('error');
                        if (newError) newError.classList.add('show');
                        isValid = false;
                    }
                }
                
                if (confirmPassword) {
                    const confirmVal = confirmPassword.value.trim();
                    const newVal = newPassword ? newPassword.value.trim() : '';
                    if (confirmVal !== newVal) {
                        confirmPassword.classList.add('error');
                        if (confirmError) confirmError.classList.add('show');
                        isValid = false;
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    if (currentPassword && !currentPassword.value.trim()) {
                        currentPassword.focus();
                    } else if (newPassword && (newPassword.value.trim().length < 8 || !/[A-Z]/.test(newPassword.value.trim()) || !/[a-z]/.test(newPassword.value.trim()) || !/[0-9]/.test(newPassword.value.trim()))) {
                        newPassword.focus();
                    } else if (confirmPassword) {
                        confirmPassword.focus();
                    }
                } else {
                    if (passwordSubmit) {
                        passwordSubmit.disabled = true;
                        passwordSubmit.innerHTML = '<span class="spinner-gold me-2"></span> Updating...';
                    }
                }
            });
        }

        // Toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.innerHTML = message;
                toast.className = 'toast-notification';
                toast.style.borderColor = 'rgba(255, 71, 87, 0.3)';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 4000);
            }
        }
    });
    </script>
</body>
</html>