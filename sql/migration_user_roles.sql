-- Migration: User Roles & Settings System
-- Erweitert das Benutzergruppen-System (Viewer, User, Admin, Owner)
-- Fügt Website-Einstellungen hinzu

-- Tabelle für Website-Einstellungen
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial-Einstellungen einfügen
INSERT INTO site_settings (setting_key, setting_value, updated_at) 
VALUES ('registration_enabled', '1', NOW())
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Hinweis: Die users.role Spalte verwendet bereits VARCHAR(50)
-- Die Werte sind nun: 'viewer', 'user', 'admin', 'owner'
-- Standard ist 'user' (wie in schema.sql definiert)

-- Um den ersten Benutzer als Owner zu markieren, führe aus:
-- UPDATE users SET role = 'owner' WHERE id = 1;
-- (Oder verwende create_admin.php mit angepasster Logik)
