<?php
// register.php - Citizen Registration with Contact OTP Verification & Admin Approval Workflow
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$settings = get_portal_settings();
$platformFee = floatval($settings['platform_fee'] ?? 50.00);
$bankName = $settings['bank_name'] ?? 'State Bank of India';
$bankHolder = $settings['bank_account_holder'] ?? 'Digital Sewa Assam Admin';
$bankAcc = $settings['bank_account_no'] ?? '';
$bankIfsc = $settings['bank_ifsc'] ?? '';
$upiId = $settings['upi_id'] ?? 'assam.digital@upi';

// Dynamic UPI URI with the exact platform fee amount
$formattedFee = number_format($platformFee, 2, '.', '');
$payeeName = ($upiId === 'ju152@cnrb') ? 'NIKUMONI BORAH' : ($bankHolder ?: 'Digital Sewa Assam Admin');
$upiPayUri = "upi://pay?pa=" . $upiId . "&pn=" . urlencode($payeeName) . "&am=" . $formattedFee . "&cu=INR&tn=" . urlencode("PlatformFee");
$dynamicQrCodeSrc = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=" . urlencode($upiPayUri);

$error = '';
$accountSubmittedProcessing = false;
$registeredApplicantName = '';
$registeredMobile = '';
$registeredTxn = '';
$registeredFee = $platformFee;

