<?php
declare(strict_types=1);
// TEMPORÄR – nach der Nutzung löschen!
require_once __DIR__ . '/src/bootstrap.php';

// Schutz: erfordert confirm=1 in der URL, um versehentliche Ausführung zu vermeiden.
if (($_GET['confirm'] ?? '') !== '1') {
    http_response_code(400);
    echo e(t('script_call_with_confirm'));
    exit;
}

$email = trim($_GET['email'] ?? ADMIN_EMAIL); // Standard: ADMIN_EMAIL aus config.php
$username = trim($_GET['username'] ?? (explode('@', $email)[0] ?: 'admin'));
$password = (string)($_GET['pw'] ?? 'Change-Me');
$name = trim($_GET['name'] ?? 'Admin');
$role = in_array(($_GET['role'] ?? 'admin'), ['admin','user'], true) ? $_GET['role'] : 'admin';
$locale = trim($_GET['locale'] ?? (defined('APP_LOCALE') ? APP_LOCALE : 'de'));
$timezone = trim($_GET['timezone'] ?? 'Europe/Berlin');
$avatarUrl = trim($_GET['avatar_url'] ?? '');

try {
    $pdo = db();
    // Basic validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(t('error_invalid_email'));
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
        throw new InvalidArgumentException(t('error_invalid_username'));
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException(t('password_too_short'));
    }

    // Doppelte prüfen (E-Mail / Benutzername)
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo e(t('user_exists_email_or_username'));
        exit;
    }
    // User anlegen (setzt Rolle anhand ADMIN_EMAIL, wir überschreiben ggf. darunter nochmals explizit)
    register_user($name, $username, $email, $password);

    // Nach Anlage ID holen
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException(t('user_not_found_after_create'));
    }
    $userId = (int)$user['id'];

    // Zusätzliche Felder aktualisieren
    $stmt = $pdo->prepare('UPDATE users SET role = ?, locale = ?, timezone = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$role, $locale ?: null, $timezone ?: null, $avatarUrl ?: null, $userId]);

    echo e(t('admin_script_created_user')) . ' ' . htmlspecialchars($email) . ' (Rolle: ' . htmlspecialchars($role) . ').';

    // Datei nach Erfolg selbst löschen
    $self = __FILE__;
    if (@unlink($self)) {
        echo "\n" . e(t('admin_script_self_removed'));
    } else {
        echo "\n" . e(t('admin_script_self_remove_failed')) . ' ' . basename($self);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Fehler: ' . htmlspecialchars($e->getMessage());
}
