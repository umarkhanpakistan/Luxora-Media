<?php
/**
 * Luxora Media – Reset Password Page
 */

session_start();

// Load required files with correct paths
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = '';

// Verify token
if (!empty($token)) {
    try {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            SELECT email, expires_at, used 
            FROM password_resets 
            WHERE token = ? 
            AND used = FALSE 
            AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $resetData = $stmt->fetch();
        
        if ($resetData) {
            $email = $resetData['email'];
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password']) && !empty($token)) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    } else {
        try {
            $pdo = getDB();
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Get the reset data again to ensure it's still valid
            $stmt = $pdo->prepare("
                SELECT email, expires_at, used 
                FROM password_resets 
                WHERE token = ? 
                AND used = FALSE 
                AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $resetData = $stmt->fetch();
            
            if (!$resetData) {
                $error = 'Invalid or expired reset link. Please request a new one.';
                $pdo->rollBack();
            } else {
                // Update password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $resetData['email']]);
                
                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
                $stmt->execute([$token]);
                
                $pdo->commit();
                $success = 'Password reset successfully! You can now sign in with your new password.';
                $token = ''; // Clear token to show success
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - Luxora Media</title>
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
        .form-group .input-wrapper .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 0;
        }
        .form-group .input-wrapper .toggle-password:hover {
            color: rgba(255,255,255,0.6);
        }
        .password-requirements {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #666;
        }
        .password-requirements .req {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.2rem 0;
        }
        .password-requirements .req i {
            font-size: 0.7rem;
            width: 16px;
        }
        .password-requirements .req.valid {
            color: #2ed573;
        }
        .password-requirements .req.invalid {
            color: #666;
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
            
            <?php if ($success): ?>
                <h2 class="auth-title">Password Reset!</h2>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="auth-footer">
                    <a href="sign-in.php"><i class="fas fa-arrow-left me-2"></i> Sign In Now</a>
                </div>
            <?php elseif ($error && empty($token) && empty($_POST)): ?>
                <h2 class="auth-title">Invalid Link</h2>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <div class="auth-footer">
                    <a href="forgot-password.php">Request New Link</a>
                </div>
            <?php else: ?>
                <h2 class="auth-title">Create New Password</h2>
                <p class="auth-subtitle">Enter your new password below.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="resetForm" novalidate>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8">
                            <i class="fas fa-lock"></i>
                            <button type="button" class="toggle-password" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
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
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <i class="fas fa-check-circle"></i>
                            <button type="button" class="toggle-password" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-auth" id="submitBtn">
                        <i class="fas fa-key me-2"></i> Reset Password
                    </button>
                </form>
                
                <div class="auth-footer">
                    <a href="sign-in.php"><i class="fas fa-arrow-left me-2"></i> Back to Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    if (input) {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        this.querySelector('i').classList.toggle('fa-eye');
                        this.querySelector('i').classList.toggle('fa-eye-slash');
                    }
                });
            });
            
            // Password requirements validation
            const passwordInput = document.getElementById('password');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
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
                });
            }
            
            // Form validation
            const form = document.getElementById('resetForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    
                    if (password.length < 8) {
                        e.preventDefault();
                        showToast('Password must be at least 8 characters long.');
                        return;
                    }
                    
                    if (password !== confirm) {
                        e.preventDefault();
                        showToast('Passwords do not match.');
                        return;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Resetting...';
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