<?php
// profile.php - User Profile & Account Settings
require_once __DIR__ . '/config/database.php';
require_login();

$userId = $_SESSION['user_id'];
$user = get_current_user_data($pdo);

$profileMsg = '';
$profileErr = '';
$mobileMsg = '';
$mobileErr = '';
$passMsg = '';
$passErr = '';

// Update Basic Profile Details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    if (empty($name)) {
        $profileErr = "Full Name is required.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $userId]);
        $_SESSION['user_name'] = $name;
        $user['name'] = $name;
        $user['email'] = $email;
        $profileMsg = "Profile information updated successfully!";
    }
}

// Change Registered Mobile Number (Using Password Verification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_mobile') {
    $newMobile = sanitize($_POST['new_mobile'] ?? '');
    $password = $_POST['auth_password'] ?? '';

    if (empty($newMobile)) {
        $mobileErr = "New 10-digit mobile number is required.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $newMobile)) {
        $mobileErr = "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
    } elseif ($newMobile === $user['mobile']) {
        $mobileErr = "The new mobile number is the same as your currently registered number.";
    } elseif (empty($password)) {
        $mobileErr = "Current account password is required to verify your identity.";
    } elseif (!password_verify($password, $user['password'])) {
        $mobileErr = "Incorrect password! For security reasons, mobile numbers cannot be changed without your valid password. If you forgot your password, please contact the administrator.";
    } else {
        // Check if new mobile already registered by someone else
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$newMobile, $userId]);
        if ($checkStmt->fetch()) {
            $mobileErr = "Mobile number +91 $newMobile is already registered to another citizen account.";
        } else {
            $updateStmt = $pdo->prepare("UPDATE users SET mobile = ? WHERE id = ?");
            $updateStmt->execute([$newMobile, $userId]);
            $_SESSION['user_mobile'] = $newMobile;
            $user['mobile'] = $newMobile;
            $mobileMsg = "✓ Your registered mobile number has been changed successfully to: +91 " . htmlspecialchars($newMobile);
        }
    }
}

// Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $passErr = "All password fields are required.";
    } elseif (!password_verify($currentPass, $user['password'])) {
        $passErr = "Current password is incorrect.";
    } elseif (strlen($newPass) < 6) {
        $passErr = "New password must be at least 6 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $passErr = "New passwords do not match.";
    } else {
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, raw_password = ? WHERE id = ?");
        $stmt->execute([$newHash, $newPass, $userId]);
        $passMsg = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Digital Sewa Assam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display: flex; flex-direction: column; min-height: 100vh;">

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">☰</button>
        <nav id="navbar">
            <a href="index.php">Home</a>
            <a href="dashboard.php">Dashboard</a>
            <a href="apply.php">Apply Online</a>
            <a href="track.php">Track Status</a>
            <a href="profile.php" style="color: var(--primary); font-weight: 700;">My Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 40px 0; flex: 1;">
    <div class="container">
        <div style="max-width: 800px; margin: auto;">
            <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                <div>
                    <a href="dashboard.php" style="color: var(--primary); font-size: 13px; font-weight: 600;">← Back to Dashboard</a>
                    <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin-top: 6px;">
                        Account Profile Settings
                    </h1>
                    <p style="color: #64748b; font-size: 14px;">
                        Manage your personal details, registered contact number, and login credentials.
                    </p>
                </div>
                <button type="button" onclick="openContactAdminModal()" class="btn secondary sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                    💬 Contact Admin for Help
                </button>
            </div>

            <!-- PROFILE DETAILS FORM -->
            <div class="content-card" style="margin-bottom: 25px;">
                <div class="card-title-bar">
                    <h3>Personal Information</h3>
                    <span class="badge" style="background: #eff6ff; color: var(--primary); border-color: #bfdbfe;">
                        Role: <?= htmlspecialchars(ucfirst($user['role'])) ?>
                    </span>
                </div>
                <div style="padding: 24px;">
                    <?php if (!empty($profileMsg)): ?>
                        <div class="alert alert-success">✓ <?= $profileMsg ?></div>
                    <?php endif; ?>
                    <?php if (!empty($profileErr)): ?>
                        <div class="alert alert-danger">⚠️ <?= $profileErr ?></div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['name']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="current_mobile">Current Mobile Number</label>
                                <input type="text" id="current_mobile" value="<?= htmlspecialchars($user['mobile']) ?>" disabled style="background: #f1f5f9; cursor: not-allowed;">
                                <small style="font-size: 11px; color: #64748b;">To change your mobile number, use the section below.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="name@example.com">
                        </div>

                        <div style="text-align: right; margin-top: 10px;">
                            <button type="submit" class="btn primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CHANGE MOBILE NUMBER (WITH PASSWORD VERIFICATION) -->
            <div class="content-card" style="margin-bottom: 25px; border-left: 4px solid #3b82f6;">
                <div class="card-title-bar" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: #1e3a8a;">📱 Change Registered Mobile Number</h3>
                        <span style="font-size: 12px; color: #64748b;">Requires account password verification</span>
                    </div>
                    <button type="button" onclick="openContactAdminModal()" class="btn secondary sm" style="font-size: 12px; font-weight: 700; color: #dc2626; border-color: #fecaca; background: #fff5f5;">
                        Forgot Password? Contact Admin
                    </button>
                </div>
                <div style="padding: 24px;">
                    <?php if (!empty($mobileMsg)): ?>
                        <div class="alert alert-success">✓ <?= $mobileMsg ?></div>
                    <?php endif; ?>
                    <?php if (!empty($mobileErr)): ?>
                        <div class="alert alert-danger">⚠️ <?= $mobileErr ?></div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="change_mobile">

                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px; font-size: 13px; color: #1e40af;">
                            ℹ️ <b>Security Notice:</b> To prevent unauthorized account takeover, entering your current password is required. If you do not remember your password, please click <b>"Forgot Password? Contact Admin"</b> and the portal administrator will update it for you.
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_mobile">New 10-Digit Mobile Number *</label>
                                <input type="tel" id="new_mobile" name="new_mobile" maxlength="10" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" required>
                                <small style="font-size: 11px; color: #64748b;">Must be an active 10-digit Indian number linked with your Aadhaar.</small>
                            </div>
                            <div class="form-group">
                                <label for="auth_password">Current Account Password *</label>
                                <input type="password" id="auth_password" name="auth_password" required placeholder="Enter password to confirm">
                                <small style="font-size: 11px; color: #64748b;">Your identity is verified using this password.</small>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                            <a href="javascript:void(0)" onclick="openContactAdminModal()" style="font-size: 12px; color: #2563eb; text-decoration: underline; font-weight: 600;">
                                Cannot verify password? Contact Portal Admin →
                            </a>
                            <button type="submit" class="btn primary" style="background: #2563eb;">
                                Update Mobile Number
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CHANGE PASSWORD FORM -->
            <div class="content-card">
                <div class="card-title-bar">
                    <h3>Change Password</h3>
                </div>
                <div style="padding: 24px;">
                    <?php if (!empty($passMsg)): ?>
                        <div class="alert alert-success">✓ <?= $passMsg ?></div>
                    <?php endif; ?>
                    <?php if (!empty($passErr)): ?>
                        <div class="alert alert-danger">⚠️ <?= $passErr ?></div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required placeholder="Enter current password">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" minlength="6" required placeholder="Minimum 6 characters">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" minlength="6" required placeholder="Repeat new password">
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 10px;">
                            <button type="submit" class="btn secondary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CONTACT ADMIN MODAL POPUP -->
<div id="contactAdminModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="content-card" style="width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border-radius: 16px; overflow: hidden; background: white;">
        <div class="card-title-bar" style="background: #0f172a; color: white; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color: white; font-size: 17px; margin: 0;">🛡️ Contact Portal Administrator</h3>
            <button type="button" onclick="closeContactAdminModal()" style="background: none; border: none; color: #94a3b8; font-size: 22px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <p style="font-size: 13px; color: #475569; margin-bottom: 18px; line-height: 1.6;">
                If you have forgotten your password or cannot change your registered mobile number, the Portal Administrator can directly reset your credentials or update your number without requiring your old password.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                <div style="margin-bottom: 14px; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        📞
                    </div>
                    <div>
                        <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700;">Helpline Number</span>
                        <div style="font-size: 16px; font-weight: 800; color: #0f172a;">
                            <a href="tel:+919613167470" style="color: #0284c7; text-decoration: none;">+91 9613167470</a>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 14px; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        💬
                    </div>
                    <div>
                        <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700;">WhatsApp Support</span>
                        <div style="font-size: 14px; font-weight: 700;">
                            <a href="https://wa.me/919613167470?text=<?= urlencode('Namaste Admin, I need assistance updating my registered mobile number on Digital Sewa Assam for account: ' . $user['name'] . ' (' . $user['mobile'] . ')') ?>" target="_blank" style="color: #16a34a; text-decoration: none;">
                                Chat on WhatsApp (+91 9613167470) ↗
                            </a>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #fdf2f8; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        ✉️
                    </div>
                    <div>
                        <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700;">Support Email</span>
                        <div style="font-size: 14px; font-weight: 600; color: #0f172a;">
                            <a href="mailto:digitalesewa.csc@gmail.com" style="color: #0284c7; text-decoration: none;">digitalesewa.csc@gmail.com</a>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="button" onclick="closeContactAdminModal()" class="btn secondary" style="padding: 8px 18px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
<script>
function openContactAdminModal() {
    document.getElementById('contactAdminModal').style.display = 'flex';
}
function closeContactAdminModal() {
    document.getElementById('contactAdminModal').style.display = 'none';
}
window.addEventListener('click', function(e) {
    const modal = document.getElementById('contactAdminModal');
    if (e.target === modal) {
        closeContactAdminModal();
    }
});
</script>
</body>
</html>
