<?php
// admin/users.php - Citizen and User Management
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

// Handle Platform Registration Fee UTR Verification & Account Activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_platform_fee' || $_POST['action'] === 'approve_user') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId > 0) {
            $pdo->prepare("UPDATE users SET status = 'active', platform_fee_paid = 1, platform_fee_status = 'verified', updated_at = NOW() WHERE id = ?")->execute([$targetUserId]);
            $msg = "✓ Citizen account #$targetUserId platform fee verified and account ACTIVATED successfully!";
        }
    } elseif ($_POST['action'] === 'reject_platform_fee' || $_POST['action'] === 'reject_user') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId > 0) {
            $pdo->prepare("UPDATE users SET status = 'blocked', platform_fee_paid = 0, platform_fee_status = 'rejected', updated_at = NOW() WHERE id = ?")->execute([$targetUserId]);
            $msg = "❌ Registration fee for user #$targetUserId rejected and account suspended.";
        }
    } elseif ($_POST['action'] === 'reset_platform_fee') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId > 0) {
            $pdo->prepare("UPDATE users SET status = 'processing', platform_fee_status = 'pending', updated_at = NOW() WHERE id = ?")->execute([$targetUserId]);
            $msg = "↺ Platform fee and account status reset to processing check.";
        }
    }
}

// Handle Manual User Creation by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
    $status = ($_POST['status'] === 'blocked') ? 'blocked' : 'active';

    if (empty($name)) {
        $err = "Citizen/User full name is required.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $err = "Please provide a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
    } elseif (strlen($password) < 6) {
        $err = "Password must be at least 6 characters long.";
    } else {
        // Check if mobile already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
        $checkStmt->execute([$mobile]);
        if ($checkStmt->fetch()) {
            $err = "An account with mobile number $mobile already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (name, mobile, email, password, raw_password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$name, $mobile, !empty($email) ? $email : null, $hashedPassword, $password, $role, $status]);
            $msg = "🎉 New user account for '$name' (+91 $mobile) created successfully!";
        }
    }
}

// Handle Edit User (Name, Mobile, Email, Role, Status) by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
    $status = ($_POST['status'] === 'blocked') ? 'blocked' : 'active';

    if ($targetUserId <= 0) {
        $err = "Invalid user specified.";
    } elseif (empty($name)) {
        $err = "User full name is required.";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $err = "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
    } else {
        // Check if mobile is used by another user
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$mobile, $targetUserId]);
        if ($checkStmt->fetch()) {
            $err = "Mobile number +91 $mobile is already registered to another user.";
        } else {
            // Prevent admin from blocking himself
            if ($targetUserId === (int)$_SESSION['user_id'] && $status === 'blocked') {
                $status = 'active';
            }
            $upd = $pdo->prepare("UPDATE users SET name = ?, mobile = ?, email = ?, role = ?, status = ? WHERE id = ?");
            $upd->execute([$name, $mobile, !empty($email) ? $email : null, $role, $status, $targetUserId]);
            $msg = "✓ Account details for citizen '$name' (+91 $mobile) updated successfully!";
        }
    }
}

