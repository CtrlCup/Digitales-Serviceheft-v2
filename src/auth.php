<?php
declare(strict_types=1);

function register_user(string $name, string $username, string $email, string $password, ?string $desiredRole = null): void {
    $pdo = db();
    // simple validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email');
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
        throw new InvalidArgumentException('Invalid username');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Password too short');
    }
    // unique checks
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Email already exists');
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Username already exists');
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $role = $desiredRole;
    if ($role !== null) {
        $role = strtolower(trim($role));
        if (!in_array($role, ['admin','user'], true)) {
            $role = 'user';
        }
    } else {
        $role = (defined('ADMIN_EMAIL') && strcasecmp($email, ADMIN_EMAIL) === 0) ? 'admin' : 'user';
    }
    if ($role === '' || $role === null) {
        $role = 'user';
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([$name ?: $username, $username, $email, $hash, $role]);
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
                $formatted = format_duration_compact($remaining);
                $logAudit($userId, false);
                return [false, sprintf(t('account_locked_wait'), $formatted)];
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
        $upd = $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ?, last_login_device = ?, updated_at = NOW() WHERE id = ?');
        $upd->execute([$ip, $device, $userId]);
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
        $error = sprintf(t('account_locked_wait'), format_duration_compact($lockSeconds));
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

function update_profile(int $userId, string $username, string $email): void {
    $pdo = db();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email');
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
        throw new InvalidArgumentException('Invalid username');
    }
    // uniqueness excluding self
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Email already exists');
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Username already exists');
    }
    $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$username, $email, $userId]);
}

function update_password(int $userId, string $currentPassword, string $newPassword, string $newPasswordConfirm): void {
    if ($newPassword !== $newPasswordConfirm) {
        throw new InvalidArgumentException('Passwords do not match');
    }
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('Password too short');
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, $row['password'])) {
        throw new RuntimeException('Current password invalid');
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$hash, $userId]);
}
