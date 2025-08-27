-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 24, 2025 at 05:55 PM
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
-- Database: `file_repo`
--

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `upload_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `folder_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`id`, `user_id`, `filename`, `original_name`, `upload_time`, `folder_id`) VALUES
(4, 1, '1751618999_2025.2.cse412_courseOutline.pdf', '2025.2.cse412_courseOutline.pdf', '2025-07-04 08:49:59', NULL),
(8, 12, '1751629491_My website.docx', 'My website.docx', '2025-07-04 11:44:51', NULL),
(16, 17, '1751645063_2025.2.cse412_courseOutline.pdf', '2025.2.cse412_courseOutline.pdf', '2025-07-04 16:04:23', NULL),
(17, 19, '1752393999_does-perfume-expire-hero-mudc-042820.jpg', 'does-perfume-expire-hero-mudc-042820.jpg', '2025-07-13 08:06:39', NULL),
(18, 19, '1752394290_1752079842_img-250709221340-001.pdf', '1752079842_img-250709221340-001.pdf', '2025-07-13 08:11:30', NULL),
(20, 21, '6881f51cdc11b.txt', 'Assignment08', '2025-07-24 08:55:56', 1),
(21, 21, '6881f89951c0a.zip', 'folder_file_manager.zip', '2025-07-24 09:10:49', 2),
(24, 21, '6881ff529f163.docx', '2023-1-60-042_Lab04.docx', '2025-07-24 09:39:30', 3),
(25, 21, '6881ff7c8e328.png', 'Ping.png', '2025-07-24 09:40:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `user_id`, `name`) VALUES
(1, 21, 'Assignment'),
(3, 21, 'Lab_Work'),
(2, 21, 'zip file');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `phone`) VALUES
(1, 'red441651@gmail.com', '$2y$10$xDuyKgKWAnSU/Zj3KQAQL.f03JBXbG6jwyXDiu8/T9GvZD9N6/ZDC', NULL),
(12, '', '$2y$10$VoDUCEvYTIoy6EO5.yDZnOveofLx2S.67cbhxqi9Lyv2ImOD0mli2', '01758922051'),
(17, '2023-1-60-042@std.ewubd.edu', '$2y$10$VGJqjj.Wdg3mFw9q2R/3WuZ5b2F18m/JDpD73jtpSuCi3zNSl3Sru', NULL),
(18, '2022-1-60-389@std.ewubd.edu', '$2y$10$PEXB..WoAtdm6SVtbRPsvuJ2zeyF.jWNf4zZ6vJKphBA2DYyngMMy', NULL),
(19, '2023-1-60-050@std.ewubd.edu', '$2y$10$nagOfvSJNGFzyKsVJypa3e8May1bK.6OBgiZlRiyHK2oO2Ym3UU7a', NULL),
(20, '2023-1-60-0245@std.ewubd.edu', '$2y$10$7WBosTeQVO6sB4J1n21.aeOWzajQGD6AXsR5w0GveyQ7Wgy3KZs.m', NULL),
(21, '2023-1-60-020@std.ewubd.edu', '$2y$10$Koo1jyS2b0xvU.vKreaYouVfAYW/.gd4rrITV43nSgnBtCym3BCgS', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_folder` (`folder_id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
ALTER TABLE users
  ADD COLUMN role ENUM('student','professor') NOT NULL DEFAULT 'student' AFTER phone;

-- 2) Classrooms created by professors
CREATE TABLE IF NOT EXISTS classrooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  professor_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_classrooms_professor
    FOREIGN KEY (professor_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Posts inside a classroom (professor announcements/assignments)
CREATE TABLE IF NOT EXISTS classroom_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  classroom_id INT NOT NULL,
  professor_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_posts_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_posts_professor FOREIGN KEY (professor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;