-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Maj 27, 2026 at 02:18 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swdtm`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `clients`
--

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_name` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `facility_address` varchar(255) NOT NULL,
  `facility_city` varchar(120) DEFAULT NULL,
  `facility_postcode` varchar(32) DEFAULT NULL,
  `facility_street` varchar(160) DEFAULT NULL,
  `facility_number` varchar(32) DEFAULT NULL,
  `facility_flat` varchar(32) DEFAULT NULL,
  `facility_lat` decimal(10,7) DEFAULT NULL,
  `facility_lon` decimal(10,7) DEFAULT NULL,
  `facility_display` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_name`, `username`, `password_hash`, `facility_address`, `facility_city`, `facility_postcode`, `facility_street`, `facility_number`, `facility_flat`, `facility_lat`, `facility_lon`, `facility_display`, `created_at`, `updated_at`) VALUES
(1, 'Szpital Miejski w Chorzowie', 'szpchorzow', '$2y$10$UJKzsRxqD8jNylDQndux1u04C/o8Bs6Miv7daOmTv2pLVUFAmVVji', 'Strzelców Bytomskich 11, 41-530 Chorzów', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 50.2919398, 18.9428060, 'Zespół Szpitali Miejskich w Chorzowie', '2026-05-11 10:36:24', '2026-05-11 10:36:24');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `client_requests`
--

CREATE TABLE `client_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_type` enum('nagłe','planowe') NOT NULL,
  `urgency` enum('zwykłe','pilne','natychmiast') NOT NULL,
  `transport_type` enum('hospital','poradnia','miedzyszpitalna','dom','transport prywatny') NOT NULL,
  `needed_team` enum('T','P','S') NOT NULL,
  `sirens` tinyint(1) NOT NULL DEFAULT 0,
  `planned_at` datetime DEFAULT NULL,
  `patient_first_name` varchar(80) NOT NULL,
  `patient_last_name` varchar(80) NOT NULL,
  `patient_position` enum('chodzi','siedzi','leży') NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `patient_weight_kg` int(11) DEFAULT NULL,
  `interview_oxygen` enum('tak','nie','nie_wiadomo') NOT NULL,
  `interview_conscious` enum('tak','nie','nie_wiadomo') NOT NULL,
  `interview_notes` varchar(255) DEFAULT NULL,
  `icd10_none` tinyint(1) NOT NULL DEFAULT 0,
  `icd10_code` varchar(16) DEFAULT NULL,
  `icd10_name` varchar(255) DEFAULT NULL,
  `order_description` text DEFAULT NULL,
  `from_infra` enum('dom','blok mieszkalny','szpital','poradnia','inne') NOT NULL,
  `from_city` varchar(120) NOT NULL,
  `from_postcode` varchar(20) DEFAULT NULL,
  `from_street` varchar(160) NOT NULL,
  `from_number` varchar(40) NOT NULL,
  `from_flat` varchar(40) DEFAULT NULL,
  `from_display` varchar(255) DEFAULT NULL,
  `from_lat` decimal(10,7) DEFAULT NULL,
  `from_lon` decimal(10,7) DEFAULT NULL,
  `to_infra` enum('dom','blok mieszkalny','szpital','poradnia','inne') NOT NULL,
  `to_city` varchar(120) DEFAULT NULL,
  `to_postcode` varchar(20) DEFAULT NULL,
  `to_street` varchar(160) DEFAULT NULL,
  `to_number` varchar(40) DEFAULT NULL,
  `to_flat` varchar(40) DEFAULT NULL,
  `to_display` varchar(255) DEFAULT NULL,
  `to_lat` decimal(10,7) DEFAULT NULL,
  `to_lon` decimal(10,7) DEFAULT NULL,
  `distance_km` decimal(8,1) DEFAULT NULL,
  `status` enum('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `confirmed_by` int(10) UNSIGNED DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_requests`
--

INSERT INTO `client_requests` (`id`, `client_id`, `order_id`, `order_type`, `urgency`, `transport_type`, `needed_team`, `sirens`, `planned_at`, `patient_first_name`, `patient_last_name`, `patient_position`, `phone`, `patient_weight_kg`, `interview_oxygen`, `interview_conscious`, `interview_notes`, `icd10_none`, `icd10_code`, `icd10_name`, `order_description`, `from_infra`, `from_city`, `from_postcode`, `from_street`, `from_number`, `from_flat`, `from_display`, `from_lat`, `from_lon`, `to_infra`, `to_city`, `to_postcode`, `to_street`, `to_number`, `to_flat`, `to_display`, `to_lat`, `to_lon`, `distance_km`, `status`, `confirmed_by`, `confirmed_at`, `rejected_by`, `rejected_at`, `created_at`, `updated_at`) VALUES
(1, 1, 24, 'nagłe', 'zwykłe', 'dom', 'T', 0, NULL, 'Bartosz', 'Stromek', 'siedzi', '123 456 789', 75, 'nie', 'tak', NULL, 1, NULL, NULL, 'odwóz', 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 'blok mieszkalny', 'Chorzów', '41-500', 'Katowicka', '111', '1', 'Hala Targowa w Chorzowie', 50.3009416, 18.9514240, 1.2, 'confirmed', 2, '2026-05-11 11:31:25', NULL, NULL, '2026-05-11 11:20:51', '2026-05-11 11:31:25'),
(2, 1, 23, 'planowe', 'zwykłe', 'poradnia', 'T', 0, '2026-05-11 16:30:00', 'Jan', 'Umok', 'leży', '456123431', 60, 'nie', 'tak', NULL, 0, 'I25.0', 'Choroba serca i naczyń krwionośnych w przebiegu miażdżycy', NULL, 'dom', 'Ruda Śląska', '41-712', 'Podlas', '11', NULL, NULL, 50.2456002, 18.8314731, 'poradnia', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 18.9, 'confirmed', 2, '2026-05-11 11:31:21', NULL, NULL, '2026-05-11 11:24:25', '2026-05-11 11:31:21');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `dispatch_cancellations`
--

CREATE TABLE `dispatch_cancellations` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `team_code` varchar(20) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `acked_by` int(11) DEFAULT NULL,
  `acked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dispatch_cancellations`
--

INSERT INTO `dispatch_cancellations` (`id`, `dispatch_id`, `order_id`, `team_code`, `reason`, `cancelled_by`, `cancelled_at`, `acked_by`, `acked_at`, `created_at`) VALUES
(1, 16, 14, 'T1', 'test', 2, '2026-04-27 12:27:15', 3, '2026-04-27 12:27:22', '2026-04-27 12:27:15'),
(2, 17, 14, 'T1', 'pozdrawiam', 2, '2026-04-27 12:31:58', 3, '2026-04-27 12:32:02', '2026-04-27 12:31:58'),
(3, 18, 14, 'T1', 'xxz', 2, '2026-04-27 12:34:39', 3, '2026-04-27 12:34:54', '2026-04-27 12:34:39'),
(4, 19, 14, 'T1', 'bcb', 2, '2026-04-27 12:38:27', 3, '2026-04-27 12:38:28', '2026-04-27 12:38:27'),
(5, 20, 14, 'T1', 'chuj', 2, '2026-04-27 12:40:41', 3, '2026-04-27 12:40:48', '2026-04-27 12:40:41'),
(6, 21, 14, 'T1', 'vbv', 2, '2026-04-27 12:54:29', 3, '2026-04-27 12:54:32', '2026-04-27 12:54:29'),
(7, 22, 14, 'T1', 'bxb', 2, '2026-04-27 12:59:07', 3, '2026-04-27 12:59:10', '2026-04-27 12:59:07'),
(8, 23, 14, 'T1', NULL, 2, '2026-04-27 13:02:45', 3, '2026-04-27 13:02:48', '2026-04-27 13:02:45'),
(9, 24, 14, 'T1', NULL, 2, '2026-04-27 13:10:27', 3, '2026-04-27 13:10:31', '2026-04-27 13:10:27'),
(10, 25, 14, 'T1', 'bv', 2, '2026-04-27 13:10:59', 3, '2026-04-27 13:11:01', '2026-04-27 13:10:59'),
(11, 26, 14, 'T1', NULL, 2, '2026-04-27 13:12:51', 3, '2026-04-27 13:12:53', '2026-04-27 13:12:51'),
(12, 27, 14, 'T1', 'xx', 2, '2026-04-27 13:34:33', 3, '2026-04-27 13:34:40', '2026-04-27 13:34:33'),
(13, 28, 15, 'T1', 'bo tak', 2, '2026-04-30 17:14:30', 3, '2026-04-30 17:14:39', '2026-04-30 17:14:30'),
(14, 29, 15, 'T1', NULL, 2, '2026-04-30 17:35:09', 3, '2026-04-30 17:35:14', '2026-04-30 17:35:09'),
(15, 31, 16, 'T1', NULL, 2, '2026-05-06 12:49:47', 3, '2026-05-06 12:49:50', '2026-05-06 12:49:47'),
(16, 33, 17, 'T1', NULL, 2, '2026-05-07 13:15:20', 3, '2026-05-07 13:15:25', '2026-05-07 13:15:20'),
(17, 35, 19, 'T1', NULL, 2, '2026-05-07 15:48:59', 3, '2026-05-07 15:49:02', '2026-05-07 15:48:59'),
(18, 41, 21, 'T1', NULL, 2, '2026-05-10 12:26:47', 3, '2026-05-10 12:27:02', '2026-05-10 12:26:47'),
(19, 40, 21, 'T1', NULL, 2, '2026-05-10 12:26:49', 3, '2026-05-10 12:26:55', '2026-05-10 12:26:49'),
(20, 39, 21, 'T1', NULL, 2, '2026-05-10 12:26:51', 3, '2026-05-10 12:26:59', '2026-05-10 12:26:51'),
(21, 38, 21, 'T1', NULL, 2, '2026-05-10 12:26:53', 3, '2026-05-10 12:26:57', '2026-05-10 12:26:53'),
(22, 44, 23, 'T1', NULL, 2, '2026-05-20 19:43:48', 3, '2026-05-20 19:48:46', '2026-05-20 19:43:48'),
(23, 45, 23, 'T1', NULL, 2, '2026-05-20 19:44:09', 3, '2026-05-20 19:48:44', '2026-05-20 19:44:09'),
(24, 46, 23, 'T1', NULL, 2, '2026-05-20 20:19:05', 3, '2026-05-20 20:19:57', '2026-05-20 20:19:05');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `dispatch_notifications`
--

CREATE TABLE `dispatch_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `team_code` varchar(20) NOT NULL,
  `dispatcher_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled','queued') NOT NULL DEFAULT 'pending',
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `notification_sent_at` datetime DEFAULT NULL,
  `notification_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dispatch_notifications`
--

