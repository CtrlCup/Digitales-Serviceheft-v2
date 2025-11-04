<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();
require_admin();

$user = current_user();
$message = '';
$errors = [];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        try {
            if (isset($_POST['action']) && $_POST['action'] === 'toggle_registration') {
                $currentValue = get_site_setting('registration_enabled', '1');
                $newValue = $currentValue === '1' ? '0' : '1';
                set_site_setting('registration_enabled', $newValue);
                $message = $newValue === '1' ? t('registration_enabled_success') : t('registration_disabled_success');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'send_test_email') {
                $to = trim($_POST['to'] ?? '');
                $subject = trim($_POST['subject'] ?? (t('test_email_subject_prefix') . ' ' . APP_NAME));
                $body = trim($_POST['body'] ?? t('test_email_body_line2') . ' ' . APP_NAME);
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException(t('error_invalid_email'));
                }
                $ok = send_email($to, '', $subject, nl2br($body), $body);
                if ($ok) {
                    $message = t('test_email_sent_success') . ' ' . e($to);
                } else {
                    throw new RuntimeException(t('test_email_send_failed'));
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$registrationEnabled = get_site_setting('registration_enabled', '1') === '1';
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('admin_settings_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('dashboard_title'), 'href' => '/', 'icon' => 'home'],
      ['label' => t('admin_settings_title'), 'href' => '/admin/', 'icon' => 'settings'],
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ]
  ]); ?>
  <main class="page-content">
    <div class="container-wide reveal-enter">

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
          setTimeout(() => {
            const toast = document.getElementById('error-toast');
            if (toast) toast.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="alert success-message" style="margin-bottom:2rem;">
          <?= e($message) ?>
        </div>
      <?php endif; ?>

      <h1 style="font-size:2rem;margin-bottom:2rem;"><?= e(t('admin_settings_title')) ?></h1>

      <div class="account-grid">
        <!-- Registrierungs-Einstellungen -->
        <div class="card">
          <h2 class="card-title" style="margin-bottom:1rem;"><?= e(t('registration_settings_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:2rem;line-height:1.6;"><?= e(t('registration_settings_description')) ?></p>
          
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="toggle_registration">
            
            <div style="display:flex;flex-direction:column;gap:1rem;padding:1.5rem;background:var(--bg-secondary);border-radius:var(--radius-md);margin-bottom:1rem;">
              <div>
                <div style="font-weight:600;margin-bottom:0.5rem;font-size:1.1rem;"><?= e(t('registration_status')) ?></div>
                <div style="font-size:0.95rem;color:var(--text-muted);line-height:1.4;">
                  <?= e($registrationEnabled ? t('registration_enabled') : t('registration_disabled')) ?>
                </div>
              </div>
              <button type="submit" class="btn-primary" style="padding:0.75rem 1.5rem;align-self:flex-start;">
                <?= e($registrationEnabled ? t('disable_registration') : t('enable_registration')) ?>
              </button>
            </div>
          </form>
        </div>
        
        <!-- Test-E-Mail senden -->
        <div class="card">
          <h2 class="card-title" style="margin-bottom:1rem;"><?= e(t('test_email_card_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:1.5rem;"><?= e(t('test_email_card_description')) ?></p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="send_test_email">
            <label>
              <span><?= e(t('test_email_recipient')) ?></span>
              <input type="email" name="to" required value="<?= e($user['email'] ?? (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '')) ?>" autocomplete="email">
            </label>
            <label>
              <span><?= e(t('test_email_subject')) ?></span>
              <input type="text" name="subject" value="<?= e(t('test_email_subject_prefix') . ' ' . APP_NAME) ?>">
            </label>
            <label>
              <span><?= e(t('test_email_message')) ?></span>
              <textarea name="body" rows="6"><?= e(t('test_email_body_line1')) ?>

<?= e(t('test_email_body_line2') . ' ' . APP_NAME) ?>

<?= e(t('test_email_body_line3')) ?></textarea>
            </label>
            <button type="submit" class="btn-primary" style="padding:0.75rem 1.5rem;align-self:flex-start;"><?= e(t('test_email_button')) ?></button>
          </form>
          <p style="color:var(--text-muted);margin-top:0.75rem;font-size:0.9rem;"><?= e(t('test_email_hint')) ?></p>
        </div>

      </div>

    </div>
  </main>
</body>
</html>
