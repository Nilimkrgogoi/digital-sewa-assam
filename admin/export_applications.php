<?php
// admin/export_applications.php - Export Applications Data in CSV / Excel (.XLS) Format with UTR Verification
require_once __DIR__ . '/../config/database.php';
require_admin();

$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$serviceFilter = (int)($_GET['service_id'] ?? 0);
$utrFilter = sanitize($_GET['utr_status'] ?? '');
$format = strtolower(sanitize($_GET['format'] ?? 'csv'));

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (a.application_id LIKE ? OR a.full_name LIKE ? OR a.mobile LIKE ? OR a.district LIKE ? OR s.service_name LIKE ? OR p.transaction_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($statusFilter) && in_array($statusFilter, ['submitted', 'under_review', 'approved', 'rejected', 'completed'])) {
    $where .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($serviceFilter > 0) {
    $where .= " AND a.service_id = ?";
    $params[] = $serviceFilter;
}

if (!empty($utrFilter) && in_array($utrFilter, ['pending', 'verified', 'rejected'])) {
    $where .= " AND p.utr_status = ?";
    $params[] = $utrFilter;
}

$sql = "
    SELECT 
        a.application_id,
        a.full_name,
        a.mobile,
        a.email,
        s.service_name,
        a.district,
        a.address,
        s.fee,
        a.payment_status,
        p.transaction_id as payment_utr,
        p.utr_status,
        p.verification_remarks,
        p.verified_at,
        a.status,
        a.remarks,
        a.created_at,
        a.updated_at
    FROM applications a
    JOIN services s ON a.service_id = s.id
    LEFT JOIN payments p ON p.application_id = a.id
    WHERE $where
    ORDER BY a.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

$timestamp = date('Ymd_His');

if ($format === 'xls') {
    // Generate native Microsoft Excel XML / HTML Spreadsheet
    $filename = "applications_export_" . $timestamp . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Applications</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
    echo '<body>';
    echo '<h3>Digital Sewa Assam - Official Applications & UTR Export</h3>';
    echo '<p>Export Date: ' . date('d M Y, h:i A') . ' | Total Records: ' . count($applications) . '</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px;">';
    echo '<tr style="background-color: #1e1b4b; color: #ffffff; font-weight: bold; text-align: left;">';
    echo '<th>SL</th>';
    echo '<th>Application ID</th>';
    echo '<th>Applicant Full Name</th>';
    echo '<th>Mobile Number</th>';
    echo '<th>Email</th>';
    echo '<th>Service Name</th>';
    echo '<th>Assam District</th>';
    echo '<th>Residential Address</th>';
    echo '<th>Govt / Portal Fee (INR)</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Payment UTR / Txn Ref</th>';
    echo '<th>UTR Verification Status</th>';
    echo '<th>UTR Verification Remarks</th>';
    echo '<th>UTR Verified At</th>';
    echo '<th>Processing Status</th>';
    echo '<th>Officer Remarks</th>';
    echo '<th>Submission Date</th>';
    echo '<th>Last Updated</th>';
    echo '</tr>';

    $i = 1;
    foreach ($applications as $app) {
        $statusBg = '#ffffff';
        if ($app['status'] === 'completed' || $app['status'] === 'approved') $statusBg = '#dcfce7';
        elseif ($app['status'] === 'rejected') $statusBg = '#fee2e2';
        elseif ($app['status'] === 'under_review') $statusBg = '#fef3c7';

        $utrBg = '#ffffff';
        $utrStatusText = strtoupper($app['utr_status'] ?? 'PENDING');
        if ($app['utr_status'] === 'verified') {
            $utrBg = '#dcfce7';
        } elseif ($app['utr_status'] === 'rejected') {
            $utrBg = '#fee2e2';
        } elseif ($app['utr_status'] === 'pending') {
            $utrBg = '#fef3c7';
        }

        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td><b>" . htmlspecialchars($app['application_id']) . "</b></td>";
        echo "<td>" . htmlspecialchars($app['full_name']) . "</td>";
        echo "<td>'" . htmlspecialchars($app['mobile']) . "</td>";
        echo "<td>" . htmlspecialchars($app['email'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($app['service_name']) . "</td>";
        echo "<td>" . htmlspecialchars($app['district'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($app['address'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format((float)$app['fee'], 2) . "</td>";
        echo "<td>" . ucfirst($app['payment_status']) . "</td>";
        echo "<td>" . htmlspecialchars($app['payment_utr'] ?? 'N/A') . "</td>";
        echo "<td style=\"background-color: {$utrBg}; font-weight: bold;\">" . $utrStatusText . "</td>";
        echo "<td>" . htmlspecialchars($app['verification_remarks'] ?? '') . "</td>";
        echo "<td>" . (!empty($app['verified_at']) ? date('Y-m-d H:i', strtotime($app['verified_at'])) : 'N/A') . "</td>";
        echo "<td style=\"background-color: {$statusBg}; font-weight: bold;\">" . strtoupper(str_replace('_', ' ', $app['status'])) . "</td>";
        echo "<td>" . htmlspecialchars($app['remarks'] ?? '') . "</td>";
        echo "<td>" . date('Y-m-d H:i', strtotime($app['created_at'])) . "</td>";
        echo "<td>" . date('Y-m-d H:i', strtotime($app['updated_at'])) . "</td>";
        echo "</tr>";
    }
    echo '</table>';
    echo '</body></html>';
    exit;
} else {
    // Default CSV format
    $filename = "applications_export_" . $timestamp . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'SL',
        'Application ID',
        'Applicant Name',
        'Mobile Number',
        'Email Address',
        'Service Name',
        'District',
        'Address',
        'Fee (INR)',
        'Payment Status',
        'Payment UTR / Txn Ref',
        'UTR Verification Status',
        'UTR Verification Remarks',
        'UTR Verified At',
        'Application Status',
        'Officer Remarks',
        'Submitted Date',
        'Last Updated'
    ]);

    $i = 1;
    foreach ($applications as $app) {
        fputcsv($output, [
            $i++,
            $app['application_id'],
            $app['full_name'],
            $app['mobile'],
            $app['email'] ?? '',
            $app['service_name'],
            $app['district'] ?? '',
            $app['address'] ?? '',
            number_format((float)$app['fee'], 2, '.', ''),
            ucfirst($app['payment_status']),
            $app['payment_utr'] ?? '',
            strtoupper($app['utr_status'] ?? 'PENDING'),
            $app['verification_remarks'] ?? '',
            !empty($app['verified_at']) ? date('Y-m-d H:i:s', strtotime($app['verified_at'])) : '',
            strtoupper(str_replace('_', ' ', $app['status'])),
            $app['remarks'] ?? '',
            date('Y-m-d H:i:s', strtotime($app['created_at'])),
            date('Y-m-d H:i:s', strtotime($app['updated_at']))
        ]);
    }

    fclose($output);
    exit;
}