// AJAX Endpoints for Mobile Number OTP Verification
if (isset($_REQUEST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['ajax_action'];
    $mobile = sanitize($_REQUEST['mobile'] ?? '');

    if ($action === 'send_otp') {
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.']);
            exit;
        }

        // Check if already registered
        $chk = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
        $chk->execute([$mobile]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This mobile number is already registered! Please log in.']);
            exit;
        }

        // Generate 6-digit OTP
        $otp = (string)rand(100000, 999999);
        $_SESSION['reg_otp'] = $otp;
        $_SESSION['reg_otp_mobile'] = $mobile;
        $_SESSION['reg_otp_time'] = time();

        echo json_encode([
            'success' => true,
            'otp' => $otp,
            'message' => "Verification OTP sent to +91 $mobile"
        ]);
        exit;
    }

    if ($action === 'verify_otp') {
        $enteredOtp = trim($_REQUEST['otp'] ?? '');
        $savedOtp = $_SESSION['reg_otp'] ?? '';
        $savedMobile = $_SESSION['reg_otp_mobile'] ?? '';
        $savedTime = $_SESSION['reg_otp_time'] ?? 0;

        if (empty($enteredOtp) || empty($savedOtp)) {
            echo json_encode(['success' => false, 'message' => 'Please click Send OTP first.']);
            exit;
        }

        if (time() - $savedTime > 600) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired (valid 10 mins). Please request a new OTP.']);
            exit;
        }

        if ($mobile !== $savedMobile || $enteredOtp !== $savedOtp) {
            echo json_encode(['success' => false, 'message' => 'Incorrect 6-digit OTP code entered. Please check and try again.']);
            exit;
        }

        // Mark mobile as verified in session
        $_SESSION['reg_mobile_verified'] = $mobile;
        echo json_encode([
            'success' => true,
            'message' => "Contact number +91 $mobile verified successfully!"
        ]);
        exit;
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authProvider = sanitize($_POST['auth_provider'] ?? 'email');
    $name = sanitize($_POST['name'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $platformFeeTxn = sanitize($_POST['platform_fee_txn'] ?? '');

    // Common Validation
    if (empty($name)) {
        $error = "Full Name is required.";
    } elseif (empty($mobile)) {
        $error = "10-Digit Aadhaar-linked Mobile Number is strictly required.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $error = "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 (registered with your Aadhaar).";
    } elseif (empty($email)) {
        $error = "Email address is required and must be unique.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address (e.g. citizen@example.com).";
    } elseif (empty($_SESSION['reg_mobile_verified']) || $_SESSION['reg_mobile_verified'] !== $mobile) {
        $error = "Mobile contact number verification is required! Please click 'Verify Number' and enter the 6-digit verification code.";
    } elseif ($platformFee > 0 && (empty($platformFeeTxn) || strlen(preg_replace('/[^0-9]/', '', $platformFeeTxn)) !== 12 || strlen($platformFeeTxn) !== 12)) {
        $error = "Platform fee payment reference (UTR) must be fixed at exactly 12 numeric digits (e.g. 412345678901), not allowed more or less than 12 digits.";
    } else {
        // Password validation for standard email registration
        if ($authProvider === 'email') {
            if (empty($password)) {
                $error = "Password is required for account creation.";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match. Please verify and re-type.";
            }
        }

        if (empty($error)) {
            // Strict check: Mobile number must be unique
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
            $checkStmt->execute([$mobile]);
            if ($checkStmt->fetch()) {
                $error = "⚠️ This mobile number (+91 {$mobile}) is already registered! Each citizen must have a unique mobile number. Please <a href='login.php' style='text-decoration: underline; font-weight: bold;'>Login here</a>.";
            } else {
                // Strict check: Email address must be unique
                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $checkEmail->execute([$email]);
                if ($checkEmail->fetch()) {
                    $error = "⚠️ This email address ({$email}) is already associated with an existing account! Each citizen must have a unique email address. Please use another email or <a href='login.php' style='text-decoration: underline; font-weight: bold;'>Login here</a>.";
                }

                if (empty($error)) {
                    // Password generation
                    $passToHash = ($authProvider === 'google') ? bin2hex(random_bytes(10)) : $password;
                    $hash = password_hash($passToHash, PASSWORD_BCRYPT);

                    // Account is inserted in 'processing' status pending admin approval of UTR
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (name, mobile, email, password, raw_password, role, status, auth_provider, platform_fee_paid, platform_fee_txn, platform_fee_status) 
                        VALUES (?, ?, ?, ?, ?, 'user', 'processing', ?, 0, ?, 'pending')
                    ");

                    try {
                        $insertStmt->execute([
                            $name, 
                            $mobile, 
                            $email, 
                            $hash, 
                            $passToHash,
                            $authProvider, 
                            $platformFeeTxn
                        ]);

                        // Clear OTP verification session
                        unset($_SESSION['reg_mobile_verified']);
                        unset($_SESSION['reg_otp']);
                        unset($_SESSION['reg_otp_mobile']);

                        $accountSubmittedProcessing = true;
                        $registeredApplicantName = $name;
                        $registeredMobile = $mobile;
                        $registeredTxn = $platformFeeTxn;
                        $registeredFee = $platformFee;

                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000') {
                            if (strpos($e->getMessage(), 'mobile') !== false) {
                                $error = "⚠️ An account with this mobile number (+91 {$mobile}) already exists. Please log in.";
                            } elseif (strpos($e->getMessage(), 'email') !== false) {
                                $error = "⚠️ An account with this email address ({$email}) already exists. Please use another email.";
                            } else {
                                $error = "Duplicate entry detected. Please ensure your mobile number and email address are unique.";
                            }
                        } else {
                            $error = "Registration failed: " . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Citizen Account - Digital Sewa Assam</title>
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
            <a href="index.php#services">Services</a>
            <a href="track.php">Track Status</a>
            <a href="login.php" class="login-btn">Login</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 50px 0; flex: 1;">
    <div class="container">

        <?php if ($accountSubmittedProcessing): ?>
            <!-- ACCOUNT IN PROCESSING CONFIRMATION CARD -->
            <div class="content-card" style="max-width: 620px; margin: 20px auto; text-align: center; padding: 45px 30px; border: 2px solid #f59e0b; background: #ffffff; box-shadow: 0 15px 40px rgba(0,0,0,0.08);">
                <div style="font-size: 56px; margin-bottom: 14px;">⏳</div>
                <span class="badge" style="background: #fef3c7; color: #b45309; border-color: #fde68a; font-size: 13px; font-weight: 800; padding: 6px 14px; margin-bottom: 14px;">
                    Account In Processing
                </span>
                <h2 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 10px 0;">
                    Registration Submitted Successfully!
                </h2>
                <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                    Namaste <b><?= htmlspecialchars($registeredApplicantName) ?></b>, your account details and verified contact number (+91 <?= htmlspecialchars($registeredMobile) ?>) have been registered.
                </p>
                
                <div style="background: #fffbeb; border: 1.5px solid #f59e0b; border-radius: 12px; padding: 20px 22px; text-align: left; margin-bottom: 28px;">
                    <div style="font-size: 14px; color: #92400e; font-weight: 800; margin-bottom: 8px;">
                        ⚡ Payment UTR Submitted for Administrator Approval:
                    </div>
                    <div style="font-size: 13px; color: #1e293b; line-height: 1.8;">
                        <b>Submitted UTR:</b> <code style="font-family: monospace; font-size: 14px; font-weight: 800; color: #0284c7; background: #ffffff; padding: 3px 8px; border-radius: 5px; border: 1px solid #cbd5e1;"><?= htmlspecialchars($registeredTxn) ?></code><br>
                        <b>Onboarding Fee:</b> ₹<?= number_format($registeredFee, 2) ?><br>
                        <b>Current Status:</b> <span style="background: #fef3c7; color: #92400e; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 999px;">⏳ Awaiting Admin Validation</span>
                    </div>
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed rgba(245, 158, 11, 0.4); font-size: 12px; color: #64748b; line-height: 1.6;">
                        📌 <b>What happens now?</b><br>
                        The portal administrator will check your payment UTR against the bank statement. Once approved, your account will be activated and you will be able to log in and apply for services.
                    </div>
                </div>

                <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                    <a href="login.php" class="btn primary" style="padding: 12px 24px; font-weight: 700;">
                        Proceed to Login Page →
                    </a>
                    <a href="index.php" class="btn secondary" style="padding: 12px 24px;">
                        Back to Home
                    </a>
                </div>
            </div>

        <?php else: ?>

            <div class="auth-box">
                <!-- LEFT COLUMN: BENEFITS & INFO -->
                <div class="auth-info" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <span class="badge" style="color: #0284c7; background: #e0f2fe; border-color: #bae6fd; margin-bottom: 12px;">
                            Citizen Registration
                        </span>
                        <h2 style="font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1.2; margin: 10px 0 14px;">
                            Join Digital Sewa Assam
                        </h2>
                        <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 24px;">
                            Create your verified citizen account to apply for government services, upload documents, track real-time applications, and download official certificates.
                        </p>

                        <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 30px;">
                            <div style="display: flex; gap: 12px; align-items: flex-start;">
                                <div style="color: #0284c7; font-size: 18px; line-height: 1;">✓</div>
                                <div>
                                    <b style="font-size: 14px; color: #0f172a;">Official Assam Services</b>
                                    <p style="font-size: 12px; color: #64748b; margin-top: 2px;">PAN Card, Voter Card, Driving Licence, e-Khajana, Sewa Setu & more.</p>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: flex-start;">
                                <div style="color: #0284c7; font-size: 18px; line-height: 1;">✓</div>
                                <div>
                                    <b style="font-size: 14px; color: #0f172a;">Live Application Tracking</b>
                                    <p style="font-size: 12px; color: #64748b; margin-top: 2px;">Track review progress, status changes, and officer remarks in real-time.</p>
                                </div>
                            </div>
                            <div style="display: flex; gap: 12px; align-items: flex-start;">
                                <div style="color: #0284c7; font-size: 18px; line-height: 1;">✓</div>
                                <div>
                                    <b style="font-size: 14px; color: #0f172a;">Verified Contact Protection</b>
                                    <p style="font-size: 12px; color: #64748b; margin-top: 2px;">Mobile numbers are verified via 6-digit OTP code to protect account security.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DIRECT GOOGLE SIGN-UP BUTTON -->
                    <div style="border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <button type="button" onclick="openGoogleModal()" class="btn" style="width: 100%; background: #ffffff; color: #1e293b !important; border: 1.5px solid #cbd5e1; font-weight: 700; font-size: 14px; padding: 12px 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                            <svg width="20" height="20" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            Sign up directly with Google
                        </button>
                    </div>
                </div>

                <!-- RIGHT COLUMN: REGISTRATION FORM -->
                <div>
                    <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
                        Citizen Account Details
                    </h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                        Please provide your authentic details. Aadhaar-linked mobile verification is mandatory.
                    </p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" style="margin-bottom: 18px;">
                            ⚠️ <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form action="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST" id="registerForm">
                        <input type="hidden" name="auth_provider" value="email">

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="name" style="font-weight: 700; font-size: 13px; color: #1e293b;">Full Name (as per Aadhaar / Official ID) *</label>
                            <input type="text" id="name" name="name" placeholder="e.g. Rupjyoti Sarma" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" style="width: 100%;">
                        </div>

                        <!-- MOBILE NUMBER FIELD WITH OTP VERIFICATION -->
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="mobile" style="font-weight: 700; font-size: 13px; color: #1e293b;">10-Digit Aadhaar-Linked Mobile Number *</label>
                            <div class="mobile-verify-group">
                                <input type="tel" id="mobile" name="mobile" maxlength="10" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" required value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" style="flex: 1;" oninput="handleMobileInputChanged()">
                                <button type="button" id="btnSendOtp" onclick="sendMobileOtp()" class="btn primary sm" style="white-space: nowrap; font-weight: 700; padding: 10px 14px;">
                                    📲 Verify Number
                                </button>
                            </div>
                            <small style="font-size: 11px; color: #64748b; display: block; margin-top: 4px;">
                                Must be an active 10-digit Indian mobile number registered with your Aadhaar Card.
                            </small>

                            <!-- OTP INPUT BOX (Revealed when OTP is sent) -->
                            <div id="otpVerificationBox" style="display: none; margin-top: 10px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px 14px;">
                                <div id="otpSimulationBanner" style="font-size: 12px; color: #0284c7; font-weight: 700; margin-bottom: 8px;"></div>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <input type="text" id="otpInput" maxlength="6" placeholder="Enter 6-Digit OTP" style="font-family: monospace; font-size: 15px; font-weight: 800; letter-spacing: 2px; width: 140px; text-align: center; max-width: 100%;">
                                    <button type="button" onclick="verifyMobileOtp()" class="btn success sm" style="font-weight: 700; padding: 8px 14px;">
                                        ✓ Confirm OTP
                                    </button>
                                </div>
                            </div>

                            <!-- VERIFIED BADGE -->
                            <div id="otpVerifiedBadge" style="display: none; margin-top: 8px; font-size: 12px; color: #16a34a; font-weight: 700;">
                                ✓ Mobile Number Verified (+91 <span id="verifiedMobileText"></span>)
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="email" style="font-weight: 700; font-size: 13px; color: #1e293b;">Email Address * (Unique & Required)</label>
                            <input type="email" id="email" name="email" placeholder="e.g. yourname@gmail.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" style="width: 100%;">
                            <small style="font-size: 11px; color: #64748b; display: block; margin-top: 3px;">
                                Each citizen must provide a unique email address for notifications, recovery, and security alerts.
                            </small>
                        </div>

                        <div class="form-row-2col" style="margin-bottom: 15px;">
                            <div class="form-group" style="margin: 0;">
                                <label for="password" style="font-weight: 700; font-size: 13px; color: #1e293b;">Create Password *</label>
                                <input type="password" id="password" name="password" minlength="6" placeholder="Min. 6 chars" required style="width: 100%;">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="confirmPassword" style="font-weight: 700; font-size: 13px; color: #1e293b;">Confirm Password *</label>
                                <input type="password" id="confirmPassword" name="confirm_password" minlength="6" placeholder="Repeat password" required style="width: 100%;">
                            </div>
                        </div>

                        <!-- PLATFORM REGISTRATION FEE SECTION (CLEAN DYNAMIC QR ONLY) -->
                        <?php if ($platformFee > 0): ?>
                            <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 14px; padding: 20px; margin: 24px 0; box-shadow: 0 8px 25px rgba(245, 158, 11, 0.12);">
                                
                                <!-- DIRECT ADMIN AMOUNT BANNER -->
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(245, 158, 11, 0.4); padding-bottom: 14px; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <span style="background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; border: 1px solid #fde68a; letter-spacing: 0.5px; display: inline-block;">
                                            ⚡ Platform Registration Fee
                                        </span>
                                        <h4 style="margin: 6px 0 0; color: #78350f; font-size: 16px; font-weight: 800;">
                                            Citizen Account Onboarding Fee
                                        </h4>
                                    </div>
                                    <div style="text-align: right; background: #ffffff; padding: 8px 16px; border-radius: 10px; border: 1.5px solid #f59e0b;">
                                        <span style="font-size: 10px; color: #92400e; font-weight: 800; display: block; text-transform: uppercase;">Amount to Pay:</span>
                                        <span style="font-size: 26px; font-weight: 900; color: #b45309; line-height: 1;">
                                            ₹<?= number_format($platformFee, 2) ?>
                                        </span>
                                        <small style="display: block; font-size: 10px; color: #166534; font-weight: 700; margin-top: 2px;">✓ Auto-Loaded in QR</small>
                                    </div>
                                </div>

                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #78350f; line-height: 1.5;">
                                    📢 <b>Mandatory Payment:</b> Scan the <b>Dynamic QR Code</b> below with <b>Google Pay, PhonePe, Paytm, or BHIM</b>. The exact fee of <b>₹<?= number_format($platformFee, 2) ?></b> is pre-loaded automatically.
                                </div>

                                <div class="qr-payment-grid">
                                    <div style="text-align: center;">
                                        <div style="display: inline-block; background: white; padding: 10px; border-radius: 10px; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                                            <img src="<?= htmlspecialchars($dynamicQrCodeSrc) ?>" alt="UPI QR Code" style="width: 165px; height: 165px; max-width: 100%; object-fit: contain; display: block; background: white;">
                                        </div>
                                        <div style="margin-top: 8px;">
                                            <span style="background: #dcfce7; color: #166534; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 5px; display: inline-block;">
                                                ✓ Pre-filled: ₹<?= number_format($platformFee, 2) ?>
                                            </span>
                                        </div>
                                        <small style="font-size: 11px; color: #64748b; display: block; margin-top: 4px;">
                                            Scan with GPay / PhonePe / Paytm
                                        </small>
                                    </div>

                                    <div style="font-size: 13px; color: #1e293b; line-height: 1.7; width: 100%;">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; justify-content: inherit;">
                                            <b>Portal UPI ID:</b> 
                                            <code style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; color: #0284c7; font-weight: 800; font-size: 13px; border: 1px solid #cbd5e1; word-break: break-all;"><?= htmlspecialchars($upiId) ?></code>
                                            <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($upiId) ?>'); alert('UPI ID copied: <?= htmlspecialchars($upiId) ?>');" class="btn secondary sm" style="padding: 2px 8px; font-size: 11px; background: white;">
                                                📋 Copy
                                            </button>
                                        </div>
                                        <div><b>Account Holder:</b> <?= htmlspecialchars($bankHolder) ?></div>
                                        <div><b>Bank:</b> <?= htmlspecialchars($bankName) ?></div>
                                        <?php if (!empty($bankAcc)): ?>
                                            <div><b>A/C No:</b> <?= htmlspecialchars($bankAcc) ?> &nbsp;|&nbsp; <b>IFSC:</b> <?= htmlspecialchars($bankIfsc) ?></div>
                                        <?php endif; ?>

                                        <!-- Mobile Direct Pay Button -->
                                        <div style="margin-top: 12px;">
                                            <a href="<?= htmlspecialchars($upiPayUri) ?>" class="btn" style="background: #0284c7; color: white; padding: 10px 16px; font-size: 13px; font-weight: 700; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; width: 100%; box-sizing: border-box; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                                                📱 Tap to Pay ₹<?= number_format($platformFee, 2) ?> via UPI App
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
                                    <label for="platform_fee_txn" style="font-weight: 800; font-size: 13px; color: #78350f;">
                                        12-Digit UPI Transaction ID / UTR Reference Number * (Fixed 12 Digits)
                                    </label>
                                    <input type="text" id="platform_fee_txn" name="platform_fee_txn" placeholder="e.g. 412345678901 (Fixed 12 Digits)" 
                                        required minlength="12" maxlength="12" pattern="[0-9]{12}" inputmode="numeric" 
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);" 
                                        value="<?= htmlspecialchars($_POST['platform_fee_txn'] ?? '') ?>" 
                                        style="background: #ffffff; border: 2px solid #f59e0b; font-family: monospace; font-size: 15px; font-weight: 700; width: 100%; color: #0f172a; letter-spacing: 1px;">
                                    <small style="font-size: 11px; color: #92400e; margin-top: 4px; display: block; font-weight: 600;">
                                        ⚠️ Required: Enter the 12-digit UTR reference ID (fixed 12 numeric digits, not allowed more or less than 12 digits).
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn primary" style="width: 100%; margin-top: 10px; padding: 14px; font-size: 15px; font-weight: 700;">
                            Create Citizen Account →
                        </button>

                        <p style="text-align: center; font-size: 13px; color: #94a3b8; margin-top: 16px;">
                            Already registered? <a href="login.php" style="color: var(--primary); font-weight: 700;">Login here</a>
                        </p>
                    </form>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- GOOGLE SIGN-UP MODAL -->
<div id="googleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(6px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div class="content-card" style="width: 100%; max-width: 500px; box-shadow: 0 25px 50px rgba(0,0,0,0.6); border-radius: 16px; overflow: hidden; background: #131b2e; max-height: 90vh; display: flex; flex-direction: column; border: 1px solid #232f48;">
        <div class="card-title-bar" style="background: #0b0f19; border-bottom: 1px solid #232f48; display: flex; align-items: center; justify-content: space-between; padding: 16px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <svg width="24" height="24" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <h3 style="font-size: 17px; font-weight: 800; color: #ffffff; margin: 0;">Sign Up with Google</h3>
            </div>
            <button type="button" onclick="closeGoogleModal()" style="background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <div style="padding: 22px; overflow-y: auto;">
            <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #4ade80;">
                🔒 <b>Verified Account Link:</b> Link your Google identity to Digital Sewa Assam. Aadhaar-linked mobile verification is mandatory.
            </div>

            <form action="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" method="POST" id="googleSignupForm">
                <input type="hidden" name="auth_provider" value="google">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="g_name" style="font-weight: 600; font-size: 13px;">Full Name (as on Google / Aadhaar) *</label>
                    <input type="text" id="g_name" name="name" placeholder="e.g. Rupjyoti Sarma" required style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="g_email" style="font-weight: 600; font-size: 13px;">Google Account Email Address *</label>
                    <input type="email" id="g_email" name="email" placeholder="e.g. yourname@gmail.com" required style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="g_mobile" style="font-weight: 700; font-size: 13px; color: #ffffff;">
                        10-Digit Aadhaar-Linked Mobile Number *
                    </label>
                    <div class="mobile-verify-group">
                        <input type="tel" id="g_mobile" name="mobile" maxlength="10" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" required style="flex: 1; border: 2px solid #3b82f6;">
                        <button type="button" id="g_btnSendOtp" onclick="sendGoogleMobileOtp()" class="btn primary sm" style="white-space: nowrap; font-weight: 700;">
                            📲 Verify
                        </button>
                    </div>

                    <div id="g_otpBox" style="display: none; margin-top: 10px; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 10px 12px;">
                        <div id="g_otpBanner" style="font-size: 12px; color: #38bdf8; font-weight: 700; margin-bottom: 6px;"></div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="g_otpInput" maxlength="6" placeholder="Enter 6-digit OTP" style="width: 140px; font-family: monospace; font-weight: 800; text-align: center; max-width: 100%;">
                            <button type="button" onclick="verifyGoogleMobileOtp()" class="btn success sm">✓ Confirm</button>
                        </div>
                    </div>

                    <div id="g_otpVerified" style="display: none; margin-top: 6px; font-size: 12px; color: #22c55e; font-weight: 700;">
                        ✓ Mobile Verified
                    </div>
                </div>

                <?php if ($platformFee > 0): ?>
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1.5px solid #f59e0b; border-radius: 12px; padding: 16px; margin-bottom: 18px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(245, 158, 11, 0.3); padding-bottom: 10px; margin-bottom: 12px;">
                            <div>
                                <span style="font-size: 11px; font-weight: 800; color: #fbbf24; text-transform: uppercase;">Required Fee:</span>
                                <h4 style="margin: 2px 0 0; color: #ffffff; font-size: 15px;">Account Setup Fee</h4>
                            </div>
                            <div style="text-align: right; background: #0b0f19; padding: 6px 12px; border-radius: 8px; border: 1.5px solid #f59e0b;">
                                <span style="font-size: 20px; font-weight: 900; color: #f59e0b; line-height: 1;">₹<?= number_format($platformFee, 2) ?></span>
                            </div>
                        </div>

                        <div class="qr-payment-grid-dark">
                            <div style="text-align: center;">
                                <div style="display: inline-block; background: white; padding: 6px; border-radius: 8px;">
                                    <img src="<?= htmlspecialchars($dynamicQrCodeSrc) ?>" alt="Dynamic QR Code" style="width: 125px; height: 125px; max-width: 100%; object-fit: contain; display: block; background: white;">
                                </div>
                            </div>
                            <div style="font-size: 12px; color: #f1f5f9; line-height: 1.6; width: 100%;">
                                <div><b>UPI ID:</b> <code style="background: #131b2e; padding: 2px 6px; border-radius: 4px; color: #38bdf8; font-weight: 800; word-break: break-all;"><?= htmlspecialchars($upiId) ?></code></div>
                                <div><b>Acc Holder:</b> <?= htmlspecialchars($bankHolder) ?></div>
                                <div style="margin-top: 8px;">
                                    <a href="<?= htmlspecialchars($upiPayUri) ?>" class="btn" style="background: #0284c7; color: white; padding: 8px 12px; font-size: 12px; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; width: 100%; box-sizing: border-box;">
                                        📱 Tap to Pay ₹<?= number_format($platformFee, 2) ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label for="g_txn" style="font-size: 11px; font-weight: 800; color: #fbbf24;">12-Digit UPI / Bank Transaction UTR Number * (Fixed 12 Digits)</label>
                            <input type="text" id="g_txn" name="platform_fee_txn" placeholder="Enter 12-digit UTR No." required 
                                minlength="12" maxlength="12" pattern="[0-9]{12}" inputmode="numeric" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);" 
                                style="background: #0b0f19; font-family: monospace; font-size: 14px; font-weight: 700; padding: 8px 12px; border: 1.5px solid #f59e0b; width: 100%; color: #ffffff; letter-spacing: 1px;">
                        </div>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                    <button type="button" onclick="closeGoogleModal()" class="btn secondary" style="padding: 10px 18px;">
                        Cancel
                    </button>
                    <button type="submit" class="btn primary" style="background: #2563eb; padding: 10px 22px; font-weight: 700;">
                        Complete Google Sign-Up →
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script>
let verifiedMobileNumber = '';

function handleMobileInputChanged() {
    const mobInput = document.getElementById("mobile");
    if (verifiedMobileNumber && mobInput.value !== verifiedMobileNumber) {
        verifiedMobileNumber = '';
        document.getElementById("otpVerifiedBadge").style.display = 'none';
        document.getElementById("btnSendOtp").innerText = '📲 Verify Number';
        document.getElementById("btnSendOtp").disabled = false;
    }
}

function sendMobileOtp() {
    const mob = document.getElementById("mobile").value.trim();
    if (!/^[6-9]\d{9}$/.test(mob)) {
        alert("Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 first.");
        document.getElementById("mobile").focus();
        return;
    }

    const btn = document.getElementById("btnSendOtp");
    btn.innerText = 'Sending...';
    btn.disabled = true;

    fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_action=send_otp&mobile=' + encodeURIComponent(mob)
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            btn.innerText = 'Resend OTP';
            const box = document.getElementById("otpVerificationBox");
            const banner = document.getElementById("otpSimulationBanner");
            box.style.display = 'block';
            banner.innerHTML = `🔔 SMS Code Sent! Enter OTP: <b style="font-size: 15px; color: #38bdf8; background: #0b0f19; padding: 2px 6px; border-radius: 4px; border: 1px solid #334155;">${data.otp}</b>`;
            document.getElementById("otpInput").value = data.otp; // Auto-fill for seamless user testing
            document.getElementById("otpInput").focus();
        } else {
            btn.innerText = '📲 Verify Number';
            alert(data.message);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = '📲 Verify Number';
        alert("Failed to send OTP. Please check your internet connection.");
    });
}

function verifyMobileOtp() {
    const mob = document.getElementById("mobile").value.trim();
    const otp = document.getElementById("otpInput").value.trim();

    if (!otp) {
        alert("Please enter the 6-digit OTP.");
        return;
    }

    fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_action=verify_otp&mobile=' + encodeURIComponent(mob) + '&otp=' + encodeURIComponent(otp)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            verifiedMobileNumber = mob;
            document.getElementById("otpVerificationBox").style.display = 'none';
            document.getElementById("otpVerifiedBadge").style.display = 'block';
            document.getElementById("verifiedMobileText").innerText = mob;
            document.getElementById("btnSendOtp").innerText = '✓ Verified';
            document.getElementById("btnSendOtp").disabled = true;
        } else {
            alert(data.message);
        }
    })
    .catch(() => {
        alert("Error verifying OTP.");
    });
}

// Google modal OTP functions
let g_verifiedMobile = '';

function sendGoogleMobileOtp() {
    const mob = document.getElementById("g_mobile").value.trim();
    if (!/^[6-9]\d{9}$/.test(mob)) {
        alert("Please enter a valid 10-digit Indian mobile number.");
        return;
    }
    const btn = document.getElementById("g_btnSendOtp");
    btn.innerText = 'Sending...';

    fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_action=send_otp&mobile=' + encodeURIComponent(mob)
    })
    .then(res => res.json())
    .then(data => {
        btn.innerText = 'Resend';
        if (data.success) {
            document.getElementById("g_otpBox").style.display = 'block';
            document.getElementById("g_otpBanner").innerHTML = `Code: <b>${data.otp}</b>`;
            document.getElementById("g_otpInput").value = data.otp;
        } else {
            alert(data.message);
        }
    });
}

