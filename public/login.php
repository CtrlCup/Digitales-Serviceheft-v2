<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        if (authenticate($identifier, $password)) {
            header('Location: /dashboard.php');
            exit;
        }
        $errors[] = t('login_failed');
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('login_title')) ?> - <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
  <script defer src="assets/js/theme.js"></script>
</head>
<body class="page">
  <main class="center">
    <div class="container">
      <div class="brand">
        <h1><?= e(APP_NAME) ?></h1>
        <button id="theme-toggle" class="btn-secondary" style="margin-top:.5rem;"><?= e(t('toggle_theme')) ?></button>
      </div>

      <?php if ($errors): ?>
        <div class="alert" style="margin-bottom:1rem;">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card">
        <h2 style="margin:0;"><?= e(t('login_title')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
          <span><?= e(t('identifier')) ?></span>
          <input type="text" name="identifier" required autocomplete="username">
        </label>
        <label>
          <span><?= e(t('password')) ?></span>
          <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('login_button')) ?></button>
      </form>

      <p class="mt-2" style="text-align:center;">
        <a class="link" href="register.php"><?= e(t('to_register')) ?></a>
      </p>
    </div>
  </main>
</body>
</html>
