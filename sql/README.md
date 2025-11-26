# SQL Dateien - Übersicht

## Für neue Installation: ⭐

### `install_complete.sql` (EMPFOHLEN)
**Die komplette, aktuelle Datenbank-Struktur in einer Datei.**

Diese Datei enthält:
- ✅ Alle Tabellen (users, vehicles, service_entries, etc.)
- ✅ Service Reminder System (reminder_days_advance, service_reminders_sent)
- ✅ Alle Indizes und Foreign Keys
- ✅ Kommentare und Dokumentation

**Installation:**
```bash
# Via MySQL CLI
mysql -u DEIN_USER -p DEINE_DB < sql/install_complete.sql

# Via phpMyAdmin
# 1. Datenbank auswählen
# 2. "Importieren" klicken
# 3. install_complete.sql hochladen
# 4. "OK" klicken
```

**Nach der Installation:**
1. Admin-Benutzer anlegen: `php create_admin.php?confirm=1`
2. SMTP-Einstellungen in `src/config.php` konfigurieren
3. Cron-Job für Erinnerungen einrichten (siehe `cron/README.md`)

---

## Migrations-Dateien (für bestehende Installationen):

### `add_service_reminders.sql`
Migration zum Hinzufügen des Service-Erinnerungssystems zu einer bestehenden Installation.

**Verwendung:**
```bash
mysql -u DEIN_USER -p DEINE_DB < sql/add_service_reminders.sql
```

Fügt hinzu:
- `users.reminder_days_advance` (INT, default 7)
- `users.reminder_enabled` (TINYINT, default 1)
- Tabelle `service_reminders_sent`

---

## Archivierte/Legacy-Dateien:

### `digitales_serviceheft_schema.sql`
Vorherige Version des Schemas ohne Service Reminders.
**Veraltet - nutze stattdessen `install_complete.sql`**

### `db_333670_7.sql`
Backup der Produktions-Datenbank.
**Nur für Referenz/Backup**

### `2025_11_11_service_schema.sql`
Alte Schema-Version.
**Veraltet - nutze stattdessen `install_complete.sql`**

### `import-Database.sql`, `update-Database.sql`, `update.sql`
Legacy-Migrations-Dateien.
**Veraltet - nutze stattdessen `install_complete.sql`**

---

## Empfohlener Workflow:

### Szenario 1: Neue Installation (leere Datenbank)
```bash
# 1. Komplettes Schema importieren
mysql -u user -p database < sql/install_complete.sql

# 2. Admin-Benutzer erstellen
php create_admin.php?confirm=1

# 3. Fertig!
```

### Szenario 2: Bestehende Installation aktualisieren
```bash
# Wenn du bereits eine Installation hast, aber Service Reminders fehlen:
mysql -u user -p database < sql/add_service_reminders.sql
```

### Szenario 3: Backup erstellen
```bash
# Alle Daten sichern
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Nur Struktur sichern (ohne Daten)
mysqldump -u user -p --no-data database > schema_backup.sql
```

---

## Tabellen-Übersicht:

### Benutzer & Authentifizierung
- `users` - Benutzerkonten
- `user_2fa` - Two-Factor Authentication
- `email_verifications` - E-Mail-Verifizierung
- `email_change_requests` - E-Mail-Änderungsanfragen
- `password_reset_tokens` - Passwort-Reset-Tokens
- `webauthn_credentials` - Passkeys/WebAuthn
- `login_audit` - Login-Versuchsprotokoll

### Fahrzeuge & Services
- `vehicles` - Fahrzeugstammdaten
- `service_entries` - Wartungseinträge
- `service_items` - Einzelposten einer Wartung
- `service_reminders_sent` - Protokoll gesendeter Erinnerungen

### System
- `site_settings` - Systemeinstellungen

---

## Häufige Probleme:

### "Table already exists"
**Lösung:** Die SQL-Dateien verwenden `CREATE TABLE IF NOT EXISTS`, daher ist das normal und kein Fehler.

### "Foreign key constraint fails"
**Lösung:** Stelle sicher, dass die Tabellen in der richtigen Reihenfolge erstellt werden. `install_complete.sql` macht das automatisch.

### "Unknown column in users"
**Problem:** Schema ist veraltet, Service Reminder Spalten fehlen.
**Lösung:** Führe `add_service_reminders.sql` aus.

### Datenbank zurücksetzen
```bash
# ACHTUNG: Löscht ALLE Daten!
mysql -u user -p -e "DROP DATABASE database_name; CREATE DATABASE database_name;"
mysql -u user -p database_name < sql/install_complete.sql
```

---

## Support

Bei Fragen oder Problemen:
1. Prüfe ob die richtige SQL-Datei verwendet wurde
2. Prüfe MySQL-Fehlerlog: `tail -f /var/log/mysql/error.log`
3. Teste Verbindung: `mysql -u user -p -e "SHOW DATABASES;"`
