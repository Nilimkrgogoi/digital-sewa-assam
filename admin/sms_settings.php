<?php
// admin/sms_settings.php - Manage SMS Gateway Provider & API Key
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

// Handle save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sms_settings') {
    $provider = trim($_POST['provider'] ?? 'fast2sms');
    $apiKey = trim($_POST['api_key'] ?? '');
    $senderId = trim($_POST['sender_id'] ?? 'TXTIND');

    try {
        $stmt = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['sms_gateway_provider', $provider]);
        $stmt->execute(['sms_gateway_api_key', $apiKey]);
        $stmt->execute(['sms_sender_id', $senderId]);
        $msg = "SMS Gateway settings updated successfully!";
    } catch (Exception $e) {
        $err = "Error saving settings: " . $e->getMessage();
    }
}

// Handle test SMS dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_sms') {
    $testMobile = trim($_POST['test_mobile'] ?? '');
    if (!preg_match('/^[6-9]\d{9}$/', $testMobile)) {
        $err = "Please enter a valid 10-digit Indian mobile number for test dispatch.";
    } else {
        $testOtp = (string)random_int(100000, 999999);
        $res = send_mobile_otp($testMobile, $testOtp);
        if (!empty($res['delivered']) && $res['delivered'] === true) {
            $msg = "🎉 Test SMS successfully dispatched to +91 $testMobile! Verification Code: $testOtp";
        } elseif (!empty($res['has_api_key']) === false) {
            $err = "Cannot dispatch live SMS: No SMS Gateway API Key configured. Please enter your API key below.";
        } else {
            $err = "SMS Gateway Error: " . ($res['message'] ?? 'Failed to deliver SMS. Check API key balance.');
        }
    }
}

// Load current settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$currentSettings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$provider = $currentSettings['sms_gateway_provider'] ?? 'fast2sms';
$apiKey = $currentSettings['sms_gateway_api_key'] ?? '';
$senderId = $currentSettings['sms_sender_id'] ?? 'TXTIND';