// Handle Reset / Change Password by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_user_password') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newPass = trim($_POST['new_password'] ?? '');

    if ($targetUserId <= 0) {
        $err = "Invalid user selected.";
    } elseif (strlen($newPass) < 6) {
        $err = "Password must be at least 6 characters long.";
    } else {
        $userStmt = $pdo->prepare("SELECT id, name, mobile, role FROM users WHERE id = ?");
        $userStmt->execute([$targetUserId]);
        $uData = $userStmt->fetch();

        if ($uData) {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ?, raw_password = ? WHERE id = ?");
            $upd->execute([$hashed, $newPass, $targetUserId]);

            $uName = htmlspecialchars($uData['name']);
            $uMob = htmlspecialchars($uData['mobile']);
            $safePass = htmlspecialchars($newPass);

            if ($targetUserId === (int)$_SESSION['user_id']) {
                $msg = "🔐 Your administrator password has been updated successfully to: <code>{$safePass}</code>";
            } else {
                $cleanMob = preg_replace('/[^0-9]/', '', $uData['mobile']);
                if (strlen($cleanMob) === 10) $cleanMob = '91' . $cleanMob;
                $waText = "Namaste {$uData['name']}, your Digital Sewa Assam login password has been reset by the administrator.\n\nYour new password is: {$newPass}\n\nYou can log in here: http://localhost/Website/login.php";
                $waLink = "https://wa.me/{$cleanMob}?text=" . urlencode($waText);

                $msg = "✓ Password for citizen <b>{$uName}</b> (+91 {$uMob}) has been reset successfully to: <code>{$safePass}</code> &nbsp; " .
                       "<a href='{$waLink}' target='_blank' class='btn success sm' style='background: #25d366; color: white; text-decoration: none; padding: 4px 10px; border-radius: 6px; font-weight: 700; margin-left: 8px; display: inline-flex; align-items: center; gap: 4px;'>💬 Send to Citizen via WhatsApp ↗</a>";
            }
        } else {
            $err = "User record not found.";
        }
    }
}

// Handle Status Toggle (Suspend/Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['new_status'] === 'blocked') ? 'blocked' : 'active';

    if ($targetUserId === (int)$_SESSION['user_id']) {
        $err = "You cannot suspend your own administrator account.";
    } else {
        $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $userStmt->execute([$targetUserId]);
        $targetUser = $userStmt->fetch();
        $userName = $targetUser ? htmlspecialchars($targetUser['name']) : 'User';

        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $targetUserId]);
        $actionVerb = ($newStatus === 'blocked') ? 'suspended' : 'activated';
        $msg = "Account for '$userName' has been $actionVerb successfully.";
    }
}

// Handle Permanent User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId === (int)$_SESSION['user_id']) {
        $err = "You cannot delete your own logged-in administrator account.";
    } else {
        $userStmt = $pdo->prepare("SELECT name, mobile FROM users WHERE id = ?");
        $userStmt->execute([$targetUserId]);
        $targetUser = $userStmt->fetch();

        if ($targetUser) {
            try {
                $pdo->beginTransaction();

                // Find and cleanup user's application documents/files
                $appStmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ?");
                $appStmt->execute([$targetUserId]);
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
                    $pdo->prepare("DELETE FROM applications WHERE user_id = ?")->execute([$targetUserId]);
                }

                $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $deleteStmt->execute([$targetUserId]);

                $pdo->commit();
                $msg = "🗑 Citizen account for '" . htmlspecialchars($targetUser['name']) . "' (+91 " . htmlspecialchars($targetUser['mobile']) . ") has been permanently deleted.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $err = "Failed to delete user: " . $e->getMessage();
            }
        } else {
            $err = "User record not found or already deleted.";
        }
    }
}

// Search & Filters
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($statusFilter) && in_array($statusFilter, ['active', 'blocked', 'processing'])) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM applications WHERE user_id = u.id) as app_count
    FROM users u
    WHERE $where
    ORDER BY u.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Metrics
$totalCitizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$activeCitizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'")->fetchColumn();
$processingCitizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status IN ('processing', 'pending')")->fetchColumn();
$blockedCitizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'blocked'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Digital Sewa Assam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ADMIN HEADER -->
<header class="header" style="background: #0f172a; border-bottom: 2px solid #1e293b; padding: 4px 0;">
    <div class="container nav">
        <?= logo_html_admin('dashboard.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" style="color: #ffffff; font-size: 26px;">☰</button>
        <nav id="navbar">
            <a href="dashboard.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Dashboard</a>
            <a href="applications.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Applications</a>
            <a href="users.php" class="active" style="color: #ffffff !important; background: #0284c7; font-weight: 800; padding: 7px 14px; border-radius: 8px;">Users</a>
            <a href="services.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">Services</a>
            <a href="settings.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #ffffff !important; font-weight: 700; padding: 7px 12px; border-radius: 8px;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #38bdf8 !important; font-weight: 700; font-size: 13px; background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.4); padding: 6px 12px; border-radius: 8px; text-decoration: none;">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn" style="background: #dc2626 !important; color: #ffffff !important; font-weight: 800; padding: 7px 16px; border-radius: 8px; text-decoration: none;">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 30px 0 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #05080a;">Registered Citizens & Accounts</h1>
            <p style="color: #242d3a; font-size: 14px;">Manage user access, review registered citizens, and validate UTR payments for account activation.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="export_users.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="btn success" style="background: #15803d; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; color: white; padding: 10px 16px; border-radius: 8px;" title="Export users to Microsoft Excel">
                📊 Export to Excel
            </a>
            <button onclick="document.getElementById('createUserModal').style.display='flex'" class="btn primary" style="background: #7c3aed; font-weight: 700;">
                + Create New User
            </button>
            <span class="badge" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35); color: #fbbf24;">
                ⏳ Awaiting Approval: <b><?= (int)$processingCitizens ?></b>
            </span>
            <span class="badge" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399;">
                Active: <b><?= (int)$activeCitizens ?></b>
            </span>
            <span class="badge" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171;">
                Suspended: <b><?= (int)$blockedCitizens ?></b>
            </span>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">⚠️ <?= $err ?></div>
    <?php endif; ?>

    <!-- CREATE USER MODAL -->
    <div id="createUserModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div class="content-card" style="width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border-radius: 16px; overflow: hidden; background: white;">
            <div class="card-title-bar" style="background: #0f172a; color: white; border-bottom: none;">
                <h3 style="color: white; font-size: 17px;">👤 Create User Account Manually</h3>
                <button type="button" onclick="document.getElementById('createUserModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 24px;">
                <p style="font-size: 13px; color: #64748b; margin-bottom: 18px;">
                    Enter citizen details below to register their account directly into the portal.
                </p>
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="create_user">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="new_name" style="font-size: 13px; font-weight: 600;">Full Name *</label>
                        <input type="text" id="new_name" name="name" placeholder="e.g. Rupesh Kalita" required style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="new_mobile" style="font-size: 13px; font-weight: 600;">Mobile Number (10 Digits) *</label>
                        <input type="tel" id="new_mobile" name="mobile" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" maxlength="10" required style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="new_email" style="font-size: 13px; font-weight: 600;">Email Address (Optional)</label>
                        <input type="email" id="new_email" name="email" placeholder="e.g. citizen@example.com" style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="new_password" style="font-size: 13px; font-weight: 600;">Initial Password *</label>
                        <input type="password" id="new_password" name="password" placeholder="Minimum 6 characters" minlength="6" required style="width: 100%;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                        <div class="form-group" style="margin: 0;">
                            <label for="new_role" style="font-size: 13px; font-weight: 600;">Account Role</label>
                            <select id="new_role" name="role" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <option value="user" selected>Citizen (Regular)</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label for="new_status" style="font-size: 13px; font-weight: 600;">Initial Status</label>
                            <select id="new_status" name="status" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                                <option value="active" selected>Active</option>
                                <option value="blocked">Suspended</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('createUserModal').style.display='none'" class="btn secondary">
                            Cancel
                        </button>
                        <button type="submit" class="btn primary" style="background: #7c3aed; font-weight: 700;">
                            ✓ Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="content-card">
        <form action="users.php" method="GET" class="filter-bar" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="🔍 Search by name, mobile, or email..." value="<?= htmlspecialchars($search) ?>" style="min-width: 260px;">
                <select name="status" style="padding: 10px 14px; border: 1px solid var(--card-border); border-radius: 9px; font-size: 14px; background: var(--card-dark); color: var(--text);">
                    <option value="">-- All Account Statuses --</option>
                    <option value="processing" <?= ($statusFilter === 'processing') ? 'selected' : '' ?>>⏳ Awaiting Approval (Processing)</option>
                    <option value="active" <?= ($statusFilter === 'active') ? 'selected' : '' ?>>Active Only</option>
                    <option value="blocked" <?= ($statusFilter === 'blocked') ? 'selected' : '' ?>>Suspended Only</option>
                </select>
                <button type="submit" class="btn primary sm" style="background: #7c3aed;">Search & Filter</button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="users.php" class="btn secondary sm">Clear</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="export_users.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="btn success sm" style="background: #15803d; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: white;" title="Export filtered users to Excel (.CSV)">
                    📊 Export (.CSV)
                </a>
                <a href="export_users.php?search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&format=xls" class="btn secondary sm" style="font-weight: 700; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;" title="Export filtered users to Excel (.XLS)">
                    📑 Export (.XLS)
                </a>
            </div>
        </form>

        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Password (Recovery)</th>
                        <th>Platform Fee & UTR</th>
                        <th>Total Applications</th>
                        <th>Registered Date</th>
                        <th>Status</th>
                        <th style="min-width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px; color: #64748b;">
                                No users found matching search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td>
                                    <b><?= htmlspecialchars($u['name']) ?></b>
                                </td>
                                <td><?= htmlspecialchars($u['mobile']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?? 'Not set') ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="admin-badge">Admin</span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; font-weight: 600; color: #475569;">Citizen</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($u['raw_password'])): ?>
                                        <div style="display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 3px 8px; border-radius: 6px;">
                                            <code id="pwd-text-<?= $u['id'] ?>" style="font-family: monospace; font-size: 13px; font-weight: 700; color: #0f172a;" data-real="<?= htmlspecialchars($u['raw_password']) ?>" data-masked="••••••••">••••••••</code>
                                            <button type="button" onclick="togglePasswordView(<?= $u['id'] ?>)" id="pwd-btn-<?= $u['id'] ?>" class="btn secondary sm" style="padding: 2px 6px; font-size: 11px; line-height: 1; border: 1px solid #cbd5e1; background: white;" title="Show/Hide Password">
                                                👁️
                                            </button>
                                            <button type="button" onclick="copyPasswordText('<?= htmlspecialchars(addslashes($u['raw_password'])) ?>', this)" class="btn secondary sm" style="padding: 2px 6px; font-size: 11px; line-height: 1; border: 1px solid #cbd5e1; background: white;" title="Copy to Clipboard">
                                                📋
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Encrypted Only</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">Exempt (Admin)</span>
                                    <?php else: ?>
                                        <?php 
                                            $feeStatus = $u['platform_fee_status'] ?? ($u['platform_fee_paid'] ? 'verified' : 'pending');
                                        ?>
                                        <?php if (!empty($u['platform_fee_txn'])): ?>
                                            <div style="margin-bottom: 4px;">
                                                <div style="display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 6px; border-radius: 5px; font-family: monospace; font-size: 11px; color: #1e3a8a; font-weight: 700;">
                                                    UTR: <?= htmlspecialchars($u['platform_fee_txn']) ?>
                                                    <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(addslashes($u['platform_fee_txn'])) ?>')" style="background:none; border:none; cursor:pointer; font-size:11px;" title="Copy UTR">📋</button>
                                                </div>
                                            </div>
                                            <div>
                                                <?php if ($feeStatus === 'verified'): ?>
                                                    <span style="display: inline-block; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700;">✓ Fee Verified</span>
                                                    <form method="POST" action="users.php" style="display:inline; margin-left:4px;" onsubmit="return confirm('Reset fee status to pending?');">
                                                        <input type="hidden" name="action" value="reset_platform_fee">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn secondary sm" style="padding: 1px 5px; font-size: 9px;" title="Reset verification">↺</button>
                                                    </form>
                                                <?php elseif ($feeStatus === 'rejected'): ?>
                                                    <span style="display: inline-block; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700;">❌ Fee Rejected</span>
                                                    <form method="POST" action="users.php" style="display:inline; margin-left:4px;" onsubmit="return confirm('Reset fee status to pending?');">
                                                        <input type="hidden" name="action" value="reset_platform_fee">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <button type="submit" class="btn secondary sm" style="padding: 1px 5px; font-size: 9px;" title="Reset verification">↺</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="display: inline-block; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700;">⏳ Awaiting Check</span>
                                                    <div style="display: flex; gap: 4px; margin-top: 4px;">
                                                        <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Validate UTR and ACTIVATE account for <?= htmlspecialchars(addslashes($u['name'])) ?>?');">
                                                            <input type="hidden" name="action" value="approve_user">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="btn sm" style="background:#16a34a; color:white; padding:2px 6px; font-size:10px; font-weight:700; border-radius:4px;" title="Approve UTR & Activate Account">✓ Verify & Activate</button>
                                                        </form>
                                                        <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Reject platform fee for <?= htmlspecialchars(addslashes($u['name'])) ?>?');">
                                                            <input type="hidden" name="action" value="reject_user">
                                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                            <button type="submit" class="btn sm" style="background:#dc2626; color:white; padding:2px 6px; font-size:10px; font-weight:700; border-radius:4px;">❌ Reject</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (!empty($u['platform_fee_paid'])): ?>
                                            <span style="color: #15803d; font-size: 11px; font-weight: 600;">✓ Paid (Direct)</span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 11px;">Not Paid / None</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['app_count'] > 0): ?>
                                        <a href="applications.php?search=<?= urlencode($u['mobile']) ?>" style="font-weight: 700; color: var(--primary);">
                                            <?= $u['app_count'] ?> Applications ↗
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="status-badge badge-approved" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 12px; border: 1px solid rgba(16, 185, 129, 0.3);">Active</span>
                                    <?php elseif ($u['status'] === 'processing' || $u['status'] === 'pending'): ?>
                                        <span class="status-badge badge-pending" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-size: 12px; border: 1px solid rgba(245, 158, 11, 0.3);">⏳ Processing</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-rejected" style="background: rgba(239, 68, 68, 0.15); color: #f87171; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.3);">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <div style="display: inline-flex; gap: 6px; align-items: center; white-space: nowrap;">
                                            <!-- Approve & Activate Button for Processing Accounts -->
                                            <?php if ($u['status'] === 'processing' || $u['status'] === 'pending'): ?>
                                                <form action="users.php" method="POST" style="margin: 0;" onsubmit="return confirm('Validate UTR and ACTIVATE account for <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                                    <input type="hidden" name="action" value="approve_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn success sm" style="background: #16a34a; color: white; padding: 6px 10px; font-size: 12px; font-weight: 700; border: none; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px;" title="Approve UTR & Activate User">
                                                        ✓ Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <!-- Edit User Button -->
                                            <button type="button" onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['mobile'])) ?>', '<?= htmlspecialchars(addslashes($u['email'] ?? '')) ?>', '<?= $u['role'] ?>', '<?= $u['status'] ?>')" class="btn primary sm" style="padding: 6px 9px; font-size: 12px; font-weight: 600; background: #2563eb;" title="Edit Citizen Details (Mobile, Name, etc.)">
                                                ✏️ Edit
                                            </button>

                                            <!-- Reset Password Button -->
                                            <button type="button" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['mobile'])) ?>', '<?= $u['role'] ?>', '<?= htmlspecialchars(addslashes($u['raw_password'] ?? '')) ?>')" class="btn secondary sm" style="padding: 6px 9px; font-size: 12px; font-weight: 600;" title="Reset / Change Citizen Password">
                                                🔑 Password
                                            </button>

                                            <!-- Suspend / Activate Button Form -->
                                            <form action="users.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to <?= ($u['status'] === 'active') ? 'SUSPEND this citizen account? They will not be able to log in.' : 'ACTIVATE this citizen account?' ?>')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <input type="hidden" name="new_status" value="blocked">
                                                    <button type="submit" class="btn warning sm" style="background: #ea580c; color: white; padding: 6px 10px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Suspend User Access">
                                                        ⏸ Suspend
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="new_status" value="active">
                                                    <button type="submit" class="btn success sm" style="background: #16a34a; color: white; padding: 6px 10px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Activate User Access">
                                                        ▶ Activate
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Permanent Delete Button Form -->
                                            <form action="users.php" method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ PERMANENT DELETE WARNING:\n\nAre you sure you want to delete citizen \'<?= htmlspecialchars(addslashes($u['name'])) ?>\' (+91 <?= htmlspecialchars($u['mobile']) ?>)?\n\nAll submitted applications, documents, and records for this user will be permanently deleted. This action CANNOT be undone.')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn danger sm" style="background: #dc2626; color: white; padding: 6px 10px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Permanently Delete User & Applications">
                                                    🗑 Delete
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: inline-flex; gap: 6px; align-items: center; white-space: nowrap;">
                                            <button type="button" onclick="openEditUserModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['mobile'])) ?>', '<?= htmlspecialchars(addslashes($u['email'] ?? '')) ?>', '<?= $u['role'] ?>', '<?= $u['status'] ?>')" class="btn primary sm" style="padding: 6px 9px; font-size: 12px; font-weight: 600; background: #2563eb;" title="Edit Your Name, Mobile & Email">
                                                ✏️ Edit
                                            </button>
                                            <button type="button" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['mobile'])) ?>', 'admin', '<?= htmlspecialchars(addslashes($u['raw_password'] ?? '')) ?>')" class="btn secondary sm" style="padding: 6px 10px; font-size: 12px; font-weight: 600; color: #4338ca; border-color: #c7d2fe;" title="Change your own administrator password">
                                                🔑 Password
                                            </button>
                                            <span style="font-size: 11px; color: #64748b; font-style: italic;">(You)</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RESET PASSWORD MODAL (ADMIN CONTROL) -->
