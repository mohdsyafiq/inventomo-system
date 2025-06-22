-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 22, 2025 at 02:39 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

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
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `address_line1`, `address_line2`) VALUES
(1, 'John Doe (Test Customer)', '123 Customer Lane', 'Kuala Lumpur'),
(2, 'Syarikat Maju (Test Supplier)', '456 Supplier Road', 'Shah Alam');

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
(4, 'CS001', 'Mohamad', 'Fauzi', 'glove', 'comtoh@gmail.com', '(601) 456-24483', 'jalan sendiri', 'Shah Alam', 'shah alam', '32500', 'SG', 'customer', '', '', '2025-06-08 14:58:03', 'active', '2025-06-08 14:58:03', '2025-06-08 16:04:25'),
(5, 'SP001', 'mohd', 'Ahmad Fauzi', 'glove', 'sasa@gmail.com', '(601) 456-24483', 'jalan sendiri', 'Shah Alam', 'shah alam', '32500', 'SG', 'supplier', 'manufacturer', 'manufacturing', '2025-06-08 14:59:09', 'active', '2025-06-08 14:59:09', '2025-06-08 14:59:09'),
(6, 'CS002', 'abu', 'ali', 'glove', 'admin@gmail.com', '(017) 599-3153', 'asas', 'Shah Alam', 'shah alam', '32500', 'MY', 'customer', '', '', '2025-06-12 12:54:57', 'active', '2025-06-12 12:54:57', '2025-06-12 12:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_item`
--

CREATE TABLE `inventory_item` (
  `itemID` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `type_product` varchar(25) NOT NULL,
  `stock` int(11) NOT NULL,
  `price` varchar(25) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_item`
--

INSERT INTO `inventory_item` (`itemID`, `product_name`, `type_product`, `stock`, `price`, `image`) VALUES
(23585, 'mesin', 'Electronic', 1000, '1020', ''),
(26040, 'Kerusi urut', 'Electronic', 2, '1000', ''),
(78110, 'mesin', 'Electronic', 100, '1000', '5b109c12eff384d888138c1aa35dad8d.jpg'),
(89614, 'contoh', 'Accessories', 1000, '2001', 'ChatGPT Image May 6, 2025, 09_30_54 PM.png'),
(93084, 'mesin', 'Accessories', 1, '1', ''),
(93831, 'alala', 'Accessories', 10, '123456', ''),
(95322, 'kerusi elektrik', 'Electronic', 2, '2000', ''),
(98023, 'asdad', 'Accessories', 123, '15963', '');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `date_issued` date NOT NULL,
  `date_due` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `customer_id`, `invoice_number`, `date_issued`, `date_due`, `notes`, `status`) VALUES
(7, 2, 'INV-20250621-064529', '2025-06-21', '2025-07-05', '', 'rejected'),
(9, 2, 'INV-20250621-065420', '2025-06-21', '2025-07-05', '', 'approved'),
(10, 2, 'INV-20250621-070258', '2025-06-21', '2025-07-05', '', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(3, 7, 2, 1, 6500.00),
(5, 9, 3, 1, 3500.00),
(6, 10, 3, 1, 3500.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `stock_quantity`) VALUES
(1, 'Peti Ais Toshiba', 5000.00, 0),
(2, 'Kerusi urut Thailand', 6500.00, 0),
(3, 'LG Mesin Basuh', 3500.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
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

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `customer_id`, `po_number`, `date_ordered`, `date_expected`, `notes`, `status`, `total_amount`, `created_at`) VALUES
(7, 5, NULL, 'PO-20250621-050651', '2025-06-21', '2025-06-28', '', 'Pending', 3710.00, '2025-06-21 03:07:02'),
(8, 5, NULL, 'PO-20250621-050717', '2025-06-21', '2025-06-28', '', 'Pending', 6890.00, '2025-06-21 03:07:24'),
(9, 5, NULL, 'PO-20250622-062405', '2025-06-22', '2025-06-29', '', 'Pending', 3710.00, '2025-06-22 04:24:28'),
(10, 5, NULL, 'PO-20250622-062445', '2025-06-22', '2025-06-29', '', 'Pending', 6890.00, '2025-06-22 04:24:53'),
(11, 5, NULL, 'PO-20250622-062537', '2025-06-22', '2025-06-29', '', 'Pending', 3710.00, '2025-06-22 04:25:57'),
(12, 5, NULL, 'PO-20250622-063329', '2025-06-22', '2025-06-29', '', 'Pending', 10600.00, '2025-06-22 04:33:59'),
(13, 5, NULL, 'PO-20250622-064221', '2025-06-22', '2025-06-29', '', 'Pending', 6890.00, '2025-06-22 04:43:14'),
(14, 5, NULL, 'PO-20250622-064319', '2025-06-22', '2025-06-29', '', 'Pending', 3710.00, '2025-06-22 04:46:08');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `quantity`, `cost_price`) VALUES
(1, 7, 3, 1, 3500.00),
(2, 8, 2, 1, 6500.00),
(3, 9, 3, 1, 3500.00),
(4, 10, 2, 1, 6500.00),
(5, 11, 3, 1, 3500.00),
(6, 12, 3, 1, 3500.00),
(7, 12, 2, 1, 6500.00),
(8, 13, 2, 1, 6500.00),
(9, 14, 3, 1, 3500.00);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_bills`
--

CREATE TABLE `supplier_bills` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `bill_number` varchar(100) NOT NULL,
  `date_received` date NOT NULL,
  `date_due` date NOT NULL,
  `notes` text DEFAULT NULL,
  `total_due` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_bills`
--

INSERT INTO `supplier_bills` (`id`, `supplier_id`, `bill_number`, `date_received`, `date_due`, `notes`, `total_due`, `status`) VALUES
(1, 2, 'BILL-XYZ-789', '2025-06-16', '2025-07-16', NULL, 1250.00, 'unpaid'),
(4, 2, 'BILL-20250621-093138', '2025-06-21', '2025-07-21', '', 3710.00, 'Pending'),
(6, 2, 'BILL-20250621-093653', '2025-06-21', '2025-07-21', '', 3710.00, 'approved'),
(7, 2, 'BILL-20250621-101115', '2025-06-21', '2025-07-21', '', 3710.00, 'kiv'),
(8, 2, 'BILL-20250621-101251', '2025-06-21', '2025-07-21', '', 3710.00, 'rejected'),
(9, 2, 'BILL-20250621-101322', '2025-06-21', '2025-07-21', '', 3710.00, 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_bill_items`
--

CREATE TABLE `supplier_bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_bill_items`
--

INSERT INTO `supplier_bill_items` (`id`, `bill_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(1, 4, 3, 1, 3500.00),
(3, 6, 3, 1, 3500.00),
(4, 7, 3, 1, 3500.00),
(5, 8, 3, 1, 3500.00),
(6, 9, 3, 1, 3500.00);

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
('AB0001', '2025-05-22', 'abu', 'sasa@gmail.com', '11111111', 'aaaa', '$2y$10$fDAAQSCejWP1xmPq/LhNGOTyFWfmCbBb6S/yxcJb6n.GH2h9pvQFa', 'user', 'default.jpg', 0, '2025-05-22 12:30:15'),
('AB0002', '2025-05-22', 'Zubair', 'admin@gmail.com', '0172343123', 'zubair', '$2y$10$U/wRSsl8uXyviGwYXPnAluiZU6VUmUXvmi.nJRfb877NIuNQ9Wgjy', 'user', 'default.jpg', 1, '2025-05-22 12:43:28'),
('AB0003', '2025-05-22', 'Test User', 'test8523@example.com', '1234567890', 'testuser602', 'password123', 'user', 'default.jpg', 1, '2025-05-22 12:44:39'),
('AB0007', '2025-05-26', 'aaasdad', 'aaaq@yahoo.com', '1234567890', 'asda', '123456', 'moderator', 'default.jpg', 1, '2025-05-26 08:55:43'),
('AB0008', '2025-05-26', 'admin', 'admin1@gmail.com', '0172343122', 'admin1', '$2y$10$RK6fL7W5ga4JX/e4e5JLce9k.SDreZ5EsFgBAMyB84MNxtKR7J2uq', 'super-admin', 'default.jpg', 1, '2025-05-26 12:05:54'),
('AB0009', '2025-06-12', 'Mohamad Syafiq', 'syafiq@gmail.com', '0175481852', 'syafiq', '$2y$10$3Razn9m3jEI634hRR2bCJOp5EKclpPjEsgEUBCfgQuKhoQP.Lrp1e', 'admin', '6857f89c17058_1750595740.jpg', 1, '2025-06-12 10:35:03'),
('AB0010', '2025-06-12', 'zubair', 'zubair123@gmail.com', '0172343123', 'zubair123', '$2y$10$hhcNHLRmE5obJVlKc1Mq/O2przno7qxfDiV2H9VOFZwoZ8VFM7ZT6', 'super-admin', 'default.jpg', 1, '2025-06-12 14:36:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_bills`
--
ALTER TABLE `supplier_bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `supplier_bill_items`
--
ALTER TABLE `supplier_bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `product_id` (`product_id`);

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
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer_supplier`
--
ALTER TABLE `customer_supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_item`
--
ALTER TABLE `inventory_item`
  MODIFY `itemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98954;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_bills`
--
ALTER TABLE `supplier_bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `supplier_bill_items`
--
ALTER TABLE `supplier_bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `items_fk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_supplier_id` FOREIGN KEY (`supplier_id`) REFERENCES `customer_supplier` (`id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `supplier_bills`
--
ALTER TABLE `supplier_bills`
  ADD CONSTRAINT `bills_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `supplier_bill_items`
--
ALTER TABLE `supplier_bill_items`
  ADD CONSTRAINT `fk_bill_id` FOREIGN KEY (`bill_id`) REFERENCES `supplier_bills` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_id_bill_items` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
