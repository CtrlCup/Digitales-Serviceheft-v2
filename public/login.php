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
        [$ok, $err] = authenticate_with_lockout($identifier, $password);
        if ($ok) {
            header('Location: /');
            exit;
        }
        $errors[] = $err ?: t('login_failed');
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_login')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  
</head>
<body class="page">
  <?php 
    $cta = (defined('ALLOW_REGISTRATION') ? ALLOW_REGISTRATION : true)
      ? ['label' => t('register_button'), 'href' => '/register/']
      : null;
    render_brand_header(['cta' => $cta]);
  ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <?php if ($errors): ?>
        <div class="alert">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card">
        <h2 class="card-title"><?= e(t('login_title')) ?></h2>
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

    </div>
  </main>
</body>
</html>
