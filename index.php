<?php
// index.php - Digital Sewa Assam Official Citizen Portal
require_once __DIR__ . '/config/database.php';

$loggedIn = is_logged_in();
$currentUser = $loggedIn ? get_current_user_data($pdo) : null;

// Fetch all active services
$stmt = $pdo->query("SELECT * FROM services WHERE status = 'active' ORDER BY id ASC");
$services = $stmt->fetchAll();

$contactSuccess = '';
$contactError = '';
$whatsappDirectUrl = '';

// Handle Citizen Contact / Query Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_inquiry') {
    $cName = sanitize($_POST['name'] ?? '');
    $cMobile = sanitize($_POST['mobile'] ?? '');
    $cService = sanitize($_POST['service_name'] ?? 'General Inquiry');
    $cMessage = sanitize($_POST['message'] ?? '');

    if (empty($cName) || empty($cMobile) || empty($cMessage)) {
        $contactError = "Please fill in all fields (Name, Mobile, and Query Message).";
    } elseif (!preg_match('/^[6-9]\d{9}$/', $cMobile)) {
        $contactError = "Please enter a valid 10-digit Indian mobile number.";
    } else {
        $inqStmt = $pdo->prepare("INSERT INTO contact_inquiries (name, mobile, service_name, message, status) VALUES (?, ?, ?, ?, 'new')");
        $inqStmt->execute([$cName, $cMobile, $cService, $cMessage]);

        $waText = urlencode("Hello Digital Sewa Assam Admin, my name is {$cName} (+91 {$cMobile}). I have an inquiry regarding '{$cService}': {$cMessage}");
        $whatsappDirectUrl = "https://wa.me/919613167470?text=" . $waText;
        $contactSuccess = "Your inquiry has been submitted directly to the Administrator! You can also click below to chat on WhatsApp instantly.";
    }
}

