<?php
// admin/update_status.php - Update Application Status, Officer Remarks & Certificate Upload
require_once __DIR__ . '/../config/database.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: applications.php");
    exit;
}

$appIdentifier = sanitize($_POST['application_id'] ?? '');
$status = sanitize($_POST['status'] ?? '');
$remarks = sanitize($_POST['remarks'] ?? '');
$paymentStatus = sanitize($_POST['payment_status'] ?? '');
$documentStatus = sanitize($_POST['document_status'] ?? '');
$docRejectReason = sanitize($_POST['document_rejection_reason'] ?? '');
$rejectedDocIds = $_POST['rejected_doc_ids'] ?? [];

$allowedStatuses = ['submitted', 'under_review', 'approved', 'rejected', 'completed'];
$allowedPayment = ['pending', 'paid', 'verified', 'rejected'];
$allowedDocStatus = ['pending', 'verified', 'rejected'];

// If document is rejected, force overall status to rejected so citizen gets prompted to re-upload
if ($documentStatus === 'rejected') {
    $status = 'rejected';
    if (!empty($docRejectReason) && strpos($remarks, $docRejectReason) === false) {
        $prefix = "⚠️ Document Verification Failed: " . $docRejectReason . ". Please upload valid/authentic documents again.";
        $remarks = !empty($remarks) ? ($prefix . " | " . $remarks) : $prefix;
    }
}

if (empty($appIdentifier) || !in_array($status, $allowedStatuses)) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    header("Location: applications.php?error=" . urlencode("Invalid status or application reference."));
    exit;
}

// Check for admin-uploaded document/certificate
$issuedDocPath = null;
if (isset($_FILES['issued_document']) && $_FILES['issued_document']['error'] === UPLOAD_ERR_OK) {
    $fileTmp = $_FILES['issued_document']['tmp_name'];
    $origName = $_FILES['issued_document']['name'];
    $fileSize = $_FILES['issued_document']['size'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
    if (in_array($ext, $allowedExts) && $fileSize <= 10 * 1024 * 1024) {
        $issuedDir = __DIR__ . '/../uploads/issued/';
        if (!is_dir($issuedDir)) {
            @mkdir($issuedDir, 0777, true);
        }
        @chmod($issuedDir, 0777);
        $safeName = 'cert_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (@move_uploaded_file($fileTmp, $issuedDir . $safeName)) {
            @chmod($issuedDir . $safeName, 0666);
            $issuedDocPath = 'uploads/issued/' . $safeName;
        }
    }
}

