-- ============================================================
-- Wiyule Motors — Database Setup
-- Run this in phpMyAdmin (http://localhost/phpmyadmin)
-- Go to SQL tab → paste this → click Go
-- ============================================================

-- 1. Create the database
CREATE DATABASE IF NOT EXISTS `wiyule_motors`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `wiyule_motors`;

-- 2. Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `full_name`  VARCHAR(100)  NOT NULL,
  `email`      VARCHAR(150)  NOT NULL UNIQUE,
  `password`   VARCHAR(255)  NOT NULL,
  `phone`      VARCHAR(30)   DEFAULT NULL,
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Bookings table
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT           DEFAULT NULL,
  `full_name`      VARCHAR(100)  NOT NULL,
  `phone`          VARCHAR(30)   NOT NULL,
  `email`          VARCHAR(150)  DEFAULT NULL,
  `vehicle_make`   VARCHAR(50)   NOT NULL,
  `vehicle_model`  VARCHAR(50)   NOT NULL,
  `vehicle_year`   VARCHAR(10)   NOT NULL,
  `registration`   VARCHAR(20)   DEFAULT NULL,
  `services`       TEXT          NOT NULL,
  `booking_date`   DATE          NOT NULL,
  `booking_time`   VARCHAR(10)   NOT NULL,
  `notes`          TEXT          DEFAULT NULL,
  `status`         ENUM('pending','confirmed','completed','cancelled')
                   DEFAULT 'pending',
  `created_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 4. Vehicles table (stores user vehicle details)
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT           NOT NULL UNIQUE,
  `make`           VARCHAR(50)   DEFAULT '',
  `model`          VARCHAR(50)   DEFAULT '',
  `year`           VARCHAR(10)   DEFAULT '',
  `registration`   VARCHAR(20)   DEFAULT '',
  `color`          VARCHAR(30)   DEFAULT '',
  `mileage`        INT           DEFAULT 0,
  `engine`         VARCHAR(50)   DEFAULT '',
  `transmission`   VARCHAR(30)   DEFAULT '',
  `created_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Done! You can now test the site at:
--   http://localhost/wiyule-motors-website/
-- ============================================================
