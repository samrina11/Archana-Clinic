-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 31, 2026 at 04:06 PM
-- Server version: 10.4.8-MariaDB
-- PHP Version: 7.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_appointments`
--

CREATE TABLE `admin_appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `scheduled_by` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `reason` text NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `reason`, `appointment_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '2026-01-12', '', '00:00:00', 'pending', 'common cold', '2026-01-11 02:57:47', '2026-01-11 02:57:47'),
(2, 50, 1, '2026-01-20', '', '00:00:00', 'cancelled', 'headache and cold', '2026-01-19 14:28:11', '2026-01-19 15:12:18'),
(3, 50, 1, '2026-01-21', '', '00:00:00', 'confirmed', 'headache and backpain', '2026-01-19 15:21:14', '2026-01-19 15:36:29'),
(4, 50, 1, '2026-02-27', '', '00:00:00', 'cancelled', 'Hello World [Cancelled: Fiilllasd', '2026-01-31 11:24:50', '2026-01-31 12:48:56'),
(5, 50, 1, '2026-02-01', '', '00:00:00', 'cancelled', 'I have ajsldkjalsd', '2026-01-31 11:59:56', '2026-01-31 13:08:30'),
(6, 50, 1, '2026-02-19', '', '00:00:00', 'cancelled', 'HSjdhakjsdhsd asdhkajshd ajshd kjas', '2026-01-31 12:23:38', '2026-01-31 13:06:30'),
(7, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'asdasdasd as da sd as d as d', '2026-01-31 12:24:05', '2026-01-31 12:24:05'),
(8, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'asdasdasd as da sd as d as d', '2026-01-31 12:24:20', '2026-01-31 12:24:20'),
(9, 50, 1, '2026-02-01', '', '00:00:00', 'pending', 'jhkjhkjhakjs hkjahskjdhak jsdkjahs dk', '2026-01-31 12:32:32', '2026-01-31 12:32:32'),
(10, 50, 1, '2026-02-01', '', '00:00:00', 'pending', 'jslkj dlkajsdlkja lsk jdla sldkjalks dlas', '2026-01-31 12:33:48', '2026-01-31 12:33:48'),
(11, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'kajhskjdhkjhaksjhdkj as', '2026-01-31 12:35:27', '2026-01-31 12:35:27'),
(12, 50, 1, '2026-02-12', '', '00:00:00', 'completed', 'kajhskjdhkjhaksjhdkj as', '2026-01-31 12:35:38', '2026-01-31 14:10:11'),
(13, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'kajhskjdhkjhaksjhdkj as', '2026-01-31 12:38:57', '2026-01-31 12:38:57'),
(14, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'kajhskjdhkjhaksjhdkj as', '2026-01-31 12:39:41', '2026-01-31 12:39:41'),
(15, 50, 1, '2026-02-12', '', '00:00:00', 'pending', 'kajhskjdhkjhaksjhdkj as', '2026-01-31 12:39:58', '2026-01-31 12:39:58');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','partial') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `appointment_id`, `amount`, `status`, `payment_method`, `payment_date`, `due_date`, `notes`, `created_at`, `updated_at`, `payment_status`) VALUES
(1, 50, 2, '300.00', 'unpaid', 'cash', '2026-01-19 21:04:14', NULL, NULL, '2026-01-19 15:11:50', '2026-01-31 12:20:14', 'pending'),
(2, 50, 3, '300.00', 'paid', 'esewa', '2026-01-31 17:44:35', NULL, NULL, '2026-01-19 15:36:29', '2026-01-31 11:59:35', 'completed'),
(3, 50, 6, '300.00', '', 'cash', '2026-01-31 18:49:23', '2026-02-19', NULL, '2026-01-31 12:23:38', '2026-01-31 13:04:23', 'pending'),
(4, 50, 7, '300.00', 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:24:05', '2026-01-31 12:24:05', 'pending'),
(5, 50, 8, '300.00', 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:24:20', '2026-01-31 12:24:20', 'pending'),
(6, 50, 9, '300.00', 'unpaid', NULL, NULL, '2026-02-01', NULL, '2026-01-31 12:32:32', '2026-01-31 12:32:32', 'pending'),
(7, 50, 10, '300.00', 'unpaid', NULL, NULL, '2026-02-01', NULL, '2026-01-31 12:33:48', '2026-01-31 12:33:48', 'pending'),
(8, 50, 11, '300.00', 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:35:27', '2026-01-31 12:35:27', 'pending'),
(9, 50, 12, '300.00', 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:35:38', '2026-01-31 12:35:38', 'pending'),
(10, 50, 13, '300.00', 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:38:57', '2026-01-31 12:38:57', 'pending'),
(12, 50, 15, '300.00', 'paid', 'Cash', NULL, '2026-02-12', NULL, '2026-01-31 12:39:58', '2026-01-31 14:04:30', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `available_days` varchar(200) DEFAULT NULL,
  `available_time_start` time DEFAULT NULL,
  `available_time_end` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `available_days`, `available_time_start`, `available_time_end`, `created_at`, `updated_at`) VALUES
(1, 11, 'General Physician', 'MBBS', 3, '300.00', 'Sun,Mon,Tue,Wed,Thu,fri', '09:00:00', '17:00:00', '2026-01-07 19:51:33', '2026-01-07 19:51:33'),
(2, 38, 'General', 'MBBS', 3, '500.00', NULL, NULL, NULL, '2026-01-31 14:43:04', '2026-01-31 14:57:54');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '1=Monday ... 7=Sunday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_duration_min` smallint(6) DEFAULT 15,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`, `slot_duration_min`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '09:00:00', '17:00:00', 15, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12'),
(2, 1, 2, '09:00:00', '17:00:00', 15, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12'),
(3, 1, 3, '09:00:00', '17:00:00', 15, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12'),
(4, 1, 4, '09:00:00', '17:00:00', 15, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12'),
(5, 1, 5, '09:00:00', '17:00:00', 15, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12'),
(6, 1, 6, '09:00:00', '14:00:00', 20, 1, '2026-01-31 11:22:12', '2026-01-31 11:22:12');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_unavailable_dates`
--

