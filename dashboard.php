<?php
// dashboard.php - Citizen Dashboard
require_once __DIR__ . '/config/database.php';
require_login();

$user = get_current_user_data($pdo);
$userId = $_SESSION['user_id'];

// Get user application counts
$statsQuery = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as review_count,
        SUM(CASE WHEN status IN ('approved', 'completed') THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM applications 
    WHERE user_id = ?
");
$statsQuery->execute([$userId]);
$stats = $statsQuery->fetch();

// Fetch user applications
$appQuery = $pdo->prepare("
    SELECT a.*, s.service_name, s.fee,
           (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) as doc_count
    FROM applications a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.user_id = ? 
    ORDER BY a.created_at DESC
");
$appQuery->execute([$userId]);
$applications = $appQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Digital Sewa Assam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">☰</button>
        <nav id="navbar">
            <a href="index.php">Home</a>
            <a href="dashboard.php" style="color: var(--primary); font-weight: 700;">My Dashboard</a>
            <a href="apply.php" class="btn primary sm">+ Apply New Service</a>
            <a href="track.php">Track Application</a>
            <a href="profile.php">My Profile</a>
            <?php if (is_admin()): ?>
                <a href="admin/dashboard.php" class="admin-badge">Admin Panel</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>

<!-- DASHBOARD HEADER -->
<div class="dashboard-header">
    <div class="container">
        <div class="dashboard-nav-strip">
            <div>
                <span class="badge" style="background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.25); color: #7dd3fc;">
                    Citizen Workspace
                </span>
                <h1 style="font-size: 28px; font-weight: 800; margin: 8px 0;">
                    Namaskar, <?= htmlspecialchars($user['name'] ?? 'Citizen') ?>!
                </h1>
                <p style="color: #cbd5e1; font-size: 14px;">
                    Mobile: <?= htmlspecialchars($user['mobile'] ?? '') ?> • Email: <?= htmlspecialchars($user['email'] ?? 'Not set') ?>
                </p>
            </div>
            <div>
                <a href="apply.php" class="btn primary" style="background: #38bdf8; color: #082f49 !important; font-weight: 700;">
                    + New Service Application
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container" style="padding-bottom: 60px;">
    <?php if (isset($_GET['welcome'])): ?>
        <div class="alert alert-success">
            🎉 <b>Welcome to Digital Sewa Assam!</b> Your citizen account has been successfully created.
        </div>
    <?php endif; ?>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">📂</div>
            <div>
                <div class="stat-val"><?= (int)($stats['total'] ?? 0) ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">⏳</div>
            <div>
                <div class="stat-val"><?= (int)($stats['review_count'] ?? 0) + (int)($stats['submitted_count'] ?? 0) ?></div>
                <div class="stat-label">In Processing</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #16a34a;">✅</div>
            <div>
                <div class="stat-val"><?= (int)($stats['approved_count'] ?? 0) ?></div>
                <div class="stat-label">Approved / Completed</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #dc2626;">❌</div>
            <div>
                <div class="stat-val"><?= (int)($stats['rejected_count'] ?? 0) ?></div>
                <div class="stat-label">Rejected / Action Needed</div>
            </div>
        </div>
    </div>

    <!-- APPLICATIONS TABLE -->
    <div class="content-card">
        <div class="card-title-bar">
            <h3>My Service Applications</h3>
            <a href="apply.php" class="btn secondary sm">+ Apply Another</a>
        </div>

        <?php if (empty($applications)): ?>
            <div style="padding: 50px 20px; text-align: center; color: #64748b;">
                <div style="font-size: 48px; margin-bottom: 14px;">📝</div>
                <h4 style="font-size: 18px; color: #1e293b; margin-bottom: 8px;">No Applications Submitted Yet</h4>
                <p style="font-size: 14px; max-width: 480px; margin: 0 auto 20px;">
                    You haven't applied for any digital services yet. Choose from PAN card, Voter ID, Driving Licence, Certificates, and more.
                </p>
                <a href="apply.php" class="btn primary">Start Your First Application →</a>
            </div>
        <?php else: ?>
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Application ID</th>
                            <th>Service Requested</th>
                            <th>Applied On</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <a href="track.php?app_id=<?= urlencode($app['application_id']) ?>" style="font-weight: 700; color: var(--primary);">
                                        <?= htmlspecialchars($app['application_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <?= get_service_logo_html($app['service_name']) ?>
                                        </div>
                                        <div>
                                            <b><?= htmlspecialchars($app['service_name']) ?></b>
                                            <div style="font-size: 12px; color: #64748b;">Applicant: <?= htmlspecialchars($app['full_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($app['created_at'])) ?>
                                    <div style="font-size: 11px; color: #94a3b8;"><?= date('h:i A', strtotime($app['created_at'])) ?></div>
                                </td>
                                <td>
                                    <?= format_currency($app['fee']) ?>
                                </td>
                                <td>
                                    <?= get_status_badge($app['status']) ?>
                                    <?php if (($app['document_status'] ?? '') === 'rejected'): ?>
                                        <div style="margin-top: 3px;">
                                            <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 4px; padding: 1px 6px; font-size: 10px; font-weight: 700; display: inline-block;">
                                                ❌ False Doc / Re-upload
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($app['payment_status'] === 'paid' || $app['payment_status'] === 'verified'): ?>
                                        <span class="status-badge badge-paid">✓ Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <a href="track.php?app_id=<?= urlencode($app['application_id']) ?>" class="btn secondary sm">
                                            Track Details
                                        </a>
                                        <?php if ($app['status'] === 'rejected' || ($app['document_status'] ?? '') === 'rejected'): ?>
                                            <a href="reupload.php?app_id=<?= urlencode($app['application_id']) ?>" class="btn danger sm" style="background: #dc2626; color: white !important; font-weight: 700;" title="Click to upload genuine document">
                                                ✏️ Re-upload
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($app['status'] === 'completed' && !empty($app['issued_document'])): ?>
                                            <a href="<?= htmlspecialchars($app['issued_document']) ?>" target="_blank" download class="btn success sm" style="background: #16a34a; color: white !important;">
                                                📥 PDF ↗
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

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
</body>
</html>
