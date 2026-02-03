-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 03, 2026 at 07:22 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `reason` text NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `reason`, `appointment_time`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '2026-01-12 00:00:00', '', '00:00:00', 'pending', 'common cold', '2026-01-11 02:57:47', '2026-01-11 02:57:47'),
(2, 50, 1, '2026-01-20 00:00:00', '', '00:00:00', 'cancelled', 'headache and cold', '2026-01-19 14:28:11', '2026-01-19 15:12:18'),
(3, 50, 1, '2026-01-21 00:00:00', '', '00:00:00', 'completed', 'done', '2026-01-19 15:21:14', '2026-02-02 17:19:31'),
(18, 50, 8, '2026-02-03 00:00:00', '', '00:00:00', 'confirmed', 'heache and commmom', '2026-02-02 08:05:30', '2026-02-02 08:07:34'),
(19, 54, 8, '2026-02-04 00:00:00', '', '00:00:00', 'pending', 'Acne prone', '2026-02-02 15:07:06', '2026-02-02 15:07:06'),
(20, 50, 1, '2026-02-04 00:00:00', '', '00:00:00', 'confirmed', 'reasonnnnn', '2026-02-02 16:28:59', '2026-02-03 04:33:47'),
(21, 50, 1, '2026-02-04 00:00:00', '', '00:00:00', 'confirmed', 'reasonnnnn', '2026-02-02 16:31:00', '2026-02-02 16:32:41'),
(22, 50, 8, '2026-02-04 00:00:00', '', '00:00:00', 'pending', 'salommms d', '2026-02-02 16:37:10', '2026-02-02 16:39:05'),
(23, 56, 8, '2026-02-04 00:00:00', '', '00:00:00', 'pending', 'reasonnn  sam', '2026-02-02 16:50:28', '2026-02-02 16:50:28'),
(24, 50, 8, '2026-02-04 00:00:00', '', '00:00:00', 'pending', 'reasonnnn and', '2026-02-03 04:15:36', '2026-02-03 04:15:36'),
(25, 50, 8, '2026-02-05 10:12:00', '', '00:00:00', 'pending', 'handd andd', '2026-02-03 04:27:52', '2026-02-03 04:27:52'),
(26, 56, 8, '2026-02-05 10:15:00', '', '00:00:00', 'pending', 'asssssssssssssssssssssssssssssssssss', '2026-02-03 04:30:45', '2026-02-03 04:30:45');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`id`, `patient_id`, `appointment_id`, `amount`, `status`, `payment_method`, `payment_date`, `due_date`, `notes`, `created_at`, `updated_at`, `payment_status`) VALUES
(1, 50, 2, 300.00, 'paid', 'Cash', '2026-01-19 21:04:14', NULL, NULL, '2026-01-19 15:11:50', '2026-01-31 17:58:05', 'pending'),
(2, 50, 3, 300.00, 'paid', 'esewa', '2026-01-31 17:44:35', NULL, NULL, '2026-01-19 15:36:29', '2026-01-31 11:59:35', 'completed'),
(3, 50, 6, 300.00, '', 'cash', '2026-01-31 18:49:23', '2026-02-19', NULL, '2026-01-31 12:23:38', '2026-01-31 13:04:23', 'pending'),
(4, 50, 7, 300.00, 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:24:05', '2026-01-31 12:24:05', 'pending'),
(5, 50, 8, 300.00, 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:24:20', '2026-01-31 12:24:20', 'pending'),
(6, 50, 9, 300.00, 'unpaid', NULL, NULL, '2026-02-01', NULL, '2026-01-31 12:32:32', '2026-01-31 12:32:32', 'pending'),
(7, 50, 10, 300.00, 'unpaid', NULL, NULL, '2026-02-01', NULL, '2026-01-31 12:33:48', '2026-01-31 12:33:48', 'pending'),
(8, 50, 11, 300.00, 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:35:27', '2026-01-31 12:35:27', 'pending'),
(9, 50, 12, 300.00, 'unpaid', NULL, NULL, '2026-02-12', NULL, '2026-01-31 12:35:38', '2026-01-31 12:35:38', 'pending'),
(10, 50, 13, 300.00, 'paid', 'esewa', '2026-01-31 21:45:07', '2026-02-12', NULL, '2026-01-31 12:38:57', '2026-01-31 16:00:07', 'completed'),
(12, 50, 15, 300.00, 'paid', 'Cash', NULL, '2026-02-12', NULL, '2026-01-31 12:39:58', '2026-01-31 14:04:30', 'pending'),
(13, 50, 16, 300.00, 'paid', 'esewa', '2026-01-31 21:52:18', '2026-02-02', NULL, '2026-01-31 16:06:02', '2026-01-31 16:07:18', 'completed'),
(15, 50, 18, 500.00, 'paid', 'esewa', '2026-02-02 13:52:34', '2026-02-03', NULL, '2026-02-02 08:05:30', '2026-02-02 08:07:34', 'completed'),
(16, 54, 19, 500.00, 'unpaid', NULL, NULL, '2026-02-04', NULL, '2026-02-02 15:07:06', '2026-02-02 15:07:06', 'pending'),
(17, 50, 20, 300.00, 'paid', 'esewa', '2026-02-03 10:18:47', '2026-02-04', NULL, '2026-02-02 16:28:59', '2026-02-03 04:33:47', 'completed'),
(18, 50, 21, 300.00, 'paid', 'esewa', '2026-02-02 22:17:41', '2026-02-04', NULL, '2026-02-02 16:31:00', '2026-02-02 16:32:41', 'completed'),
(19, 50, 22, 500.00, '', 'cash', '2026-02-02 22:22:16', '2026-02-03', NULL, '2026-02-02 16:37:10', '2026-02-02 16:37:16', 'pending'),
(20, 56, 23, 500.00, '', 'cash', '2026-02-02 22:35:37', '2026-02-04', NULL, '2026-02-02 16:50:28', '2026-02-02 16:50:37', 'pending'),
(21, 50, 24, 500.00, '', 'cash', '2026-02-03 10:00:40', '2026-02-04', NULL, '2026-02-03 04:15:36', '2026-02-03 04:15:40', 'pending'),
(22, 50, 25, 500.00, 'unpaid', NULL, NULL, '2026-02-05', NULL, '2026-02-03 04:27:52', '2026-02-03 04:27:52', 'pending'),
(23, 56, 26, 500.00, '', 'cash', '2026-02-03 10:15:49', '2026-02-05', NULL, '2026-02-03 04:30:45', '2026-02-03 04:30:49', 'pending');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `available_days`, `available_time_start`, `available_time_end`, `created_at`, `updated_at`) VALUES
(1, 11, 'General Physician', 'MBBS', 3, 300.00, 'Sun,Mon,Tue,Wed,Thu,fri', '09:00:00', '17:00:00', '2026-01-07 19:51:33', '2026-01-07 19:51:33'),
(4, 40, 'Internal Medicine', 'MBBS,MD', 5, 500.00, NULL, NULL, NULL, '2026-01-31 17:11:52', '2026-01-31 17:18:35'),
(5, 41, 'Orthopedics', 'MBBS,MD Orthopedics', 6, 600.00, NULL, NULL, NULL, '2026-01-31 17:25:03', '2026-01-31 17:27:45'),
(6, 42, 'Cardiologist', 'MBBS, DM', 9, 800.00, NULL, NULL, NULL, '2026-01-31 17:31:18', '2026-01-31 17:33:13'),
(7, 43, 'Opthamology', 'MBBS,MD', 4, 500.00, NULL, NULL, NULL, '2026-01-31 17:38:40', '2026-02-03 05:32:25'),
(8, 44, 'Dermatologist', 'MBBS,MD Dermatology', 4, 500.00, NULL, NULL, NULL, '2026-01-31 17:42:37', '2026-01-31 17:43:39');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `visit_date`, `diagnosis`, `treatment`, `prescription`, `notes`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '2026-01-08', 'Acute Upper Respiratory Infection', 'Monitor blood pressure weekly\r\nLifestyle modifications: low-sodium diet, 30 minutes of moderate exercise daily\r\nFollow-up visit scheduled in 4 weeks', 'Medication: Lisinopril\r\nDosage: 10 mg\r\nFrequency: Once daily\r\nDuration: 30 days\r\nRefills: 2\r\nInstructions: Take in the morning, with or without food', 'Patient reports no adverse reactions to current medications', '2026-01-18 19:50:17', '2026-01-18 19:50:17'),
(2, 56, 8, '2026-02-04', 'acne prone', 'antibiotics', 'avoid oily food', 'internal observations', '2026-02-02 17:04:00', '2026-02-02 17:04:00'),
(3, 50, 1, '2026-02-02', 'gastric', 'avoid junk food', 'eat healthy food', 'notes', '2026-02-02 17:20:34', '2026-02-02 17:20:34');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `phone`, `date_of_birth`, `gender`, `address`, `emergency_contact`, `emergency_phone`, `medical_history`, `blood_group`, `allergies`, `age`) VALUES
(1, 0, '9823607844', '2000-02-28', 'female', 'Bhaktapur', '9847512638', '9848896230', 'Low blood pressure', 'O+', 'walnut allergy', '25'),
(6, 26, '9843607844', '2000-02-11', 'female', 'Bhaktapur', '', '9823604520', 'Sinus', 'O-', 'Walnut', NULL),
(50, 15, '9823607845', '1997-06-10', 'female', 'Ghalate,Bhaktapur', '', '9848896233', 'Hypercholesterolemia: Diagnosed 2 years ago; treated with simvastatin 40 mg daily.', 'AB+', 'Penicillin', NULL),
(51, 9, '9823607845', '1997-06-10', 'female', 'Ghalate,Bhaktapur', '', '9848896233', 'Hypercholesterolemia: Diagnosed 2 years ago; treated with simvastatin 40 mg daily.', 'AB+', 'Penicillin', NULL),
(53, 46, '9856231208', '0000-00-00', '', '', '', '', '', '', '', NULL),
(54, 47, '9856235610', '0000-00-00', 'other', 'Not provided', '', 'Not provided', '', NULL, NULL, NULL),
(56, 14, '9856231205', '2000-02-08', 'female', 'Sanepa, Lalitpur', '', '9848896201', '', 'AB-', 'Walnut allergy', NULL),
(62, 51, '9856231256', '0000-00-00', 'other', 'Not provided', '', 'Not provided', '', NULL, NULL, NULL),
(64, 52, '9823607842', '0000-00-00', 'other', 'Not provided', '', 'Not provided', '', NULL, NULL, NULL),
(65, 53, '9856231256', '0000-00-00', 'other', 'Not provided', '', 'Not provided', '', NULL, NULL, NULL),
(66, 57, '9823607560', '0000-00-00', '', '', '', '', '', '', '', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `billing_id`, `gateway`, `transaction_uuid`, `gateway_ref_id`, `amount`, `status`, `raw_response`, `created_at`, `updated_at`) VALUES
(32, 13, 'esewa', 'ESEWA-13-1769875567', NULL, 300.00, 'completed', '{\"transaction_code\":\"000E00N\",\"status\":\"COMPLETE\",\"total_amount\":\"300.0\",\"transaction_uuid\":\"ESEWA-13-1769875567\",\"product_code\":\"EPAYTEST\",\"signed_field_names\":\"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names\",\"signature\":\"y3P49TATg3ugdsz2qteSP2rEVzSc7ewnRsln7TxIThg=\"}', '2026-01-31 16:06:07', '2026-01-31 16:07:18'),
(34, 15, 'esewa', 'ESEWA-15-1770019537', NULL, 500.00, 'completed', '{\"transaction_code\":\"000E0RO\",\"status\":\"COMPLETE\",\"total_amount\":\"500.0\",\"transaction_uuid\":\"ESEWA-15-1770019537\",\"product_code\":\"EPAYTEST\",\"signed_field_names\":\"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names\",\"signature\":\"dqG020TMfSjYz3DLCvnKo+ZCXOc7lmSZHR+pVIyL6AM=\"}', '2026-02-02 08:05:37', '2026-02-02 08:07:34'),
(35, 16, 'khalti', 'KHALTI-6980bdbd5e2de', '68ZEbD4dCKkndhwsUeZYNd', 500.00, 'pending', NULL, '2026-02-02 15:07:41', '2026-02-02 15:07:41'),
(36, 18, 'esewa', 'ESEWA-18-1770049875', NULL, 300.00, 'completed', '{\"transaction_code\":\"000E0YM\",\"status\":\"COMPLETE\",\"total_amount\":\"300.0\",\"transaction_uuid\":\"ESEWA-18-1770049875\",\"product_code\":\"EPAYTEST\",\"signed_field_names\":\"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names\",\"signature\":\"04Y/LRgzjWMqGcLy9vPqVAONefNotNgfGZdwlwm2fEY=\"}', '2026-02-02 16:31:15', '2026-02-02 16:32:41'),
(37, 19, 'cash', 'CASH-6980d2bc413e1', NULL, 500.00, 'pending', NULL, '2026-02-02 16:37:16', '2026-02-02 16:37:16'),
(38, 20, 'cash', 'CASH-6980d5dd3c8c2', NULL, 500.00, 'pending', NULL, '2026-02-02 16:50:37', '2026-02-02 16:50:37'),
(39, 21, 'cash', 'CASH-6981766c04fce', NULL, 500.00, 'pending', NULL, '2026-02-03 04:15:40', '2026-02-03 04:15:40'),
(40, 23, 'cash', 'CASH-698179f9cd817', NULL, 500.00, 'pending', NULL, '2026-02-03 04:30:49', '2026-02-03 04:30:49'),
(41, 17, 'esewa', 'ESEWA-17-1770093147', NULL, 300.00, 'completed', '{\"transaction_code\":\"000E11J\",\"status\":\"COMPLETE\",\"total_amount\":\"300.0\",\"transaction_uuid\":\"ESEWA-17-1770093147\",\"product_code\":\"EPAYTEST\",\"signed_field_names\":\"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names\",\"signature\":\"TKb8uT/piljpeh7DELU2uviFpVI4xAKeBvNGoG3AQwc=\"}', '2026-02-03 04:32:27', '2026-02-03 04:33:47');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `created_at`, `updated_at`) VALUES
(9, 'Archana Silwal', 'admin@archana.com', '$2y$10$McDjKifDVTS5M8Z1drxsUOqEo6gZSkVBrboEz49AA.l6uhnj97Obq', 'admin', '9818974955', '2026-01-07 06:54:21', '2026-01-07 16:31:35'),
(11, 'Amrita Singh', 'amritacc@gmail.com', '$2y$10$yqH15pTOcqEqc.QlQSlNFeDLJxAU3W2h91.sMiUbPaJrGDXAh91Vm', 'doctor', '9855963277', '2026-01-07 13:27:53', '2026-02-02 15:31:32'),
(14, 'Sarala Silwal', 'sarala22@gmail.com', '$2y$10$6a4lZLUC5d58A.AknIE/uuvYax9OvHuNGZvb25cxL5J9POcJuN6Ce', 'patient', '9823607844', '2026-01-07 19:13:48', '2026-01-11 19:27:12'),
(15, 'Rachana Silwal', 'rachana12@gmail.com', '$2y$10$GIgyI7aYOl6X08LGhVnmsecTpPKi52gMOKDXBsd7WspZvBf6PmG2K', 'patient', '9823607845', '2026-01-08 16:08:29', '2026-01-18 12:50:34'),
(37, 'Shreeya Shrestha', 'shresthasree12@gmail.com', '$2y$10$cdgOCssUXebp1ipQELB75uZ7eRY176FWxBnRIJ1Zz8ahrjuKaPpKm', 'patient', '9818974953', '2026-01-19 16:41:06', '2026-01-19 16:41:06'),
(40, 'Archana Sharma', 'archana99@gmail.com', '$2y$10$l78TRwK0Y/Z0uY9UotDdbuKx34IEEJgLlBb.pFT1MwOUX39oRJUG2', 'doctor', '9818974955', '2026-01-31 17:11:52', '2026-02-02 15:31:22'),
(41, 'Sabin Shrestha', 'sabin99@gmail.com', '$2y$10$Qv089YnjkHBYRDWwa7vCW.fJGy/3kp3HbNXDXclNmTIOUZwR04Nw2', 'doctor', '9823000005', '2026-01-31 17:25:03', '2026-02-02 15:31:10'),
(42, 'Rajesh Kumar', 'rajeshac@gmail.com', '$2y$10$a58f22b4vpcvLySAow49le0aMJA.bj4mkds8tRiFilMqZH2K50luO', 'doctor', '9800000021', '2026-01-31 17:31:18', '2026-02-02 15:30:58'),
(43, 'Priya Patel', 'priyaac@gmail.com', '$2y$10$qWFo7KzlvRuFfLf597c5eO75cGNPzwAcivPiAyNIBsdZkyfIDDJlK', 'doctor', '9800000025', '2026-01-31 17:38:40', '2026-02-01 09:52:35'),
(44, 'Salon Basnet', 'salonac@gmail.com', '$2y$10$./kJ3tbTAfVYWL5y.7woxO2JtOW2EvlHjzaWsC59InNltMc8/o3Sy', 'doctor', '9856231203', '2026-01-31 17:42:37', '2026-02-02 15:30:44'),
(50, 'Samrina Duwal', 'sam123@gmail.com', '$2y$10$FeoGvHKE2ajAkbyxT8Yk.eSQodN7cucI7EXtyd75IAEfZY3VxNZja', 'patient', '9812457896', '2026-02-03 02:51:49', '2026-02-03 02:51:49'),
(54, 'Shreeya Awal', 'shreeyaa1@gmail.com', '$2y$10$WJlWj/pnehLG6oUfV/fIV.Ka/0MpXuxW5lgg3YHyaEFLC0aoaCR0m', 'patient', '9823607822', '2026-02-03 05:09:06', '2026-02-03 05:09:06'),
(57, 'Ramila Rai', 'ramila12@gmail.com', '$2y$10$ZViFFLbcotXUx5HtIaWxl.ep3FIka0hkImdze/EiFvDPrBtRdejCK', 'patient', '9823607560', '2026-02-03 05:29:29', '2026-02-03 05:29:29');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

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
