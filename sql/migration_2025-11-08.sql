-- 2025-11-08: Add HSN/TSN to vehicles

-- Add HSN if not exists
ALTER TABLE `vehicles`
ADD COLUMN `hsn` VARCHAR(16) NULL AFTER `user_id`;

-- Add TSN if not exists
ALTER TABLE `vehicles`
ADD COLUMN `tsn` VARCHAR(16) NULL AFTER `hsn`;

