<?php
// reupload.php - Re-upload corrected documents after rejection
require_once __DIR__ . '/config/database.php';
require_login();

$appId = sanitize($_GET['app_id'] ?? $_POST['app_id'] ?? '');
if (empty($appId)) {
    header("Location: dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = is_admin();

// Fetch application
$stmt = $pdo->prepare("
    SELECT a.*, s.service_name, s.fee 
    FROM applications a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.application_id = ?
");
$stmt->execute([$appId]);
$application = $stmt->fetch();

if (!$application) {
    header("Location: dashboard.php?error=" . urlencode("Application not found."));
    exit;
}

// Ensure user owns application or is admin
if ($application['user_id'] != $userId && !$isAdmin) {
    header("Location: dashboard.php?error=" . urlencode("Unauthorized access."));
    exit;
}

// Fetch existing documents
$docStmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ? ORDER BY document_number ASC");
$docStmt->execute([$application['id']]);
$existingDocs = $docStmt->fetchAll();
$docMap = [];
foreach ($existingDocs as $d) {
    $docMap[$d['document_number']] = $d;
}

// Load service required document slots
$docSlots = get_service_required_documents($application['service_name'], $application['service_id']);
if (empty($docSlots) && !empty($existingDocs)) {
    $docSlots = [];
    foreach ($existingDocs as $d) {
        $num = (int)$d['document_number'];
        $docSlots['doc_' . $num] = [
            'num' => $num,
            'name' => $d['document_name'] ?? ('Document #' . $num),
            'desc' => 'Upload corrected document file (PDF, JPG, PNG)',
            'required' => true
        ];
    }
}

$error = '';
$success = '';

// Handle Re-upload Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $applicantNote = sanitize($_POST['applicant_note'] ?? '');
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    @chmod($uploadDir, 0777);

    if (!is_writable($uploadDir)) {
        throw new Exception("Server uploads folder is not writable. Please contact server administrator.");
    }

    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
    $reuploadedCount = 0;

    try {
        $pdo->beginTransaction();

        foreach ($docSlots as $slot => $info) {
            if (isset($_FILES[$slot]) && $_FILES[$slot]['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES[$slot]['tmp_name'];
                $originalName = $_FILES[$slot]['name'];
                $fileSize = $_FILES[$slot]['size'];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedExts)) {
                    throw new Exception("Invalid file format for " . $info['name'] . ". Only PDF, JPG, and PNG are allowed.");
                }

                if ($fileSize > 5 * 1024 * 1024) {
                    throw new Exception("File is too large for " . $info['name'] . ". Maximum size is 5MB.");
                }

                $safeName = 'reupload_doc_' . $info['num'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $uploadDir . $safeName;

                if (@move_uploaded_file($fileTmp, $destination)) {
                    @chmod($destination, 0666);
                    $newFilePath = 'uploads/' . $safeName;

                    // Check if record exists
                    if (isset($docMap[$info['num']])) {
                        $updStmt = $pdo->prepare("
                            UPDATE application_documents 
                            SET file_name = ?, file_path = ?, status = 'pending', rejection_reason = NULL, uploaded_at = NOW() 
                            WHERE application_id = ? AND document_number = ?
                        ");
                        $updStmt->execute([$originalName, $newFilePath, $application['id'], $info['num']]);
                    } else {
                        $insStmt = $pdo->prepare("
                            INSERT INTO application_documents (application_id, document_number, document_name, file_name, file_path, status, rejection_reason) 
                            VALUES (?, ?, ?, ?, ?, 'pending', NULL)
                        ");
                        $insStmt->execute([$application['id'], $info['num'], $info['name'], $originalName, $newFilePath]);
                    }

                    $reuploadedCount++;
                }
            }
        }

        if ($reuploadedCount === 0 && empty($applicantNote)) {
            throw new Exception("Please choose at least one document to re-upload or provide a note.");
        }

        // Update application status to under_review and document_status to pending
        $timestamp = date('d M Y, h:i A');
        $noteText = !empty($applicantNote) ? " [Applicant Note: " . $applicantNote . "]" : "";
        $newRemarks = "Fresh documents re-uploaded by citizen on {$timestamp}.{$noteText} Awaiting officer re-verification. (Previous note: " . ($application['remarks'] ?? 'None') . ")";

        $appUpdate = $pdo->prepare("
            UPDATE applications 
            SET status = 'under_review', document_status = 'pending', remarks = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $appUpdate->execute([$newRemarks, $application['id']]);

        $pdo->commit();

        header("Location: track.php?app_id=" . urlencode($application['application_id']) . "&reuploaded=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit & Re-upload Documents - <?= htmlspecialchars($application['application_id']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <nav id="navbar">
            <a href="dashboard.php">Dashboard</a>
            <a href="track.php?app_id=<?= urlencode($application['application_id']) ?>">Track Application</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 40px 0;">
    <div class="container">
        <div style="max-width: 820px; margin: auto;">
            
            <div style="margin-bottom: 24px;">
                <a href="track.php?app_id=<?= urlencode($application['application_id']) ?>" style="color: var(--primary); font-size: 13px; font-weight: 600;">← Back to Status Tracking</a>
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 6px;">
                    Edit & Re-upload Required Documents
                </h1>
                <p style="color: #64748b; font-size: 14px;">
                    Application Reference: <b><?= htmlspecialchars($application['application_id']) ?></b> • Service: <b><?= htmlspecialchars($application['service_name']) ?></b>
                </p>
            </div>

            <!-- OFFICER REJECTION REASON CALLOUT -->
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 5px solid #dc2626; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                    <span style="font-size: 18px;">⚠️</span>
                    <strong style="color: #991b1b; font-size: 15px;">Reason for Rejection / Document Correction Needed:</strong>
                </div>
                <div style="color: #7f1d1d; font-size: 14px; line-height: 1.5; background: white; padding: 12px; border-radius: 8px; border: 1px solid #fee2e2;">
                    <?= !empty($application['remarks']) ? nl2br(htmlspecialchars($application['remarks'])) : 'Document rejected due to clarity, formatting, or validity mismatch. Please upload corrected documents.' ?>
                </div>
                <p style="font-size: 12px; color: #991b1b; margin-top: 8px;">
                    * You only need to upload new files for the document(s) that were rejected or require correction. Other previously submitted valid files will be kept.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- RE-UPLOAD FORM -->
            <div class="content-card" style="padding: 32px;">
                <form action="reupload.php?app_id=<?= urlencode($application['application_id']) ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="app_id" value="<?= htmlspecialchars($application['application_id']) ?>">

                    <h3 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        Select Corrected Documents to Replace
                    </h3>

                    <?php foreach ($docSlots as $slotKey => $slot): 
                        $num = $slot['num'];
                    ?>
                        <?php 
                            $isRejectedDoc = (isset($docMap[$num]) && ($docMap[$num]['status'] ?? '') === 'rejected');
                        ?>
                        <div style="background: <?= $isRejectedDoc ? '#fff5f5' : '#f8fafc' ?>; border: <?= $isRejectedDoc ? '2px solid #ef4444' : '1px solid #e2e8f0' ?>; border-radius: 10px; padding: 16px; margin-bottom: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                <label for="<?= $slotKey ?>" style="font-weight: 700; font-size: 14px; color: #1e293b;">
                                    📄 Document <?= $num ?>: <?= htmlspecialchars($slot['name']) ?>
                                    <?= !empty($slot['required']) ? '<span style="color: #dc2626;">*</span>' : '<span style="color: #64748b; font-size: 12px; font-weight: normal;">(Optional)</span>' ?>
                                </label>
                                <?php if (isset($docMap[$num])): ?>
                                    <div style="font-size: 12px; display: flex; align-items: center; gap: 8px;">
                                        <?php if ($isRejectedDoc): ?>
                                            <span style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;">
                                                ❌ False / Rejected - Re-upload Required
                                            </span>
                                        <?php endif; ?>
                                        <span style="color: #64748b;">
                                            Current: <a href="<?= htmlspecialchars($docMap[$num]['file_path']) ?>" target="_blank" style="color: var(--primary); text-decoration: underline;"><?= htmlspecialchars($docMap[$num]['file_name']) ?></a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($isRejectedDoc && !empty($docMap[$num]['rejection_reason'])): ?>
                                <div style="font-size: 12px; color: #991b1b; background: #fee2e2; padding: 6px 10px; border-radius: 6px; margin-bottom: 8px; font-weight: 600;">
                                    ⚠️ Rejection Remark: <?= htmlspecialchars($docMap[$num]['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($slot['desc'])): ?>
                                <p style="font-size: 12px; color: #64748b; margin: 0 0 8px;"><?= htmlspecialchars($slot['desc']) ?></p>
                            <?php endif; ?>
                            <input type="file" id="<?= $slotKey ?>" name="<?= $slotKey ?>" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    <?php endforeach; ?>

                    <!-- EXPLANATION NOTE -->
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="applicant_note" style="font-weight: 700; color: #1e293b;">
                            Applicant Correction Note / Explanation for Reviewing Officer
                        </label>
                        <textarea id="applicant_note" name="applicant_note" rows="3" placeholder="Explain what changes were made (e.g. 'Uploaded a high-resolution color scan of Aadhaar card showing full date of birth')."></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <a href="track.php?app_id=<?= urlencode($application['application_id']) ?>" class="btn secondary">Cancel</a>
                        <button type="submit" class="btn primary" style="background: #16a34a; padding: 12px 28px;">
                            ✓ Submit Corrected Documents for Re-review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
</body>
</html>