CREATE TABLE `doctor_unavailable_dates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `unavailable_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `whole_day` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `doctor_unavailable_dates`
--

INSERT INTO `doctor_unavailable_dates` (`id`, `doctor_id`, `unavailable_date`, `reason`, `whole_day`, `start_time`, `end_time`, `created_at`) VALUES
(1, 1, '2026-02-16', 'Public holiday - Maha Shivaratri', 1, NULL, NULL, '2026-01-31 11:22:12'),
(2, 1, '2026-02-20', 'Conference - morning only', 0, '09:00:00', '13:00:00', '2026-01-31 11:22:12'),
(3, 1, '2026-03-05', 'CME / Training - whole day', 1, NULL, NULL, '2026-01-31 11:22:12'),
(4, 1, '2026-04-14', 'Nepali New Year', 1, NULL, NULL, '2026-01-31 11:22:12'),
(5, 1, '2026-05-01', 'Labour Day + Personal leave', 1, NULL, NULL, '2026-01-31 11:22:12'),
(6, 1, '2026-08-15', 'Annual family vacation', 1, NULL, NULL, '2026-01-31 11:22:12');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `visit_date`, `diagnosis`, `treatment`, `prescription`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '2026-01-08', 'Acute Upper Respiratory Infection', 'Monitor blood pressure weekly\r\nLifestyle modifications: low-sodium diet, 30 minutes of moderate exercise daily\r\nFollow-up visit scheduled in 4 weeks', 'Medication: Lisinopril\r\nDosage: 10 mg\r\nFrequency: Once daily\r\nDuration: 30 days\r\nRefills: 2\r\nInstructions: Take in the morning, with or without food', 'Patient reports no adverse reactions to current medications', '2026-01-18 19:50:17', '2026-01-18 19:50:17');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `address` text NOT NULL,
  `emergency_contact` varchar(100) NOT NULL,
  `emergency_phone` varchar(20) NOT NULL,
  `medical_history` text DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `age` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `name`, `password`, `phone`, `date_of_birth`, `gender`, `address`, `emergency_contact`, `emergency_phone`, `medical_history`, `blood_group`, `allergies`, `age`) VALUES
