<?php
// Start session
session_start();

// Use absolute path with __DIR__ to find db.php
require_once __DIR__ . '/db.php';

// If already logged in, redirect based on role
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

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) ? true : false;

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $db = getDB();
            
            // Get user by email including role
            $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'] ?? 'user';
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();

                // Remember me - set cookie for 30 days
                if ($remember) {
                    setcookie('user_id', $user['id'], time() + (86400 * 30), '/');
                    setcookie('user_email', $user['email'], time() + (86400 * 30), '/');
                    setcookie('user_name', $user['name'], time() + (86400 * 30), '/');
                    setcookie('user_role', $user['role'] ?? 'user', time() + (86400 * 30), '/');
                }

                // Update last login time
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

// Check for remember me cookies
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$_COOKIE['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $_COOKIE['user_email'] === $user['email']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['logged_in'] = true;
            
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }
    } catch (PDOException $e) {
        // Cookie invalid, ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In – Luxora Media</title>
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
        .login-card .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #d4af37, #f5c84c);
            color: #050505;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.2rem 0.8rem;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-left: 0.5rem;
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
        .alert-custom-success {
            background: rgba(46, 213, 115, 0.08);
            border: 1px solid rgba(46, 213, 115, 0.15);
            color: #2ed573;
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
            <p class="subtitle">
                Welcome back! Sign in to your account
                <span class="admin-badge"><i class="fas fa-crown me-1"></i> Admin Access</span>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-custom-danger mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-custom-success mb-3">
                    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="mb-3 input-group-icon">
                    <input type="email" name="email" class="form-control-gold" placeholder="Email address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
                    <i class="fas fa-envelope icon"></i>
                </div>
                <div class="mb-3 input-group-icon">
                    <input type="password" name="password" class="form-control-gold" placeholder="Password" required />
                    <i class="fas fa-lock icon"></i>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        <label class="form-check-label text-secondary small" for="remember">Remember me</label>
                    </div>
                    <a href="forgot-password.php" class="auth-link small">Forgot password?</a>
                </div>
                <button type="submit" class="btn-gold-full" id="loginBtn">
                    <i class="fas fa-arrow-right-to-bracket me-2"></i> Sign In
                </button>
            </form>

            <div class="divider"></div>

            <p class="text-center text-secondary small mt-2">
                Don't have an account? <a href="sign-up.php" class="auth-link">Sign Up</a>
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

        const form = document.querySelector('form');
        const loginBtn = document.getElementById('loginBtn');
        
        if (form) {
            form.addEventListener('submit', function() {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="spinner-gold me-2"></span> Signing in...';
            });
        }
    });
    </script>
</body>
</html>