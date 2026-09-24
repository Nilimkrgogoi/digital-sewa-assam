<?php
// admin/settings.php - Platform Registration Fee, Bank Details & UPI QR Code Settings
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $platformFee = (float)($_POST['platform_fee'] ?? 0.00);
    $bankName = sanitize($_POST['bank_name'] ?? '');
    $bankHolder = sanitize($_POST['bank_account_holder'] ?? '');
    $bankAccNo = sanitize($_POST['bank_account_no'] ?? '');
    $bankIfsc = strtoupper(sanitize($_POST['bank_ifsc'] ?? ''));
    $upiId = sanitize($_POST['upi_id'] ?? '');

    update_portal_setting('platform_fee', number_format($platformFee, 2, '.', ''));
    update_portal_setting('bank_name', $bankName);
    update_portal_setting('bank_account_holder', $bankHolder);
    update_portal_setting('bank_account_no', $bankAccNo);
    update_portal_setting('bank_ifsc', $bankIfsc);
    update_portal_setting('upi_id', $upiId);

    // Handle QR code image upload if provided
    if (isset($_FILES['qr_code_image']) && $_FILES['qr_code_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_code_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowedExts)) {
            $destDir = __DIR__ . '/../uploads/qr/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $targetFileName = 'admin_qr_' . time() . '.' . $ext;
            $targetPath = $destDir . $targetFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $relPath = 'uploads/qr/' . $targetFileName;
                update_portal_setting('upi_qr_image', $relPath);
            } else {
                $err = "Could not save uploaded QR code file.";
            }
        } else {
            $err = "Invalid image format for QR code. Please upload PNG, JPG, or WEBP.";
        }
    }

    if (empty($err)) {
        $msg = "Platform Fee, Bank Account, and UPI Settings updated successfully!";
    }
}