(1, 0, 'Sarala Suwal', '$2y$10$Okt5k4LbYAU2154cyx9URuXcyjhpc4tDvhuLmYpxGq963yo00HJqe', '9823607844', '2000-02-28', 'female', 'Bhaktapur', '9847512638', '9848896230', 'Low blood pressure', 'O+', 'walnut allergy', '25'),
(6, 26, '', '', '9843607844', '2000-02-11', 'female', 'Bhaktapur', '', '9823604520', 'Sinus', 'O-', 'Walnut', NULL),
(49, 11, '', '', '9823607845', '1998-06-02', 'female', 'Ghalate,Bhaktapur', '', '9856230122', 'Hypercholesterolemia: Diagnosed 2 years ago; treated with simvastatin 40 mg daily.', 'AB+', 'Penicillin', NULL),
(50, 15, '', '', '9823607845', '1997-06-10', 'female', 'Ghalate,Bhaktapur', '', '9848896233', 'Hypercholesterolemia: Diagnosed 2 years ago; treated with simvastatin 40 mg daily.', 'AB+', 'Penicillin', NULL),
(51, 9, '', '', '9823607845', '1997-06-10', 'female', 'Ghalate,Bhaktapur', '', '9848896233', 'Hypercholesterolemia: Diagnosed 2 years ago; treated with simvastatin 40 mg daily.', 'AB+', 'Penicillin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `gateway` enum('esewa','khalti','cash') NOT NULL,
  `transaction_uuid` varchar(100) NOT NULL,
  `gateway_ref_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `raw_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `billing_id`, `gateway`, `transaction_uuid`, `gateway_ref_id`, `amount`, `status`, `raw_response`, `created_at`, `updated_at`) VALUES
