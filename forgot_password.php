<?php
// forgot_password.php - Citizen Password Recovery & Direct Admin Helpline
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$successMsg = '';
$errorMsg = '';
$waRedirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $name = sanitize($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $note = sanitize($_POST['note'] ?? '');

    if (empty($name)) {
        $errorMsg = "Please enter your full name.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errorMsg = "Please enter a valid 10-digit Indian mobile number.";
    } else {
        // Check if user exists in database
        $stmt = $pdo->prepare("SELECT id, name, mobile FROM users WHERE mobile = ? LIMIT 1");
        $stmt->execute([$mobile]);
        $existingUser = $stmt->fetch();

        $message = "PASSWORD RESET REQUEST: Citizen $name (Mobile: $mobile) is requesting a password reset.";
        if (!empty($note)) {
            $message .= " Additional Note: " . $note;
        }

        // Store inquiry in database for admin dashboard
        $inqStmt = $pdo->prepare("INSERT INTO contact_inquiries (name, mobile, service_name, message, status) VALUES (?, ?, ?, ?, 'new')");
        $inqStmt->execute([$name, $mobile, 'Password Reset Request', $message]);

        // WhatsApp direct link for citizen
        $adminPhone = '919613167470';
        $waText = "Namaste Digital Sewa Assam Admin, I forgot my login password.\n\nName: " . $name . "\nRegistered Mobile: " . $mobile . "\n\nPlease help me reset my password.";
        $waRedirectUrl = "https://wa.me/" . $adminPhone . "?text=" . urlencode($waText);

        $successMsg = "Your password reset request has been submitted to the administrator! The administrator will review and reset your account credentials shortly. You can also connect directly on WhatsApp right now.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password & Account Recovery - Digital Sewa Assam</title>
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
            <a href="index.php#contact">Contact</a>
            <a href="login.php" class="login-btn">Sign In</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 50px 0 80px;">
    <div class="container" style="max-width: 720px;">
        <div class="login-box" style="max-width: 100%; padding: 36px 32px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); background: white;">
            
            <div style="text-align: center; margin-bottom: 28px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; font-size: 30px; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);">
                    🔐
                </div>
                <span class="badge" style="color: #2563eb; background: #eff6ff; border-color: #bfdbfe; font-weight: 700;">
                    Citizen Account Recovery
                </span>
                <h2 style="font-size: 26px; font-weight: 800; margin: 10px 0 6px; color: #0f172a;">
                    Forgot Your Password?
                </h2>
                <p style="color: #64748b; font-size: 14px; line-height: 1.5; max-width: 540px; margin: 0 auto;">
                    For citizen security, password resets are handled directly by the <b>Digital Sewa Assam Administrator</b>. Please connect with the admin via direct call or WhatsApp, and your password will be reset immediately.
                </p>
            </div>

            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 10px; padding: 16px; margin-bottom: 24px;">
                    <div style="font-weight: 700; font-size: 15px; margin-bottom: 6px;">✓ Request Sent Successfully!</div>
                    <div style="font-size: 13px; line-height: 1.5;"><?= $successMsg ?></div>
                    <?php if (!empty($waRedirectUrl)): ?>
                        <div style="margin-top: 14px;">
                            <a href="<?= $waRedirectUrl ?>" target="_blank" class="btn success" style="background: #25d366; color: white; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; font-weight: 700; border-radius: 8px; font-size: 14px;">
                                💬 Open WhatsApp Chat with Admin →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    ⚠️ <?= $errorMsg ?>
                </div>
            <?php endif; ?>

            <!-- DIRECT ADMIN CONTACT CHANNELS -->
            <div style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 14px; padding: 22px; margin-bottom: 28px;">
                <h4 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 8px;">
                    <span>📞 Administrator Direct Contact Details</span>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px;">
                    <!-- Call Card -->
                    <div style="background: white; border: 1px solid #cbd5e1; border-radius: 10px; padding: 16px; text-align: center;">
                        <div style="font-size: 24px; margin-bottom: 6px;">📞</div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Direct Phone Call</div>
                        <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 4px 0 12px;">+91 9613167470</div>
                        <a href="tel:+919613167470" class="btn primary sm" style="width: 100%; justify-content: center; text-decoration: none; font-weight: 700;">
                            Call Admin Now
                        </a>
                    </div>

                    <!-- WhatsApp Card -->
                    <div style="background: white; border: 1px solid #cbd5e1; border-radius: 10px; padding: 16px; text-align: center;">
                        <div style="font-size: 24px; margin-bottom: 6px;">💬</div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">WhatsApp Support</div>
                        <div style="font-size: 18px; font-weight: 800; color: #15803d; margin: 4px 0 12px;">+91 9613167470</div>
                        <a href="https://wa.me/919613167470?text=Hello%20Digital%20Sewa%20Assam%20Admin,%20I%20forgot%20my%20password.%20Please%20help%20me%20reset%20it." target="_blank" class="btn success sm" style="background: #25d366; width: 100%; justify-content: center; text-decoration: none; font-weight: 700; color: white;">
                            Chat on WhatsApp
                        </a>
                    </div>
                </div>

                <div style="margin-top: 14px; font-size: 12px; color: #64748b; text-align: center;">
                    ✉️ Email: <a href="mailto:digitalesewa.csc@gmail.com" style="color: #0284c7; text-decoration: underline;">digitalesewa.csc@gmail.com</a> &nbsp;•&nbsp; 📍 Digital Sewa Kendra, Nagaon, Assam
                </div>
            </div>

            <!-- RESET REQUEST FORM -->
            <div style="border-top: 1px solid #e2e8f0; padding-top: 24px;">
                <h4 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 8px;">
                    Or Submit a Password Reset Request
                </h4>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                    Provide your registered mobile number below. The admin will reset your password and send you the new login details:
                </p>

                <form action="forgot_password.php" method="POST">
                    <input type="hidden" name="action" value="request_reset">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="req_name" style="font-size: 13px; font-weight: 600;">Your Full Name *</label>
                        <input type="text" id="req_name" name="name" required placeholder="e.g. Rupesh Kalita" style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="req_mobile" style="font-size: 13px; font-weight: 600;">Registered Mobile Number *</label>
                        <input type="tel" id="req_mobile" name="mobile" pattern="[6-9][0-9]{9}" maxlength="10" required placeholder="e.g. 9876543210" style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label for="req_note" style="font-size: 13px; font-weight: 600;">Additional Note (Optional)</label>
                        <input type="text" id="req_note" name="note" placeholder="e.g. Please send new password on WhatsApp" style="width: 100%;">
                    </div>

                    <button type="submit" class="btn primary" style="width: 100%; padding: 13px; font-weight: 700; font-size: 15px;">
                        Submit Reset Request to Admin →
                    </button>
                </form>
            </div>

            <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #e2e8f0; text-align: center;">
                <a href="login.php" style="color: var(--primary); font-size: 14px; font-weight: 700; text-decoration: none;">
                    ← Back to Login
                </a>
            </div>

        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
</body>
</html>
