# Digitales Serviceheft (Plain PHP)

Ein schlankes, FTP-freundliches Plain-PHP-Projekt mit Login/Registrierung, Session-Auth, CSRF-Schutz, einfacher i18n (de/en), Light/Dark Theme, Profilverwaltung (E-Mail/Benutzername ändern) und vorbereitetem DB-Schema (inkl. 2FA/Passkey-Tabellen für später).

## Anforderungen
- PHP 8.1+ (empfohlen 8.2/8.3/8.4)
  - Aktivierte Erweiterungen: `pdo_mysql`, `mbstring`, `openssl`
- MySQL 8.x (oder kompatibel)
- FTP-Zugang oder Webhosting, das PHP ausführt

## Projektstruktur
- `public/` – Einstiegspunkte (login.php, register.php, dashboard.php, account.php)
- `src/` – App-Logik (bootstrap, config, db, auth, csrf, i18n, helpers)
- `assets/` – CSS/JS (Palette, Basis-Styles, Dark/Light Toggle)
- `lang/` – Übersetzungen (`de.php`, `en.php`)
- `sql/` – Schema (`schema.sql`)

Hinweis: Manche Hoster können nicht auf `public/` als Webroot zeigen. Dafür liegen im Projekt-Root Wrapper-Dateien (z. B. `login.php`), die die `public/*`-Dateien laden.

## Installation (lokal oder direkt auf dem Hoster)
1. Projekt-Dateien auf den Server kopieren (per FTP) oder lokal klonen und anschließend hochladen.
2. `src/config.php` erstellen (nicht committen):
   - Vorlage: `src/config.example.php` → als `src/config.php` kopieren und Werte eintragen.
   - Konfigurationsvariablen:
     - `APP_NAME`, `APP_LOCALE` (z. B. `de`), `ADMIN_EMAIL`
     - `DB_HOST`, `DB_PORT` (meist `3306`), `DB_NAME`, `DB_USER`, `DB_PASS`
3. Datenbanktabellen anlegen:
   - Variante A: `sql/schema.sql` in phpMyAdmin importieren.
   - Variante B: Manuell per SQL (siehe Auszug unten) ausführen.

### Auszug aus dem DB-Schema (users)
```sql
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  username VARCHAR(32) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  email_verified_at TIMESTAMP NULL,
  avatar_url VARCHAR(512) NULL,
  locale VARCHAR(10) NULL,
  timezone VARCHAR(64) NULL,
  last_login_at TIMESTAMP NULL,
  last_login_ip VARCHAR(45) NULL,
  failed_logins INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Weitere Tabellen: `password_reset_tokens`, `email_verifications`, `user_2fa`, `webauthn_credentials`, `login_audit` (siehe vollständige `sql/schema.sql`).

## Erste Schritte
- Rufe im Browser `login.php` oder `register.php` auf.
- Registrierung verlangt: Name, Benutzername, E-Mail, Passwort.
- Login akzeptiert: Benutzername ODER E-Mail + Passwort.

## Admin-Benutzer anlegen
Es gibt zwei Wege, den ersten Admin anzulegen:

1) Über Registrierung (empfohlen, wenn `ADMIN_EMAIL` passt):
- Setze `ADMIN_EMAIL` in `src/config.php` auf die gewünschte Admin-E-Mail.
- Registriere dich mit dieser E-Mail unter `register.php`. Die Rolle wird automatisch `admin`.

2) Einmaliges Skript `create_admin.php` (löscht sich nach Erfolg selbst):
- Aufruf mit `?confirm=1` und optionalen Parametern:
```
https://<deine-domain>/create_admin.php?confirm=1&name=Alex&username=alex
  &email=alex@example.com&pw=SehrSicher123!&role=admin
  &locale=de&timezone=Europe/Berlin&avatar_url=https://example.com/a.png
```
- Falls Selbstlöschung nicht möglich ist (Hoster-Beschränkung), bitte die Datei per FTP löschen.

## Sicherheitshinweise
- `src/config.php` ist in `.gitignore` – niemals echte Zugangsdaten committen.
- `create_admin.php` nur einmal benutzen und danach löschen (oder nutzt Selbstlöschung).
- CSRF-Schutz: Alle Formulare übermitteln einen CSRF-Token.
- Passwörter werden mit `password_hash` (BCRYPT) gespeichert.

## Anpassungen / Features
- Sprache: `lang/de.php` und `lang/en.php`, Standard über `APP_LOCALE` in `src/config.php`.
- Theme: `assets/css/theme.css` (Light/Dark) und `assets/js/theme.js` (Toggle).
- Konto: `account.php` für Benutzername/E-Mail ändern und Passwortwechsel.
- Zukünftig: 2FA (TOTP) und Passkeys (WebAuthn) sind im Schema vorbereitet und können später implementiert werden.

## Lizenz
Dieses Projekt ist ein Beispiel/Starter. Lizenz nach Wunsch ergänzen.
