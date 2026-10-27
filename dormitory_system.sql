-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 10:44 PM
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
-- Database: `dormitory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, NULL, '🛠️ มีคำขอแจ้งซ่อมใหม่จากห้อง 101: ไฟห้องน้ำไม่ติด', NULL, 0, '2026-10-27 18:44:45'),
(2, 2, NULL, '🛠️ สถานะงานซ่อม \'ไฟห้องน้ำไม่ติด\' เปลี่ยนเป็น: รออนุมัติ', NULL, 0, '2026-10-27 18:56:21'),
(3, 5, NULL, '🛠️ งานซ่อม \'แอร์ไม่เย็น\' ดำเนินการเสร็จสิ้นแล้ว ขอบคุณค่ะ', NULL, 0, '2026-10-27 18:56:28'),
(4, 2, NULL, '🛠️ นิติบุคคลรับเรื่องแจ้งซ่อม \'ไฟห้องน้ำไม่ติด\' แล้ว และกำลังดำเนินการแก้ไขค่ะ', NULL, 0, '2026-10-27 18:56:33'),
(5, 2, NULL, '🛠️ นิติบุคคลรับเรื่องแจ้งซ่อม \'ไฟห้องน้ำไม่ติด\' แล้ว และกำลังดำเนินการแก้ไขค่ะ', NULL, 0, '2026-10-27 18:56:38'),
(6, 2, NULL, '🛠️ นิติบุคคลรับเรื่องแจ้งซ่อม \'ไฟห้องน้ำไม่ติด\' แล้ว และกำลังดำเนินการแก้ไขค่ะ', NULL, 0, '2026-10-27 18:57:44'),
(7, 2, NULL, '🛠️ งานซ่อม \'ไฟห้องน้ำไม่ติด\' ดำเนินการเสร็จสิ้นแล้ว ขอบคุณค่ะ', NULL, 0, '2026-10-27 18:57:47'),
(8, 2, NULL, '🛠️ สถานะงานซ่อม \'ไฟห้องน้ำไม่ติด\' เปลี่ยนเป็น: รออนุมัติ', NULL, 0, '2026-10-27 18:57:50'),
(9, 2, NULL, '📦 คุณมีพัสดุใหม่จำนวน 1 ชิ้น จากขนส่ง ไปรษณีย์ไทย (Thailand Post) (พัสดุ: พัสดุสินค้าทั่วไป) รอรับได้ที่ห้องนิติบุคคลค่ะ', NULL, 0, '2026-10-27 19:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `parcels`
--

CREATE TABLE `parcels` (
  `parcel_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `courier` varchar(100) DEFAULT NULL,
  `parcel_type` varchar(100) DEFAULT 'พัสดุทั่วไป',
  `tracking_number` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('รอรับ','รับแล้ว') DEFAULT 'รอรับ',
  `pickup_note` varchar(255) DEFAULT NULL,
  `received_at` datetime DEFAULT current_timestamp(),
  `picked_up_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parcels`
--

