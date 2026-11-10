DROP DATABASE IF EXISTS wiyule_motors;
CREATE DATABASE wiyule_motors CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE wiyule_motors;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `vehicle_make` varchar(50) NOT NULL,
  `vehicle_model` varchar(50) NOT NULL,
  `vehicle_year` varchar(10) NOT NULL,
  `registration` varchar(20) DEFAULT NULL,
  `services` text NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` varchar(10) NOT NULL,
  `notes` text DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL,
  `first_time_discount` tinyint(1) DEFAULT 0,
  `service_mileage` int(11) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bookings_source` (`source`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `invoice_counter` (
  `year` int(11) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_type` enum('part','labour','service','other') NOT NULL DEFAULT 'service',
  `description` varchar(500) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(20) NOT NULL,
  `type` enum('quotation','invoice') NOT NULL DEFAULT 'invoice',
  `booking_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `vehicle_info` varchar(255) DEFAULT NULL,
  `claim_reference` varchar(100) DEFAULT NULL,
  `insurance_company_id` int(11) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('none','percent','fixed') NOT NULL DEFAULT 'none',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vat_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 17.50,
  `vat_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `less_excess` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('','cash','airtel_money','tnm_mpamba','bank_transfer','card','other') NOT NULL DEFAULT '',
  `payment_date` date DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_type` (`type`),
  KEY `idx_issued` (`issued_at`),
  KEY `idx_invoices_insurer` (`insurance_company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `first_offer_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_code` varchar(255) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `last_verification_sent` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `reset_requests_count` tinyint(4) DEFAULT 0,
  `reset_requests_window_start` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `make` varchar(50) DEFAULT '',
  `model` varchar(50) DEFAULT '',
  `year` varchar(10) DEFAULT '',
  `registration` varchar(20) DEFAULT '',
  `color` varchar(30) DEFAULT '',
  `mileage` int(11) DEFAULT 0,
  `engine` varchar(50) DEFAULT '',
  `transmission` varchar(30) DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ROW COUNTS (no data)
--   bookings = 23
--   insurance_companies = 10
--   invoice_counter = 1
--   invoice_items = 41
--   invoices = 7
--   users = 10
--   vehicles = 15
SET FOREIGN_KEY_CHECKS=1;
-- our soft-delete/archive migration (local; will also go to prod later)
ALTER TABLE invoices ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER payment_status;
-- Phase 4: track when we last emailed the customer a reminder/nudge for this invoice
ALTER TABLE invoices ADD COLUMN last_reminder_at DATETIME NULL DEFAULT NULL AFTER archived_at;
-- Service-due: track when we last emailed a service-due reminder for this vehicle
ALTER TABLE vehicles ADD COLUMN last_service_reminder_at DATETIME NULL DEFAULT NULL;
-- Invoices: vehicle registration / plate number as its own field (was previously appended to vehicle_info)
ALTER TABLE invoices ADD COLUMN registration varchar(20) NOT NULL DEFAULT '' AFTER vehicle_info;
-- Bookings: customer-uploaded photos of the problem (damage / warning light) attached at booking time.
-- Files live under /uploads/bookings/ ; this table only stores the reference.
CREATE TABLE IF NOT EXISTS `booking_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime` varchar(100) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ba_booking` (`booking_id`),
  CONSTRAINT `fk_ba_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- Bookings: customer feedback on how the online booking felt (1–5 + optional comment).
-- One row per booking (UNIQUE), upserted so a customer can refine their rating.
CREATE TABLE IF NOT EXISTS `booking_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bf_booking` (`booking_id`),
  CONSTRAINT `fk_bf_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
