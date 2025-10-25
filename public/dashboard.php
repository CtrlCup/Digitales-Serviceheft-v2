<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Redirect to login if not authenticated
if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_dashboard')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ]
  ]); ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <div class="card">
        <h2 class="card-title"><?= e(get_time_based_greeting()) ?>, <?= e($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'User'))) ?></h2>
        <p><?= e(t('dashboard_intro')) ?></p>
      </div>
    </div>
  </main>
</body>
</html>
