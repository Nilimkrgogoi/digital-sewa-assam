<?php
// admin/login.php - Official Administrator Login Portal
require_once __DIR__ . '/../config/database.php';

if (is_admin()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter your administrative credentials.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (mobile = ? OR email = ?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] !== 'admin') {
                $error = "Access Denied: This portal is reserved strictly for authorized officials and administrators. Citizens should use the <a href='../login.php' style='text-decoration:underline;'>Citizen Login</a>.";
            } elseif ($user['status'] === 'blocked') {
                $error = "This administrator account has been disabled.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['user_mobile'] = $user['mobile'];
                $_SESSION['user_email'] = $user['email'];

                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid administrator username/mobile or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin & Officer Login - Digital Sewa Assam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background: #0f172a; color: white;">

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="login-box" style="background: #1e293b; border-color: #334155; width: 100%; max-width: 440px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="../assets/images/logo.jpg" alt="Logo" style="margin: 0 auto 12px; width: 65px; height: 65px; border-radius: 50%; object-fit: contain; display: block; border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 4px 14px rgba(0,0,0,0.3);">
            <span class="admin-badge">Admin Gateway</span>
            <h2 style="font-size: 24px; font-weight: 800; margin: 10px 0; color: white;">
                Official Administration
            </h2>
            <p style="color: #94a3b8; font-size: 13px;">
                Digital Sewa Assam Service Management & Verification Portal
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background: #450a0a; color: #fca5a5; border-color: #7f1d1d;">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="identifier" style="color: #cbd5e1;">Official Mobile or Email</label>
                <input type="text" id="identifier" name="identifier" required placeholder="e.g. 9876543210" style="background: #0f172a; border-color: #334155; color: white;" value="<?= htmlspecialchars($_POST['identifier'] ?? '9876543210') ?>">
            </div>

            <div class="form-group">
                <label for="password" style="color: #cbd5e1;">Administrator Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter admin password" style="background: #0f172a; border-color: #334155; color: white;" value="Admin@123">
            </div>

            <button type="submit" class="btn primary" style="width: 100%; margin-top: 10px; padding: 14px; background: #7c3aed; box-shadow: 0 4px 14px rgba(124, 58, 237, 0.4);">
                Unlock Admin Portal →
            </button>
        </form>

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #334155; text-align: center; font-size: 13px; color: #94a3b8;">
            Looking for public citizen access? <a href="../login.php" style="color: #38bdf8; font-weight: 700;">Citizen Login</a>
            <br>
            <a href="../index.php" style="color: #94a3b8; font-size: 12px; display: inline-block; margin-top: 8px;">← Return to Home</a>
        </div>
    </div>
</div>

<?= portal_footer_html(true) ?>

</body>
</html>
