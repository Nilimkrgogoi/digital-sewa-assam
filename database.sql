-- Digital Seva Assam - Database Schema and Seed Data
-- Database: `digital_seva_assam`

CREATE DATABASE IF NOT EXISTS `digital_seva_assam` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `digital_seva_assam`;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `application_documents`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pre-seeded users:
-- Admin: mobile '9876543210', password 'Admin@123'
-- User: mobile '9876543211', password 'User@123'
INSERT INTO `users` (`id`, `name`, `mobile`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', '9876543210', 'admin@digitalseva.assam.gov.in', '$2y$10$tZ2cQ287B8n9bVpG/ZJ9y.f6Rk6k6vG4aE5qgM.6/H3q1Y.7sR6pG', 'admin', 'active', NOW()),
(2, 'Rupjyoti Sarma', '9876543211', 'rupjyoti@example.com', '$2y$10$wK1k6xZf8/zG9lS.j0X82.qVp5x5/Z5xL1rT2uY5/K3w1E9mK5rSa', 'user', 'active', NOW());

-- --------------------------------------------------------
-- Table structure for `services`
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `service_name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `fee` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `services` (`id`, `service_name`, `description`, `fee`, `status`, `created_at`) VALUES
(1, 'PAN Card Apply', 'New PAN card application and processing assistance', 280.00, 'active', NOW()),
(2, 'Voter Card', 'New voter ID card registration and EPIC download', 100.00, 'active', NOW()),
(3, 'Driving Licence', 'Learner and permanent driving licence application assistance', 7000.00, 'active', NOW()),
(4, 'Income Certificate', 'Assam revenue department income certificate application', 200.00, 'active', NOW()),
(5, 'Birth Certificate', 'Registration and issuance assistance for birth certificates', 1800.00, 'active', NOW()),
(6, 'Bank Account Opening', 'Zero balance savings account assistance', 300.00, 'active', NOW()),
(7, 'Caste Certificate', 'SC/ST/OBC caste certificate application', 300.00, 'active', NOW()),
(8, 'Mobile Number Update', 'Aadhaar / Portal mobile number linking assistance', 100.00, 'active', NOW()),
(9, 'Farmer Registry', 'Assam Farmer Registry official entry & documentation', 100.00, 'active', NOW()),
(10, 'PM Kisan', 'PM Kisan Samman Nidhi registration & eKYC verification', 100.00, 'active', NOW()),
(11, 'Scholarship Apply', 'National & Assam post-matric/pre-matric scholarship apply', 100.00, 'active', NOW()),
(12, 'College Admission', 'Online admission portal counseling & form filling', 100.00, 'active', NOW()),
(13, 'Job Apply', 'State and Central Govt online vacancy application', 100.00, 'active', NOW()),
(14, 'PM Surya Ghar Apply', 'PM Surya Ghar Muft Bijli Yojana rooftop solar application', 100.00, 'active', NOW()),
(15, 'PAN Card Correction', 'Correction and update of Name, Father\'s Name, Date of Birth, Photo, Signature, or Address on existing PAN', 280.00, 'active', NOW()),
(16, 'Voter Card Correction', 'Correction of Name, Age, Address, Photo, or Assembly Constituency in Voter ID (Form 8)', 100.00, 'active', NOW()),
(17, 'Voter Certified Copy Apply', 'Official certified copy / extract of Electoral Roll (Voter List) from Assam Election Office', 120.00, 'active', NOW()),
(18, 'ABHA Card Apply', 'Ayushman Bharat Health Account (ABHA) Digital Health Card generation, verification and card download', 50.00, 'active', NOW()),
(19, 'eShram Card', 'Ministry of Labour & Employment e-Shram National Database of Unorganised Workers card registration and UAN download', 60.00, 'active', NOW()),
(20, 'Jamabandi Land Document Apply', 'Assam Dharitree portal certified Jamabandi / Record of Rights (RoR) land document extract download', 150.00, 'active', NOW()),
(21, 'Land Revenue (Khajana)', 'Online Assam e-Khajana mouza land revenue assessment, payment and official payment receipt generation', 100.00, 'active', NOW()),
(22, 'CV / Resume Making', 'Professional curriculum vitae, job application resume, biodata formatting and PDF creation', 100.00, 'active', NOW()),
(23, 'Sewa Setu - Employment Exchange', 'Government of Assam Sewa Setu portal Employment Exchange online registration, renewal and certificate generation', 100.00, 'active', NOW()),
(24, 'Sewa Setu - Citizen Certificates', 'Assam Sewa Setu certificates: Bakijai, Permanent Resident Certificate (PRC), Senior Citizen, Non-Encumbrance', 150.00, 'active', NOW()),
(25, 'CMAAA Apply', 'Chief Minister\'s Atmanirbhar Asom Abhijan (CMAAA 2.0) online application, entrepreneurship subsidy & project documentation', 200.00, 'active', NOW()),
(26, 'Other Services', 'General government citizen queries and miscellaneous digital forms', 50.00, 'active', NOW());

-- --------------------------------------------------------
-- Table structure for `applications`
-- --------------------------------------------------------
CREATE TABLE `applications` (
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
  `payment_status` ENUM('pending','paid') DEFAULT 'pending',
  `issued_document` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `user_id` (`user_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `application_documents`
-- --------------------------------------------------------
CREATE TABLE `application_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `document_number` TINYINT(4) NOT NULL,
  `document_name` VARCHAR(100) DEFAULT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `payments`
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_id` VARCHAR(150) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `password_resets`
-- --------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mobile` VARCHAR(20) NOT NULL,
  `otp` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mobile_idx` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

