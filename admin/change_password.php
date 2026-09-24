<?php
// admin/change_password.php - Change Administrator Password
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_admin_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    $adminId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $err = "Administrator account not found.";
    } elseif (!password_verify($currentPass, $admin['password'])) {
        $err = "Current password is incorrect. Please check and try again.";
    } elseif (strlen($newPass) < 6) {
        $err = "New password must be at least 6 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $err = "New password and confirmation do not match.";
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, raw_password = ? WHERE id = ?");
        $updateStmt->execute([$hashed, $newPass, $adminId]);
        $msg = "🎉 Your administrator password has been updated successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Digital Sewa Assam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ADMIN HEADER -->
<header class="header" style="background: #0f172a; border-color: #1e293b;">
    <div class="container nav">
        <?= logo_html_admin('dashboard.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" style="color: white;">☰</button>
        <nav id="navbar">
            <a href="dashboard.php" style="color: #cbd5e1;">Dashboard</a>
            <a href="applications.php" style="color: #cbd5e1;">Applications</a>
            <a href="users.php" style="color: #cbd5e1;">Users</a>
            <a href="services.php" style="color: #cbd5e1;">Services</a>
            <a href="settings.php" style="color: #cbd5e1;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #38bdf8; font-weight: 700;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #94a3b8; font-size: 13px;">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 40px 0 70px; max-width: 600px;">
    <div style="margin-bottom: 24px; text-align: center;">
        <span class="admin-badge" style="background: #e0e7ff; color: #4338ca;">Security & Credentials</span>
        <h1 style="font-size: 26px; font-weight: 800; color: #000000; margin: 10px 0 6px;">
            Change Administrator Password
        </h1>
        <p style="color: #2c3644; font-size: 14px;">
            Update your master admin account login credentials.
        </p>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">⚠️ <?= $err ?></div>
    <?php endif; ?>

    <div class="content-card" style="padding: 30px; background:white ; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <form action="change_password.php" method="POST">
            <input type="hidden" name="action" value="change_admin_password">

            <div class="form-group" style="margin-bottom: 18px;">
                <label for="current_password" style="font-weight: 600; font-size: 14px;">Current Administrator Password *</label>
                <input type="password" id="current_password" name="current_password" required placeholder="Enter current password" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
                <label for="new_password" style="font-weight: 600; font-size: 14px;">New Password *</label>
                <input type="password" id="new_password" name="new_password" minlength="6" required placeholder="Minimum 6 characters" style="width: 100%;">
                <small style="font-size: 11px; color: #64748b;">Must be at least 6 characters long.</small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label for="confirm_password" style="font-weight: 600; font-size: 14px;">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required placeholder="Re-type new password" style="width: 100%;">
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
                <a href="dashboard.php" class="btn secondary" style="text-decoration: none;">Back to Dashboard</a>
                <button type="submit" class="btn primary" style="background: #7c3aed; font-weight: 700; padding: 12px 24px;">
                    Update Admin Password →
                </button>
            </div>
        </form>
    </div>
</div>

<?= portal_footer_html(true) ?>

<script src="../assets/js/script.js"></script>
</body>
</html>