// Read recent OTP log lines
$logFile = __DIR__ . '/../uploads/otp_log.txt';
$logLines = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice(array_reverse($lines), 0, 15);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway Configuration - Digital Sewa Assam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ADMIN HEADER -->
<header class="header" style="background: #0f172a; border-bottom: 2px solid #1e293b; padding: 4px 0;">
    <div class="container nav">
        <?= logo_html_admin('dashboard.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" style="color: #ffffff; font-size: 24px;">☰</button>
        <nav id="navbar">
            <a href="dashboard.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">Dashboard</a>
            <a href="applications.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">Applications</a>
            <a href="users.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">Users</a>
            <a href="services.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">Services</a>
            <a href="settings.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">⚙️ Settings</a>
            <a href="sms_settings.php" style="color: #ffffff !important; font-weight: 700; background: #0284c7; padding: 6px 14px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">SMS Settings</a>
            <a href="../index.php" target="_blank" style="color: #38bdf8 !important; font-weight: 600; background: rgba(56, 189, 248, 0.1); padding: 5px 10px; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3);">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn" style="background: #dc2626 !important; color: #ffffff !important; font-weight: 700; padding: 6px 14px; border-radius: 6px;">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 30px 0 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #0f172a;">SMS Gateway Configuration</h1>
            <p style="color: #64748b; font-size: 14px;">Configure your SMS API provider so OTP codes are sent directly to users' mobile phones.</p>
        </div>
        <div>
            <span class="badge" style="background: <?= !empty($apiKey) ? '#dcfce7; color: #166534;' : '#fee2e2; color: #991b1b;' ?> font-weight: 700;">
                <?= !empty($apiKey) ? '● Live Gateway Active' : '⚠️ No API Key Configured' ?>
            </span>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">⚠️ <?= $err ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
        <!-- GATEWAY CONFIG FORM -->
        <div class="content-card">
            <div class="card-title-bar">
                <h3>SMS Provider API Key</h3>
            </div>
            <div style="padding: 24px;">
                <form action="sms_settings.php" method="POST">
                    <input type="hidden" name="action" value="save_sms_settings">

                    <div class="form-group">
                        <label for="provider">SMS Provider</label>
                        <select name="provider" id="provider" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                            <option value="fast2sms" <?= $provider === 'fast2sms' ? 'selected' : '' ?>>Fast2SMS (Recommended for India)</option>
                            <option value="custom" <?= $provider === 'custom' ? 'selected' : '' ?>>Custom HTTP SMS Gateway</option>
                        </select>
                        <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">
                            Fast2SMS provides instant quick OTP delivery across all Indian networks.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="api_key">Provider Authorization / API Key</label>
                        <input 
                            type="text" 
                            name="api_key" 
                            id="api_key" 
                            value="<?= htmlspecialchars($apiKey) ?>" 
                            placeholder="Enter your Fast2SMS API Key..."
                            style="font-family: monospace; width: 100%;"
                        >
                        <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">
                            Sign up at <a href="https://www.fast2sms.com" target="_blank" style="color: var(--primary); font-weight: 600;">Fast2SMS.com</a> and copy your API authorization key from Dev API section.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="sender_id">Sender ID / Route</label>
                        <input 
                            type="text" 
                            name="sender_id" 
                            id="sender_id" 
                            value="<?= htmlspecialchars($senderId) ?>" 
                            placeholder="e.g. TXTIND or OTP"
                            style="width: 100%;"
                        >
                    </div>

                    <button type="submit" class="btn primary" style="width: 100%; background: #7c3aed; padding: 12px; font-weight: 700;">
                        Save SMS Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- TEST DISPATCH CARD -->
        <div class="content-card">
            <div class="card-title-bar">
                <h3>Test Live SMS Delivery</h3>
            </div>
            <div style="padding: 24px;">
                <p style="font-size: 14px; color: #475569; margin-bottom: 16px;">
                    Send a test OTP to your mobile phone right now to verify that your SMS gateway sends text messages successfully.
                </p>

                <form action="sms_settings.php" method="POST">
                    <input type="hidden" name="action" value="test_sms">

                    <div class="form-group">
                        <label for="test_mobile">Your 10-Digit Mobile Number</label>
                        <input 
                            type="tel" 
                            name="test_mobile" 
                            id="test_mobile" 
                            placeholder="e.g. 9876543210" 
                            maxlength="10" 
                            pattern="[6-9][0-9]{9}" 
                            required 
                            style="width: 100%;"
                        >
                    </div>

                    <button type="submit" class="btn success" style="width: 100%; background: #16a34a; padding: 12px; font-weight: 700;">
                        📲 Send Test SMS to Mobile
                    </button>
                </form>

                <div style="margin-top: 20px; padding: 14px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe; font-size: 13px; color: #1e40af;">
                    <b>ℹ️ How Real SMS Delivery Works:</b>
                    <ol style="margin: 8px 0 0 16px; padding: 0; line-height: 1.5;">
                        <li>Get a free Fast2SMS account at <a href="https://www.fast2sms.com" target="_blank" style="color: #1d4ed8; text-decoration: underline;">fast2sms.com</a>.</li>
                        <li>Copy your API Key from Dev API.</li>
                        <li>Paste it into the API Key box on the left and click Save.</li>
                        <li>Test with your mobile number to confirm instant delivery!</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT OTP DISPATCH LOG -->
    <div class="content-card" style="margin-top: 30px;">
        <div class="card-title-bar">
            <h3>Recent OTP Dispatch Audit Log</h3>
        </div>
        <div style="padding: 20px;">
            <?php if (empty($logLines)): ?>
                <p style="color: #94a3b8; font-size: 14px;">No OTP dispatches logged yet.</p>
            <?php else: ?>
                <pre style="background: #0f172a; color: #38bdf8; padding: 16px; border-radius: 8px; font-size: 12px; overflow-x: auto; max-height: 250px;"><?php foreach ($logLines as $l) { echo htmlspecialchars($l) . "\n"; } ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
