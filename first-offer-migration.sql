-- ============================================================
-- Wiyule Motors — First-Time Customer Offer Tracking
-- Run ONCE in phpMyAdmin to enable the 10% first-time discount
-- ============================================================

-- Add a column to track if user has used their first-time offer
ALTER TABLE `users`
  ADD COLUMN `first_offer_used` TINYINT(1) DEFAULT 0 AFTER `phone`;

-- Add a column on bookings to mark which ones got the first-time discount
ALTER TABLE `bookings`
  ADD COLUMN `first_time_discount` TINYINT(1) DEFAULT 0 AFTER `notes`;

-- Mark ALL existing users as having already used the offer
-- (so only brand-new signups qualify going forward)
UPDATE `users` SET `first_offer_used` = 1 WHERE `id` > 0;

-- Also mark existing bookings as not discounted (already happened)
UPDATE `bookings` SET `first_time_discount` = 0 WHERE `id` > 0;
