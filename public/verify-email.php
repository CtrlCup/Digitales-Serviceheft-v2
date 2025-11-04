<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
$tokenPrefill = strtoupper(str_replace('-', '', trim((string)($_GET['token'] ?? ''))));

// If token present via GET, try auto-verify immediately
if ($tokenPrefill !== '') {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token = ? LIMIT 1');
    $stmt->execute([$tokenPrefill]);
    $row = $stmt->fetch();
    if ($row && verify_email_token($tokenPrefill)) {
        $_SESSION['user_id'] = (int)$row['user_id'];
        unset($_SESSION['pending_verification_email']);
        header('Location: /');
        exit;
    } elseif ($tokenPrefill !== '') {
        $errors[] = t('verification_failed');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        $token = strtoupper(str_replace('-', '', trim((string)($_POST['token'] ?? ''))));
        if ($token === '') {
            $errors[] = t('verification_failed');
        } else {
            // find user id for optional auto-login after success
            $pdo = db();
            $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token = ? LIMIT 1');
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if (!$row) {
                $errors[] = t('verification_failed');
            } else if (verify_email_token($token)) {
                $_SESSION['user_id'] = (int)$row['user_id'];
                unset($_SESSION['pending_verification_email']);
                header('Location: /');
                exit;
            } else {
                $errors[] = t('verification_failed');
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('verify_email_page_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header(['cta' => ['label' => t('to_login'), 'href' => '/login/']]); ?>
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
          <button type="button" class="error-close" onclick="this.parentElement.style.display='none'" aria-label="<?= e(t('close')) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <script>
          setTimeout(() => { const t = document.getElementById('error-toast'); if (t) t.style.display = 'none'; }, 5000);
        </script>
      <?php endif; ?>

      <form method="post" class="card">
        <h2 class="card-title"><?= e(t('verify_email_page_title')) ?></h2>
        <p class="text-muted" style="margin-bottom:1rem;"><?= e(t('verify_email_intro')) ?></p>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
          <span><?= e(t('verify_email_enter_code')) ?></span>
          <input type="text" name="token" value="<?= e($tokenPrefill) ?>" required autocomplete="off">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('verify_email_submit')) ?></button>
      </form>

    </div>
  </main>
</body>
</html>
