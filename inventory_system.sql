-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 04:50 PM
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
-- Database: `inventory_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer_invoices`
--

CREATE TABLE `customer_invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('draft','unpaid','paid','cancelled') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_invoices`
--

INSERT INTO `customer_invoices` (`id`, `invoice_number`, `customer_id`, `invoice_date`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(6, 'INV-2025-009', 6, '2025-06-25', 103.88, 'paid', '2025-06-25 10:09:19', '2025-06-25 17:59:16'),
(9, 'INV-2025-111', 6, '2025-06-25', 69.96, 'paid', '2025-06-25 17:14:20', '2025-06-25 18:09:37');

-- --------------------------------------------------------

--
-- Table structure for table `customer_supplier`
--

CREATE TABLE `customer_supplier` (
  `id` int(11) NOT NULL,
  `registrationID` varchar(250) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `companyName` varchar(200) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zipCode` varchar(20) NOT NULL,
  `country` varchar(10) NOT NULL,
  `registrationType` enum('customer','supplier') NOT NULL,
  `businessType` varchar(50) DEFAULT NULL,
  `industry` varchar(50) DEFAULT NULL,
  `dateRegistered` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_supplier`
--

INSERT INTO `customer_supplier` (`id`, `registrationID`, `firstName`, `lastName`, `companyName`, `email`, `phone`, `address`, `city`, `state`, `zipCode`, `country`, `registrationType`, `businessType`, `industry`, `dateRegistered`, `status`, `created_at`, `updated_at`) VALUES
(5, 'SP001', 'SYUKOR', 'AHMAD FAUZI', 'NIKEY SDN BHD', 'syukor@nikey.com.my', '(601) 456-24483', 'A-51-2, TINGKAT 2, BAGUNAN SENANDUNG, JALAN ISMAIL 2,', 'KOTA DAMANSARA', 'SELANGOR', '41000', 'MY', 'supplier', 'manufacturer', 'manufacturing', '2025-06-08 14:59:09', 'active', '2025-06-08 14:59:09', '2025-06-24 17:49:12'),
(6, 'CS002', 'ABU YUSOF', 'ALIF', 'TOP GLOVE SDN BHD', 'abu@topglove.com.my', '(017) 599-3153', 'A-02-01, TINGKAT 2, BANGUNAN AMCORP,JALAN TUN RAZAK', 'CHERAS', 'KUALA LUMPUR', '56000', 'MY', 'customer', '', '', '2025-06-12 12:54:57', 'active', '2025-06-12 12:54:57', '2025-06-24 17:46:52'),
(7, 'SP002', 'SYIKIN', 'ANUAR', 'KABEX SDN BHD', 'syikin@kabex.com.my', '(019) 992-8821', 'LOT551, LORONG JEBAT 3,SYEKSYEN 21', 'SHAH ALAM', 'SELANGOR', '40000', 'MY', 'supplier', 'distributor', 'technology', '2025-06-24 15:47:32', 'active', '2025-06-24 15:47:32', '2025-06-24 15:47:32'),
(8, 'CS003', 'AH HONG', 'LING', 'PANDA HARDWARE', 'ahong@panda.com.my', '(018) 882-9922', 'LOT514, WISMA PERDANA, JALAN KUCHING 5/1', 'PUTRA HEIGHT', 'SELANGOR', '45000', 'MY', 'customer', '', '', '2025-06-25 13:52:39', 'active', '2025-06-25 13:52:39', '2025-06-25 13:52:39'),
(9, 'SP003', 'TARMIZI', 'RAHIM', 'NJ PLASTIK SDN BHD', 'tarimizi@njplastik.com.my', '(012) 622-0921', 'LOT 32, JALAN BATU KUNING 14/1,', 'SABAK BERNAM', 'SELANGOR', '71000', 'MY', 'supplier', 'manufacturer', 'construction', '2025-06-25 18:32:46', 'active', '2025-06-25 18:32:46', '2025-06-25 18:32:46'),
(10, 'CS004', 'SUFFIAN', 'KHAN', 'SUFFIAN ELECTRIC', 'admin@selec.com.my', '(011) 987-2272', 'LOT43, JALAN CHERAS 1, BANDAR PERMAISURI', 'CHERAS', 'KUALA LUMPUR', '56000', 'MY', 'customer', '', '', '2025-06-26 13:35:39', 'active', '2025-06-26 13:35:39', '2025-06-26 13:35:39');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_item`
--

CREATE TABLE `inventory_item` (
  `itemID` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `type_product` varchar(25) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `price` varchar(25) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_item`
--

INSERT INTO `inventory_item` (`itemID`, `product_name`, `type_product`, `stock`, `price`, `supplier_id`, `last_updated`, `image`) VALUES
(23585, 'PLUG 3 PIN', 'Electronic', 784, '3', NULL, '2025-06-27 02:15:07', ''),
(26040, 'LED LAMP 18W 6FT', 'Electronic', 25, '8', NULL, '2025-06-27 02:26:19', ''),
(78110, 'BATTERY 3A 4PCS EVEREADY', 'Electronic', 301, '6', NULL, '2025-06-27 02:26:58', '5b109c12eff384d888138c1aa35dad8d.jpg'),
(89614, 'WATER HEATER 12L SINGER', 'Kitchen', 151, '59', NULL, '2025-06-27 02:26:42', 'ChatGPT Image May 6, 2025, 09_30_54 PM.png'),
(93084, 'CAR JUMPER 6GAUGE 5MTR', 'Accessories', 111, '45', NULL, '2025-06-27 02:27:33', ''),
(93831, 'TEST PEN STANLEY 65MM', 'Electronic', 273, '11', NULL, '2025-06-27 02:26:58', ''),
(95322, 'WASHING MACHINE 15L SAMSUNG', 'Electronic', 133, '899', NULL, '2025-06-27 02:26:19', '');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_id`, `item_name`, `quantity`, `sale_price`, `line_total`, `created_at`) VALUES
(11, 6, 93084, 'CAR JUMPER 6GAUGE 5MTR', 2, 45.00, 90.00, '2025-06-25 10:09:19'),
(12, 6, 26040, 'LED LAMP 18W 6FT', 1, 8.00, 8.00, '2025-06-25 10:09:19'),
(18, 9, 78110, 'BATTERY 3A 4PCS EVEREADY', 1, 6.00, 6.00, '2025-06-25 17:14:20'),
(19, 9, 78110, 'BATTERY 3A 4PCS EVEREADY', 10, 6.00, 60.00, '2025-06-25 17:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `date_ordered` date NOT NULL,
  `date_expected` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `po_number`, `date_ordered`, `date_expected`, `notes`, `status`, `total_amount`, `created_at`) VALUES
(78, 5, 'PO-2024-026', '2025-06-25', NULL, NULL, 'rejected', 95.40, '2025-06-25 05:04:22'),
(79, 7, 'PO-20250624-023', '2025-06-25', NULL, NULL, 'approved', 233.20, '2025-06-25 05:05:22'),
(81, 7, 'PO-20250622-2', '2025-06-25', NULL, NULL, 'rejected', 108.12, '2025-06-25 10:25:08'),
(83, 7, 'PO-20250700', '2025-06-26', NULL, NULL, 'kiv', 127.20, '2025-06-25 16:35:55'),
(84, 5, 'PO-20250701', '2025-06-25', NULL, NULL, 'pending', 29.68, '2025-06-25 16:36:28'),
(85, 9, 'PO-20250702', '2025-06-26', NULL, NULL, 'pending', 6.36, '2025-06-26 13:40:05'),
(86, 7, 'PO-2024-022', '2025-06-26', NULL, NULL, 'pending', 47.70, '2025-06-26 13:47:57'),
(87, 9, 'PO-20250627-001', '2025-06-27', NULL, NULL, 'pending', 1325.00, '2025-06-27 02:15:07'),
(88, 7, 'PO-20250627-002', '2025-06-27', NULL, NULL, 'pending', 95506.00, '2025-06-27 02:26:19'),
(89, 9, 'PO-20250627-003', '2025-06-27', NULL, NULL, 'pending', 6254.00, '2025-06-27 02:26:42'),
(90, 7, 'PO-20250627-004', '2025-06-27', NULL, NULL, 'pending', 18.02, '2025-06-27 02:26:58'),
(91, 5, 'PO-20250627-005', '2025-06-27', NULL, NULL, 'pending', 47.70, '2025-06-27 02:27:33');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `line_total` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `item_id`, `quantity`, `cost_price`, `item_name`, `line_total`) VALUES
(68, 78, 93084, 2, 45.00, 'CAR JUMPER 6GAUGE 5MTR', 90.00),
(69, 79, 93831, 20, 11.00, 'TEST PEN STANLEY 65MM', 220.00),
(73, 81, 93084, 2, 45.00, 'CAR JUMPER 6GAUGE 5MTR', 90.00),
(74, 81, 23585, 4, 3.00, 'PLUG 3 PIN', 12.00),
(77, 83, 23585, 20, 3.00, 'PLUG 3 PIN', 60.00),
(78, 83, 78110, 10, 6.00, 'BATTERY 3A 4PCS EVEREADY', 60.00),
(79, 84, 78110, 1, 6.00, 'BATTERY 3A 4PCS EVEREADY', 6.00),
(80, 84, 93831, 2, 11.00, 'TEST PEN STANLEY 65MM', 22.00),
(81, 85, 23585, 2, 3.00, 'PLUG 3 PIN', 6.00),
(83, 86, 93084, 1, 45.00, 'CAR JUMPER 6GAUGE 5MTR', 45.00),
(84, 87, 93831, 100, 11.00, 'TEST PEN STANLEY 65MM', 1100.00),
(85, 87, 23585, 50, 3.00, 'PLUG 3 PIN', 150.00),
(86, 88, 26040, 25, 8.00, 'LED LAMP 18W 6FT', 200.00),
(87, 88, 95322, 100, 899.00, 'WASHING MACHINE 15L SAMSUNG', 89900.00),
(88, 89, 89614, 100, 59.00, 'WATER HEATER 12L SINGER', 5900.00),
(89, 90, 78110, 1, 6.00, 'BATTERY 3A 4PCS EVEREADY', 6.00),
(90, 90, 93831, 1, 11.00, 'TEST PEN STANLEY 65MM', 11.00),
(91, 91, 93084, 1, 45.00, 'CAR JUMPER 6GAUGE 5MTR', 45.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_in_history`
--

CREATE TABLE `stock_in_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_added` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_in_history`
--

INSERT INTO `stock_in_history` (`id`, `product_id`, `product_name`, `quantity_added`, `username`, `transaction_date`) VALUES
(1, 93831, 'alala', 155, 'abu', '2025-06-24 15:09:31'),
(2, 78110, 'BATTERY 3A 4PCS EVEREADY', 30, 'RAFIZ', '2025-06-24 15:22:23'),
(3, 78110, 'BATTERY 3A 4PCS EVEREADY', 100, 'RAFIZ', '2025-06-24 15:23:30'),
(4, 23585, 'PLUG 3 PIN', 9, 'RAFIZ', '2025-06-24 15:23:48'),
(5, 26040, 'LED LAMP 18W 6FT', 30, 'RAFIZ', '2025-06-25 04:35:13'),
(6, 75127, 'IPHONE 17 PRO MAX 516GB', 12, 'RAFIZ', '2025-06-25 13:48:30'),
(7, 78110, 'BATTERY 3A 4PCS EVEREADY', 28, 'RAFIZ', '2025-06-26 00:03:47'),
(8, 95322, 'WASHING MACHINE 15L SAMSUNG', 5, 'RAFIZ', '2025-06-26 00:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `stock_out_history`
--

CREATE TABLE `stock_out_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_deducted` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_out_history`
--

INSERT INTO `stock_out_history` (`id`, `product_id`, `product_name`, `quantity_deducted`, `username`, `transaction_date`) VALUES
(1, 23585, 'PLUG 3 PIN', 10, 'RAFIZ', '2025-06-24 17:06:23'),
(2, 23585, 'PLUG 3 PIN', 10, 'RAFIZ', '2025-06-24 17:06:42'),
(3, 93831, 'TEST PEN STANLEY 65MM', 10, 'RAFIZ', '2025-06-24 17:08:42'),
(4, 23585, 'PLUG 3 PIN', 300, 'RAFIZ', '2025-06-24 17:29:07'),
(5, 89614, 'WATER HEATER 12L SINGER', 1, 'RAFIZ', '2025-06-25 04:35:23'),
(6, 75127, 'IPHONE 17 PRO MAX 516GB', 10, 'RAFIZ', '2025-06-25 13:50:47'),
(7, 26040, 'LED LAMP 18W 6FT', 151, 'RAFIZ', '2025-06-25 16:31:40'),
(8, 89614, 'WATER HEATER 12L SINGER', 15, 'RAFIZ', '2025-06-25 16:32:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `Id` varchar(10) NOT NULL,
  `date_join` date NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_no` varchar(20) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `position` enum('user','moderator','admin','super-admin') DEFAULT 'user',
  `profile_picture` varchar(255) DEFAULT 'default.jpg',
  `active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`Id`, `date_join`, `full_name`, `email`, `phone_no`, `username`, `password`, `position`, `profile_picture`, `active`, `created_at`) VALUES
('AB0001', '2025-05-22', 'RAFIZ', 'rafiz@gmail.com', '0179761702', 'rafiz', '$2y$10$fa138javsOCo.RknSLcfJ.BBdYvuCdwl7bh6y8hnTWtZ3VtcBIA2e', 'admin', '685c0b5857d69_1750862680.png', 1, '2025-05-22 12:30:15'),
('AB0002', '2025-05-22', 'Zubair', 'admin@gmail.com', '0172343123', 'zubair', '$2y$10$U/wRSsl8uXyviGwYXPnAluiZU6VUmUXvmi.nJRfb877NIuNQ9Wgjy', 'user', 'default.jpg', 1, '2025-05-22 12:43:28'),
('AB0003', '2025-05-22', 'Test User', 'test8523@example.com', '1234567890', 'testuser602', 'password123', 'user', 'default.jpg', 1, '2025-05-22 12:44:39'),
('AB0007', '2025-05-26', 'aaasdad', 'aaaq@yahoo.com', '1234567890', 'asda', '123456', 'moderator', 'default.jpg', 1, '2025-05-26 08:55:43'),
('AB0008', '2025-05-26', 'admin', 'admin1@gmail.com', '0172343122', 'admin1', '$2y$10$RK6fL7W5ga4JX/e4e5JLce9k.SDreZ5EsFgBAMyB84MNxtKR7J2uq', 'super-admin', 'default.jpg', 1, '2025-05-26 12:05:54'),
('AB0009', '2025-06-12', 'Mohamad Syafiq', 'syafiq@gmail.com', '0175481851', 'syafiq', '$2y$10$3Razn9m3jEI634hRR2bCJOp5EKclpPjEsgEUBCfgQuKhoQP.Lrp1e', 'admin', '6857f89c17058_1750595740.jpg', 1, '2025-06-12 10:35:03'),
('AB0010', '2025-06-12', 'zubair', 'zubair123@gmail.com', '0172343123', 'zubair123', '$2y$10$hhcNHLRmE5obJVlKc1Mq/O2przno7qxfDiV2H9VOFZwoZ8VFM7ZT6', 'super-admin', 'default.jpg', 1, '2025-06-12 14:36:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer_invoices`
--
ALTER TABLE `customer_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_supplier`
--
ALTER TABLE `customer_supplier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registrationID` (`registrationID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `inventory_item`
--
ALTER TABLE `inventory_item`
  ADD PRIMARY KEY (`itemID`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_po_supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_po_item` (`purchase_order_id`,`item_id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`item_id`);

--
-- Indexes for table `stock_in_history`
--
ALTER TABLE `stock_in_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_out_history`
--
ALTER TABLE `stock_out_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer_invoices`
--
ALTER TABLE `customer_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_supplier`
--
ALTER TABLE `customer_supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `inventory_item`
--
ALTER TABLE `inventory_item`
  MODIFY `itemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98954;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `stock_in_history`
--
ALTER TABLE `stock_in_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `stock_out_history`
--
ALTER TABLE `stock_out_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_invoices`
--
ALTER TABLE `customer_invoices`
  ADD CONSTRAINT `customer_invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer_supplier` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_item` (`itemID`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `customer_supplier` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_purchase_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_item` (`itemID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
