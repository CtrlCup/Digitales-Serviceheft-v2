# Service Reminder System - Implementierung abgeschlossen ✓

## Übersicht

Das automatische E-Mail-Erinnerungssystem für fällige Wartungen (TÜV, Service, Ölwechsel) wurde vollständig implementiert.

## Was wurde implementiert?

### 1. Datenbank-Struktur
**Datei:** `sql/add_service_reminders.sql`

- `users` Tabelle erweitert:
  - `reminder_enabled` (TINYINT): E-Mail-Erinnerungen aktiviert/deaktiviert (Standard: aktiviert)
  - `reminder_days_advance` (INT): Tage im Voraus für Erinnerung (Standard: 7 Tage)

- Neue Tabelle `service_reminders_sent`:
  - Protokolliert gesendete Erinnerungen
  - Verhindert Duplikate (keine erneute Erinnerung innerhalb 7 Tagen)
  - Speichert: service_entry_id, user_id, reminder_type, sent_at, due_date, due_km

### 2. Benutzeroberfläche
**Datei:** `public/account.php`

Neue Sektion "Erinnerungseinstellungen" in Account-Einstellungen:
- ✓ Checkbox: E-Mail-Erinnerungen aktivieren/deaktivieren
- ✓ Eingabefeld: Tage im Voraus (1-90 Tage)
- ✓ Speichern-Button mit Erfolgsanzeige
- ✓ Automatische Spaltenprüfung und -erstellung (kein manuelles ALTER TABLE nötig)

### 3. Cron-Script
**Datei:** `cron/send_service_reminders.php`

Automatisches Script für tägliche Ausführung:
- ✓ CLI-only (Sicherheit)
- ✓ Lädt alle Benutzer mit aktivierten Erinnerungen
- ✓ Prüft fällige Wartungen:
  - **Datumsbasiert:** `next_due_date` innerhalb X Tage
  - **Kilometerbasiert:** Aktueller Stand ≥ (next_due_km - 1.000 km)
- ✓ Duplikatschutz (keine Wiederholung innerhalb 7 Tagen)
- ✓ Mehrsprachige E-Mails (basierend auf Benutzer-Locale)
- ✓ HTML + Text-Format
- ✓ Detaillierte Konsolen-Ausgabe für Monitoring
- ✓ Error-Logging

### 4. Übersetzungen
**Dateien:** `lang/de.php`, `lang/en.php`

Neue Translation Keys:
```
reminder_settings, reminder_enabled, reminder_days_advance, 
reminder_days_advance_hint, reminder_email_subject, 
reminder_email_greeting, reminder_email_intro, 
reminder_email_vehicle_label, reminder_email_service_type,
reminder_email_due_date, reminder_email_due_km, 
reminder_email_current_km, reminder_email_footer,
reminder_email_cta, reminder_email_signature, reminder_saved
```

### 5. Dokumentation
**Datei:** `cron/README.md`

Vollständige Setup- und Troubleshooting-Anleitung:
- Cron-Job-Konfiguration (3 Optionen)
- Testing & Debugging
- Wartung & Cleanup
- Troubleshooting-Guide

## Nächste Schritte (Deployment)

### Schritt 1: Datenbank-Migration ausführen

```bash
mysql -u DEIN_USER -p DEINE_DB < sql/add_service_reminders.sql
```

Oder über phpMyAdmin:
1. Datei `sql/add_service_reminders.sql` öffnen
2. SQL-Code kopieren
3. In phpMyAdmin unter "SQL" einfügen und ausführen

**Wichtig:** Die Migration ist sicher und kann mehrfach ausgeführt werden (IF NOT EXISTS).

### Schritt 2: Cron-Job einrichten

**Option A: System Cron (empfohlen)**
```bash
crontab -e
```
Füge hinzu:
```
0 8 * * * /usr/bin/php /home/DEIN_USER/website/cron/send_service_reminders.php >> /home/DEIN_USER/website/cron/reminder.log 2>&1
```

**Option B: Webhosting Control Panel**
- Gehe zu "Cron Jobs" im Control Panel
- Befehl: `/usr/bin/php /absoluter/pfad/cron/send_service_reminders.php`
- Zeitplan: Täglich 08:00 Uhr
- Ausgabe nach: `/pfad/zu/cron/reminder.log`

### Schritt 3: Testen

**Manueller Test:**
```bash
php cron/send_service_reminders.php
```

**Test mit echten Daten:**
1. Gehe zu Account-Einstellungen → Erinnerungen aktivieren (7 Tage)
2. Erstelle Service-Eintrag mit `next_due_date` in 7 Tagen
3. Führe Cron-Script aus: `php cron/send_service_reminders.php`
4. Prüfe E-Mail-Posteingang

**Erwartete Ausgabe:**
```
[2024-11-25 08:00:00] Starting service reminder check...
Found 1 users with reminders enabled.
✓ Sent reminder to deine@email.de for 1 service(s)
[2024-11-25 08:00:03] Reminder check completed.
Summary: 1 emails sent, 0 skipped
```

