<?php
/**
 * Luxora Media – Forgot Password Page
 */

session_start();

// Load required files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDB();
            
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate token
                $token = generateToken();
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Check if password_resets table exists
                try {
                    $pdo->query("SELECT 1 FROM password_resets LIMIT 1");
                } catch (PDOException $e) {
                    // Table doesn't exist, create it
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS password_resets (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            email VARCHAR(100) NOT NULL,
                            token VARCHAR(255) NOT NULL,
                            expires_at DATETIME NOT NULL,
                            used BOOLEAN DEFAULT FALSE,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_token (token),
                            INDEX idx_email (email)
                        )
                    ");
                }
                
                // Delete any existing entries for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Insert new token
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (email, token, expires_at, used) 
                    VALUES (?, ?, ?, FALSE)
                ");
                $stmt->execute([$email, $token, $expires_at]);
                
                // Send email
                if (sendResetEmail($email, $token)) {
                    $success = 'Password reset link has been sent to your email. Please check your inbox.';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                    error_log("Failed to send reset email to: $email");
                }
            } else {
                // Security: Don't reveal if email exists or not
                $success = 'If your email exists in our system, you will receive a password reset link.';
            }
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password - Luxora Media</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="luxora.css" />
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #0a0a0a;
            position: relative;
        }
        .auth-container {
            max-width: 440px;
            width: 100%;
            background: rgba(16, 16, 16, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.12);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            position: relative;
            z-index: 2;
        }
        .auth-logo {
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        .auth-logo span {
            font-weight: 300;
            -webkit-text-fill-color: #fff;
        }
        .auth-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        .auth-subtitle {
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.5rem;
            letter-spacing: 0.02em;
        }
        .form-group .input-wrapper {
            position: relative;
        }
        .form-group .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3);
            transition: color 0.3s ease;
        }
        .form-group .input-wrapper input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .form-group .input-wrapper input:focus {
            border-color: rgba(212, 175, 55, 0.3);
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.04);
            background: rgba(255,255,255,0.06);
        }
        .form-group .input-wrapper input:focus + i,
        .form-group .input-wrapper input:focus ~ i {
            color: #d4af37;
        }
        .form-group .input-wrapper input::placeholder {
            color: rgba(255,255,255,0.25);
        }
        .btn-auth {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            color: #0a0a0a;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            position: relative;
            overflow: hidden;
        }
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 40px rgba(212, 175, 55, 0.3);
        }
        .btn-auth:active {
            transform: translateY(0);
        }
        .btn-auth:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #888;
            font-size: 0.9rem;
        }
        .auth-footer a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .auth-footer a:hover {
            color: #f5c84c;
            text-decoration: underline;
        }
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-error {
            background: rgba(255, 71, 87, 0.08);
            border: 1px solid rgba(255, 71, 87, 0.15);
            color: #ff4757;
        }
        .alert-success {
            background: rgba(46, 213, 115, 0.08);
            border: 1px solid rgba(46, 213, 115, 0.15);
            color: #2ed573;
        }
        .alert i {
            font-size: 1.1rem;
        }
        .back-to-login {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #888;
            text-decoration: none;
            transition: color 0.3s ease;
            margin-top: 0.5rem;
        }
        .back-to-login:hover {
            color: #d4af37;
        }
        @media (max-width: 480px) {
            .auth-container { padding: 2rem 1.5rem; }
            .auth-title { font-size: 1.3rem; }
        }
    </style>
</head>
<body>
    <?php include '3d-scene.php'; ?>
    
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-logo">LUXORA<span>.</span></div>
            <h2 class="auth-title">Forgot Password?</h2>
            <p class="auth-subtitle">Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success || strpos($success, 'If your email exists') !== false): ?>
                <form method="POST" action="" id="forgotForm" novalidate>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" placeholder="Enter your email" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                   required autofocus>
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-auth" id="submitBtn">
                        <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <a href="sign-in.php" class="back-to-login">
                    <i class="fas fa-arrow-left"></i> Back to Sign In
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const submitBtn = document.getElementById('submitBtn');
            const emailInput = document.getElementById('email');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = emailInput.value.trim();
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (!email) {
                        e.preventDefault();
                        showToast('Please enter your email address.');
                        emailInput.focus();
                        return;
                    }
                    
                    if (!emailPattern.test(email)) {
                        e.preventDefault();
                        showToast('Please enter a valid email address.');
                        emailInput.focus();
                        return;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
                });
            }
            
            function showToast(message) {
                let toast = document.getElementById('toast');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = 'toast';
                    toast.className = 'toast-notification';
                    document.body.appendChild(toast);
                }
                toast.innerHTML = message;
                toast.className = 'toast-notification';
                toast.style.borderColor = 'rgba(255, 71, 87, 0.3)';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 4000);
            }
        });
    </script>
    
    <div id="toast" class="toast-notification"></div>
    <script src="luxora.js"></script>
</body>
</html>