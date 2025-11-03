<?php
declare(strict_types=1);

function register_user(string $name, string $username, string $email, string $password, ?string $desiredRole = null): void {
    $pdo = db();
    // simple validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('error_invalid_email'));
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
        throw new InvalidArgumentException(t('error_invalid_username'));
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException(t('password_too_short'));
    }
    // Passwort muss Großbuchstabe, Kleinbuchstabe und Zahl enthalten
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        throw new InvalidArgumentException(t('password_weak'));
    }
    // unique checks
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new RuntimeException(t('email_already_exists'));
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new RuntimeException(t('username_already_exists'));
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Rollen-Logik: Standard ist immer 'user'
    $role = 'user';
    if ($desiredRole !== null) {
        $role = strtolower(trim($desiredRole));
        // Nur 'admin' oder 'user' sind erlaubt
        if (!in_array($role, ['admin','user'], true)) {
            $role = 'user';
        }
    } elseif (defined('ADMIN_EMAIL') && strcasecmp($email, ADMIN_EMAIL) === 0) {
        // Nur wenn ADMIN_EMAIL definiert ist und übereinstimmt
        $role = 'admin';
    }
    
    $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    if (!$stmt->execute([$name ?: $username, $username, $email, $hash, $role])) {
        $errorInfo = $stmt->errorInfo();
        throw new RuntimeException(t('error_database') . ': ' . ($errorInfo[2] ?? 'Unknown error'));
    }
}

function authenticate_with_lockout(string $identifier, string $password): array {
    // Returns [bool success, ?string errorMessage]
    $pdo = db();
    $maxAttempts = defined('LOGIN_MAX_FAILED_ATTEMPTS') ? (int)LOGIN_MAX_FAILED_ATTEMPTS : 5;
    $lockMinutes = defined('LOGIN_LOCKOUT_MINUTES') ? (int)LOGIN_LOCKOUT_MINUTES : 10;

    $stmt = $pdo->prepare('SELECT id, password, failed_logins, locked_until FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);

    $now = new DateTimeImmutable('now');

    // Helper to log audit rows (works even for unknown user)
    $logAudit = function (?int $userId, bool $success) use ($pdo, $ip, $agent): void {
        try {
            $s = $pdo->prepare('INSERT INTO login_audit (user_id, ip, user_agent, success, created_at) VALUES (?, ?, ?, ?, NOW())');
            $s->execute([$userId, $ip, $agent, $success ? 1 : 0]);
        } catch (Throwable $e) {
            // swallow to not block login on audit errors
        }
    };

    if (!$user) {
        // Unknown user: generic failure, still audit without user_id
        $logAudit(null, false);
        return [false, t('login_failed')];
    }

    $userId = (int)$user['id'];
    // Check lockout first
    if (!empty($user['locked_until'])) {
        try {
            $lockedUntil = new DateTimeImmutable($user['locked_until']);
            if ($lockedUntil > $now) {
                $remaining = $lockedUntil->getTimestamp() - $now->getTimestamp();
                [$d,$h,$m,$s] = format_duration_dhms($remaining);
                $logAudit($userId, false);
                return [false, sprintf(t('account_locked_remaining'), $d, $h, $m, $s)];
            }
        } catch (Throwable $e) {
            // if parse fails, ignore lock and continue
        }
    }

    // Verify password
    $ok = password_verify($password, (string)$user['password']);
    if ($ok) {
        // success: reset counters, set last login info, set session
        $_SESSION['user_id'] = $userId;
        $device = $agent !== '' ? $agent : null;
        
        // Note: For users with 2FA, these fields will be updated again after 2FA verification
        // This ensures last_login_* reflects the most recent completed authentication
        $has2fa = user_has_2fa($userId) ? 1 : 0;
        $upd = $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?, last_login_device = ?, last_login_method = ?, last_login_2fa_enabled = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$ip, $device, 'password', $has2fa, $userId]);
        $logAudit($userId, true);
        return [true, null];
    }

    // failure: increment failed_logins and maybe lock
    $failed = (int)($user['failed_logins'] ?? 0);
    $failed++;
    $lockedUntilSql = null;
    $error = t('login_failed');
    if ($failed >= max(1, $maxAttempts)) {
        $lockSeconds = max(60, $lockMinutes * 60);
        $until = $now->modify('+' . $lockSeconds . ' seconds');
        $lockedUntilSql = $until->format('Y-m-d H:i:s');
        [$d2,$h2,$m2,$s2] = format_duration_dhms($lockSeconds);
        $error = sprintf(t('account_locked_remaining'), $d2, $h2, $m2, $s2);
    }
    if ($lockedUntilSql) {
        if (defined('LOGIN_RESET_ON_LOCK') && LOGIN_RESET_ON_LOCK) {
            $upd = $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([$lockedUntilSql, $userId]);
        } else {
            $upd = $pdo->prepare('UPDATE users SET failed_logins = ?, locked_until = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([$failed, $lockedUntilSql, $userId]);
        }
    } else {
        $upd = $pdo->prepare('UPDATE users SET failed_logins = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$failed, $userId]);
    }
    $logAudit($userId, false);
    return [false, $error];
}

function format_duration_compact(int $seconds): string {
    if ($seconds < 0) $seconds = 0;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    if ($m > 0 && $s > 0) {
        return $m . 'm ' . $s . 's';
    } elseif ($m > 0) {
        return $m . 'm';
    }
    return $s . 's';
}

function format_duration_dhms(int $seconds): array {
    if ($seconds < 0) $seconds = 0;
    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return [$d, $h, $m, $s];
}

function authenticate(string $identifier, string $password): bool {
    $pdo = db();
    // allow login by email OR username
    $stmt = $pdo->prepare('SELECT id, name, username, email, password FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }
    return false;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, username, email, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function update_profile(int $userId, string $name, string $username, string $email): void {
    $pdo = db();
    // Validierung
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('invalid_email'));
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
        throw new InvalidArgumentException(t('invalid_username'));
    }
    if (strlen(trim($name)) < 2) {
        throw new InvalidArgumentException(t('invalid_name'));
    }
    
    // Eindeutigkeit prüfen (außer für aktuellen Benutzer)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new RuntimeException(t('email_already_exists'));
    }
    
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        throw new RuntimeException(t('username_already_exists'));
    }
    
    $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([trim($name), $username, $email, $userId]);
}

