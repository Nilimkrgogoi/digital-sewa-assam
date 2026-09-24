<?php
// admin/applications.php - Application Management & Document Review & UTR Validation
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = sanitize($_GET['msg'] ?? '');
$err = sanitize($_GET['err'] ?? '');
$currentUrl = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'applications.php');

// Handle Quick UTR Validation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $adminId = $_SESSION['user_id'] ?? null;
    $action = $_POST['action'];
    $appId = (int)($_POST['application_id'] ?? 0);

    if ($appId > 0) {
        if ($action === 'verify_utr') {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET utr_status = 'verified', payment_status = 'verified', verified_at = NOW(), verified_by = ?, verification_remarks = 'Payment verified by Admin' WHERE application_id = ?")->execute([$adminId, $appId]);
            $pdo->prepare("UPDATE applications SET payment_status = 'verified', updated_at = NOW() WHERE id = ?")->execute([$appId]);
            $pdo->commit();
            $returnUrl = $_POST['return_url'] ?? 'applications.php';
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            header("Location: " . $returnUrl . $sep . "msg=" . urlencode("✓ Payment UTR verified and confirmed!"));
            exit;
        } elseif ($action === 'reject_utr') {
            $reason = sanitize($_POST['rejection_reason'] ?? 'Payment reference not found in bank statement');
            $customNote = sanitize($_POST['rejection_notes'] ?? '');
            $fullRemarks = !empty($customNote) ? ($reason . ' - ' . $customNote) : $reason;

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET utr_status = 'rejected', payment_status = 'rejected', verified_at = NOW(), verified_by = ?, verification_remarks = ? WHERE application_id = ?")->execute([$adminId, $fullRemarks, $appId]);
            $pdo->prepare("UPDATE applications SET payment_status = 'rejected', remarks = CONCAT(IFNULL(remarks, ''), IF(remarks IS NULL OR remarks='', '', ' | '), ?), updated_at = NOW() WHERE id = ?")->execute(["Payment Rejected: " . $fullRemarks, $appId]);
            $pdo->commit();
            $returnUrl = $_POST['return_url'] ?? 'applications.php';
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            header("Location: " . $returnUrl . $sep . "msg=" . urlencode("❌ Payment UTR marked as invalid/rejected."));
            exit;
        } elseif ($action === 'reset_utr') {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET utr_status = 'pending', payment_status = 'paid', verified_at = NULL, verified_by = NULL, verification_remarks = NULL WHERE application_id = ?")->execute([$appId]);
            $pdo->prepare("UPDATE applications SET payment_status = 'paid', updated_at = NOW() WHERE id = ?")->execute([$appId]);
            $pdo->commit();
            $returnUrl = $_POST['return_url'] ?? 'applications.php';
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            header("Location: " . $returnUrl . $sep . "msg=" . urlencode("↺ UTR status reset to pending verification."));
            exit;
        } elseif ($action === 'reject_document') {
            $reason = sanitize($_POST['rejection_reason'] ?? 'False or invalid document uploaded');
            $customNote = sanitize($_POST['rejection_notes'] ?? '');
            $fullRemarks = !empty($customNote) ? ($reason . ' - ' . $customNote) : $reason;
            $docId = (int)($_POST['document_id'] ?? 0);

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE applications SET status = 'rejected', document_status = 'rejected', remarks = ?, updated_at = NOW() WHERE id = ?")->execute(["⚠️ Document Rejected: " . $fullRemarks, $appId]);
            if ($docId > 0) {
                $pdo->prepare("UPDATE application_documents SET status = 'rejected', rejection_reason = ? WHERE id = ? AND application_id = ?")->execute([$fullRemarks, $docId, $appId]);
            } else {
                $pdo->prepare("UPDATE application_documents SET status = 'rejected', rejection_reason = ? WHERE application_id = ?")->execute([$fullRemarks, $appId]);
            }
            $pdo->commit();
            $returnUrl = $_POST['return_url'] ?? 'applications.php';
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            header("Location: " . $returnUrl . $sep . "msg=" . urlencode("❌ Document rejected as false/invalid. Citizen has been notified to re-upload."));
            exit;
        } elseif ($action === 'verify_document') {
            $docId = (int)($_POST['document_id'] ?? 0);
            $pdo->beginTransaction();
            if ($docId > 0) {
                $pdo->prepare("UPDATE application_documents SET status = 'verified', rejection_reason = NULL WHERE id = ? AND application_id = ?")->execute([$docId, $appId]);
                $pendingCount = $pdo->prepare("SELECT COUNT(*) FROM application_documents WHERE application_id = ? AND status != 'verified'");
                $pendingCount->execute([$appId]);
                if ((int)$pendingCount->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE applications SET document_status = 'verified', updated_at = NOW() WHERE id = ?")->execute([$appId]);
                }
            } else {
                $pdo->prepare("UPDATE applications SET document_status = 'verified', updated_at = NOW() WHERE id = ?")->execute([$appId]);
                $pdo->prepare("UPDATE application_documents SET status = 'verified', rejection_reason = NULL WHERE application_id = ?")->execute([$appId]);
            }
            $pdo->commit();
            $returnUrl = $_POST['return_url'] ?? 'applications.php';
            $sep = (strpos($returnUrl, '?') !== false) ? '&' : '?';
            header("Location: " . $returnUrl . $sep . "msg=" . urlencode("✓ Document(s) marked as verified & authentic."));
            exit;
        }
    }
}