<div id="resetPasswordModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="content-card" style="width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border-radius: 16px; overflow: hidden; background: white;">
        <div class="card-title-bar" style="background: #0f172a; color: white; border-bottom: none;">
            <h3 style="color: white; font-size: 17px;" id="resetModalTitle">🔑 Change / Reset Password</h3>
            <button type="button" onclick="closeResetPasswordModal()" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px;">
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700;">Account Target</div>
                <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 2px;" id="resetTargetName">Citizen Name</div>
                <div style="font-size: 13px; color: #475569;" id="resetTargetMobile">📞 +91 9876543210</div>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Current Saved Password:</span>
                        <code id="resetTargetCurrentPass" style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 13px; font-weight: 800; color: #0f172a; margin-left: 4px; font-family: monospace;">••••••••</code>
                    </div>
                    <button type="button" onclick="copyModalCurrentPass()" class="btn secondary sm" style="padding: 3px 9px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; background: white;" title="Copy current password">
                        📋 Copy
                    </button>
                </div>
            </div>

            <form action="users.php" method="POST">
                <input type="hidden" name="action" value="reset_user_password">
                <input type="hidden" name="user_id" id="resetUserId" value="">

                <div class="form-group" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="resetNewPass" style="font-size: 13px; font-weight: 600; margin: 0;">New Password *</label>
                        <button type="button" onclick="generateRandomPassword()" style="background: none; border: none; color: #7c3aed; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline;">
                            ⚡ Auto-Generate
                        </button>
                    </div>
                    <div style="position: relative;">
                        <input type="text" id="resetNewPass" name="new_password" required minlength="6" placeholder="Enter or generate new password" style="width: 100%; font-family: monospace; font-size: 15px; letter-spacing: 1px;">
                    </div>
                    <small style="font-size: 11px; color: #64748b; margin-top: 4px; display: block;">
                        Minimum 6 characters. After saving, you can send it to the citizen via WhatsApp.
                    </small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px;">
                    <button type="button" onclick="closeResetPasswordModal()" class="btn secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn primary" style="background: #7c3aed; font-weight: 700;">
                        Save & Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL (ADMIN CAN UPDATE MOBILE, NAME, EMAIL DIRECTLY WITHOUT OLD PASSWORD) -->
