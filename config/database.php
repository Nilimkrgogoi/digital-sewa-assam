<?php
// config/database.php - Database connection & system helper functions

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$db_host = 'sql.freedb.tech';
$db_user = 'u_AcSPiK';
$db_pass = 'ab4vmlM4Vww1';
$db_name = 'freedb_yZsuzaP1';
// SMS Gateway Configuration (e.g., Fast2SMS, Twilio, Textlocal)
// Insert your SMS provider API key below to dispatch real SMS text messages directly to citizen phones.
define('SMS_GATEWAY_API_KEY', '');

try {
    // Connect to MySQL server first
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$db_name`");

    // Ensure users table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `mobile` VARCHAR(15) NOT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('user','admin') DEFAULT 'user',
        `status` ENUM('active','blocked') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `mobile` (`mobile`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure services table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `services` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `service_name` VARCHAR(150) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `fee` DECIMAL(10,2) DEFAULT 0.00,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure applications table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `applications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `application_id` VARCHAR(30) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `service_id` INT(11) NOT NULL,
        `full_name` VARCHAR(150) NOT NULL,
        `mobile` VARCHAR(15) NOT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `district` VARCHAR(100) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `status` ENUM('submitted','under_review','approved','rejected','completed') DEFAULT 'submitted',
        `remarks` TEXT DEFAULT NULL,
        `payment_status` ENUM('pending','paid','verified','rejected') DEFAULT 'pending',
        `issued_document` VARCHAR(500) DEFAULT NULL,
        `document_status` ENUM('pending','verified','rejected') DEFAULT 'pending',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `application_id` (`application_id`),
        KEY `user_id` (`user_id`),
        KEY `service_id` (`service_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure application_documents table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `application_documents` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `application_id` INT(11) NOT NULL,
        `document_number` TINYINT(4) NOT NULL,
        `document_name` VARCHAR(100) DEFAULT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `status` ENUM('pending','verified','rejected') DEFAULT 'pending',
        `rejection_reason` TEXT DEFAULT NULL,
        `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `application_id` (`application_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-migration: ensure required columns exist if tables pre-existed
    $appCols = $pdo->query("SHOW COLUMNS FROM applications")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('email', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN email VARCHAR(150) DEFAULT NULL AFTER mobile");
    }
    if (!in_array('district', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN district VARCHAR(100) DEFAULT NULL AFTER email");
    }
    if (!in_array('address', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN address TEXT DEFAULT NULL AFTER district");
    }
    if (!in_array('payment_status', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN payment_status ENUM('pending','paid','verified','rejected') DEFAULT 'pending' AFTER remarks");
    }
    if (!in_array('issued_document', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN issued_document VARCHAR(500) DEFAULT NULL AFTER payment_status");
    }
    if (!in_array('document_status', $appCols)) {
        $pdo->exec("ALTER TABLE applications ADD COLUMN document_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER issued_document");
    }

    $docCols = $pdo->query("SHOW COLUMNS FROM application_documents")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('document_name', $docCols)) {
        $pdo->exec("ALTER TABLE application_documents ADD COLUMN document_name VARCHAR(100) DEFAULT NULL AFTER document_number");
    }
    if (!in_array('status', $docCols)) {
        $pdo->exec("ALTER TABLE application_documents ADD COLUMN status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER file_path");
    }
    if (!in_array('rejection_reason', $docCols)) {
        $pdo->exec("ALTER TABLE application_documents ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER status");
    }

    // Auto-migration: ensure required_docs column exists in services
    $svcCols = $pdo->query("SHOW COLUMNS FROM services")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('required_docs', $svcCols)) {
        $pdo->exec("ALTER TABLE services ADD COLUMN required_docs TEXT DEFAULT NULL AFTER fee");
    }

    // Auto-migration: ensure auth and platform fee columns exist in users
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('auth_provider', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN auth_provider VARCHAR(30) DEFAULT 'email' AFTER role");
    }
    if (!in_array('platform_fee_paid', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN platform_fee_paid TINYINT(1) DEFAULT 0 AFTER auth_provider");
    }
    if (!in_array('platform_fee_txn', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN platform_fee_txn VARCHAR(100) DEFAULT NULL AFTER platform_fee_paid");
    }
    if (!in_array('raw_password', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN raw_password VARCHAR(255) DEFAULT NULL AFTER password");
        $pdo->exec("UPDATE users SET raw_password = 'Admin@123' WHERE role = 'admin' AND (raw_password IS NULL OR raw_password = '')");
        $pdo->exec("UPDATE users SET raw_password = 'User@123' WHERE role = 'user' AND (raw_password IS NULL OR raw_password = '')");
    }
    if (!in_array('platform_fee_status', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN platform_fee_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER platform_fee_txn");
        $pdo->exec("UPDATE users SET platform_fee_status = 'verified' WHERE platform_fee_paid = 1");
    }

    // Auto-migration: ensure users status ENUM supports processing and pending
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('active','blocked','processing','pending') DEFAULT 'processing'");
    } catch (Exception $e) {}

    // Ensure updated_at exists on users table
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('updated_at', $userCols)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } catch (Exception $e) {}
    }

    // Ensure UNIQUE index exists on users.email
    $userIndices = $pdo->query("SHOW INDEX FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $hasEmailIndex = false;
    foreach ($userIndices as $ui) {
        if ($ui['Column_name'] === 'email' && $ui['Non_unique'] == 0) {
            $hasEmailIndex = true;
            break;
        }
    }
    if (!$hasEmailIndex) {
        try {
            $pdo->exec("ALTER TABLE users ADD UNIQUE KEY `users_email_unique` (`email`)");
        } catch (Exception $e) {}
    }

    // Ensure uploads, uploads/issued and uploads/qr directories exist
    $issuedDir = __DIR__ . '/../uploads/issued/';
    if (!is_dir($issuedDir)) {
        mkdir($issuedDir, 0755, true);
    }
    $qrDir = __DIR__ . '/../uploads/qr/';
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0755, true);
    }

    // Ensure payments table exists with UTR verification fields
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `application_id` INT(11) NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `transaction_id` VARCHAR(150) DEFAULT NULL,
        `payment_method` VARCHAR(50) DEFAULT NULL,
        `payment_status` ENUM('pending','paid','verified','failed','rejected') DEFAULT 'pending',
        `utr_status` ENUM('pending','verified','rejected') DEFAULT 'pending',
        `verified_at` DATETIME DEFAULT NULL,
        `verified_by` INT(11) DEFAULT NULL,
        `verification_remarks` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `application_id` (`application_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-migration: ensure UTR verification columns exist in payments if table already existed
    $payCols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('utr_status', $payCols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN utr_status ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER payment_status");
        $pdo->exec("UPDATE payments SET utr_status = 'verified' WHERE payment_status = 'paid'");
    }
    if (!in_array('verified_at', $payCols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN verified_at DATETIME DEFAULT NULL AFTER utr_status");
    }
    if (!in_array('verified_by', $payCols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN verified_by INT(11) DEFAULT NULL AFTER verified_at");
    }
    if (!in_array('verification_remarks', $payCols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN verification_remarks VARCHAR(255) DEFAULT NULL AFTER verified_by");
    }

    // Ensure applications payment_status column supports verified and rejected
    try {
        $pdo->exec("ALTER TABLE applications MODIFY COLUMN payment_status ENUM('pending','paid','verified','rejected') DEFAULT 'pending'");
    } catch (Exception $e) {}

    // Ensure password_resets table exists for OTP verification
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `mobile` VARCHAR(20) NOT NULL,
        `otp` VARCHAR(10) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `mobile_idx` (`mobile`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure system_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT DEFAULT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure contact_inquiries table exists for citizen support messages
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_inquiries` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `mobile` VARCHAR(20) NOT NULL,
        `service_name` VARCHAR(150) DEFAULT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('new','responded') DEFAULT 'new',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default SMS settings if not present
    $settingCheck = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'sms_gateway_provider'")->fetchColumn();
    if ($settingCheck == 0) {
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES 
            ('sms_gateway_provider', 'fast2sms'),
            ('sms_gateway_api_key', ''),
            ('sms_sender_id', 'TXTIND')");
    }

    // Seed default Platform Fee & Payment receiver settings if not present
    $platCheck = $pdo->query("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'platform_fee'")->fetchColumn();
    if ($platCheck == 0) {
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES 
            ('platform_fee', '50.00'),
            ('bank_name', 'State Bank of India'),
            ('bank_account_holder', 'Digital Sewa Assam Admin'),
            ('bank_account_no', '389201948201'),
            ('bank_ifsc', 'SBIN0000123'),
            ('upi_id', '9613167470@upi'),
            ('upi_qr_image', '')");
    }

    // Check and seed default admin user
    $adminCheck = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $adminCheck->execute();
    if (!$adminCheck->fetch()) {
        $adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("INSERT INTO users (name, mobile, email, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $insertAdmin->execute(['System Administrator', '9876543210', 'admin@digitalsewa.assam.gov.in', $adminPass]);
    }

    // Check and seed sample citizen user
    $userCheck = $pdo->prepare("SELECT id FROM users WHERE mobile = '9876543211' LIMIT 1");
    $userCheck->execute();
    if (!$userCheck->fetch()) {
        $userPass = password_hash('User@123', PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare("INSERT INTO users (name, mobile, email, password, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
        $insertUser->execute(['Rupjyoti Sarma', '9876543211', 'rupjyoti@example.com', $userPass]);
    }

    // Standard Digital Sewa Assam services
    $allStandardServices = [
        ['PAN Card Apply', 'New PAN card application and processing assistance', 280.00],
        ['PAN Card Correction', 'Correction and update of Name, Father\'s Name, Date of Birth, Photo, Signature, or Address on existing PAN', 280.00],
        ['Voter Card', 'New voter ID card registration and EPIC download', 100.00],
        ['Voter Card Correction', 'Correction of Name, Age, Address, Photo, or Assembly Constituency in Voter ID (Form 8)', 100.00],
        ['Voter Certified Copy Apply', 'Official certified copy / extract of Electoral Roll (Voter List) from Assam Election Office', 120.00],
        ['Driving Licence', 'Learner and permanent driving licence application assistance', 7000.00],
        ['Income Certificate', 'Assam revenue department income certificate application', 200.00],
        ['Birth Certificate', 'Registration and issuance assistance for birth certificates', 1800.00],
        ['Bank Account Opening', 'Zero balance savings account assistance', 300.00],
        ['Caste Certificate', 'SC/ST/OBC caste certificate application', 300.00],
        ['Mobile Number Update', 'Aadhaar / Portal mobile number linking assistance', 100.00],
        ['ABHA Card Apply', 'Ayushman Bharat Health Account (ABHA) Digital Health Card generation, verification and card download', 50.00],
        ['eShram Card', 'Ministry of Labour & Employment e-Shram National Database of Unorganised Workers card registration and UAN download', 60.00],
        ['Jamabandi Land Document Apply', 'Assam Dharitree portal certified Jamabandi / Record of Rights (RoR) land document extract download', 150.00],
        ['Land Revenue (Khajana)', 'Online Assam e-Khajana mouza land revenue assessment, payment and official payment receipt generation', 100.00],
        ['CV / Resume Making', 'Professional curriculum vitae, job application resume, biodata formatting and PDF creation', 100.00],
        ['Sewa Setu - Employment Exchange', 'Government of Assam Sewa Setu portal Employment Exchange online registration, renewal and certificate generation', 100.00],
        ['Sewa Setu - Citizen Certificates', 'Assam Sewa Setu certificates: Bakijai, Permanent Resident Certificate (PRC), Senior Citizen, Non-Encumbrance', 150.00],
        ['CMAAA Apply', 'Chief Minister\'s Atmanirbhar Asom Abhijan (CMAAA 2.0) online application, entrepreneurship subsidy & project documentation', 200.00],
        ['Farmer Registry', 'Assam Farmer Registry official entry & documentation', 100.00],
        ['PM Kisan', 'PM Kisan Samman Nidhi registration & eKYC verification', 100.00],
        ['Scholarship Apply', 'National & Assam post-matric/pre-matric scholarship apply', 100.00],
        ['College Admission', 'Online admission portal counseling & form filling', 100.00],
        ['Job Apply', 'State and Central Govt online vacancy application', 100.00],
        ['PM Surya Ghar Apply', 'PM Surya Ghar Muft Bijli Yojana rooftop solar application', 100.00],
        ['Other Services', 'General government citizen queries and miscellaneous digital forms', 50.00]
    ];

    // Seed standard services ONLY IF services table is completely empty
    $svcCount = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ($svcCount == 0) {
        $insertSvc = $pdo->prepare("INSERT INTO services (service_name, description, fee, status) VALUES (?, ?, ?, 'active')");
        foreach ($allStandardServices as $s) {
            $insertSvc->execute($s);
        }
    }

} catch (PDOException $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// ---------------- Helper Functions ----------------

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login($redirect_to = null) {
    if (!is_logged_in()) {
        $target = $redirect_to ? urlencode($redirect_to) : urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: login.php?redirect=" . $target);
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header("Location: ../admin/login.php?error=unauthorized");
        exit;
    }
}

function get_current_user_data($pdo) {
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function format_date($datetime) {
    if (!$datetime) return 'N/A';
    return date('d M Y, h:i A', strtotime($datetime));
}

function generate_app_id() {
    return 'DSA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function get_status_badge($status) {
    switch (strtolower($status)) {
        case 'submitted':
            return '<span class="status-badge badge-submitted">Submitted</span>';
        case 'under_review':
            return '<span class="status-badge badge-review">Under Review</span>';
        case 'approved':
            return '<span class="status-badge badge-approved">Approved</span>';
        case 'completed':
            return '<span class="status-badge badge-completed">Completed</span>';
        case 'rejected':
            return '<span class="status-badge badge-rejected">Rejected</span>';
        case 'processing':
        case 'pending':
            return '<span class="status-badge badge-pending">Processing</span>';
        default:
            return '<span class="status-badge">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}

/**
 * Return the site logo HTML for citizen pages (relative path from root)
 */
function logo_html($link = 'index.php') {
    return '<a href="' . $link . '" class="logo">
        <img src="assets/images/logo.jpg" alt="Digital Sewa Assam Logo" class="logo-img">
        <span>Digital Sewa <b>Assam</b></span>
    </a>';
}

/**
 * Return the site logo HTML for admin pages (one level deep)
 */
function logo_html_admin($link = 'dashboard.php') {
    return '<a href="' . $link . '" class="logo" style="color:#ffffff; font-weight: 800; text-decoration: none;">
        <img src="../assets/images/logo.jpg" alt="Digital Sewa Assam Logo" class="logo-img" style="border: 2px solid #38bdf8;">
        <span style="color: #ffffff; font-size: 19px; font-weight: 800;">Digital Sewa <b style="color: #38bdf8; font-weight: 800;">Admin</b></span>
    </a>';
}

/**
 * Return service logo image HTML or fallback icon
 */
function get_service_logo_html($name, $prefix = '') {
    $n = strtolower($name);
    $img = '';
    
    if (strpos($n, 'pan') !== false) {
        $img = $prefix . 'assets/images/services/pan.svg';
    } elseif (strpos($n, 'aadhaar') !== false || strpos($n, 'adhaar') !== false || strpos($n, 'mobile') !== false) {
        $img = $prefix . 'assets/images/services/aadhaar.svg';
    } elseif (strpos($n, 'voter') !== false || strpos($n, 'epic') !== false) {
        $img = $prefix . 'assets/images/services/voter.svg';
    } elseif (strpos($n, 'licence') !== false || strpos($n, 'license') !== false || strpos($n, 'dl') !== false) {
        $img = $prefix . 'assets/images/services/dl.svg';
    } elseif (strpos($n, 'cmaaa') !== false || strpos($n, 'atmanirbhar') !== false) {
        $img = $prefix . 'assets/images/services/cmaaa.svg';
    } elseif (strpos($n, 'sewa') !== false || strpos($n, 'setu') !== false || strpos($n, 'employment') !== false) {
        $img = $prefix . 'assets/images/services/sewasetu.svg';
    } elseif (strpos($n, 'abha') !== false || strpos($n, 'health') !== false) {
        $img = $prefix . 'assets/images/services/abha.svg';
    } elseif (strpos($n, 'eshram') !== false || strpos($n, 'shram') !== false) {
        $img = $prefix . 'assets/images/services/eshram.svg';
    } elseif (strpos($n, 'jamabandi') !== false || strpos($n, 'khajana') !== false || strpos($n, 'dharitree') !== false) {
        $img = $prefix . 'assets/images/services/land.svg';
    } elseif (strpos($n, 'resume') !== false || strpos($n, 'cv') !== false || strpos($n, 'biodata') !== false) {
        $img = $prefix . 'assets/images/services/resume.svg';
    } elseif (strpos($n, 'income') !== false || strpos($n, 'caste') !== false || strpos($n, 'certificate') !== false) {
        $img = $prefix . 'assets/images/services/certificate.svg';
    } elseif (strpos($n, 'birth') !== false) {
        $img = $prefix . 'assets/images/services/birth.svg';
    } elseif (strpos($n, 'bank') !== false) {
        $img = $prefix . 'assets/images/services/bank.svg';
    } elseif (strpos($n, 'farmer') !== false || strpos($n, 'kisan') !== false) {
        $img = $prefix . 'assets/images/services/farmer.svg';
    } elseif (strpos($n, 'scholarship') !== false || strpos($n, 'scholaship') !== false || strpos($n, 'college') !== false || strpos($n, 'admission') !== false) {
        $img = $prefix . 'assets/images/services/education.svg';
    } elseif (strpos($n, 'job') !== false) {
        $img = $prefix . 'assets/images/services/job.svg';
    } elseif (strpos($n, 'surya') !== false || strpos($n, 'solar') !== false) {
        $img = $prefix . 'assets/images/services/solar.svg';
    }

    if (!empty($img)) {
        return '<img src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($name) . ' Logo" class="service-logo-img">';
    }
    return '<span class="service-icon emoji">📄</span>';
}

/**
 * Retrieve a single portal setting value
 */
function get_portal_setting($key, $default = '') {
    global $pdo;
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null) {
                return $val;
            }
        }
    } catch (Exception $e) {}
    return $default;
}

/**
 * Retrieve all portal settings as associative array
 */
function get_portal_settings() {
    global $pdo;
    $settings = [];
    try {
        if ($pdo) {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
        }
    } catch (Exception $e) {}
    return $settings;
}

/**
 * Update or insert a portal setting
 */
function update_portal_setting($key, $value) {
    global $pdo;
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            return $stmt->execute([$key, $value]);
        }
    } catch (Exception $e) {}
    return false;
}

/**
 * Return required and optional documents definition for a given service.
 * First checks if custom required_docs is configured by Admin in the database.
 */
function get_service_required_documents($serviceName, $serviceId = null) {
    global $pdo;

    // 1. Check custom documents configured in database
    try {
        if ($pdo) {
            $svcRow = null;
            if ($serviceId) {
                $sStmt = $pdo->prepare("SELECT id, service_name, required_docs FROM services WHERE id = ? LIMIT 1");
                $sStmt->execute([(int)$serviceId]);
                $svcRow = $sStmt->fetch();
            } elseif (!empty($serviceName)) {
                $sStmt = $pdo->prepare("SELECT id, service_name, required_docs FROM services WHERE service_name = ? LIMIT 1");
                $sStmt->execute([$serviceName]);
                $svcRow = $sStmt->fetch();
            }

            if ($svcRow && !empty($svcRow['required_docs'])) {
                $raw = trim($svcRow['required_docs']);
                $docList = [];

                if ($raw[0] === '[' || $raw[0] === '{') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $docList = $decoded;
                    }
                }

                if (empty($docList)) {
                    $lines = preg_split('/[\r\n,\|]+/', $raw);
                    foreach ($lines as $l) {
                        $l = trim($l);
                        if (!empty($l)) {
                            $docList[] = ['name' => $l, 'desc' => 'Upload clear scan/copy of ' . $l, 'required' => true];
                        }
                    }
                }

                if (!empty($docList)) {
                    $result = [];
                    $idx = 1;
                    foreach ($docList as $d) {
                        $dName = is_array($d) ? ($d['name'] ?? "Document $idx") : $d;
                        $dDesc = is_array($d) ? ($d['desc'] ?? 'Upload clear document scan/copy') : 'Upload clear document scan/copy';
                        $dReq = is_array($d) ? ($d['required'] ?? true) : true;
                        $result['doc' . $idx] = [
                            'num' => $idx,
                            'name' => $dName,
                            'desc' => $dDesc,
                            'required' => (bool)$dReq
                        ];
                        $idx++;
                    }
                    return $result;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to presets
    }

    $n = strtolower($serviceName);

    // PAN Card Correction
    if (strpos($n, 'pan') !== false && (strpos($n, 'correction') !== false || strpos($n, 'update') !== false)) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Existing PAN Card Copy / Proof of PAN', 'desc' => 'Copy of current PAN card or allotment letter', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Identity (Aadhaar Card)', 'desc' => 'Clear copy of Aadhaar Card', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Address (Aadhaar / Voter / Electricity Bill)', 'desc' => 'Valid residential proof', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Proof for Requested Correction', 'desc' => 'Document supporting change (10th Admit, Birth Cert, or Gazette copy)', 'required' => true],
            'doc5' => ['num' => 5, 'name' => 'Recent Passport Size Color Photograph', 'desc' => 'Clear photo with white background', 'required' => true],
            'doc6' => ['num' => 6, 'name' => 'Signature Specimen / Thumb Impression', 'desc' => 'Clear signature on white paper', 'required' => false],
        ];
    }

    // Voter Card Correction
    if (strpos($n, 'voter') !== false && (strpos($n, 'correction') !== false || strpos($n, 'update') !== false)) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Existing Voter Card (EPIC) Copy', 'desc' => 'Front and back scan of current Voter ID card', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Identity & Residence (Aadhaar / Ration Card)', 'desc' => 'Clear copy of Aadhaar Card or Ration Card', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof Supporting Correct Details', 'desc' => 'Birth Cert, 10th Admit Card, or Land Record showing correct Name/DOB', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Recent Passport Size Photograph', 'desc' => 'Color passport photograph', 'required' => false],
        ];
    }

    // Voter Certified Copy Apply
    if (strpos($n, 'voter') !== false && (strpos($n, 'certif') !== false || strpos($n, 'copy') !== false)) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Voter ID (EPIC) / Part & Serial No Details', 'desc' => 'Copy of Voter ID card or voter slip reference', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Applicant Identity Proof (Aadhaar Card)', 'desc' => 'Clear copy of official ID', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Residence in Assam', 'desc' => 'Aadhaar Card, Land Document, or Electricity Bill', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Requisition / Purpose Note', 'desc' => 'Mention court case, NRC, or legal purpose if applicable', 'required' => false],
        ];
    }

    // ABHA Card Apply
    if (strpos($n, 'abha') !== false || strpos($n, 'health') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Aadhaar Card (Mobile Linked for OTP)', 'desc' => 'Clear copy of Aadhaar Card with active phone number for instant OTP', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Supporting / Existing Health ID', 'desc' => 'Previous health card, ration card, or phone number proof', 'required' => false],
        ];
    }

    // eShram Card
    if (strpos($n, 'eshram') !== false || strpos($n, 'shram') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Aadhaar Card Copy', 'desc' => 'Clear copy of Aadhaar Card', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Bank Account Passbook Copy', 'desc' => 'Front page of bank passbook showing Account Number & IFSC', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Active Mobile Number Proof / Nominee Aadhaar', 'desc' => 'Nominee details or occupation proof', 'required' => false],
        ];
    }

    // Jamabandi Land Document Apply
    if (strpos($n, 'jamabandi') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Land Patta / Dag / Mouza Details Note', 'desc' => 'Details of Patta No, Dag No, Revenue Village, Mouza, Circle & District', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Applicant Identity Proof (Aadhaar / Voter ID)', 'desc' => 'Clear copy of official photo ID', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Previous Khajana Receipt / Sale Deed Copy', 'desc' => 'Latest land revenue receipt or registered deed copy', 'required' => false],
        ];
    }

    // Land Revenue (Khajana)
    if (strpos($n, 'khajana') !== false || (strpos($n, 'land') !== false && strpos($n, 'revenue') !== false)) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Latest Land Revenue Receipt (Previous Khajana Rasid / খাজনাৰ ৰছিদ)', 'desc' => 'Up-to-date or previous tax receipt copy', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Land Patta / Jamabandi Copy', 'desc' => 'Copy of Patta document or Dharitree Jamabandi extract', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Pattadar / Applicant Identity Proof (Aadhaar / Voter)', 'desc' => 'Aadhaar or Voter ID of landholder/applicant', 'required' => true],
        ];
    }

    // CV / Resume Making
    if (strpos($n, 'cv') !== false || strpos($n, 'resume') !== false || strpos($n, 'biodata') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Educational Certificates & Marksheets', 'desc' => 'Scans of 10th, 12th, Degree or highest qualification certificates', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Draft Biodata / Personal Details Note', 'desc' => 'Text note or handwritten list of work history, skills & contact details', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Recent Passport Size Color Photograph', 'desc' => 'Clear professional photo for resume header', 'required' => false],
            'doc4' => ['num' => 4, 'name' => 'Work Experience / Training Certificates', 'desc' => 'Experience letters, computer course diploma, or internship certs', 'required' => false],
        ];
    }

    // Sewa Setu - Employment Exchange
    if ((strpos($n, 'sewa') !== false || strpos($n, 'setu') !== false) && strpos($n, 'employment') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Educational Qualification Certificates & Marksheets', 'desc' => 'All educational certificates (HSLC, HS, Degree/Diploma)', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Age (HSLC Admit Card / Birth Certificate)', 'desc' => 'Valid DOB proof document', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Residence in Assam (PRC / Voter Card / Aadhaar)', 'desc' => 'Permanent residence proof within Assam', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Recent Passport Size Color Photograph', 'desc' => 'Clear passport photo', 'required' => true],
            'doc5' => ['num' => 5, 'name' => 'Caste Certificate (If Applicable)', 'desc' => 'SC, ST, OBC, MOBC or EWS certificate', 'required' => false],
            'doc6' => ['num' => 6, 'name' => 'Skill / Computer / Experience Certificate', 'desc' => 'Any additional vocational training diplomas', 'required' => false],
        ];
    }

    // Sewa Setu - Citizen Certificates
    if (strpos($n, 'sewa') !== false || strpos($n, 'setu') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Proof of Identity (Aadhaar / Voter ID)', 'desc' => 'Clear official government ID', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Residence in Assam (PRC / Land Doc / Electricity Bill)', 'desc' => 'Residential address proof', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Gaon Burah / Mouzadar Recommendation Certificate', 'desc' => 'Local authority recommendation letter', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Land Revenue Receipt / Supporting Documentation', 'desc' => 'Up-to-date Khajana receipt or family tree record', 'required' => false],
        ];
    }

    // CMAAA Apply (Chief Minister's Atmanirbhar Asom Abhijan)
    if (strpos($n, 'cmaaa') !== false || strpos($n, 'atmanirbhar') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Educational Qualification (Min Class 10 / 12 / ITI / Degree)', 'desc' => 'Pass certificate of highest qualification', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Age & Identity (HSLC Admit / Aadhaar Card)', 'desc' => 'Age between 28-40 (or relaxed category) with DOB proof', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Permanent Residence in Assam (PRC / Voter Card)', 'desc' => 'PRC or Voter ID issued in Assam', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Bank Account Passbook (Showing IFSC & Account No)', 'desc' => 'Active personal bank account details for subsidy disbursement', 'required' => true],
            'doc5' => ['num' => 5, 'name' => 'Passport Size Color Photograph', 'desc' => 'Clear passport photo', 'required' => true],
            'doc6' => ['num' => 6, 'name' => 'Business Project Concept / Skill Training Certificate', 'desc' => 'Proposed enterprise plan, trade details or ITI/MSME certificate', 'required' => false],
        ];
    }

    // Income Certificate
    if (strpos($n, 'income') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Address Proof (Aadhaar / Voter / Ration Card)', 'desc' => 'Aadhaar Card, Voter Card or Ration Card', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Identity Proof (Aadhaar / Voter / PAN Card)', 'desc' => 'Aadhaar Card or Voter Card', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Land Revenue Receipt (Up-to-date Khajana Rasid)', 'desc' => 'Latest land tax receipt (খাজনাৰ ৰছিদ)', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Salary Slip (If Salaried Person)', 'desc' => 'Required for salaried applicants (দৰমহাৰ পত্ৰ)', 'required' => false],
            'doc5' => ['num' => 5, 'name' => 'Gaon Burah Certificate / Recommendation', 'desc' => 'Village headman certificate (গাঁওবুঢ়াৰ প্ৰমাণ পত্ৰ)', 'required' => false],
            'doc6' => ['num' => 6, 'name' => 'Ration Card / Other Supporting Document', 'desc' => 'Ration card or application copy', 'required' => false],
        ];
    }

    // Caste Certificate
    if (strpos($n, 'caste') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => "Applicant's Photo (পাছপৰ্ট আকাৰৰ ফটো)", 'desc' => 'Clear recent passport photograph', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Date of Birth (Birth Certificate / Aadhaar / PAN / Admit Card)', 'desc' => 'Birth Certificate, Aadhaar, PAN or School Admit Card', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Residence (Aadhaar / Voter / PRC / Land Doc / Electricity Bill)', 'desc' => 'Aadhaar Card, Voter Card, PRC or Ration Card', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Caste Certificate of Father / Parent', 'desc' => "Father's existing caste certificate (পিতৃৰ জাতিৰ প্ৰমাণ পত্ৰ)", 'required' => true],
            'doc5' => ['num' => 5, 'name' => 'Recommendation of Authorized Caste/Tribe Organization / Gaon Burah', 'desc' => 'Community / organization recommendation letter', 'required' => false],
            'doc6' => ['num' => 6, 'name' => 'Any Other Document (Voter List / Affidavit / Existing Caste Doc)', 'desc' => 'Voter list copy, court affidavit, or supporting paper', 'required' => false],
        ];
    }

    // PAN Card Apply
    if (strpos($n, 'pan') !== false) {
        return [
            'doc1' => ['num' => 1, 'name' => 'Proof of Identity (Aadhaar Card / Voter Card)', 'desc' => 'Clear copy of Aadhaar or Voter Card', 'required' => true],
            'doc2' => ['num' => 2, 'name' => 'Proof of Address (Aadhaar / Electricity Bill / Bank Passbook)', 'desc' => 'Residential address proof', 'required' => true],
            'doc3' => ['num' => 3, 'name' => 'Proof of Date of Birth (Aadhaar / Birth Certificate / 10th Admit Card)', 'desc' => 'DOB proof document', 'required' => true],
            'doc4' => ['num' => 4, 'name' => 'Recent Passport Size Color Photograph', 'desc' => 'Clear photo with white background', 'required' => true],
            'doc5' => ['num' => 5, 'name' => 'Signature Specimen / Thumb Impression', 'desc' => 'Signature on white blank paper', 'required' => false],
        ];
    }

    // Default general citizen service documents
    return [
        'doc1' => ['num' => 1, 'name' => 'Identity Proof (Aadhaar / Voter ID)', 'desc' => 'Clear copy of official ID', 'required' => true],
        'doc2' => ['num' => 2, 'name' => 'Address Proof (Electricity / Ration / Passbook)', 'desc' => 'Valid residential proof', 'required' => true],
        'doc3' => ['num' => 3, 'name' => 'Passport Size Photograph', 'desc' => 'Applicant photograph', 'required' => false],
        'doc4' => ['num' => 4, 'name' => 'Supporting / Category Proof Document', 'desc' => 'Any related application support document', 'required' => false],
    ];
}

/**
 * Dispatch real SMS OTP to citizen mobile number
 * Supports standard HTTP SMS gateways (Fast2SMS, Textlocal, etc.)
 */
function send_mobile_otp($mobile, $otp) {
    global $pdo;

    // Clean mobile number
    $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($cleanMobile) === 12 && substr($cleanMobile, 0, 2) === '91') {
        $cleanMobile = substr($cleanMobile, 2);
    }

    // Check database settings first, then constants, then environment
    $smsApiKey = '';
    $provider = 'fast2sms';
    try {
        if ($pdo) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_gateway_api_key', 'sms_gateway_provider')");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($settings['sms_gateway_api_key'])) {
                $smsApiKey = trim($settings['sms_gateway_api_key']);
            }
            if (!empty($settings['sms_gateway_provider'])) {
                $provider = trim($settings['sms_gateway_provider']);
            }
        }
    } catch (Exception $e) {
        // Fallback
    }

    if (empty($smsApiKey)) {
        $smsApiKey = defined('SMS_GATEWAY_API_KEY') ? trim(SMS_GATEWAY_API_KEY) : '';
    }
    if (empty($smsApiKey)) {
        $smsApiKey = getenv('SMS_API_KEY') ?: '';
    }
    
    // Log dispatch attempt for administrative audit
    $logDir = __DIR__ . '/../uploads/';
    $statusText = !empty($smsApiKey) ? "Attempting real SMS delivery via $provider" : "No SMS Gateway API Key configured (waiting for key)";
    $logEntry = date('Y-m-d H:i:s') . " | Mobile: +91 $cleanMobile | Code: $otp | Status: $statusText\n";
    @file_put_contents($logDir . 'otp_log.txt', $logEntry, FILE_APPEND);

    // If API Key is configured, attempt real HTTP delivery
    if (!empty($smsApiKey) && $smsApiKey !== 'YOUR_API_KEY_HERE') {
        if (function_exists('curl_init')) {
            $curl = curl_init();
            
            // Primary: Fast2SMS Quick SMS / OTP bulkV2 route
            $postData = [
                "variables_values" => (string)$otp,
                "route" => "otp",
                "numbers" => (string)$cleanMobile
            ];

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false, // Ensure local SSL bundle doesn't block local XAMPP requests
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    "authorization: " . $smsApiKey,
                    "accept: */*",
                    "cache-control: no-cache",
                    "content-type: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // Log raw response for debugging
            @file_put_contents($logDir . 'otp_log.txt', date('Y-m-d H:i:s') . " | Fast2SMS HTTP $httpCode Response: " . ($err ? "cURL Error: $err" : $response) . "\n", FILE_APPEND);

            if (!$err && !empty($response)) {
                $json = json_decode($response, true);
                if (isset($json['return']) && $json['return'] === true) {
                    @file_put_contents($logDir . 'otp_log.txt', date('Y-m-d H:i:s') . " | Real SMS Delivered Successfully to +91 $cleanMobile\n", FILE_APPEND);
                    return [
                        'success' => true,
                        'delivered' => true,
                        'provider' => 'fast2sms',
                        'message' => 'Real SMS sent to +91 ' . $cleanMobile
                    ];
                } else {
                    $errorMsg = is_array($json['message'] ?? null) ? implode(', ', $json['message']) : ($json['message'] ?? 'Fast2SMS returned an error');
                    return [
                        'success' => false,
                        'delivered' => false,
                        'provider' => 'fast2sms',
                        'message' => $errorMsg
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'delivered' => false,
                    'provider' => 'fast2sms',
                    'message' => 'Connection error to Fast2SMS: ' . ($err ?: 'No response')
                ];
            }
        }
    }

    // No API key configured yet
    return [
        'success' => false, 
        'delivered' => false,
        'has_api_key' => false,
        'message' => 'Fast2SMS API Key not configured yet. Please add your key in Admin SMS Settings or config/database.php.',
        'mobile' => $cleanMobile
    ];
}

/**
 * Global Portal Developer & Copyright Footer HTML
 */
function portal_footer_html($isAdmin = false) {
    $year = date('Y');
    $subTitle = $isAdmin ? 'Official Administration Console' : 'Citizen Facilitation & Digital Services Portal';
    return '<footer style="margin-top: auto; padding: 25px 0; background: #0f172a; text-align: center; color: #94a3b8; font-size: 13px; line-height: 1.8;" class="site-footer">
    <div class="container">
        <div>© ' . $year . ' Digital Sewa Assam • ' . $subTitle . '</div>
        <div style="margin-top: 8px; font-size: 12px; color: #cbd5e1; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px;">
            Developed by - <b style="color: #38bdf8;">Nilim Kumar Gogoi</b> &nbsp;|&nbsp; 
            Portfolio: <a href="https://nilimkrgogoi.github.io/Nilim_Portfolio/" target="_blank" rel="noopener noreferrer" style="color: #67e8f9; text-decoration: underline;">https://nilimkrgogoi.github.io/Nilim_Portfolio/</a> &nbsp;|&nbsp; 
            Email: <a href="mailto:nilimkumargogoi03@gmail.com" style="color: #67e8f9; text-decoration: underline;">nilimkumargogoi03@gmail.com</a>
        </div>
    </div>
</footer>';
}
?>
