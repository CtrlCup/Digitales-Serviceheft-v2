-- Migration: Initial Database Schema for Digital Servicebook (2025-11-04)
-- Charset: utf8mb4, Engine: InnoDB

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Drops (safe order not required when FK checks are disabled)
DROP TABLE IF EXISTS vehicle_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS inspections;
DROP TABLE IF EXISTS parts_inventory;
DROP TABLE IF EXISTS fuel_logs;
DROP TABLE IF EXISTS reminders;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS service_items;
DROP TABLE IF EXISTS service_entries;
DROP TABLE IF EXISTS vehicles;

SET FOREIGN_KEY_CHECKS = 1;

-- vehicles: Stammdaten pro Fahrzeug
CREATE TABLE IF NOT EXISTS vehicles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  user_id BIGINT UNSIGNED NULL COMMENT 'Besitzer/Account (FK zu users.id, optional)',
  vin VARCHAR(64) NULL COMMENT 'Fahrzeug-Identifizierungsnummer (VIN)',
  license_plate VARCHAR(32) NULL COMMENT 'Kennzeichen',
  make VARCHAR(100) NOT NULL COMMENT 'Hersteller',
  model VARCHAR(100) NOT NULL COMMENT 'Modell',
  trim_level VARCHAR(100) NULL COMMENT 'Ausstattungslinie/Trim',
  year SMALLINT UNSIGNED NULL COMMENT 'Baujahr (YYYY)',
  engine_code VARCHAR(64) NULL COMMENT 'Motorcode',
  fuel_type ENUM('petrol','diesel','electric','hybrid','lpg','cng','hydrogen','other') NOT NULL DEFAULT 'petrol' COMMENT 'Kraftstofftyp',
  color VARCHAR(64) NULL COMMENT 'Farbe',
  odometer_km INT UNSIGNED NULL COMMENT 'Aktueller Kilometerstand (km)',
  odometer_unit ENUM('km','mi') NOT NULL DEFAULT 'km' COMMENT 'Einheit für Kilometerstand',
  profile_image VARCHAR(512) NULL COMMENT 'Relativer Pfad zum Fahrzeug-Profilbild',
  purchase_date DATE NULL COMMENT 'Kaufdatum',
  purchase_price DECIMAL(10,2) NULL COMMENT 'Kaufpreis',
  purchase_mileage_km INT UNSIGNED NULL COMMENT 'Kilometerstand beim Kauf (km)',
  sale_date DATE NULL COMMENT 'Verkaufsdatum',
  sale_price DECIMAL(10,2) NULL COMMENT 'Verkaufspreis',
  notes TEXT NULL COMMENT 'Freitext/Notizen zum Fahrzeug',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  CONSTRAINT uq_vehicles_vin UNIQUE KEY (vin),
  KEY idx_vehicles_license_plate (license_plate),
  KEY idx_vehicles_user (user_id),
  CONSTRAINT fk_vehicles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fahrzeug-Stammdaten';

-- service_entries: Serviceeinträge pro Fahrzeug
CREATE TABLE IF NOT EXISTS service_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  service_date DATE NOT NULL COMMENT 'Servicedatum',
  odometer_km INT UNSIGNED NULL COMMENT 'Kilometerstand (km) zum Servicezeitpunkt',
  title VARCHAR(255) NOT NULL COMMENT 'Titel/Kurzbeschreibung des Service',
  description TEXT NULL COMMENT 'Detailbeschreibung',
  workshop VARCHAR(255) NULL COMMENT 'Werkstatt/Servicebetrieb',
  total_cost DECIMAL(10,2) NULL COMMENT 'Gesamtkosten für den Service',
  next_service_date DATE NULL COMMENT 'Nächstes Service-Datum (optional)',
  next_service_odometer_km INT UNSIGNED NULL COMMENT 'Nächster Service bei km (optional)',
  under_warranty TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Service in Garantie? 1=Ja,0=Nein',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_service_entries_vehicle (vehicle_id),
  KEY idx_service_entries_date (service_date),
  KEY idx_service_entries_next_date (next_service_date),
  CONSTRAINT fk_service_entries_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Serviceeinträge pro Fahrzeug';

