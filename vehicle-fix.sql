-- ============================================================
-- Wiyule Motors — Vehicle Table Fix
-- Run this in phpMyAdmin to allow multiple vehicles per user
-- ============================================================

-- Remove the UNIQUE constraint on user_id
ALTER TABLE `vehicles` DROP INDEX `user_id`;

-- Add a regular index instead (for fast lookups)
ALTER TABLE `vehicles` ADD INDEX `idx_user_id` (`user_id`);
