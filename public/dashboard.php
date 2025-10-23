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
  <link rel="stylesheet" href="/assets/css/app.css">
  <script defer src="/assets/js/theme.js"></script>
</head>
<body>
<header class="container py-2 flex space-between">
  <h1><?= e(APP_NAME) ?></h1>
  <nav class="flex gap-2">
    <a class="btn-secondary" href="/logout.php"><?= e(t('logout')) ?></a>
    <button id="theme-toggle" class="btn-secondary"><?= e(t('toggle_theme')) ?></button>
  </nav>
</header>
<main class="container">
  <h2><?= e(t('welcome')) ?>, <?= e($user['name'] ?? $user['email'] ?? 'User') ?></h2>
  <p><?= e(t('dashboard_intro')) ?></p>
</main>
</body>
</html>