// Icon mapping helper
function get_service_icon($name) {
    $n = strtolower($name);
    if (strpos($n, 'pan') !== false) return '🪪';
    if (strpos($n, 'voter') !== false) return '🗳️';
    if (strpos($n, 'licence') !== false || strpos($n, 'dl') !== false) return '🚗';
    if (strpos($n, 'income') !== false) return '📜';
    if (strpos($n, 'birth') !== false) return '👶';
    if (strpos($n, 'bank') !== false) return '🏦';
    if (strpos($n, 'caste') !== false) return '📄';
    if (strpos($n, 'mobile') !== false) return '📱';
    if (strpos($n, 'farmer') !== false) return '🌾';
    if (strpos($n, 'kisan') !== false) return '🌱';
    if (strpos($n, 'scholarship') !== false) return '🎓';
    if (strpos($n, 'college') !== false || strpos($n, 'admission') !== false) return '🏫';
    if (strpos($n, 'job') !== false) return '💼';
    if (strpos($n, 'surya') !== false || strpos($n, 'solar') !== false) return '☀️';
    return '✨';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Sewa Assam - Official Online Citizen Service Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ================= HEADER ================= -->
<header class="header">
    <div class="container nav">
        <?= logo_html('index.php') ?>

        <button class="menu-btn" onclick="toggleMenu()" aria-label="Toggle Navigation">☰</button>

        <nav id="navbar">
            <a href="index.php#home">Home</a>
            <a href="index.php#services">Services</a>
            <a href="apply.php">Apply Online</a>
            <a href="track.php">Track Status</a>
            <a href="index.php#how-it-works">How It Works</a>
            <a href="index.php#contact" style="color: #16a34a; font-weight: 700;">📞 Contact Admin</a>

            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="user-badge">
                    👤 <?= htmlspecialchars($currentUser['name'] ?? 'Dashboard') ?>
                </a>
                <?php if (is_admin()): ?>
                    <a href="admin/dashboard.php" class="admin-badge">Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- ================= HERO ================= -->
<section class="hero" id="home">
    <div class="container hero-content">
        <div class="hero-text">
            <div class="badge">
                ✓ Government & Digital Citizen Services
            </div>

            <h1>
                Digital Services
                <span>Simple. Fast. Reliable.</span>
            </h1>

            <p>
                Apply for PAN Card, Voter Card, Driving Licence, Certificates, 
                Farmer Registration, Student Services and more directly from home with transparent tracking.
            </p>

            <div class="hero-buttons">
                <?php if ($loggedIn): ?>
                    <a href="dashboard.php" class="btn primary">Go to My Dashboard →</a>
                    <a href="apply.php" class="btn secondary">Apply for a Service</a>
                <?php else: ?>
                    <a href="register.php" class="btn primary">Create Free Account</a>
                    <a href="#services" class="btn secondary">Explore All Services</a>
                <?php endif; ?>
            </div>

            <div class="trust">
                <span>🔒 100% Secure Portal</span>
                <span>⚡ Instant Application ID</span>
                <span>📱 Real-time Tracking</span>
            </div>
        </div>

        <div class="hero-card">
            <div class="card-header">
                <strong>Digital Sewa Assam Portal</strong>
                <span class="online">● Online & Accepting Applications</span>
            </div>

            <div class="dashboard-preview">
                <p style="font-size: 13px; color: #475569; margin-bottom: 8px;">Popular Quick Applications:</p>
                <div class="mini-services">
                    <div><img src="assets/images/services/pan.svg" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px;"> PAN Card</div>
                    <div><img src="assets/images/services/aadhaar.svg" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px;"> Aadhaar</div>
                    <div><img src="assets/images/services/voter.svg" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px;"> Voter ID</div>
                    <div><img src="assets/images/services/dl.svg" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px;"> Driving Licence</div>
                </div>

                <a href="apply.php" class="btn primary" style="width: 100%;">
                    Start Online Application →
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ================= QUICK TRACK SEARCH ================= -->
<section class="section" style="padding: 40px 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
    <div class="container">
        <div style="max-width: 680px; margin: auto; text-align: center; background: #ffffff; border: 1.5px solid #0284c7; border-radius: 18px; padding: 34px 28px; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.1);">
            <span class="badge" style="margin-bottom: 10px;">🔍 Instant Status Checker</span>
            <h3 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">
                Already Applied? Track Your Status Instantly
            </h3>
            <p style="font-size: 14px; color: #64748b; margin-bottom: 20px;">
                Enter your Application ID (e.g. DSA-2026-XXXXXX) to view current stage and officer remarks.
            </p>
            <form action="track.php" method="GET" style="display: flex; gap: 10px; max-width: 500px; margin: auto;">
                <input type="text" name="app_id" placeholder="Enter Application ID..." required style="padding: 12px 16px; border-radius: 9px; border: 1.5px solid #cbd5e1; background: #f8fafc; color: #0f172a;">
                <button type="submit" class="btn primary" style="white-space: nowrap; font-weight: 700;">Track Now →</button>
            </form>
        </div>
    </div>
</section>

<!-- ================= SERVICES ================= -->
<section class="section" id="services">
    <div class="container">
        <div class="section-title">
            <span>▦ Citizen Services Catalog</span>
            <h2>Available Digital Services</h2>
            <p>
                Select any service below to fill your application form and submit supporting documents.
            </p>
        </div>

        <div class="services-search-box">
            <input 
                type="text" 
                id="serviceSearch" 
                onkeyup="filterServices()" 
                placeholder="🔍 Search service (e.g., PAN, Voter, Licence, Certificate, Farmer)..." 
            >
        </div>

        <div class="services-grid" id="servicesGrid">
            <?php foreach ($services as $svc): ?>
                <div class="service-card">
                    <div class="service-icon"><?= get_service_logo_html($svc['service_name']) ?></div>
                    <h3><?= htmlspecialchars($svc['service_name']) ?></h3>
                    <p><?= htmlspecialchars($svc['description'] ?? 'Assistance and application processing.') ?></p>
                    <div class="service-footer">
                        <span class="service-fee"><?= format_currency($svc['fee']) ?></span>
                        <a href="apply.php?service_id=<?= $svc['id'] ?>" class="apply-link">
                            Apply Now →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="section grey" id="how-it-works">
    <div class="container">
        <div class="section-title">
            <span>Simple Process</span>
            <h2>Apply in 4 Easy Steps</h2>
            <p>Our streamlined digital workflow helps citizens submit applications with full transparency.</p>
        </div>

        <div class="steps">
            <div class="step">
                <strong>01</strong>
                <h3>Create Account</h3>
                <p>Register with your mobile number in less than a minute.</p>
            </div>

            <div class="step">
                <strong>02</strong>
                <h3>Choose Service</h3>
                <p>Select the digital or government service you require.</p>
            </div>

            <div class="step">
                <strong>03</strong>
                <h3>Upload Docs</h3>
                <p>Submit required identity and address proof documents.</p>
            </div>

            <div class="step">
                <strong>04</strong>
                <h3>Track & Receive</h3>
                <p>Monitor progress in real-time until approved and generated.</p>
            </div>
        </div>
    </div>
</section>

<!-- ================= PACKAGES / OPERATOR PLANS ================= -->
<section class="section" id="packages">
    <div class="container">
        <div class="section-title">
            <span>Operator Plans</span>
            <h2>Digital Service Partner Membership</h2>
            <p>Flexible options for CSC operators, cyber cafes, and digital service kiosks in Assam.</p>
        </div>

        <div class="plans">
            <div class="plan featured">
                <span class="popular">POPULAR</span>
                <h3>Retailer Partner</h3>
                <div class="price">₹199</div>
                <p>For cyber cafes, local CSC operators & service points.</p>
                <ul>
                    <li>✓ Multi-application management</li>
                    <li>✓ Priority application review</li>
                    <li>✓ Dedicated digital service dashboard</li>
                    <li>✓ Bulk tracking for customer queries</li>
                </ul>
                <a href="register.php" class="btn primary" style="width: 100%;">Join as Partner</a>
            </div>
        </div>
    </div>
</section>

<!-- ================= CONTACT ADMINISTRATION & CITIZEN HELPDESK ================= -->
<section class="section grey" id="contact" style="padding: 70px 0;">
    <div class="container">
        <div class="section-title">
            <span class="badge" style="margin-bottom: 12px;">💬 24/7 Citizen Helpdesk & Support</span>
            <h2>Connect with Portal Administration</h2>
            <p>
                Have questions about document requirements, rejection clearance, or need personal assistance? Contact the administrator directly through Call, WhatsApp, or the inquiry form below.
            </p>
        </div>

        <?php if (!empty($contactSuccess)): ?>
            <div class="alert alert-success" style="max-width: 800px; margin: 0 auto 30px; padding: 18px 24px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div>
                    <b>🎉 <?= htmlspecialchars($contactSuccess) ?></b>
                </div>
                <?php if (!empty($whatsappDirectUrl)): ?>
                    <a href="<?= htmlspecialchars($whatsappDirectUrl) ?>" target="_blank" class="btn success" style="background: #25d366; color: white !important; font-weight: 700; white-space: nowrap; padding: 10px 18px; border-radius: 8px; text-decoration: none;">
                        💬 Open WhatsApp Chat Now →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($contactError)): ?>
            <div class="alert alert-danger" style="max-width: 800px; margin: 0 auto 30px;">
                ⚠️ <?= htmlspecialchars($contactError) ?>
            </div>
        <?php endif; ?>

        <!-- 3 HIGHLIGHTED ACTION CARDS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 45px;">
            <!-- Call Admin -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-top: 4px solid #0284c7; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(2, 132, 199, 0.08); text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="width: 58px; height: 58px; background: #e0f2fe; color: #0284c7; font-size: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 1px solid #bae6fd;">
                        📞
                    </div>
                    <h3 style="font-size: 19px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Direct Phone Call</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 16px; line-height: 1.5;">
                        Speak directly with the Digital Sewa Assam officer for urgent guidance on any certificate or application.
                    </p>
                    <div style="font-size: 20px; font-weight: 800; color: #0284c7; margin-bottom: 6px;">
                        +91 9613167470
                    </div>
                    <span style="font-size: 12px; color: #16a34a; font-weight: 700; display: block; margin-bottom: 20px;">
                        ● Available Mon - Sat: 9:00 AM - 7:00 PM
                    </span>
                </div>
                <a href="tel:+919613167470" class="btn primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; font-weight: 700; text-decoration: none;">
                    📞 Call Admin Now
                </a>
            </div>

            <!-- WhatsApp Chat -->
            <div style="background: #ffffff; border: 2px solid #22c55e; border-radius: 16px; padding: 30px; box-shadow: 0 12px 35px rgba(34, 197, 94, 0.16); text-align: center; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                <span style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #16a34a; color: white; padding: 3px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px;">
                    FASTEST RESPONSE
                </span>
                <div>
                    <div style="width: 58px; height: 58px; background: #dcfce7; color: #16a34a; font-size: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 1px solid #bbf7d0;">
                        💬
                    </div>
                    <h3 style="font-size: 19px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">WhatsApp Support</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 16px; line-height: 1.5;">
                        Send your queries, photos of rejected documents, or request status updates directly to our admin team.
                    </p>
                    <div style="font-size: 20px; font-weight: 800; color: #16a34a; margin-bottom: 6px;">
                        +91 9613167470
                    </div>
                    <span style="font-size: 12px; color: #0284c7; font-weight: 700; display: block; margin-bottom: 20px;">
                        ● Instant Document Guidance & Chat
                    </span>
                </div>
                <a href="https://wa.me/919613167470?text=Hello%20Digital%20Sewa%20Assam,%20I%20need%20help%20with%20a%20service%20application" target="_blank" class="btn success" style="background: #25d366; width: 100%; justify-content: center; padding: 12px; font-size: 14px; font-weight: 700; text-decoration: none;">
                    💬 Chat on WhatsApp →
                </a>
            </div>

            <!-- Email & Center Location -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-top: 4px solid #f59e0b; border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="width: 58px; height: 58px; background: #fef3c7; color: #b45309; font-size: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 1px solid #fde68a;">
                        📍
                    </div>
                    <h3 style="font-size: 19px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Email & Center Visit</h3>
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 16px; line-height: 1.5;">
                        Official citizen facilitation service point for physical document verification and assistance in Nagaon, Assam.
                    </p>
                    <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 6px; word-break: break-all;">
                        digitalesewa.csc@gmail.com
                    </div>
                    <span style="font-size: 12px; color: #64748b; display: block; margin-bottom: 20px;">
                        Nagaon, Assam - PIN: 782001
                    </span>
                </div>
                <a href="mailto:digitalesewa.csc@gmail.com?subject=Citizen%20Service%20Inquiry%20-%20Digital%20Sewa%20Assam" class="btn secondary" style="width: 100%; justify-content: center; padding: 12px; font-size: 14px; font-weight: 700; text-decoration: none;">
                    ✉️ Send Official Email
                </a>
            </div>
        </div>

        <!-- HIGHLIGHTED QUICK INQUIRY FORM -->
        <div style="max-width: 820px; margin: auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 34px; box-shadow: 0 15px 40px rgba(0,0,0,0.06);">
            <div style="text-align: center; margin-bottom: 24px;">
                <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 6px;">
                    Send an Instant Message to Admin
                </h3>
                <p style="font-size: 14px; color: #64748b;">
                    Fill out this quick form and the administrator will respond to your mobile number or WhatsApp.
                </p>
            </div>

            <form action="index.php#contact" method="POST">
                <input type="hidden" name="action" value="contact_inquiry">

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_name" style="font-weight: 700; color: #1e293b; font-size: 13px;">Your Full Name *</label>
                        <input type="text" id="contact_name" name="name" required placeholder="e.g. Rupesh Kalita" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>

                    <div class="form-group">
                        <label for="contact_mobile" style="font-weight: 700; color: #1e293b; font-size: 13px;">Mobile Number (10 Digits) *</label>
                        <input type="tel" id="contact_mobile" name="mobile" pattern="[6-9][0-9]{9}" maxlength="10" required placeholder="e.g. 9876543210" value="<?= htmlspecialchars($currentUser['mobile'] ?? '') ?>" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact_service" style="font-weight: 700; color: #1e293b; font-size: 13px;">Service You Need Assistance With</label>
                    <select id="contact_service" name="service_name" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;">
                        <option value="General Query">-- General Inquiry / Question --</option>
                        <?php foreach ($services as $svc): ?>
                            <option value="<?= htmlspecialchars($svc['service_name']) ?>">
                                <?= htmlspecialchars($svc['service_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="contact_message" style="font-weight: 700; color: #1e293b; font-size: 13px;">Your Query or Specific Requirement *</label>
                    <textarea id="contact_message" name="message" rows="3" required placeholder="Describe what certificate or correction you need assistance with..." style="background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;"></textarea>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 18px;">
                    <span style="font-size: 12px; color: #64748b;">
                        🔒 Your contact information is kept strictly confidential and only used for your service request.
                    </span>
                    <button type="submit" class="btn primary" style="padding: 12px 28px; font-size: 14px; font-weight: 700;">
                        ✉️ Submit Inquiry to Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- FLOATING WHATSAPP ADMIN BUTTON -->
<a href="https://wa.me/919613167470?text=Hello%20Digital%20Sewa%20Assam,%20I%20have%20an%20inquiry%20regarding%20a%20service" target="_blank" style="position: fixed; bottom: 25px; right: 25px; z-index: 999; display: flex; align-items: center; gap: 8px; background: #25d366; color: white; padding: 12px 20px; border-radius: 50px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 18px rgba(37, 211, 102, 0.45); font-size: 14px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" title="Click to chat directly with Admin on WhatsApp">
    <span style="font-size: 18px;">💬</span>
    <span>Connect with Admin</span>
</a>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="container footer-grid">
        <div>
            <?= logo_html('index.php') ?>
            <p style="margin: 14px 0; font-size: 14px; line-height: 1.6;">
                Digital Sewa Assam is dedicated to providing efficient, transparent, and prompt online citizen services across all districts of Assam.
            </p>
            <p style="font-size: 13px; color: #64748b;">
                Admin Portal: <a href="admin/login.php" style="color: #60a5fa; text-decoration: underline;">Staff & Officer Login</a>
            </p>
        </div>

        <div>
            <h4>Quick Links</h4>
            <a href="index.php">Home</a>
            <a href="#services">Services Catalog</a>
            <a href="apply.php">Apply Online</a>
            <a href="track.php">Track Application</a>
            <a href="#how-it-works">How It Works</a>
        </div>

        <div>
            <h4>Citizen Helpdesk</h4>
            <p style="margin-bottom: 8px;">📞 <b>+91 9613167470</b></p>
            <p style="margin-bottom: 8px;">✉️ digitalesewa.csc@gmail.com</p>
            <p style="font-size: 13px; color: #94a3b8;">
                Government Citizen Facilitation Services<br>
                Nagaon, Assam, India
            </p>
        </div>
    </div>

    <div class="copyright">
        <div>© <?= date('Y') ?> Digital Sewa Assam. All Rights Reserved.</div>
        <div style="margin-top: 8px; font-size: 12px; color: #cbd5e1; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px;">
            Developed by - <b style="color: #38bdf8;">Nilim Kumar Gogoi</b> &nbsp;|&nbsp; 
            Portfolio: <a href="https://nilimkrgogoi.github.io/Nilim_Portfolio/" target="_blank" rel="noopener noreferrer" style="color: #67e8f9; text-decoration: underline;">https://nilimkrgogoi.github.io/Nilim_Portfolio/</a> &nbsp;|&nbsp; 
            Email: <a href="mailto:nilimkumargogoi03@gmail.com" style="color: #67e8f9; text-decoration: underline;">nilimkumargogoi03@gmail.com</a>
        </div>
    </div>
</footer>

<script src="assets/js/script.js"></script>
</body>
</html>