<div id="editUserModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="content-card" style="width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border-radius: 16px; overflow: hidden; background: white;">
        <div class="card-title-bar" style="background: #0f172a; color: white; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color: white; font-size: 17px; margin: 0;">✏️ Edit Citizen / User Details</h3>
            <button type="button" onclick="closeEditUserModal()" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <p style="font-size: 13px; color: #64748b; margin-bottom: 18px;">
                Update registered user details directly. Administrator privileges allow updating mobile numbers and account details without old passwords.
            </p>
            <form action="users.php" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId" value="">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="edit_name" style="font-size: 13px; font-weight: 600;">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="edit_mobile" style="font-size: 13px; font-weight: 700; color: #1e3a8a;">
                        10-Digit Mobile Number *
                    </label>
                    <input type="tel" id="edit_mobile" name="mobile" maxlength="10" pattern="[6-9][0-9]{9}" required style="width: 100%; border: 2px solid #3b82f6;">
                    <small style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
                        Change registered mobile number directly without needing citizen's password.
                    </small>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="edit_email" style="font-size: 13px; font-weight: 600;">Email Address (Optional)</label>
                    <input type="email" id="edit_email" name="email" style="width: 100%;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                    <div class="form-group" style="margin: 0;">
                        <label for="edit_role" style="font-size: 13px; font-weight: 600;">Account Role</label>
                        <select id="edit_role" name="role" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                            <option value="user">Citizen (Regular)</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="edit_status" style="font-size: 13px; font-weight: 600;">Account Status</label>
                        <select id="edit_status" name="status" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                            <option value="active">Active</option>
                            <option value="blocked">Suspended</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeEditUserModal()" class="btn secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn primary" style="background: #2563eb; font-weight: 700;">
                        ✓ Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html(true) ?>

