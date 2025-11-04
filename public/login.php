<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
$require2fa = false;
$userId2fa = null;
$offerReset = false;
$identifierPrefill = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        // Handle password reset request triggered from failed login
        if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
            $identifierPrefill = trim($_POST['identifier'] ?? '');
            try {
                reset_password_and_email($identifierPrefill);
                $errors[] = 'Neues Passwort wurde per E-Mail gesendet. Bitte Posteingang prüfen.';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        // Check if this is 2FA verification
        if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
            $code = trim($_POST['2fa_code'] ?? '');
            $userId = (int)($_SESSION['2fa_user_id'] ?? 0);
            
            if (!$userId) {
                $errors[] = t('session_expired_login_again');
            } elseif (empty($code)) {
                $errors[] = t('2fa_code_required');
                $require2fa = true;
                $userId2fa = $userId;
            } else {
                // Get user's 2FA data
                $twofa = get_user_2fa($userId);
                if ($twofa && verify_totp_code($twofa['totp_secret'], $code)) {
                    // Success - complete login
                    $_SESSION['user_id'] = $userId;
                    unset($_SESSION['2fa_user_id']);
                    
                    // Update login info to reflect completed 2FA authentication
                    $pdo = db();
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                    $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ?, last_login_device = ?, last_login_method = ?, last_login_2fa_enabled = ?, updated_at = NOW() WHERE id = ?');
                    $upd->execute([$ip, $agent, 'password', 1, $userId]);
                    
                    header('Location: /');
                    exit;
                } elseif ($twofa && verify_recovery_code($userId, $code)) {
                    // Recovery code used - complete login
                    $_SESSION['user_id'] = $userId;
                    unset($_SESSION['2fa_user_id']);
                    
                    // Update login info to reflect completed 2FA authentication
                    $pdo = db();
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                    $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ?, last_login_device = ?, last_login_method = ?, last_login_2fa_enabled = ?, updated_at = NOW() WHERE id = ?');
                    $upd->execute([$ip, $agent, 'password', 1, $userId]);
                    
                    header('Location: /');
                    exit;
                } else {
                    $errors[] = t('2fa_invalid_code');
                    $require2fa = true;
                    $userId2fa = $userId;
                }
            }
        } else if (!isset($_POST['action'])) {
            // Regular login
            $identifier = trim($_POST['identifier'] ?? '');
            $password = $_POST['password'] ?? '';
            $identifierPrefill = $identifier;
            [$ok, $err] = authenticate_with_lockout($identifier, $password);
            if ($ok) {
                // Check if user has 2FA enabled
                $currentUser = current_user();
                if ($currentUser && user_has_2fa((int)$currentUser['id'])) {
                    // Require 2FA verification
                    $userId2fa = (int)$currentUser['id'];
                    $_SESSION['2fa_user_id'] = $userId2fa;
                    unset($_SESSION['user_id']); // Remove regular login
                    $require2fa = true;
                } else {
                    // Login complete
                    header('Location: /');
                    exit;
                }
            } else {
                $errors[] = $err ?: t('login_failed');
                $offerReset = true;
            }
        }
    }
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_login')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <style>
    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
    }
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid var(--border);
    }
    .divider span {
      padding: 0 1rem;
      color: var(--text-muted);
      font-size: 0.875rem;
    }
  </style>
