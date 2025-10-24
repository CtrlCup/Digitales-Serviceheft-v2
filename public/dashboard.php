<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();
$user = current_user();
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('dashboard_title')) ?> - <?= e(APP_NAME) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/files/favicon.svg">
  <link rel="stylesheet" href="assets/css/app.css">
  <script defer src="assets/js/theme.js"></script>
</head>
<body class="page">
  <main class="center">
    <div class="container">
      <div class="brand">
        <h1><?= e(APP_NAME) ?></h1>
        <div class="flex gap-2" style="justify-content:center;">
          <a class="btn-secondary" href="account.php"><?= e(t('account_link')) ?></a>
          <a class="btn-secondary" href="logout.php"><?= e(t('logout')) ?></a>
          <button id="theme-toggle" class="btn-secondary"><?= e(t('toggle_theme')) ?></button>
        </div>
      </div>

      <div class="card">
        <h2 style="margin:0;"><?= e(t('welcome')) ?>, <?= e($user['username'] ?? ($user['name'] ?? ($user['email'] ?? 'User'))) ?></h2>
        <p style="margin:0;">&nbsp;<?= e(t('dashboard_intro')) ?></p>
      </div>
    </div>
  </main>
</body>
</html>
