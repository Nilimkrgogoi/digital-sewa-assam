<?php
// apply.php - Apply for Digital Services with Real-time Dynamic QR Fee Payment
require_once __DIR__ . '/config/database.php';
require_login('apply.php');

$currentUser = get_current_user_data($pdo);

// Fetch all active services
$services = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY service_name ASC")->fetchAll();

$preselectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// If no service ID provided in URL, default to the first active service so fee is never zero
if ($preselectedServiceId <= 0 && !empty($services)) {
    $preselectedServiceId = (int)$services[0]['id'];
}

$preselectedFee = 0.00;
$preselectedServiceName = '';
$serviceDocsMap = [];

foreach ($services as $s) {
    if ($s['id'] == $preselectedServiceId) {
        $preselectedFee = (float)$s['fee'];
        $preselectedServiceName = $s['service_name'];
    }
    $serviceDocsMap[$s['id']] = [
        'name' => $s['service_name'],
        'fee'  => (float)$s['fee'],
        'docs' => get_service_required_documents($s['service_name'], $s['id'])
    ];
}

$initialDocs = !empty($preselectedServiceName) 
    ? get_service_required_documents($preselectedServiceName, $preselectedServiceId) 
    : get_service_required_documents('General');

$portalSettings = get_portal_settings();
$adminUpi = $portalSettings['upi_id'] ?? 'ju152@cnrb';
$bankName = $portalSettings['bank_name'] ?? 'State Bank of India';
$bankHolder = $portalSettings['bank_account_holder'] ?? 'Digital Sewa Assam Admin';
$bankAccNo = $portalSettings['bank_account_no'] ?? '389201948201';
$bankIfsc = $portalSettings['bank_ifsc'] ?? 'SBIN0000123';

$payeeName = ($adminUpi === 'ju152@cnrb') ? 'NIKUMONI BORAH' : ($bankHolder ?: 'Digital Sewa Assam Admin');
$cleanNote = preg_replace('/[^a-zA-Z0-9 ]/', '', $preselectedServiceName . ' Fee');
if (empty($cleanNote)) {
    $cleanNote = 'Service Processing Fee';
}
$formattedPreselectedFee = number_format($preselectedFee, 2, '.', '');
$initialUpiUri = "upi://pay?pa=" . $adminUpi . "&pn=" . urlencode($payeeName) . "&am=" . $formattedPreselectedFee . "&cu=INR&tn=" . urlencode($cleanNote);
$initialQrSrc = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=" . urlencode($initialUpiUri);

