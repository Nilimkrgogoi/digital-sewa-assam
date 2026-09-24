<?php
// admin/download_doc.php - Secure Document & Bulk ZIP Downloader for Admins
require_once __DIR__ . '/../config/database.php';
require_admin();

$docId = (int)($_GET['doc_id'] ?? 0);
$appId = (int)($_GET['app_id'] ?? 0);
$isZip = isset($_GET['zip']) || ($appId > 0 && $docId === 0);

$baseDir = realpath(__DIR__ . '/../');

// -------------------------------------------------------------
// 1. SINGLE DOCUMENT DOWNLOAD BY DOC_ID
// -------------------------------------------------------------
if ($docId > 0) {
    $stmt = $pdo->prepare("
        SELECT d.*, a.application_id as app_code, a.full_name 
        FROM application_documents d 
        JOIN applications a ON d.application_id = a.id 
        WHERE d.id = ?
    ");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();

    if (!$doc) {
        die("Document not found.");
    }

    $relativePath = ltrim($doc['file_path'], '/\\');
    $filePath = realpath($baseDir . '/' . $relativePath);

    // Prevent directory traversal attacks
    if (!$filePath || strpos($filePath, $baseDir) !== 0 || !file_exists($filePath)) {
        die("Error: The requested file could not be located on the server.");
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $docLabel = !empty($doc['document_name']) ? $doc['document_name'] : ('Doc_' . $doc['document_number']);
    $cleanDocName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $docLabel);
    $cleanAppCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['app_code']);
    $downloadFilename = $cleanAppCode . '_' . $cleanDocName . '.' . $extension;

    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    ob_clean();
    flush();
    readfile($filePath);
    exit;
}

// -------------------------------------------------------------
// 2. BULK ZIP DOWNLOAD FOR ALL DOCUMENTS OF AN APPLICATION
// -------------------------------------------------------------
if ($appId > 0) {
    $appStmt = $pdo->prepare("SELECT application_id, full_name FROM applications WHERE id = ?");
    $appStmt->execute([$appId]);
    $app = $appStmt->fetch();

    if (!$app) {
        die("Application not found.");
    }

    $docsStmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ? ORDER BY document_number ASC");
    $docsStmt->execute([$appId]);
    $docs = $docsStmt->fetchAll();

    if (empty($docs)) {
        header("Location: applications.php?err=" . urlencode("No documents available for download for application " . $app['application_id']));
        exit;
    }

    $cleanAppCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $app['application_id']);

    // If only 1 document uploaded, redirect to single download
    if (count($docs) === 1 && !$isZip) {
        header("Location: download_doc.php?doc_id=" . $docs[0]['id']);
        exit;
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $tmpZipFile = tempnam(sys_get_temp_dir(), 'app_docs_') . '.zip';

        if ($zip->open($tmpZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $addedFilesCount = 0;
            foreach ($docs as $index => $doc) {
                $relativePath = ltrim($doc['file_path'], '/\\');
                $filePath = realpath($baseDir . '/' . $relativePath);

                if ($filePath && strpos($filePath, $baseDir) === 0 && file_exists($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $docLabel = !empty($doc['document_name']) ? $doc['document_name'] : ('Document_' . ($index + 1));
                    $cleanLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $docLabel);
                    $localName = sprintf("%02d_%s.%s", ($index + 1), $cleanLabel, $ext);

                    $zip->addFile($filePath, $localName);
                    $addedFilesCount++;
                }
            }
            $zip->close();

            if ($addedFilesCount > 0 && file_exists($tmpZipFile)) {
                $zipDownloadName = "Application_" . $cleanAppCode . "_Documents.zip";

                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipDownloadName . '"');
                header('Content-Length: ' . filesize($tmpZipFile));
                header('Pragma: public');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

                ob_clean();
                flush();
                readfile($tmpZipFile);
                @unlink($tmpZipFile);
                exit;
            }
        }
    }

    // Fallback if ZipArchive fails or no valid files could be zipped
    die("Unable to package documents into a zip archive.");
}

header("Location: applications.php");
exit;
