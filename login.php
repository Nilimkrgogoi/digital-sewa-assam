<?php
// login.php - Citizen and Staff Login
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
$info = '';

if (isset($_GET['registered'])) {
    $info = "Registration successful! You can now log in with your credentials.";
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $info = "Password reset successfully! You can now log in with your new password.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter your registered mobile number/email and password.";
    } else {
        // Query by mobile or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'blocked') {
                $error = "Your account is temporarily suspended. Please contact Digital Sewa Assam helpline (+91 9613167470).";
            } elseif ($user['status'] === 'processing' || $user['status'] === 'pending') {
                $utrInfo = !empty($user['platform_fee_txn']) ? " (UTR: <code>" . htmlspecialchars($user['platform_fee_txn']) . "</code>)" : "";
                $error = "⏳ <strong>Account In Processing:</strong> Your registration details were received, but your platform fee payment" . $utrInfo . " is currently awaiting administrator validation. Once approved by the administrator, your account will be activated and you can sign in.";
            } else {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_mobile'] = $user['mobile'];
                $_SESSION['user_email'] = $user['email'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                    exit;
                }

                $redirectTo = !empty($_GET['redirect']) ? urldecode($_GET['redirect']) : 'dashboard.php';
                header("Location: " . $redirectTo);
                exit;
            }
        } else {
            $error = "Invalid mobile number/email or password. Please check and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Login - Digital Sewa Assam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <nav id="navbar">
            <a href="index.php">Home</a>
            <a href="index.php#services">Services</a>
            <a href="track.php">Track Status</a>
            <a href="register.php" class="register-btn">Register New Account</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 60px 0;">
    <div class="container">
        <div class="login-box">
            <div style="text-align: center; margin-bottom: 24px;">
                <img src="assets/images/logo.jpg" alt="Logo" style="margin: 0 auto 12px; width: 65px; height: 65px; border-radius: 50%; object-fit: contain; display: block; box-shadow: 0 4px 12px rgba(0,0,0,0.12);">
                <span class="badge" style="color: var(--primary); background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3);">
                    Secure Citizen Login
                </span>
                <h2 style="font-size: 26px; font-weight: 800; margin: 10px 0; color: var(--text-main);">
                    Welcome Back
                </h2>
                <p style="color: var(--text-muted); font-size: 14px;">
                    Access your Digital Sewa Assam account
                </p>
            </div>

            <?php if (!empty($info)): ?>
                <div class="alert alert-success">
                    ✓ <?= $info ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>

            <form action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="identifier">Mobile Number or Email</label>
                    <input type="text" id="identifier" name="identifier" placeholder="e.g. 9876543210 or your email" required value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label for="password">Password</label>
                        <a href="forgot_password.php" style="font-size: 13px; color: var(--primary); font-weight: 600;">Forgot Password?</a>
                    </div>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn primary" style="width: 100%; margin-top: 10px; padding: 14px;">
                    Sign In to Portal →
                </button>
            </form>

            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--card-border); text-align: center; font-size: 13px; color: #94a3b8;">
                Don't have an account? <a href="register.php" style="color: var(--primary); font-weight: 700;">Register here</a>
                <br><br>
                <a href="admin/login.php" style="color: #64748b; font-size: 12px;">🛡️ Administrator / Official Login</a>
            </div>
        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
</body>
</html>
