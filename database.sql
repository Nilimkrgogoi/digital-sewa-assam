-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db:3306
-- Generation Time: Sep 24, 2026 at 05:27 PM
-- Server version: 8.0.46
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `digital_seva_assam`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int NOT NULL,
  `application_id` varchar(30) NOT NULL,
  `user_id` int NOT NULL,
  `service_id` int NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `address` text,
  `status` enum('submitted','under_review','approved','rejected','completed') DEFAULT 'submitted',
  `remarks` text,
  `payment_status` enum('pending','paid','verified','rejected') DEFAULT 'pending',
  `issued_document` varchar(500) DEFAULT NULL,
  `document_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `application_id`, `user_id`, `service_id`, `full_name`, `mobile`, `email`, `district`, `address`, `status`, `remarks`, `payment_status`, `issued_document`, `document_status`, `created_at`, `updated_at`) VALUES
(1, 'DSA-2026-9F5F6B', 2, 12, 'Rupjyoti Sarma', '9876543211', 'rupjyoti@example.com', 'Dibrugarh', 'asjbajd', 'submitted', 'sdj', 'paid', NULL, 'pending', '2026-09-23 18:02:51', '2026-09-23 18:02:51'),
(2, 'DSA-2026-FCB79C', 2, 12, 'Rupjyoti Sarma', '9876543211', 'rupjyoti@example.com', 'Dibrugarh', 'asjbajd', 'under_review', 'sdj', 'verified', NULL, 'verified', '2026-09-23 18:06:23', '2026-09-24 11:07:55');

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `id` int NOT NULL,
  `application_id` int NOT NULL,
  `document_number` tinyint NOT NULL,
  `document_name` varchar(100) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `application_documents`
--

INSERT INTO `application_documents` (`id`, `application_id`, `document_number`, `document_name`, `file_name`, `file_path`, `status`, `rejection_reason`, `uploaded_at`) VALUES
(1, 2, 1, 'Aadhaar Card (Mobile Linked for OTP)', 'ss.png', 'uploads/doc_1_20260923180623_e496de50.png', 'verified', NULL, '2026-09-23 18:06:23'),
(2, 2, 2, 'Supporting / Existing Health ID', 'Screenshot_2026-08-01_00-17-05.png', 'uploads/doc_2_20260923180623_905ef218.png', 'verified', NULL, '2026-09-23 18:06:23');

-- --------------------------------------------------------

--
-- Table structure for table `contact_inquiries`
--

CREATE TABLE `contact_inquiries` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `service_name` varchar(150) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','responded') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `application_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(150) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','verified','failed','rejected') DEFAULT 'pending',
  `utr_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `verification_remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `application_id`, `amount`, `transaction_id`, `payment_method`, `payment_status`, `utr_status`, `verified_at`, `verified_by`, `verification_remarks`, `created_at`) VALUES
(1, 1, 50.00, '654321781202', 'UPI_ONLINE', 'paid', 'pending', NULL, NULL, NULL, '2026-09-23 18:02:51'),
(2, 2, 50.00, '654321781202', 'UPI_ONLINE', 'verified', 'verified', '2026-09-24 11:07:55', 1, 'Payment verified by Admin', '2026-09-23 18:06:23');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text,
  `fee` decimal(10,2) DEFAULT '0.00',
  `required_docs` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `service_name`, `description`, `fee`, `required_docs`, `status`, `created_at`) VALUES
