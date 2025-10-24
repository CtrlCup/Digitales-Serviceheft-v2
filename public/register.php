<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (defined('ALLOW_REGISTRATION') && ALLOW_REGISTRATION === false) {
    header('Location: /login.php');
    exit;
}

$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if ($password !== $password_confirm) {
            $errors[] = t('password_mismatch');
        }
        if (!$errors) {
            try {
                register_user($name, $username, $email, $password);
                authenticate($username ?: $email, $password);
                header('Location: /dashboard.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = t('register_failed');
            }
        }
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('register_title')) ?> - <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/files/favicon.svg">
  <link rel="stylesheet" href="assets/css/app.css">
  <script defer src="assets/js/theme.js"></script>
</head>
<body class="page">
  <main class="center">
    <div class="container">
      <div class="brand">
        <h1><?= e(APP_NAME) ?></h1>
        <button id="theme-toggle" class="btn-secondary" style="margin-top:.5rem;">
          <?= e(t('toggle_theme')) ?>
        </button>
      </div>

      <?php if ($errors): ?>
        <div class="alert" style="margin-bottom:1rem;">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card">
        <h2 style="margin:0;">&nbsp;<?= e(t('register_title')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
          <span><?= e(t('name')) ?></span>
          <input type="text" name="name" required autocomplete="name">
        </label>
        <label>
          <span><?= e(t('username')) ?></span>
          <input type="text" name="username" required autocomplete="username">
        </label>
        <label>
          <span><?= e(t('email')) ?></span>
          <input type="email" name="email" required autocomplete="email">
        </label>
        <label>
          <span><?= e(t('password')) ?></span>
          <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>
          <span><?= e(t('password_confirm')) ?></span>
          <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('register_button')) ?></button>
      </form>

      <p class="mt-2" style="text-align:center;">
        <a class="link" href="login.php"><?= e(t('to_login')) ?></a>
      </p>
    </div>
  </main>
</body>
</html>
