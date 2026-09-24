<?php
// admin/services.php - Manage Digital Services Catalog & Fees
require_once __DIR__ . '/../config/database.php';
require_admin();

$msg = '';
$err = '';

// Handle Add Service
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
        $msg = "New service '{$name}' created successfully with configured document requirements!";
    }
}

// Handle Update Service
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
        $msg = "Service updated successfully with updated document requirements!";
    }
}

// Common document definitions for selection
$commonDocuments = [
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

// Handle Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_service_status') {
    $svcId = (int)($_POST['service_id'] ?? 0);
    $newStatus = ($_POST['new_status'] === 'inactive') ? 'inactive' : 'active';

    $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $svcId]);
    $msg = "Service status changed to " . ucfirst($newStatus) . ".";
}

// Handle Delete / Remove Service
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

// Fetch all services
$services = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM applications WHERE service_id = s.id) as app_count
    FROM services s
    ORDER BY s.id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Digital Sewa Assam Admin</title>
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
            <a href="services.php" style="color: #ffffff !important; font-weight: 700; background: #0284c7; padding: 6px 14px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Services</a>
            <a href="settings.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">⚙️ Settings</a>
            <a href="change_password.php" style="color: #ffffff !important; font-weight: 600; opacity: 0.9;">🔐 Change Password</a>
            <a href="../index.php" target="_blank" style="color: #38bdf8 !important; font-weight: 600; background: rgba(56, 189, 248, 0.1); padding: 5px 10px; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3);">🌐 Live Site ↗</a>
            <a href="../logout.php" class="logout-btn" style="background: #dc2626 !important; color: #ffffff !important; font-weight: 700; padding: 6px 14px; border-radius: 6px;">Sign Out</a>
        </nav>
    </div>
</header>