function update_password(int $userId, string $currentPassword, string $newPassword, string $newPasswordConfirm): void {
    if ($newPassword !== $newPasswordConfirm) {
        throw new InvalidArgumentException(t('password_mismatch'));
    }
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException(t('password_too_short'));
    }
    // Passwort muss Großbuchstabe, Kleinbuchstabe und Zahl enthalten
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        throw new InvalidArgumentException(t('password_weak'));
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, $row['password'])) {
        throw new RuntimeException(t('error_current_password_invalid'));
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$hash, $userId]);
}

function delete_user_account(int $userId): void {
    $pdo = db();
    
    // Delete user and all related data (cascading deletes are configured in schema)
    // Related tables: email_verifications, user_2fa, webauthn_credentials, login_audit
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);
}

// ========================================
// Role Management Functions
// ========================================

/**
 * Rolle-Hierarchie: viewer < user < admin < owner
 * Rückgabewert: niedrigere Zahl = niedrigere Berechtigung
 */
function get_role_level(string $role): int {
    $levels = [
        'viewer' => 1,
        'user' => 2,
        'admin' => 3,
        'owner' => 4,
    ];
    return $levels[strtolower($role)] ?? 0;
}

/**
 * Prüft, ob ein Benutzer eine bestimmte Rolle hat
 */
function user_has_role(int $userId, string $role): bool {
    $user = get_user_by_id($userId);
    if (!$user) return false;
    return strtolower($user['role']) === strtolower($role);
}

/**
 * Prüft, ob ein Benutzer mindestens eine bestimmte Rolle hat
 */
function user_has_min_role(int $userId, string $minRole): bool {
    $user = get_user_by_id($userId);
    if (!$user) return false;
    return get_role_level($user['role']) >= get_role_level($minRole);
}

/**
 * Prüft, ob der aktuelle Benutzer Admin oder Owner ist
 */
function is_admin(): bool {
    $user = current_user();
    if (!$user) return false;
    return in_array(strtolower($user['role']), ['admin', 'owner'], true);
}

/**
 * Prüft, ob der aktuelle Benutzer Owner ist
 */
function is_owner(): bool {
    $user = current_user();
    if (!$user) return false;
    return strtolower($user['role']) === 'owner';
}

/**
 * Erzwingt Admin-Berechtigung
 */
function require_admin(): void {
    if (!is_admin()) {
        http_response_code(403);
        die('Zugriff verweigert. Admin-Berechtigung erforderlich.');
    }
}

/**
 * Erzwingt Owner-Berechtigung
 */
function require_owner(): void {
    if (!is_owner()) {
        http_response_code(403);
        die('Zugriff verweigert. Owner-Berechtigung erforderlich.');
    }
}

