<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');
    $agree = isset($_POST['agree']) ? true : false;

    // Validation
    $errors = [];

    if (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Full name is too long.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif (strlen($password) > 100) {
        $errors[] = 'Password is too long.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$agree) {
        $errors[] = 'You must agree to the terms and conditions.';
    }

    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered. <a href="sign-in.php" class="auth-link">Sign In</a>';
            } else {
                // Default role is 'user'
                $role = 'user';
                
                // Insert new user
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt->execute([$name, $email, $hashed, $role])) {
                    $userId = $db->lastInsertId();
                    
                    // --- NEW: Add user to clients table ---
                    try {
                        // Check if clients table exists
                        $stmt = $db->query("SHOW TABLES LIKE 'clients'");
                        if ($stmt->rowCount() > 0) {
                            // Insert into clients table
                            $stmt = $db->prepare("INSERT INTO clients (name, email, status, joined_date) VALUES (?, ?, 'active', NOW())");
                            $stmt->execute([$name, $email]);
                        }
                    } catch (PDOException $e) {
                        error_log("Failed to add user to clients table: " . $e->getMessage());
                    }
                    // --- End of new code ---
                    
                    // Auto-login after registration
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = $role;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Signup Error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign Up – Luxora Media</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="luxora.css" />
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 1.5rem 2rem;
            position: relative;
            z-index: 1;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            padding: 2.5rem;
            border-radius: 1.5rem;
            background: rgba(16, 16, 16, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.12);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
            z-index: 2;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(212, 175, 55, 0.03), transparent 60%);
            pointer-events: none;
        }
        .login-card:hover {
            border-color: rgba(212, 175, 55, 0.25);
            transform: translateY(-4px);
            box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 60px rgba(212, 175, 55, 0.04);
        }
        .login-card .logo {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 2rem;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-align: center;
            display: block;
            margin-bottom: 0.5rem;
        }
        .login-card .logo span {
            -webkit-text-fill-color: #fff;
            font-weight: 300;
        }
        .login-card .subtitle {
            text-align: center;
            color: #a5a5a5;
            font-size: 0.95rem;
            margin-bottom: 2rem;
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
            background: rgba(255,255,255,0.06);
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
        .btn-gold-full {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            color: #050505;
            font-weight: 600;
            padding: 0.9rem 2.2rem;
            border-radius: 100px;
            border: none;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        .btn-gold-full::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }
        .btn-gold-full:hover::before {
            left: 100%;
        }
        .btn-gold-full:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 50px rgba(212, 175, 55, 0.3);
            color: #050505;
        }
        .btn-gold-full:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .auth-link {
            color: #d4af37;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .auth-link:hover {
            color: #f5c84c;
        }
        .alert-custom-danger {
            background: rgba(255, 71, 87, 0.08);
            border: 1px solid rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            font-size: 0.9rem;
        }
        .input-group-icon {
            position: relative;
        }
        .input-group-icon .icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a5a5a5;
            opacity: 0.6;
            transition: color 0.3s ease;
        }
        .input-group-icon .form-control-gold:focus + .icon,
        .input-group-icon .form-control-gold:focus ~ .icon {
            color: #d4af37;
            opacity: 1;
        }
        .input-group-icon .form-control-gold {
            padding-left: 2.8rem;
        }
        .form-check-input:checked {
            background-color: #d4af37;
            border-color: #d4af37;
        }
        .form-check-input:focus {
            box-shadow: 0 0 0 0.2rem rgba(212, 175, 55, 0.25);
        }
        .password-requirements {
            font-size: 0.75rem;
            color: #a5a5a5;
            margin-top: 0.3rem;
        }
        .password-requirements .valid {
            color: #2ed573;
        }
        .password-requirements .valid i {
            color: #2ed573;
        }
        .password-requirements .invalid {
            color: #ff4757;
        }
        .password-requirements .invalid i {
            color: #ff4757;
        }
        .password-requirements span {
            display: inline-block;
            margin-right: 1rem;
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
        .form-check-label a {
            color: #d4af37;
            text-decoration: none;
        }
        .form-check-label a:hover {
            color: #f5c84c;
        }
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212,175,55,0.1), transparent);
            margin: 1.5rem 0;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 1.8rem;
            }
            .login-card .logo {
                font-size: 1.6rem;
            }
            .password-requirements span {
                display: block;
                margin-right: 0;
            }
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

    <div class="login-wrapper">
        <div class="login-card">
            <a href="index.php" class="logo">LUXORA<span>.</span></a>
            <p class="subtitle">Create your account and get started</p>

            <?php if ($error): ?>
                <div class="alert alert-custom-danger mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="signupForm">
                <div class="mb-3 input-group-icon">
                    <input type="text" name="name" class="form-control-gold" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" />
                    <i class="fas fa-user icon"></i>
                </div>
                <div class="mb-3 input-group-icon">
                    <input type="email" name="email" class="form-control-gold" placeholder="Email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
                    <i class="fas fa-envelope icon"></i>
                </div>
                <div class="mb-3 input-group-icon">
                    <input type="password" name="password" id="password" class="form-control-gold" placeholder="Password (min 6 characters)" required />
                    <i class="fas fa-lock icon"></i>
                </div>
                <div class="mb-3 input-group-icon">
                    <input type="password" name="confirm_password" class="form-control-gold" placeholder="Confirm Password" required />
                    <i class="fas fa-check-circle icon"></i>
                </div>
                <div class="password-requirements mb-3" id="passwordHelp">
                    <span id="lengthCheck" class="invalid"><i class="fas fa-circle me-1"></i> 6+ characters</span>
                    <span id="upperCheck" class="invalid"><i class="fas fa-circle me-1"></i> Uppercase</span>
                    <span id="numberCheck" class="invalid"><i class="fas fa-circle me-1"></i> Number</span>
                </div>
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" id="agree" name="agree" required>
                    <label class="form-check-label text-secondary small" for="agree">
                        I agree to the <a href="#" class="auth-link">Terms & Conditions</a> and <a href="#" class="auth-link">Privacy Policy</a>
                    </label>
                </div>
                <button type="submit" class="btn-gold-full" id="signupBtn">
                    <i class="fas fa-user-plus me-2"></i> Sign Up
                </button>
            </form>

            <div class="divider"></div>

            <p class="text-center text-secondary small mt-2">
                Already have an account? <a href="sign-in.php" class="auth-link">Sign In</a>
            </p>
            <p class="text-center text-secondary small">
                <i class="fas fa-arrow-left me-1"></i> <a href="index.php" class="auth-link">Back to Home</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading screen
            const loader = document.getElementById('loader');
            if (loader) {
                setTimeout(function() {
                    loader.classList.add('hidden');
                }, 500);
            }

            const form = document.getElementById('signupForm');
            const signupBtn = document.getElementById('signupBtn');
            
            // Password validation
            document.getElementById('password')?.addEventListener('input', function() {
                const val = this.value;
                const hasMinLength = val.length >= 6;
                const hasUpper = /[A-Z]/.test(val);
                const hasNumber = /[0-9]/.test(val);

                const lengthCheck = document.getElementById('lengthCheck');
                const upperCheck = document.getElementById('upperCheck');
                const numberCheck = document.getElementById('numberCheck');

                lengthCheck.className = hasMinLength ? 'valid' : 'invalid';
                lengthCheck.innerHTML = `<i class="fas fa-${hasMinLength ? 'check-circle' : 'circle'} me-1"></i> 6+ characters`;
                
                upperCheck.className = hasUpper ? 'valid' : 'invalid';
                upperCheck.innerHTML = `<i class="fas fa-${hasUpper ? 'check-circle' : 'circle'} me-1"></i> Uppercase`;
                
                numberCheck.className = hasNumber ? 'valid' : 'invalid';
                numberCheck.innerHTML = `<i class="fas fa-${hasNumber ? 'check-circle' : 'circle'} me-1"></i> Number`;
            });

            // Form submission loading state
            if (form) {
                form.addEventListener('submit', function() {
                    signupBtn.disabled = true;
                    signupBtn.innerHTML = '<span class="spinner-gold me-2"></span> Creating account...';
                });
            }
        });
    </script>
</body>
</html>