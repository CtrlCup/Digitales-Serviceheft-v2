# Service Reminder Cron Job Setup

## Übersicht

Das automatische Erinnerungssystem sendet Benutzern E-Mails, wenn ihre Fahrzeugwartungen (TÜV, Service, Ölwechsel) bald fällig sind.

## Voraussetzungen

1. **Datenbankmigrationen ausführen:**
   ```bash
   mysql -u username -p database_name < sql/add_service_reminders.sql
   ```

2. **E-Mail-Konfiguration:**
   - SMTP muss in `src/config.php` konfiguriert sein
   - SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS müssen gesetzt sein

3. **Benutzereinstellungen:**
   - Benutzer können Erinnerungen in Account-Einstellungen aktivieren/deaktivieren
   - Standard: Erinnerungen sind aktiviert, 7 Tage im Voraus

## Cron Job einrichten

### Option 1: System Cron (Empfohlen)

Füge folgende Zeile zur Crontab hinzu (täglich um 8:00 Uhr):

```bash
crontab -e
```

Dann hinzufügen:
```
0 8 * * * /usr/bin/php /pfad/zum/projekt/cron/send_service_reminders.php >> /pfad/zum/projekt/cron/reminder.log 2>&1
```

### Option 2: Webhosting Cron Panel

Falls dein Hoster ein Control Panel bietet:

1. Navigiere zu "Cron Jobs" oder "Scheduled Tasks"
2. Erstelle neuen Cron Job:
   - **Befehl:** `/usr/bin/php /home/username/website/cron/send_service_reminders.php`
   - **Zeitplan:** Täglich um 8:00 Uhr
   - **Zeitzone:** Europe/Berlin (oder deine Zeitzone)

### Option 3: Web-basierter Trigger (empfohlen für Webhoster ohne CLI)

**Diese Lösung funktioniert auf ALLEN Webhostern!**

Die Datei `cron/trigger.php` ist bereits vorhanden und ermöglicht das Ausführen per HTTP-Request.

#### Setup:

1. **Secret Key konfigurieren** in `src/config.php`:
   ```php
   const CRON_SECRET_KEY = 'ihr-zufälliger-geheimer-schlüssel';
   ```
   
   Generiere einen sicheren Key:
   ```bash
   # Option 1: OpenSSL
   openssl rand -hex 32
   
   # Option 2: Online
   # https://www.random.org/strings/
   ```

2. **Externen Cron-Service nutzen** (kostenlos):

   **A) cron-job.org (empfohlen):**
   - Registriere dich auf https://cron-job.org
   - Erstelle neuen Cronjob:
     - URL: `https://deine-domain.de/cron/trigger.php?key=DEIN_SECRET_KEY`
     - Schedule: Täglich 8:00 Uhr
     - Notification: Bei Fehler E-Mail

   **B) EasyCron.com:**
   - URL: `https://deine-domain.de/cron/trigger.php?key=DEIN_SECRET_KEY`
   - Cron Expression: `0 8 * * *`

   **C) Webhost Control Panel:**
   Falls dein Hoster Cronjobs unterstützt, aber kein CLI PHP:
   ```bash
   curl -s "https://deine-domain.de/cron/trigger.php?key=DEIN_SECRET_KEY"
   ```
   oder:
   ```bash
   wget -q -O- "https://deine-domain.de/cron/trigger.php?key=DEIN_SECRET_KEY"
   ```

#### Sicherheit:

- ✅ Secret Key erforderlich
- ✅ Lock-Mechanismus verhindert doppelte Ausführung
- ✅ Optional: IP-Whitelist (in `trigger.php` auskommentieren)
- ✅ Timeout nach 5 Minuten
- ✅ Alle Ausgaben werden geloggt

## Funktionsweise

### 1. Erinnerungs-Logik

Das Script prüft für jeden Benutzer mit aktivierten Erinnerungen:

- **Datumsbasiert:** Wenn `next_due_date` innerhalb der nächsten X Tage liegt
- **Kilometerbasiert:** Wenn aktueller Kilometerstand innerhalb von 1.000 km vor `next_due_km` liegt

