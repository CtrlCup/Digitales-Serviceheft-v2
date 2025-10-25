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
  <?php render_i18n_for_js([
    'pwd_req_title', 'pwd_req_length', 'pwd_req_uppercase', 'pwd_req_lowercase', 'pwd_req_number', 'pwd_match', 'pwd_no_match',
    'availability_error_register', 'username_available', 'username_taken', 'email_available', 'email_taken', 'email_format_invalid', 'checking_availability'
  ]); ?>
  <script defer src="/assets/js/password-validator.js"></script>
  <script defer src="/assets/js/register-validator.js"></script>
</head>
<body class="page">
  <?php 
    render_brand_header(['cta' => ['label' => t('to_login'), 'href' => '/login/']]);
  ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <?php if ($errors): ?>
        <div class="error-notification" id="error-toast">
          <div class="error-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </div>
          <div class="error-content">
            <?php foreach ($errors as $err): ?>
              <div class="error-message"><?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="error-close" onclick="this.parentElement.style.display='none'" aria-label="Schließen">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <script>
          setTimeout(() => {
            const toast = document.getElementById('error-toast');
            if (toast) toast.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <form method="post" class="card" novalidate>
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
          <input type="text" name="email" required autocomplete="email" inputmode="email">
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