<div class="container" style="padding: 30px 0 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: #000000;">Services Catalog & Required Documents</h1>
            <p style="color: #64748b; font-size: 14px;">Define citizen services, set government/operator fees, and customize mandatory upload documents per service.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a href="export_applications.php?format=xls" class="btn success sm" style="background: #15803d; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                📊 Export Applications (.XLS)
            </a>
            <button onclick="document.getElementById('addServiceCard').scrollIntoView({behavior: 'smooth'})" class="btn primary" style="background: #7c3aed; font-weight: 700;">
                + Add New Service
            </button>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success">✓ <?= $msg ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
        <div class="alert alert-danger">⚠️ <?= $err ?></div>
    <?php endif; ?>

    <!-- SERVICES TABLE -->
    <div class="content-card" style="margin-bottom: 35px;">
        <div class="card-title-bar">
            <h3>Active & Configured Services (<?= count($services) ?>)</h3>
        </div>

        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Govt / Portal Fee</th>
                        <th>Required Documents</th>
                        <th>Applications Filed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                        <?php 
                        $svcDocs = get_service_required_documents($s['service_name'], $s['id']);
                        ?>
                        <tr>
                            <td>#<?= $s['id'] ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 28px; height: 28px; flex-shrink: 0;">
                                        <?= get_service_logo_html($s['service_name'], '../') ?>
                                    </div>
                                    <b style="color: #050303; font-size: 15px;"><?= htmlspecialchars($s['service_name']) ?></b>
                                </div>
                            </td>
                            <td style="max-width: 220px; color: #000000; font-size: 13px;">
                                <?= htmlspecialchars($s['description'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <b style="color: #15803d; font-size: 16px;">
                                    <?= format_currency($s['fee']) ?>
                                </b>
                            </td>
                            <td>
                                <div style="font-size: 11px; max-width: 230px; line-height: 1.3;">
                                    <span class="badge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; font-size: 11px; margin-bottom: 4px; display: inline-block;">
                                        📋 <?= count($svcDocs) ?> Documents Configured
                                    </span>
                                    <div style="color: #000000; font-weight: 600; font-size: 11px;">
                                        <?= htmlspecialchars(implode(', ', array_slice(array_column($svcDocs, 'name'), 0, 2))) ?>
                                        <?= count($svcDocs) > 2 ? '... (+' . (count($svcDocs) - 2) . ' more)' : '' ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="applications.php?service_id=<?= $s['id'] ?>" style="font-weight: 700; color: var(--primary);">
                                    <?= $s['app_count'] ?> Applications ↗
                                </a>
                            </td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="status-badge badge-approved">Active</span>
                                <?php else: ?>
                                    <span class="status-badge badge-rejected">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: nowrap;">
                                    <button onclick="editService(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn secondary sm" title="Edit service details & fee">
                                        ✏️ Edit
                                    </button>

                                    <form action="services.php" method="POST" style="display: inline; margin: 0;">
                                        <input type="hidden" name="action" value="toggle_service_status">
                                        <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                                        <?php if ($s['status'] === 'active'): ?>
                                            <input type="hidden" name="new_status" value="inactive">
                                            <button type="submit" class="btn warning sm" style="background: #ea580c; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Hide service from citizens">
                                                ⏸ Deactivate
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" class="btn success sm" style="background: #16a34a; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Make service available to citizens">
                                                ▶ Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <form action="services.php" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('⚠️ PERMANENT REMOVAL WARNING:\n\nAre you sure you want to completely delete service \'<?= htmlspecialchars(addslashes($s['service_name'])) ?>\'?\n\nThis will remove the service from the catalog. This action cannot be undone.')">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn danger sm" style="background: #dc2626; color: white; padding: 5px 9px; font-size: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" title="Permanently Delete Service">
                                            🗑 Remove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ADD SERVICE FORM -->
    <div class="content-card" id="addServiceCard" style="max-width: 850px; margin: auto;">
        <div class="card-title-bar">
            <h3 id="formTitle">+ Add New Citizen Service</h3>
        </div>
        <div style="padding: 24px;">
            <form action="services.php" method="POST" id="serviceForm">
                <input type="hidden" name="action" value="add_service" id="formAction">
                <input type="hidden" name="service_id" value="" id="formServiceId">

                <div class="form-row">
                    <div class="form-group">
                        <label for="service_name">Service Title *</label>
                        <input type="text" id="service_name" name="service_name" required placeholder="e.g. Disability Certificate Apply">
                    </div>
                    <div class="form-group">
                        <label for="fee">Processing / Govt Fee (₹) *</label>
                        <input type="number" id="fee" name="fee" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="description">Service Description</label>
                        <input type="text" id="description" name="description" placeholder="Brief explanation of requirement and processing...">
                    </div>
                    <div class="form-group">
                        <label for="status">Initial Availability</label>
                        <select id="status" name="status">
                            <option value="active">Active (Available for applications)</option>
                            <option value="inactive">Inactive (Draft / Hidden)</option>
                        </select>
                    </div>
                </div>

                <!-- REQUIRED DOCUMENTS CHECKLIST -->
                <div style="margin: 20px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <label style="font-weight: 700; font-size: 14px; color: #0f172a; margin: 0;">
                                📄 Required Documents for this Service (Check to Require)
                            </label>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                Selected documents will be required from citizens in the application form for this specific service.
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="selectAllDocs(true)" style="background: none; border: none; color: #7c3aed; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline;">Select All</button>
                            <span style="color: #cbd5e1;">|</span>
                            <button type="button" onclick="selectAllDocs(false)" style="background: none; border: none; color: #64748b; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: underline;">Clear All</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px; margin-bottom: 16px;">
                        <?php foreach ($commonDocuments as $docOption): ?>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #000000; font-weight: 600; background: white; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer;">
                                <input type="checkbox" name="req_docs[]" value="<?= htmlspecialchars($docOption) ?>" class="doc-chk" style="width: auto;">
                                <span><?= htmlspecialchars($docOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label for="custom_docs" style="font-weight: 600; font-size: 12px; color: #475569;">
                            + Extra / Custom Document Names (comma-separated):
                        </label>
                        <input type="text" id="custom_docs" name="custom_docs" placeholder="e.g. Village Headman Certificate, Land NOC, Medical Fitness Certificate" style="background: white;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px;">
                    <button type="button" class="btn secondary" id="cancelEditBtn" style="display: none;" onclick="resetServiceForm()">Cancel Edit</button>
                    <button type="submit" class="btn primary" id="saveServiceBtn" style="background: #7c3aed; font-weight: 700;">
                        Save Service →
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html(true) ?>

<script>
function selectAllDocs(check) {
    document.querySelectorAll('.doc-chk').forEach(c => c.checked = check);
}

function editService(service) {
    document.getElementById('formTitle').innerText = "✏️ Edit Service: " + service.service_name;
    document.getElementById('formAction').value = "edit_service";
    document.getElementById('formServiceId').value = service.id;
    document.getElementById('service_name').value = service.service_name;
    document.getElementById('fee').value = service.fee;
    document.getElementById('description').value = service.description || '';
    document.getElementById('status').value = service.status;

    // Reset document checkboxes and custom input
    document.querySelectorAll('.doc-chk').forEach(c => c.checked = false);
    document.getElementById('custom_docs').value = '';

    // If service has required_docs configured
    if (service.required_docs) {
        try {
            let docs = JSON.parse(service.required_docs);
            let customArr = [];
            if (Array.isArray(docs)) {
                docs.forEach(d => {
                    let dName = (typeof d === 'object' && d.name) ? d.name : d;
                    let found = false;
                    document.querySelectorAll('.doc-chk').forEach(c => {
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
                    document.getElementById('custom_docs').value = customArr.join(', ');
                }
            }
        } catch(e) {
            document.getElementById('custom_docs').value = service.required_docs;
        }
    }

    document.getElementById('saveServiceBtn').innerText = "Update Service";
    document.getElementById('cancelEditBtn').style.display = "inline-flex";
    document.getElementById('addServiceCard').scrollIntoView({behavior: 'smooth'});
}

function resetServiceForm() {
    document.getElementById('formTitle').innerText = "+ Add New Citizen Service";
    document.getElementById('formAction').value = "add_service";
    document.getElementById('formServiceId').value = "";
    document.getElementById('serviceForm').reset();
    document.querySelectorAll('.doc-chk').forEach(c => c.checked = false);
    document.getElementById('custom_docs').value = '';
    document.getElementById('saveServiceBtn').innerText = "Save Service →";
    document.getElementById('cancelEditBtn').style.display = "none";
}
</script>
<script src="../assets/js/script.js"></script>
</body>
</html>
