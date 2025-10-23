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
