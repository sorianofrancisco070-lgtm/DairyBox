-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2026 at 06:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dairybox_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module` varchar(60) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `module`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', 'Auth', NULL, '::1', '2026-07-27 15:08:40'),
(2, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-27 15:19:28'),
(3, 2, 'Login', 'Auth', NULL, '::1', '2026-07-27 15:19:55'),
(4, 2, 'Logout', 'Auth', NULL, '::1', '2026-07-27 15:25:32'),
(5, 3, 'Login', 'Auth', NULL, '::1', '2026-07-27 15:26:06'),
(6, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-27 15:37:11'),
(7, 4, 'Login', 'Auth', NULL, '::1', '2026-07-27 15:37:42'),
(8, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 01:31:53'),
(9, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 01:32:09'),
(10, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 01:32:21'),
(11, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 01:32:25'),
(12, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 01:32:51'),
(13, 1, 'Add Buffalo', 'Buffaloes', 'Tag: 143', NULL, '2026-07-30 01:36:21'),
(14, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 01:41:51'),
(15, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 01:42:08'),
(16, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 01:44:50'),
(17, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 01:45:10'),
(18, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 02:11:31'),
(19, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 02:12:02'),
(20, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 02:56:26'),
(21, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 02:57:02'),
(22, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 02:57:17'),
(23, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 04:05:41'),
(24, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 04:24:27'),
(25, 4, 'Login', 'Auth', NULL, '::1', '2026-07-30 04:31:38'),
(26, 4, 'Logout', 'Auth', NULL, '::1', '2026-07-30 04:32:34'),
(27, 4, 'Login', 'Auth', NULL, '::1', '2026-07-30 04:32:47'),
(28, 4, 'Update Buffalo', 'Buffaloes', 'Tag: BUF-004', NULL, '2026-07-30 04:38:37'),
(29, 4, 'Update Buffalo', 'Buffaloes', 'Tag: BUF-004', NULL, '2026-07-30 04:39:05'),
(30, 4, 'Update Buffalo', 'Buffaloes', 'Tag: 143', NULL, '2026-07-30 04:54:59'),
(31, 4, 'Update Buffalo', 'Buffaloes', 'Tag: BUF-004', NULL, '2026-07-30 04:55:11'),
(32, 4, 'Logout', 'Auth', NULL, '::1', '2026-07-30 05:00:23'),
(33, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 05:00:45'),
(34, 3, 'Update Buffalo', 'Buffaloes', 'Tag: 143', NULL, '2026-07-30 05:12:35'),
(35, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 05:16:56'),
(36, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 05:17:05'),
(37, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 05:31:18'),
(38, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 05:31:27'),
(39, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 05:37:40'),
(40, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 05:38:46'),
(41, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 07:23:32'),
(42, 1, 'Login', 'Auth', NULL, '::1', '2026-07-30 07:23:41'),
(43, 1, 'Logout', 'Auth', NULL, '::1', '2026-07-30 07:59:47'),
(44, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 08:01:04'),
(45, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 08:10:55'),
(46, 2, 'Login', 'Auth', NULL, '::1', '2026-07-30 08:11:36'),
(47, 3, 'Login', 'Auth', NULL, '::1', '2026-07-30 15:53:35'),
(48, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-30 16:00:01'),
(49, 4, 'Login', 'Auth', NULL, '::1', '2026-07-30 16:00:16'),
(50, 3, 'Login', 'Auth', NULL, '::1', '2026-07-31 11:06:21'),
(51, 3, 'Logout', 'Auth', NULL, '::1', '2026-07-31 11:08:31'),
(52, 2, 'Login', 'Auth', NULL, '::1', '2026-07-31 11:08:56'),
(53, 1, 'Login', 'Auth', NULL, '::1', '2026-08-01 02:21:42'),
(54, 1, 'Logout', 'Auth', NULL, '::1', '2026-08-01 02:23:08'),
(55, 3, 'Login', 'Auth', NULL, '::1', '2026-08-01 02:23:21'),
(56, 3, 'Logout', 'Auth', NULL, '::1', '2026-08-01 02:26:32'),
(57, 4, 'Login', 'Auth', NULL, '::1', '2026-08-01 02:26:46'),
(58, 2, 'Login', 'Auth', NULL, '::1', '2026-08-01 02:31:53'),
(59, 2, 'Update Buffalo', 'Buffaloes', 'Tag: BUF-001', NULL, '2026-08-01 02:35:43'),
(60, 2, 'Logout', 'Auth', NULL, '::1', '2026-08-01 02:46:19'),
(61, 1, 'Login', 'Auth', NULL, '::1', '2026-08-01 02:50:13'),
(62, 3, 'Login', 'Auth', NULL, '::1', '2026-08-01 10:19:25'),
(63, 3, 'Logout', 'Auth', NULL, '::1', '2026-08-01 10:44:19'),
(64, 4, 'Login', 'Auth', NULL, '::1', '2026-08-01 10:44:37'),
(65, 3, 'Login', 'Auth', NULL, '::1', '2026-08-02 13:45:48'),
(66, 3, 'Logout', 'Auth', NULL, '::1', '2026-08-02 13:51:08'),
(67, 2, 'Login', 'Auth', NULL, '::1', '2026-08-02 13:51:30'),
(68, 2, 'Login', 'Auth', NULL, '::1', '2026-08-03 02:52:08');

-- --------------------------------------------------------

--
-- Table structure for table `breeding_records`
--

CREATE TABLE `breeding_records` (
  `id` int(11) NOT NULL,
  `buffalo_id` int(11) NOT NULL,
  `breeding_date` date NOT NULL,
  `method` enum('Natural','Artificial Insemination') DEFAULT 'Natural',
  `sire_id` int(11) DEFAULT NULL,
  `sire_name` varchar(100) DEFAULT NULL,
  `expected_calving` date DEFAULT NULL,
  `pregnancy_status` enum('Not Confirmed','Confirmed','Failed','Delivered') DEFAULT 'Not Confirmed',
  `pregnancy_check_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `breeding_records`