### 2. Duplikatschutz

- Gesendete Erinnerungen werden in `service_reminders_sent` protokolliert
- Keine erneute Erinnerung für denselben Service innerhalb von 7 Tagen
- Verhindert Spam bei täglichen Cron-Läufen

### 3. E-Mail-Inhalt

Die E-Mail enthält:
- Übersicht aller fälligen Wartungen
- Fahrzeugdetails (Hersteller, Modell, Kennzeichen)
- Art der Wartung (TÜV, Service, Ölwechsel)
- Fälligkeitsdatum und/oder Kilometerstand
- Link zur Fahrzeugübersicht
- Mehrsprachig (DE/EN basierend auf User-Einstellung)

## Testing

### Manueller Test

Führe das Script manuell aus:

```bash
php cron/send_service_reminders.php
```

Ausgabe sollte sein:
```
[2024-01-15 08:00:00] Starting service reminder check...
Found 5 users with reminders enabled.
✓ Sent reminder to user@example.com for 2 service(s)
[2024-01-15 08:00:05] Reminder check completed.
Summary: 1 emails sent, 0 skipped (already sent recently)
```

### Test-Einträge erstellen

1. Lege einen Service-Eintrag an mit `next_due_date` in 7 Tagen
2. Setze in Account-Einstellungen "7 Tage im Voraus"
3. Führe Cron-Script manuell aus
4. Prüfe E-Mail-Posteingang

### Debug-Logs

Aktiviere Error-Logging:
```bash
tail -f /pfad/zum/projekt/cron/reminder.log
```

Für detaillierte PHP-Fehler, füge in `cron/send_service_reminders.php` hinzu:
```php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

## Troubleshooting

### Problem: Keine E-Mails werden versendet

1. **SMTP-Konfiguration prüfen:**
   ```bash
   php -r "require 'src/bootstrap.php'; send_email('test@example.com', 'Test', '<p>Test</p>', 'Test');"
   ```

2. **Benutzer hat Erinnerungen aktiviert?**
   ```sql
   SELECT id, email, reminder_enabled, reminder_days_advance FROM users WHERE id = X;
   ```

3. **Service-Einträge haben `next_due_date` gesetzt?**
   ```sql
   SELECT * FROM service_entries WHERE next_due_date IS NOT NULL;
   ```

### Problem: Duplikate werden trotzdem gesendet

- Prüfe, ob Tabelle `service_reminders_sent` existiert
- Lösche alte Einträge: `DELETE FROM service_reminders_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY);`

### Problem: Cron läuft nicht

1. **Crontab-Syntax prüfen:**
   ```bash
   crontab -l
   ```

2. **PHP-Pfad verifizieren:**
   ```bash
   which php
   ```

3. **Berechtigungen:**
   ```bash
   chmod +x cron/send_service_reminders.php
   ```

## Wartung

### Alte Reminder-Logs bereinigen

Erstelle einen monatlichen Cleanup-Cron:

```sql
-- Einträge älter als 90 Tage löschen
DELETE FROM service_reminders_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

Crontab:
```
0 2 1 * * mysql -u user -p'pass' dbname -e "DELETE FROM service_reminders_sent WHERE sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY);"
```

## Anpassungen

### Erinnerungsfrequenz ändern

Im Script `send_service_reminders.php` Zeile ~150:
```php
// Kilometerschwelle anpassen (aktuell 1.000 km)
AND v.odometer_km >= (se.next_due_km - 1000)
```

### Wiederholungssperre ändern

Im Script Zeile ~175:
```php
// Aktuell: 7 Tage, ändern auf z.B. 14 Tage:
AND sent_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
```

## Support

Bei Fragen oder Problemen:
1. Prüfe die Logs: `cron/reminder.log`
2. Teste manuell: `php cron/send_service_reminders.php`
3. Prüfe Datenbank-Einträge für Benutzer und Services
