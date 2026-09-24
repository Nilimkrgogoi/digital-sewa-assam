<?php
// track.php - Real-Time Application Tracking & Acknowledgement Slip
require_once __DIR__ . '/config/database.php';

$appId = isset($_GET['app_id']) ? sanitize($_GET['app_id']) : '';
$application = null;
$documents = [];
$payment = null;
$error = '';

// Handle citizen resubmission of corrected UTR if previously rejected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_utr') {
    $targetAppId = sanitize($_POST['application_id'] ?? '');
    $newUtr = sanitize($_POST['new_utr'] ?? '');
    $cleanUtr = preg_replace('/[^0-9]/', '', $newUtr);

    if (empty($newUtr) || strlen($cleanUtr) !== 12 || strlen($newUtr) !== 12) {
        $error = "Please enter a valid 12-digit numeric UPI / Bank Transaction Reference (UTR). Not allowed more or less than 12 digits.";
    } else {
        $checkApp = $pdo->prepare("SELECT id FROM applications WHERE application_id = ?");
        $checkApp->execute([$targetAppId]);
        $appRow = $checkApp->fetch();
        if ($appRow) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET transaction_id = ?, utr_status = 'pending', payment_status = 'paid', verification_remarks = 'Corrected UTR resubmitted by citizen' WHERE application_id = ?")->execute([$cleanUtr, $appRow['id']]);
            $pdo->prepare("UPDATE applications SET payment_status = 'paid', updated_at = NOW() WHERE id = ?")->execute([$appRow['id']]);
            $pdo->commit();
            header("Location: track.php?app_id=" . urlencode($targetAppId) . "&utr_updated=1");
            exit;
        }
    }
}

