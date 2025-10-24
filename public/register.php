<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (defined('ALLOW_REGISTRATION') && ALLOW_REGISTRATION === false) {
    header('Location: /login/');
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
                // Explizit 'user' als Rolle setzen für neue Registrierungen
                register_user($name, $username, $email, $password, 'user');
                authenticate($username ?: $email, $password);
                header('Location: /');
                exit;
            } catch (Throwable $e) {
                // Zeige benutzerfreundliche Fehlermeldung
                $errors[] = $e->getMessage();
            }
        }
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_register')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <?php render_i18n_for_js(['pwd_req_title', 'pwd_req_length', 'pwd_req_uppercase', 'pwd_req_lowercase', 'pwd_req_number', 'pwd_match', 'pwd_no_match']); ?>
  <script defer src="/assets/js/password-validator.js"></script>
</head>
<body class="page">
  <?php 
    render_brand_header(['cta' => ['label' => t('to_login'), 'href' => '/login/']]);
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
        <h2 class="card-title"><?= e(t('register_title')) ?></h2>
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

      
    </div>
  </main>
</body>
</html>
