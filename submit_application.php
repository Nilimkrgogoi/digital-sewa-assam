<?php
// submit_application.php - Process Service Application Submission
require_once __DIR__ . '/config/database.php';
require_login('apply.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: apply.php");
    exit;
}

$userId = $_SESSION['user_id'];
$serviceId = (int)($_POST['service_id'] ?? 0);
$fullName = sanitize($_POST['full_name'] ?? '');
$mobile = sanitize($_POST['mobile'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$district = sanitize($_POST['district'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$remarks = sanitize($_POST['remarks'] ?? '');
$paymentTxnId = sanitize($_POST['payment_txn_id'] ?? '');

// Basic validation
if (!$serviceId || empty($fullName) || empty($mobile) || empty($district) || empty($address)) {
    header("Location: apply.php?error=" . urlencode("Please fill in all required fields."));
    exit;
}

// Fetch service details
$svcStmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND status = 'active' LIMIT 1");
$svcStmt->execute([$serviceId]);
$service = $svcStmt->fetch();

if (!$service) {
    header("Location: apply.php?error=" . urlencode("Invalid or inactive service selected."));
    exit;
}

// Mandatory Fee Payment Validation: Applicant MUST pay and provide a valid 12-digit numeric UTR
$cleanTxnId = preg_replace('/[^0-9]/', '', $paymentTxnId);
if ($service['fee'] > 0 && empty($paymentTxnId)) {
    header("Location: apply.php?service_id=" . $service['id'] . "&error=" . urlencode("Fee payment is strictly mandatory for " . $service['service_name'] . " (₹" . number_format($service['fee'], 2) . "). You must pay and enter your 12-digit UPI / Bank Transaction UTR number to submit this application."));
    exit;
}

if (!empty($paymentTxnId)) {
    if (strlen($cleanTxnId) !== 12 || strlen($paymentTxnId) !== 12) {
        header("Location: apply.php?service_id=" . $service['id'] . "&error=" . urlencode("Invalid UTR Reference Number! The UTR / Transaction ID must be fixed at exactly 12 numeric digits (e.g. 412345678901), not allowed more or less than 12 digits."));
        exit;
    }
    $paymentTxnId = $cleanTxnId;
}

// Prepare uploads folder
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}
@chmod($uploadDir, 0777);

if (!is_writable($uploadDir)) {
    header("Location: apply.php?error=" . urlencode("Server upload directory is not writable. Please contact server administrator."));
    exit;
}

// Allowed extensions
$allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
$uploadedDocs = [];

// Fetch service-specific document checklist (checking custom admin documents first)
$docFields = get_service_required_documents($service['service_name'], $service['id']);

foreach ($docFields as $field => $info) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES[$field]['tmp_name'];
        $originalName = $_FILES[$field]['name'];
        $fileSize = $_FILES[$field]['size'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            header("Location: apply.php?error=" . urlencode("Invalid file format for " . $info['name'] . ". Only PDF, JPG, and PNG allowed."));
            exit;
        }

        if ($fileSize > 5 * 1024 * 1024) {
            header("Location: apply.php?error=" . urlencode("File size exceeds 5MB limit for " . $info['name']));
            exit;
        }

        // Generate unique safe file name
        $safeName = 'doc_' . $info['num'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $safeName;

        if (@move_uploaded_file($fileTmp, $destination)) {
            @chmod($destination, 0666);
            $uploadedDocs[] = [
                'doc_num' => $info['num'],
                'doc_name' => $info['name'],
                'file_name' => $originalName,
                'file_path' => 'uploads/' . $safeName
            ];
        } else {
            header("Location: apply.php?error=" . urlencode("Failed to save uploaded file (" . $info['name'] . "). Server directory permission issue."));
            exit;
        }
    } elseif ($info['required']) {
        header("Location: apply.php?error=" . urlencode("Mandatory document (" . $info['name'] . ") is missing."));
        exit;
    }
}

// Generate Unique Application ID
$applicationId = generate_app_id();
$paymentStatus = ($service['fee'] > 0) ? 'paid' : 'paid';

try {
    $pdo->beginTransaction();

    // Insert application
    $appInsert = $pdo->prepare("
        INSERT INTO applications (
            application_id, user_id, service_id, full_name, mobile, email, district, address, status, remarks, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, ?)
    ");
    $appInsert->execute([
        $applicationId, $userId, $serviceId, $fullName, $mobile, $email, $district, $address, $remarks, $paymentStatus
    ]);
    $insertedAppId = $pdo->lastInsertId();

    // Insert document records
    $docInsert = $pdo->prepare("
        INSERT INTO application_documents (application_id, document_number, document_name, file_name, file_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($uploadedDocs as $doc) {
        $docInsert->execute([
            $insertedAppId,
            $doc['doc_num'],
            $doc['doc_name'],
            $doc['file_name'],
            $doc['file_path']
        ]);
    }

    // Insert payment record with citizen's verified UTR number (starts as pending admin verification)
    $payInsert = $pdo->prepare("
        INSERT INTO payments (application_id, amount, transaction_id, payment_method, payment_status, utr_status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $txnId = !empty($paymentTxnId) ? $paymentTxnId : ('TXN-DSA-' . strtoupper(bin2hex(random_bytes(4))));
    $payMethod = 'UPI_ONLINE';
    $payInsert->execute([
        $insertedAppId,
        $service['fee'],
        $txnId,
        $payMethod,
        $paymentStatus
    ]);

    $pdo->commit();

    // Redirect to tracking page with confirmation
    header("Location: track.php?app_id=" . urlencode($applicationId) . "&submitted=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: apply.php?error=" . urlencode("Application submission error: " . $e->getMessage()));
    exit;
}
?>
