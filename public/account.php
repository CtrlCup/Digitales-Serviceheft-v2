<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

$user = current_user();
$profile_message = '';
$password_message = '';
$security_message = '';
$errors = [];

// Get 2FA and Passkeys status
$has2fa = user_has_2fa((int)$user['id']);
$passkeys = get_user_passkeys((int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        try {
            if (isset($_POST['action']) && $_POST['action'] === 'profile') {
                $name = trim($_POST['name'] ?? '');
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                update_profile((int)$user['id'], $name, $username, $email);
                $profile_message = t('profile_saved');
                $user = current_user(); // reload
            } elseif (isset($_POST['action']) && $_POST['action'] === 'password') {
                $current = $_POST['current_password'] ?? '';
                $new = $_POST['new_password'] ?? '';
                $confirm = $_POST['new_password_confirm'] ?? '';
                update_password((int)$user['id'], $current, $new, $confirm);
                $password_message = t('password_saved');
            } elseif (isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
                disable_user_2fa((int)$user['id']);
                $security_message = t('2fa_disabled_success');
                $has2fa = false;
            } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_passkey') {
                $passkeyId = (int)($_POST['passkey_id'] ?? 0);
                if (remove_user_passkey((int)$user['id'], $passkeyId)) {
                    $security_message = t('passkey_removed_success');
                    $passkeys = get_user_passkeys((int)$user['id']);
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_account')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <?php render_i18n_for_js(['pwd_req_title', 'pwd_req_length', 'pwd_req_uppercase', 'pwd_req_lowercase', 'pwd_req_number', 'pwd_match', 'pwd_no_match']); ?>
  <script defer src="/assets/js/password-validator.js"></script>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('dashboard_title'), 'href' => '/', 'icon' => 'home'],
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ]
  ]); ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <?php if ($errors): ?>
        <div class="alert">
          <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title"><?= e(t('profile_section')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">
        <label>
          <span><?= e(t('name')) ?></span>
          <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required autocomplete="name">
        </label>
        <label>
          <span><?= e(t('username')) ?></span>
          <input type="text" name="username" value="<?= e($user['username'] ?? '') ?>" required autocomplete="username">
        </label>
        <label>
          <span><?= e(t('email')) ?></span>
          <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required autocomplete="email">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('save_profile')) ?></button>
        <?php if ($profile_message): ?>
          <div class="alert success-message mt-1">
            <?= e($profile_message) ?>
          </div>
        <?php endif; ?>
      </form>

      <form method="post" class="card">
        <h2 class="card-title"><?= e(t('password_section')) ?></h2>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">
        <label>
          <span><?= e(t('current_password')) ?></span>
          <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label>
          <span><?= e(t('new_password')) ?></span>
          <input type="password" name="new_password" required autocomplete="new-password">
        </label>
        <label>
          <span><?= e(t('new_password_confirm')) ?></span>
          <input type="password" name="new_password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn-primary"><?= e(t('save_password')) ?></button>
        <?php if ($password_message): ?>
          <div class="alert success-message mt-1">
            <?= e($password_message) ?>
          </div>
        <?php endif; ?>
      </form>

      <div class="card" style="margin-bottom:1.5rem;">
        <h2 class="card-title"><?= e(t('security_section')) ?></h2>
        
        <?php if ($security_message): ?>
          <div class="alert success-message" style="margin-bottom:1rem;">
            <?= e($security_message) ?>
          </div>
        <?php endif; ?>
        
        <!-- 2FA Section -->
        <div style="margin-bottom:2rem;">
          <h3 style="font-size:1.1rem;margin-bottom:0.5rem;"><?= e(t('2fa_title')) ?></h3>
          <p style="color:var(--text-muted);margin-bottom:1rem;"><?= e(t('2fa_description')) ?></p>
          <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-weight:500;">
              <?= e(t('2fa_status_' . ($has2fa ? 'enabled' : 'disabled'))) ?>
            </span>
            <?php if ($has2fa): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Möchtest du 2FA wirklich deaktivieren? Dies verringert die Sicherheit deines Kontos.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="disable_2fa">
                <button type="submit" class="btn-primary" style="padding:0.5rem 1rem;background:#dc3545;border-color:#dc3545;">
                  <?= e(t('2fa_disable_button')) ?>
                </button>
              </form>
            <?php else: ?>
              <a href="/account/setup-2fa" class="btn-primary" style="padding:0.5rem 1rem;text-decoration:none;">
                <?= e(t('2fa_enable_button')) ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- Passkeys Section -->
        <div>
          <h3 style="font-size:1.1rem;margin-bottom:0.5rem;"><?= e(t('passkeys_title')) ?></h3>
          <p style="color:var(--text-muted);margin-bottom:1rem;"><?= e(t('passkeys_description')) ?></p>
          
          <?php if (empty($passkeys)): ?>
            <p style="color:var(--text-muted);font-style:italic;margin-bottom:1rem;">
              <?= e(t('passkeys_none')) ?>
            </p>
          <?php else: ?>
            <div style="margin-bottom:1rem;">
              <?php foreach ($passkeys as $pk): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:var(--bg-secondary);border-radius:0.5rem;margin-bottom:0.5rem;">
                  <div>
                    <div style="font-weight:500;"><?= e($pk['name'] ?: 'Passkey #' . $pk['id']) ?></div>
                    <div style="font-size:0.875rem;color:var(--text-muted);">
                      <?php if ($pk['last_used_at']): ?>
                        <?= e(t('passkey_last_used')) ?> <?= e(date('d.m.Y H:i', strtotime($pk['last_used_at']))) ?>
                      <?php else: ?>
                        <?= e(t('passkey_never_used')) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Möchtest du diesen Passkey wirklich entfernen?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove_passkey">
                    <input type="hidden" name="passkey_id" value="<?= e((string)$pk['id']) ?>">
                    <button type="submit" class="btn-primary" style="padding:0.5rem 1rem;background:#dc3545;border-color:#dc3545;">
                      <?= e(t('passkey_remove_button')) ?>
                    </button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          
          <a href="/account/add-passkey" class="btn-primary" style="padding:0.5rem 1rem;text-decoration:none;">
            <?= e(t('passkey_add_button')) ?>
          </a>
        </div>
      </div>

    </div>
  </main>
</body>
</html>