function verifyGoogleMobileOtp() {
    const mob = document.getElementById("g_mobile").value.trim();
    const otp = document.getElementById("g_otpInput").value.trim();

    fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax_action=verify_otp&mobile=' + encodeURIComponent(mob) + '&otp=' + encodeURIComponent(otp)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            g_verifiedMobile = mob;
            document.getElementById("g_otpBox").style.display = 'none';
            document.getElementById("g_otpVerified").style.display = 'block';
            document.getElementById("g_btnSendOtp").innerText = '✓ Verified';
            document.getElementById("g_btnSendOtp").disabled = true;
        } else {
            alert(data.message);
        }
    });
}

// Intercept form submissions to ensure mobile is verified
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("registerForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            const mob = document.getElementById("mobile").value.trim();
            if (!verifiedMobileNumber || verifiedMobileNumber !== mob) {
                e.preventDefault();
                alert("Mobile contact verification required! Please click 'Verify Number' and enter the 6-digit OTP before submitting.");
                document.getElementById("mobile").focus();
            }
        });
    }

    const gForm = document.getElementById("googleSignupForm");
    if (gForm) {
        gForm.addEventListener("submit", function(e) {
            const mob = document.getElementById("g_mobile").value.trim();
            if (!g_verifiedMobile || g_verifiedMobile !== mob) {
                e.preventDefault();
                alert("Aadhaar mobile verification required! Please verify the mobile number via OTP first.");
            }
        });
    }
});

function openGoogleModal() {
    const modal = document.getElementById("googleModal");
    if (modal) modal.style.display = "flex";
}

function closeGoogleModal() {
    const modal = document.getElementById("googleModal");
    if (modal) modal.style.display = "none";
}

window.addEventListener("click", function(event) {
    const modal = document.getElementById("googleModal");
    if (event.target === modal) {
        closeGoogleModal();
    }
});
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
