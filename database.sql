-- MedLink AI Database
-- Safe import file: creates missing database/tables and keeps existing data.
-- Import: C:\xampp\mysql\bin\mysql.exe -u root < database.sql

CREATE DATABASE IF NOT EXISTS medlink_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medlink_ai;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `diseases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `disease_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `drugs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drug_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `input_type` varchar(50) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `result_summary` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_history_user` (`user_id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `predictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `input_type` enum('drug','disease') NOT NULL,
  `input_name` varchar(150) NOT NULL,
  `result_name` varchar(255) NOT NULL,
  `score` decimal(10,4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo accounts. INSERT IGNORE keeps existing accounts if this file is imported again.
INSERT IGNORE INTO `users`
  (`id`, `username`, `email`, `password`, `role`, `created_at`)
VALUES
  (2, 'tienne', 'trannhattien9801@gmail.com', '$2y$10$mLkZsrT5a53LIWmjCAoX/ezQazet6T9YFre6wiw5sO1NnJO6K/uFy', 'admin', '2026-04-10 19:13:13'),
  (3, 'tien', 'nv003@gmail.com', '$2y$10$PRfNb5k1FQ1uGhFqZ3oLMOBshYgA4ZtlxQfuvWpCXz5Fv7o9ukngu', 'user', '2026-04-10 19:13:31');

SET FOREIGN_KEY_CHECKS=1;