-- service_items: Einzelpositionen eines Serviceeintrags
CREATE TABLE IF NOT EXISTS service_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  service_entry_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu service_entries.id',
  item_type ENUM('part','labor','fluid','other') NOT NULL DEFAULT 'part' COMMENT 'Typ der Position: Teil/Arbeit/Flüssigkeit/sonstiges',
  name VARCHAR(255) NOT NULL COMMENT 'Bezeichnung der Position',
  part_number VARCHAR(128) NULL COMMENT 'Teilenummer (falls zutreffend)',
  manufacturer VARCHAR(128) NULL COMMENT 'Hersteller (falls Teil/Flüssigkeit)',
  quantity DECIMAL(10,3) NULL COMMENT 'Menge',
  unit VARCHAR(32) NULL COMMENT 'Mengeneinheit (z.B. Stk, L, h)',
  unit_price DECIMAL(10,2) NULL COMMENT 'Einzelpreis',
  total_price DECIMAL(10,2) NULL COMMENT 'Gesamtpreis der Position',
  notes TEXT NULL COMMENT 'Notizen zur Position',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_service_items_entry (service_entry_id),
  KEY idx_service_items_part_number (part_number),
  CONSTRAINT fk_service_items_entry FOREIGN KEY (service_entry_id) REFERENCES service_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Einzelpositionen eines Serviceeintrags';

-- documents: Dokumente (fahrzeug- oder servicebezogen)
CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  service_entry_id BIGINT UNSIGNED NULL COMMENT 'Optionale Verknüpfung zu service_entries.id',
  path VARCHAR(512) NOT NULL COMMENT 'Dateipfad/URL zum Dokument (Datei wird nicht in DB gespeichert)',
  mime_type VARCHAR(128) NULL COMMENT 'MIME-Typ',
  title VARCHAR(255) NULL COMMENT 'Titel/Displayname',
  description TEXT NULL COMMENT 'Beschreibung/Notizen',
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload-Zeitpunkt',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_documents_vehicle (vehicle_id),
  KEY idx_documents_service (service_entry_id),
  CONSTRAINT fk_documents_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_documents_service FOREIGN KEY (service_entry_id) REFERENCES service_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumente zu Fahrzeugen/Services (nur Pfad)';

-- reminders: Erinnerungen
CREATE TABLE IF NOT EXISTS reminders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  title VARCHAR(255) NOT NULL COMMENT 'Titel/Betreff der Erinnerung',
  description TEXT NULL COMMENT 'Beschreibung/Details',
  remind_at_date DATE NULL COMMENT 'Erinnern am (Datum, optional)',
  remind_at_odometer_km INT UNSIGNED NULL COMMENT 'Erinnern bei km-Stand (optional)',
  is_completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Erledigt? 1=Ja,0=Nein',
  completed_at TIMESTAMP NULL COMMENT 'Zeitpunkt der Erledigung (optional)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_reminders_vehicle (vehicle_id),
  KEY idx_reminders_date (remind_at_date),
  CONSTRAINT fk_reminders_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Erinnerungen für Services/Inspektionen';

-- fuel_logs: Tankvorgänge
CREATE TABLE IF NOT EXISTS fuel_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  fill_date DATE NOT NULL COMMENT 'Tankdatum',
  odometer_km INT UNSIGNED NULL COMMENT 'Kilometerstand (km) beim Tanken',
  volume_liters DECIMAL(10,3) NOT NULL COMMENT 'Getankte Menge (Liter)',
  price_total DECIMAL(10,2) NOT NULL COMMENT 'Gesamtpreis',
  price_per_liter DECIMAL(10,3) NULL COMMENT 'Preis pro Liter',
  station VARCHAR(255) NULL COMMENT 'Tankstelle',
  is_full_tank TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Vollgetankt? 1=Ja,0=Nein',
  notes TEXT NULL COMMENT 'Notizen',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_fuel_logs_vehicle (vehicle_id),
  KEY idx_fuel_logs_date (fill_date),
  CONSTRAINT fk_fuel_logs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tankvorgänge mit Kosten/Verbrauch';