$assamDistricts = [
    "Baksa", "Barpeta", "Biswanath", "Bongaigaon", "Cachar", "Charaideo", 
    "Chirang", "Darrang", "Dhemaji", "Dhubri", "Dibrugarh", "Dima Hasao", 
    "Goalpara", "Golaghat", "Hailakandi", "Hojai", "Jorhat", "Kamrup Metropolitan", 
    "Kamrup Rural", "Karbi Anglong", "Karimganj", "Kokrajhar", "Lakhimpur", 
    "Majuli", "Morigaon", "Nagaon", "Nalbari", "Sivasagar", "Sonitpur", 
    "South Salmara-Mankachar", "Tamulpur", "Tinsukia", "Udalguri", "West Karbi Anglong"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Citizen Service - Digital Sewa Assam</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display: flex; flex-direction: column; min-height: 100vh;">

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>
        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">☰</button>
        <nav id="navbar">
            <a href="index.php">Home</a>
            <a href="dashboard.php">My Dashboard</a>
            <a href="apply.php" style="color: var(--primary); font-weight: 700;">Apply Online</a>
            <a href="track.php">Track Status</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </div>
</header>

<div class="section" style="padding: 40px 0; flex: 1;">
    <div class="container">
        <div style="max-width: 860px; margin: auto;">
            <div style="margin-bottom: 24px;">
                <a href="dashboard.php" style="color: #0284c7; font-size: 13px; font-weight: 600; text-decoration: none;">← Back to Dashboard</a>
                <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin-top: 6px;">
                    Online Service Application Form
                </h1>
                <p style="color: #64748b; font-size: 14px;">
                    Select your digital service, enter citizen details, upload supporting documents, and complete the instant UPI fee payment to submit.
                </p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    ⚠️ <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <form action="submit_application.php" method="POST" enctype="multipart/form-data" id="applicationForm" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 34px; box-shadow: 0 12px 35px rgba(0,0,0,0.06);">
                
                <!-- SERVICE SELECTION BANNER -->
                <div style="background: #f0f9ff; padding: 22px; border-radius: 14px; margin-bottom: 26px; border: 1.5px solid #bae6fd;">
                    <div class="form-row" style="align-items: center; display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 280px;">
                            <label for="serviceSelect" style="color: #0284c7; font-weight: 800; font-size: 14px; margin-bottom: 8px; display: block;">
                                ▦ Select Digital Service *
                            </label>
                            <select name="service_id" id="serviceSelect" required onchange="onServiceSelectionChanged()" style="font-weight: 700; font-size: 15px; border-color: #93c5fd; background: #ffffff; color: #0f172a; padding: 12px; width: 100%; border-radius: 8px;">
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?= $svc['id'] ?>" data-fee="<?= $svc['fee'] ?>" <?= ($svc['id'] == $preselectedServiceId) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($svc['service_name']) ?> (Fee: <?= format_currency($svc['fee']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="text-align: right; background: #ffffff; padding: 10px 18px; border-radius: 10px; border: 1px solid #bae6fd;">
                            <span style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; display: block; letter-spacing: 0.5px;">Applicable Govt/Portal Fee:</span>
                            <span id="serviceFeeDisplay" style="font-size: 28px; font-weight: 900; color: #0284c7; line-height: 1.2;">
                                <?= format_currency($preselectedFee) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- APPLICANT DETAILS -->
                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    1. Applicant Information
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" placeholder="Enter full name">
                    </div>
                    <div class="form-group">
                        <label for="mobile">Mobile Number *</label>
                        <input type="tel" id="mobile" name="mobile" maxlength="10" required value="<?= htmlspecialchars($currentUser['mobile'] ?? '') ?>" placeholder="10-digit mobile number">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" placeholder="name@example.com">
                    </div>
                    <div class="form-group">
                        <label for="district">District in Assam *</label>
                        <select id="district" name="district" required>
                            <option value="">-- Select District --</option>
                            <?php foreach ($assamDistricts as $dist): ?>
                                <option value="<?= htmlspecialchars($dist) ?>"><?= htmlspecialchars($dist) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Full Residential Address *</label>
                    <textarea id="address" name="address" rows="3" required placeholder="House No, Village/Town, Post Office, PIN Code..."></textarea>
                </div>

                <div class="form-group">
                    <label for="remarks">Specific Details / Requirement Notes</label>
                    <textarea id="remarks" name="remarks" rows="2" placeholder="Mention any extra details (e.g. Existing PAN No, Father's Name, Aadhaar Linked, Purpose of Certificate, etc.)"></textarea>
                </div>

                <!-- DOCUMENT UPLOADS -->
                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin: 28px 0 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;">
                        2. Upload Supporting Documents
                    </h3>
                    <span id="serviceDocNotice" style="font-size: 13px; color: #0284c7; background: #e0f2fe; border: 1px solid #bae6fd; padding: 4px 10px; border-radius: 6px; font-weight: 700;">
                        <?= !empty($preselectedServiceName) ? 'Requirements for: ' . htmlspecialchars($preselectedServiceName) : 'Standard Document Checklist' ?>
                    </span>
                </div>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">
                    Upload clear scans or photos (Formats: <b>PDF, JPG, PNG</b> • Max size: <b>5 MB</b> per file). Red asterisk (<span style="color:#ef4444; font-weight:bold;">*</span>) indicates mandatory documents.
                </p>

                <div class="documents-grid" id="documentsContainer">
                    <?php foreach ($initialDocs as $docKey => $docInfo): ?>
                        <div class="doc-upload-item" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                            <label for="<?= htmlspecialchars($docKey) ?>" style="font-size: 13px; font-weight: 700; color: #1e293b; display: block; margin-bottom: 4px;">
                                Document <?= $docInfo['num'] ?>: <?= htmlspecialchars($docInfo['name']) ?>
                                <?= $docInfo['required'] ? '<span style="color: #ef4444; font-weight: bold;">*</span>' : '<span style="color: #64748b; font-size: 11px; font-weight: normal;">(Optional)</span>' ?>
                            </label>
                            <?php if (!empty($docInfo['desc'])): ?>
                                <div style="font-size: 11px; color: #64748b; margin-bottom: 6px; line-height: 1.3;"><?= htmlspecialchars($docInfo['desc']) ?></div>
                            <?php endif; ?>
                            <input type="file" id="<?= htmlspecialchars($docKey) ?>" name="<?= htmlspecialchars($docKey) ?>" <?= $docInfo['required'] ? 'required' : '' ?> accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileInput(this)">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- MANDATORY FEE PAYMENT SECTION (DYNAMIC QR WITH EXACT SERVICE FEE) -->
                <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin: 28px 0 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;">
                        3. Mandatory Service Fee Payment
                    </h3>
                    <span class="badge" style="background: #fee2e2; color: #991b1b; border-color: #fecaca; font-weight: 800;">
                        Payment Required to Submit
                    </span>
                </div>

                <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 14px; padding: 22px; margin-bottom: 24px; box-shadow: 0 8px 25px rgba(245, 158, 11, 0.12);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(245, 158, 11, 0.4); padding-bottom: 14px; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #92400e; letter-spacing: 0.5px;">Service Processing Fee</span>
                            <h4 id="payBoxServiceName" style="margin: 4px 0 0; color: #78350f; font-size: 18px; font-weight: 800;">
                                <?= !empty($preselectedServiceName) ? htmlspecialchars($preselectedServiceName) : 'Selected Service' ?>
                            </h4>
                        </div>
                        <div style="text-align: right; background: #ffffff; padding: 8px 16px; border-radius: 10px; border: 1.5px solid #f59e0b;">
                            <span style="font-size: 10px; color: #92400e; font-weight: 800; display: block; text-transform: uppercase;">Total Amount Payable:</span>
                            <span id="payBoxFeeDisplay" style="font-size: 26px; font-weight: 900; color: #b45309; line-height: 1;">
                                <?= format_currency($preselectedFee) ?>
                            </span>
                            <small style="display: block; font-size: 10px; color: #166534; font-weight: 700; margin-top: 2px;">✓ Auto-Loaded in QR</small>
                        </div>
                    </div>

                    <p style="font-size: 13px; color: #78350f; margin-bottom: 16px; line-height: 1.5;">
                        Scan the <b>Dynamic Official QR Code</b> below with <b>Google Pay, PhonePe, Paytm, or BHIM</b>. The exact fee amount of <b id="payBoxFeeInlineText"><?= format_currency($preselectedFee) ?></b> is pre-loaded into the QR automatically. After completing payment, enter your <b>12-digit UPI UTR Transaction Reference Number</b> below.
                    </p>

                    <!-- DYNAMIC QR CODE AND BANK DETAILS -->
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 18px; align-items: center; background: #ffffff; padding: 18px; border-radius: 12px; border: 1px solid #fde68a; margin-bottom: 16px;">
                        <div style="text-align: center;">
                            <div style="display: inline-block; background: white; padding: 10px; border-radius: 10px; border: 2px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                                <img id="servicePaymentQr" src="<?= $initialQrSrc ?>" alt="Service Payment QR Code" style="width: 175px; height: 175px; object-fit: contain; display: block; background: white;">
                            </div>
                            <div style="margin-top: 8px;">
                                <span id="qrFeeTag" style="background: #dcfce7; color: #166534; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 5px; display: inline-block;">
                                    ✓ Pre-filled: <?= format_currency($preselectedFee) ?>
                                </span>
                            </div>
                            <small id="serviceQrSubtext" style="font-size: 11px; color: #64748b; display: block; margin-top: 4px;">
                                Scan with GPay / PhonePe / Paytm
                            </small>
                        </div>

                        <div style="font-size: 13px; color: #1e293b; line-height: 1.7;">
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px;">
                                <b>Portal UPI ID:</b> 
                                <code id="adminUpiText" style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; color: #0284c7; font-weight: 800; font-size: 13px; border: 1px solid #cbd5e1;"><?= htmlspecialchars($adminUpi) ?></code>
                                <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($adminUpi) ?>'); alert('UPI ID copied: <?= htmlspecialchars($adminUpi) ?>');" class="btn secondary sm" style="padding: 2px 8px; font-size: 11px; background: white;">
                                    📋 Copy
                                </button>
                            </div>
                            <div><b>Account Holder:</b> <?= htmlspecialchars($bankHolder) ?></div>
                            <div><b>Bank:</b> <?= htmlspecialchars($bankName) ?></div>
                            <?php if (!empty($bankAccNo)): ?>
                                <div><b>A/C No:</b> <?= htmlspecialchars($bankAccNo) ?> &nbsp;|&nbsp; <b>IFSC:</b> <?= htmlspecialchars($bankIfsc) ?></div>
                            <?php endif; ?>

                            <!-- Mobile Direct Pay Button -->
                            <div style="margin-top: 10px;">
                                <a id="serviceMobilePayLink" href="<?= htmlspecialchars($initialUpiUri) ?>" class="btn" style="background: #0284c7; color: white; padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                                    📱 Tap to Pay <?= format_currency($preselectedFee) ?> via UPI App
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="payment_txn_id" style="font-weight: 800; font-size: 13px; color: #78350f;">
                            12-Digit UPI Transaction ID / UTR Reference Number * (Fixed 12 Digits)
                        </label>
                        <input type="text" id="payment_txn_id" name="payment_txn_id" required 
                            minlength="12" maxlength="12" pattern="[0-9]{12}" inputmode="numeric" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);" 
                            placeholder="e.g. 412345678901 (Fixed 12 Digits)" 
                            style="background: #ffffff; border: 2px solid #f59e0b; color: #0f172a; font-family: monospace; font-size: 15px; font-weight: 700; width: 100%; letter-spacing: 1px;">
                        <small style="font-size: 11px; color: #92400e; margin-top: 4px; display: block; font-weight: 600;">
                            ⚠️ Required: Enter your valid 12-digit UTR transaction reference number (fixed 12 numeric digits, not allowed more or less than 12 digits).
                        </small>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <a href="dashboard.php" class="btn secondary">Cancel</a>
                    <button type="submit" class="btn primary" style="padding: 14px 32px; font-size: 15px; font-weight: 700;">
                        ✓ Confirm Payment & Submit Application →
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= portal_footer_html() ?>

