<?php
// admin/dashboard.php - Administrative Overview & Management Center
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

// Handle Add Service from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $name = sanitize($_POST['service_name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $fee = (float)($_POST['fee'] ?? 0.00);
    $status = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';

    // Process required documents
    $selectedDocs = $_POST['req_docs'] ?? [];
    $customDocs = sanitize($_POST['custom_docs'] ?? '');
    $finalDocs = [];
    if (is_array($selectedDocs)) {
        foreach ($selectedDocs as $d) {
            $d = trim($d);
            if (!empty($d) && !in_array($d, $finalDocs)) {
                $finalDocs[] = $d;
            }
        }
    }
    if (!empty($customDocs)) {
        $parts = explode(',', $customDocs);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p) && !in_array($p, $finalDocs)) {
                $finalDocs[] = $p;
            }
        }
    }
    $reqDocsJson = !empty($finalDocs) ? json_encode($finalDocs, JSON_UNESCAPED_UNICODE) : null;

    if (empty($name)) {
        $err = "Service Name is required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (service_name, description, fee, required_docs, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $fee, $reqDocsJson, $status]);
        $msg = "🎉 New service '{$name}' created successfully with configured document requirements!";
    }
}

// Handle Update Service from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_service') {
    $svcId = (int)($_POST['service_id'] ?? 0);
    $name = sanitize($_POST['service_name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $fee = (float)($_POST['fee'] ?? 0.00);
    $status = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';

    // Process required documents
    $selectedDocs = $_POST['req_docs'] ?? [];
    $customDocs = sanitize($_POST['custom_docs'] ?? '');
    $finalDocs = [];
    if (is_array($selectedDocs)) {
        foreach ($selectedDocs as $d) {
            $d = trim($d);
            if (!empty($d) && !in_array($d, $finalDocs)) {
                $finalDocs[] = $d;
            }
        }
    }
    if (!empty($customDocs)) {
        $parts = explode(',', $customDocs);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p) && !in_array($p, $finalDocs)) {
                $finalDocs[] = $p;
            }
        }
    }
    $reqDocsJson = !empty($finalDocs) ? json_encode($finalDocs, JSON_UNESCAPED_UNICODE) : null;

    if (empty($name) || !$svcId) {
        $err = "Service Name and ID are required.";
    } else {
        $stmt = $pdo->prepare("UPDATE services SET service_name = ?, description = ?, fee = ?, required_docs = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $fee, $reqDocsJson, $status, $svcId]);
        $msg = "✓ Service '{$name}' updated successfully with updated document requirements!";
    }
}

// Handle Status Toggle from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_service_status') {
    $svcId = (int)($_POST['service_id'] ?? 0);
    $newStatus = ($_POST['new_status'] === 'inactive') ? 'inactive' : 'active';

    $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $svcId]);
    $msg = "Service status changed to " . ucfirst($newStatus) . ".";
}

// Handle Remove / Delete Service from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_service') {
    $svcId = (int)($_POST['service_id'] ?? 0);
    if ($svcId > 0) {
        $svcStmt = $pdo->prepare("SELECT service_name FROM services WHERE id = ?");
        $svcStmt->execute([$svcId]);
        $service = $svcStmt->fetch();

        if ($service) {
            try {
                $pdo->beginTransaction();

                // Find applications linked to this service
                $appStmt = $pdo->prepare("SELECT id FROM applications WHERE service_id = ?");
                $appStmt->execute([$svcId]);
                $appIds = $appStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($appIds)) {
                    foreach ($appIds as $aId) {
                        $docsStmt = $pdo->prepare("SELECT file_path FROM application_documents WHERE application_id = ?");
                        $docsStmt->execute([$aId]);
                        $files = $docsStmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($files as $f) {
                            $fullPath = __DIR__ . '/../' . $f;
                            if (!empty($f) && file_exists($fullPath) && is_file($fullPath)) {
                                @unlink($fullPath);
                            }
                        }
                        $pdo->prepare("DELETE FROM application_documents WHERE application_id = ?")->execute([$aId]);
                        $pdo->prepare("DELETE FROM payments WHERE application_id = ?")->execute([$aId]);
                    }
                    $pdo->prepare("DELETE FROM applications WHERE service_id = ?")->execute([$svcId]);
                }

                $delStmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                $delStmt->execute([$svcId]);

                $pdo->commit();
                $msg = "🗑 Service '" . htmlspecialchars($service['service_name']) . "' has been permanently removed.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $err = "Could not delete service: " . $e->getMessage();
            }
        }
    }
}