// Services list for filter
$allServices = $pdo->query("SELECT id, service_name FROM services ORDER BY service_name ASC")->fetchAll();

// Build filter query
$where = ["1=1"];
$params = [];

$statusFilter = sanitize($_GET['status'] ?? '');
if (!empty($statusFilter)) {
    $where[] = "a.status = ?";
    $params[] = $statusFilter;
}

$docFilter = sanitize($_GET['doc_status'] ?? '');
if (!empty($docFilter) && in_array($docFilter, ['pending', 'verified', 'rejected'])) {
    $where[] = "a.document_status = ?";
    $params[] = $docFilter;
}

$serviceFilter = (int)($_GET['service_id'] ?? 0);
if ($serviceFilter > 0) {
    $where[] = "a.service_id = ?";
    $params[] = $serviceFilter;
}

$utrFilter = sanitize($_GET['utr_status'] ?? '');
if (!empty($utrFilter) && in_array($utrFilter, ['pending', 'verified', 'rejected'])) {
    $where[] = "p.utr_status = ?";
    $params[] = $utrFilter;
}

$validateUtr = sanitize($_GET['validate_utr'] ?? '');
if (!empty($validateUtr)) {
    $where[] = "(p.transaction_id LIKE ? OR a.application_id LIKE ?)";
    $cleanUtr = "%" . $validateUtr . "%";
    $params[] = $cleanUtr;
    $params[] = $cleanUtr;
}