INSERT INTO `dispatch_notifications` (`id`, `order_id`, `team_code`, `dispatcher_id`, `status`, `accepted_at`, `accepted_by`, `rejected_at`, `rejected_by`, `cancelled_at`, `cancelled_by`, `notification_sent_at`, `notification_seen_at`, `created_at`, `updated_at`) VALUES
(6, 12, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-04-23 15:13:39', 2, '2026-04-23 15:13:31', NULL, '2026-04-23 15:12:18', '2026-04-23 15:13:39'),
(7, 12, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-04-23 15:53:24', 2, '2026-04-23 15:52:34', NULL, '2026-04-23 15:52:34', '2026-04-23 15:53:24'),
(8, 13, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-04-27 11:38:53', 2, '2026-04-27 11:37:59', NULL, '2026-04-27 11:37:59', '2026-04-27 11:38:53'),
(9, 13, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-04-27 11:42:44', 2, '2026-04-27 11:42:14', NULL, '2026-04-27 11:42:14', '2026-04-27 11:42:44'),
(10, 13, 'T1', 2, 'cancelled', '2026-04-27 11:47:14', 3, NULL, NULL, '2026-04-27 11:47:59', 2, '2026-04-27 11:44:51', NULL, '2026-04-27 11:44:51', '2026-04-27 11:47:59'),
(11, 13, 'T1', 2, 'cancelled', '2026-04-27 11:52:59', 3, NULL, NULL, '2026-04-27 11:55:18', 2, '2026-04-27 11:52:46', NULL, '2026-04-27 11:52:46', '2026-04-27 11:55:18'),
(12, 13, 'T1', 2, 'cancelled', '2026-04-27 11:55:27', 3, NULL, NULL, '2026-04-27 11:58:01', 2, '2026-04-27 11:55:24', NULL, '2026-04-27 11:55:24', '2026-04-27 11:58:01'),
(13, 13, 'T1', 2, 'cancelled', '2026-04-27 11:58:12', 3, NULL, NULL, '2026-04-27 12:04:42', 2, '2026-04-27 11:58:06', NULL, '2026-04-27 11:58:06', '2026-04-27 12:04:42'),
(14, 13, 'T1', 2, 'cancelled', '2026-04-27 12:08:41', 3, NULL, NULL, '2026-04-27 12:10:41', 2, '2026-04-27 12:08:28', NULL, '2026-04-27 12:08:28', '2026-04-27 12:10:41'),
(31, 16, 'T1', 2, 'cancelled', '2026-05-06 12:40:16', 3, NULL, NULL, '2026-05-06 12:49:47', 2, '2026-05-06 12:45:15', NULL, '2026-05-06 12:40:12', '2026-05-06 12:49:47'),
(32, 16, 'T1', 2, 'accepted', '2026-05-06 12:50:02', 3, NULL, NULL, NULL, NULL, '2026-05-06 12:50:14', NULL, '2026-05-06 12:50:00', '2026-05-06 12:50:14'),
(33, 17, 'T1', 2, 'cancelled', '2026-05-07 13:13:23', 3, NULL, NULL, '2026-05-07 13:15:20', 2, '2026-05-07 13:13:10', NULL, '2026-05-07 13:13:10', '2026-05-07 13:15:20'),
(34, 18, 'T1', 2, 'accepted', '2026-05-07 13:17:19', 3, NULL, NULL, NULL, NULL, '2026-05-07 13:17:14', NULL, '2026-05-07 13:17:14', '2026-05-07 13:17:19'),
(35, 19, 'T1', 2, 'cancelled', '2026-05-07 15:48:36', 3, NULL, NULL, '2026-05-07 15:48:59', 2, '2026-05-07 15:48:27', NULL, '2026-05-07 15:48:27', '2026-05-07 15:48:59'),
(36, 19, 'T1', 2, 'accepted', '2026-05-10 11:24:06', 3, NULL, NULL, NULL, NULL, '2026-05-10 11:24:00', NULL, '2026-05-10 11:24:00', '2026-05-10 11:24:06'),
(37, 20, 'T1', 2, 'accepted', '2026-05-10 12:00:44', 3, NULL, NULL, NULL, NULL, '2026-05-10 12:00:38', NULL, '2026-05-10 12:00:38', '2026-05-10 12:00:44'),
(38, 21, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-05-10 12:26:53', 2, NULL, NULL, '2026-05-10 12:08:25', '2026-05-10 12:26:53'),
(39, 21, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-05-10 12:26:51', 2, NULL, NULL, '2026-05-10 12:11:54', '2026-05-10 12:26:51'),
(40, 21, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-05-10 12:26:49', 2, NULL, NULL, '2026-05-10 12:13:23', '2026-05-10 12:26:49'),
(41, 21, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-05-10 12:26:47', 2, NULL, NULL, '2026-05-10 12:13:29', '2026-05-10 12:26:47'),
(42, 21, 'T1', 2, 'accepted', '2026-05-10 12:27:12', 3, NULL, NULL, NULL, NULL, '2026-05-10 12:27:10', NULL, '2026-05-10 12:27:10', '2026-05-10 12:27:12'),
(43, 22, 'T1', 2, 'accepted', '2026-05-10 12:37:42', 3, NULL, NULL, NULL, NULL, '2026-05-10 12:37:39', NULL, '2026-05-10 12:28:25', '2026-05-10 12:37:42'),
(44, 23, 'T1', 2, 'cancelled', '2026-05-20 19:20:40', 3, NULL, NULL, '2026-05-20 19:43:48', 2, '2026-05-20 19:20:37', NULL, '2026-05-20 19:20:37', '2026-05-20 19:43:48'),
(45, 23, 'T1', 2, 'cancelled', NULL, NULL, NULL, NULL, '2026-05-20 19:44:09', 2, '2026-05-20 19:44:03', NULL, '2026-05-20 19:44:03', '2026-05-20 19:44:09'),
(46, 23, 'T1', 2, 'cancelled', '2026-05-20 19:49:09', 3, NULL, NULL, '2026-05-20 20:19:05', 2, '2026-05-20 19:49:05', NULL, '2026-05-20 19:49:05', '2026-05-20 20:19:05'),
(47, 23, 'T1', 2, 'accepted', '2026-05-20 20:31:09', 3, NULL, NULL, NULL, NULL, '2026-05-20 20:31:00', NULL, '2026-05-20 20:31:00', '2026-05-20 20:31:09');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `dispatch_urges`
--

CREATE TABLE `dispatch_urges` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `team_code` varchar(20) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `urged_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `acked_by` int(11) DEFAULT NULL,
  `acked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dispatch_urges`
--

INSERT INTO `dispatch_urges` (`id`, `dispatch_id`, `order_id`, `team_code`, `reason`, `urged_by`, `created_at`, `acked_by`, `acked_at`) VALUES
(1, 31, 16, 'T1', NULL, 2, '2026-05-06 12:45:15', 3, '2026-05-06 12:47:31'),
(2, 32, 16, 'T1', NULL, 2, '2026-05-06 12:50:06', 3, '2026-05-06 12:50:11'),
(3, 32, 16, 'T1', NULL, 2, '2026-05-06 12:50:14', 3, '2026-05-06 12:50:16');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dispatcher_id` int(10) UNSIGNED DEFAULT NULL,
  `order_seq` int(10) UNSIGNED NOT NULL,
  `order_month` tinyint(3) UNSIGNED NOT NULL,
  `order_year` smallint(5) UNSIGNED NOT NULL,
  `assigned_team_code` varchar(20) DEFAULT NULL,
  `assigned_team_type` enum('T','P','S') DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `order_type` enum('nagłe','planowe') NOT NULL,
  `urgency` enum('zwykłe','pilne','natychmiast') NOT NULL,
  `transport_type` enum('hospital','poradnia','miedzyszpitalna','dom') NOT NULL,
  `needed_team` enum('T','P','S') NOT NULL,
  `sirens` tinyint(1) NOT NULL DEFAULT 0,
  `planned_at` datetime DEFAULT NULL,
  `patient_first_name` varchar(80) NOT NULL,
  `patient_last_name` varchar(80) NOT NULL,
  `patient_position` enum('chodzi','siedzi','leży') NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `interview_oxygen` enum('tak','nie','nie_wiadomo') NOT NULL,
  `interview_conscious` enum('tak','nie','nie_wiadomo') NOT NULL,
  `interview_notes` varchar(255) DEFAULT NULL,
  `icd10_none` tinyint(1) NOT NULL DEFAULT 0,
  `icd10_code` varchar(16) DEFAULT NULL,
  `icd10_name` varchar(255) DEFAULT NULL,
  `order_description` text DEFAULT NULL,
  `from_infra` enum('dom','blok mieszkalny','szpital','poradnia','inne') NOT NULL,
  `from_city` varchar(120) NOT NULL,
  `from_postcode` varchar(20) DEFAULT NULL,
  `from_street` varchar(160) NOT NULL,
  `from_number` varchar(40) NOT NULL,
  `from_flat` varchar(40) DEFAULT NULL,
  `from_display` varchar(255) DEFAULT NULL,
  `from_lat` decimal(10,7) DEFAULT NULL,
  `from_lon` decimal(10,7) DEFAULT NULL,
  `to_infra` enum('dom','blok mieszkalny','szpital','poradnia','inne') NOT NULL,
  `to_city` varchar(120) DEFAULT NULL,
  `to_postcode` varchar(20) DEFAULT NULL,
  `to_street` varchar(160) DEFAULT NULL,
  `to_number` varchar(40) DEFAULT NULL,
  `to_flat` varchar(40) DEFAULT NULL,
  `to_display` varchar(255) DEFAULT NULL,
  `to_lat` decimal(10,7) DEFAULT NULL,
  `to_lon` decimal(10,7) DEFAULT NULL,
  `distance_km` decimal(8,1) DEFAULT NULL,
  `status` enum('new','assigned','done','cancelled','odwolany') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `patient_weight_kg` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `dispatcher_id`, `order_seq`, `order_month`, `order_year`, `assigned_team_code`, `assigned_team_type`, `assigned_at`, `assigned_by`, `order_type`, `urgency`, `transport_type`, `needed_team`, `sirens`, `planned_at`, `patient_first_name`, `patient_last_name`, `patient_position`, `phone`, `interview_oxygen`, `interview_conscious`, `interview_notes`, `icd10_none`, `icd10_code`, `icd10_name`, `order_description`, `from_infra`, `from_city`, `from_postcode`, `from_street`, `from_number`, `from_flat`, `from_display`, `from_lat`, `from_lon`, `to_infra`, `to_city`, `to_postcode`, `to_street`, `to_number`, `to_flat`, `to_display`, `to_lat`, `to_lon`, `distance_km`, `status`, `created_at`, `updated_at`, `patient_weight_kg`) VALUES
(12, 2, 1, 4, 2026, NULL, NULL, NULL, NULL, 'nagłe', 'zwykłe', 'hospital', 'P', 0, NULL, 'Bartosz', 'Kowalski', 'chodzi', '+48 880 299 314', 'nie', 'tak', NULL, 1, NULL, NULL, 'gg', 'dom', 'Katowice', '40-010', 'Warszawska', '18', NULL, 'Kościół Zmartwychwstania Pańskiego w Katowicach', 50.2589530, 19.0268949, 'szpital', 'Katowice', '41-006', 'Warszawska', '11', NULL, NULL, 50.2587110, 19.0254172, 0.1, 'new', '2026-04-23 15:12:15', '2026-04-23 15:53:24', NULL),
(13, 2, 2, 4, 2026, 'T1', 'T', '2026-04-27 12:08:28', 2, 'nagłe', 'zwykłe', 'hospital', 'T', 0, NULL, 'Bartosz', 'Stromek', 'chodzi', '666 666 666', 'nie', 'tak', NULL, 0, 'B00.1', 'Pęcherzykowe zapalenie skóry wywołane przez wirus opryszczki', 'ble ble ble', 'dom', 'Chorzów', '41-500', 'Pocztowa', '1', NULL, NULL, 50.2988771, 18.9517136, 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 1.0, 'done', '2026-04-27 11:37:52', '2026-04-27 12:10:32', NULL),
(16, 2, 1, 5, 2026, 'T1', 'T', '2026-05-06 12:50:00', 2, 'nagłe', 'zwykłe', 'hospital', 'P', 0, NULL, 'Bartosz', 'Stromek', 'chodzi', '880 299 314', 'nie', 'tak', 'hhg', 1, NULL, NULL, 'vhgh', 'dom', 'Tarnowskie Góry', '42-600', 'Śląska', '4', NULL, 'Ratusz w Tarnowskich Górach', 50.4441102, 18.8556590, 'szpital', 'Tarnowskie Góry', '42-612', 'Pyskowicka', '4', NULL, NULL, 50.4399208, 18.8182724, 2.7, 'done', '2026-05-06 12:39:14', '2026-05-07 13:09:47', NULL),
(17, 2, 2, 5, 2026, 'T1', 'T', '2026-05-07 13:13:10', 2, 'nagłe', 'zwykłe', 'hospital', 'T', 0, NULL, 'Jan', 'Chudy', 'siedzi', '987 656 432', 'nie_wiadomo', 'tak', NULL, 0, 'I70.9', 'Miażdżyca uogólniona i nieokreślona', 'przekazanie na ip Godula', 'blok mieszkalny', 'Ruda Śląska', '41-709', 'Pokoju', '12', '1', NULL, 50.2843987, 18.8815873, 'szpital', 'Ruda Śląska', '41-703', 'Karola Goduli', '24', NULL, 'Stary Szpital', 50.3131065, 18.8870025, 3.2, 'done', '2026-05-07 13:13:06', '2026-05-07 13:14:26', NULL),
(18, 2, 3, 5, 2026, 'T1', 'T', '2026-05-07 13:17:14', 2, 'nagłe', 'zwykłe', 'hospital', 'P', 0, NULL, 'Jan', 'Świącik', 'chodzi', '124054303', 'nie', 'tak', NULL, 1, NULL, NULL, 'opsi sovoisd', 'poradnia', 'Chorzów', '41-500', 'Świętego Pawła', '11a', NULL, NULL, 50.3083549, 18.9450269, 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 1.8, 'done', '2026-05-07 13:17:04', '2026-05-07 14:58:23', NULL),
(19, 2, 4, 5, 2026, 'T1', 'T', '2026-05-10 11:24:00', 2, 'planowe', 'zwykłe', 'poradnia', 'T', 0, '2026-05-10 17:00:00', 'Adam', 'Małysz', 'siedzi', '666 666 666', 'nie', 'tak', NULL, 0, 'R30.0', 'Bolesne lub utrudnione oddawanie moczu', 'wymiana cewnika', 'blok mieszkalny', 'Chorzów', '41-530', 'Księcia Władysława Opolskiego', '11', '121', NULL, 50.2938618, 18.9472898, 'poradnia', 'Chorzów', '41-513', 'Wolności', '91', NULL, NULL, 50.2891770, 18.9425825, 1.2, 'done', '2026-05-07 14:50:22', '2026-05-10 11:43:52', 67),
(20, 2, 5, 5, 2026, 'T1', 'T', '2026-05-10 12:00:38', 2, 'nagłe', 'zwykłe', 'hospital', 'T', 1, NULL, 'Jan', 'Stromek', 'siedzi', '987 656 432', 'nie', 'tak', 'test', 0, 'R10.4', 'Inny i nieokreślony ból brzucha', 'bke ble', 'blok mieszkalny', 'Katowice', '40-870', 'Chorzowska', '11', '103', 'La Clave', 50.2661209, 19.0184706, 'szpital', 'Katowice', '40-760', 'Medyków', '10', NULL, NULL, 50.2266573, 18.9551131, 6.3, 'done', '2026-05-10 11:14:37', '2026-05-10 12:13:43', 107),
(21, 2, 6, 5, 2026, 'T1', 'T', '2026-05-10 12:27:10', 2, 'nagłe', 'pilne', 'hospital', 'T', 0, NULL, 'Adam', 'Stromek', 'leży', '987 656 432', 'nie', 'tak', NULL, 1, NULL, NULL, 'jadą z orenżadą :P', 'blok mieszkalny', 'Katowice', '40-015', 'Francuska', '18', '10', NULL, 50.2553889, 19.0277216, 'szpital', 'Katowice', '41-006', 'Warszawska', '18', NULL, 'Kościół Zmartwychwstania Pańskiego w Katowicach', 50.2589530, 19.0268949, 0.4, 'done', '2026-05-10 12:02:20', '2026-05-10 12:37:36', 52),
(22, 2, 7, 5, 2026, 'T1', 'T', '2026-05-10 12:37:39', 2, 'nagłe', 'zwykłe', 'hospital', 'P', 0, NULL, 'Dawid', 'Kowalski', 'chodzi', '-', 'nie', 'tak', 'i10', 1, NULL, NULL, '5et', 'blok mieszkalny', 'Świętochłowice', '41-604', 'Juliana Zubrzyckiego', '58', '15', NULL, 50.3007886, 18.9132755, 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 2.3, 'done', '2026-05-10 12:28:13', '2026-05-10 12:50:31', 87),
(23, 2, 8, 5, 2026, 'T1', 'T', '2026-05-20 20:31:00', 2, 'planowe', 'zwykłe', 'poradnia', 'T', 0, '2026-05-20 19:20:00', 'Jan', 'Umok', 'leży', '456123431', 'nie', 'tak', NULL, 0, 'I25.0', 'Choroba serca i naczyń krwionośnych w przebiegu miażdżycy', NULL, 'dom', 'Ruda Śląska', '41-712', 'Podlas', '11', NULL, NULL, 50.2456002, 18.8314731, 'poradnia', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 18.9, 'done', '2026-05-11 11:31:21', '2026-05-27 13:51:08', 60),
(24, 2, 9, 5, 2026, NULL, NULL, NULL, NULL, 'nagłe', 'zwykłe', 'dom', 'T', 0, NULL, 'Bartosz', 'Stromek', 'siedzi', '123 456 789', 'nie', 'tak', NULL, 1, NULL, NULL, 'odwóz', 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 'blok mieszkalny', 'Chorzów', '41-500', 'Katowicka', '111', '1', 'Hala Targowa w Chorzowie', 50.3009416, 18.9514240, 1.2, 'new', '2026-05-11 11:31:25', '2026-05-11 11:31:25', 75),
(25, 2, 10, 5, 2026, NULL, NULL, NULL, NULL, 'nagłe', 'zwykłe', 'miedzyszpitalna', 'T', 0, NULL, 'Grzegorz', 'Małysz', 'chodzi', '345 678 900', 'nie', 'tak', NULL, 1, NULL, NULL, 'konsultacja naczyniowa', 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 'szpital', 'Bytom', '41-902', 'Aleja Legionów', 'Aleja Legionów', NULL, 'Aleja Legionów', 50.3530181, 18.9172519, 14.1, 'new', '2026-05-11 11:55:37', '2026-05-11 11:55:37', 70),
(26, 2, 11, 5, 2026, NULL, NULL, NULL, NULL, 'nagłe', 'zwykłe', 'hospital', 'T', 0, NULL, 'Adam', 'Kowalski', 'leży', '666 666 666', 'nie', 'tak', NULL, 1, NULL, NULL, 'tteds', 'dom', 'Świętochłowice', '41-604', 'Wiosenna', '1', NULL, 'I Liceum Ogólnokształcące im. Jana Kochanowskiego w Świętochłowicach', 50.2906539, 18.9297138, 'szpital', 'Chorzów', '41-530', 'Strzelców Bytomskich', '11', NULL, 'Zespół Szpitali Miejskich w Chorzowie', 50.2919398, 18.9428060, 0.9, 'new', '2026-05-11 12:09:44', '2026-05-11 12:09:44', 42),
(27, 2, 12, 5, 2026, NULL, NULL, NULL, NULL, 'nagłe', 'zwykłe', 'hospital', 'T', 0, NULL, 'Bartosz', 'Swendek', 'siedzi', '123456789', 'tak', 'tak', NULL, 1, NULL, NULL, 'odwóz z szp.w Sieradzu o. wew. Fv 44 kwota 300zł', 'szpital', 'Sieradz', '42-217', 'Armii Krajowej', '7', NULL, 'Salonik fryzjerski', 51.5817929, 18.7109296, 'blok mieszkalny', 'Częstochowa', '98-209', 'Juliusza Słowackiego', '29', '56', NULL, 50.8033485, 19.1057427, 90.8, 'odwolany', '2026-05-16 16:23:20', '2026-05-16 16:45:54', 110);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `order_cancellations`
--

CREATE TABLE `order_cancellations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `cancelled_by_user` int(11) DEFAULT NULL,
  `cancelled_by_name` varchar(120) NOT NULL,
  `cancelled_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_cancellations`
--

INSERT INTO `order_cancellations` (`id`, `order_id`, `cancelled_by_user`, `cancelled_by_name`, `cancelled_at`) VALUES
(1, 27, 2, 'a', '2026-05-16 16:45:46'),
(2, 27, 2, 'a', '2026-05-16 16:45:46'),
(3, 27, 2, 'a', '2026-05-16 16:45:47'),
(4, 27, 2, 'a', '2026-05-16 16:45:47'),
(5, 27, 2, 'a', '2026-05-16 16:45:47'),
(6, 27, 2, 'a', '2026-05-16 16:45:51'),
(7, 27, 2, 'a', '2026-05-16 16:45:53'),
(8, 27, 2, 'a', '2026-05-16 16:45:53'),
(9, 27, 2, 'a', '2026-05-16 16:45:54');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `teams`
--

CREATE TABLE `teams` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` enum('T','P','S') NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `code`, `type`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'T1', 'T', 'T1', 1, '2026-04-21 12:09:59', '2026-04-21 12:09:59'),
(2, 'T2', 'T', 'T2', 1, '2026-04-21 16:17:25', '2026-04-21 16:17:25');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `team_events`
--

CREATE TABLE `team_events` (
  `id` int(11) NOT NULL,
  `team_code` varchar(20) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `event_type` varchar(60) NOT NULL,
  `message` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_by` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_events`
--

INSERT INTO `team_events` (`id`, `team_code`, `order_id`, `event_type`, `message`, `created_by`, `created_at`, `read_by`, `read_at`) VALUES
(1, 'T1', 15, 'Błędny adres', 'Poprawiono: Skąd\nByło: Świętochłowice, Juliana Zubrzyckiego 58\nJest: Świętochłowice, Juliana Zubrzyckiego 58/15', 3, '2026-04-30 17:05:38', 2, '2026-04-30 17:05:45'),
(2, 'T1', 15, 'Błędny adres', 'Poprawiono: Skąd\nByło: Świętochłowice, Juliana Zubrzyckiego 58\nJest: Świętochłowice, Juliana Zubrzyckiego 48', 3, '2026-04-30 17:09:00', 2, '2026-04-30 17:09:23'),
(3, 'T1', 15, 'Odmowa przyjęcia', 'Lekarz: Janusz Chuj\nPowód: Bo tak', 3, '2026-04-30 17:10:09', 2, '2026-04-30 17:10:13'),
(4, 'T1', 15, 'Długi czas oczekiwania', NULL, 3, '2026-04-30 17:19:05', 2, '2026-04-30 17:19:10'),
(5, 'T1', 15, 'Długi czas oczekiwania', 'Uwagi: Długa kolejka karetek', 3, '2026-04-30 17:19:47', 2, '2026-04-30 17:19:55'),
(6, 'T1', 15, 'Długi czas oczekiwania', NULL, 3, '2026-04-30 17:19:59', 2, '2026-04-30 17:20:14'),
(7, 'T1', 15, 'Długi czas oczekiwania', NULL, 3, '2026-04-30 17:20:00', 2, '2026-04-30 17:20:14'),
(8, 'T1', 15, 'Długi czas oczekiwania', NULL, 3, '2026-04-30 17:20:05', 2, '2026-04-30 17:20:14'),
(9, 'T1', 15, 'Odmowa pacjenta/opiekuna', 'Powód: Bo tak\n\nPodpis: #SIG-1', 3, '2026-05-06 12:15:43', 2, '2026-05-06 12:15:50'),
(10, 'T1', 15, 'Odmowa pacjenta/opiekuna', 'Powód: Test\nUwagi: Ttyu\n\nPodpis: #SIG-2', 3, '2026-05-06 12:20:09', 2, '2026-05-06 12:20:27'),
(11, 'T1', 16, 'Długi czas oczekiwania', NULL, 3, '2026-05-06 20:32:17', 2, '2026-05-06 20:32:36');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `team_event_files`
--

CREATE TABLE `team_event_files` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `team_code` varchar(20) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `file_type` varchar(40) NOT NULL,
  `mime_type` varchar(80) NOT NULL,
  `data` longblob NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_event_files`
--

INSERT INTO `team_event_files` (`id`, `event_id`, `team_code`, `order_id`, `file_type`, `mime_type`, `data`, `created_at`) VALUES
(1, 9, 'T1', 15, 'patient_refusal_signature', 'image/png', 0x89504e470d0a1a0a0000000d49484452000002d70000016808060000009cfad76000001000494441547801ecddcb8e24599e1760b3ac9ae911a08c9440193dcc0204abca8469b147a27b3f8be63d58f41b74cf1bf4029e8346623f25f102d34c67d60ac4669a8a14888890400c5355cef9c72523c2ddccfc669763e77cae3c19ee763997ef9887ffc2c2dce355e34680000102040810204080c02802c2f5288c2a2140601a01b5122040800081750908d7eb9a2fbd254080000102047211d00f021d02c275078a45040810204080000102044e1110ae4f51b3cf1402ea2440800001020408ac5e40b85efd141a00010204084c2fa0050204081c26205c1fe6642b020408102040800001027b051609d77b7b6503020408102040800001022b1410ae573869ba4c80c0a4022a274080000102270b08d727d3d9910001020408102030b780f6721710ae739f21fd234080000102040810588d8070bd9aa9d2d12904d44980000102040810185340b81e53535d0408102040603c01351120b04201e17a8593a6cb040810204080000102790ad413aef3f4d72b020408102040800081820484eb8226d350081058af809e13204080401902c27519f368140408102040800081a904d47b8480707d04964d091020408000010204080c0908d7433ad6119842409d04081020408040b102c275b1536b600408102040e078017b1020709e80707d9e9fbd091020408000010204087c1610ae3f534c71479d040810204080000102350908d735cdb6b1122040e0b980fb0408102030ba80703d3aa90a091020408000010204ce1558ebfec2f55a674ebf091020408000010204b21310aeb39b121d223085803a09102040800081390484eb3994b541800001020408f40b5843a02001e1baa0c93414020408102040800081650584eb65fda7685d9d0408102040800001020b0908d70bc16b96000102750a1835010204ca1610aecb9e5fa323408000010204081038546084ed84eb11105541800001020408102040200484eb505008109842409d0408102040a03a01e1baba293760020408102040a0691810984640b89ec655ad040810204080000102150a08d7154efa14435627010204081020408040d308d78e0202040810285dc0f8081020309b80703d1bb5860810204080000102044a17383e5c972e627c040810204080000102044e1410ae4f84b31b0102790ae8150102040810585240b85e525fdb040810204080404d02c65a8180705dc1241b220102040810204080c03c02c2f53cce5a9942409d0408102040800081cc0484ebcc26447708102040a00c01a32040a04e01e1bace79376a02040810204080008109045612ae2718b92a091020408000010204088c2c205c8f0caa3a02042a1430640204081020f020205c3f40f87298c0ebb75ffd7071f96e13e5b03d6c458000010204082c29a0ed790584eb79bd57dd5a04ea36dd1e071141fbf1beaf0408102040800001024d235c3b0a4e164839bb3d79e7d5eea8e304081020408000817e01e1badfc61a0204081020b02e01bd2540607101e17af129584707e29290ed9ede5c7d74e67a1bc563020408102040a06a01e1ba7ffaad21408000010204081020709480707d14978d091020908b807e10204080408e02c2758eb392599fba3e1564936e99755377081020408000815c042aee87705df1e41f3af4ae4f05b9fdf48d63e75040db1120508dc0c5e5fbff7571f96ef3e6c7efbfae66d0064a80c00b0101e905870704b214d02902045620701fa8376fa2abe9977bffeacde5fb5fc47d850081ba0484ebbae67b94d1a6178dcd2815a98400010205096c36cd4f9e0f277da3fce5f3c7e5de373202049e0b08d7cf35dcdf11885f6f6e2f7449c8b688c7040810689ab66d7edbb8112050bd80709dd921a03b04081020b052814df39be6c56d737789c88b451e102050bc80705dfc141b200102044613501101020408ec1110aef700d5bcbaeb92107f95b1e623c2d809101812b8befaf0ebedf5ded4b82de231812905f2a85bb8ce631ef482000102048a1068af8b188641102070b280707d329d1d09942d607404088c20d0363f6fdc0810a84a40b8ae6aba0f1fac4b420eb7b225010204fa04b63f9eaf6f3bcb8f16b003816c0584eb6ca746c708102040606d026dd3fcf9dafaacbf04088c2b205c8febb9cedaf49a000102042612f0717c13c1aa9640b602c275b653b35cc75ebffdea87edd6fd55c66d118f0910984b60ededf8c490b5cfa0fe13384e40b83eceab8aaddb74db1ea8bfcab82de2310102047605ba3e8e6f772b4b08102848606728c2f50e89050408102040e01c81d6c7f19dc3675f022b1710ae573e81ba4fa02801832150a0c0a6697e59e0b00c8900811e01e1ba07a6d6c53e82afd699376e0204c61268dbe6b763d5a59ebc04f486c02102c2f5214ab6214080000102870a6c9adf346e0408542b205c573bf54b0f5cfb040810a84560f3a696911a2701024d235c3b0a3e0bb824e433853b0408d42e70c6f8bb3e31c4c7f19d016a57022b1310ae573661ba4b800001022b1468dbbf5c61af759900811304e608d72774cb2e040810204060d5025b61fa879fae7a343a4f80c0c102c2f5c154656fe89290b2e7d7e88604ac2330be40db36ff61fc5ad54880c01a0484eb35cc923e12204080c0ca045e7dfdd4e1f6ebeb6f3ffeeae9b17b048e10b0e9ea045eadaec73a4c80000102043217b8fef6775fdf5c7d6cdbf6d5cf6eae3efc2cf3eeea1e0102230a08d72362aeb5aad76fbffa61bbef9b74db5e56c06343204080c0ac0211b2676d506304082c2e205c2f3e05cb77209d5a69b77b71fbe91bc7c6368ac7040810985440e504089420204095308bc6408000010204081020908540b1e13a0b5d9d204080000102040810a84a40b8ae6aba7707eb23f8764d2c213083802608102040a05001e1bad089352c02040810204080c06902f63a4740b83e47cfbe040810204080000102049e0908d7cf306abbeb929079665c2b040810204080403d02c2753d736da40408102040605bc0630204461610ae4706551d010204081020408040bd02c2f59873bfa2ba5ebffdea87edeede5c7ddcf96332dbdb784c8000010204081020d02f205cf7db14bda6ebaf32163d60832340a0414080000102d30b08d7d31b6b8100010204081020406058a098b5c275315369200408102040800001024b0b08d74bcf80f6094c21a04e020408102040601101e17a11768d1220408000817a058c9c40c902c275c9b36b6c040810204080000102b30a08d7b3724fd1d869756ed2edb43ded458000010204081020d027205cf7c9584e80000102e70ba881000102950908d7954db8e1122040800001020408dc0b4cf1bf703d85ea0aeabcfdf4cdcedc77fdd5c6150c45170910204080000102d908ec04ac6c7aa6230408ac4c407709102040800001e1da3140800001020408942f608404661210ae6782d60c010204081020408040f902c275f9737cf008db743b70639b2d28f0faed57df5f5cbedb2cd8054d132040800001023d02c2750f8cc504721488509d7e06ba7bdec6fd2839f6539f082c2ba0750204082c2770f722bd5cf35a2640e05c8108d8e96cf677e7d6637f020408102040e07c81bde1fafc26d44080c0d402e96cf61711b2a76e47fd040810204080c0b080703dec53f4da4dba153dc00a0717013b4a454337540204081020909580709dd5742cdf99d76fbffa61f95ee8c1b90202f6b982f6274080c01802eaa85140b8ae71d61fc6dcf5571adb747b58ed4b6602e9079fef8fe95204ec28c7ec635b020408102040e03c01e1fa3cbfd5efbd49b7b50c423f77056eae3eb65176d73c2d89801de569897b040810204080c05402c2f554b22bae379d217569c8cae66f5fc08ee108d8a1a010984c40c5040810b81310aeef18eafdcfa521eb99fbb66d079faf11b0a30c8d28027694a16dac234080000102044e17187cb13ebdda33f7b43b0102270b44c0de6c3683d76747c08e72722376244080000102043a0584eb4e96ba16a620b6f3a7b45d1ab2ee6320fd46e2cb08d9fb462160ef13ea5e6f290102040810e81310aefb642a5a9e82d8ce71d0a65b4504c50e35027694a10146c08e32b48d7504081020b01a011d5d586027542ddc1fcd13203081c0be801d4d0ad8a1a0102040800081f30484ebf3fc8ad9dba5213d5359d0e208d851868614013bcad036d611204080000102fd02c275bf4d556b5c1a52cf7447c08e3234e208d85186b6b18e0081e505f4800081fc0484ebfce6448f08cc22b02f60472704ec50500810204080c0e102c2f5672b775c1ab2ae6320cdd7d97fec27027694a19147c08e32b48d75040810204080c0bd80707defe0ff24d07569485aec5f050211b05358f7d9d839cfb5be11204080c02a0484eb554cd3729d6cd36db9d6b53c24907e18fa6268fdb1eb527d3e1bfb5834db13204080c09d80ff9e0484eb270bf792403a7be90fca2487dcfebd7efbd5e059e531fb1b67b1a30cd51997894419dac63a020408102050a380705de3ac0f8c399dbd744c0cf8ccb32a8f56f605ece86504ec14fcbf8bfb0a010204081020d0348294a360af409b6e7b37b241910211b0a30c0d2e1d1e5f44c81edac63a02040a1230140204060584eb419e3a57765d1a52a7443ea34e0176d1e76a04ec28432211b0a30c6d631d0102040810285d60d117ecd2710f185f969b745d1a927ef57ff6c7be6539589d3a4a605fc08eca04ec505008102040a05601e1bad6993f72dce9cc697be42e362f5420027694a1e145c08e32b48d756b10d04702040810385640b83e56ac92ed5d1a52c9449f31cc08d8e93819fc149308d851ce68c6ae0408102040a05b20d3a5c275a613b374b75c1ab2f40caca3fd749cf86cec754c955e12204080c04c02c2f54cd02534e3d2901266b1770c67ad88b3d851862a8933d85186b6b18e0001020408ac5d40b85efb0c4ed8fff42bff9d3f28336173aa2e40605fc08e2146c07efdf62b9f8d1d180a0102070ad88cc07a0484ebf5ccd5ec3d4dbff2df393e5228f2a921b3cfc46e83e9079f6ce721027694dd5e3f2d49bf05f1d9d84f1cee112040804041023be1a9a0b1194a8fc0398b5328f2a921e70056b46f04ec2843438eb3d85186b6b18e0001020408ac4940b85ed36c2dd0d77486d4a5210bb897d4e4be801d6315b043417910f085000102ab1610ae573d7dcb74dea521cbb83f6ff5f6d3375f3c7f9cfbfd08d85186fa19013bcad036d61120408000816505f6b72e5cef37aa7a8b14e2768e119786cc7b48a41f66063f4b7adede9cd75a04ec2843b508d8433ad61120408040ee023bc129f70eebdffc025d97860840f3cf43492d3e06ecbe3139befa642c2740800081dc0584ebdc672883fe759dbd8e6ea533aad97e6245f44fc95b20027694be5e46c08ed2b7de720204084c2ca07a02270908d727b1d5b753d7d96b9787cc731c24e7a29fa743013b8405ec505008102040602d0245bf68af6512d6d0cfbeb3d707079f350c521f17138880bdd934bd7f58c671b6d8d46898000102048e1410ae8f04ab79f308405de3177cba542c3b56e0f6d3c73fe83bc6a22ec75928285309a897000102630908d7634956524fd7e5213174d75f87823286c0be80fdfaedbbbf1da31d7510204080008129042608d75374539db908dc7efae65557c06ed32d973eeac7fa05860276db365f3a8bbdfe393602020408942a205c973ab3138e2b027657f5024f978a65a70a44c09ee43aec533bf2063eef0000100049444154643f0204081020708080707d00924d760522f8ec2e6d1a978774a95876aac0adebb04fa5b31f01022b15d0edf50b08d7eb9fc3c546e0f29065e8937b759f2fdef7c35ccc40fcc6c475d821a110204080400e02c2750eb3b0d23edc7efaa6f3f889b093c790f4a22481a180ed3aec9266da58081020b06e81ce70b4ee21e9fd9c027d8147c09e7316ea692b8e37d761d733dfc58fd4000910285240b82e725ae71d54049eae165d7fdda572feb2f41b832fceaf65bd35dcba0e7bbd93a7e7040810a840a094705dc154e53dc44dba6df7b04db7ed651e1f27907e40f9feb83dead9baef87ba1088df9cb80e3b241402040810985b40b89e5bbcd0f66e5d7f5de8cce63daca180ddb6397d1e76de8e7a4780000102e30908d7e359565f535fd089b388d5e300984ca0efb87b6cd0f1f728e12b0102047a042c1e5540b81e9553659b74eb5278fdf6abea3e3eaecbe1d8656ddb7a8e1e801601bbb6373abebe7cf77fe30787fbf2fe7f1fc06413020408109841c00bf70cc83535d17779480a896d250e86b990c06d456f748c409d9e503f7aa2defc9d58f6589e96bb4780000102730b08d7738b57d05e9c45ec1a66bcf0772db78cc098027dc75fb411c7600d6f748c713e2f317685c0bd80ff0910985a40b89e5ab8d2fafb028ecb432a3d20661e76dff117dd682b7ca3e3f3a07d71e91292380e140204084c25205c9f216bd761814dba6d6fd1a6dbf6328f094c211001bbd4ebb0374df337a79bb984e4743b7b12204060bf8070bddfc816270adcfa78be13e5ec3696c06da1d761df5e7dfca3038c0edae4e2f2dde6793968271b1120408040af8070dd4b63c5180271f6b0ab9e7831ef5a6e19812904fa8ec3682b8ec552aec38e7146699af6ff3427dec2e3a9b884e44446bb1120b057a0dc0d84eb72e7369b916dd2adab33aebfee52195e96287da4e13051efdafbd0d9bdba5de175d8439786dc5c7df8bb31dec7d23dea4396ba84e41025db102040e0b98070fd5cc3fd49046e3f7df32a85c294055e56dfa6dbcb251e9d2a60bfc304226c967a1d7608c4675fc7d7ed12e37e5eb6d71ffaf8f18cf6a1dbdb8e000102350a08d735cefa02638e80ddd56cbc58772db78cc05402b7855c87dd75ddf5cbcfbeee177c19b48fbf84249eb751fa5bb086c00b010f085425205c5735ddcb0e365ed0bb7ae0f2902e95ee65b79fbef9a27b8da5c70af41d8f514f04c752aec38ef10c95732e2109a7a1baad234080408d02c2f5da667de5fd7579c8e113987ee8f8fef0ad6d798ac050c06edbe6cbdcc3e3a6e323f9fa2e0d39d4274c1e4b73c01b23c328959bc68d00010204ee0484eb3b06ffcd2590cebc761e73e9c539e584b97aa11d024f0211249f1eeddecbf9d8ecba34647704a72f797e563bd5729b4adfbfd78f4e7d1b584e8000815a043a834e2d8337ce6504fac28c17e765e643ab4d13c764296f743cf4baeb63e73d195da492aaefdf339ec3a9388bdd4ff479cd9bcb77bf4f56f119e33fa4af77e5cde5fbff12e5f346ee102030b6c02cf509d7b3306b645ba0ef45faf5dbaf7cd4dc0356dbb69e9f0f16737cb95de91b1dd3af7ccef86b8dc7cb3e3c779dc53e9eeef31e294ca79fe59a3f7e58103fb0dc954db3f92751627d47b90be069f9ddd708e1511eeaf08500818c04bc78673419b575c5f5d72b9ff142bbff101e3b479782cd660d6f743cf7baebcec13f5b988c0e3a8bfd6c1777cf17b80be0a99abbaf11c2a3c431b955eec277dace3f0204161210ae1782d76cd3dc7efaa6f3f88b170a3e04961448e131024c6717daccdee8d875dd75eafc8f3a3b3ff2c207a7deb3d8f15c8e3272b3aa1b1648d3dfb4c9bdfa37440f33594b603a81ce70335d736a26f052e0e1c5f9e5c2f428bd30a4df76a73bfe115848208ecdf4bbfbeffa9a778cdecb242767b1ef2972fbff551ca31797ef7b8fe1dc3aac3f044a1110ae4b99c949c7316de55d9787448baebf0e05654981db955c879d7e129df5baebae3949213bce9876adba5b7671f92edebc97ba7af7b0daffde5cbefbfdf6e0c36ebbb44dfb5fa3a46dc3ecb1a487c7fedb7c7121601f8b667b02670908d767f1d9790c81db9ecb43da741ba37e7510385720824f5f1d1729342e7d1d76d7a521535f77dde5114e51bad63d2e0bafc7fba37d2db0a2ebab0fff34cacdd5c757cf4a9beebf2811c01f4b6288109ebe6cff8b80fd6e9382fd7fde5ee3310102e30b08d7e39baaf1048178c1e8dacd0b71978a654b08f41da3d19736b3ebb0effad434b35c771d6d6d9721abd8369ed751e2be729ec0f543088faf37f741bc6d9ab6f37aeb94bcfff985b3d88d1b81a905b6c3f5d4eda99f40af407a61482f0abbab5d1e726fb2d96c7c4ce13dc562ffc7319aeb75d829382d7e69c8f38909ab28cf976ddfbf4867fdb797797cbec0cdd5872f9b9e80dd347116db75d88d1b81090584eb0971557dbcc026ddb6f76ad36d7b99c7b50a2c3feedb4f1fff602834e6141897b834647b8686ac62dbf08a12f795f1042260a7b3157fd55d6304ecb8065ec8eef6b194c07902c2f5797ef61e59e0b6e7fa6b2fbe2343abee6c81a1d018c7ebdcd761775d779dc2d56297863c070eab28cf976ddf0fb3ed65253e4ebf6178fce331930feffaeae39fdebb775f26e22cf60453a04a024940b84e08fee52570ff62b0dba75a5e7c63e4afdf7eb573cd64fac1e38b58a7e423d077ac460fdbb6f9b2a66336c6bcaf0c79c5bee11525ee2be309dcecbd4cc49b1dc7d3561381a611ae1d05590adc5c7d6cbb3a9642a7eb8ebb602c5b4ce090c03865e7d219f2ff17652da134bca20c99ac652c4363c86d5d04ec7bf7eeb3d8e98cba373be63669fab35a01e17ab553577ec737e9b63dca36ddb697794c606981082d53bdd131827394089c5da56d9b3f88d26710fbf6ad5b7279980db5ff38d6a16d965db7ced6236437deecd8b811985240b89e5257dd6709dc567cfd75dbb69e9b671d3df3ef7c7be21b1d23fc46790c93db5fdb3de1f9809176fe16e880fd26df24027694a186c26368bd75c70b44c04e0785373b1e4f670f02070964f1027e504f6d54a540df0b6fc997870813eb3ed4fb8ed91855cced7619213c47d5bd25427fefca4c560c9945171fcc6ee2be328ec0b5373b8e03a916021d02c275078a4579096cd26dbb476dba9516b0633c1122b6c7eaf1e2024777605f583cbac22377d86c9abf8db2743f8ee9f6435f6f07f679edf931a073e2aa1b6f763c51ce6e04fa0584eb7e1b6b3211b8edb93c24e5ebf49bcd4c3a79663722580f8de721789cd98adde71488394b01f7bb29da4cf5de85e7f81aed6c97db4f1fff30ca146d4f59671ac7452a83cfeb08d8a9388b3de24444c0be77f766c7115967ac4a53b90908d7b9cd88fe740adc7fe3df5d955e6437bb4bd7b524c62058af6bce0eed6d0ab8837f70a6af9e08cd8f258efded92eabd0bcff1b5af8e352f8ff1a6fe3b8b9d10e6fc1721bbf166c7c68dc0b902c2f5b982f69f4de0e10577a7bd38ebbbb3f0c40573ee16fd8e60ddd7e626ddfac6dcb78fe5790a6ccfe363708eafb16ebb44687e2c798e68fa5e259383ce624fdf93ba5a88809d7e7570f49b1ddf5cbefbf7a95cd7a565b404ba0584eb6e174b3315487973e74cf5d059df4c87d144a81eea770a16ed6dcfe530b98e49bf8605624e1fcbedc3651bf175782f6bc32c29f49ec58ee75294b4cd2affa520fbdf8fe8f82c9b5e1ff966c708d5e91bf3cf53b988b988c7b374542304321510ae339d18ddea16b8ed099cf10dbd7b8fbc963a5b9dd77ce8cd3a0452c076167b81a9ba39f0cd8e11aa9f772f1ec7f76421fbb98afb3509d41bae6b9ae5c2c69a5e68d3c99edd41c537f3dda5f92c89fe0d9dad8eb3f27d3f3ce4330a3d21b09c40df73ffb147f11c8bf2f8d8d7f3052260dfbbf7bfd9b1af1521bb4fc6f2d20584ebd267b8d0f14510ed1a5a9c19ee5abee4b2e8d3be17fc78f112ac979ca5fcdad6a36e8178ae44e95e7bbf74dff3ed7e2bff1f231021bbe97db36333788b901d67b1a30c6e682581420484eb4226b2b661f405d1a133c34b18c58bfc509fe287847d4161897e6b9340ee02fb9e37f1dc8b92fb38d6d4bf08d8fbdcfbc613013b8a80dd27b4bae53a3c20205c0fe05895b740df37f95c5e50f7f523fadff74342def27a47200f81780e4519eacdbee7e1d0be73acbbbefaf80fe76867cc36eecdbb2f13796ca76d9acecf228f801d7322643f4af95aa280705de2ac5634a6fb6ff2bb038e4b317697ceb324da8e178fbed676ce56f76d683901020709f47d1f78dc399e8f511e1f2ff53505cadf2fd5f6d8edde0cbed9b16922440fb519eb634e92898fef1b82b26e9502c2f52aa74da79f0b44587dfe38ee0f5d8a11eba72af16231d476840067aba7d2576fcd02f1dc8a326410cfcfa1f5d6750bf42d8d80bdcfbc69daeffbce6237e926642704ff8a1310ae8b9bd2fa06d41756e77c2175b6babee3ce88f314d817f6e2fb42943c7b5f62af365f44808e79d917b2e32c769412158ca92e01e17ad6f9d6d85402f18dbbabee395e44a30d67abbbf42d23b08c407c3f8832d47a3c6f87d65b37ae40786f9af6efc5bcf485ec4dd35c4411b0c7b557dbfc02c2f5fce65a9c4820be6977551d6795bb969fbb2cea8d178ca17afafa34b48f7504161328ace17dcfbf78fe46296cd8190f67f3c5c5e5fbefaeaf3ebe7908d8294bef76372df4971e77592c59918070bda2c9d2d5fd02735d7f7d71f96e3374b63afab1ef857dff686c4180c0b902f13c8c32544f3c9f87d68fb52e85c63f1eabae9ceb6937edbf6b9af6fba6f31601fbdd66737f16fbd543c8eede329dc98eb97126bb93a7ca856b19b470bd9699d2cf8304a6befefad0b3d57dfd3868103622406074814302760a729d1f1f377a672aa8f0e6eac397f7e64321fbfe2c766c2764577050543444e1baa2c9ae65a8f18dba6bac118cbb961fba2cbdf01674b6fad051db8e4039020fdf1b6e0746f43a9ee703ebad3a52204276b3e72c76936ed7571fdfc4fcec0bd96f7efcee3fa5cdfd2390b580709df5f4e8dca9027159c6f6be7119c7a9017bdf0b6ebc28385bbd2dee3181fc04d273f5229594e1fafb16cff7549cc5ee27da5ad37eb7b5e0c5c39b3d67b193f5e6e2f2fd5d1dd72964a7c9f94ddbf7476836cdbf7c51b90704321410ae339c145d3a5fa02fe846c03ea6f608e31797ef367dfb4488dff742ddb7afe504082c27f0f0bc75167bc629b84921bb193c8bfd3960ffebebfb90bdfb034edbfc4de346207301e13af3091aa17bd556f1f0e2b933fea1b0fc7ce3d86e288c47fd7d21fe793dee132090a7407a0e1f7416fbdcde5ffcf8ddff88ef27e7d653c2fe372960a733d37fd53d96fb373b5e3c9cc5deb4cd1f756f672981bc0584ebbce747efce14482f9ee9fbf86e2571467a77e9fd925877e16cf53d86ff094c2eb07c037ddf271e7b16df0fa23c3e3ef4ebe750bd69fefea1fbd4b0ddf5d5c73fbd371f7eb36397c5cdb71f05ee2e18cbb21210aeb39a0e9d9942202eddd8aeb7ef8c74bc80f6ad8b3ae205c1d9ea905008942510cfed2843a38aef0f43eb9fafbbdb764fa8ded7def3fa5675ffd5e6cf0ee9ef4d3a8bdd0c5c26d26c9a1f35cf6f2e0979aee1fe5c0227b4235c9f8066977509f485e1bb17bf87a1ec3b5b1d9b15fb421883530810b813d8f73c8fef1b51ee36eef82fd645e958f5b4a86dfee7be769e362efb5e04ec7b8b31b60f2800000e5a49444154beb3d84fe377d6fac9c2bdbc0584ebbce747ef4612b8ffe6bd5b59bc0846193a5b1d67befbf6dfadd1920101ab08ac42209eef51863a1bdf379eafff7c09c8f385dbf71f42750a89ff607b55ed8f236437bd67b19ba671d6ba715b8f8070bd9eb9d2d3330522241f5b45bcc0f69df93eb62edb1320b02e8178fe0ff53802f663690eb80444a81ed26c9a08d86dd374bfd971d3ce905786fb672d8143051cac874ad96ef502c784e408e2fb5e58570f62000408ec1588ef0351f66ed8b7c1c3d9eabed596bf14b8ee7cb363fbfdcdd5873f7cb9a54704f21510aef39d9b55f72cd7ce1ff22219db1c13c4731dab7e1120309e407c5f38aab68750ed6cf5516a9f374e61facb308f33d971fff30a7708ac4040b85ec124e9e2b802f10dbbab4667abbb542c2350a4c049838aef1d51067716aa07798e5d799dce641fbb8fed092c2d205c2f3d03da5f44a06d5ffdec79c3f182e96cf57311f70910e81388ef175deb62b933d55d329611a84be0fc705d9797d1162270fdedefbebe7b21bcfad8c6d7428665180408cc2410df37a2a4e66ee36b9474df3f02040834c2b583800081a2050c8ec0940229545f4c59bfba0910589f8070bdbe39d363020408102040a00c01a3285040b82e70520d890001020408102040601901e17a1977ad4e21a04e02040810204080c0c202c2f5c213a07902040810a843a0d451b6ede6af4b1d9b7111384540b83e45cd3e040810204080000102043a04561aae3b46621101020408102040800081850584eb852740f3040814286048042a16d86cda3fa978f8864ec0e75c3b060810204080000102350918ebb402ce5c4febab760204081020408000818a0484eb8a26db50a710502701020408102040e04940b87eb2708f0001020408942560340408cc2e205ccf4eae41020408102040800081520584ebc367d69604081020408000010204060584eb411e2b091020b01601fd24408000811c0484eb1c66411f0810204080000102250b543436e1baa2c93654020408102040800081690584eb697dd54e600a017512204080000102990a08d7994e8c6e112040800081750ae83581ba0584ebbae7dfe8091020408000010204461410ae47c49ca22a75122040800081ac057e68ff63d6fdd33902330b08d733836b8e0001020509180a010204086c0908d75b201e1220408000010204089420b0cc1884eb65dcb54a80000102040a15d87c59e8c00c8bc04102c2f5414c3622408000010204081020b05f40b8de6f640b020408102040206f01bd23908d80709dcd54e8080102040810204080c0da0584ebb5cfe014fd57270102040810204080c04902c2f5496c762240800081a504b44b8000819c0584eb9c6747df081020408000010204d624d008d7ab9a2e9d254080000102040810c85940b8ce7976f48d40ed02c64f80000102045626205caf6cc27497000102040810c843402f08740908d75d2a9611204080000102040810384140b83e01cd2e5308a89300010204081020b07e01e17afd7368040408102030b580fa09102070a080707d2094cd081020408000815d81eb4f1ffecdee524b08d42bb044b8ae57dbc80910204080000102048a1610ae8b9e5e832340e078017b102040800081d30584ebd3edec498000010204087408bc79fbfedf762cb6680c0175642f205c673f453a488000010204081020b01601e17a2d33a59f5308a8930001020408102030aa80703d2aa7ca0810204080c05802ea2140608d02c2f51a674d9f091020408000010204b214a8265c67a9af53040810204080000102450908d7454da7c11020b05201dd2640800081420484eb4226d2300810204080000102d308a8f51801e1fa182ddb12204080000102040810181010ae0770ac223085803a09102040800081720584eb72e7d6c80810204080c0b102b62740e04c01e1fa4c40bb13204080000102040810781410ae1f25a6f8aa4e020408102040800081aa0484ebaaa6db60091020f024e01e81f104daefc6ab4b4d04d62d205caf7bfef49e0001020408102050a2c06ac7245caf76ea749c0001020408102040203701e13ab719d11f025308a893000102040810984540b89e8559230408102040a02281579b3f3b66b4b625509280705dd26c1a0b0102040810204080c0a202c2f5a2fc5334ae4e020408102040800081a50484eba5e4b54b8000811a058c99000102850b08d7854fb0e11120408000010204081c2630c656c2f5188aea20408000010204081020900484eb84e01f01025308a8930001020408d427205cd737e7464c8000010204081020309180703d11ac6a091020408000010204ea1310aeeb9bf32946ac4e02040810204080008124205c2704ff08102040a0640163234080c07c02c2f57cd65a224080000102040810285ce0e8705db887e111204080000102040810385940b83e99ce8e04086428a04b04082c20d0b69bbf5ea0594d12c85240b8ce725a748a000102040810284fc0886a1010ae6b986563244080000102330a6c36ed9fccd89ca6086425205c67351d3a738c806d0910204080000102b90908d7b9cd88fe10204080400902c6408040a502c275a5136fd8040810204080000102e30bac235c8f3f6e3512204080000102040810185d40b81e9d54850408d42660bc0408102040e05140b87e94f09500010204081020509e8011cd2c205ccf0cae390204081020509cc0a6bd7e39a6cd772f1f7b44a01e01e1ba9eb936d23104d44180000102bb026df3225cb76d73d5b811a85440b8ae74e20d9b00010204ca13586a449b4df393976db7ffede5638f08d423205cd733d7464a800001020408102030b18070dd0b6c0501020408102040800081e30484ebe3bc6c4d8000813c04f4824056029b372fbab3697ed3b811a85440b8ae74e20d9b00010204081020309540cdf50ad735cfbeb11320408000810904aeaf3efc7a826a5549601502c2f52aa64927eb16307a020408e42bf0e6f2fd2ff2ed9d9e11985f40b89edf5c8b0408102040a01c01232140e0858070fd82c303020408102040e02881b6f979f3e2d6bef883322f567940a00201e13aaf49d61b020408102040800081150b08d72b9e3c5d274080c0bc025a23b05fa06d9bdfeedfca1604ca1510aecb9d5b23234080000102930becfee9f3c99bd400816e814c960ad7994c846e102040800001020408ac5f40b85eff1c1a01812904d4498000810305fc75c603a16c568980705dc9441b2601020408102847c04808e42b205ce73b377a468000010204081020b03201e17a6513364577d54980000102044e11e8faeb8cd7571f7e7d4a5df621508a80705dca4c1a07010204ca14302a020408ac4a40b85ed574e92c0102040810c84860e7af3366d4375d21308bc06e23c2f5ae8925040810204080c04902edf549bbd989404102c2754193692804d62ea0ff040810204060ed02c2f5da6750ff091020408040260285ffe9f34c9475237701e13af719d23f020408102090a9c066d3fc24d3aee91681c50484ebc5e82b6fd8f00910204080000102050a08d7054eaa2111204080c07902f63e5460f3e6c5969be6378d1b81ca0584ebca0f00c3274080000102040810184f6086703d5e67d544800001020408e42be0af33e63b377a369f80703d9fb5960810c851409f08103849e0cde5fb5f9cb4a39d08142e205c173ec18647800001020408ac5740cfd727205caf6fcef49800010204082c2fd0363f6f5edcfc75c6171c1e542b205c573bf5350edc980910204080000102d30a08d7d3faaa9d00010204081c26b0f2addab6f9edca87a0fb04461110ae4761540901020408102040800081a629355c9b5b020408102040604281cda6f9c984d5ab9ac06a0584ebd54e9d8e1320b05e013d2740800081520584eb5267d6b80810204080c0a402fef4f9a4bc4b56aeedb30484ebb3f8ec4c800001020408102040e04940b87eb2708fc01402ea244080401502d7571f7e5dc5400d92c01e01e17a0f90d50408102040a05c81d346f6e6f2fd2f4edbd35e04ca1710aecb9f6323244080000102040810984940b81e115a550408102040a00a81b6f979f3e2d65ebf78e801818a0584eb8a27dfd00910a84ac06009102040600601e17a06644d1020408000815204defcf89ffd74b3d9fca3e7e3f1a7cf9f6bb87f9a40397b09d7e5cca5911020408000814905defcf8ddaf369b1ffe2235f28f53f18f00810e01e1ba03c522026b17d07f0204088c2d701fac9b5f76d57bfded879f762db78c408d02c2758db36ecc0408102040e04081b80ce4e2f2fd5f6c369dc1fa2fdbf6d5cf0eacea71335f09142d205c173dbd064780000102044e17b83f5b1d97816c76ce4cb76df3e737571fffc5f5b7bffbfaf416ec49a03c01e17aed73aaff040810204060028181b3d54d04ebeb6f3ffe6a8266554960f502c2f5eaa7d00008102090af809ead4fe0f13290a6d93d5bdd34edd76dfbea678275e346a05740b8eea5b1820001020408d425b0ff32900f2958bb0ca4aea3a2e8d14e3238e17a12569512204080008175090c5f06e26cf5ba66536f971410ae97d4d7368192048c850081550a1c761988b3d5ab9c5c9d5e4440b85e845da304081020406079819a2e03595e5b0f6a1110ae6b9969e3244080000102cf045c06f20cc35d02230a08d72362d653959112204080c05a05ee2f0379b719fe34109781ac757ef57b7901e17af939d003020408101853405dbd024f9781ec6e129f5d7d73e5d34076652c21709c80707d9c97ad0910204080c02a055c06b2ca69d3e9150aec0bd72b1c922e132040800001028f022e037994f095c03c02c2f53cce5a21406012019512203024e03290211deb084c23205c4fe3aa560204081020b0a880cb4016e5bf6fdcff550a08d7554ebb411320408040c9021797fd9f067273f5b1bdfed6a781943cffc6b6ac8070bdacbfd60f17b025010204081c20109782746df6f869205deb2c2340603c01e17a3c4b351120408040b502390dfcd5d7dbbd69db573fbbfef6e3afb6977b4c80c0f802afc6af528d040810204080c0520271c9479ca5be6fbffdda6520f712fe2730974096e17aaec16b87000102040894281067a92354c71f8529717cc644206781ff0f0000ffffc6a016da00000006494441540300766a4f6719417de90000000049454e44ae426082, '2026-05-06 12:15:43');
INSERT INTO `team_event_files` (`id`, `event_id`, `team_code`, `order_id`, `file_type`, `mime_type`, `data`, `created_at`) VALUES
(2, 10, 'T1', 15, 'patient_refusal_signature', 'image/png', 0x89504e470d0a1a0a0000000d49484452000002d70000016808060000009cfad76000001000494441547801ecdd09946c795d1ff0ffad3733129879dd6f9879dd33a03328c2bcee37202e685804145907b29c9c6812cd72a259044f3466714b84b8e624519323261a63a242d693934476541819d0b8a0465ef7808a30a2d0f5d0335d0f478199d737bf5bddd55dddaf7aa9eaba5577f9f4b9ffaee5defbbfffffe75fddfded7fdfbed5493e0810204080000102040810988a80703d1546951020508e805a091020408040bd0484eb7a8d97d6122040800001025511d00e02230484eb11289e22408000010204081020308980703d899a7dca10502701020408102040a0f602c275ed87500708102040a07c0147204080c0c90484eb9339d98a000102040810204080c0b1027309d7c7b6ca06040810204080000102046a28205cd770d0349900815205544e8000010204261610ae27a6b3230102040810204060d6028e577501e1baea23a47d040810204080000102b51110ae6b33541a5a86803a0910204080000102d31410aea7a9a92e0204081020303d01351120504301e1ba8683a6c9040810204080000102d514684fb8aea6bf56112040800001020408344840b86ed060ea0a0102f515d0720204081068868070dd8c71d40b02040810204080405902ea1d4340b81e03cba6040810204080000102048e1210ae8fd2b18e401902ea244080000102041a2b205c377668758c00010204088c2f600f02044e27205c9fcecfde04081020408000010204760584eb5d8a32eea8930001020408102040a04d02c2759b465b5f091020302ce03e010204084c5d40b89e3aa90a0910204080000102044e2b50d7fd85ebba8e9c76132040800001020408544e40b8aedc906810813204d449800001020408cc4240b89e85b26310204080000102870b5843a04102c27583065357081020408000010204e62b205ccfd7bf8ca3ab930001020408102040604e02c2f59ce01d96000102ed14d06b020408345b40b86ef6f8ea1d0102040810204080c04905a6b09d703d05445510204080000102040810280484eb42412140a00c0175122040800081d60908d7ad1b721d26408000010204526240a01c01e1ba1c57b5122040800001020408b45040b86ee1a097d16575122040800001020408a4245c7b151020408040d305f48f000102331310ae6746ed400408102040800001024d17183f5c375d44ff081020408000010204084c28205c4f0867370204aa29a0550408102040609e02c2f53cf51d9b000102040810689380beb64040b86ec120eb220102040810204080c06c0484ebd9383b4a1902ea2440800001020408544c40b8aed880680e010204083443402f081068a78070ddce71d76b02040810204080008112046a12ae4be8b92a4b1758585a79204a7eb09c3d7fe1919b6f7ee2e34b6f80031020408000010204662c205ccf18bc5d87cbce8cea6f966567ae5e7fc3870f86ee711f2fde76e18e51f57b8ec0cc051c900001020408ec0808d73b106ea62b1041f98194f2c74db7d6fdb56d5d4d1f5c5cbeebcefdcf7a448000010204080c0bb83f5b01e17ab6dead395aafbb1eb3cad9ef97d9e19801cfcaac5fdd0408102040800081710584eb71c56c7f62815e77edf1299517b0f3f84833ff704002040810204080c0e102c2f5e136d64c41a01fb0f3f47bc7551539796b8c92679dfcce2b97efef6c6ebcef43c7d56d3d0102045a23a0a30408cc5d40b89efb1034bf01bdcbeb9fdeebae67e988909d6559e7baebce1481f94c84e6e34a67f3a3f73fd07c393d24408000010204ea26205c1f3e62d64c59a008d94705ecab57b77e776169253f77fbca674cf9d0aa2340800001020408cc4440b89e09b3830c048a809da774e4a91c5b5753fffad84276f241e00801ab08102040a08a02c2751547a5e16dbad25d7f42ff3491943e7c545785eca374ac23408000010215166871d384eb160ffebcbb1e01fb33a21497d33b36649bc59ef768393e81941696567e38cac7a314efbc7a75e1fcc517702140800081fd02c2f57e0f8fe6201001fbd8903d98c56ee9bb32ce61541c92c02881ac78d3a61b77d674b26ceb876f3e7ff1193b8fdd10204080400808d78160a9864011b2a32547ce62e75bd9878a5933213ba42c046628b0b874e1fb52ca9f3e7cc83ca53bb7b2add709d8c32a6dbcafcf04080c0b08d7c31aeecf5da008d851b268c8ef4639742942b6807d288f1504a62f90759e14959e8db26f2902f62329bf69df931e102040a0c502c275c5065f73b6052260df11e5c8905d04ecfe2cf6f25dc59faab777f4990081990b6459fe96b3e7575f38f3033b200102042a28205c57705034694f2002f61df1e8e859ecbcf341213b942c044a14c8f3747d4ae9d09f19fd807debea8b4a6c82aa091020500b8143bf51d6a2f51ad90a812260472966b18f7c57c65cc86ec5eb4127ab2b9075f2379ac1aeeef8681981e60b54a387c27535c6412b4e201001fbce28270cd94f7ec209aab4090102130b648fc4ae7994e1a51333d86f12b08749dc2740a06d02c275db46bc01fd8d807d82907de6778a5345169684ec4987dc7e04060223af1492a77b62fd8f44f9a328c38b803dace13e0102ad1310ae5b37e4cde97011b2a3371f8a72c422641f8163158193091c72a590f81afc3b294bf74525d7cc6077b2fcdf9d3bbffacc5867215086803a09545640b8aeecd068d84904e287fb13a214a78a1c1bb2cf9e5f79d84cf649546d43e0588187b3b4b5556cd5db587f4904ecb7c4fd7d013b1edc9967f96bcfddbaf2ac5867214080406b0484ebd60cf5111d6dc0aa08d8c786ec2c4bd7a554cc64dffd990de87265ba707679e5be85a595de76597d246ef343cae6e2f9d5a756a6e11a726281e3ae147254c0be7a26b906f689a56d488040130484eb268ca23eec0a14213b1e1c338b7df503dbe14fc80eab6397b34babef0eaf43c37396a76266b278739128f999232a5c8899cc5f8fbab6c3f76d777dde11db5a5561812ca5dfcc3b9dde70137702f65b879f2beec7ebe34df10bd88b8bfb9316fb112040a04e02c2759d464b5b4f245004ec28f1f33ffbe0d13b0c42f64a7e7669e5930b4bed0cdbd1f79f8fc05b84e7ab71bb1d7c9756766fb3943f231c2338a7284786e7d86c8c65abf32b43c7db5cbc75f573c6d8dba6331218f5cf8c5b79f68d5736d67ee960132260bf78e714917dabb23cbde1ec6dae81bd0fc50302049a22704d3f84eb6b483cd114815e77ed334f16b253e48174434a7b617be1fcc5cf4a0dfe8850fb1b51fa013a7e0bf9d3d1d508ce699edf0f16f24efe6b83362ddc76f1f3a34d962a081cf2cf8c4734adf8cbd1c70facef645bf91b05ec032a1e1220d0488179fe306d24a84e554f609c90bddbfa6cebb707412f66763fd9a4b01dfdfa8de8e7dd51ca5e1e4a29bb9ac6f9186cbbb5f5cbd1ce7ef88fdbcdc5db579e3658e5b6da02317bfd77e3b7d5d7452b05ec40b01020d03e01e1ba7d63deda1e17213b3aff3b51c65a6266f786341cb66fbdf0d96355d0dc8d233ca72b9dbcf3d4e22f0423ca8d617eddf0f329cfde3301c7427e35fd6a84ec7ed83ebbbcfaf409eab0cb840223fe9971f74a218755b913b0475fa26f2bffb7ae2072989ce7ab2ea07d044e22205c9f44c9368d1188a0f75951b2a244a7c60edab14fca3ad96f0e825e717bb6b9617b3b3ca7fceec26b4489f0bcbef0e0e54bc54c7841736ce95d5efbfc413d1306ed94e5f92f16ee3b6573e1b60b9f7bec816d30738108d82f8d19ec37c781f328bb4b3cb833efa49f8c80fdecdd27dd21408040830484eb060d66bdba32ffd646c8db0dda713f26a8d3072669d570d8bee996bb9e34491df3dee790d9e7edf0dcbdff5219ed1b0edadbfef9af4c709c85b495bd67276817ff98fa8513d461977104f2ad7b7a97efffe993ec5204ec2c656f8a6d2353c7e79d251edc79f54cee127d3b1e6e081068968070ddacf1d49b530844c07b6294feac76fcf0bf3faa8a9bf83cc6d239d379ff20e815b737ddfee4278fb1fb5c368d4e7e226557fb6f08329706ec1cb4d7bdff0b7addf5be7f4a1305ed98284dffb7701f94b34b2bc2f68eef24378bcbab6f88b178fe24fb0ef6d9ecaedd93f254bcc9cce0a9fe6d96676f3cbb7ce125fd0755fca44d040810985040b89e10ce6ecd16b8d25d5fe975d73b5176c25efaed497adcb97ae67d83a05785a0bdb0b45a5c3e6d75b82f67f2ce173e58d2ecf4f071c6b9df1b0adabd08dcb1ef2f47197bc9d25ed83ebb74e18bc6aec00e5311e85d5e7f4904ece214917df545c07efdd9e59517ef7bd203020408d45c6016e1bae6449a4f20a508789f1da51fb463a6772d4ce2263e8fb10c07ed85a595fca6dbefbe6b8cdda7b469fea8a8a8765ff761fff4287dff94b2e2948447d2981f59ca7ea1701f949bcedf555c8270cc5a5abff9c329cb26fb2b47271b7d89be3cbd41c06efdeb0a00814609d4ee876ca3f475a6960231ab7d3182def0acf66f4dd291ced5abf70f82ded9a5954fce276c4fd2f2f9eed3ebaebd20fcaf8fb213b653311b3f76a33a59a778f39c7c61a9ff86399b679757bf60ec4a1abec3882b854cdce3dec6dad746307f6d5470ed25fa04ec60b11020d01401e1ba2923a91f73138890f7a42883a057fcf3dfd8b3da594a370c87ed1b6fbb78616e1daad981c3fe0ba3ecf8676f8be68f3dab1dfb2c6479fe4b835f766e3a7fb17857ca78da322c10afd3f7e759a737fcdc38f78b809da5ec9db1cfc1af914e274f3fe40a222163217050c0e3da0908d7b51b320daeb24084bce2b2757bb3da59f6fe49da7b666b6b7d10f48a596d61fb648a31abfdc21883e159ed5f3cd99efbb7ea645bef1ef8c76d31abddba6b6b2f2e5df8be94f27dfdcef3ad6fbcb2b136d1f9ef03e1cdeeda3d11b0475e4124efa49f58bcedc2170fb6754b8000813a0a08d7751c356d9e5460e6fbc54cdd5dbdeee00a18e9bdd180833376f1d4d14b16b3dac361fbc6e5d595a3f738f9da684c25ae1472f2168fb765d87f5194ed59ed2cbd35f69e74567bf7dada379d5f7d66d4d3f825cfb2e24a3767cbe8e8e676c07e63d41d2fc1f8bcb3c4833bb7f2e4127d3b1e6e0810a8a780705dcf71d3ea1a0af4baeb4f89b237ab9dd2fb26e9c6993c5f8bd9d4ed772b5c5af9e449c3f6424dae143289c949f6e96dacbf28fc8767b57fe124fb1ddca693e5ef5a58ea9fa75d8cc166fc65a12d97fb9bfc9f190f22c6e3cdeedacbe266d41544de7076f9c24b635d0b175d2640a00902c2751346511f6a29d0ebae5f88b23dab9ab2e25d0e63e26ebcaef467b587c2f6b9a50b17c7aba1bd5b87fd33a26cfb67a9b80ef364b3da4397fb3b77ebcab31a239a67d7475f4afd1911fe45882e4e118943ed2d599efd54046cd7c0de23718f00811a0994fa8d739e0ea73df6b9f3ab5fbbb0b4da5d5c5e79e7edb77fdea34f5b9ffd091c25d0ebae3d3582c6f0ac76f1263647ed3272dd56cadebbb033ab1a33aa31abbd32744deb7a5e866f6447a7fc64cc6abf38fc7767b5b394fffc2487d8eaa4fb06fe71db3bdba06b6bc72f72a7fa67c6c33cc37d54c08e3f1064af17b00f53f33c0102551610ae0f199dad2cffde94f2f3799e9efdd0d627be2eadaede70c8a69e26307581081cc59bd86ccfaae6e9d753bc18a38cb54418bae14c9e2e2dec84edd8f9ee289613086c76ef7f668cc1b6ffe4b3da67b3a16b6b1ff847bd13b4627e9becfc33e3be4b134ee39f190fed51961d720d6c01fb50332bcaac1b8600001000494441540810a8ac80703d62688a59eb787a374cc72cd63db73ff8a8ebe2390b81990bf42eaf3f2d82deeeac7696d2facc1bd1e2035e3bab9dde3d0947be95fddce0179db8ed55f94d6ccafc67c65176bd8db557a42cfbc95877cd35b03b79f69a3afd62127db0106880802e9c4640b81ea1f7e0e5b51f8a6ff41f8b55639f031bfb94b69cbb7df5998bcb179f3b28e76ebbf0ad8bcb2baf5a585a7d5b94776c97954f2deccc54c6ede6c2d2ea838b4babef5a5c5ebd7750169657ff713cf7f54e77296da84aad78b3bbbe1a61bb3fab5adcc68bb478c7c8528fa9f23d81f07f56e13e287996deb5b7f6c4f7ce0ebf89cde2f25dcf39f19eb3d87006e75b1fec4611b0b394fd5c3c1f2fe9f8bcb3c4833bd356f6e302f60e881b02042a2f205c1f3244f18dfed363c6e495bd5bb24fdbdc587ff6473ef29e3f3e64d3533d7deef695670dc272fcf0f8b6c57e585ef9e908c53b6179356eb703f3d6d5fc5df1a7d9770ccad656f69d799ebe3da5fccba23c77bba4e29f90066d5a88e716f3943f33cff3e7e43b25e5f9f7c673dfffd0d53f7968612f8817573ee82d8c0ae34b2bdf5484f1a5a5a73c6650b1db930b94bde595eefac541d08b63156f621337c72f115a1a7d19bee305a6b3c595f8fe30f09f3068c79764e7de85a1afc508dbf1f53c9df68d5bcbe2d2caff8aef1bcf3bb0df54af1472a0eedd879bddb59745c07e433c112fcff8bcb3c4833baf6ee5a55c1670e7106e081020303501e1fa08cafe0cf6dadaa78ed8e49a55e76e1f0acbc52cf36d174604e69587073f48b7aea6fbf27c6b3b306f65dfb11d96d3f3e3875bfc70cd77cabec07ccd31a7f844fcf0caaf0de3297d4f9ef2efff447ae48f06eddeb91d84f1772f0ecd8c9fdb09e3b7deba7ae314dba6aa1308f4baebc59bd8ecce6ac72e8786ed3369ebe90f76ef3f747dec6b1953603868c7586411b6ef1bb38afee679de297ea92e7ee1cd671db4f39415bf44ef3f0d2edfbaa7d7bdff67fa8d2bf9d36677ede511b0afb9824827755e7fd3d25df7947cf83656afcf04084c5940b83e25e8b9db579eb5707ef52d1136fb81795f582e42f3c8c09cf6ffe03a651be6b8fb208c3f23df99152f6eb776c2f8a73af9c7c3a51f10e2b61fc417b667c60f86f16f5e5c5afdfa5b6e79b2378f98f260f686c276545dbc894dca53fad4d594dffd60f77dfdc7f1bca5248108db5f1c63d0ff6527cbb26246f6e1710f351cb41762763b7e913d38ab3c6e95876ebf3862d63a82ee43293b33c9650a0f3dce712b36bbfd77712cde64e6b84dad27408040e50484eb530e4984e937a42c7f6154735d8a4fb35af23c7b774ad9bddb257d7796a557a7bcf3822ceb3caf93655f97a5ec1b3a297b5b96653f97454959fa83d87673bb14f92acdfaa31fc46346be98193f18c6bfbb98197ff8cc992b4578d8294784f18bdfb21861fce69b9f1875ceba1bf53d5e6ffb4d6cb22bddf54ffb2333d6331fc8cd8db597c518dc10a51fb6e3abf09d9334227e817dfbced748beb0bcf207e7a6f88e91f98859eb3cbffae77bddb5b74fd256fb102040a08d029d36767a5a7d3e77dbcab3a3aea9cc4247587e57da0dcb5984e6fd81398bd0dcebfea9dd1fcc572eaf3d2b7ee03d6fbbac7febe6c6faab7a972ffdf4e6c6a57b1fdc58fbc1cdeeda0f3cd85d7be1e6c6da738bd2db58bf35b63db75dd677af3c91f2ec6f6711c48bd2393c8c6fa5d97f4470ce174787f1adef2ac2f8d5eb6f8800befb4e79c5fd98295ffd54ccec7d787179e543fdb274f1c716cfaffee04d8fbbebb1b3ef822312385ca07779fd39bd9db7c6cfb2f4fad872ec59ed94a7c76e65c3ef18b9fa2551cfa1cb512b162b326b7d541bad234080401d043a75686455dbf8e047d7efcbfb81388dfc93e981c0fc5df103f4d5299df9b222280f4aaf7bfefae2076c84e56747f0dd09cb6b71bb3f306f46684ee93de3fff03d06af7779ed4736238817e588307ea6b7130252cafe5616613c65d95bb32cebcf8a67719bb2f4b1583798199f53184f374618bf3e66f61e9fe7e98e7e495b7f23cff257741ee9fcc1ee6cdfd2ea230b4bab0f2f2eadfe5e3f8017417c10c26f7fd22dc90781190bc42fc72f8fafb1dd5f9ed3f6f79534fe47feb30b4b3bbf702eaffce138ef1819dfcbf69d6b1d5fe70fe52945bb2a336b9dc7f79a68d2f82af62040a016028d69a4707dcaa1bcd25dbba773263d2f8b99e5a20cc272fca0cc0e04e66f8b1fa0afea75dffb3345501e9494ee1d19cc4fd9acd2768f5f00fefd6684f1dec6da8b367766c58bdbdec6faf9583798191f0ee35f133fa4bf2165d95be2076345c2787e2642f87531fbfdb87e00cf23880f42f8d5eb3eb61b4e4685f0e598113fbffa1f8bd9f01b979f766b69d02a6eb5407c2d15bf60f74f1f8934f9538131fe2fd679baf9c03b467e69d43372595c5af9dff135f1bc912b3d4980000102630908d763718ddef8c18facbf6b73e3d2bd45a95b581edda3e93d1b21e14777c2f88b374f14c63b5fbd1dc6d381309e5d4e29db4c29cd70567c4408cf238867f95f2f66c3cfe49fbcbc1dc447cc840f85f0c79cbfb814ed9eede2688d11b8d25dff33bdeefad0ac767ac7849dfb99edd76bcc6c17b3dadba7b5f5ab8a007fcdac75cad2cbe2eb772ee75a2f2eadfe68346c5fd8df4a5b2fbfb271bf7f720c180b0102d51610aeab3d3ead6b5daf7be93f6cf667c6d70f84f1b5a55e77ed5cafbbbe3b2b1ef7b32c75fe66d63f4d25bd39cbb298194f1fc8b2f440cad22753ca66f45781a343f875d9d6c66ea819351b7e7ef53f1533e18fb9757539f920708c40bceebf244a7f563b5ee7ff33361feb72a1b17d4ac5acf6567ae7e07519cf3d3f4a9596e53ce58fae5283b465ba026a23d06401e1bac9a3db82be6d762ffdd8e676187fc9f6ccf8fa133737d6efec6dac3f2ac278ff7cf6decef9e2599efdb328af8940727f11c08b12f73f916616c2537c8c08e259fed78a99f0eb3af94707616741080f2bcb7102f13aff0bf1fafeb428db613ba5a9cc34e7f9d69fdbdc589b7486fcb8664fb2def9d693a8d9870081b90808d773619fe641d5755281cdcb6bdf1ee595bd8df59522801725eeffa96b4378e7d545088ff0bd1ee581a20c85f0f80bfa498f789aed4e11c2b74f49f9f16236fc965b2edc769a56d8b75e0211b2bf34ca76d0ced2ff88d6c75f70e2f3b84b96bd6d616965fb1af5e7ef7ac1b8bbdb9e0001026d1610aedb3cfafa3e5260f3f2a5576d5e5e7b6584efd5287716652884ef5ec630cbd3abb23c7b4d84efdd101ef78b535206b3e13308e22342f8f679e17fb5980d7ff84cf691dd90b43b1bbef2fbbb574939bffa1345087fec639ff4b8e4a35102f19afd8b11b4e32f38ebdb613ba59f9da88359e7adbbafa1e5953f5c5cbeeb3963d5339d8dbb59def993e954a516020408942b205c97ebabf6060b6c5e5e7ff5c1103e2a884700ffa7515ed32f595acbb234e7d9f074fbee5552b2fcab8a10fec875d7fdde6e803a2284df7cf3531edfe0216d74d722683f3fca2068fff7e8ecf8b3da79ba39cf3bf70e5e2b67cfaf166fa015554d6f39e49f19bf26fec23495535ea6d752351120d0048132fa205c97a1aa4e02430211c0bf23ca2bfb6563fd6211c08b12338b835352f666c353f64faa1cc2af5effc88707c16a6154085f5ef9d0e2f2f66cf8cdb75ffcf42106772b241021fbcba339f74539d59265f95b1696764e1fe9cf6aafeebbc2c78495fb67c609e1ec468040350484eb6a8c835610e80b6c76d7be73cc107e6930139e6559fcd9bc7f8594199c8e523477704aca60263cddb13d23be3d1b7ef5ead6ef6e07afc1a50a874e47598e10beb4f293c5292942786139dbb2d8bfae757a6e1afec8f317f4baeb594ad97f4ba9b8da4e7c1e67c98b59edfceddb63be929fbd75f545e3ec7ec4b679bcb667f49a3ea21556112040e08402c2f509a16c46a04a029b7b21fcee62167cbbac3d3afe745e5c216577263ca5ceb7edcc840f85f0e2b4945906f14342784a5f599c92b217c257f28551b3e14b2baf2d42f8e26d17eea8d218d4b52d45b08ea4fad268ff7551fa4b96b287b24ea77fe9ca780d7d45af5b5c6d677de71492ec6dfd8dc6fc9475f2372f2c15631a2566b5179656bf64cc2a6c4e60ba026a23302301e17a46d00e43601e02bdeea5efda99091f0ae1eb776e6e8c0ce2dfb213c4df9bed9c179e65b30ce185d088209ed25f294278be957d686110d6f642f84716976316bc284b17b743f8f25d771635292717c8d3d69f8dd7c43b46edd1ebaebd30c2763f6817b7294d10b663563ba5fc6707e3777679e5c5e9e41ffe99f1e456b62440a00202c2750506a1864dd0e4060af4ba97be6727883f657b26fcb0109ebeb94221fcb6ed5351d21d1110b74378def9e020c42d8c0ae111c4e3f9d715b3e10b4b4f79420387f2d02e2d2eadfe9f91b3d6d999feacf5a13b0eade80d85ed94a5b70ead3af1dd2c4f6f5a588a19eda20ccd6ac7736fcc535eccaaefd615bf587d751cd33f33ee8ab8438040d50584ebaa8f90f611a89840cc5e7eef8942f856f64d5971a9c294fd46b63b139e1ec8663a1bbe3b13be17c2f37447cca2fee5086daf48e991df8940976f97dd73c37767c31796567742f8dd9f59b161985a73f2feacf5a57b27a9b0b7b1fea2783deccd6a67e92d63d73334ab1dfbbe24ca141655102040607e02c2f5fcec1d9940a3057a1f5bfbe7fd10de5d7beade4cf8fa35a7a4a44108bf2688cfed9494dd20be17c2af7e60a19865ed979121fc3ff767c2cf5ffcacaa0eeaf6ac755e84d7fde75a8f316b7d5cdf226cbf7810b6d32441fbda03c4447b2acab56b3c438000818a0a1c1bae2bda6ecd2240a02102bb21bc78e39e7d417cc479e179e71f6dcf86a7ff97edce86677f9cd25cae92321cc2ff527f263cdbfaeda34278717ef8c2d26a3f889fbd75f58969861f79ca1f1387db0dd6713f526b71aef564b3d6c5fe4795e1a05d04ee38d89b8fdade3a020408344540b86eca48ea07811608f42e5ffa17dbb3e1eb9fb3331b5ecc843fa6d75ddb7f9594bcf30fab18c28bf3c36336bc1fc4b34efe5b0b4333e10b4bab0f2f2cad7cb408e04559585afd2fc56cf8d95b2f7cf66987b698b58e3a9e136577e95f21648ab3d6bb151f72a77779fd2545c82e4a96d2eb62b363dfc4267e6179d9958dfbdf14db5a081020501b01e1ba3643a5a104089c542042f8bfdceccf841f1dc2b33cfd83ac7f3a4afaf5bd99f0549c173ec3d9f0edf3c2237417b3cacb45002f4a3cfe8a0897afc83ad96f2ef44378f10f80dba7a42c2cef0be1ffb508e137dd72d793d2211fb39eb53ea419bb4f6f76d7bf3242f6a3a2f4cfd78e15027420589a28a04f6d1410aedb38eafa4c80405f60f3f2fabfda09e14fdb9b095f1f391b1e21fc1ba3bc2642f8af4589003eeb105e34792788e76938847f7911c23b673aef1f19c297561f8a3dbf34caee92a5eca16c86b3d6bb073ee44e84ec9746e907ed2ca5d76629fbfb5bd76ddd12b3d66f3c64174f132040a0b202c275658746c30e0a784c609e0211c2bf2fca2b23847f6e9408e08784f00886950ae1297ff441b7fc1457083958d7b41fc7acf6576d76d7beffe3bfffbe3f9c76ddea234080c02c0484eb59283b060102ad112882e10943f837ec84f05fdd9b094f31239ec54c7379ffa019b3c2959ab56ed00b4357081020d01710aefb0c3e11204060b60211c27f6027847fdedec4f63e2a00001000494441544c787f36fcc6ded03f6866a9f3f5fd105e9c1b9ea5bd209ec60fe145b04e5976cfe646395708493e0810204020752a69a05104081020d017d8ec5efad7fd105efc83e6c6fa5e10efaeed0be1bdee7a4c8077fe5e3f8867d97be241cc82a7072250ff7154b41525cf537ab9601d12160204089428205c9788ab6a02049a2950d55e6d762ffd9b7e10df58fbfcddd9f0eeda637addf533513a3123fef6aab65dbb081020d01401e1ba2923a91f04081020408000819418cc5940b89ef300383c010204081020408040730484ebe68ca59e9421a04e02040810204080c01802c2f51858362540800001025512d0160204aa27205c576f4cb4880001020408102040a0a602c2f5eec0b943800001020408102040e07402c2f5e9fcec4d800081d908380a01020408d44240b8aec530692401020408102040a0ba025ab627205cef59b847800001020408102040e05402c2f5a9f8ec4ca00c017512204080000102751510aeeb3a72da4d8000010204e621e09804081c29205c1fc9632501020408102040800081930b08d727b72a634b75122040800001020408344840b86ed060ea0a010204a62ba03602040810185740b81e57ccf6040810204080000102f317a8680b84eb8a0e8c66112040800001020408d44f40b8aedf986931813204d4498000010204084c4140b89e02a22a081020408000813205d44da03e02c2757dc64a4b0910204080000102042a2e205c577c80ca689e3a091020408000010204ca1110aecb71552b010204084c26602f020408d45a40b8aef5f0693c0102040810204080c0ec048e3f92707dbc912d081020408000010204089c4840b83e11938d08102843409d0408102040a06902c275d346547f081020408000816908a883c04402c2f5446c762240800001020408102070ad80707dad8967ca10502701020408102040a00502c2750b06591709102040e068016b091020302d01e17a5a92ea21408000010204081068bd4009e1baf5a6000810204080000102045a2a205cb774e0759b406b05749c00010204089428205c9788ab6a02040810204080c03802b6adbf80705dff31d4030204081020408000818a0808d7151908cd2843409d0408102040800081d90a08d7b3f576340204081020b02de03301028d1410ae1b39ac3a458000010204081020300f81a684eb79d83926010204081020408000817d02c2f53e0e0f0810205086803a09102040a02d02c2755b465a3f0910204080000102a3043c375501e17aaa9c2a23408000010204081068b38070dde6d1d7f73204d449800001020408b45840b86ef1e0eb3a01020408b44d407f0910285b40b82e5b58fd040810204080000102ad1110ae4f31d47625408000010204081020302c205c0f6bb84f800081e608e80901020408cc4140b89e03ba431220408000010204da2dd0dcde0bd7cd1d5b3d23408000010204081098b180703d637087235086803a091020408000816a0808d7d51807ad2040800001024d15d02f02ad1210ae5b35dc3a4b800001020408102050a680705da66e1975ab930001020408102040a0b202c275658746c3081020503f012d26408040db0584ebb6bf02f49f00010204081020d00e8199f452b89e09b383102040800001020408b44140b86ec328eb23813204d4498000010204085c23205c5f43e209020408102040a0ee02da4f605e02c2f5bce41d970001020408102040a07102c275e386b48c0ea9930001020408102040e02402c2f549946c4380000102d515d032020408544840b8aed060680a010204081020408040bd050e86eb7af746eb09102040800001020408cc5140b89e23be43132030ae80ed091020408040b50584eb6a8f8fd6112040800001027511d04e0221205c078285000102040810204080c0340484eb6928aaa30c017512204080000102046a27205cd76ec8349800010204e62fa005040810182d205c8f76f12c01020408102040800081b1052a11aec76eb51d08102040800001020408545040b8aee0a0681201029512d0180204081020706201e1fac454362440800001020408544d407baa26205c576d44b4870001020408102040a0b602c2756d874ec3cb10502701020408102040e03402c2f569f4ec4b80000102046627e0480408d44040b8aec12069220102040810204080403d04da1baeeb313e5a498000010204081020502301e1ba4683a5a90408b447404f09102040a09e02c2753dc74dab0910204080000102f31270dc230484eb2370ac22408000010204081020308e80703d8e966d099421a04e020408102040a03102c275638652470810204080c0f405d44880c07802c2f5785eb62640800001020408102070a880707d284d192bd4498000010204081020d06401e1bac9a3ab6f0408101847c0b604081020706a01e1fad4842a204080000102040810285ba02ef50bd7751929ed244080000102040810a8bc80705df921d240026508a893000102040810284340b82e43559d040810204080c0e402f624506301e1bac683a7e9040810204080000102d51210aeab351e65b4469d040810204080000102331210ae6704ed3004081020304ac07304081068968070ddacf1d41b02040810204080008169094c508f703d019a5d081020408000010204088c1210ae47a9788e00813204d44980000102041a2f205c377e887590000102040810385ec01604a623205c4fc7512d040810204080000102049270ed45508a804a09102040800001026d1410aedb38eafa4c800081760be83d0102044a1310ae4ba35531010204081020408040db044e1faedb26a6bf040810204080000102040e1110ae0f81f1340102cd10d00b0204081020304b01e17a96da8e4580000102040810d81370af8102c27503075597081020408000010204e623205ccfc7dd51cb1050270102040810204060ce02c2f59c07c0e109102040a01d027a4980403b0484eb768cb35e122040800001020408cc40a0a6e17a06320e41800001020408102040604c01e17a4c309b132040e058011b10204080406b0584ebd60ebd8e132040800001026d14d0e7720584eb727dd54e8000010204081020d02201e1ba4583adab6508a89300010204081020b027205cef59b84780000102049a25a0370408cc5c40b89e39b903122040800001020408345540b83ef9c8da92000102040810204080c09102c2f5913c56122040a02e02da4980000102551010aeab300ada408000010204081068b2408bfa265cb768b075950001020408102040a05c01e1ba5c5fb5132843409d04081020408040450584eb8a0e8c661120408000817a0a683581760b08d7ed1e7fbd27408000010204081098a280703d45cc32aa52270102040810204080407d0484ebfa8c9596122040a06a02da4380000102070484eb03201e1220408000010204083441603e7d10aee7e3eea80408102040800001020d1410ae1b38a8ba44a00c017512204080000102c70b08d7c71bd982000102040810a8b680d611a88c80705d99a1d010020408102040800081ba0b08d7751fc132daaf4e020408102040800081890484eb89d8ec4480000102f312705c020408545940b8aef2e8681b0102040810204080409d0492705dabe1d2580204081020408000812a0b08d7551e1d6d23d07601fd274080000102351310ae6b36609a4b8000010204085443402b088c1210ae47a9788e000102040810204080c00402c2f50468762943409d0408102040800081fa0b08d7f51f433d2040800081b205d44f800081130a08d72784b21901020408102040800081e304e611ae8f6b93f5040810204080000102046a29205cd772d8349a0081f204d44c8000010204261710ae27b7b3270102040810204060b6028e567901e1baf243a481040810204080000102751110aeeb3252da5986803a0910204080000102531510aea7caa9320204081020302d01f51020504701e1ba8ea3a6cd0408102040800001029514684db8aea4be46112040800001020408344a40b86ed470ea0c01023515d06c0204081068888070dd9081d40d020408102040804039026a1d4740b81e47cbb6040810204080000102048e1010ae8fc0b18a401902ea244080000102049a2b205c37776cf58c00010204088c2b607b02044e29205c9f12d0ee040810204080000102040602c2f540a28c5b75122040800001020408b44a40b86ed570eb2c010204f604dc2340800081e90b08d7d33755230102040810204080c0e9046abbb7705ddba1d370020408102040800081aa0908d7551b11ed215086803a09102040800081990808d733617610020408102040e03001cf1368928070dda4d1d417020408102040800081b90a08d773e52fe3e0ea244080000102040810989780703d2f79c7254080401b05f4990001020d1710ae1b3ec0ba478000010204081020703281696c255c4f43511d040810204080000102044240b80e040b01026508a8930001020408b44f40b86edf98eb310102040810204080404902c27549b0aa254080000102040810689f8070ddbe312fa3c7ea2440800001020408100801e13a102c04081020d064017d234080c0ec0484ebd9593b12010204081020408040c305c60ed70df7d03d02040810204080000102130b08d713d3d99100810a0a6812010204081098ab80703d577e0727408000010204da23a0a76d1010aedb30cafa488000010204081020301301e17a26cc0e5286803a0910204080000102551310aeab3622da43800001024d10d00702045a2a205cb774e0759b000102040810204060fa02f508d7d3efb71a091020408000010204084c5d40b89e3aa90a0910689b80fe1220408000818180703d90704b80000102040810689e801ecd5840b89e31b8c3112040800001020408345740b86eeed8ea591902ea24408000010204081c21205c1f8163150102040810a89380b61220307f01e17afe63a0050408102040800001020d1110ae0f1d482b081020408000010204088c27205c8fe7656b0204085443402b08102040a09202c275258745a30810204080000102f5156873cb85eb368fbebe1320408000010204084c5540b89e2aa7ca089421a04e020408102040a02e02c2755d464a3b0910204080401505b48900817d02c2f53e0e0f081020408000010204084c2e205c4f6e57c69eea244080000102040810a8b180705de3c1d374020408cc56c0d108102040e03801e1fa3821eb091020408000010204aa2f5091160ad7151908cd204080000102040810a8bf80705dff31d403026508a893000102040810984040b89e00cd2e040810204080c03c051c9b40750584ebea8e8d96112040800001020408d44c40b8aed98095d15c751220408000010204084c4740b89e8ea35a08102040a01c01b5122040a05602c275ad864b63091020408000010204aa23706d4b84eb6b4d3c43800001020408102040602201e17a22363b11205086803a091020408040dd0584ebba8fa0f613204080000102b310700c02271210ae4fc4642302040810204080000102c70b08d7c71bd9a20c017512204080000102041a28205c3770507589000102044e27606f0204084c2a205c4f2a673f0204081020408000010207046610ae0f1cd143020408102040800001020d1510ae1b3ab0ba4580c009056c468000010204a628205c4f1153550408102040800081690aa8ab7e02c275fdc64c8b0910204080000102042a2a20328f754f00000292494441545c57746034ab0c01751220408000010204ca1510aecbf5553b0102040810389980ad081068848070dd8861d4090204081020408000812a0834355c57c1561b0810204080000102045a26205cb76cc0759700812a08680301020408345540b86eeac8ea1701020408102040601201fb9c4a40b83e159f9d09102040800001020408ec0908d77b16ee112843409d040810204080408b0484eb160db6ae122040800081fd021e1120306d01e17adaa2ea23408000010204081068ad80703dc5a15715010204081020408040bb0584eb768fbfde1320d01e013d2540800081190808d733407608020408102040800081a3049ab34eb86ece58ea090102040810204080c09c0584eb390f80c3132843409d040810204080c07c0484ebf9b83b2a010204081068ab807e1368b48070dde8e1d539020408102040800081590a08d7b3d42ee358ea244080000102040810a88c80705d99a1d010020408344f408f081020d03601e1ba6d23aebf04081020408000010285402945b82e8555a50408102040800001026d1410aedb38eafa4ca00c01751220408000010249b8f622204080000102041a2fa083046625205ccf4ada710810204080000102041a2f205c377e88cbe8a03a091020408000010204460908d7a3543c4780000102f515d072020408cc5140b89e23be43132040800001020408344be0b870ddacdeea0d01020408102040800081120584eb1271554d8040d902ea274080000102d51210aeab351e5a438000010204083445403f5a29205cb772d8759a0001020408102040a00c01e1ba0c55759621a04e0204081020408040e50584ebca0f91061220408040f505b490000102db02c2f5b683cf040810204080000102044e2d50c9707dea5ea980000102040810204080c01c04fe3f000000ffffdc0ed9cf00000006494441540300a5fceaa39b47e1d90000000049454e44ae426082, '2026-05-06 12:20:09');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `team_locations`
--

CREATE TABLE `team_locations` (
  `team_code` varchar(20) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lon` decimal(10,7) NOT NULL,
  `accuracy_m` decimal(8,2) DEFAULT NULL,
  `heading_deg` decimal(6,2) DEFAULT NULL,
  `speed_mps` decimal(6,2) DEFAULT NULL,
  `status_code` varchar(60) DEFAULT NULL,
  `status_label` varchar(120) DEFAULT NULL,
  `leader_name` varchar(120) DEFAULT NULL,
  `driver_name` varchar(120) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_locations`
--

INSERT INTO `team_locations` (`team_code`, `lat`, `lon`, `accuracy_m`, `heading_deg`, `speed_mps`, `status_code`, `status_label`, `leader_name`, `driver_name`, `updated_at`) VALUES
('T1', 50.3009318, 18.9140392, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-27 14:18:21');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `team_mileage_daily`
--

CREATE TABLE `team_mileage_daily` (
  `id` int(11) NOT NULL,
  `team_code` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `distance_km` decimal(10,3) NOT NULL DEFAULT 0.000,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `team_presence`
--

CREATE TABLE `team_presence` (
  `team_code` varchar(20) NOT NULL,
  `status_code` varchar(60) DEFAULT NULL,
  `status_label` varchar(120) DEFAULT NULL,
  `leader_name` varchar(120) DEFAULT NULL,
  `driver_name` varchar(120) DEFAULT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `team_presence`
--

INSERT INTO `team_presence` (`team_code`, `status_code`, `status_label`, `leader_name`, `driver_name`, `last_seen_at`) VALUES
('T1', 'ready_base', 'Gotowy w bazie', 'Sebastian Pabjańczyk', NULL, '2026-05-27 14:18:55');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','dispatcher','team','management') NOT NULL DEFAULT 'admin',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `role`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', NULL, '$2y$10$BnVwQPJNB38zfaEZKPvwoOD0uo78TuBPcHrcFhERMo6s7cpeyZQ5i', 'admin', '2026-05-16 17:02:43', '2026-04-21 10:45:27', '2026-05-16 17:02:43'),
(2, 'dyspo1', 'Bartosz Świącik', '$2y$10$hqX2f5vbAmRyBeI9NiHuXuJ1RZJbrg/u4MhBmQpSNGLBnmocmYLRi', 'dispatcher', '2026-05-27 13:22:05', '2026-04-21 10:48:55', '2026-05-27 13:22:05'),
(3, 'ratol1', 'Sebastian Pabjańczyk', '$2y$10$k.93L//C7a5F69lZQZVcHeXuSLOGPEIFfPretiMq/tk4AL65SF4tu', 'team', '2026-05-27 13:34:16', '2026-04-21 11:53:52', '2026-05-27 13:34:16'),
(4, 'ratol2', 'Janek Kowalski', '$2y$10$/.MhY.fYtXagw/x5IsyNFOpLzAwPVp2Vp2YqabYGS5odRG8RG3wKm', 'team', '2026-04-21 14:44:21', '2026-04-21 11:54:09', '2026-04-21 14:44:21'),
(5, 'ratol3', 'Mateusz Kowalski', '$2y$10$zwSCF7M2uJE7MeSdpaONtO/rc3W15sWIAc4kk4X3.DVfxTnBV0f5C', 'team', NULL, '2026-04-21 11:54:52', '2026-04-21 11:54:52');

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_clients_username` (`username`);

--
-- Indeksy dla tabeli `client_requests`
--
ALTER TABLE `client_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_requests_status_created` (`status`,`created_at`),
  ADD KEY `idx_client_requests_client` (`client_id`),
  ADD KEY `idx_client_requests_order` (`order_id`),
  ADD KEY `fk_client_requests_client` (`client_id`),
  ADD KEY `fk_client_requests_confirmed_by` (`confirmed_by`),
  ADD KEY `fk_client_requests_rejected_by` (`rejected_by`),
  ADD KEY `fk_client_requests_order_id` (`order_id`);

--
-- Indeksy dla tabeli `dispatch_cancellations`
--
ALTER TABLE `dispatch_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispatch_cancellations_team` (`team_code`),
  ADD KEY `idx_dispatch_cancellations_ack` (`acked_at`),
  ADD KEY `idx_dispatch_cancellations_dispatch` (`dispatch_id`),
  ADD KEY `idx_dispatch_cancellations_order` (`order_id`);

--
-- Indeksy dla tabeli `dispatch_notifications`
--
ALTER TABLE `dispatch_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispatch_notifications_order` (`order_id`),
  ADD KEY `idx_dispatch_notifications_team` (`team_code`),
  ADD KEY `idx_dispatch_notifications_status` (`status`),
  ADD KEY `idx_dispatch_notifications_created` (`created_at`),
  ADD KEY `fk_dispatch_notifications_dispatcher` (`dispatcher_id`),
  ADD KEY `fk_dispatch_notifications_accepted_by` (`accepted_by`),
  ADD KEY `fk_dispatch_notifications_rejected_by` (`rejected_by`),
  ADD KEY `fk_dispatch_notifications_cancelled_by` (`cancelled_by`);

--
-- Indeksy dla tabeli `dispatch_urges`
--
ALTER TABLE `dispatch_urges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispatch_urges_team` (`team_code`),
  ADD KEY `idx_dispatch_urges_ack` (`acked_at`),
  ADD KEY `idx_dispatch_urges_dispatch` (`dispatch_id`),
  ADD KEY `idx_dispatch_urges_order` (`order_id`);

--
-- Indeksy dla tabeli `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_status_created` (`status`,`created_at`),
  ADD KEY `idx_orders_planned_at` (`planned_at`),
  ADD KEY `idx_orders_urgency` (`urgency`),
  ADD KEY `fk_orders_dispatcher` (`dispatcher_id`),
  ADD KEY `fk_orders_assigned_by` (`assigned_by`);

--
-- Indeksy dla tabeli `order_cancellations`
--
ALTER TABLE `order_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_cancellations_order` (`order_id`),
  ADD KEY `idx_order_cancellations_at` (`cancelled_at`);

--
-- Indeksy dla tabeli `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teams_code` (`code`),
  ADD KEY `idx_teams_active` (`is_active`);

--
-- Indeksy dla tabeli `team_events`
--
ALTER TABLE `team_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_team_events_unread` (`read_at`),
  ADD KEY `idx_team_events_team` (`team_code`),
  ADD KEY `idx_team_events_order` (`order_id`),
  ADD KEY `idx_team_events_created` (`created_at`);

--
-- Indeksy dla tabeli `team_event_files`
--
ALTER TABLE `team_event_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tef_event` (`event_id`),
  ADD KEY `idx_tef_team` (`team_code`),
  ADD KEY `idx_tef_order` (`order_id`),
  ADD KEY `idx_tef_created` (`created_at`);

--
-- Indeksy dla tabeli `team_locations`
--
ALTER TABLE `team_locations`
  ADD PRIMARY KEY (`team_code`),
  ADD KEY `idx_team_locations_updated` (`updated_at`);

--
-- Indeksy dla tabeli `team_mileage_daily`
--
ALTER TABLE `team_mileage_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_date` (`team_code`,`date`);

--
-- Indeksy dla tabeli `team_presence`
--
ALTER TABLE `team_presence`
  ADD PRIMARY KEY (`team_code`),
  ADD KEY `idx_team_presence_last_seen` (`last_seen_at`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_users_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `client_requests`
--
ALTER TABLE `client_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dispatch_cancellations`
--
ALTER TABLE `dispatch_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `dispatch_notifications`
--
ALTER TABLE `dispatch_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `dispatch_urges`
--
ALTER TABLE `dispatch_urges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `order_cancellations`
--
ALTER TABLE `order_cancellations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `team_events`
--
ALTER TABLE `team_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `team_event_files`
--
ALTER TABLE `team_event_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `team_mileage_daily`
--
ALTER TABLE `team_mileage_daily`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_requests`
--
ALTER TABLE `client_requests`
  ADD CONSTRAINT `fk_client_requests_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_client_requests_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_client_requests_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_client_requests_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dispatch_notifications`
--
ALTER TABLE `dispatch_notifications`
  ADD CONSTRAINT `fk_dispatch_notifications_accepted_by` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dispatch_notifications_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dispatch_notifications_dispatcher` FOREIGN KEY (`dispatcher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dispatch_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dispatch_notifications_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_dispatcher` FOREIGN KEY (`dispatcher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
