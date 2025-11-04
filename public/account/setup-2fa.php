<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();

$user = current_user();
$errors = [];
$step = 'setup'; // 'setup' or 'verify'
$recoveryCodes = [];

// Check if already has 2FA enabled
if (user_has_2fa((int)$user['id'])) {
    header('Location: /account/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = t('csrf_invalid');
    } else {
        try {
            if (isset($_POST['action']) && $_POST['action'] === 'verify') {
                $code = trim($_POST['code'] ?? '');
                
                if (empty($code)) {
                    $errors[] = t('2fa_code_required');
                } else {
                    // Get the secret from session
                    $secret = $_SESSION['2fa_setup_secret'] ?? null;
                    if (!$secret) {
                        throw new RuntimeException(t('error_no_setup_session'));
                    }
                    
                    // Verify the code
                    if (verify_totp_code($secret, $code)) {
                        // Enable 2FA
                        enable_user_2fa((int)$user['id']);
                        
                        // Get recovery codes
                        $twofa = get_user_2fa((int)$user['id']);
                        if ($twofa && !empty($twofa['recovery_codes'])) {
                            $recoveryCodes = json_decode($twofa['recovery_codes'], true);
                        }
                        
                        // Clear setup session
                        unset($_SESSION['2fa_setup_secret']);
                        unset($_SESSION['2fa_setup_qr']);
                        
                        $step = 'complete';
                    } else {
                        $errors[] = t('2fa_invalid_code');
                    }
                }
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Generate secret if not in session
if ($step === 'setup' && empty($_SESSION['2fa_setup_secret'])) {
    $secret = setup_user_2fa((int)$user['id']);
    $_SESSION['2fa_setup_secret'] = $secret;
    $_SESSION['2fa_setup_qr'] = generate_totp_qr_code($secret, $user['email'], APP_NAME);
}

$secret = $_SESSION['2fa_setup_secret'] ?? '';
$qrCodeUrl = $_SESSION['2fa_setup_qr'] ?? '';
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('2fa_setup_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <style>
    .recovery-codes {
      background: var(--bg-secondary);
      padding: 1.5rem;
      border-radius: 0.5rem;
      margin: 1rem 0;
    }
    .recovery-codes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 0.5rem;
      margin-top: 1rem;
    }
    .recovery-code {
      font-family: 'Courier New', monospace;
      font-size: 1rem;
      padding: 0.5rem;
      background: var(--bg);
      border-radius: 0.25rem;
      text-align: center;
    }
    .qr-code {
      text-align: center;
      margin: 1.5rem 0;
    }
    .secret-code {
      font-family: 'Courier New', monospace;
      font-size: 1.1rem;
      font-weight: bold;
      letter-spacing: 2px;
      padding: 1rem;
      background: var(--bg-secondary);
      border-radius: 0.5rem;
      text-align: center;
      margin: 1rem 0;
      word-break: break-all;
    }
  </style>
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

      <?php if ($step === 'complete'): ?>
        <!-- Step 3: Show recovery codes -->
        <div class="card">
          <h2 class="card-title"><?= e(t('2fa_enabled_success')) ?></h2>
          
          <div class="recovery-codes">
            <h3 style="margin-top:0;"><?= e(t('2fa_recovery_codes_title')) ?></h3>
            <p><?= e(t('2fa_recovery_codes_description')) ?></p>
            <p style="color:var(--danger);font-weight:500;"><?= e(t('2fa_recovery_codes_warning')) ?></p>
            
            <div class="recovery-codes-grid">
              <?php foreach ($recoveryCodes as $code): ?>
                <div class="recovery-code"><?= e($code) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
          
          <a href="/account/" class="btn-primary" style="text-decoration:none;">
            <?= e(t('account_link')) ?>
          </a>
        </div>
      <?php else: ?>
        <!-- Step 1 & 2: Show QR code and verify -->
        <div class="card">
          <h2 class="card-title"><?= e(t('2fa_setup_title')) ?></h2>
          
          <p><?= e(t('2fa_setup_step1')) ?></p>
          
          <div class="qr-code">
            <canvas id="qrcode"></canvas>
          </div>
          
          <p><?= e(t('2fa_setup_step2')) ?></p>
          <div class="secret-code"><?= e($secret) ?></div>
          
          <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
          <script>
            // Generate QR code with QRious library
            const otpauthUrl = 'otpauth://totp/<?= urlencode(APP_NAME) ?>:<?= urlencode($user['email']) ?>?secret=<?= e($secret) ?>&issuer=<?= urlencode(APP_NAME) ?>';
            
            new QRious({
              element: document.getElementById('qrcode'),
              value: otpauthUrl,
              size: 250,
              level: 'M',
              background: '#ffffff',
              foreground: '#000000'
            });
          </script>
          
          <p><?= e(t('2fa_setup_step3')) ?></p>
          
          <form method="post" style="margin-top:1.5rem;" id="verify-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="verify">
            <label>
              <span><?= e(t('2fa_verify_code')) ?></span>
              <input 
                type="text" 
                name="code" 
                id="code-input"
                required 
                autocomplete="off"
                pattern="[0-9]{6}"
                maxlength="7"
                placeholder="<?= e(t('2fa_placeholder')) ?>"
                inputmode="numeric"
                style="font-family:'Courier New',monospace;font-size:1.2rem;letter-spacing:0.3rem;text-align:center;">
            </label>
            <div style="display:flex;gap:1rem;">
              <button type="submit" class="btn-primary"><?= e(t('2fa_verify_button')) ?></button>
              <a href="/account/" class="btn-secondary" style="text-decoration:none;padding:0.75rem 1.5rem;">
                <?= e(t('cancel')) ?>
              </a>
            </div>
          </form>
          
          <script>
            // Format 2FA code input with 3-digit blocks (e.g., "226 157")
            const codeInput = document.getElementById('code-input');
            const verifyForm = document.getElementById('verify-form');
            
            codeInput.addEventListener('input', function(e) {
              let value = e.target.value.replace(/\s/g, '').replace(/\D/g, ''); // Remove spaces and non-digits
              
              // Limit to 6 digits
              if (value.length > 6) {
                value = value.slice(0, 6);
              }
              
              // Add space after 3rd digit
              if (value.length > 3) {
                value = value.slice(0, 3) + ' ' + value.slice(3);
              }
              
              e.target.value = value;
            });
            
            // Remove space before form submission
            verifyForm.addEventListener('submit', function(e) {
              codeInput.value = codeInput.value.replace(/\s/g, '');
            });
          </script>
        </div>
      <?php endif; ?>

    </div>
  </main>
</body>
</html>