</head>
<body class="page">
  <?php 
    $cta = is_registration_enabled()
      ? ['label' => t('register_button'), 'href' => '/register/']
      : null;
    render_brand_header(['cta' => $cta]);
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
          <button type="button" class="error-close" onclick="this.parentElement.style.display='none'" aria-label="<?= e(t('close')) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <script>
          // Auto-hide error toast after 5 seconds
          setTimeout(() => {
            const toast = document.getElementById('error-toast');
            if (toast) toast.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <?php if ($require2fa): ?>
        <!-- 2FA Verification Form -->
        <form method="post" class="card" id="2fa-form">
          <h2 class="card-title"><?= e(t('2fa_title')) ?></h2>
          <p style="color:var(--text-muted);margin-bottom:1.5rem;">
            <?= e(t('2fa_code_required')) ?>
          </p>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="verify_2fa">
          <label>
            <span><?= e(t('2fa_code_label')) ?></span>
            <input 
              type="text" 
              id="2fa-code-input"
              name="2fa_code" 
              required 
              autocomplete="off"
              placeholder="<?= e(t('2fa_placeholder')) ?>"
              autofocus
              inputmode="numeric"
              maxlength="9"
              style="font-family:'Courier New',monospace;font-size:1.2rem;letter-spacing:0.3rem;text-align:center;">
          </label>
          <p style="font-size:0.875rem;color:var(--text-muted);margin-top:0.5rem;margin-bottom:1rem;">
            <?= e(t('2fa_or_recovery_code')) ?>
          </p>
          <button type="submit" class="btn-primary"><?= e(t('login_button')) ?></button>
        </form>
        
        <script>
          // Auto-format 2FA code or recovery code
          const codeInput = document.getElementById('2fa-code-input');
          const tfaForm = document.getElementById('2fa-form');
          
          function formatCode(value) {
            // Remove all non-digits
            value = value.replace(/[^0-9]/g, '');
            
            // For 6-digit 2FA code: format as "123 456" (two 3-digit blocks)
            if (value.length <= 6) {
              if (value.length > 3) {
                return value.substring(0, 3) + ' ' + value.substring(3);
              }
              return value;
            }
            
            // For 7+ digits: format as recovery code "1234-5678"
            if (value.length > 6 && value.length <= 8) {
              return value.substring(0, 4) + '-' + value.substring(4);
            }
            
            return value;
          }
          
          codeInput.addEventListener('input', function(e) {
            const cursorPos = e.target.selectionStart;
            const oldValue = e.target.value;
            const newValue = formatCode(oldValue);
            
            e.target.value = newValue;
            
            // Adjust cursor position if formatting added characters
            if (newValue.length !== oldValue.length) {
              const diff = newValue.length - oldValue.length;
              e.target.setSelectionRange(cursorPos + diff, cursorPos + diff);
            }
          });
          
          // Also format on paste
          codeInput.addEventListener('paste', function(e) {
            setTimeout(() => {
              e.target.value = formatCode(e.target.value);
            }, 10);
          });
          
          // Remove formatting before form submission
          tfaForm.addEventListener('submit', function(e) {
            codeInput.value = codeInput.value.replace(/[\s\-]/g, '');
          });
        </script>
      <?php else: ?>
        <!-- Regular Login Form -->
        <form method="post" class="card">
          <h2 class="card-title"><?= e(t('login_title')) ?></h2>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <label>
            <span><?= e(t('identifier')) ?></span>
            <input type="text" name="identifier" required autocomplete="username" value="<?= e($identifierPrefill) ?>">
          </label>
          <label>
            <span><?= e(t('password')) ?></span>
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <button type="submit" class="btn-primary"><?= e(t('login_button')) ?></button>

          <?php if ($offerReset && $identifierPrefill !== ''): ?>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
              <button type="submit" name="action" value="reset_password" class="btn-secondary"><?php echo e('Passwort zurücksetzen'); ?></button>
              <span style="font-size:0.875rem;color:var(--text-muted);">
                <?php echo e('Du erhälst ein neues Passwort per E-Mail.'); ?>
              </span>
            </div>
          <?php endif; ?>
          
          <div class="divider">
            <span><?= e(t('or_divider')) ?></span>
          </div>
          
          <button type="button" id="passkey-login-btn" class="btn-primary" style="width:100%;">
            <?= e(t('login_with_passkey')) ?>
          </button>
        </form>
        
        <script>
          const passkeyBtn = document.getElementById('passkey-login-btn');
          
          // Convert base64url to ArrayBuffer
          function base64urlToBuffer(base64url) {
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const binary = atob(base64);
            const buffer = new ArrayBuffer(binary.length);
            const bytes = new Uint8Array(buffer);
            for (let i = 0; i < binary.length; i++) {
              bytes[i] = binary.charCodeAt(i);
            }
            return buffer;
          }
          
          // Convert ArrayBuffer to base64url
          function bufferToBase64url(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
              binary += String.fromCharCode(bytes[i]);
            }
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
          }
          
          passkeyBtn.addEventListener('click', async () => {
            try {
              passkeyBtn.disabled = true;
              passkeyBtn.textContent = '<?= e(t('connecting')) ?>';
              
              // Get authentication options
              const optionsResponse = await fetch('/api/passkey-auth-options.php');
              if (!optionsResponse.ok) throw new Error('<?= e(t('passkey_get_options_failed')) ?>');
              
              const options = await optionsResponse.json();
              
              // Prepare credential request options
              const publicKeyCredentialRequestOptions = {
                challenge: base64urlToBuffer(options.challenge),
                timeout: options.timeout,
                rpId: options.rpId,
                userVerification: options.userVerification
              };
              
              // Get credential
              const credential = await navigator.credentials.get({
                publicKey: publicKeyCredentialRequestOptions
              });
              
              if (!credential) throw new Error('<?= e(t('passkey_no_credential')) ?>');
              
              // Send to server for verification
              const verifyResponse = await fetch('/api/passkey-auth-verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  id: credential.id,
                  rawId: bufferToBase64url(credential.rawId),
                  type: credential.type,
                  response: {
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                    signature: bufferToBase64url(credential.response.signature),
                    userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null
                  }
                })
              });
              
              const result = await verifyResponse.json();
              
              if (result.success) {
                window.location.href = '/';
              } else {
                throw new Error(result.error || '<?= e(t('passkey_auth_failed_generic')) ?>');
              }
              
            } catch (error) {
              console.error('Passkey login error:', error);
              alert('<?= e(t('passkey_login_failed')) ?>: ' + error.message);
              passkeyBtn.disabled = false;
              passkeyBtn.textContent = '<?= e(t('login_with_passkey')) ?>';
            }
          });
        </script>
      <?php endif; ?>

    </div>
  </main>
</body>
</html>