-- parts_inventory: Teilelager
CREATE TABLE IF NOT EXISTS parts_inventory (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  name VARCHAR(255) NOT NULL COMMENT 'Bezeichnung des Teils',
  part_number VARCHAR(128) NULL COMMENT 'Teilenummer',
  manufacturer VARCHAR(128) NULL COMMENT 'Hersteller',
  location VARCHAR(128) NULL COMMENT 'Lagerort',
  quantity DECIMAL(10,3) NOT NULL DEFAULT 0 COMMENT 'Menge im Lager',
  unit VARCHAR(32) NULL COMMENT 'Mengeneinheit (z.B. Stk)',
  price_per_unit DECIMAL(10,2) NULL COMMENT 'Preis pro Einheit',
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR' COMMENT 'Währung (ISO-4217)',
  notes TEXT NULL COMMENT 'Notizen',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_parts_inventory_vehicle (vehicle_id),
  KEY idx_parts_inventory_part_number (part_number),
  CONSTRAINT fk_parts_inventory_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lager eigener Ersatzteile pro Fahrzeug';

-- inspections: Prüfungen (TÜV/AU/sonstige)
CREATE TABLE IF NOT EXISTS inspections (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  inspection_date DATE NOT NULL COMMENT 'Datum der Prüfung',
  odometer_km INT UNSIGNED NULL COMMENT 'Kilometerstand (km) bei der Prüfung',
  inspection_type ENUM('TUEV','AU','GENERAL','OTHER') NOT NULL DEFAULT 'GENERAL' COMMENT 'Art der Prüfung',
  result ENUM('pass','fail','advisory') NOT NULL DEFAULT 'pass' COMMENT 'Ergebnis',
  summary TEXT NULL COMMENT 'Zusammenfassung/Notizen',
  expiry_date DATE NULL COMMENT 'Ablaufdatum (z.B. HU-Gültigkeit)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_inspections_vehicle (vehicle_id),
  KEY idx_inspections_date (inspection_date),
  CONSTRAINT fk_inspections_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Prüfungen/Inspektionen (TÜV/AU usw.)';

-- notes: Notizen/ToDos
CREATE TABLE IF NOT EXISTS notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  note_type ENUM('general','todo','idea') NOT NULL DEFAULT 'general' COMMENT 'Typ der Notiz',
  title VARCHAR(255) NOT NULL COMMENT 'Titel/Betreff',
  body TEXT NULL COMMENT 'Inhalt/Notiztext',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium' COMMENT 'Priorität',
  due_date DATE NULL COMMENT 'Fälligkeitsdatum (optional)',
  is_completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Erledigt? 1=Ja,0=Nein',
  completed_at TIMESTAMP NULL COMMENT 'Zeitpunkt der Erledigung (optional)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  KEY idx_notes_vehicle (vehicle_id),
  KEY idx_notes_due (due_date),
  CONSTRAINT fk_notes_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Freie Notizen/ToDos zum Fahrzeug';

-- tags: Schlagwörter
CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Primärschlüssel',
  name VARCHAR(64) NOT NULL COMMENT 'Tag-Name',
  color VARCHAR(16) NULL COMMENT 'Farbcode (z.B. #RRGGBB oder Token)',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellt am',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Zuletzt aktualisiert',
  CONSTRAINT uq_tags_name UNIQUE KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Schlagwörter zur Kategorisierung';

-- vehicle_tags: m:n Zuordnung vehicles ↔ tags
CREATE TABLE IF NOT EXISTS vehicle_tags (
  vehicle_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu vehicles.id',
  tag_id BIGINT UNSIGNED NOT NULL COMMENT 'FK zu tags.id',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Zuordnung erstellt am',
  PRIMARY KEY (vehicle_id, tag_id),
  KEY idx_vehicle_tags_tag (tag_id),
  CONSTRAINT fk_vehicle_tags_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_vehicle_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='m:n Zuordnung zwischen Fahrzeugen und Tags';