$search = sanitize($_GET['search'] ?? '');
if (!empty($search)) {
    $where[] = "(a.application_id LIKE ? OR a.full_name LIKE ? OR a.mobile LIKE ? OR a.district LIKE ? OR p.transaction_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereClause = implode(" AND ", $where);
$sql = "
    SELECT a.*, s.service_name, s.fee, 
           p.id as payment_id, p.transaction_id as payment_utr, p.utr_status, 
           p.verification_remarks, p.verified_at,
           u.name as verifier_name
    FROM applications a
    JOIN services s ON a.service_id = s.id
    LEFT JOIN payments p ON p.application_id = a.id
    LEFT JOIN users u ON p.verified_by = u.id
    WHERE $whereClause
    ORDER BY a.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Metrics
$totalPendingUtr = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE utr_status = 'pending' AND transaction_id IS NOT NULL AND transaction_id != ''")->fetchColumn();
$totalVerifiedUtr = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE utr_status = 'verified'")->fetchColumn();
$totalRejectedUtr = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE utr_status = 'rejected'")->fetchColumn();

try {
    $totalPendingDocs = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE document_status = 'pending'")->fetchColumn();
    $totalRejectedDocs = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE document_status = 'rejected'")->fetchColumn();
} catch (Exception $e) {
    $appCols = $pdo->query("SHOW COLUMNS FROM applications")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('document_status', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN document_status ENUM('pending','verified','rejected') DEFAULT 'pending'");
    }
    $totalPendingDocs = (int)($pdo->query("SELECT COUNT(*) FROM applications WHERE document_status = 'pending'")->fetchColumn() ?? 0);
    $totalRejectedDocs = (int)($pdo->query("SELECT COUNT(*) FROM applications WHERE document_status = 'rejected'")->fetchColumn() ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications & UTR Validation - Digital Sewa Assam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .utr-code-box {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            color: #1e3a8a;
            font-weight: 700;
        }
        .utr-badge-verified {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .utr-badge-pending {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .utr-badge-rejected {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .btn-copy {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0 3px;
            font-size: 13px;
            line-height: 1;
        }
        .btn-copy:hover {
            transform: scale(1.2);
        }
    </style>
</head>
<body>

<!-- ADMIN HEADER -->
<header class="header" style="background: #0f172a; border-color: #1e293b;">
    <div class="container nav">
        <?= logo_html_admin('dashboard.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" style="color: white;">☰</button>
        <nav id="navbar">
            <a href="dashboard.php" style="color: #cbd5e1;">Dashboard</a>
            <a href="applications.php" style="color: #38bdf8; font-weight: 700;">Applications</a>
            <a href="users.php" style="color: #cbd5e1;">Users</a>
            <a href="services.php" style="color: #cbd5e1;">Services</a>
            <a href="settings.php" style="color: #cbd5e1;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #cbd5e1;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #94a3b8; font-size: 13px;">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 30px 0 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #0f172a;">Citizen Service Applications</h1>
            <p style="color: #64748b; font-size: 14px;">Review submissions, validate citizen payment UTRs against bank statements, and issue decisions.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="export_applications.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&service_id=<?= $serviceFilter ?>&format=csv" class="btn success" style="background: #15803d; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 10px 16px; border-radius: 8px;" title="Export filtered applications to CSV">
                📊 Export (.CSV)
            </a>
            <a href="export_applications.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&service_id=<?= $serviceFilter ?>&format=xls" class="btn primary" style="background: #7c3aed; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 10px 16px; border-radius: 8px;" title="Export filtered applications to Excel">
                📑 Export (.XLS)
            </a>
            <span class="badge" style="background: white; border: 1px solid #cbd5e1; color: #0f172a; font-size: 14px;">
                Total Found: <b><?= count($applications) ?></b>
            </span>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            ✓ Application updated successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>

    <!-- QUICK UTR PAYMENT VALIDATOR CARD -->
    <div class="content-card" style="margin-bottom: 24px; border: 2px solid #e0e7ff; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">⚡</span>
                <div>
                    <h3 style="font-size: 16px; font-weight: 800; color: #1e1b4b; margin: 0;">Quick UTR Payment Validator & Reconciler</h3>
                    <p style="font-size: 12px; color: #64748b; margin: 2px 0 0;">Paste 12-digit UTR from your Canara Bank / UPI App SMS or statement to quickly locate and verify citizen payment.</p>
                </div>
            </div>
            <!-- Quick Filter Pills -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="applications.php" class="btn secondary sm" style="<?= empty($utrFilter) && empty($validateUtr) ? 'background:white; color:white;' : '' ?>">
                    All (<?= count($applications) ?>)
                </a>
                <a href="applications.php?utr_status=pending" class="btn sm" style="<?= ($utrFilter === 'pending') ? 'background:#d97706; color:white;' : 'background:#fef3c7; color:#92400e; border:1px solid #fde68a;' ?>">
                    ⏳ Awaiting Check (<?= $totalPendingUtr ?>)
                </a>
                <a href="applications.php?utr_status=verified" class="btn sm" style="<?= ($utrFilter === 'verified') ? 'background:#16a34a; color:white;' : 'background:#dcfce7; color:#15803d; border:1px solid #bbf7d0;' ?>">
                    ✓ Verified (<?= $totalVerifiedUtr ?>)
                </a>
                <a href="applications.php?utr_status=rejected" class="btn sm" style="<?= ($utrFilter === 'rejected') ? 'background:#dc2626; color:white;' : 'background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;' ?>">
                    ❌ Rejected (<?= $totalRejectedUtr ?>)
                </a>
            </div>
        </div>

        <form action="applications.php" method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="service_id" value="<?= $serviceFilter ?>">
            <div style="position: relative; flex: 1; min-width: 280px;">
                <input type="text" name="validate_utr" value="<?= htmlspecialchars($validateUtr) ?>" placeholder="🔍 Paste 12-digit UTR (e.g., 412345678901) or Application ID..." style="width: 100%; padding: 10px 14px; border: 2px solid #818cf8; border-radius: 8px; font-size: 14px; font-family: monospace; font-weight: 600;">
            </div>
            <button type="submit" class="btn primary" style="background: #4f46e5; font-weight: 700; padding: 10px 18px; border-radius: 8px;">
                🔍 Validate UTR
            </button>
            <?php if (!empty($validateUtr)): ?>
                <a href="applications.php" class="btn secondary" style="padding: 10px 14px; border-radius: 8px;">Clear Search</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($validateUtr)): ?>
            <div style="margin-top: 14px; padding: 10px 14px; background: #e0e7ff; border-radius: 8px; font-size: 13px; color: #3730a3; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span>Results for UTR / Reference: <b><?= htmlspecialchars($validateUtr) ?></b> (<?= count($applications) ?> matched)</span>
                <span style="font-size: 12px; color: #4338ca;">Check UTR against bank app statement before clicking Verify</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- MAIN FILTER & SEARCH BAR -->
    <div class="content-card" style="margin-bottom: 24px;">
        <form action="applications.php" method="GET" class="filter-bar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="🔍 Search ID, Name, Mobile, District..." value="<?= htmlspecialchars($search) ?>" style="min-width: 240px;">

                <select name="status">
                    <option value="">-- All App Statuses --</option>
                    <option value="submitted" <?= ($statusFilter === 'submitted') ? 'selected' : '' ?>>Submitted</option>
                    <option value="under_review" <?= ($statusFilter === 'under_review') ? 'selected' : '' ?>>Under Review</option>
                    <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="completed" <?= ($statusFilter === 'completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>

                <select name="doc_status">
                    <option value="">-- All Document Statuses --</option>
                    <option value="pending" <?= ($docFilter === 'pending') ? 'selected' : '' ?>>⏳ Docs Pending Verification</option>
                    <option value="verified" <?= ($docFilter === 'verified') ? 'selected' : '' ?>>✓ Docs Verified & Authentic</option>
                    <option value="rejected" <?= ($docFilter === 'rejected') ? 'selected' : '' ?>>❌ False / Rejected Documents (<?= $totalRejectedDocs ?>)</option>
                </select>

                <select name="utr_status">
                    <option value="">-- All Payment Statuses --</option>
                    <option value="pending" <?= ($utrFilter === 'pending') ? 'selected' : '' ?>>⏳ Pending Verification</option>
                    <option value="verified" <?= ($utrFilter === 'verified') ? 'selected' : '' ?>>✓ Verified & Confirmed</option>
                    <option value="rejected" <?= ($utrFilter === 'rejected') ? 'selected' : '' ?>>❌ Invalid / Rejected UTR</option>
                </select>

                <select name="service_id">
                    <option value="">-- All Services --</option>
                    <?php foreach ($allServices as $svc): ?>
                        <option value="<?= $svc['id'] ?>" <?= ($serviceFilter === $svc['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($svc['service_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn primary sm" style="background: #7c3aed;">Apply</button>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($utrFilter) || $serviceFilter > 0 || !empty($validateUtr)): ?>
                    <a href="applications.php" class="btn secondary sm">Clear All</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="export_applications.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&service_id=<?= $serviceFilter ?>&format=xls" class="btn secondary sm" style="font-weight: 700; text-decoration: none;">
                    📊 Quick Export (.XLS)
                </a>
            </div>
        </form>

        <!-- TABLE -->
        <?php if (empty($applications)): ?>
            <div style="padding: 50px; text-align: center; color: #64748b;">
                <div style="font-size: 40px; margin-bottom: 10px;">🔍</div>
                <h3>No Applications Match Your Query</h3>
                <p style="font-size: 14px; margin-top: 6px;">Try clearing filters or search terms.</p>
            </div>
        <?php else: ?>
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Application Ref</th>
                            <th>Applicant Details</th>
                            <th>Service</th>
                            <th>Documents</th>
                            <th>Status & Remarks</th>
                            <th style="min-width: 220px;">Payment & UTR Verification</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): 
                            // Fetch documents for this app
                            $docs = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
                            $docs->execute([$app['id']]);
                            $appDocs = $docs->fetchAll();
                            $utrStatus = $app['utr_status'] ?? 'pending';
                        ?>
                            <tr>
                                <td>
                                    <b style="color: var(--primary); font-size: 14px;">
                                        <?= htmlspecialchars($app['application_id']) ?>
                                    </b>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                        📅 <?= date('d M Y, h:i A', strtotime($app['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($app['full_name']) ?></b>
                                    <div style="font-size: 12px; color: #475569;">
                                        📞 <?= htmlspecialchars($app['mobile']) ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b;">
                                        📍 <?= htmlspecialchars($app['district']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 28px; height: 28px; flex-shrink: 0;">
                                            <?= get_service_logo_html($app['service_name'], '../') ?>
                                        </div>
                                        <div>
                                            <b><?= htmlspecialchars($app['service_name']) ?></b>
                                            <div style="font-size: 12px; color: #15803d; font-weight: 700;">
                                                <?= format_currency($app['fee']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $docsList = [];
                                        foreach ($appDocs as $d) {
                                            $docsList[] = [
                                                'id' => (int)$d['id'],
                                                'num' => $d['document_number'],
                                                'name' => $d['document_name'] ?? ('Doc #' . $d['document_number']),
                                                'file' => $d['file_path'],
                                                'status' => $d['status'] ?? 'pending',
                                                'reason' => $d['rejection_reason'] ?? ''
                                            ];
                                        }
                                        $docsJson = htmlspecialchars(json_encode($docsList), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <?php if (empty($appDocs)): ?>
                                        <span style="font-size: 12px; color: #94a3b8;">No docs</span>
                                    <?php else: ?>
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                            <?php if (count($appDocs) > 1): ?>
                                                <a href="download_doc.php?app_id=<?= $app['id'] ?>&zip=1" class="btn sm" style="background: #0284c7; color: white; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 2px;" title="Download all uploaded documents for this application as a ZIP file">
                                                    📦 Download All (.ZIP)
                                                </a>
                                            <?php endif; ?>

                                            <?php foreach ($appDocs as $d): 
                                                $isDocRej = (($d['status'] ?? '') === 'rejected');
                                                $isDocVer = (($d['status'] ?? '') === 'verified');
                                            ?>
                                                <div style="background: <?= $isDocRej ? '#fff5f5' : '#f8fafc' ?>; border: 1px solid <?= $isDocRej ? '#fecaca' : '#e2e8f0' ?>; border-radius: 6px; padding: 6px 8px;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                                                        <span style="font-size: 12px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;" title="<?= htmlspecialchars($d['document_name'] ?? 'Doc #' . $d['document_number']) ?>">
                                                            📎 <?= htmlspecialchars($d['document_name'] ?? 'Doc #' . $d['document_number']) ?>
                                                        </span>
                                                        <?php if ($isDocVer): ?>
                                                            <span style="font-size: 10px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 4px; padding: 1px 5px; font-weight: 700;">✓ Authentic</span>
                                                        <?php elseif ($isDocRej): ?>
                                                            <span style="font-size: 10px; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 4px; padding: 1px 5px; font-weight: 700;">❌ False / Rej</span>
                                                        <?php else: ?>
                                                            <span style="font-size: 10px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 4px; padding: 1px 5px; font-weight: 600;">⏳ Check</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if ($isDocRej && !empty($d['rejection_reason'])): ?>
                                                        <div style="font-size: 10px; color: #b91c1c; margin-top: 3px; line-height: 1.3;">
                                                            <b>Rejection:</b> <?= htmlspecialchars($d['rejection_reason']) ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Quick Doc Action Buttons -->
                                                    <div style="display: flex; gap: 4px; margin-top: 6px; align-items: center; flex-wrap: wrap;">
                                                        <a href="../<?= htmlspecialchars($d['file_path']) ?>" target="_blank" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600; text-decoration: none;" title="Preview file in new browser tab">
                                                            👁️ View
                                                        </a>
                                                        <a href="download_doc.php?doc_id=<?= $d['id'] ?>" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 700; text-decoration: none;" title="Download this document directly">
                                                            📥 Download
                                                        </a>
                                                        <?php if (!$isDocVer): ?>
                                                            <form method="POST" action="applications.php" style="margin: 0; display: inline;" onsubmit="return confirm('Mark <?= htmlspecialchars(addslashes($d['document_name'] ?? 'document')) ?> as authentic & verified?');">
                                                                <input type="hidden" name="action" value="verify_document">
                                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                                <input type="hidden" name="document_id" value="<?= $d['id'] ?>">
                                                                <input type="hidden" name="return_url" value="<?= $currentUrl ?>">
                                                                <button type="submit" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 700; cursor: pointer;" title="Verify Document">
                                                                    ✓ Verify
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if (!$isDocRej): ?>
                                                            <button type="button" onclick="openRejectDocModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['application_id'])) ?>', <?= $d['id'] ?>, '<?= htmlspecialchars(addslashes($d['document_name'] ?? 'Document')) ?>')" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 700; cursor: pointer;" title="Reject false or invalid document">
                                                                ❌ Flag
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php if (count($appDocs) === 1): ?>
                                                <a href="download_doc.php?doc_id=<?= $appDocs[0]['id'] ?>" class="btn sm" style="background: #0284c7; color: white; font-weight: 700; font-size: 11px; padding: 4px 8px; border-radius: 6px; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 5px; margin-top: 2px;" title="Download uploaded document">
                                                    📥 Download Document
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= get_status_badge($app['status']) ?></div>
                                    <div style="margin-top: 4px;">
                                        <?php if (($app['document_status'] ?? 'pending') === 'rejected'): ?>
                                            <span style="font-size: 11px; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 5px; padding: 2px 7px; font-weight: 700; display: inline-block;">
                                                ❌ False / Rejected Docs
                                            </span>
                                        <?php elseif (($app['document_status'] ?? 'pending') === 'verified'): ?>
                                            <span style="font-size: 11px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 5px; padding: 2px 7px; font-weight: 700; display: inline-block;">
                                                ✓ Docs Verified
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 11px; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 5px; padding: 2px 7px; font-weight: 600; display: inline-block;">
                                                ⏳ Docs In Review
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($app['remarks'])): ?>
                                        <div style="font-size: 11px; color: #475569; max-width: 220px; margin-top: 4px; line-height: 1.4;">
                                            💬 <i><?= htmlspecialchars($app['remarks']) ?></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px; font-weight: 700; color: #15803d; margin-bottom: 4px;">
                                        <?= format_currency($app['fee']) ?>
                                    </div>

                                    <?php if (!empty($app['payment_utr'])): ?>
                                        <div style="margin-bottom: 6px;">
                                            <div class="utr-code-box">
                                                <span>UTR: <?= htmlspecialchars($app['payment_utr']) ?></span>
                                                <button type="button" class="btn-copy" onclick="copyUtrToClipboard('<?= htmlspecialchars(addslashes($app['payment_utr'])) ?>', this)" title="Copy UTR to Clipboard">📋</button>
                                            </div>
                                        </div>

                                        <div style="margin-bottom: 6px;">
                                            <?php if ($utrStatus === 'verified'): ?>
                                                <span class="utr-badge-verified">✓ UTR Verified</span>
                                                <?php if (!empty($app['verified_at'])): ?>
                                                    <div style="font-size: 10px; color: #15803d; margin-top: 2px;">
                                                        On <?= date('d M Y, h:i A', strtotime($app['verified_at'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif ($utrStatus === 'rejected'): ?>
                                                <span class="utr-badge-rejected">❌ UTR Rejected</span>
                                                <?php if (!empty($app['verification_remarks'])): ?>
                                                    <div style="font-size: 10px; color: #b91c1c; margin-top: 2px; max-width: 200px;">
                                                        Reason: <?= htmlspecialchars($app['verification_remarks']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="utr-badge-pending">⏳ Awaiting Check</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Quick 1-Click Validation Actions -->
                                        <div style="display: flex; gap: 6px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                                            <?php if ($utrStatus === 'pending'): ?>
                                                <!-- Verify Button Form -->
                                                <form method="POST" action="applications.php" style="margin: 0;" onsubmit="return confirm('Verify and confirm receipt of ₹<?= $app['fee'] ?> (UTR: <?= htmlspecialchars($app['payment_utr']) ?>) for <?= htmlspecialchars(addslashes($app['full_name'])) ?>?');">
                                                    <input type="hidden" name="action" value="verify_utr">
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <input type="hidden" name="return_url" value="<?= $currentUrl ?>">
                                                    <button type="submit" class="btn sm" style="background: #16a34a; color: white; padding: 4px 9px; font-size: 11px; font-weight: 700; border-radius: 6px;">
                                                        ✓ Verify
                                                    </button>
                                                </form>

                                                <!-- Reject Button (Modal Trigger) -->
                                                <button type="button" onclick="openRejectUtrModal(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['application_id'])) ?>', '<?= htmlspecialchars(addslashes($app['payment_utr'])) ?>', '<?= $app['fee'] ?>')" class="btn sm" style="background: #dc2626; color: white; padding: 4px 9px; font-size: 11px; font-weight: 700; border-radius: 6px;">
                                                    ❌ Reject
                                                </button>
                                            <?php else: ?>
                                                <!-- Reset Button Form -->
                                                <form method="POST" action="applications.php" style="margin: 0;" onsubmit="return confirm('Reset UTR status back to pending verification?');">
                                                    <input type="hidden" name="action" value="reset_utr">
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <input type="hidden" name="return_url" value="<?= $currentUrl ?>">
                                                    <button type="submit" class="btn secondary sm" style="padding: 3px 8px; font-size: 11px; border-radius: 6px;" title="Reset UTR verification status">
                                                        ↺ Re-check / Reset
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>
                                        <span class="status-badge badge-pending" style="font-size: 11px;">Unpaid / No UTR</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 6px;">
                                        <button onclick="openStatusModal('<?= $app['id'] ?>', '<?= htmlspecialchars(addslashes($app['application_id'])) ?>', '<?= $app['status'] ?>', '<?= htmlspecialchars(addslashes($app['remarks'] ?? '')) ?>', '<?= $app['payment_status'] ?>', '<?= htmlspecialchars(addslashes($app['issued_document'] ?? '')) ?>', '<?= $app['document_status'] ?? 'pending' ?>', '<?= $docsJson ?>')" class="btn primary sm" style="background: #7c3aed; white-space: nowrap;">
                                            Change Status
                                        </button>
                                        <a href="../track.php?app_id=<?= urlencode($app['application_id']) ?>" target="_blank" class="btn secondary sm" style="white-space: nowrap;">
                                            View Slip ↗
                                        </a>
                                        <?php if (!empty($app['issued_document'])): ?>
                                            <a href="../<?= htmlspecialchars($app['issued_document']) ?>" target="_blank" style="font-size: 11px; color: #16a34a; font-weight: 700; text-decoration: underline;">
                                                📥 Issued Doc ↗
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- REJECT UTR MODAL -->
<div class="modal-overlay" id="rejectUtrModal">
    <div class="modal-box">
        <div class="modal-header" style="background: #dc2626; color: white;">
            <h3 style="color: white; margin: 0;">❌ Reject Payment Reference</h3>
            <button class="modal-close" onclick="closeRejectUtrModal()" style="color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <form action="applications.php" method="POST">
                <input type="hidden" name="action" value="reject_utr">
                <input type="hidden" name="application_id" id="rejectModalAppId">
                <input type="hidden" name="return_url" value="<?= $currentUrl ?>">

                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-size: 13px; color: #991b1b;">
                        <b>Application:</b> <span id="rejectModalAppCode"></span><br>
                        <b>Submitted UTR:</b> <code id="rejectModalUtr" style="font-family: monospace; font-weight: 700;"></code><br>
                        <b>Required Amount:</b> ₹<span id="rejectModalAmount"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="rejectReasonSelect">Select Rejection Reason *</label>
                    <select name="rejection_reason" id="rejectReasonSelect" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <option value="Transaction reference not found in bank statement">Transaction reference not found in bank statement</option>
                        <option value="Payment amount credited is less than service fee">Payment amount credited is less than service fee</option>
                        <option value="Duplicate UTR reference already used by another application">Duplicate UTR reference already used by another application</option>
                        <option value="Invalid or fake transaction screenshot / UTR number">Invalid or fake transaction screenshot / UTR number</option>
                        <option value="Transaction reversed or failed at bank end">Transaction reversed or failed at bank end</option>
                        <option value="Other / Unverified payment">Other / Unverified payment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rejectNotes">Additional Instructions for Citizen (Visible in Tracking)</label>
                    <textarea name="rejection_notes" id="rejectNotes" rows="3" placeholder="Explain the issue or instruct citizen to pay correct amount / verify UTR..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn secondary" onclick="closeRejectUtrModal()">Cancel</button>
                    <button type="submit" class="btn danger" style="background: #dc2626; color: white;">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REJECT DOCUMENT MODAL (DIRECT FROM TABLE) -->
<div class="modal-overlay" id="rejectDocModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header" style="background: #dc2626; color: white;">
            <h3 style="color: white; margin: 0;">❌ Reject Document as False / Invalid</h3>
            <button class="modal-close" onclick="closeRejectDocModal()" style="color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <form action="applications.php" method="POST">
                <input type="hidden" name="action" value="reject_document">
                <input type="hidden" name="application_id" id="rejectDocAppId">
                <input type="hidden" name="document_id" id="rejectDocId">
                <input type="hidden" name="return_url" value="<?= $currentUrl ?>">

                <div style="background: #fef2f2; border: 1.5px solid #fecaca; padding: 14px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-size: 13px; color: #991b1b; line-height: 1.5;">
                        <b>Application:</b> <span id="rejectDocAppCode" style="font-family: monospace; font-weight: 700;"></span><br>
                        <b>Flagged Document:</b> <span id="rejectDocName" style="font-weight: 700; color: #7f1d1d;"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="docRejectionReasonSelect" style="font-weight: 700;">Select Reason for Document Rejection *</label>
                    <select name="rejection_reason" id="docRejectionReasonSelect" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px;">
                        <option value="False / Fake / Tampered document detected">False / Fake / Tampered document detected</option>
                        <option value="Document is blurred, illegible, or unreadable">Document is blurred, illegible, or unreadable</option>
                        <option value="Name, Date of Birth, or Details mismatch with application">Name, Date of Birth, or Details mismatch with application</option>
                        <option value="Incorrect document type uploaded (e.g. invalid certificate/proof)">Incorrect document type uploaded (e.g. invalid certificate/proof)</option>
                        <option value="Incomplete document (Back side, second page, or seal missing)">Incomplete document (Back side, second page, or seal missing)</option>
                        <option value="Document is expired or outdated">Document is expired or outdated</option>
                        <option value="Other document discrepancy">Other document discrepancy</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="docRejectionNotes" style="font-weight: 700;">Custom Instructions for Citizen (Visible in Tracking)</label>
                    <textarea name="rejection_notes" id="docRejectionNotes" rows="3" placeholder="Explain why this document was rejected and instruct citizen to upload authentic file..."></textarea>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 12px; font-size: 12px; color: #1e40af; margin-top: 10px;">
                    ℹ️ <b>Citizen Notification:</b> Marking document as rejected will change application status to <b>Rejected / Document Correction Required</b>, allowing the citizen to re-upload from their tracking page.
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn secondary" onclick="closeRejectDocModal()">Cancel</button>
                    <button type="submit" class="btn danger" style="background: #dc2626; color: white; font-weight: 700;">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- STATUS UPDATE MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box" style="max-width: 650px;">
        <div class="modal-header">
            <h3>Update Application Status (<span id="modalAppCode"></span>)</h3>
            <button class="modal-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="update_status.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="modalAppId">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label for="modalStatus">Processing Status *</label>
                        <select name="status" id="modalStatus" required onchange="handleModalStatusChange()">
                            <option value="submitted">Submitted (Awaiting Processing)</option>
                            <option value="under_review">Under Review (Verifying Documents)</option>
                            <option value="approved">Approved (Processing Complete)</option>
                            <option value="completed">Completed (Issued / Certificate Ready)</option>
                            <option value="rejected">Rejected (Disapproved / Document Error)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modalPaymentStatus">Payment & UTR Status</label>
                        <select name="payment_status" id="modalPaymentStatus">
                            <option value="pending">Pending (Unpaid)</option>
                            <option value="paid">Paid (Submitted, Pending Check)</option>
                            <option value="verified">Verified (Confirmed in Bank Statement)</option>
                            <option value="rejected">Rejected (Invalid / Fake UTR)</option>
                        </select>
                    </div>
                </div>

                <!-- DOCUMENT VERIFICATION STATUS (NEW DEDICATED FEATURE) -->
                <div class="form-group" style="border: 2px solid #bae6fd; background: #f0f9ff; padding: 14px; border-radius: 10px; margin-bottom: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="modalDocStatus" style="font-weight: 700; color: #0369a1; font-size: 14px; margin: 0;">
                            📄 Document Verification Status
                        </label>
                        <span style="font-size: 11px; color: #0284c7; font-weight: 600;">Authenticity Verification</span>
                    </div>
                    <select name="document_status" id="modalDocStatus" onchange="toggleDocRejectionSection()" style="font-weight: 700; width: 100%;">
                        <option value="pending">⏳ Pending Document Review / Unchecked</option>
                        <option value="verified">✓ Verified & Authentic (All Documents Valid)</option>
                        <option value="rejected">❌ Rejected (False / Invalid / Unclear Document)</option>
                    </select>

                    <!-- FALSE / REJECTED DOCUMENT SETTINGS (SHOWN WHEN REJECTED) -->
                    <div id="docRejectionSection" style="display: none; margin-top: 14px; background: #ffffff; border: 1.5px solid #ef4444; border-radius: 8px; padding: 14px;">
                        <div style="font-weight: 700; color: #dc2626; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <span>⚠️ False / Rejected Document Notice to Citizen</span>
                        </div>
                        <p style="font-size: 12px; color: #475569; margin: 0 0 10px;">
                            Selecting rejection will instruct the citizen to re-upload their documents and automatically set application status to <b>Rejected / Re-upload Required</b>.
                        </p>

                        <label for="modalDocRejectReason" style="font-size: 12px; font-weight: 700; color: #991b1b; display: block; margin-bottom: 4px;">
                            Select Rejection Reason:
                        </label>
                        <select name="document_rejection_reason" id="modalDocRejectReason" onchange="applyDocRejectionPreset()" style="font-size: 13px; padding: 8px; margin-bottom: 12px; width: 100%; border: 1px solid #fca5a5; border-radius: 6px;">
                            <option value="False / Fake / Tampered document detected">False / Fake / Tampered document detected</option>
                            <option value="Document is blurred, illegible, or unreadable">Document is blurred, illegible, or unreadable</option>
                            <option value="Name, Date of Birth, or Details mismatch with application">Name, Date of Birth, or Details mismatch with application</option>
                            <option value="Incorrect document type uploaded (e.g. invalid certificate/proof)">Incorrect document type uploaded (e.g. invalid certificate/proof)</option>
                            <option value="Incomplete document (Back side, second page, or seal missing)">Incomplete document (Back side, second page, or seal missing)</option>
                            <option value="Document is expired or outdated">Document is expired or outdated</option>
                            <option value="Other document discrepancy">Other document discrepancy</option>
                        </select>

                        <div id="modalDocListWrapper" style="display: none;">
                            <label style="font-size: 12px; font-weight: 700; color: #991b1b; display: block; margin-bottom: 4px;">
                                Check which document(s) are false / need re-upload:
                            </label>
                            <div id="modalDocCheckboxes" style="display: flex; flex-direction: column; gap: 6px; background: #fff5f5; padding: 10px; border-radius: 6px; border: 1px solid #fecaca; max-height: 140px; overflow-y: auto;">
                                <!-- Dynamically generated checkbox list -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modalIssuedDocument">Upload Approved Certificate / Issued Document (PDF / Image)</label>
                    <input type="file" name="issued_document" id="modalIssuedDocument" accept=".pdf,.jpg,.jpeg,.png">
                    <small style="font-size: 11px; color: #64748b;">Upload official PAN copy, Certificate, Voter slip or DL for citizen to download.</small>
                    <div id="modalCurrentDocBox" style="display: none; margin-top: 6px; font-size: 12px; background: #f0fdf4; padding: 6px 10px; border-radius: 6px; border: 1px solid #bbf7d0;">
                        ✅ Current Document: <a href="#" id="modalCurrentDocLink" target="_blank" style="color: #16a34a; font-weight: 700; text-decoration: underline;">View Uploaded File ↗</a>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modalRemarks">Official Remarks / Citizen Instructions (Visible in Tracking)</label>
                    <textarea name="remarks" id="modalRemarks" rows="4" placeholder="Enter status explanation, document review feedback, or rejection reasons visible to citizen..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn primary" style="background: #7c3aed;">Save Status & Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html(true) ?>

<script src="../assets/js/script.js"></script>
<script>
function openRejectUtrModal(appId, appCode, utr, fee) {
    const modal = document.getElementById("rejectUtrModal");
    if (!modal) return;
    document.getElementById("rejectModalAppId").value = appId;
    document.getElementById("rejectModalAppCode").innerText = appCode;
    document.getElementById("rejectModalUtr").innerText = utr;
    document.getElementById("rejectModalAmount").innerText = fee;
    modal.classList.add("open");
}

function closeRejectUtrModal() {
    const modal = document.getElementById("rejectUtrModal");
    if (modal) {
        modal.classList.remove("open");
    }
}

function openRejectDocModal(appId, appCode, docId, docName) {
    const modal = document.getElementById("rejectDocModal");
    if (!modal) return;
    document.getElementById("rejectDocAppId").value = appId;
    document.getElementById("rejectDocId").value = docId;
    document.getElementById("rejectDocAppCode").innerText = appCode;
    document.getElementById("rejectDocName").innerText = docName;
    modal.classList.add("open");
}

function closeRejectDocModal() {
    const modal = document.getElementById("rejectDocModal");
    if (modal) {
        modal.classList.remove("open");
    }
}

function copyUtrToClipboard(utr, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(utr).then(function() {
            const original = btn.innerText;
            btn.innerText = '✓';
            setTimeout(function() { btn.innerText = original; }, 1500);
        }).catch(function() {
            prompt("Copy UTR:", utr);
        });
    } else {
        prompt("Copy UTR:", utr);
    }
}

// Close modals when clicking outside
window.addEventListener("click", function (event) {
    const rModal = document.getElementById("rejectUtrModal");
    if (event.target === rModal) {
        closeRejectUtrModal();
    }
    const dModal = document.getElementById("rejectDocModal");
    if (event.target === dModal) {
        closeRejectDocModal();
    }
});
</script>
</body>
</html>