### Schritt 4: Website aktualisieren (optional)

Lade folgende Dateien auf deinen Webserver:
- `public/account.php` (aktualisiert)
- `lang/de.php` (aktualisiert)
- `lang/en.php` (aktualisiert)
- `cron/send_service_reminders.php` (neu)
- `cron/README.md` (neu)

## Funktionsweise für Benutzer

### Für den Endbenutzer:

1. **Einstellungen anpassen:**
   - Account → Erinnerungseinstellungen
   - Checkbox aktivieren/deaktivieren
   - Tage im Voraus festlegen (1-90)

2. **Service-Einträge anlegen:**
   - Beim Erstellen eines Service-Eintrags (TÜV, Wartung, Ölwechsel)
   - `next_due_date` und/oder `next_due_km` angeben
   - System errechnet automatisch Fälligkeiten

3. **E-Mail erhalten:**
   - Täglich um 8:00 Uhr prüft das System
   - Bei fälliger Wartung: E-Mail mit Übersicht
   - Link direkt zur Fahrzeugübersicht
   - Keine Duplikate (max. 1x pro Woche für denselben Service)

### E-Mail-Beispiel:

**Betreff:** Wartungserinnerung für dein Fahrzeug

**Inhalt:**
```
Hallo Max Mustermann,

Dies ist eine Erinnerung, dass folgende Wartungen für deine Fahrzeuge bald fällig sind:

+------------------+----------+-------------+----------------+
| Fahrzeug         | Art      | Fällig am   | Fällig bei (km)|
+------------------+----------+-------------+----------------+
| BMW 320d (B-MW123)| TÜV      | 2024-12-01  | -              |
| Audi A4 (M-AU456) | Service  | -           | 85.000 km      |
|                  |          |             | (Aktuell: 84.200)|
+------------------+----------+-------------+----------------+

Bitte plane die Wartung rechtzeitig ein.

[Zu meinen Fahrzeugen] (Button/Link)

Diese E-Mail wurde automatisch vom Digitalen Serviceheft versendet.
```

## Technische Details

### Erinnerungs-Logik

**Datumsbasiert:**
```sql
next_due_date <= (HEUTE + reminder_days_advance)
```
Beispiel: Bei 7 Tagen Vorlauf wird am 24.11. eine Erinnerung für Fälligkeit am 01.12. gesendet.

**Kilometerbasiert:**
```sql
current_odometer_km >= (next_due_km - 1000)
```
Beispiel: Bei Fälligkeit bei 85.000 km wird ab 84.000 km erinnert.

### Duplikatschutz

- Eintrag in `service_reminders_sent` beim Versand
- Check: "Wurde für diesen Service innerhalb der letzten 7 Tage bereits erinnert?"
- Verhindert mehrfache E-Mails bei täglichen Cron-Läufen

### Performance

- Optimierte SQL-Queries mit JOINs
- Index auf `service_reminders_sent.sent_at` für schnelle Duplikat-Checks
- Lazy-Loading: Nur Benutzer mit enabled=1 werden geprüft

## Wartung

### Log-Rotation

Cron-Logs automatisch bereinigen:
```bash
# Logs älter als 30 Tage löschen (täglich 3 Uhr)
0 3 * * * find /pfad/zu/cron/reminder.log* -mtime +30 -delete
```

### Alte Reminder-Einträge löschen

```sql
-- Monatlich ausführen
DELETE FROM service_reminders_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Troubleshooting

### Problem: Keine E-Mails

1. SMTP-Konfiguration prüfen: `src/config.php`
2. Benutzer hat Erinnerungen aktiviert?
3. Service-Einträge haben `next_due_date` gesetzt?
4. E-Mail im Spam-Ordner?

### Problem: Cron läuft nicht

1. PHP-Pfad korrekt? `which php`
2. Berechtigungen: `chmod +x cron/send_service_reminders.php`
3. Log-Datei prüfen: `cat cron/reminder.log`

### Problem: Duplikate trotzdem

- Tabelle `service_reminders_sent` existiert?
- Foreign Keys korrekt gesetzt?

Detaillierte Lösungen in `cron/README.md`!

## Zusammenfassung

✅ **Vollständig implementiert:**
- Datenbank-Schema
- Backend-Logik
- Benutzeroberfläche
- Cron-Script
- Übersetzungen (DE/EN)
- Dokumentation

🎯 **Nächste Aktion:**
1. SQL-Migration ausführen
2. Cron-Job einrichten
3. Manuell testen
4. Optional: Dateien auf Server hochladen

📧 **Kontakt:**
Bei Fragen oder Problemen:
- Prüfe `cron/README.md`
- Teste mit `php cron/send_service_reminders.php`
- Prüfe Logs in `cron/reminder.log`