<script src="assets/js/script.js"></script>
<script>
const serviceDocsMap = <?= json_encode($serviceDocsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const defaultDocs = <?= json_encode(get_service_required_documents('General'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const adminUpi = <?= json_encode($adminUpi) ?>;
const payeeName = (adminUpi === 'ju152@cnrb') ? 'NIKUMONI BORAH' : <?= json_encode($bankHolder ?: 'Digital Sewa Assam Admin') ?>;

function onServiceSelectionChanged() {
    const select = document.getElementById("serviceSelect");
    if (!select) return;

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const feeAttr = selectedOption.getAttribute("data-fee");
    const rawTitle = selectedOption.text.split(" (Fee:")[0].trim();
    const feeNum = (feeAttr !== null && feeAttr !== "") ? parseFloat(feeAttr) : 0;
    const formattedFee = "₹" + feeNum.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Update Top Fee Display
    const feeDisplay = document.getElementById("serviceFeeDisplay");
    if (feeDisplay) feeDisplay.innerText = formattedFee;

    // Update Pay Box Fee Display
    const payBoxFee = document.getElementById("payBoxFeeDisplay");
    if (payBoxFee) payBoxFee.innerText = formattedFee;

    // Update Pay Box Title & Inline Text
    const payBoxTitle = document.getElementById("payBoxServiceName");
    if (payBoxTitle && rawTitle) payBoxTitle.innerText = rawTitle;

    const payBoxInline = document.getElementById("payBoxFeeInlineText");
    if (payBoxInline) payBoxInline.innerText = formattedFee;

    // Re-generate Dynamic UPI QR Code with exact service fee
    const cleanNote = (rawTitle + ' Fee').replace(/[^a-zA-Z0-9 ]/g, '').trim().substring(0, 30);
    const upiUri = `upi://pay?pa=${adminUpi}&pn=${encodeURIComponent(payeeName)}&am=${feeNum.toFixed(2)}&cu=INR&tn=${encodeURIComponent(cleanNote)}`;
    const dynamicQrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=15&data=${encodeURIComponent(upiUri)}`;

    const qrImg = document.getElementById("servicePaymentQr");
    if (qrImg) {
        qrImg.src = dynamicQrUrl;
    }

    const qrFeeTag = document.getElementById("qrFeeTag");
    if (qrFeeTag) qrFeeTag.innerText = "✓ Pre-filled: " + formattedFee;

    const mobileLink = document.getElementById("serviceMobilePayLink");
    if (mobileLink) {
        mobileLink.href = upiUri;
        mobileLink.innerText = `📱 Tap to Pay ${formattedFee} via UPI App`;
    }

    updateRequiredDocuments();
}

function updateRequiredDocuments() {
    const select = document.getElementById("serviceSelect");
    const container = document.getElementById("documentsContainer");
    const badgeNotice = document.getElementById("serviceDocNotice");
    if (!select || !container) return;

    const serviceId = select.value;
    let docs = defaultDocs;
    let serviceName = "Standard Document Checklist";

    if (serviceId && serviceDocsMap[serviceId]) {
        docs = serviceDocsMap[serviceId].docs;
        serviceName = "Requirements for: " + serviceDocsMap[serviceId].name;
    }

    if (badgeNotice) {
        badgeNotice.innerText = serviceName;
    }

    let html = '';
    for (const [key, item] of Object.entries(docs)) {
        const requiredBadge = item.required 
            ? '<span style="color: #ef4444; font-weight: bold;">*</span>' 
            : '<span style="color: #64748b; font-size: 11px; font-weight: normal;">(Optional)</span>';
        const reqAttr = item.required ? 'required' : '';
        const descHtml = item.desc 
            ? `<div style="font-size: 11px; color: #94a3b8; margin-bottom: 6px; line-height: 1.3;">${escapeHtml(item.desc)}</div>` 
            : '';

        html += `
            <div class="doc-upload-item" style="background: #0b0f19; border: 1px solid #232f48; border-radius: 8px; padding: 12px;">
                <label for="${escapeHtml(key)}" style="font-size: 13px; font-weight: 700; color: #f1f5f9; display: block; margin-bottom: 4px;">
                    Document ${item.num}: ${escapeHtml(item.name)} ${requiredBadge}
                </label>
                ${descHtml}
                <input type="file" id="${escapeHtml(key)}" name="${escapeHtml(key)}" ${reqAttr} accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileInput(this)">
            </div>
        `;
    }

    container.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
</body>
</html>