(1, 'PAN Card Apply', 'New PAN card application and processing assistance', 280.00, NULL, 'active', '2026-09-23 17:41:32'),
(2, 'PAN Card Correction', 'Correction and update of Name, Father\'s Name, Date of Birth, Photo, Signature, or Address on existing PAN', 280.00, NULL, 'active', '2026-09-23 17:41:32'),
(3, 'Voter Card', 'New voter ID card registration and EPIC download', 100.00, NULL, 'active', '2026-09-23 17:41:33'),
(4, 'Voter Card Correction', 'Correction of Name, Age, Address, Photo, or Assembly Constituency in Voter ID (Form 8)', 100.00, NULL, 'active', '2026-09-23 17:41:33'),
(5, 'Voter Certified Copy Apply', 'Official certified copy / extract of Electoral Roll (Voter List) from Assam Election Office', 120.00, NULL, 'active', '2026-09-23 17:41:33'),
(6, 'Driving Licence', 'Learner and permanent driving licence application assistance', 7000.00, NULL, 'active', '2026-09-23 17:41:34'),
(7, 'Income Certificate', 'Assam revenue department income certificate application', 200.00, NULL, 'active', '2026-09-23 17:41:34'),
(8, 'Birth Certificate', 'Registration and issuance assistance for birth certificates', 1800.00, NULL, 'active', '2026-09-23 17:41:35'),
(9, 'Bank Account Opening', 'Zero balance savings account assistance', 300.00, NULL, 'active', '2026-09-23 17:41:35'),
(10, 'Caste Certificate', 'SC/ST/OBC caste certificate application', 300.00, NULL, 'active', '2026-09-23 17:41:35'),
(11, 'Mobile Number Update', 'Aadhaar / Portal mobile number linking assistance', 100.00, NULL, 'active', '2026-09-23 17:41:35'),
(12, 'ABHA Card Apply', 'Ayushman Bharat Health Account (ABHA) Digital Health Card generation, verification and card download', 50.00, NULL, 'active', '2026-09-23 17:41:36'),
(13, 'eShram Card', 'Ministry of Labour & Employment e-Shram National Database of Unorganised Workers card registration and UAN download', 60.00, NULL, 'active', '2026-09-23 17:41:36'),
(14, 'Jamabandi Land Document Apply', 'Assam Dharitree portal certified Jamabandi / Record of Rights (RoR) land document extract download', 150.00, NULL, 'active', '2026-09-23 17:41:36'),
(15, 'Land Revenue (Khajana)', 'Online Assam e-Khajana mouza land revenue assessment, payment and official payment receipt generation', 100.00, NULL, 'active', '2026-09-23 17:41:37'),
(16, 'CV / Resume Making', 'Professional curriculum vitae, job application resume, biodata formatting and PDF creation', 100.00, NULL, 'active', '2026-09-23 17:41:37'),
(17, 'Sewa Setu - Employment Exchange', 'Government of Assam Sewa Setu portal Employment Exchange online registration, renewal and certificate generation', 100.00, NULL, 'active', '2026-09-23 17:41:37'),
(18, 'Sewa Setu - Citizen Certificates', 'Assam Sewa Setu certificates: Bakijai, Permanent Resident Certificate (PRC), Senior Citizen, Non-Encumbrance', 150.00, NULL, 'active', '2026-09-23 17:41:37'),
(19, 'CMAAA Apply', 'Chief Minister\'s Atmanirbhar Asom Abhijan (CMAAA 2.0) online application, entrepreneurship subsidy & project documentation', 200.00, NULL, 'active', '2026-09-23 17:41:38'),
(20, 'Farmer Registry', 'Assam Farmer Registry official entry & documentation', 100.00, NULL, 'active', '2026-09-23 17:41:38'),
(21, 'PM Kisan', 'PM Kisan Samman Nidhi registration & eKYC verification', 100.00, NULL, 'active', '2026-09-23 17:41:39'),
(22, 'Scholarship Apply', 'National & Assam post-matric/pre-matric scholarship apply', 100.00, NULL, 'active', '2026-09-23 17:41:39'),
(23, 'College Admission', 'Online admission portal counseling & form filling', 100.00, NULL, 'active', '2026-09-23 17:41:39'),
(24, 'Job Apply', 'State and Central Govt online vacancy application', 100.00, NULL, 'active', '2026-09-23 17:41:39'),
(25, 'PM Surya Ghar Apply', 'PM Surya Ghar Muft Bijli Yojana rooftop solar application', 100.00, NULL, 'active', '2026-09-23 17:41:40'),
(26, 'Other Services', 'General government citizen queries and miscellaneous digital forms', 50.00, NULL, 'active', '2026-09-23 17:41:40');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('bank_account_holder', 'Digital Sewa Assam Admin', '2026-09-23 17:41:30'),
('bank_account_no', '389201948201', '2026-09-23 17:41:30'),
('bank_ifsc', 'SBIN0000123', '2026-09-23 17:41:30'),
('bank_name', 'State Bank of India', '2026-09-23 17:41:30'),
('platform_fee', '50.00', '2026-09-23 17:41:30'),
('sms_gateway_api_key', '', '2026-09-23 17:41:30'),
('sms_gateway_provider', 'fast2sms', '2026-09-23 17:41:30'),
('sms_sender_id', 'TXTIND', '2026-09-23 17:41:30'),
('upi_id', '9613167470@upi', '2026-09-23 17:41:30'),
('upi_qr_image', '', '2026-09-23 17:41:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `raw_password` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `auth_provider` varchar(30) DEFAULT 'email',
  `platform_fee_paid` tinyint(1) DEFAULT '0',
  `platform_fee_txn` varchar(100) DEFAULT NULL,
  `platform_fee_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `status` enum('active','blocked','processing','pending') DEFAULT 'processing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `mobile`, `email`, `password`, `raw_password`, `role`, `auth_provider`, `platform_fee_paid`, `platform_fee_txn`, `platform_fee_status`, `status`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', '9678104815', 'digitalsewa.csc@gmail.com', '$2y$12$gCQtCP0Er/1VyxeGKumX1urit3M7oDkb1yLFOtDsiISYMUVvKU.7W', NULL, 'admin', 'email', 0, NULL, 'pending', 'active', '2026-09-23 17:41:31', '2026-09-23 17:49:49'),
(2, 'Rupjyoti Sarma', '9876543211', 'rupjyoti@example.com', '$2y$12$y7s9pQQK8p9ObaMMIu6roehxwwfNt4wOyiNVHo84IhemEFlXjSue6', NULL, 'user', 'email', 0, NULL, 'pending', 'active', '2026-09-23 17:41:31', '2026-09-23 17:41:31'),
(3, 'Nilim kumar Gogoi', '9854433421', 'nilimkumargogoi03@gmail.com', '$2y$12$KbmfK9seq/f3uWhLIk7Bze83I6IjuC1FGpC/gfrvM1IqoY0dRgL9G', '63bec35690d39766a00a', 'user', 'google', 1, '231231284219', 'verified', 'active', '2026-09-23 18:22:51', '2026-09-23 18:23:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mobile_idx` (`mobile`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
