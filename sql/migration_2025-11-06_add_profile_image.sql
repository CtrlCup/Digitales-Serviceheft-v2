-- Migration: Add profile_image column to vehicles
-- Date: 2025-11-06
-- Purpose: Store relative path to uploaded vehicle profile image

-- Compatible approach for MySQL 5.7+ (no IF NOT EXISTS on ADD COLUMN)
-- Only runs the ALTER if the column does not already exist.

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'profile_image'
);

SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE vehicles ADD COLUMN profile_image VARCHAR(512) NULL AFTER odometer_unit',
  'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional index (skip if not needed). Uncomment if desired.
-- SET @idx_exists := (
--   SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles' AND INDEX_NAME = 'idx_vehicles_profile_image'
-- );
-- SET @ddl2 := IF(@idx_exists = 0, 'CREATE INDEX idx_vehicles_profile_image ON vehicles (profile_image)', 'DO 0');
-- PREPARE stmt2 FROM @ddl2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;
