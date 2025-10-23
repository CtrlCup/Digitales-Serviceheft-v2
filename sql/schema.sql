-- Minimal erforderliche Tabellen
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

-- Passwort-Zurücksetzen (Token-basiert)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- E-Mail-Verifizierung (separater Token-Store)
CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_email_verifications_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Zwei-Faktor-Authentifizierung (TOTP)
CREATE TABLE IF NOT EXISTS user_2fa (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  totp_secret VARCHAR(255) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  recovery_codes TEXT NULL,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_user_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WebAuthn/Passkeys (mehrere Credentials pro User)
CREATE TABLE IF NOT EXISTS webauthn_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  credential_id VARBINARY(255) NOT NULL,
  public_key TEXT NOT NULL,
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  transports VARCHAR(255) NULL,
  aaguid CHAR(36) NULL,
  attestation_fmt VARCHAR(100) NULL,
  name VARCHAR(255) NULL,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_webauthn_credential_id (credential_id),
  KEY idx_webauthn_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login-Audit (erfolgreiche/fehlgeschlagene Logins)
CREATE TABLE IF NOT EXISTS login_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  success TINYINT(1) NOT NULL,
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_login_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_login_audit_user (user_id),
  KEY idx_login_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