<script src="../assets/js/script.js"></script>
<script>
function openEditUserModal(id, name, mobile, email, role, status) {
    document.getElementById('editUserId').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_mobile').value = mobile;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

let currentModalPassword = '';

function openResetPasswordModal(id, name, mobile, role, currentPassword = '') {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetTargetName').innerText = name + (role === 'admin' ? ' (Administrator)' : ' (Citizen)');
    document.getElementById('resetTargetMobile').innerText = '📞 +91 ' + mobile;
    document.getElementById('resetModalTitle').innerText = (role === 'admin') ? '🔑 Change Your Administrator Password' : '🔑 Reset Citizen Password: ' + name;
    
    currentModalPassword = currentPassword || '';
    const passDisplay = document.getElementById('resetTargetCurrentPass');
    if (passDisplay) {
        passDisplay.innerText = currentModalPassword ? currentModalPassword : '(Encrypted / Not recorded)';
    }

    document.getElementById('resetNewPass').value = 'User@' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

function copyModalCurrentPass() {
    if (!currentModalPassword) {
        alert('No plain text password recorded for this account.');
        return;
    }
    navigator.clipboard.writeText(currentModalPassword).then(() => {
        alert('✓ Current password (' + currentModalPassword + ') copied to clipboard!');
    }).catch(() => {
        alert('Current password: ' + currentModalPassword);
    });
}

function togglePasswordView(id) {
    const el = document.getElementById('pwd-text-' + id);
    const btn = document.getElementById('pwd-btn-' + id);
    if (!el || !btn) return;
    
    if (el.innerText === '••••••••') {
        el.innerText = el.getAttribute('data-real');
        btn.innerText = '🙈';
        btn.title = 'Hide Password';
    } else {
        el.innerText = '••••••••';
        btn.innerText = '👁️';
        btn.title = 'Show Password';
    }
}

function copyPasswordText(pwd, btn) {
    if (!pwd) return;
    navigator.clipboard.writeText(pwd).then(() => {
        const oldText = btn.innerText;
        btn.innerText = '✓';
        btn.style.color = '#15803d';
        setTimeout(() => {
            btn.innerText = oldText;
            btn.style.color = '';
        }, 1500);
    }).catch(() => {
        alert('Password: ' + pwd);
    });
}

function generateRandomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
    let pass = 'Sewa@';
    for (let i = 0; i < 4; i++) {
        pass += Math.floor(Math.random() * 10);
    }
    document.getElementById('resetNewPass').value = pass;
}

// Close on background click
window.addEventListener('click', function(e) {
    const resetModal = document.getElementById('resetPasswordModal');
    if (e.target === resetModal) {
        closeResetPasswordModal();
    }
    const editModal = document.getElementById('editUserModal');
    if (e.target === editModal) {
        closeEditUserModal();
    }
    const createModal = document.getElementById('createUserModal');
    if (e.target === createModal) {
        createModal.style.display = 'none';
    }
});
</script>
</body>
</html>