try {
    $pdo->beginTransaction();

    // Prepare update parameters
    $queryParts = ["status = ?", "remarks = ?", "updated_at = NOW()"];
    $bindings = [$status, $remarks];

    if (!empty($paymentStatus) && in_array($paymentStatus, $allowedPayment)) {
        $queryParts[] = "payment_status = ?";
        $bindings[] = $paymentStatus;

        $utrStatus = ($paymentStatus === 'verified') ? 'verified' : (($paymentStatus === 'rejected') ? 'rejected' : (($paymentStatus === 'paid') ? 'verified' : 'pending'));
        $verifiedAt = in_array($utrStatus, ['verified', 'rejected']) ? date('Y-m-d H:i:s') : null;
        $verifiedBy = in_array($utrStatus, ['verified', 'rejected']) ? ($_SESSION['user_id'] ?? null) : null;

        // Also update payments table with UTR verification info
        $payUpdate = $pdo->prepare("
            UPDATE payments p
            JOIN applications a ON p.application_id = a.id
            SET p.payment_status = ?, p.utr_status = ?, p.verified_at = ?, p.verified_by = ?
            WHERE a.id = ? OR a.application_id = ?
        ");
        $payUpdate->execute([$paymentStatus, $utrStatus, $verifiedAt, $verifiedBy, $appIdentifier, $appIdentifier]);
    }

    // Document status handling
    if (!empty($documentStatus) && in_array($documentStatus, $allowedDocStatus)) {
        $queryParts[] = "document_status = ?";
        $bindings[] = $documentStatus;

        // Fetch application ID primary key
        $getAppId = $pdo->prepare("SELECT id FROM applications WHERE id = ? OR application_id = ? LIMIT 1");
        $getAppId->execute([$appIdentifier, $appIdentifier]);
        $appRow = $getAppId->fetch();

        if ($appRow) {
            $realAppId = $appRow['id'];
            if ($documentStatus === 'rejected') {
                $rejReasonText = !empty($docRejectReason) ? $docRejectReason : "Document rejected as false or invalid";
                if (!empty($rejectedDocIds) && is_array($rejectedDocIds)) {
                    $cleanDocIds = array_map('intval', $rejectedDocIds);
                    $inPlaceholders = implode(',', array_fill(0, count($cleanDocIds), '?'));
                    $docUpd = $pdo->prepare("UPDATE application_documents SET status = 'rejected', rejection_reason = ? WHERE application_id = ? AND id IN ($inPlaceholders)");
                    $docUpd->execute(array_merge([$rejReasonText, $realAppId], $cleanDocIds));
                } else {
                    $docUpd = $pdo->prepare("UPDATE application_documents SET status = 'rejected', rejection_reason = ? WHERE application_id = ?");
                    $docUpd->execute([$rejReasonText, $realAppId]);
                }
            } elseif ($documentStatus === 'verified') {
                $docUpd = $pdo->prepare("UPDATE application_documents SET status = 'verified', rejection_reason = NULL WHERE application_id = ?");
                $docUpd->execute([$realAppId]);
            } elseif ($documentStatus === 'pending') {
                $docUpd = $pdo->prepare("UPDATE application_documents SET status = 'pending' WHERE application_id = ?");
                $docUpd->execute([$realAppId]);
            }
        }
    }

    // If admin checked delete_user_docs, purge physical uploaded files and DB records for this application
    if (isset($_POST['delete_user_docs']) && ($_POST['delete_user_docs'] == '1' || $_POST['delete_user_docs'] == 'yes')) {
        $getAppId = $pdo->prepare("SELECT id FROM applications WHERE id = ? OR application_id = ? LIMIT 1");
        $getAppId->execute([$appIdentifier, $appIdentifier]);
        $appRow = $getAppId->fetch();
        if ($appRow) {
            $rId = (int)$appRow['id'];
            $docStmt = $pdo->prepare("SELECT file_path FROM application_documents WHERE application_id = ?");
            $docStmt->execute([$rId]);
            $files = $docStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($files as $f) {
                if (!empty($f)) {
                    $fullP = __DIR__ . '/../' . ltrim($f, '/\\');
                    if (file_exists($fullP) && is_file($fullP)) {
                        @unlink($fullP);
                    }
                }
            }
            $pdo->prepare("DELETE FROM application_documents WHERE application_id = ?")->execute([$rId]);
            if (strpos($remarks, '[Uploaded Citizen Docs Cleaned') === false) {
                $remarks = !empty($remarks) ? ($remarks . " | [Uploaded Citizen Docs Cleaned to Free Server Storage]") : "[Uploaded Citizen Docs Cleaned to Free Server Storage]";
            }
        }
    }

    if ($issuedDocPath !== null) {
        $queryParts[] = "issued_document = ?";
        $bindings[] = $issuedDocPath;
    }

    $bindings[] = $appIdentifier;
    $bindings[] = $appIdentifier;

    $sql = "UPDATE applications SET " . implode(", ", $queryParts) . " WHERE id = ? OR application_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);

    $pdo->commit();

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'issued_document' => $issuedDocPath]);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? 'applications.php';
    $separator = (strpos($referer, '?') !== false) ? '&' : '?';
    header("Location: " . $referer . $separator . "updated=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    header("Location: applications.php?error=" . urlencode("Update error: " . $e->getMessage()));
    exit;
}
?>
