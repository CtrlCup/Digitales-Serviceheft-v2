<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
$success = '';
$token = trim((string)($_GET['token'] ?? ''));

if ($token === '') {
    $errors[] = t('email_change_invalid');
} else {
    if (apply_email_change_from_token($token)) {
        $success = t('email_change_success');
    } else {
        $errors[] = t('email_change_invalid');
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('email_change_subject_prefix')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header(['cta' => ['label' => t('to_login'), 'href' => '/login/']]); ?>
  <main class="page-content">
    <div class="container reveal-enter">
      <?php if ($success): ?>
        <div class="card">
          <h2 class="card-title">✅ <?= e($success) ?></h2>
          <p><a href="/account.php" class="link"><?= e(t('account_title')) ?></a></p>
        </div>
      <?php endif; ?>

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
          <button type="button" class="error-close" onclick="this.parentElement.style.display='none'" aria-label="<?= e(t('close')) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <script>
          setTimeout(() => { const t = document.getElementById('error-toast'); if (t) t.style.display = 'none'; }, 6000);
        </script>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