INSERT INTO `parcels` (`parcel_id`, `room_id`, `recorded_by`, `courier`, `parcel_type`, `tracking_number`, `note`, `image`, `quantity`, `image_path`, `status`, `pickup_note`, `received_at`, `picked_up_at`) VALUES
(1, 1, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 2, NULL, 'รับแล้ว', NULL, '2026-04-30 13:00:00', '2026-10-28 02:17:13'),
(2, 5, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 1, NULL, 'รอรับ', NULL, '2026-04-30 11:00:00', NULL),
(3, 5, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 1, NULL, 'รับแล้ว', NULL, '2026-04-30 12:00:00', '2026-10-28 02:17:05'),
(4, 6, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 3, NULL, 'รับแล้ว', NULL, '2026-04-30 16:30:00', '2026-10-28 02:17:09'),
(5, 2, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 3, NULL, 'รอรับ', NULL, '2026-04-20 09:00:00', NULL),
(6, 3, 1, NULL, 'พัสดุทั่วไป', NULL, '', NULL, 7, NULL, 'รอรับ', NULL, '2026-04-21 09:00:00', NULL),
(7, 1, 1, 'ไปรษณีย์ไทย (Thailand Post)', 'พัสดุสินค้าทั่วไป', '', '', NULL, 1, NULL, 'รอรับ', NULL, '2026-10-28 02:26:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `repair_categories`
--

CREATE TABLE `repair_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repair_categories`
--

INSERT INTO `repair_categories` (`category_id`, `category_name`) VALUES
(1, 'ไฟฟ้า'),
(2, 'ประปา'),
(3, 'แอร์'),
(4, 'อื่น ๆ');

-- --------------------------------------------------------

--
-- Table structure for table `repair_requests`
--

CREATE TABLE `repair_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `urgency` enum('ปกติ','ด่วน','ด่วนมาก') DEFAULT 'ปกติ',
  `status` enum('รออนุมัติ','กำลังดำเนินการ','เสร็จสิ้น','ยกเลิก') DEFAULT 'รออนุมัติ',
  `image` varchar(255) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `confirmed_date` date DEFAULT NULL,
  `admin_remark` text DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repair_requests`
--

INSERT INTO `repair_requests` (`request_id`, `user_id`, `room_id`, `category_id`, `description`, `urgency`, `status`, `image`, `appointment_date`, `confirmed_date`, `admin_remark`, `acknowledged_at`, `created_at`, `updated_at`) VALUES
(1, 5, 5, 3, 'แอร์ไม่เย็น', 'ปกติ', 'เสร็จสิ้น', NULL, '2026-04-29', NULL, NULL, NULL, '2026-04-29 09:00:00', '2026-10-28 01:46:39'),
(2, 6, 6, 2, 'สายชำระรั่ว', 'ปกติ', 'กำลังดำเนินการ', NULL, '2026-04-24', NULL, NULL, NULL, '2026-04-24 10:00:00', '2026-10-27 22:25:52'),
(3, 4, 3, 2, 'ก๊อกน้ำรั่ว', 'ปกติ', 'เสร็จสิ้น', NULL, '2026-04-20', NULL, NULL, NULL, '2026-04-20 11:00:00', '2026-10-27 22:25:52'),
(4, 2, 1, 1, 'ไฟห้องน้ำไม่ติด', 'ปกติ', 'รออนุมัติ', NULL, '2026-10-27', NULL, NULL, NULL, '2026-10-28 01:42:13', '2026-10-28 01:57:50'),
(5, 2, 1, 1, 'ไฟห้องน้ำไม่ติด', 'ปกติ', 'กำลังดำเนินการ', NULL, '2026-10-27', NULL, NULL, NULL, '2026-10-28 01:42:18', '2026-10-28 01:56:38'),
(6, 2, 1, 1, 'ไฟห้องน้ำไม่ติด', 'ปกติ', 'เสร็จสิ้น', NULL, '2026-10-27', NULL, NULL, NULL, '2026-10-28 01:43:23', '2026-10-28 01:57:47'),
(7, 2, 1, 1, 'ไฟห้องน้ำไม่ติด', 'ปกติ', 'กำลังดำเนินการ', NULL, '2026-10-27', NULL, NULL, NULL, '2026-10-28 01:44:45', '2026-10-28 01:57:44');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_no` varchar(10) NOT NULL,
  `floor` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_no`, `floor`) VALUES