if (!empty($appId)) {
    $stmt = $pdo->prepare("
        SELECT a.*, s.service_name, s.fee, u.name as user_account_name, u.email as user_email
        FROM applications a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.application_id = ?
        LIMIT 1
    ");
    $stmt->execute([$appId]);
    $application = $stmt->fetch();

    if ($application) {
        // Fetch documents
        $docStmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ? ORDER BY document_number ASC");
        $docStmt->execute([$application['id']]);
        $documents = $docStmt->fetchAll();

        // Fetch payment details
        $payStmt = $pdo->prepare("SELECT * FROM payments WHERE application_id = ? ORDER BY id DESC LIMIT 1");
        $payStmt->execute([$application['id']]);
        $payment = $payStmt->fetch();
    } else {
        $error = "No application found matching Application ID: " . htmlspecialchars($appId);
    }
}

// Timeline state calculation
$currentStatus = $application ? $application['status'] : '';
$isSubmitted = in_array($currentStatus, ['submitted', 'under_review', 'approved', 'completed', 'rejected']);
$isUnderReview = in_array($currentStatus, ['under_review', 'approved', 'completed']);
$isApprovedOrCompleted = in_array($currentStatus, ['approved', 'completed']);
$isRejected = ($currentStatus === 'rejected');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Status & Acknowledgement Slip - Digital Sewa Assam</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* PRINT SPECIFIC STYLES - STRICT COMPLIANCE */
        @media print {
            .no-print, .screen-only, header, footer, .track-box, .tracking-timeline, .remarks-box, .docs-section {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background: white !important;
                color: #0f172a !important;
                margin: 0 !important;
                padding: 10mm 15mm !important;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            }
            .print-slip-container {
                display: block !important;
                border: 2px solid #0f172a !important;
                border-radius: 8px !important;
                padding: 24px !important;
                margin: 0 auto !important;
                max-width: 100% !important;
            }
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body style="display: flex; flex-direction: column; min-height: 100vh;">

<!-- HEADER (SCREEN ONLY) -->
<header class="header no-print">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">☰</button>
        <nav id="navbar">
            <a href="index.php">Home</a>
            <a href="index.php#services">Services</a>
            <a href="apply.php">Apply Online</a>
            <a href="track.php" style="color: var(--primary); font-weight: 700;">Track Status</a>
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="section" style="padding: 40px 0; flex: 1;">
    <div class="container">
        
        <!-- SEARCH BOX (SCREEN ONLY) -->
        <div class="track-box no-print" style="margin-bottom: 35px;">
            <span>Real-Time Citizen Tracking</span>
            <h2>Track Application Status</h2>
            <p>Enter your unique Application ID received during submission (e.g., DSA-2026-XXXXXX)</p>

            <form action="track.php" method="GET" class="track-form">
                <input type="text" name="app_id" value="<?= htmlspecialchars($appId) ?>" placeholder="Enter Application ID..." required style="flex-grow: 1;">
                <button type="submit" class="btn primary">Track Status</button>
            </form>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-top: 20px;">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($application): ?>
            <!-- ======================================================== -->
            <!-- PRINT-ONLY DEDICATED ACKNOWLEDGEMENT SLIP (NO STATUS, NO DOCS) -->
            <!-- ======================================================== -->
            <div class="print-only print-slip-container">
                <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 6px;">
                        <img src="assets/images/logo.jpg" alt="Digital Sewa Assam Logo" style="width: 50px; height: 50px; border-radius: 8px; object-fit: contain;">
                        <h1 style="font-size: 26px; margin: 0; color: #0f172a; font-weight: 900; letter-spacing: 1px;">
                            DIGITAL SEWA ASSAM
                        </h1>
                    </div>
                    <div style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                        Official Application Acknowledgement Slip
                    </div>
                </div>

                <!-- APPLICATION ID AND DATE/TIME BOX -->
                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Application ID:</span>
                        <div style="font-size: 20px; font-weight: 800; color: #0284c7; font-family: monospace;">
                            <?= htmlspecialchars($application['application_id']) ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Submission Date & Time:</span>
                        <div style="font-size: 14px; font-weight: 700; color: #1e293b;">
                            <?= date('d F Y, h:i A', strtotime($application['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <!-- APPLICATION SUMMARY ONLY -->
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 12px; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 6px; text-transform: uppercase;">
                    Application Summary
                </h3>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px;">
                    <tbody>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600; width: 35%;">Service Requested</td>
                            <td style="padding: 10px 8px; color: #0f172a; font-weight: 700; font-size: 14px;"><?= htmlspecialchars($application['service_name']) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">Applicant Full Name</td>
                            <td style="padding: 10px 8px; color: #0f172a; font-weight: 700;"><?= htmlspecialchars($application['full_name']) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">Contact Mobile Number</td>
                            <td style="padding: 10px 8px; color: #0f172a; font-weight: 700;">+91 <?= htmlspecialchars($application['mobile']) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">District (Assam)</td>
                            <td style="padding: 10px 8px; color: #0f172a; font-weight: 700;"><?= htmlspecialchars($application['district']) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">Residential Address</td>
                            <td style="padding: 10px 8px; color: #0f172a; font-weight: 600;"><?= nl2br(htmlspecialchars($application['address'])) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">Service Application Fee</td>
                            <td style="padding: 10px 8px; color: #16a34a; font-weight: 700;">
                                ₹<?= number_format($application['fee'], 2) ?> 
                                <?php if ($payment && $payment['utr_status'] === 'verified'): ?>
                                    <span style="color: #15803d; font-size: 12px;">(✓ Payment Verified)</span>
                                <?php elseif ($payment && $payment['utr_status'] === 'rejected'): ?>
                                    <span style="color: #dc2626; font-size: 12px;">(❌ UTR Verification Rejected)</span>
                                <?php else: ?>
                                    <span style="color: #d97706; font-size: 12px;">(⏳ Awaiting Payment Verification)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($payment && !empty($payment['transaction_id'])): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px 8px; color: #64748b; font-weight: 600;">Payment Reference (UTR)</td>
                                <td style="padding: 10px 8px; color: #475569; font-family: monospace; font-weight: 600;">
                                    <?= htmlspecialchars($payment['transaction_id']) ?>
                                    <?php if ($payment['utr_status'] === 'verified'): ?>
                                        <span style="color: #15803d; font-size: 11px; margin-left: 6px;">[✓ Confirmed Received]</span>
                                    <?php elseif ($payment['utr_status'] === 'rejected'): ?>
                                        <span style="color: #dc2626; font-size: 11px; margin-left: 6px;">[❌ Rejected: <?= htmlspecialchars($payment['verification_remarks'] ?? 'Invalid') ?>]</span>
                                    <?php else: ?>
                                        <span style="color: #d97706; font-size: 11px; margin-left: 6px;">[⏳ Pending Admin Verification]</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top: 30px; padding-top: 16px; border-top: 1px dashed #94a3b8; font-size: 11px; color: #64748b; text-align: center; line-height: 1.6;">
                    This is an official computer-generated citizen acknowledgement receipt. Physical signature is not required.<br>
                    Helpline: +91 9613167470 • Digital Sewa Assam Citizen Facilitation Portal
                </div>
            </div>
            <!-- ======================================================== -->

            <!-- SCREEN WEB VIEW -->
            <!-- SUCCESS NOTIFICATION FOR NEW SUBMISSION -->
            <?php if (isset($_GET['submitted'])): ?>
                <div class="alert alert-success no-print">
                    🎉 <b>Application Submitted Successfully!</b> Your tracking reference code is <b><?= htmlspecialchars($application['application_id']) ?></b>. Please save or print this acknowledgement slip for future reference.
                </div>
            <?php endif; ?>

            <!-- SUCCESS NOTIFICATION FOR RE-UPLOAD -->
            <?php if (isset($_GET['reuploaded'])): ?>
                <div class="alert alert-success no-print">
                    🎉 <b>Corrected Documents Re-uploaded Successfully!</b> Your application has been returned to <b>Under Review</b> for official verification.
                </div>
            <?php endif; ?>

            <!-- REJECTION CALLOUT WITH RE-UPLOAD BUTTON -->
            <?php if ($application['status'] === 'rejected' || ($application['document_status'] ?? '') === 'rejected'): ?>
                <div class="alert alert-danger no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; padding: 20px 24px; border-left: 6px solid #dc2626; background: #fef2f2; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 6px 20px rgba(220, 38, 38, 0.12);">
                    <div style="flex: 1; min-width: 280px;">
                        <b style="font-size: 16px; display: flex; align-items: center; gap: 8px; color: #991b1b; margin-bottom: 6px;">
                            <span>⚠️ Document Verification Failed / False Document Detected</span>
                        </b>
                        <div style="font-size: 14px; color: #7f1d1d; line-height: 1.5; background: white; padding: 12px 14px; border-radius: 8px; border: 1px solid #fee2e2; margin: 8px 0;">
                            <b>Officer Note:</b> <?= !empty($application['remarks']) ? nl2br(htmlspecialchars($application['remarks'])) : 'One or more of your submitted documents were found to be false, unreadable, or invalid. Please re-upload authentic documents.' ?>
                        </div>
                        <span style="font-size: 12px; color: #b91c1c; font-weight: 600;">
                            👉 Please re-upload your authentic, clear, and valid documents below to continue processing.
                        </span>
                    </div>
                    <div>
                        <a href="reupload.php?app_id=<?= urlencode($application['application_id']) ?>" class="btn danger" style="background: #dc2626; color: white !important; font-weight: 800; font-size: 14px; padding: 13px 22px; border-radius: 8px; white-space: nowrap; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3); display: inline-block;">
                            ✏️ Upload Authentic Document Again →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- COMPLETED / ISSUED DOCUMENT DOWNLOAD BANNER -->
            <?php if ($application['status'] === 'completed'): ?>
                <div class="alert alert-success no-print" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; padding: 18px 22px; border-left: 6px solid #16a34a;">
                    <div>
                        <b style="font-size: 15px; display: block; color: #065f46;">🎉 Application Completed & Document Ready!</b>
                        <span style="font-size: 13px; color: #047857;">
                            Your service application has been verified, approved, and officially completed.
                            <?= !empty($application['issued_document']) ? 'You can now download your official certificate / document below.' : 'Your official certificate will be available for download shortly.' ?>
                        </span>
                    </div>
                    <?php if (!empty($application['issued_document'])): ?>
                        <a href="<?= htmlspecialchars($application['issued_document']) ?>" target="_blank" download class="btn success" style="background: #16a34a; color: white !important; font-weight: 700; padding: 12px 20px; white-space: nowrap;">
                            📥 Download Document (PDF) ↗
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <!-- UTR UPDATED NOTIFICATION -->
            <?php if (isset($_GET['utr_updated'])): ?>
                <div class="alert alert-success no-print" style="margin-bottom: 20px;">
                    🎉 <b>Corrected Payment Reference (UTR) Submitted!</b> Your transaction is now awaiting administrator verification.
                </div>
            <?php endif; ?>

            <!-- UTR REJECTION ALERT WITH CORRECTION FORM -->
            <?php if ($payment && $payment['utr_status'] === 'rejected'): ?>
                <div class="alert alert-danger no-print" style="margin-bottom: 20px; border-left: 6px solid #dc2626; padding: 18px 22px; background: #fef2f2;">
                    <div style="margin-bottom: 12px;">
                        <b style="font-size: 15px; display: block; color: #991b1b;">⚠️ Payment Verification Failed (UTR: <?= htmlspecialchars($payment['transaction_id']) ?>)</b>
                        <p style="font-size: 13px; color: #7f1d1d; margin: 4px 0 6px;">
                            <b>Officer Remark:</b> <?= htmlspecialchars($payment['verification_remarks'] ?? 'Transaction reference not found in bank records.') ?>
                        </p>
                        <span style="font-size: 12px; color: #4b5563;">If you made a payment or entered an incorrect UTR, enter the valid 12-digit UPI Transaction Reference below:</span>
                    </div>
                    <form action="track.php?app_id=<?= urlencode($application['application_id']) ?>" method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="action" value="resubmit_utr">
                        <input type="hidden" name="application_id" value="<?= htmlspecialchars($application['application_id']) ?>">
                        <input type="text" name="new_utr" placeholder="Enter corrected 12-digit UPI UTR..." 
                            minlength="12" maxlength="12" pattern="[0-9]{12}" inputmode="numeric" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);" 
                            required style="padding: 9px 12px; border: 2px solid #ef4444; border-radius: 6px; font-family: monospace; font-size: 14px; font-weight: 700; min-width: 280px; letter-spacing: 1px;">
                        <button type="submit" class="btn danger" style="background: #dc2626; font-weight: 700; padding: 9px 16px; border-radius: 6px; color: white !important;">
                            Submit Corrected UTR →
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- TRACKING RESULT CARD (WEB VIEW) -->
            <div class="content-card no-print" style="max-width: 900px; margin: auto;">
                <div class="card-title-bar">
                    <div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700;">APPLICATION ID</span>
                        <h2 style="font-size: 24px; font-weight: 800; color: var(--primary);">
                            <?= htmlspecialchars($application['application_id']) ?>
                        </h2>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <?php if ($application['status'] === 'rejected'): ?>
                            <a href="reupload.php?app_id=<?= urlencode($application['application_id']) ?>" class="btn danger sm">
                                ✏️ Re-upload Docs
                            </a>
                        <?php endif; ?>
                        <?php if ($application['status'] === 'completed' && !empty($application['issued_document'])): ?>
                            <a href="<?= htmlspecialchars($application['issued_document']) ?>" target="_blank" download class="btn success sm">
                                📥 Download PDF ↗
                            </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn secondary sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;" title="Print official slip with Application ID, Date & Time, and Summary">
                            🖨️ Print Slip
                        </button>
                        <?php if (is_admin()): ?>
                            <a href="admin/applications.php?search=<?= urlencode($application['application_id']) ?>" class="btn primary sm">
                                ⚙️ Admin Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="padding: 28px;">
                    <!-- PROGRESS STEPPER TIMELINE (SCREEN ONLY) -->
                    <div class="tracking-timeline screen-only">
                        <!-- Step 1 -->
                        <div class="timeline-step <?= $isSubmitted ? 'completed' : '' ?>">
                            <div class="step-icon">✓</div>
                            <div class="step-title">Submitted</div>
                            <div class="step-desc"><?= date('d M Y', strtotime($application['created_at'])) ?></div>
                        </div>

                        <!-- Step 2 -->
                        <div class="timeline-step <?= $isUnderReview ? 'completed' : ($currentStatus === 'submitted' ? 'active' : '') ?>">
                            <div class="step-icon"><?= $isUnderReview ? '✓' : '2' ?></div>
                            <div class="step-title">Under Review</div>
                            <div class="step-desc">Document Verification</div>
                        </div>

                        <!-- Step 3 -->
                        <div class="timeline-step <?= $isApprovedOrCompleted ? 'completed' : ($isRejected ? 'rejected' : ($currentStatus === 'under_review' ? 'active' : '')) ?>">
                            <div class="step-icon"><?= $isApprovedOrCompleted ? '✓' : ($isRejected ? '✕' : '3') ?></div>
                            <div class="step-title"><?= $isRejected ? 'Rejected' : 'Approval & Processing' ?></div>
                            <div class="step-desc"><?= $isRejected ? 'Disapproved' : 'Officer Processing' ?></div>
                        </div>

                        <!-- Step 4 -->
                        <div class="timeline-step <?= ($currentStatus === 'completed') ? 'completed' : ($isRejected ? 'rejected' : '') ?>">
                            <div class="step-icon"><?= ($currentStatus === 'completed') ? '★' : '4' ?></div>
                            <div class="step-title">Completed</div>
                            <div class="step-desc">Service Ready / Issued</div>
                        </div>
                    </div>

                    <!-- OFFICER REMARKS BOX (SCREEN ONLY) -->
                    <div class="remarks-box screen-only" style="background: #f8fafc; border-left: 4px solid var(--primary); padding: 18px 20px; border-radius: 8px; margin: 25px 0;">
                        <h4 style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                            Current Status & Official Remarks:
                        </h4>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <?= get_status_badge($application['status']) ?>
                            <span style="font-size: 12px; color: #64748b;">
                                Last Updated: <?= format_date($application['updated_at']) ?>
                            </span>
                        </div>
                        <p style="font-size: 14px; color: #334155;">
                            <?= !empty($application['remarks']) ? nl2br(htmlspecialchars($application['remarks'])) : 'Your application is progressing normally through the official Assam digital portal workflow. Please check back for updates.' ?>
                        </p>
                    </div>

                    <!-- APPLICATION DETAILS GRID -->
                    <h3 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        Application Summary
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; font-size: 14px;">
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">Service Requested:</span>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <div style="width: 28px; height: 28px; flex-shrink: 0;">
                                    <?= get_service_logo_html($application['service_name']) ?>
                                </div>
                                <b style="color: #0f172a; font-size: 16px;"><?= htmlspecialchars($application['service_name']) ?></b>
                            </div>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">Applicant Full Name:</span>
                            <b><?= htmlspecialchars($application['full_name']) ?></b>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">Contact Mobile:</span>
                            <b><?= htmlspecialchars($application['mobile']) ?></b>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">District (Assam):</span>
                            <b><?= htmlspecialchars($application['district']) ?></b>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">Submission Date:</span>
                            <b><?= format_date($application['created_at']) ?></b>
                        </div>
                        <div>
                            <span style="color: #64748b; display: block; font-size: 12px;">Payment Status:</span>
                            <b>
                                <?php if ($payment && $payment['utr_status'] === 'verified'): ?>
                                    <span style="color: #15803d; display: inline-flex; align-items: center; gap: 4px;">
                                        ✓ Verified (<?= format_currency($application['fee']) ?>)
                                    </span>
                                    <div style="font-size: 11px; font-family: monospace; color: #1e3a8a; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 6px; border-radius: 4px; margin-top: 3px; font-weight: 600;">
                                        UTR: <?= htmlspecialchars($payment['transaction_id']) ?>
                                    </div>
                                <?php elseif ($payment && $payment['utr_status'] === 'rejected'): ?>
                                    <span style="color: #dc2626; display: inline-flex; align-items: center; gap: 4px;">
                                        ❌ Rejected / Invalid UTR
                                    </span>
                                    <div style="font-size: 11px; font-family: monospace; color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; padding: 2px 6px; border-radius: 4px; margin-top: 3px; font-weight: 600;">
                                        UTR: <?= htmlspecialchars($payment['transaction_id']) ?>
                                    </div>
                                <?php elseif ($payment && !empty($payment['transaction_id'])): ?>
                                    <span style="color: #d97706; display: inline-flex; align-items: center; gap: 4px;">
                                        ⏳ Awaiting Verification (<?= format_currency($application['fee']) ?>)
                                    </span>
                                    <div style="font-size: 11px; font-family: monospace; color: #92400e; background: #fef3c7; border: 1px solid #fde68a; padding: 2px 6px; border-radius: 4px; margin-top: 3px; font-weight: 600;">
                                        UTR: <?= htmlspecialchars($payment['transaction_id']) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #64748b;">Pending Payment</span>
                                <?php endif; ?>
                            </b>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px; font-size: 14px;">
                        <span style="color: #64748b; display: block; font-size: 12px;">Residential Address:</span>
                        <div style="color: #1e293b;"><?= nl2br(htmlspecialchars($application['address'])) ?></div>
                    </div>

                    <!-- UPLOADED DOCUMENTS (SCREEN ONLY) -->
                    <div class="docs-section screen-only">
                        <h3 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                            Uploaded Verification Documents (<?= count($documents) ?>)
                        </h3>

                        <?php if (empty($documents)): ?>
                            <p style="font-size: 13px; color: #64748b;">No documents uploaded with this submission.</p>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 14px;">
                                <?php foreach ($documents as $doc): 
                                    $isDocRejected = (($doc['status'] ?? '') === 'rejected');
                                    $isDocVerified = (($doc['status'] ?? '') === 'verified');
                                ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; background: <?= $isDocRejected ? '#fff5f5' : '#f8fafc' ?>; border: <?= $isDocRejected ? '1.5px solid #ef4444' : '1px solid #e2e8f0' ?>; border-radius: 10px; padding: 12px 16px;">
                                        <div>
                                            <div style="font-size: 13px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                <span>📄 <?= htmlspecialchars($doc['document_name'] ?? 'Document #' . $doc['document_number']) ?></span>
                                                <?php if ($isDocVerified): ?>
                                                    <span style="font-size: 10px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 4px; padding: 1px 6px; font-weight: 700;">✓ Verified</span>
                                                <?php elseif ($isDocRejected): ?>
                                                    <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 4px; padding: 1px 6px; font-weight: 700;">❌ Rejected / False</span>
                                                <?php else: ?>
                                                    <span style="font-size: 10px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 4px; padding: 1px 6px; font-weight: 600;">⏳ In Review</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <?= htmlspecialchars($doc['file_name']) ?>
                                            </div>
                                            <?php if ($isDocRejected && !empty($doc['rejection_reason'])): ?>
                                                <div style="font-size: 11px; color: #dc2626; margin-top: 4px; font-weight: 600;">
                                                    ⚠️ <?= htmlspecialchars($doc['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; gap: 6px; align-items: center;">
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn secondary sm">
                                                View ↗
                                            </a>
                                            <?php if ($isDocRejected): ?>
                                                <a href="reupload.php?app_id=<?= urlencode($application['application_id']) ?>" class="btn danger sm" style="background: #dc2626; color: white !important; font-size: 11px;">
                                                    Re-upload
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
</body>
</html>