(1, 2, 'khalti', 'KHALTI-697dea7c4c9b7', NULL, '300.00', 'pending', NULL, '2026-01-31 11:41:48', '2026-01-31 11:41:48'),
(2, 2, 'khalti', 'KHALTI-697deada331c7', NULL, '300.00', 'pending', NULL, '2026-01-31 11:43:22', '2026-01-31 11:43:22'),
(3, 2, 'khalti', 'KHALTI-697deb22c70cb', NULL, '300.00', 'pending', NULL, '2026-01-31 11:44:34', '2026-01-31 11:44:34'),
(4, 2, 'khalti', 'KHALTI-697deba741e6a', NULL, '300.00', 'pending', NULL, '2026-01-31 11:46:47', '2026-01-31 11:46:47'),
(5, 2, 'khalti', 'KHALTI-697debb4317ce', NULL, '300.00', 'pending', NULL, '2026-01-31 11:47:00', '2026-01-31 11:47:00'),
(6, 2, 'khalti', 'KHALTI-697dec27c7f57', NULL, '300.00', 'pending', NULL, '2026-01-31 11:48:55', '2026-01-31 11:48:55'),
(7, 2, 'esewa', 'ESEWA-2-1769860139', NULL, '300.00', 'pending', NULL, '2026-01-31 11:48:59', '2026-01-31 11:48:59'),
(8, 2, 'esewa', 'ESEWA-2-1769860543', NULL, '300.00', 'completed', '{\"transaction_code\":\"000DZZB\",\"status\":\"COMPLETE\",\"total_amount\":\"300.0\",\"transaction_uuid\":\"ESEWA-2-1769860543\",\"product_code\":\"EPAYTEST\",\"signed_field_names\":\"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names\",\"signature\":\"6zoqzCH9f2ivJ+6J7rhuWoI4qvZCTuKCrScyWzhn0vA=\"}', '2026-01-31 11:55:43', '2026-01-31 11:59:35'),
(9, 1, 'khalti', 'KHALTI-697df38721d58', NULL, '300.00', 'pending', NULL, '2026-01-31 12:20:23', '2026-01-31 12:20:23'),
(10, 1, 'esewa', 'ESEWA-1-1769862030', NULL, '300.00', 'pending', NULL, '2026-01-31 12:20:30', '2026-01-31 12:20:30'),
(11, 1, 'khalti', 'KHALTI-697df3982718a', NULL, '300.00', 'pending', NULL, '2026-01-31 12:20:40', '2026-01-31 12:20:40'),
(12, 1, 'khalti', 'KHALTI-697df3d2d572a', NULL, '300.00', 'pending', NULL, '2026-01-31 12:21:38', '2026-01-31 12:21:38'),
(13, 3, 'khalti', 'KHALTI-697df44c9ae5a', NULL, '300.00', 'pending', NULL, '2026-01-31 12:23:40', '2026-01-31 12:23:40'),
(14, 4, 'esewa', 'ESEWA-4-1769862248', NULL, '300.00', 'pending', NULL, '2026-01-31 12:24:08', '2026-01-31 12:24:08'),
(15, 5, 'khalti', 'KHALTI-697df47ad5ce7', NULL, '300.00', 'pending', NULL, '2026-01-31 12:24:26', '2026-01-31 12:24:26'),
(16, 5, 'khalti', 'KHALTI-697df555600b2', NULL, '300.00', 'pending', NULL, '2026-01-31 12:28:05', '2026-01-31 12:28:05'),
(17, 5, 'khalti', 'KHALTI-697df56a8dd08', 'WeKwc4Z5zSsJPMUA6kTHAQ', '300.00', 'pending', NULL, '2026-01-31 12:28:26', '2026-01-31 12:28:26'),
(18, 6, 'khalti', 'KHALTI-697df662da3c0', NULL, '300.00', 'pending', NULL, '2026-01-31 12:32:34', '2026-01-31 12:32:34'),
(19, 7, 'khalti', 'KHALTI-697df6aea29b7', NULL, '300.00', 'pending', NULL, '2026-01-31 12:33:50', '2026-01-31 12:33:50'),
(20, 7, 'khalti', 'KHALTI-697df6b901a7b', NULL, '300.00', 'pending', NULL, '2026-01-31 12:34:01', '2026-01-31 12:34:01'),
(21, 7, 'esewa', 'ESEWA-7-1769862845', NULL, '300.00', 'pending', NULL, '2026-01-31 12:34:05', '2026-01-31 12:34:05'),
(22, 7, 'khalti', 'KHALTI-697df6c230853', NULL, '300.00', 'pending', NULL, '2026-01-31 12:34:10', '2026-01-31 12:34:10'),
(23, 3, 'khalti', 'KHALTI-697df6c5c400e', NULL, '300.00', 'pending', NULL, '2026-01-31 12:34:13', '2026-01-31 12:34:13'),
(24, 8, 'esewa', 'ESEWA-8-1769862931', NULL, '300.00', 'pending', NULL, '2026-01-31 12:35:31', '2026-01-31 12:35:31'),
(25, 3, 'khalti', 'KHALTI-697dfdcfc22f4', 'A4A63gZVRM4rgPsg7g3kmk', '300.00', 'pending', NULL, '2026-01-31 13:04:15', '2026-01-31 13:04:16'),
(26, 3, 'cash', 'CASH-697dfdd70e7c6', NULL, '300.00', 'pending', NULL, '2026-01-31 13:04:23', '2026-01-31 13:04:23'),
(27, 12, 'esewa', 'ESEWA-12-1769865119', NULL, '300.00', 'pending', NULL, '2026-01-31 13:11:59', '2026-01-31 13:11:59');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `clinic_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `working_hours` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `ticket_number` varchar(50) NOT NULL,
  `status` enum('active','used','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','receptionist','patient') NOT NULL,
  `phone` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `created_at`, `updated_at`) VALUES
(9, 'Archana Silwal', 'admin@archana.com', '$2y$10$McDjKifDVTS5M8Z1drxsUOqEo6gZSkVBrboEz49AA.l6uhnj97Obq', 'admin', '9818974955', '2026-01-07 06:54:21', '2026-01-07 16:31:35'),
(11, 'Dr. Amrita Singh', 'amritacc@gmailcom', '$2y$10$yqH15pTOcqEqc.QlQSlNFeDLJxAU3W2h91.sMiUbPaJrGDXAh91Vm', 'doctor', '9855963277', '2026-01-07 13:27:53', '2026-01-18 17:16:12'),
(14, 'Sarala Silwal', 'sarala22@gmail.com', '$2y$10$6a4lZLUC5d58A.AknIE/uuvYax9OvHuNGZvb25cxL5J9POcJuN6Ce', 'patient', '9823607844', '2026-01-07 19:13:48', '2026-01-11 19:27:12'),
(15, 'Rachana Silwal', 'rachana12@gmail.com', '$2y$10$GIgyI7aYOl6X08LGhVnmsecTpPKi52gMOKDXBsd7WspZvBf6PmG2K', 'patient', '9823607845', '2026-01-08 16:08:29', '2026-01-18 12:50:34'),
(16, 'Shiven Prajapati', 'shiven24@gmail.com', '$2y$10$hhvqjtKHHZjLMWOtYWyOwujWOULAP46MfaYE6i7b7zEge1lldQtMO', 'patient', '9823607825', '2026-01-08 18:18:14', '2026-01-08 18:18:14'),
(20, 'Dr. Rajesh Kumar', 'drajesh@archana.com', '$2y$10$pd3xSPSqxIjaO887G0pJKO5kejO1W1wAIdZgaZKbQZlnfFB448HGa', 'doctor', '9876543211', '2026-01-10 07:39:40', '2026-01-10 07:39:40'),
(22, 'Dr. Priya Patel', 'dpriya@archana.com', '$2y$10$lTwrIhlsBED1dB1jN1Nf0ePrQAJPAun.tDzag6TiOHZOeVDPmI8qK', 'doctor', '9826543215', '2026-01-10 08:02:54', '2026-01-10 08:02:54'),
(23, 'Roshni Duwal', 'roshd12@gmail.com', '$2y$10$YYSh4cQu2VGpIb8hDjSG5.4Y6OwNBLUqrJFyl2xMQjeouMuxZTrsy', 'patient', '9818974953', '2026-01-10 13:45:17', '2026-01-10 13:45:17'),
(25, 'Samir Hemba', 'samir@gmail.com', '$2y$10$3liih50jDAHH4Ric4nfWyu2qspwyCFNO8Cj.f1KKZOm7tvkPBaSo2', 'patient', '9856230142', '2026-01-11 02:20:00', '2026-01-11 02:20:00'),
(26, 'Sarala Duwal', 'sarala12@gmail.com', '$2y$10$VQc75hbzX1Tgxjg9J8D9EONGGNMkia8./tNapUyRA37yWkRZZZUm.', 'patient', '9843825810', '2026-01-11 02:27:45', '2026-01-11 02:27:45'),
(27, 'Simran Malla', 'simran12@gmail.com', '$2y$10$ABnLLO6tcF1aGKptHB3aeeiEMHYeGvgJhw.uwgc9Jz4u2soGatxP6', 'patient', '982360733', '2026-01-11 19:43:43', '2026-01-11 19:43:43'),
(31, 'Prajwol Hemba', 'phemba1@gmail.com', '$2y$10$eH/HHvOyVxeYCLUeOcq5X.84S7QA/qepCuq8uhliMRoP58NT8FMAS', 'patient', '9823602520', '2026-01-11 20:41:24', '2026-01-11 20:41:24'),
(32, 'Bibek Awal', 'awalb1@gmail.com', '$2y$10$lPwlDPiNgmnK0nK/XEE4oOYIQVs4WymUSW7VbpO4C6fSzawtLZzlG', 'patient', '9823602582', '2026-01-11 20:53:45', '2026-01-11 20:53:45'),
(34, 'Laxmi Shrestha', 'laxmi12@gmail.com', '$2y$10$090fu0MIu33hIe41lvbzLuUyBXBiqDmmMV2cqqKzVNjVY3Y1kQAyu', 'patient', '9823607320', '2026-01-11 21:16:23', '2026-01-11 21:16:23'),
(36, 'Dinesh Duwal', 'dinesh@gmail.com', '$2y$10$CiigH7.D215BRqTgPirX/ezho5Y35MtZ6gHPNIJ2od1hBnnccekTu', 'patient', '9823607332', '2026-01-11 21:22:43', '2026-01-11 21:22:43'),
(37, 'Shreeya Shrestha', 'shresthasree12@gmail.com', '$2y$10$cdgOCssUXebp1ipQELB75uZ7eRY176FWxBnRIJ1Zz8ahrjuKaPpKm', 'patient', '9818974953', '2026-01-19 16:41:06', '2026-01-19 16:41:06'),
(38, 'Test Doctor', 'test.doc@gmail.com', '$2y$10$OQ5RpiHYVx/WheligS9N/.mEJ9dNpYfUMlI/QawWHWUkCOEpaU2zi', 'doctor', '9843742374', '2026-01-31 14:43:04', '2026-01-31 14:43:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_appointments`
--
ALTER TABLE `admin_appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `scheduled_by` (`scheduled_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_day_per_doctor` (`doctor_id`,`day_of_week`);

--
-- Indexes for table `doctor_unavailable_dates`
--
ALTER TABLE `doctor_unavailable_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_per_doctor` (`doctor_id`,`unavailable_date`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_id` (`billing_id`),
  ADD KEY `transaction_uuid` (`transaction_uuid`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_appointments`
--
ALTER TABLE `admin_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `doctor_unavailable_dates`
--
ALTER TABLE `doctor_unavailable_dates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_appointments`
--
ALTER TABLE `admin_appointments`
  ADD CONSTRAINT `admin_appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_appointments_ibfk_3` FOREIGN KEY (`scheduled_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_unavailable_dates`
--
ALTER TABLE `doctor_unavailable_dates`
  ADD CONSTRAINT `fk_unavailable_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_billing_fk` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
