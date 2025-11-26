# 🚗 Digitales Serviceheft

<div align="center">

**Eine moderne, selbstgehostete Fahrzeugverwaltungs- und Serviceheft-Anwendung**

[![PHP Version](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

Ein schlankes, FTP-freundliches Plain-PHP-Projekt zur Verwaltung von Fahrzeugen und deren Wartungshistorie mit automatischen Erinnerungen für fällige Services.

[Features](#-features) • [Installation](#-installation) • [Konfiguration](#️-konfiguration) • [Dokumentation](#-dokumentation)

</div>

---

## 📋 Inhaltsverzeichnis

- [Über das Projekt](#-über-das-projekt)
- [Features](#-features)
- [Screenshots](#-screenshots)
- [Technologie-Stack](#-technologie-stack)
- [Anforderungen](#-anforderungen)
- [Installation](#-installation)
- [Konfiguration](#️-konfiguration)
- [Projektstruktur](#-projektstruktur)
- [Features im Detail](#-features-im-detail)
- [Sicherheit](#-sicherheit)
- [Automatische Service-Erinnerungen](#-automatische-service-erinnerungen)
- [Administration](#-administration)
- [Entwicklung](#-entwicklung)
- [Troubleshooting](#-troubleshooting)
- [Lizenz](#-lizenz)

---

## 🎯 Über das Projekt

Das **Digitale Serviceheft** ist eine webbasierte Anwendung zur umfassenden Verwaltung von Fahrzeugen und deren Wartungshistorie. Benutzer können:

- 🚙 **Fahrzeuge verwalten** - Detaillierte Fahrzeugprofile mit allen relevanten Daten (VIN, HSN/TSN, Kennzeichen, etc.)
- 📝 **Service-Historie pflegen** - Alle Wartungen, Reparaturen und Inspektionen dokumentieren
- 💰 **Kosten tracken** - Automatische Kostenkalkulation pro Fahrzeug und Service
- 📧 **Erinnerungen erhalten** - Automatische E-Mail-Benachrichtigungen für fällige Wartungen
- 📊 **Übersicht behalten** - Dashboard mit aktuellen Fahrzeugdaten und anstehenden Services

Das Projekt ist bewusst als **Plain-PHP-Anwendung** konzipiert und benötigt keine Frameworks oder Build-Tools - perfekt für einfaches Deployment auf Shared Hosting via FTP.

---

## ✨ Features

### 🔐 Authentifizierung & Benutzerverwaltung
- ✅ Benutzerregistrierung und Login
- ✅ Session-basierte Authentifizierung
- ✅ CSRF-Schutz für alle Formulare
- ✅ Sicheres Password Hashing (BCRYPT)
- ✅ Account-Lockout nach fehlgeschlagenen Login-Versuchen
- ✅ Login-Audit-Log
- ✅ E-Mail-Verifikation
- ✅ Password-Reset-Funktion
- ✅ Vorbereitet für 2FA (TOTP) und WebAuthn/Passkeys

### 🚗 Fahrzeugverwaltung
- ✅ Unbegrenzt viele Fahrzeuge pro Benutzer
- ✅ Detaillierte Fahrzeugdaten (HSN/TSN, VIN, Kennzeichen, Motor, Kraftstoffart, etc.)
- ✅ Profilbilder für Fahrzeuge
- ✅ Kilometerstand-Tracking
- ✅ Kauf- und Verkaufsdokumentation mit Preisen
- ✅ Freitext-Notizen pro Fahrzeug

### 🔧 Service-Historie
- ✅ Wartungseinträge (TÜV, Service, Ölwechsel, Reparaturen, etc.)
- ✅ Mehrere Service-Items pro Eintrag
- ✅ Automatische Kostenberechnung
- ✅ Fälligkeits-Tracking (Datum und/oder Kilometerstand)
- ✅ Automatische Berechnung der nächsten fälligen Wartung

### 📧 Automatische Erinnerungen
- ✅ E-Mail-Benachrichtigungen für fällige Wartungen
- ✅ Datums- und kilometerbasierte Erinnerungen
- ✅ Konfigurierbare Vorlaufzeit (1-90 Tage)
- ✅ Duplikatschutz (keine mehrfachen E-Mails)
- ✅ Mehrsprachige E-Mail-Templates (DE/EN)
- ✅ Cron-Job-System mit mehreren Setup-Optionen

### 🎨 UI/UX
- ✅ Modernes, responsives Design
- ✅ Light/Dark Mode mit automatischem Toggle
- ✅ Mehrsprachig (Deutsch/Englisch)
- ✅ Mobile-freundlich
- ✅ Benutzerfreundliche Formulare mit Client-seitiger Validierung

### 👨‍💼 Administration
- ✅ Admin-Panel zur Benutzerverwaltung
- ✅ Erste Admin-Erstellung via Skript oder ADMIN_EMAIL
- ✅ Rollenbasierte Zugriffskontrolle

---

## 📸 Screenshots

*TODO: Screenshots hier einfügen*

---

## 🛠 Technologie-Stack

- **Backend:** PHP 8.1+ (Plain PHP, kein Framework)
- **Datenbank:** MySQL 8.x / MariaDB 10.x
- **Frontend:** Vanilla JavaScript, CSS3
- **E-Mail:** SMTP oder PHP mail()
- **Deployment:** FTP-freundlich, läuft auf Standard Shared Hosting

### Verwendete Technologien

| Technologie | Verwendung |
|------------|------------|
| PHP 8.1+ | Server-seitige Logik |
| MySQL 8.x | Datenbank |
| PDO | Datenbank-Zugriff |
| Sessions | Authentifizierung |
| SMTP/PHPMailer | E-Mail-Versand |
| Vanilla JS | Client-seitige Validierung |
| CSS3 | Styling (inkl. Custom Properties) |

---

## 📦 Anforderungen

### Server-Anforderungen

- **PHP:** 8.1 oder höher (empfohlen: 8.2/8.3/8.4)
- **PHP-Erweiterungen:**
  - `pdo_mysql` - Datenbank-Zugriff
  - `mbstring` - Multibyte-String-Verarbeitung
  - `openssl` - Verschlüsselung und Hashing
  - `json` - JSON-Verarbeitung (meist standardmäßig aktiv)
- **MySQL:** 8.0+ oder MariaDB 10.5+
- **Webserver:** Apache mit mod_rewrite (oder nginx mit entsprechender Konfiguration)
- **FTP-Zugang** oder SSH-Zugang zum Server

### Optionale Anforderungen

- **Cron-Jobs** für automatische Service-Erinnerungen (alternative Methoden verfügbar)
- **SMTP-Server** für E-Mail-Versand (Fallback auf PHP mail() möglich)

---

## 🚀 Installation

### Schnellstart

1. **Repository klonen oder herunterladen**
   ```bash
   git clone https://github.com/DEIN-USERNAME/digitales-serviceheft.git
   cd digitales-serviceheft
   ```

2. **Dateien auf den Server hochladen**
   - Via FTP: Alle Dateien in das Webroot-Verzeichnis hochladen
   - Via SSH: Repository direkt auf dem Server klonen

3. **Konfigurationsdatei erstellen**
   ```bash
   cp src/config.example.php src/config.php
   ```
   
   Bearbeite `src/config.php` und trage deine Daten ein:
   ```php
   // Datenbank-Konfiguration
   const DB_HOST = 'localhost';
   const DB_PORT = 3306;
   const DB_NAME = 'deine_datenbank';
   const DB_USER = 'dein_user';
   const DB_PASS = 'dein_passwort';
   
   // SMTP-Konfiguration (optional)
   const SMTP_HOST = 'mail.example.com';
   const SMTP_PORT = 587;
   const SMTP_USER = 'no-reply@example.com';
   const SMTP_PASS = 'smtp_passwort';
   ```

4. **Datenbank importieren**
   
   **Option A: phpMyAdmin**
   - Öffne phpMyAdmin
   - Wähle deine Datenbank
   - Gehe zu "Importieren"
   - Wähle die Datei `sql/install_complete.sql`
   - Klicke auf "OK"

   **Option B: MySQL CLI**
   ```bash
   mysql -u DEIN_USER -p DEINE_DB < sql/install_complete.sql
   ```

5. **Ersten Admin-Benutzer anlegen**

   **Methode 1: Via ADMIN_EMAIL (empfohlen)**
   - Setze in `src/config.php`: `const ADMIN_EMAIL = 'deine@email.de';`
   - Registriere dich unter `/register.php` mit dieser E-Mail
   - Du erhältst automatisch Admin-Rechte

   **Methode 2: Via create_admin.php**
   - Rufe auf: `https://deine-domain.de/create_admin.php?confirm=1&name=Admin&username=admin&email=admin@example.com&pw=SicheresPasswort123!`
   - **Wichtig:** Lösche danach `create_admin.php` manuell!

6. **Fertig! 🎉**
   - Öffne `https://deine-domain.de/login.php`
   - Melde dich mit deinen Admin-Zugangsdaten an

---

## ⚙️ Konfiguration

### Grundeinstellungen (`src/config.php`)

```php
// App-Konfiguration
const APP_NAME = 'Digitales-Serviceheft';
const APP_LOCALE = 'de';  // 'de' oder 'en'
const APP_DOMAIN = 'deine-domain.de';
const ADMIN_EMAIL = 'admin@example.com';

// Datenbank
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'serviceheft_db';
const DB_USER = 'db_user';
const DB_PASS = 'db_password';

// Registrierung aktivieren/deaktivieren
const ALLOW_REGISTRATION = true;

// SMTP (optional, sonst PHP mail())
const SMTP_HOST = 'mail.example.com';
const SMTP_PORT = 587;
const SMTP_USER = 'no-reply@example.com';
const SMTP_PASS = 'smtp_password';
const SMTP_ENCRYPTION = 'tls';  // 'tls', 'ssl', oder 'none'
const SMTP_FROM_EMAIL = 'no-reply@example.com';

// Standard-Intervalle für Wartungen
const DEFAULT_OIL_INTERVAL_KM = 15000;      // 15.000 km
const DEFAULT_OIL_INTERVAL_YEARS = 1;       // 1 Jahr
const DEFAULT_SERVICE_INTERVAL_KM = 30000;  // 30.000 km
const DEFAULT_SERVICE_INTERVAL_YEARS = 2;   // 2 Jahre

// Login-Sicherheit
const LOGIN_MAX_FAILED_ATTEMPTS = 5;   // Max. Fehlversuche
const LOGIN_LOCKOUT_MINUTES = 10;      // Sperrdauer in Minuten
const LOGIN_RESET_ON_LOCK = false;     // Counter bei Sperre zurücksetzen?
```

### .htaccess Konfiguration

Die `.htaccess`-Datei ist bereits vorkonfiguriert und ermöglicht:
- ✅ Clean URLs (ohne `.php`-Endung)
- ✅ Zugriff auf `src/` blockiert
- ✅ Automatische Weiterleitung zu `public/`
- ✅ Browser-Caching für statische Assets

---

## 📁 Projektstruktur

```
digitales-serviceheft/
├── 📁 public/              # Öffentlich zugängliche Dateien
│   ├── 📁 account/         # Account-Verwaltung
│   ├── 📁 admin/           # Admin-Panel
│   ├── 📁 services/        # Service-Verwaltung
│   ├── 📁 vehicles/        # Fahrzeugverwaltung
│   ├── dashboard.php       # Dashboard
│   ├── login.php           # Login-Seite
│   ├── register.php        # Registrierung
│   └── ...
├── 📁 src/                 # Backend-Logik (nicht öffentlich)
│   ├── 📁 vehicles/        # Fahrzeug-Handler
│   ├── auth.php            # Authentifizierungs-Funktionen
│   ├── bootstrap.php       # App-Initialisierung
│   ├── config.example.php  # Konfigurations-Vorlage
│   ├── config.php          # Konfiguration (nicht in Git)
│   ├── csrf.php            # CSRF-Schutz
│   ├── db.php              # Datenbankverbindung
│   ├── helpers.php         # Hilfsfunktionen
│   ├── i18n.php            # Internationalisierung
│   ├── mailer.php          # E-Mail-Versand
│   └── security.php        # Sicherheitsfunktionen
├── 📁 assets/              # Statische Assets
│   ├── 📁 css/             # Stylesheets
│   │   ├── app.css         # Haupt-Styles
│   │   └── theme.css       # Theme-Variablen
│   ├── 📁 js/              # JavaScript
│   │   ├── theme.js        # Theme-Toggle
│   │   └── *-validator.js  # Form-Validierung
│   └── 📁 files/           # Upload-Ordner
├── 📁 lang/                # Übersetzungen
│   ├── de.php              # Deutsch
│   └── en.php              # English
├── 📁 sql/                 # Datenbank-Migrationen
│   ├── install_complete.sql # Vollständiges Schema
│   └── README.md           # SQL-Dokumentation
├── 📁 cron/                # Cron-Job-Skripte
│   ├── send_service_reminders.php  # Erinnerungs-Script
│   ├── trigger.php         # Web-basierter Trigger
│   └── README.md           # Cron-Setup-Anleitung
├── .htaccess               # Apache-Konfiguration
├── .gitignore              # Git-Ignore-Regeln
├── README.md               # Diese Datei
└── REMINDER_SYSTEM_SUMMARY.md  # Erinnerungs-System-Doku
```

### Wichtige Dateien

| Datei | Beschreibung |
|-------|-------------|
| `src/config.php` | **Hauptkonfiguration** (nicht in Git, muss erstellt werden) |
| `src/bootstrap.php` | Initialisiert die App (Session, DB, i18n) |
| `src/auth.php` | Authentifizierungs-Logik |
| `src/db.php` | Datenbankverbindung via PDO |
| `.htaccess` | URL-Rewriting und Sicherheit |

---

## 🎯 Features im Detail

### Fahrzeugverwaltung

**Erstellen eines neuen Fahrzeugs:**
- Navigiere zu Dashboard → "Neues Fahrzeug"
- Pflichtfelder: Hersteller, Modell, Kraftstoffart
- Optional: VIN, HSN/TSN, Kennzeichen, Kilometerstand, Kaufdaten, etc.
- Profilbild hochladen (JPG, PNG, max. 5MB)

**Fahrzeugdetails:**
- Vollständige Fahrzeugdaten
- Aktuelle Service-Historie
- Anstehende Wartungen
- Gesamtausgaben
- Bearbeiten/Löschen

### Service-Verwaltung

**Service-Eintrag erstellen:**
- Wähle Fahrzeug aus
- Service-Typ: TÜV, Wartung, Ölwechsel, Reparatur, Sonstiges
- Datum und Kilometerstand
- Service-Items mit Einzelpreisen
- Nächste Fälligkeit (Datum und/oder km)

**Automatische Berechnungen:**
- Gesamtkosten pro Service
- Gesamtausgaben pro Fahrzeug
- Nächste fällige Wartung

### Service-Erinnerungen

Das System kann automatisch E-Mails versenden, wenn Wartungen fällig werden.

**Wie es funktioniert:**
1. **Benutzer aktiviert Erinnerungen** in Account-Einstellungen
2. **Legt Vorlaufzeit fest** (z.B. 7 Tage im Voraus)
3. **Cron-Job prüft täglich** alle Fahrzeuge
4. **E-Mail wird versendet** bei fälligen Wartungen

**Erinnerungs-Logik:**
- **Datumsbasiert:** `next_due_date ≤ (heute + Vorlaufzeit)`
- **Kilometerbasiert:** `aktueller_km ≥ (next_due_km - 1.000 km)`
- **Duplikatschutz:** Max. 1 Erinnerung pro Woche pro Service

→ Detaillierte Anleitung: [cron/README.md](cron/README.md)

---

## 🔒 Sicherheit

### Implementierte Sicherheitsmaßnahmen

✅ **Passwort-Sicherheit**
- BCRYPT-Hashing mit `password_hash()`
- Minimum-Anforderungen: 8 Zeichen
- Passwort-Bestätigung bei Registrierung

✅ **Session-Sicherheit**
- Session-Regeneration bei Login
- Secure Session-Cookies
- Session-Timeout

✅ **CSRF-Schutz**
- CSRF-Token in allen Formularen
- Token-Validierung server-seitig

✅ **SQL-Injection-Schutz**
- Ausschließlich Prepared Statements (PDO)
- Parameter-Binding für alle Queries

✅ **XSS-Schutz**
- Output-Escaping via `htmlspecialchars()`
- Content-Security-Policy-Header

✅ **Login-Schutz**
- Account-Lockout nach 5 fehlgeschlagenen Versuchen
- Sperre für 10 Minuten
- Login-Audit-Log

✅ **Zugriffskontrolle**
- Session-basierte Authentifizierung
- Rollenbasierte Zugriffskontrolle (Admin/User)
- Zugriff auf `src/` blockiert via `.htaccess`

### Sensible Dateien

**Niemals in Git committen:**
- `src/config.php` - Enthält DB- und SMTP-Zugangsdaten
- `*.log` - Log-Dateien

→ Siehe `.gitignore` für vollständige Liste

---

## 📧 Automatische Service-Erinnerungen

### Setup

**1. Datenbank ist bereits vorbereitet** (wenn `install_complete.sql` importiert)

**2. Cron-Job einrichten**

**Option A: System Cron (empfohlen für VPS/dedizierte Server)**
```bash
crontab -e
```
Füge hinzu:
```bash
0 8 * * * /usr/bin/php /pfad/zum/projekt/cron/send_service_reminders.php >> /pfad/zum/projekt/cron/reminder.log 2>&1
```

**Option B: Web-basierter Trigger (für Shared Hosting)**
1. Secret Key in `src/config.php` setzen:
   ```php
   const CRON_SECRET_KEY = 'dein-zufälliger-geheimer-key';
   ```
2. Externen Cron-Service nutzen (z.B. cron-job.org):
   - URL: `https://deine-domain.de/cron/trigger.php?key=DEIN_SECRET_KEY`
   - Zeitplan: Täglich 8:00 Uhr

**3. Testen**
```bash
php cron/send_service_reminders.php
```

**Erwartete Ausgabe:**
```
[2024-11-26 08:00:00] Starting service reminder check...
Found 3 users with reminders enabled.
✓ Sent reminder to user@example.com for 2 service(s)
[2024-11-26 08:00:03] Reminder check completed.
Summary: 1 emails sent, 0 skipped
```

→ Vollständige Anleitung: [cron/README.md](cron/README.md)

---

## 👨‍💼 Administration

### Admin-Panel

Zugriff nur für Benutzer mit Rolle `admin`:
- **URL:** `/admin.php`
- **Features:**
  - Benutzerliste anzeigen
  - Benutzer bearbeiten/löschen
  - Rollen ändern
  - Account-Sperren aufheben

### Benutzer entsperren (SQL)

Falls ein Benutzer ausgesperrt ist:

```sql
-- Entsperren per E-Mail
UPDATE users
SET failed_logins = 0,
    locked_until = NULL,
    updated_at = NOW()
WHERE email = 'user@example.com';

-- Status prüfen
SELECT id, email, failed_logins, locked_until, last_login_at
FROM users
WHERE email = 'user@example.com';
```

### Login-Audit-Log einsehen

```sql
-- Letzte 50 Login-Versuche eines Benutzers
SELECT * FROM login_audit 
WHERE user_id = 123 
ORDER BY created_at DESC 
LIMIT 50;

-- Alte Einträge löschen (älter als 90 Tage)
DELETE FROM login_audit 
WHERE created_at < (NOW() - INTERVAL 90 DAY);
```

---

## 🛠 Entwicklung

### Lokale Entwicklungsumgebung

**1. XAMPP/MAMP/Laragon installieren**
- PHP 8.1+
- MySQL 8.0+
- Apache mit mod_rewrite

**2. Projekt klonen**
```bash
git clone https://github.com/DEIN-USERNAME/digitales-serviceheft.git
cd digitales-serviceheft
```

**3. Konfiguration erstellen**
```bash
cp src/config.example.php src/config.php
```

**4. Datenbank importieren**
```bash
mysql -u root -p < sql/install_complete.sql
```

**5. Lokale Konfiguration**
```php
const DB_HOST = 'localhost';
const DB_NAME = 'serviceheft_dev';
const DB_USER = 'root';
const DB_PASS = '';
```

**6. Development Server starten**
```bash
php -S localhost:8000 -t .
```

Öffne: `http://localhost:8000/login.php`

### Code-Style

- **PHP:** PSR-12 Standard
- **Indentation:** 4 Spaces
- **Encoding:** UTF-8
- **Line Endings:** LF (Unix)

### Beitragen

Pull Requests sind willkommen! Für größere Änderungen bitte zuerst ein Issue erstellen.

---

## 🔧 Troubleshooting

### Problem: Weiße Seite / 500 Error

**Lösung:**
1. PHP Error Reporting aktivieren (in `src/bootstrap.php`):
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', '1');
   ```
2. PHP-Logs prüfen (meist in `/var/log/apache2/error.log`)
3. Rechte prüfen: `chmod 755` für Ordner, `chmod 644` für Dateien

### Problem: "Database connection failed"

**Lösung:**
1. `src/config.php` prüfen:
   - DB_HOST korrekt? (meist `localhost`)
   - DB_NAME existiert?
   - DB_USER/DB_PASS korrekt?
2. MySQL läuft?
   ```bash
   sudo systemctl status mysql
   ```
3. Benutzer hat Rechte?
   ```sql
   GRANT ALL ON serviceheft_db.* TO 'db_user'@'localhost';
   ```

### Problem: Keine E-Mails

**Lösung:**
1. SMTP-Konfiguration prüfen (`src/config.php`)
2. Firewall blockiert Port 587/465?
3. SMTP-Logs prüfen
4. Fallback auf PHP mail() testen:
   ```php
   const SMTP_HOST = ''; // Leer lassen für mail()
   ```

### Problem: Clean URLs funktionieren nicht

**Lösung:**
1. `mod_rewrite` aktiviert?
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
2. `.htaccess` wird gelesen?
   - Apache-Config: `AllowOverride All` setzen

### Problem: Cron-Job läuft nicht

**Lösung:**
1. PHP-Pfad korrekt?
   ```bash
   which php  # meist /usr/bin/php
   ```
2. Datei ausführbar?
   ```bash
   chmod +x cron/send_service_reminders.php
   ```
3. Logs prüfen:
   ```bash
   tail -f cron/reminder.log
   ```

→ Weitere Lösungen in [cron/README.md](cron/README.md)

---

## 📚 Dokumentation

- **[REMINDER_SYSTEM_SUMMARY.md](REMINDER_SYSTEM_SUMMARY.md)** - Ausführliche Dokumentation des Erinnerungs-Systems
- **[cron/README.md](cron/README.md)** - Cron-Job-Setup und Troubleshooting
- **[sql/README.md](sql/README.md)** - Datenbank-Migrationen und Schema-Änderungen

---

## 🤝 Support

Bei Fragen oder Problemen:
1. 📖 Lies die [Troubleshooting](#-troubleshooting)-Sektion
2. 🔍 Durchsuche die [Issues](https://github.com/DEIN-USERNAME/digitales-serviceheft/issues)
3. 💬 Erstelle ein neues [Issue](https://github.com/DEIN-USERNAME/digitales-serviceheft/issues/new)

---

## 📝 Changelog

### Version 2.0 (2024-11-26)
- ✅ Automatische Service-Erinnerungen
- ✅ E-Mail-Benachrichtigungen
- ✅ Cron-Job-System
- ✅ Mehrsprachigkeit (DE/EN)
- ✅ Duplikatschutz für Erinnerungen

### Version 1.0 (Initial Release)
- ✅ Fahrzeugverwaltung
- ✅ Service-Historie
- ✅ Benutzer-Authentifizierung
- ✅ Admin-Panel
- ✅ Light/Dark Theme

---

## 📄 Lizenz

Dieses Projekt ist Open Source und steht unter der [MIT License](LICENSE).

```
MIT License

Copyright (c) 2024 [Dein Name]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 🌟 Credits

Entwickelt mit ❤️ und ☕ von [Dein Name]

**Built with:**
- PHP
- MySQL
- Vanilla JavaScript
- CSS3

---

<div align="center">

**[⬆ Zurück nach oben](#-digitales-serviceheft)**

Made with ❤️ for the car enthusiast community

</div>
