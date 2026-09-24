<?php
// admin/export_users.php - Export Citizens & User Accounts to Excel / CSV
require_once __DIR__ . '/../config/database.php';
require_admin();

$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$format = strtolower(sanitize($_GET['format'] ?? 'csv'));

$where = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($statusFilter) && in_array($statusFilter, ['active', 'blocked'])) {
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

$filename = 'digital_sewa_assam_users_' . date('Ymd_His');

if ($format === 'xls') {
    // Microsoft Excel HTML Spreadsheet format
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style>
            table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
            th { background-color: #1e3a8a; color: #ffffff; padding: 10px; border: 1px solid #cbd5e1; text-align: left; font-weight: bold; }
            td { padding: 8px 10px; border: 1px solid #e2e8f0; }
            .num { mso-number-format: "\@"; }
            .badge-active { color: #15803d; font-weight: bold; }
            .badge-blocked { color: #b91c1c; font-weight: bold; }
        </style>
    </head>
    <body>
        <h2>Digital Sewa Assam - Registered Citizens & Accounts Export</h2>
        <p>Export Date: <?= date('d M Y, h:i A') ?> | Total Records: <?= count($users) ?></p>
        <table border="1">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Mobile Number</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Password (Recovery)</th>
                    <th>Platform Fee Status</th>
                    <th>Platform Fee UTR / Txn</th>
                    <th>Account Status</th>
                    <th>Total Applications</th>
                    <th>Registered Date</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td class="num">+91 <?= htmlspecialchars($u['mobile']) ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
                        <td><?= ($u['role'] === 'admin') ? 'Administrator' : 'Citizen' ?></td>
                        <td class="num"><?= htmlspecialchars($u['raw_password'] ?? 'N/A') ?></td>
                        <td><?= ($u['role'] === 'admin') ? 'Exempt' : ucfirst($u['platform_fee_status'] ?? ($u['platform_fee_paid'] ? 'Verified' : 'Pending')) ?></td>
                        <td class="num"><?= htmlspecialchars($u['platform_fee_txn'] ?? 'N/A') ?></td>
                        <td class="<?= ($u['status'] === 'active') ? 'badge-active' : 'badge-blocked' ?>">
                            <?= ($u['status'] === 'active') ? 'Active' : 'Suspended' ?>
                        </td>
                        <td><?= (int)$u['app_count'] ?></td>
                        <td><?= date('d M Y, h:i A', strtotime($u['created_at'])) ?></td>
                        <td><?= !empty($u['updated_at']) ? date('d M Y, h:i A', strtotime($u['updated_at'])) : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
} else {
    // Microsoft Excel Native CSV with UTF-8 BOM
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $output = fopen('php://output', 'w');

    // Output UTF-8 BOM for Microsoft Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Header Row
    fputcsv($output, [
        'User ID',
        'Full Name',
        'Mobile Number',
        'Email Address',
        'Role',
        'Password (Recovery)',
        'Platform Fee Status',
        'Platform Fee UTR / Txn',
        'Account Status',
        'Total Applications',
        'Registered Date',
        'Last Updated'
    ]);

    // Data Rows
    foreach ($users as $u) {
        fputcsv($output, [
            '#' . $u['id'],
            $u['name'],
            '+91 ' . $u['mobile'],
            $u['email'] ?? 'N/A',
            ($u['role'] === 'admin') ? 'Administrator' : 'Citizen',
            $u['raw_password'] ?? 'N/A',
            ($u['role'] === 'admin') ? 'Exempt' : ucfirst($u['platform_fee_status'] ?? ($u['platform_fee_paid'] ? 'Verified' : 'Pending')),
            $u['platform_fee_txn'] ?? 'N/A',
            ($u['status'] === 'active') ? 'Active' : 'Suspended',
            (int)$u['app_count'],
            date('d M Y, h:i A', strtotime($u['created_at'])),
            !empty($u['updated_at']) ? date('d M Y, h:i A', strtotime($u['updated_at'])) : 'N/A'
        ]);
    }

    fclose($output);
    exit;
}