(1, '101', 1),
(2, '102', 1),
(3, '103', 1),
(4, '104', 1),
(5, '105', 1),
(6, '201', 2),
(7, '202', 2),
(8, '203', 2),
(9, '204', 2),
(10, '205', 2),
(11, '301', 3),
(12, '302', 3),
(13, '303', 3),
(14, '304', 3),
(15, '305', 3),
(16, '401', 4),
(17, '402', 4),
(18, '403', 4),
(19, '404', 4),
(20, '405', 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('ใช้งานอยู่','ย้ายออก') DEFAULT 'ใช้งานอยู่',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `room_id`, `full_name`, `email`, `phone`, `username`, `password`, `role`, `status`, `must_change_password`, `created_at`) VALUES
(1, NULL, 'ผู้ดูแลระบบ', 'admin@dorm.com', '081-234-5678', 'admin', '$2y$10$.b1bWVRxGdL7.zneBVrxTeaN5xCNQWwpFkLZOzSgMh31sjB9TQ3FO', 'admin', 'ใช้งานอยู่', 0, '2026-10-27 22:45:02'),
(2, 1, 'สมชาย ศิริรัตน์', 'user_101@example.com', '087-680-6705', 'room101', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(3, 2, 'กฤษฎา ใจดี', 'user_102@example.com', '088-545-1460', 'room102', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(4, 3, 'สมหญิง มั่นคง', 'user_103@example.com', '082-329-5439', 'room103', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(5, 4, 'เอกราช สุข', 'user_104@example.com', '087-905-9396', 'room104', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(6, 5, 'จิราพร บุญมา', 'user_105@example.com', '082-276-1602', 'room105', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(7, 6, 'ชัญญา ยอดเยี่ยม', 'user_201@example.com', '087-108-3894', 'room201', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(8, 7, 'ปวีณา ไชยศรี', 'user_202@example.com', '084-507-8049', 'room202', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(9, 8, 'กฤษฎา รุ่งเรือง', 'user_203@example.com', '082-630-3861', 'room203', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(10, 9, 'กิตติศักดิ์ พิทักษ์ไทย', 'user_204@example.com', '087-946-8810', 'room204', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(11, 10, 'เอกราช รักสงบ', 'user_205@example.com', '082-938-1134', 'room205', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(12, 11, 'พรพรรณ มีสุข', 'user_301@example.com', '087-325-9014', 'room301', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(13, 12, 'เอกราช แสงสว่าง', 'user_302@example.com', '085-354-6204', 'room302', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(14, 13, 'กิตติศักดิ์ มั่นคง', 'user_303@example.com', '081-212-4262', 'room303', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(15, 14, 'จิรภัทร ทองทา', 'user_304@example.com', '084-862-4147', 'room304', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(16, 15, 'ณัฐวุฒิ มั่นคง', 'user_305@example.com', '088-533-9310', 'room305', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(17, 16, 'เอกราช งามดี', 'user_401@example.com', '082-463-2113', 'room401', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(18, 17, 'ศุภชัย ทองทา', 'user_402@example.com', '082-648-4737', 'room402', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(19, 18, 'ปวีณา วิเศษศรี', 'user_403@example.com', '087-435-1017', 'room403', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(20, 19, 'สมชาย รักสงบ', 'user_404@example.com', '086-893-8783', 'room404', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41'),
(21, 20, 'กฤษฎา ศิริรัตน์', 'user_405@example.com', '085-520-9630', 'room405', '$2y$10$M3KusLVjEFYmyXN0y0i/Yu6bdM90cwJ82x6ZU2i93iWbIJFf36WDq', 'user', 'ใช้งานอยู่', 1, '2026-10-27 23:26:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parcels`
--
ALTER TABLE `parcels`
  ADD PRIMARY KEY (`parcel_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `repair_categories`
--
ALTER TABLE `repair_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `room_id` (`room_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `parcels`
--
ALTER TABLE `parcels`
  MODIFY `parcel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `repair_categories`
--
ALTER TABLE `repair_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `repair_requests`
--
ALTER TABLE `repair_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `parcels`
--
ALTER TABLE `parcels`
  ADD CONSTRAINT `parcels_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `parcels_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `repair_requests`
--
ALTER TABLE `repair_requests`
  ADD CONSTRAINT `repair_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `repair_requests_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `repair_requests_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `repair_categories` (`category_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