// Fetch current settings
$currentSettings = get_portal_settings();
$platformFee = $currentSettings['platform_fee'] ?? '50.00';
$bankName = $currentSettings['bank_name'] ?? '';
$bankHolder = $currentSettings['bank_account_holder'] ?? '';
$bankAccNo = $currentSettings['bank_account_no'] ?? '';
$bankIfsc = $currentSettings['bank_ifsc'] ?? '';
$upiId = $currentSettings['upi_id'] ?? '';
$qrImage = $currentSettings['upi_qr_image'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment & Platform Settings - Digital Sewa Assam Admin</title>
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
            <a href="settings.php" style="color: #38bdf8; font-weight: 700;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #cbd5e1;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #94a3b8; font-size: 13px;">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 30px 0 60px; max-width: 900px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #000000; margin: 0 0 6px;">
                ⚙️ Platform Fee & Payment Receiver Settings
            </h1>
            <p style="color: #151b24; font-size: 14px; margin: 0;">
                Configure the mandatory platform registration fee and your receiving Bank Account / UPI / QR Code details.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="export_applications.php?format=xls" class="btn success sm" style="background: #15803d; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                📊 Export Applications (.XLS)
            </a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">⚠️ <?= $err ?></div>
    <?php endif; ?>

    <form action="settings.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_settings">

        <!-- 1. PLATFORM REGISTRATION FEE -->
        <div class="content-card" style="margin-bottom: 24px; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="font-size: 26px;">💰</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; color: #000000;">Platform Fee for User Account Creation</h3>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #484874;">
                        Citizens will be required to pay this amount when creating their portal account. Enter 0.00 to make registration free.
                    </p>
                </div>
            </div>

            <div class="form-row" style="max-width: 450px;">
                <div class="form-group" style="margin: 0;">
                    <label for="platform_fee" style="font-weight: 700; font-size: 14px;">Platform Fee Amount (₹) *</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b; font-size: 16px;">₹</span>
                        <input type="number" step="0.01" min="0" id="platform_fee" name="platform_fee" value="<?= htmlspecialchars($platformFee) ?>" required style="padding-left: 32px; font-size: 16px; font-weight: 700; width: 100%;">
                    </div>
                    <small style="color: #64748b; font-size: 12px; margin-top: 4px; display: block;">
                        Current registration fee charged: <b>₹<?= number_format((float)$platformFee, 2) ?></b>
                    </small>
                </div>
            </div>
        </div>

        <!-- 2. ADMIN BANK ACCOUNT DETAILS -->
        <div class="content-card" style="margin-bottom: 24px; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="font-size: 26px;">🏦</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; color: #000000;">Admin Bank Account Details for Receiving Payments</h3>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #38485f;">
                        Displayed to citizens on the payment page for direct IMPS/NEFT/RTGS bank transfers.
                    </p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="bank_name" style="font-weight: 600; font-size: 13px;">Bank Name *</label>
                    <input type="text" id="bank_name" name="bank_name" value="<?= htmlspecialchars($bankName) ?>" placeholder="e.g. State Bank of India" required>
                </div>
                <div class="form-group">
                    <label for="bank_account_holder" style="font-weight: 600; font-size: 13px;">Account Holder Name *</label>
                    <input type="text" id="bank_account_holder" name="bank_account_holder" value="<?= htmlspecialchars($bankHolder) ?>" placeholder="e.g. Digital Sewa Kendra Assam" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="bank_account_no" style="font-weight: 600; font-size: 13px;">Account Number *</label>
                    <input type="text" id="bank_account_no" name="bank_account_no" value="<?= htmlspecialchars($bankAccNo) ?>" placeholder="e.g. 389201948201" required>
                </div>
                <div class="form-group">
                    <label for="bank_ifsc" style="font-weight: 600; font-size: 13px;">IFSC Code *</label>
                    <input type="text" id="bank_ifsc" name="bank_ifsc" value="<?= htmlspecialchars($bankIfsc) ?>" placeholder="e.g. SBIN0000123" required style="text-transform: uppercase;">
                </div>
            </div>
        </div>

        <!-- 3. UPI ID & QR CODE -->
        <div class="content-card" style="margin-bottom: 24px; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <div style="font-size: 26px;">📱</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; color: #040505;">UPI ID & Payment QR Code</h3>
                    <p style="margin: 2px 0 0; font-size: 13px; color: #2e3742;">
                        Citizens can scan this QR code using PhonePe, Google Pay, Paytm, or BHIM to pay instantly.
                    </p>
                </div>
            </div>

            <div class="form-group" style="max-width: 500px; margin-bottom: 20px;">
                <label for="upi_id" style="font-weight: 600; font-size: 13px;">Admin UPI ID (VPA) *</label>
                <input type="text" id="upi_id" name="upi_id" value="<?= htmlspecialchars($upiId) ?>" placeholder="e.g. 9613167470@upi or digitalsewa@okaxis" required>
                <small style="font-size: 11px; color: #9099a7; margin-top: 4px; display: block;">
                    Citizens can copy this UPI ID to pay from any UPI app.
                </small>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; align-items: start; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                <div>
                    <label for="qr_code_image" style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 6px;">
                        Upload Official UPI QR Code Image
                    </label>
                    <input type="file" id="qr_code_image" name="qr_code_image" accept=".png,.jpg,.jpeg,.webp">
                    <small style="font-size: 11px; color: #64748b; margin-top: 6px; display: block;">
                        Upload your printed GPay, PhonePe, or Paytm QR code screenshot. Formats: PNG, JPG, WEBP.
                    </small>
                </div>

                <div style="text-align: center; background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px;">
                    <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px;">
                        Current Active QR Code
                    </div>
                    <?php if (!empty($qrImage) && file_exists(__DIR__ . '/../' . $qrImage)): ?>
                        <img src="../<?= htmlspecialchars($qrImage) ?>" alt="Admin UPI QR Code" style="max-width: 180px; height: auto; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="font-size: 11px; color: #16a34a; font-weight: 700; margin-top: 6px;">✅ Custom QR Uploaded</div>
                    <?php else: ?>
                        <!-- Fallback Dynamic UPI QR Code -->
                        <?php 
                        $upiString = "upi://pay?pa=" . urlencode($upiId) . "&pn=" . urlencode($bankHolder ?: 'Digital Sewa Assam') . "&cu=INR";
                        $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($upiString);
                        ?>
                        <img src="<?= $qrApiUrl ?>" alt="Auto-generated UPI QR Code" style="width: 180px; height: 180px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 11px; color: #64748b; margin-top: 6px;">(Auto-generated from UPI ID: <?= htmlspecialchars($upiId) ?>)</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="submit" class="btn primary" style="background: #7c3aed; font-weight: 700; padding: 12px 30px; font-size: 15px;">
                💾 Save Payment & Platform Settings
            </button>
        </div>
    </form>
</div>

<?= portal_footer_html(true) ?>

<script src="../assets/js/script.js"></script>
</body>
</html>