// Handle Mark Inquiry Responded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_inquiry') {
    $inqId = (int)($_POST['inquiry_id'] ?? 0);
    if ($inqId > 0) {
        $pdo->prepare("UPDATE contact_inquiries SET status = 'responded' WHERE id = ?")->execute([$inqId]);
        $msg = "Citizen inquiry marked as responded.";
    }
}

// Metrics queries
$appStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as review_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM applications
")->fetch();

$userStats = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$revenueTotal = $pdo->query("SELECT SUM(amount) FROM payments WHERE payment_status = 'paid'")->fetchColumn();
$activeServicesCount = $pdo->query("SELECT COUNT(*) FROM services WHERE status = 'active'")->fetchColumn();

// Fetch latest 10 applications
$recentApps = $pdo->query("
    SELECT a.*, s.service_name, s.fee,
           (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as doc_count
    FROM applications a
    JOIN services s ON a.service_id = s.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll();

// Fetch all services for dashboard management
$allServices = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM applications WHERE service_id = s.id) as app_count
    FROM services s
    ORDER BY s.id ASC
")->fetchAll();

// Fetch recent citizen inquiries
$recentInquiries = $pdo->query("
    SELECT * FROM contact_inquiries
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Digital Sewa Assam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ADMIN HEADER -->
<header class="header" style="background: #0f172a; border-bottom: 2px solid #1e293b; padding: 4px 0;">
    <div class="container nav">
        <?= logo_html_admin('dashboard.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" style="color: #ffffff; font-size: 26px;">☰</button>
        <nav id="navbar">
            <a href="dashboard.php" class="active" style="color: #ffffff !important; background: #0284c7; font-weight: 800; padding: 7px 14px; border-radius: 8px;">Dashboard</a>
            <a href="applications.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Applications</a>
            <a href="users.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Users</a>
            <a href="services.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Services</a>
            <a href="settings.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #38bdf8 !important; font-weight: 700; font-size: 13px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.4); padding: 6px 12px; border-radius: 8px; text-decoration: none;">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn" style="background: #dc2626 !important; color: #ffffff !important; font-weight: 800; padding: 7px 16px; border-radius: 8px; text-decoration: none;">Sign Out</a>
        </nav>
    </div>
</header>

<div class="dashboard-header" style="background: linear-gradient(135deg, #1e1b4b, #312e81);">
    <div class="container">
        <div class="dashboard-nav-strip">
            <div>
                <span class="admin-badge">Admin Workspace</span>
                <h1 style="font-size: 26px; font-weight: 800; margin: 8px 0; color: white;">
                    Portal Administration Overview
                </h1>
                <p style="color: #c7d2fe; font-size: 14px;">
                    Assam citizen services operational management, verification, and status dispatch.
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="export_applications.php?format=xls" class="btn success" style="background: #15803d; text-decoration: none; font-weight: 700;">
                    📊 Export Applications (.XLS)
                </a>
                <a href="applications.php" class="btn primary" style="background: #7c3aed;">
                    View All Applications →
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container" style="padding-bottom: 60px;">
    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">
            <?= $msg ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">
            ⚠️ <?= $err ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            ✓ Application status and remarks updated successfully.
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #ede9fe; color: #7c3aed;">📋</div>
            <div>
                <div class="stat-val"><?= (int)$appStats['total'] ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">⚡</div>
            <div>
                <div class="stat-val"><?= (int)$appStats['submitted_count'] + (int)$appStats['review_count'] ?></div>
                <div class="stat-label">Pending / In Review</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
            <div>
                <div class="stat-val"><?= (int)$appStats['approved_count'] + (int)$appStats['completed_count'] ?></div>
                <div class="stat-label">Approved / Completed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">❌</div>
            <div>
                <div class="stat-val"><?= (int)$appStats['rejected_count'] ?></div>
                <div class="stat-label">Rejected Applications</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">👥</div>
            <div>
                <div class="stat-val"><?= (int)$userStats ?></div>
                <div class="stat-label">Registered Citizens</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #f0fdf4; color: #15803d;">💰</div>
            <div>
                <div class="stat-val"><?= format_currency($revenueTotal ?? 0) ?></div>
                <div class="stat-label">Paid Revenue Collected</div>
            </div>
        </div>

        <div class="stat-card" style="cursor: pointer;" onclick="document.getElementById('services-section').scrollIntoView({behavior: 'smooth'})" title="Click to view & manage services below">
            <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">🏛️</div>
            <div>
                <div class="stat-val"><?= (int)$activeServicesCount ?></div>
                <div class="stat-label">Active Services (Catalog)</div>
            </div>
        </div>
    </div>

    <!-- RECENT APPLICATIONS SECTION -->
    <div class="content-card">
        <div class="card-title-bar">
            <h3>Recent Submissions</h3>
            <a href="applications.php" class="btn secondary sm">View All →</a>
        </div>

        <?php if (empty($recentApps)): ?>
            <div style="padding: 40px; text-align: center; color: #64748b;">
                No applications submitted yet.
            </div>
        <?php else: ?>
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Applicant</th>
                            <th>Service</th>
                            <th>District</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentApps as $app): ?>
                            <tr>
                                <td>
                                    <a href="../track.php?app_id=<?= urlencode($app['application_id']) ?>" target="_blank" style="font-weight: 700; color: var(--primary);">
                                        <?= htmlspecialchars($app['application_id']) ?> ↗
                                    </a>
                                </td>
                                <td>
                                    <b><?= htmlspecialchars($app['full_name']) ?></b>
                                    <div style="font-size: 12px; color: #000000; font-weight: 600;">📞 <?= htmlspecialchars($app['mobile']) ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 28px; height: 28px; flex-shrink: 0;">
                                            <?= get_service_logo_html($app['service_name'], '../') ?>
                                        </div>
                                        <div>
                                            <b><?= htmlspecialchars($app['service_name']) ?></b>
                                            <div style="font-size: 11px; color: #000000; font-weight: 600;"><?= format_currency($app['fee']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($app['district']) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($app['created_at'])) ?></td>
                                <td><?= get_status_badge($app['status']) ?></td>
                                <td>
                                    <?php if ($app['payment_status'] === 'paid'): ?>
                                        <span class="status-badge badge-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="openStatusModal('<?= $app['id'] ?>', '<?= htmlspecialchars($app['application_id']) ?>', '<?= $app['status'] ?>', '<?= htmlspecialchars(addslashes($app['remarks'] ?? '')) ?>', '<?= $app['payment_status'] ?>', '<?= htmlspecialchars(addslashes($app['issued_document'] ?? '')) ?>')" class="btn primary sm" style="background: #7c3aed;">
                                        Update Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- CITIZEN SERVICES MANAGEMENT (NO-CODE ADMIN) -->
    <div class="content-card" id="services-section" style="margin-top: 35px;">
        <div class="card-title-bar" style="flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <span>🏛️ Citizen Services Management</span>
                </h3>
                <p style="margin: 4px 0 0; font-size: 13px; color: #000000; font-weight: 600;">
                    Easily add new certificates/services, update prices, pause, or remove anytime without touching any code.
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" onclick="openAddServiceModal()" class="btn primary sm" style="background: #7c3aed; font-weight: 700; box-shadow: 0 2px 6px rgba(124, 58, 237, 0.3);">
                    + Add New Service
                </button>
                <a href="services.php" class="btn secondary sm">Full Catalog Page →</a>
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Description / Details</th>
                        <th>Govt / Portal Fee</th>
                        <th>Applications Filed</th>
                        <th>Status</th>
                        <th style="text-align: right;">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allServices)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 30px;">
                                No services found. Click "+ Add New Service" above to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allServices as $svc): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <?= get_service_logo_html($svc['service_name'], '../') ?>
                                        </div>
                                        <div>
                                            <b style="font-size: 14px; color: #0f172a;"><?= htmlspecialchars($svc['service_name']) ?></b>
                                            <div style="font-size: 11px; color: #475569; font-weight: 600;">ID: #<?= $svc['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="max-width: 260px; font-size: 13px; color: #000000; font-weight: 600; line-height: 1.4;">
                                    <?= htmlspecialchars($svc['description'] ?? 'No description') ?>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #15803d; font-size: 15px;">
                                        <?= format_currency($svc['fee']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="applications.php?service_id=<?= $svc['id'] ?>" style="font-weight: 700; color: #7c3aed;">
                                        <?= (int)$svc['app_count'] ?> Applications ↗
                                    </a>
                                </td>
                                <td>
                                    <?php if ($svc['status'] === 'active'): ?>
                                        <span class="status-badge badge-approved">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-rejected">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <button type="button" onclick="openEditServiceModal(<?= htmlspecialchars(json_encode($svc)) ?>)" class="btn secondary sm" style="padding: 5px 9px; font-size: 12px;" title="Edit service and fee">
                                            ✏️ Edit
                                        </button>

                                        <form action="dashboard.php" method="POST" style="display: inline; margin: 0;">
                                            <input type="hidden" name="action" value="toggle_service_status">
                                            <input type="hidden" name="service_id" value="<?= $svc['id'] ?>">
                                            <?php if ($svc['status'] === 'active'): ?>
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" class="btn warning sm" style="background: #ea580c; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Hide service from citizens">
                                                    ⏸ Pause
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="btn success sm" style="background: #16a34a; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Publish service on citizen portal">
                                                    ▶ Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <form action="dashboard.php" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('⚠️ PERMANENT SERVICE DELETION WARNING:\n\nAre you sure you want to permanently delete \'<?= htmlspecialchars(addslashes($svc['service_name'])) ?>\'?\n\nThis will remove it from the citizen portal. This action cannot be reversed.')">
                                            <input type="hidden" name="action" value="delete_service">
                                            <input type="hidden" name="service_id" value="<?= $svc['id'] ?>">
                                            <button type="submit" class="btn danger sm" style="background: #dc2626; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Permanently Delete Service">
                                                🗑 Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CITIZEN CONTACT & HELPDESK INQUIRIES -->
    <div class="content-card" style="margin-top: 35px;">
        <div class="card-title-bar" style="flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <span>💬 Citizen Contact & Helpdesk Inquiries</span>
                    <span style="font-size: 11px; background: #e0f2fe; color: #0284c7; padding: 2px 8px; border-radius: 999px; font-weight: 700;">Live Website Messages</span>
                </h3>
                <p style="margin: 4px 0 0; font-size: 13px; color: #000000; font-weight: 600;">
                    Queries sent by citizens via the website contact form. One-click direct WhatsApp chat or Call response.
                </p>
            </div>
        </div>

        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Citizen Details</th>
                        <th>Service Inquired</th>
                        <th>Citizen Query</th>
                        <th>Received On</th>
                        <th>Status</th>
                        <th style="text-align: right;">Connect & Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInquiries)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 30px;">
                                No citizen inquiries received yet. Queries submitted on the homepage will appear here instantly.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentInquiries as $inq): ?>
                            <tr>
                                <td>
                                    <b style="font-size: 14px; color: #0f172a;"><?= htmlspecialchars($inq['name']) ?></b>
                                    <div style="font-size: 12px; color: #000000; font-weight: 600; margin-top: 2px;">
                                        📞 <a href="tel:<?= htmlspecialchars($inq['mobile']) ?>" style="color: #0284c7; text-decoration: underline; font-weight: 700;"><?= htmlspecialchars($inq['mobile']) ?></a>
                                    </div>
                                </td>
                                <td>
                                    <span style="display: inline-block; background: #ede9fe; color: #6d28d9; padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                        <?= htmlspecialchars($inq['service_name'] ?: 'General Help') ?>
                                    </span>
                                </td>
                                <td style="max-width: 280px; font-size: 13px; color: #000000; font-weight: 600; line-height: 1.4;">
                                    <?= nl2br(htmlspecialchars($inq['message'])) ?>
                                </td>
                                <td style="font-size: 12px; color: #000000; font-weight: 600;">
                                    <?= date('d M Y, h:i A', strtotime($inq['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($inq['status'] === 'responded'): ?>
                                        <span class="status-badge badge-completed">Responded</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-pending">New Query</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <?php 
                                        $cleanMob = preg_replace('/[^0-9]/', '', $inq['mobile']);
                                        if (strlen($cleanMob) === 10) $cleanMob = '91' . $cleanMob;
                                        $waText = "Namaste " . $inq['name'] . ", this is Digital Sewa Kendra Assam regarding your inquiry about " . ($inq['service_name'] ?: 'our citizen services') . ". How can we assist you today?";
                                        ?>
                                        <a href="https://wa.me/<?= $cleanMob ?>?text=<?= urlencode($waText) ?>" target="_blank" class="btn success sm" style="background: #25d366; color: white; padding: 5px 10px; font-size: 12px; text-decoration: none; border-radius: 6px; font-weight: 600;" title="Reply directly on WhatsApp">
                                            💬 WhatsApp
                                        </a>

                                        <?php if ($inq['status'] === 'new'): ?>
                                            <form action="dashboard.php" method="POST" style="display: inline; margin: 0;">
                                                <input type="hidden" name="action" value="resolve_inquiry">
                                                <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                                <button type="submit" class="btn secondary sm" style="padding: 5px 8px; font-size: 12px;" title="Mark inquiry as resolved">
                                                    ✓ Done
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- STATUS UPDATE MODAL -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Update Application Status (<span id="modalAppCode"></span>)</h3>
            <button class="modal-close" onclick="closeStatusModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="update_status.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="modalAppId">

                <div class="form-group">
                    <label for="modalStatus">Processing Status *</label>
                    <select name="status" id="modalStatus" required>
                        <option value="submitted">Submitted (Awaiting Processing)</option>
                        <option value="under_review">Under Review (Verifying Documents)</option>
                        <option value="approved">Approved (Forwarded / Processing Complete)</option>
                        <option value="completed">Completed (Issued / Ready for Citizen)</option>
                        <option value="rejected">Rejected (Disapproved / Resubmission Required)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modalPaymentStatus">Payment Status</label>
                    <select name="payment_status" id="modalPaymentStatus">
                        <option value="pending">Pending</option>
                        <option value="paid">Paid / Confirmed</option>
                    </select>
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
                    <label for="modalRemarks">Officer Remarks / Comments for Citizen *</label>
                    <textarea name="remarks" id="modalRemarks" rows="4" placeholder="Enter instructions, approval comments, or rejection reasons visible to the applicant..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn primary" style="background: #7c3aed;">Save Status & Notify</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD / EDIT SERVICE MODAL (ZERO CODE ADMIN) -->
<div class="modal-overlay" id="serviceModal">
    <div class="modal-box" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="serviceModalTitle">+ Add New Citizen Service</h3>
            <button class="modal-close" onclick="closeServiceModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form action="dashboard.php" method="POST" id="serviceModalForm">
                <input type="hidden" name="action" id="svcAction" value="add_service">
                <input type="hidden" name="service_id" id="svcId" value="">

                <div class="form-group">
                    <label for="svcName">Service Title / Certificate Name *</label>
                    <input type="text" name="service_name" id="svcName" required placeholder="e.g. Disability Certificate Apply">
                    <small style="font-size: 11px; color: #64748b;">This title appears directly on the home page and citizen apply form.</small>
                </div>

                <div class="form-group">
                    <label for="svcFee">Government / Portal Processing Fee (₹) *</label>
                    <input type="number" step="0.01" min="0" name="fee" id="svcFee" required placeholder="100.00">
                    <small style="font-size: 11px; color: #64748b;">Enter 0 for free citizen services.</small>
                </div>

                <div class="form-group">
                    <label for="svcDesc">Description & Citizen Guidance</label>
                    <textarea name="description" id="svcDesc" rows="3" placeholder="Explain requirements or documents needed by citizen..."></textarea>
                </div>

                <!-- REQUIRED DOCUMENTS CHECKLIST -->
                <div style="margin: 18px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                    <label style="font-weight: 700; font-size: 13px; color: #0f172a; display: block; margin-bottom: 4px;">
                        📄 Required Documents for this Service (Check to Require)
                    </label>
                    <div style="font-size: 11px; color: #000000; font-weight: 600; margin-bottom: 10px;">
                        Citizens will be required to upload clear copies of these selected documents.
                    </div>

                    <?php
                    $dashboardDocOptions = [
                        'Aadhaar Card Copy (Identity / Address Proof)',
                        'Existing PAN Card Copy / Proof of PAN',
                        'Existing Voter ID (EPIC) Copy',
                        'Land Patta / Jamabandi Document',
                        'Latest Land Revenue (Khajana) Receipt',
                        'Income Certificate / Salary Slip',
                        'Caste / Community Certificate Proof',
                        'Bank Account Passbook Copy',
                        'Recent Passport Size Photograph',
                        'Educational Marksheet / 10th Admit Card',
                        'Employment Exchange Registration Card',
                        'Medical / Disability Certificate Copy',
                        'Signature Specimen / Thumb Impression',
                        'Ration Card Copy',
                        'Supporting Requisition / Purpose Document'
                    ];
                    ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 180px; overflow-y: auto; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 10px;">
                        <?php foreach ($dashboardDocOptions as $docOpt): ?>
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #000000; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" name="req_docs[]" value="<?= htmlspecialchars($docOpt) ?>" class="dash-doc-chk" style="width: auto;">
                                <span><?= htmlspecialchars($docOpt) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="dash_custom_docs" style="font-weight: 700; font-size: 12px; color: #000000;">
                            + Extra / Custom Document Names (comma-separated):
                        </label>
                        <input type="text" id="dash_custom_docs" name="custom_docs" placeholder="e.g. Village Headman Certificate, Land NOC" style="background: white; font-size: 13px;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="svcStatus">Catalog Status *</label>
                    <select name="status" id="svcStatus" required>
                        <option value="active">Active (Visible to citizens for online apply)</option>
                        <option value="inactive">Inactive / Paused (Hidden from citizens)</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
                    <button type="button" class="btn secondary" onclick="closeServiceModal()">Cancel</button>
                    <button type="submit" id="svcSubmitBtn" class="btn primary" style="background: #7c3aed; font-weight: 700;">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html(true) ?>

<script src="../assets/js/script.js"></script>
<script>
function openAddServiceModal() {
    document.getElementById('serviceModalTitle').innerText = '+ Add New Citizen Service';
    document.getElementById('svcAction').value = 'add_service';
    document.getElementById('svcId').value = '';
    document.getElementById('svcName').value = '';
    document.getElementById('svcFee').value = '100.00';
    document.getElementById('svcDesc').value = '';
    document.getElementById('svcStatus').value = 'active';
    document.querySelectorAll('.dash-doc-chk').forEach(c => c.checked = false);
    document.getElementById('dash_custom_docs').value = '';
    document.getElementById('svcSubmitBtn').innerText = 'Create Service';
    document.getElementById('serviceModal').classList.add('active');
}

function openEditServiceModal(svc) {
    document.getElementById('serviceModalTitle').innerText = '✏️ Edit Service: ' + svc.service_name;
    document.getElementById('svcAction').value = 'edit_service';
    document.getElementById('svcId').value = svc.id;
    document.getElementById('svcName').value = svc.service_name;
    document.getElementById('svcFee').value = parseFloat(svc.fee).toFixed(2);
    document.getElementById('svcDesc').value = svc.description || '';
    document.getElementById('svcStatus').value = svc.status;

    // Reset and fill document checklist
    document.querySelectorAll('.dash-doc-chk').forEach(c => c.checked = false);
    document.getElementById('dash_custom_docs').value = '';

    if (svc.required_docs) {
        try {
            let docs = JSON.parse(svc.required_docs);
            let customArr = [];
            if (Array.isArray(docs)) {
                docs.forEach(d => {
                    let dName = (typeof d === 'object' && d.name) ? d.name : d;
                    let found = false;
                    document.querySelectorAll('.dash-doc-chk').forEach(c => {
                        if (c.value.toLowerCase() === dName.toLowerCase() || c.value.toLowerCase().includes(dName.toLowerCase())) {
                            c.checked = true;
                            found = true;
                        }
                    });
                    if (!found) {
                        customArr.push(dName);
                    }
                });
                if (customArr.length > 0) {
                    document.getElementById('dash_custom_docs').value = customArr.join(', ');
                }
            }
        } catch(e) {
            document.getElementById('dash_custom_docs').value = svc.required_docs;
        }
    }

    document.getElementById('svcSubmitBtn').innerText = 'Update Service';
    document.getElementById('serviceModal').classList.add('active');
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.remove('active');
}

// Close service modal when clicking outside box
window.addEventListener('click', function(e) {
    const sModal = document.getElementById('serviceModal');
    if (e.target === sModal) {
        closeServiceModal();
    }
});
</script>
</body>
</html>
