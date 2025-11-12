<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
require_auth();

$user = current_user();
$profile_message = '';
$password_message = '';
$security_message = '';
$language_message = '';
$interval_message = '';
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
                $oldEmail = (string)($user['email'] ?? '');
                if ($email !== $oldEmail) {
                    // Apply name/username changes but keep old email in DB
                    update_profile((int)$user['id'], $name, $username, $oldEmail);
                    // Create email change request and send confirmation link to NEW email
                    $token = create_email_change_request((int)$user['id'], $email);
                    $confirmLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/confirm-email-change/?token=' . urlencode($token);
                    $subject = t('email_change_subject_prefix') . ' ' . APP_NAME;
                    $htmlBody = '<p>' . e(t('verify_email_hello')) . '</p>'
                        . '<p>' . e(t('email_change_body_intro')) . '</p>'
                        . '<p><a href="' . e($confirmLink) . '" target="_blank">' . e(t('email_change_link_text')) . '</a></p>'
                        . '<p>' . e(t('verify_email_thanks')) . '</p>';
                    $textBody = t('email_change_body_intro') . "\n" . t('email_change_link_text') . ': ' . $confirmLink;
                    $sent = send_email($email, $name ?: $username, $subject, $htmlBody, $textBody);
                    if ($sent) {
                        $profile_message = t('email_change_requested');
                    } else {
                        $errors[] = t('email_send_failed');
                    }
                    $user = current_user(); // reload
                } else {
                    update_profile((int)$user['id'], $name, $username, $email);
                    $profile_message = t('profile_saved');
                    $user = current_user(); // reload
                }
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
            } elseif (isset($_POST['action']) && $_POST['action'] === 'language') {
                $locale = trim($_POST['locale'] ?? '');
                // allow-list locales available
                $allowed = ['de','en'];
                if (!in_array($locale, $allowed, true)) {
                    throw new InvalidArgumentException('Invalid locale');
                }
                $pdo = db();
                $stmt = $pdo->prepare('UPDATE users SET locale = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$locale, (int)$user['id']]);
                $language_message = t('language_saved_success');
                // Reload translations for this request
                load_locale($locale);
                $user = current_user();
            } elseif (isset($_POST['action']) && $_POST['action'] === 'intervals') {
                // Save per-user maintenance intervals
                $oilKmRaw = (string)($_POST['oil_interval_km'] ?? '');
                $oilYrRaw = (string)($_POST['oil_interval_years'] ?? '');
                $srvKmRaw = (string)($_POST['service_interval_km'] ?? '');
                $srvYrRaw = (string)($_POST['service_interval_years'] ?? '');

                $oilKm = $oilKmRaw !== '' ? (int)preg_replace('/\D+/', '', $oilKmRaw) : null;
                $oilYr = $oilYrRaw !== '' ? (int)preg_replace('/\D+/', '', $oilYrRaw) : null;
                $srvKm = $srvKmRaw !== '' ? (int)preg_replace('/\D+/', '', $srvKmRaw) : null;
                $srvYr = $srvYrRaw !== '' ? (int)preg_replace('/\D+/', '', $srvYrRaw) : null;

                $pdo = db();
                // Ensure columns exist (safe ALTERs)
                $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll());
                if (!in_array('oil_interval_km', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN oil_interval_km INT NULL AFTER locale"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
                if (!in_array('oil_interval_years', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN oil_interval_years INT NULL AFTER oil_interval_km"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
                if (!in_array('service_interval_km', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN service_interval_km INT NULL AFTER oil_interval_years"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
                if (!in_array('service_interval_years', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN service_interval_years INT NULL AFTER service_interval_km"); } catch (Throwable $__) {} }

                // Build dynamic update only for changed fields
                $setParts = [];
                $params = [':id' => (int)$user['id']];

                // Oil KM: if input empty -> clear only if previously non-null; if non-empty -> set to value
                if ($oilKmRaw === '') {
                    if (isset($user['oil_interval_km']) && $user['oil_interval_km'] !== null) {
                        $setParts[] = 'oil_interval_km = NULL';
                    }
                } else {
                    $setParts[] = 'oil_interval_km = :okm';
                    $params[':okm'] = $oilKm;
                }

                // Oil Years
                if ($oilYrRaw === '') {
                    if (isset($user['oil_interval_years']) && $user['oil_interval_years'] !== null) {
                        $setParts[] = 'oil_interval_years = NULL';
                    }
                } else {
                    $setParts[] = 'oil_interval_years = :oy';
                    $params[':oy'] = $oilYr;
                }

                // Service KM
                if ($srvKmRaw === '') {
                    if (isset($user['service_interval_km']) && $user['service_interval_km'] !== null) {
                        $setParts[] = 'service_interval_km = NULL';
                    }
                } else {
                    $setParts[] = 'service_interval_km = :skm';
                    $params[':skm'] = $srvKm;
                }

                // Service Years
                if ($srvYrRaw === '') {
                    if (isset($user['service_interval_years']) && $user['service_interval_years'] !== null) {
                        $setParts[] = 'service_interval_years = NULL';
                    }
                } else {
                    $setParts[] = 'service_interval_years = :sy';
                    $params[':sy'] = $srvYr;
                }

                if (!empty($setParts)) {
                    $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                $interval_message = t('intervals_saved_success');
                $user = current_user();
            } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_account') {
                $confirmEmail = trim($_POST['confirm_email'] ?? '');
                if ($confirmEmail === $user['email']) {
                    // Delete user account
                    delete_user_account((int)$user['id']);
                    // Logout
                    logout_user();
                    header('Location: /');
                    exit;
                } else {
                    $errors[] = t('delete_account_email_mismatch');
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
  <?php render_i18n_for_js([
    'pwd_req_title', 'pwd_req_length', 'pwd_req_uppercase', 'pwd_req_lowercase', 'pwd_req_number', 'pwd_match', 'pwd_no_match',
    'availability_error', 'username_available', 'username_taken', 'email_available', 'email_taken', 'checking_availability'
  ]); ?>
  <script defer src="/assets/js/password-validator.js"></script>
  <script defer src="/assets/js/account-validator.js"></script>
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

      <div class="account-grid">
        <?php if (is_admin()): ?>
        <!-- Admin-Kachel für Einstellungen -->
        <div class="card" style="grid-column: 1 / -1;">
          <h2 class="card-title"><?= e(t('admin_panel_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:1.5rem;"><?= e(t('admin_panel_description')) ?></p>
          <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <a href="/admin/" class="btn-primary" style="padding:0.75rem 1.5rem;text-decoration:none;">
              <?= e(t('admin_settings_link')) ?>
            </a>
            <a href="/admin/users" class="btn-primary" style="padding:0.75rem 1.5rem;text-decoration:none;">
              <?= e(t('manage_users')) ?>
            </a>
          </div>
        </div>
        <?php endif; ?>

        <form method="post" class="card">
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
        <script>
          (function(){
            function formatKm(val){
              var digits = (val||'').replace(/\D+/g,'');
              if (!digits) return '';
              return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
            function attachKmFormatting(input){
              if (!input) return;
              input.addEventListener('input', function(){
                var pos = input.selectionStart;
                input.value = formatKm(input.value);
                try { input.setSelectionRange(pos, pos); } catch(e) {}
              });
              input.addEventListener('blur', function(){ input.value = formatKm(input.value); });
            }
            var oilKm = document.getElementById('oil_interval_km');
            var srvKm = document.getElementById('service_interval_km');
            attachKmFormatting(oilKm);
            attachKmFormatting(srvKm);
            if (oilKm) oilKm.value = formatKm(oilKm.value);
            if (srvKm) srvKm.value = formatKm(srvKm.value);
            // Strip separators on submit of the intervals form only
            var forms = document.getElementsByTagName('form');
            for (var i=0;i<forms.length;i++){
              if (forms[i].querySelector('input[name="action"][value="intervals"]')){
                forms[i].addEventListener('submit', function(){
                  if (oilKm) oilKm.value = (oilKm.value||'').replace(/\D+/g,'');
                  if (srvKm) srvKm.value = (srvKm.value||'').replace(/\D+/g,'');
                });
              }
            }
          })();
        </script>

        <!-- Maintenance Intervals -->
        <?php
          // Ensure columns exist for display too
          try {
            $pdo = db();
            $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll());
            if (!in_array('oil_interval_km', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN oil_interval_km INT NULL AFTER locale"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
            if (!in_array('oil_interval_years', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN oil_interval_years INT NULL AFTER oil_interval_km"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
            if (!in_array('service_interval_km', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN service_interval_km INT NULL AFTER oil_interval_years"); } catch (Throwable $__) {} $cols = array_map(function($c){ return is_array($c)&&isset($c['Field'])?(string)$c['Field']:(string)($c[0]??''); }, $pdo->query('SHOW COLUMNS FROM users')->fetchAll()); }
            if (!in_array('service_interval_years', $cols, true)) { try { $pdo->exec("ALTER TABLE users ADD COLUMN service_interval_years INT NULL AFTER service_interval_km"); } catch (Throwable $__) {} }
          } catch (Throwable $__) {}
          $oilKmVal = isset($user['oil_interval_km']) && $user['oil_interval_km'] !== null ? number_format((int)$user['oil_interval_km'], 0, '', '.') : '';
          $oilYrVal = isset($user['oil_interval_years']) && $user['oil_interval_years'] !== null ? (string)$user['oil_interval_years'] : '';
          $srvKmVal = isset($user['service_interval_km']) && $user['service_interval_km'] !== null ? number_format((int)$user['service_interval_km'], 0, '', '.') : '';
          $srvYrVal = isset($user['service_interval_years']) && $user['service_interval_years'] !== null ? (string)$user['service_interval_years'] : '';
          $defOilKmPh = number_format((int)DEFAULT_OIL_INTERVAL_KM, 0, '', '.');
          $defSrvKmPh = number_format((int)DEFAULT_SERVICE_INTERVAL_KM, 0, '', '.');
        ?>
        <form method="post" class="card">
          <h2 class="card-title"><?= e(t('account_intervals_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:1rem;">&nbsp;<?= e(t('account_intervals_desc')) ?></p>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="intervals">
          <div class="row">
            <div class="field">
              <label>
                <span><?= e(t('oil_interval_km')) ?></span>
                <input id="oil_interval_km" type="text" name="oil_interval_km" inputmode="numeric" placeholder="<?= e($defOilKmPh) ?>" value="<?= e($oilKmVal) ?>">
              </label>
            </div>
            <div class="field">
              <label>
                <span><?= e(t('oil_interval_years')) ?></span>
                <input type="number" name="oil_interval_years" min="0" step="1" placeholder="<?= e((string)DEFAULT_OIL_INTERVAL_YEARS) ?>" value="<?= e($oilYrVal) ?>">
              </label>
            </div>
          </div>
          <div class="row">
            <div class="field">
              <label>
                <span><?= e(t('service_interval_km')) ?></span>
                <input id="service_interval_km" type="text" name="service_interval_km" inputmode="numeric" placeholder="<?= e($defSrvKmPh) ?>" value="<?= e($srvKmVal) ?>">
              </label>
            </div>
            <div class="field">
              <label>
                <span><?= e(t('service_interval_years')) ?></span>
                <input type="number" name="service_interval_years" min="0" step="1" placeholder="<?= e((string)DEFAULT_SERVICE_INTERVAL_YEARS) ?>" value="<?= e($srvYrVal) ?>">
              </label>
            </div>
          </div>
          <button type="submit" class="btn-primary"><?= e(t('save')) ?></button>
          <?php if ($interval_message): ?>
            <div class="alert success-message mt-1">
              <?= e($interval_message) ?>
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

        <!-- Language Preference (after password) -->
        <form method="post" class="card">
          <h2 class="card-title"><?= e(t('language_settings_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:1rem;">&nbsp;<?= e(t('language_settings_description')) ?></p>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="language">
          <label>
            <span><?= e(t('language_label')) ?></span>
            <?php $currentLocale = $user['locale'] ?: APP_LOCALE; ?>
            <select name="locale" required>
              <option value="de" <?= $currentLocale === 'de' ? 'selected' : '' ?>><?= e(t('language_de')) ?></option>
              <option value="en" <?= $currentLocale === 'en' ? 'selected' : '' ?>><?= e(t('language_en')) ?></option>
            </select>
          </label>
          <button type="submit" class="btn-primary"><?= e(t('language_save_button')) ?></button>
          <?php if ($language_message): ?>
            <div class="alert success-message mt-1">
              <?= e($language_message) ?>
            </div>
          <?php endif; ?>
        </form>

        <div class="card" style="grid-column: 1 / -1;">
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
              <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(t('confirm_disable_2fa')) ?>');">
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
                    <div style="font-weight:500;"><?= e($pk['name'] ?: (t('passkey_default_prefix') . (string)$pk['id'])) ?></div>
                    <div style="font-size:0.875rem;color:var(--text-muted);">
                      <?php if ($pk['last_used_at']): ?>
                        <?= e(t('passkey_last_used')) ?> <?= e(date('d.m.Y H:i', strtotime($pk['last_used_at']))) ?>
                      <?php else: ?>
                        <?= e(t('passkey_never_used')) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(t('confirm_remove_passkey')) ?>');">
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


        <!-- Account Deletion Section -->
        <div style="margin-top:3rem;padding-top:2rem;border-top:2px solid rgba(var(--color-border), 0.5);">
          <h3 style="font-size:1.1rem;margin-bottom:0.5rem;color:rgb(239, 68, 68);"><?= e(t('delete_account_title')) ?></h3>
          <p style="color:var(--text-muted);margin-bottom:1rem;"><?= e(t('delete_account_description')) ?></p>
          
          <button type="button" id="delete-account-btn" class="btn-primary" style="padding:0.5rem 1rem;background:#dc3545;border-color:#dc3545;">
            <?= e(t('delete_account_button')) ?>
          </button>
        </div>
        </div>
      </div>

    </div>
  </main>
  
  <!-- Delete Account Modal -->
  <div id="delete-account-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:rgb(var(--color-bg));padding:2rem;border-radius:var(--radius-lg);max-width:500px;width:90%;margin:1rem;box-shadow:var(--shadow-lg);">
      <h3 style="color:rgb(239, 68, 68);margin-bottom:1rem;font-size:1.5rem;"><?= e(t('delete_account_confirm_title')) ?></h3>
      <p style="margin-bottom:1rem;color:var(--text-muted);"><?= e(t('delete_account_confirm_text')) ?></p>
      <p style="margin-bottom:1.5rem;font-weight:600;"><?= e(t('delete_account_confirm_instruction')) ?></p>
      
      <form method="post" id="delete-account-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_account">
        <label style="margin-bottom:1.5rem;">
          <span style="font-weight:500;margin-bottom:0.5rem;display:block;"><?= e(t('delete_account_email_label')) ?></span>
          <input type="text" name="confirm_email" id="confirm-email-input" required autocomplete="off" 
                 placeholder="<?= e($user['email']) ?>" 
                 style="font-family:'Courier New',monospace;">
        </label>
        
        <div style="display:flex;gap:1rem;">
          <button type="submit" id="confirm-delete-btn" disabled class="btn-primary" 
                  style="flex:1;background:#dc3545;border-color:#dc3545;opacity:0.5;cursor:not-allowed;">
            <?= e(t('delete_account_confirm_button')) ?>
          </button>
          <button type="button" id="cancel-delete-btn" class="btn-primary" style="flex:1;background:var(--text-muted);border-color:var(--text-muted);">
            <?= e(t('cancel')) ?>
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    // Delete account modal functionality
    const deleteAccountBtn = document.getElementById('delete-account-btn');
    const deleteAccountModal = document.getElementById('delete-account-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmEmailInput = document.getElementById('confirm-email-input');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const deleteAccountForm = document.getElementById('delete-account-form');
    const userEmail = '<?= e($user['email']) ?>';
    
    deleteAccountBtn.addEventListener('click', () => {
      deleteAccountModal.style.display = 'flex';
      confirmEmailInput.value = '';
      confirmEmailInput.focus();
    });
    
    cancelDeleteBtn.addEventListener('click', () => {
      deleteAccountModal.style.display = 'none';
    });
    
    // Close modal on background click
    deleteAccountModal.addEventListener('click', (e) => {
      if (e.target === deleteAccountModal) {
        deleteAccountModal.style.display = 'none';
      }
    });
    
    // Enable/disable confirm button based on email match
    confirmEmailInput.addEventListener('input', () => {
      if (confirmEmailInput.value === userEmail) {
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.style.opacity = '1';
        confirmDeleteBtn.style.cursor = 'pointer';
      } else {
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.style.opacity = '0.5';
        confirmDeleteBtn.style.cursor = 'not-allowed';
      }
    });
    
    // Confirm before form submission
    deleteAccountForm.addEventListener('submit', (e) => {
      if (confirmEmailInput.value !== userEmail) {
        e.preventDefault();
        return;
      }
      if (!confirm('<?= e(t('delete_account_final_confirm')) ?>')) {
        e.preventDefault();
      }
    });

    // Reorder cards: 1) profile, 2) password, 3) intervals, 4) language, 5) security
    try {
      var profileForm = document.querySelector('form.card input[name="action"][value="profile"]');
      var passwordForm = document.querySelector('form.card input[name="action"][value="password"]');
      var intervalsForm = document.querySelector('form.card input[name="action"][value="intervals"]');
      var languageForm = document.querySelector('form.card input[name="action"][value="language"]');
      var securityTitle = <?= json_encode(t('security_section')) ?>;
      var securityCard = null;
      var cardTitles = document.querySelectorAll('.card .card-title');
      for (var i=0;i<cardTitles.length;i++) {
        if ((cardTitles[i].textContent||'').trim() === securityTitle) {
          securityCard = cardTitles[i].closest('.card');
          break;
        }
      }
      var parent = null;
      if (profileForm) parent = profileForm.closest('.card').parentNode;
      if (parent && profileForm && passwordForm && intervalsForm && languageForm && securityCard) {
        var pf = profileForm.closest('.card');
        var pw = passwordForm.closest('.card');
        var iv = intervalsForm.closest('.card');
        var lg = languageForm.closest('.card');
        var sc = securityCard;
        parent.insertBefore(pf, parent.firstChild);
        parent.insertBefore(pw, pf.nextSibling);
        parent.insertBefore(iv, pw.nextSibling);
        parent.insertBefore(lg, iv.nextSibling);
        parent.insertBefore(sc, lg.nextSibling);
        // adjust spacing for language: slightly closer to previous card
        lg.style.marginTop = '0.75rem';
      }
    } catch (e) { /* no-op */ }
  </script>
</body>
</html>
