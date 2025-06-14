-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 14, 2025 at 10:49 AM
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
(78110, 'mesin', 'Electronic', 100, '1000', '5b109c12eff384d888138c1aa35dad8d.jpg'),
(89614, 'contoh', 'Accessories', 1000, '2001', 'ChatGPT Image May 6, 2025, 09_30_54 PM.png'),
(93084, 'mesin', 'Accessories', 1, '1', ''),
(93831, 'alala', 'Accessories', 10, '123456', ''),
(98023, 'asdad', 'Accessories', 123, '15963', '');

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
('AB0009', '2025-06-12', 'Mohamad Syafiq', 'syafiq@gmail.com', '0175481852', 'syafiq', '$2y$10$3Razn9m3jEI634hRR2bCJOp5EKclpPjEsgEUBCfgQuKhoQP.Lrp1e', 'moderator', 'default.jpg', 1, '2025-06-12 10:35:03'),
('AB0010', '2025-06-12', 'zubair', 'zubair123@gmail.com', '0172343123', 'zubair123', '$2y$10$hhcNHLRmE5obJVlKc1Mq/O2przno7qxfDiV2H9VOFZwoZ8VFM7ZT6', 'super-admin', 'default.jpg', 1, '2025-06-12 14:36:17');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `customer_supplier`
--
ALTER TABLE `customer_supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_item`
--
ALTER TABLE `inventory_item`
  MODIFY `itemID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98954;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