--

INSERT INTO `breeding_records` (`id`, `buffalo_id`, `breeding_date`, `method`, `sire_id`, `sire_name`, `expected_calving`, `pregnancy_status`, `pregnancy_check_date`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 1, '2026-03-10', 'Natural', NULL, 'Rex', '2026-12-10', 'Confirmed', NULL, NULL, 1, '2026-07-27 14:24:29'),
(2, 2, '2026-04-15', 'Artificial Insemination', NULL, NULL, '2027-01-15', 'Confirmed', NULL, NULL, 1, '2026-07-27 14:24:29'),
(3, 5, '2026-05-20', 'Natural', NULL, 'Rex', '2027-02-20', 'Not Confirmed', NULL, '', 1, '2026-07-27 14:24:29'),
(4, 9, '2026-07-30', 'Natural', NULL, 'John Dave', '2026-08-07', 'Confirmed', '2026-08-26', '', 4, '2026-07-30 04:50:55');

-- --------------------------------------------------------

--
-- Table structure for table `buffaloes`
--

CREATE TABLE `buffaloes` (
  `id` int(11) NOT NULL,
  `tag_number` varchar(40) NOT NULL,
  `qr_code` varchar(100) DEFAULT NULL,
  `name` varchar(80) DEFAULT NULL,
  `breed` varchar(80) DEFAULT NULL,
  `sex` enum('Female','Male') DEFAULT 'Female',
  `date_of_birth` date DEFAULT NULL,
  `weight_kg` decimal(6,2) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_type` enum('Born on Farm','Purchased','Donated') DEFAULT 'Born on Farm',
  `status` enum('Active','Sold','Dead','Transferred') DEFAULT 'Active',
  `health_status` enum('Healthy','Sick','Under Treatment','Recovered') DEFAULT 'Healthy',
  `notes` text DEFAULT NULL,
  `photo` varchar(200) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buffaloes`
--

INSERT INTO `buffaloes` (`id`, `tag_number`, `qr_code`, `name`, `breed`, `sex`, `date_of_birth`, `weight_kg`, `color`, `acquisition_date`, `acquisition_type`, `status`, `health_status`, `notes`, `photo`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BUF-001', 'QR-BUF-001', 'Bella', 'Murrah', 'Female', '2019-03-15', 480.00, 'Black', NULL, 'Born on Farm', 'Active', 'Healthy', '', NULL, 1, '2026-07-27 14:24:29', '2026-08-01 02:35:43'),
(2, 'BUF-002', 'QR-BUF-002', 'Rosa', 'Murrah', 'Female', '2020-06-20', 510.00, 'Black', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(3, 'BUF-003', 'QR-BUF-003', 'Luna', 'Nili-Ravi', 'Female', '2018-09-10', 550.00, 'Gray', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(4, 'BUF-004', 'QR-BUF-004', 'Star', 'Murrah', 'Female', '2021-01-05', 420.00, 'Black', NULL, 'Born on Farm', 'Active', 'Healthy', '', NULL, 1, '2026-07-27 14:24:29', '2026-07-30 04:55:11'),
(5, 'BUF-005', 'QR-BUF-005', 'Daisy', 'Surti', 'Female', '2020-11-18', 395.00, 'Brown', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(6, 'BUF-006', 'QR-BUF-006', 'Lola', 'Nili-Ravi', 'Female', '2017-07-22', 580.00, 'Gray', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(7, 'BUF-007', 'QR-BUF-007', 'Rex', 'Murrah', 'Male', '2019-04-12', 620.00, 'Black', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(8, 'BUF-008', 'QR-BUF-008', 'Coco', 'Murrah', 'Female', '2022-02-28', 360.00, 'Black', NULL, 'Born on Farm', 'Active', 'Healthy', NULL, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(9, '143', 'QR-143', 'Derodd', 'kanding', 'Female', '1975-06-11', 500.00, 'white/black', '2026-07-03', 'Born on Farm', 'Active', 'Under Treatment', '', NULL, 1, '2026-07-30 01:36:21', '2026-07-30 16:01:03');

-- --------------------------------------------------------

--
-- Table structure for table `calving_records`
--

CREATE TABLE `calving_records` (
  `id` int(11) NOT NULL,
  `mother_id` int(11) NOT NULL,
  `breeding_id` int(11) DEFAULT NULL,
  `calving_date` date NOT NULL,
  `calf_tag` varchar(40) DEFAULT NULL,
  `calf_sex` enum('Female','Male','Unknown') DEFAULT 'Unknown',
  `calf_weight_kg` decimal(5,2) DEFAULT NULL,
  `delivery_type` enum('Normal','Assisted','Cesarean') DEFAULT 'Normal',
  `calf_health` enum('Healthy','Weak','Stillborn') DEFAULT 'Healthy',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coop_inventory`
--

CREATE TABLE `coop_inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('Stock In','Stock Out','Adjustment','Sale','Return') DEFAULT 'Stock In',
  `quantity` decimal(10,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coop_inventory`
--

INSERT INTO `coop_inventory` (`id`, `product_id`, `movement_type`, `quantity`, `reference_id`, `notes`, `recorded_by`, `created_at`) VALUES
(5, 11, 'Stock In', 120.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:07:45'),
(6, 12, 'Stock In', 60.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:12:54'),
(7, 13, 'Stock In', 80.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:18:00'),
(8, 14, 'Stock In', 70.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:19:12'),
(9, 15, 'Stock In', 65.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:20:10'),
(10, 16, 'Stock In', 50.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:21:41'),
(11, 17, 'Stock In', 45.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:22:47'),
(12, 18, 'Stock In', 35.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:24:16'),
(13, 19, 'Stock In', 30.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:25:18'),
(14, 20, 'Stock In', 25.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:26:31'),
(15, 21, 'Stock In', 40.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:27:40'),
(16, 22, 'Stock In', 20.00, NULL, 'Initial stock on product creation', 3, '2026-07-30 06:28:35'),
(17, 22, 'Stock Out', 19.00, NULL, '', 3, '2026-07-30 06:29:37'),
(18, 22, 'Stock In', 10.00, NULL, '', 3, '2026-07-30 06:30:35'),
(19, 22, 'Stock In', 1.00, NULL, '', 3, '2026-07-30 06:31:03'),
(20, 22, 'Stock Out', 10.00, NULL, '', 3, '2026-07-30 06:31:33'),
(21, 13, 'Adjustment', 60.00, NULL, '', 3, '2026-07-30 06:32:07'),
(22, 12, 'Sale', 1.00, 1, 'POS Sale RCP-20260730-6A77A', 3, '2026-07-30 07:04:41'),
(23, 22, 'Sale', 1.00, 2, 'POS Sale RCP-20260730-CA87D', 3, '2026-07-30 07:22:27'),
(24, 22, 'Sale', 1.00, 3, 'POS Sale RCP-20260730-D2E2C', 3, '2026-07-30 08:05:13'),
(25, 20, 'Sale', 1.00, 4, 'POS Sale RCP-20260730-D554A', 3, '2026-07-30 08:06:17'),
(26, 14, 'Sale', 1.00, 4, 'POS Sale RCP-20260730-D554A', 3, '2026-07-30 08:06:17'),
(27, 13, 'Sale', 1.00, 4, 'POS Sale RCP-20260730-D554A', 3, '2026-07-30 08:06:17'),
(28, 11, 'Sale', 1.00, 4, 'POS Sale RCP-20260730-D554A', 3, '2026-07-30 08:06:17'),
(29, 13, 'Sale', 1.00, 5, 'POS Sale RCP-20260801-84FEE', 3, '2026-08-01 10:25:44'),
(30, 21, 'Sale', 1.00, 5, 'POS Sale RCP-20260801-84FEE', 3, '2026-08-01 10:25:44'),
(31, 13, 'Sale', 3.00, 6, 'POS Sale RCP-20260801-18D17', 3, '2026-08-01 10:27:05'),
(32, 21, 'Sale', 1.00, 6, 'POS Sale RCP-20260801-18D17', 3, '2026-08-01 10:27:05'),
(33, 14, 'Sale', 1.00, 7, 'POS Sale RCP-20260801-DEF3C', 3, '2026-08-01 10:32:39'),
(34, 13, 'Sale', 1.00, 7, 'POS Sale RCP-20260801-DEF3C', 3, '2026-08-01 10:32:39'),
(35, 14, 'Sale', 1.00, 8, 'POS Sale RCP-20260801-DEDEF', 3, '2026-08-01 10:33:31'),
(36, 19, 'Sale', 1.00, 8, 'POS Sale RCP-20260801-DEDEF', 3, '2026-08-01 10:33:31'),
(37, 13, 'Sale', 1.00, 9, 'POS Sale RCP-20260801-545CC', 3, '2026-08-01 10:34:01'),
(38, 15, 'Sale', 1.00, 9, 'POS Sale RCP-20260801-545CC', 3, '2026-08-01 10:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `coop_products`
--

CREATE TABLE `coop_products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` varchar(80) DEFAULT 'Uncategorized',
  `description` text DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'liter',
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `stock_qty` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT 10.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coop_products`
--

INSERT INTO `coop_products` (`id`, `product_code`, `name`, `category`, `description`, `unit`, `selling_price`, `cost_price`, `stock_qty`, `reorder_level`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(11, 'BD001', 'Fresh Carabao\'s Milk(250mL)', 'Fresh Milk', '', 'bottle', 50.00, 35.00, 119.00, 20.00, 1, 3, '2026-07-30 06:07:45', '2026-07-30 08:06:17'),
(12, 'BD002', 'Fresh Carabao\'s Milk(1L)', 'Fresh Milk', '', 'bottle', 180.00, 145.00, 59.00, 10.00, 1, 3, '2026-07-30 06:12:54', '2026-07-30 07:04:41'),
(13, 'BD003', 'Chocolate Milk(250mL)', 'Flavored Milk', '', 'bottle', 60.00, 42.00, 13.00, 15.00, 1, 3, '2026-07-30 06:18:00', '2026-08-01 10:34:01'),
(14, 'BD004', 'Strawberry Milk(250mL)', 'Flavored Milk', '', 'bottle', 60.00, 42.00, 67.00, 15.00, 1, 3, '2026-07-30 06:19:12', '2026-08-01 10:33:31'),
(15, 'BD005', 'Mango Milk(250mL)', 'Flavored Milk', '', 'bottle', 60.00, 42.00, 64.00, 15.00, 1, 3, '2026-07-30 06:20:10', '2026-08-01 10:34:01'),
(16, 'BD006', 'Plain Yogurt(250mL)', 'Yugort', '', 'Cup', 90.00, 65.00, 50.00, 10.00, 1, 3, '2026-07-30 06:21:41', '2026-07-30 06:21:41'),
(17, 'BD007', 'Strawberry Yogurt(250mL)', 'Yugort', '', 'Cup', 100.00, 70.00, 45.00, 10.00, 1, 3, '2026-07-30 06:22:47', '2026-07-30 06:22:47'),
(18, 'BD008', 'Chocolate Ica Cream(1L)', 'Ice Cream', '', 'Tub', 220.00, 165.00, 35.00, 8.00, 1, 3, '2026-07-30 06:24:16', '2026-07-30 06:24:16'),
(19, 'BD009', 'Vanilla Ice Cream(1L)', 'Ice Cream', '', 'Tub', 220.00, 165.00, 29.00, 8.00, 1, 3, '2026-07-30 06:25:18', '2026-08-01 10:33:31'),
(20, 'BD010', 'Kesong Puti(200g)', 'Cheese', '', 'Pack', 160.00, 120.00, 24.00, 5.00, 1, 3, '2026-07-30 06:26:31', '2026-07-30 08:06:17'),
(21, 'BD011', 'Pastillas(10pcs)', 'Confectionery', '', 'Pack', 120.00, 80.00, 38.00, 10.00, 1, 3, '2026-07-30 06:27:40', '2026-08-01 10:27:05'),
(22, 'BD012', 'Cheese Spread(250g)', 'Cheese', '', 'Jar', 180.00, 135.00, 0.00, 5.00, 1, 3, '2026-07-30 06:28:35', '2026-07-30 08:05:13');

-- --------------------------------------------------------

--
-- Table structure for table `coop_sales`
--

CREATE TABLE `coop_sales` (
  `id` int(11) NOT NULL,
  `receipt_number` varchar(40) NOT NULL,
  `sale_date` date NOT NULL DEFAULT curdate(),
  `customer_name` varchar(120) DEFAULT 'Walk-in Customer',
  `customer_phone` varchar(30) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_tendered` decimal(12,2) DEFAULT 0.00,
  `change_amount` decimal(12,2) DEFAULT 0.00,
  `payment_method` enum('Cash','GCash','Maya','Bank Transfer','Credit') DEFAULT 'Cash',
  `status` enum('Completed','Voided','Refunded') DEFAULT 'Completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coop_sales`
--

INSERT INTO `coop_sales` (`id`, `receipt_number`, `sale_date`, `customer_name`, `customer_phone`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `amount_tendered`, `change_amount`, `payment_method`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 'RCP-20260730-6A77A', '2026-07-30', 'Walk-in Customer', '', 180.00, 0.00, 0.00, 180.00, 0.00, 0.00, 'GCash', 'Completed', '', 3, '2026-07-30 07:04:41'),
(2, 'RCP-20260730-CA87D', '2026-07-30', 'Walk-in Customer', '', 180.00, 0.00, 0.00, 180.00, 200.00, 20.00, 'Cash', 'Completed', '', 3, '2026-07-30 07:22:27'),
(3, 'RCP-20260730-D2E2C', '2026-07-30', 'Walk-in Customer', '', 180.00, 0.00, 0.00, 180.00, 300.00, 120.00, 'Cash', 'Completed', '', 3, '2026-07-30 08:05:13'),
(4, 'RCP-20260730-D554A', '2026-07-30', 'Walk-in Customer', '', 330.00, 0.00, 0.00, 330.00, 1000.00, 670.00, 'Cash', 'Completed', '', 3, '2026-07-30 08:06:17'),
(5, 'RCP-20260801-84FEE', '2026-08-01', 'Walk-in Customer', '', 180.00, 0.00, 0.00, 180.00, 200.00, 20.00, 'GCash', 'Completed', '', 3, '2026-08-01 10:25:44'),
(6, 'RCP-20260801-18D17', '2026-08-01', 'Walk-in Customer', '', 300.00, 0.00, 0.00, 300.00, 200.00, 0.00, 'Cash', 'Completed', '', 3, '2026-08-01 10:27:05'),
(7, 'RCP-20260801-DEF3C', '2026-08-01', 'Walk-in Customer', '', 120.00, 0.00, 0.00, 120.00, 200.00, 80.00, 'Cash', 'Completed', '', 3, '2026-08-01 10:32:39'),
(8, 'RCP-20260801-DEDEF', '2026-08-01', 'Walk-in Customer', '', 280.00, 0.00, 0.00, 280.00, 300.00, 20.00, 'GCash', 'Completed', '', 3, '2026-08-01 10:33:31'),
(9, 'RCP-20260801-545CC', '2026-08-01', 'Walk-in Customer', '', 120.00, 0.00, 0.00, 120.00, 300.00, 180.00, 'GCash', 'Completed', '', 3, '2026-08-01 10:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `coop_sale_items`
--

CREATE TABLE `coop_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coop_sale_items`
--

INSERT INTO `coop_sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `discount`, `line_total`) VALUES
(1, 1, 12, 1.00, 180.00, 0.00, 180.00),
(2, 2, 22, 1.00, 180.00, 0.00, 180.00),
(3, 3, 22, 1.00, 180.00, 0.00, 180.00),
(4, 4, 20, 1.00, 160.00, 0.00, 160.00),
(5, 4, 14, 1.00, 60.00, 0.00, 60.00),
(6, 4, 13, 1.00, 60.00, 0.00, 60.00),
(7, 4, 11, 1.00, 50.00, 0.00, 50.00),
(8, 5, 13, 1.00, 60.00, 0.00, 60.00),
(9, 5, 21, 1.00, 120.00, 0.00, 120.00),
(10, 6, 13, 3.00, 60.00, 0.00, 180.00),
(11, 6, 21, 1.00, 120.00, 0.00, 120.00),
(12, 7, 14, 1.00, 60.00, 0.00, 60.00),
(13, 7, 13, 1.00, 60.00, 0.00, 60.00),
(14, 8, 14, 1.00, 60.00, 0.00, 60.00),
(15, 8, 19, 1.00, 220.00, 0.00, 220.00),
(16, 9, 13, 1.00, 60.00, 0.00, 60.00),
(17, 9, 15, 1.00, 60.00, 0.00, 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL,
  `buffalo_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `condition_type` enum('Illness','Injury','Routine Check','Disease Alert','Other') DEFAULT 'Routine Check',
  `diagnosis` varchar(200) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `medicine_used` varchar(200) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `vet_name` varchar(120) DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `status` enum('Active','Resolved','Monitoring') DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`id`, `buffalo_id`, `record_date`, `condition_type`, `diagnosis`, `symptoms`, `treatment`, `medicine_used`, `dosage`, `vet_name`, `followup_date`, `status`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 9, '2026-07-30', 'Injury', 'Brucellosis', 'Loss of appetite', 'Deworming', 'Amoxicillin', '10 ML IM once daily for 3days', 'Ronnel Jalandoni', '2026-07-30', 'Active', '', 4, '2026-07-30 04:47:45');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `category` enum('Medicine','Vaccine','Supply','Equipment','Feed','Other') DEFAULT 'Medicine',
  `unit` varchar(30) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT 10.00,
  `expiry_date` date DEFAULT NULL,
  `supplier` varchar(120) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `category`, `unit`, `quantity`, `reorder_level`, `expiry_date`, `supplier`, `unit_cost`, `notes`, `updated_by`, `updated_at`, `created_at`) VALUES
(1, 'FMD Vaccine', 'Vaccine', 'dose', 50.00, 20.00, '2027-01-01', NULL, 85.00, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(2, 'Hemorrhagic Sep. Vaccine', 'Vaccine', 'dose', 30.00, 15.00, '2026-12-01', NULL, 90.00, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(3, 'Ivermectin', 'Medicine', 'bottle', 15.00, 10.00, '2027-06-01', '', 250.00, '', 1, '2026-07-27 15:11:05', '2026-07-27 14:24:29'),
(4, 'Penicillin', 'Medicine', 'vial', 25.00, 10.00, '2027-03-01', NULL, 120.00, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(5, 'Milk Recording Sheets', 'Supply', 'pack', 8.00, 5.00, NULL, '', 45.00, '', 1, '2026-07-30 02:01:32', '2026-07-27 14:24:29'),
(6, 'Syringes (5ml)', 'Supply', 'box', 20.00, 10.00, NULL, NULL, 80.00, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29'),
(7, 'Ear Tags', 'Supply', 'piece', 100.00, 50.00, NULL, NULL, 5.00, NULL, 1, '2026-07-27 14:24:29', '2026-07-27 14:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `milk_production`
--

CREATE TABLE `milk_production` (
  `id` int(11) NOT NULL,
  `buffalo_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `session` enum('Morning','Afternoon','Evening') DEFAULT 'Morning',
  `quantity_liters` decimal(7,2) NOT NULL,
  `quality_notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `milk_production`
--

INSERT INTO `milk_production` (`id`, `buffalo_id`, `record_date`, `session`, `quantity_liters`, `quality_notes`, `recorded_by`, `created_at`) VALUES
(1, 1, '2026-07-20', 'Morning', 6.50, NULL, 2, '2026-07-27 14:24:29'),
(2, 1, '2026-07-20', 'Evening', 5.80, NULL, 2, '2026-07-27 14:24:29'),
(3, 2, '2026-07-20', 'Morning', 7.20, NULL, 2, '2026-07-27 14:24:29'),
(4, 2, '2026-07-20', 'Evening', 6.50, NULL, 2, '2026-07-27 14:24:29'),
(5, 3, '2026-07-20', 'Morning', 8.10, NULL, 2, '2026-07-27 14:24:29'),
(6, 3, '2026-07-20', 'Evening', 7.40, NULL, 2, '2026-07-27 14:24:29'),
(7, 5, '2026-07-20', 'Morning', 5.90, NULL, 2, '2026-07-27 14:24:29'),
(8, 5, '2026-07-20', 'Evening', 5.20, NULL, 2, '2026-07-27 14:24:29'),
(9, 6, '2026-07-20', 'Morning', 9.00, NULL, 2, '2026-07-27 14:24:29'),
(10, 6, '2026-07-20', 'Evening', 8.30, NULL, 2, '2026-07-27 14:24:29'),
(11, 1, '2026-07-21', 'Morning', 6.70, NULL, 2, '2026-07-27 14:24:29'),
(12, 1, '2026-07-21', 'Evening', 5.90, NULL, 2, '2026-07-27 14:24:29'),
(13, 2, '2026-07-21', 'Morning', 7.40, NULL, 2, '2026-07-27 14:24:29'),
(14, 2, '2026-07-21', 'Evening', 6.70, NULL, 2, '2026-07-27 14:24:29'),
(15, 3, '2026-07-21', 'Morning', 8.30, NULL, 2, '2026-07-27 14:24:29'),
(16, 3, '2026-07-21', 'Evening', 7.60, NULL, 2, '2026-07-27 14:24:29'),
(17, 5, '2026-07-21', 'Morning', 6.10, NULL, 2, '2026-07-27 14:24:29'),
(18, 5, '2026-07-21', 'Evening', 5.40, NULL, 2, '2026-07-27 14:24:29'),
(19, 6, '2026-07-21', 'Morning', 9.20, NULL, 2, '2026-07-27 14:24:29'),
(20, 6, '2026-07-21', 'Evening', 8.50, NULL, 2, '2026-07-27 14:24:29'),
(21, 1, '2026-07-22', 'Morning', 6.60, NULL, 2, '2026-07-27 14:24:29'),
(22, 1, '2026-07-22', 'Evening', 5.85, NULL, 2, '2026-07-27 14:24:29'),
(23, 2, '2026-07-22', 'Morning', 7.30, NULL, 2, '2026-07-27 14:24:29'),
(24, 2, '2026-07-22', 'Evening', 6.60, NULL, 2, '2026-07-27 14:24:29'),
(25, 3, '2026-07-22', 'Morning', 8.20, NULL, 2, '2026-07-27 14:24:29'),
(26, 3, '2026-07-22', 'Evening', 7.50, NULL, 2, '2026-07-27 14:24:29'),
(27, 5, '2026-07-22', 'Morning', 6.00, NULL, 2, '2026-07-27 14:24:29'),
(28, 5, '2026-07-22', 'Evening', 5.30, NULL, 2, '2026-07-27 14:24:29'),
(29, 6, '2026-07-22', 'Morning', 9.10, NULL, 2, '2026-07-27 14:24:29'),
(30, 6, '2026-07-22', 'Evening', 8.40, NULL, 2, '2026-07-27 14:24:29'),
(31, 9, '2026-07-02', 'Morning', 5.00, '', 1, '2026-07-30 01:57:00'),
(32, 9, '2026-07-30', 'Afternoon', 15.00, '', 1, '2026-07-30 01:58:49'),
(33, 1, '2026-07-30', 'Morning', 10.00, '', 2, '2026-07-30 08:12:40');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('vaccination','breeding','calving','health','production','system') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `buffalo_id` int(11) DEFAULT NULL,
  `target_role` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `buffalo_id`, `target_role`, `is_read`, `priority`, `due_date`, `created_at`) VALUES
(1, 'vaccination', 'FMD Vaccination Due – BUF-001', 'Bella (BUF-001) is overdue for FMD Vaccine. Schedule immediately.', 1, 'farm_manager', 1, 'urgent', '2026-07-15', '2026-07-27 14:24:29'),
(2, 'vaccination', 'Upcoming Vaccination – BUF-002', 'Rosa (BUF-002) FMD Vaccine due on Aug 10.', 2, 'farm_manager', 1, 'high', '2026-08-10', '2026-07-27 14:24:29'),
(3, 'calving', 'Expected Calving – Bella', 'Bella (BUF-001) expected calving on Dec 10, 2026.', 1, 'veterinarian', 1, 'medium', '2026-12-10', '2026-07-27 14:24:29'),
(4, 'health', 'Health Alert – BUF-004', 'Star (BUF-004) is currently under treatment. Follow up needed.', 4, 'veterinarian', 1, 'high', '2026-07-28', '2026-07-27 14:24:29'),
(5, 'breeding', 'Pregnancy Check Due – BUF-005', 'Daisy (BUF-005) breeding on May 20 – pregnancy check due.', 5, 'farm_manager', 1, 'medium', '2026-07-20', '2026-07-27 14:24:29'),
(6, 'vaccination', 'Upcoming: FMD Vaccine – 143', 'Vaccine due on 2026-08-30 for Derodd (143)', 9, 'farm_manager', 1, 'medium', '2026-08-30', '2026-07-30 02:05:21'),
(7, 'calving', 'Expected Calving – 143', 'Expected calving on 2026-08-07 for Derodd', 9, 'veterinarian', 1, 'medium', '2026-08-07', '2026-07-30 04:50:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role` enum('farm_manager','farm_caretaker','dairy_cooperative','veterinarian') NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `email`, `phone`, `is_active`, `created_at`) VALUES
(1, 'manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'farm_manager', 'manager@dairybox.ph', '09987654321', 1, '2026-07-27 14:24:29'),
(2, 'caretaker1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ronel Jalandoni', 'farm_caretaker', 'caretaker@dairybox.ph', '', 1, '2026-07-27 14:24:29'),
(3, 'coop1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Surallah Dairy Coop', 'dairy_cooperative', 'coop@dairybox.ph', NULL, 1, '2026-07-27 14:24:29'),
(4, 'vet1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Jose Reyes', 'veterinarian', 'vet@dairybox.ph', NULL, 1, '2026-07-27 14:24:29'),
(5, 'Manager Ronel', '$2y$10$NAMzi46uAVkd1fBjbyk.1OraI6hcJXAsvYostz/r8ftDr0NZeBOEC', 'Ronnel Jalandoni', 'farm_manager', 'manager@dairybox.ph', '09266074123', 1, '2026-07-30 02:17:38');

-- --------------------------------------------------------

--
-- Table structure for table `vaccinations`
--

CREATE TABLE `vaccinations` (
  `id` int(11) NOT NULL,
  `buffalo_id` int(11) NOT NULL,
  `vaccine_name` varchar(120) NOT NULL,
  `vaccine_type` varchar(80) DEFAULT NULL,
  `administered_date` date NOT NULL,
  `next_due_date` date DEFAULT NULL,
  `administered_by` varchar(120) DEFAULT NULL,
  `batch_number` varchar(60) DEFAULT NULL,
  `dose` varchar(60) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Done','Scheduled','Overdue') DEFAULT 'Done',
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccinations`
--

INSERT INTO `vaccinations` (`id`, `buffalo_id`, `vaccine_name`, `vaccine_type`, `administered_date`, `next_due_date`, `administered_by`, `batch_number`, `dose`, `notes`, `status`, `recorded_by`, `created_at`) VALUES
(1, 1, 'FMD Vaccine', 'Foot and Mouth Disease', '2026-01-15', '2026-07-15', 'Dr. Reyes', '', '', '', 'Overdue', 1, '2026-07-27 14:24:29'),
(2, 2, 'FMD Vaccine', 'Foot and Mouth Disease', '2026-02-10', '2026-08-10', 'Dr. Reyes', '', '', '', 'Done', 1, '2026-07-27 14:24:29'),
(3, 3, 'Hemorrhagic Septicemia', 'Bacterial', '2026-03-05', '2026-09-05', 'Dr. Reyes', NULL, NULL, NULL, 'Scheduled', 1, '2026-07-27 14:24:29'),
(4, 4, 'Brucellosis', 'Bacterial', '2026-05-20', '2027-05-20', 'Dr. Reyes', NULL, NULL, NULL, 'Done', 1, '2026-07-27 14:24:29'),
(5, 5, 'FMD Vaccine', 'Foot and Mouth Disease', '2026-04-12', '2026-10-12', 'Dr. Reyes', NULL, NULL, NULL, 'Scheduled', 1, '2026-07-27 14:24:29'),
(6, 9, 'FMD Vaccine', 'Foot and Mouth Disease', '2026-07-30', '2026-08-30', 'Dr. Reyes', '', '', '', 'Scheduled', 1, '2026-07-30 02:05:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `breeding_records`
--
ALTER TABLE `breeding_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buffalo_id` (`buffalo_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `buffaloes`
--
ALTER TABLE `buffaloes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_number` (`tag_number`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `calving_records`
--
ALTER TABLE `calving_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mother_id` (`mother_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `coop_inventory`
--
ALTER TABLE `coop_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `coop_products`
--
ALTER TABLE `coop_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `coop_sales`
--
ALTER TABLE `coop_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `coop_sale_items`
--
ALTER TABLE `coop_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buffalo_id` (`buffalo_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `milk_production`
--
ALTER TABLE `milk_production`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buffalo_id` (`buffalo_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buffalo_id` (`buffalo_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buffalo_id` (`buffalo_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `breeding_records`
--
ALTER TABLE `breeding_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `buffaloes`
--
ALTER TABLE `buffaloes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `calving_records`
--
ALTER TABLE `calving_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coop_inventory`
--
ALTER TABLE `coop_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `coop_products`
--
ALTER TABLE `coop_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `coop_sales`
--
ALTER TABLE `coop_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `coop_sale_items`
--
ALTER TABLE `coop_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `milk_production`
--
ALTER TABLE `milk_production`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vaccinations`
--
ALTER TABLE `vaccinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `breeding_records`
--
ALTER TABLE `breeding_records`
  ADD CONSTRAINT `breeding_records_ibfk_1` FOREIGN KEY (`buffalo_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `breeding_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `buffaloes`
--
ALTER TABLE `buffaloes`
  ADD CONSTRAINT `buffaloes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `calving_records`
--
ALTER TABLE `calving_records`
  ADD CONSTRAINT `calving_records_ibfk_1` FOREIGN KEY (`mother_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `calving_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coop_inventory`
--
ALTER TABLE `coop_inventory`
  ADD CONSTRAINT `coop_inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `coop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coop_inventory_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coop_products`
--
ALTER TABLE `coop_products`
  ADD CONSTRAINT `coop_products_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coop_sales`
--
ALTER TABLE `coop_sales`
  ADD CONSTRAINT `coop_sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coop_sale_items`
--
ALTER TABLE `coop_sale_items`
  ADD CONSTRAINT `coop_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `coop_sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coop_sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `coop_products` (`id`);

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`buffalo_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `health_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `milk_production`
--
ALTER TABLE `milk_production`
  ADD CONSTRAINT `milk_production_ibfk_1` FOREIGN KEY (`buffalo_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `milk_production_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`buffalo_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD CONSTRAINT `vaccinations_ibfk_1` FOREIGN KEY (`buffalo_id`) REFERENCES `buffaloes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccinations_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