/**
 * Holt einen Benutzer per ID
 */
function get_user_by_id(int $userId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, username, email, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/**
 * Holt alle Benutzer (nur für Admins)
 */
function get_all_users(): array {
    $pdo = db();
    $stmt = $pdo->query('SELECT id, name, username, email, role, created_at, last_login_at, locked_until FROM users ORDER BY created_at ASC');
    return $stmt->fetchAll();
}

/**
 * Ändert die Rolle eines Benutzers
 * @param int $actorId - ID des Benutzers, der die Änderung vornimmt
 * @param int $targetId - ID des Benutzers, dessen Rolle geändert wird
 * @param string $newRole - Neue Rolle (viewer, user, admin, owner)
 */
function change_user_role(int $actorId, int $targetId, string $newRole): void {
    $pdo = db();
    $actor = get_user_by_id($actorId);
    $target = get_user_by_id($targetId);
    
    if (!$actor || !$target) {
        throw new InvalidArgumentException(t('error_user_not_found'));
    }
    
    $newRole = strtolower(trim($newRole));
    $validRoles = ['viewer', 'user', 'admin', 'owner'];
    if (!in_array($newRole, $validRoles, true)) {
        throw new InvalidArgumentException(t('error_invalid_role'));
    }
    
    $actorLevel = get_role_level($actor['role']);
    $targetLevel = get_role_level($target['role']);
    $newLevel = get_role_level($newRole);
    
    // Owner kann alles
    if ($actorLevel === 4) {
        // Owner-Rolle kann nur einmal vergeben werden
        if ($newRole === 'owner') {
            // Prüfen, ob es bereits einen Owner gibt
            $stmt = $pdo->prepare('SELECT id FROM users WHERE role = ? AND id <> ? LIMIT 1');
            $stmt->execute(['owner', $targetId]);
            if ($stmt->fetch()) {
                throw new RuntimeException(t('error_owner_exists'));
            }
        }
    } else if ($actorLevel === 3) {
        // Admin kann nur User zu Admin machen, aber nicht zurück
        if ($newLevel > 3) {
            throw new RuntimeException(t('error_permission_denied'));
        }
        // Admin kann nur befördern, nicht degradieren
        if ($newLevel < $targetLevel) {
            throw new RuntimeException(t('error_cannot_demote'));
        }
        // Admin kann nur bis zu seinem eigenen Level befördern
        if ($newLevel > $actorLevel) {
            throw new RuntimeException(t('error_permission_denied'));
        }
    } else if ($actorLevel === 2) {
        // User kann nur Viewer zu User machen
        if ($targetLevel !== 1 || $newLevel !== 2) {
            throw new RuntimeException(t('error_permission_denied'));
        }
    } else {
        // Viewer kann nichts ändern
        throw new RuntimeException(t('error_permission_denied'));
    }
    
    // Rolle ändern
    $stmt = $pdo->prepare('UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$newRole, $targetId]);
}

// ========================================
// Site Settings Functions
// ========================================

/**
 * Holt eine Einstellung
 */
function get_site_setting(string $key, ?string $default = null): ?string {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

/**
 * Setzt eine Einstellung
 */
function set_site_setting(string $key, string $value): void {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()');
    $stmt->execute([$key, $value, $value]);
}

/**
 * Prüft, ob Registrierung aktiviert ist
 */
function is_registration_enabled(): bool {
    return get_site_setting('registration_enabled', '1') === '1';
}

// ========================================
// Admin User Management Functions
// ========================================

/**
 * Sperrt oder entsperrt einen Benutzer-Account
 * @param int $adminId - ID des Admins, der die Aktion durchführt
 * @param int $userId - ID des Benutzers, der gesperrt/entsperrt wird
 * @param bool $lock - true zum Sperren, false zum Entsperren
 * @param string|null $lockUntil - Optionales Datum/Zeit (Y-m-d H:i:s) bis wann gesperrt wird
 */
function admin_lock_user_account(int $adminId, int $userId, bool $lock = true, ?string $lockUntil = null): void {
    $pdo = db();
    $admin = get_user_by_id($adminId);
    $target = get_user_by_id($userId);
    
    if (!$admin || !$target) {
        throw new InvalidArgumentException(t('error_user_not_found'));
    }
    
    // Prüfe Berechtigung
    if (!user_has_min_role($adminId, 'admin')) {
        throw new RuntimeException(t('error_permission_denied'));
    }
    
    // Owner kann nicht gesperrt werden, außer von einem anderen Owner
    if (strtolower($target['role']) === 'owner' && !user_has_role($adminId, 'owner')) {
        throw new RuntimeException(t('error_cannot_lock_owner'));
    }
    
    // Sich selbst sperren ist nicht erlaubt
    if ($adminId === $userId) {
        throw new RuntimeException(t('error_cannot_lock_self'));
    }
    
    $lockedUntil = null;
    if ($lock) {
        if ($lockUntil !== null && trim($lockUntil) !== '') {
            // Validieren/Normalisieren: akzeptiere alles was strtotime versteht
            $ts = strtotime($lockUntil);
            if ($ts === false) {
                // Fallback: 1 Woche ab jetzt
                $ts = strtotime('+1 week');
            }
            $lockedUntil = date('Y-m-d H:i:s', $ts);
        } else {
            // Sperre für 100 Jahre (effektiv permanent, kann manuell entsperrt werden)
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+100 years'));
        }
    }
    
    $stmt = $pdo->prepare('UPDATE users SET locked_until = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$lockedUntil, $userId]);
}

/**
 * Ändert die Email eines Benutzers (Admin-Funktion)
 * @param int $adminId - ID des Admins
 * @param int $userId - ID des Benutzers
 * @param string $newEmail - Neue Email-Adresse
 */
function admin_update_user_email(int $adminId, int $userId, string $newEmail): void {
    $pdo = db();
    $admin = get_user_by_id($adminId);
    $target = get_user_by_id($userId);
    
    if (!$admin || !$target) {
        throw new InvalidArgumentException(t('error_user_not_found'));
    }
    
    // Prüfe Berechtigung
    if (!user_has_min_role($adminId, 'admin')) {
        throw new RuntimeException(t('error_permission_denied'));
    }
    
    // Owner kann nicht von Admin geändert werden
    if (strtolower($target['role']) === 'owner' && !user_has_role($adminId, 'owner')) {
        throw new RuntimeException(t('error_cannot_modify_owner'));
    }
    
    // Validierung
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('error_invalid_email'));
    }
    
    // Eindeutigkeit prüfen
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        throw new RuntimeException(t('email_already_exists'));
    }
    
    $stmt = $pdo->prepare('UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$newEmail, $userId]);
}

/**
 * Ändert das Passwort eines Benutzers (Admin-Funktion)
 * @param int $adminId - ID des Admins
 * @param int $userId - ID des Benutzers
 * @param string $newPassword - Neues Passwort
 */
function admin_update_user_password(int $adminId, int $userId, string $newPassword): void {
    $pdo = db();
    $admin = get_user_by_id($adminId);
    $target = get_user_by_id($userId);
    
    if (!$admin || !$target) {
        throw new InvalidArgumentException(t('error_user_not_found'));
    }
    
    // Prüfe Berechtigung
    if (!user_has_min_role($adminId, 'admin')) {
        throw new RuntimeException(t('error_permission_denied'));
    }
    
    // Owner kann nicht von Admin geändert werden
    if (strtolower($target['role']) === 'owner' && !user_has_role($adminId, 'owner')) {
        throw new RuntimeException(t('error_cannot_modify_owner'));
    }
    
    // Passwort-Validierung
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException(t('password_too_short'));
    }
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        throw new InvalidArgumentException(t('password_weak'));
    }
    
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$hash, $userId]);
}

/**
 * Löscht einen Benutzer-Account (Admin-Funktion)
 * @param int $adminId - ID des Admins
 * @param int $userId - ID des Benutzers
 */
function admin_delete_user_account(int $adminId, int $userId): void {
    $pdo = db();
    $admin = get_user_by_id($adminId);
    $target = get_user_by_id($userId);
    
    if (!$admin || !$target) {
        throw new InvalidArgumentException(t('error_user_not_found'));
    }
    
    // Prüfe Berechtigung
    if (!user_has_min_role($adminId, 'admin')) {
        throw new RuntimeException(t('error_permission_denied'));
    }
    
    // Owner kann nicht von Admin gelöscht werden
    if (strtolower($target['role']) === 'owner' && !user_has_role($adminId, 'owner')) {
        throw new RuntimeException(t('error_cannot_delete_owner'));
    }
    
    // Sich selbst löschen ist nicht erlaubt
    if ($adminId === $userId) {
        throw new RuntimeException(t('error_cannot_delete_self'));
    }
    
    // Lösche den Benutzer (cascading deletes sollten konfiguriert sein)
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$userId]);
}
