<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();

$user = current_user();
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('passkey_add_button')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <style>
    .status-message {
      padding: 1rem;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
    }
    .status-info {
      background: var(--bg-secondary);
      color: var(--text);
    }
    .status-error {
      background: var(--danger);
      color: white;
    }
    .status-success {
      background: var(--success);
      color: white;
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
      <div class="card">
        <h2 class="card-title"><?= e(t('passkey_add_button')) ?></h2>
        
        <div id="status-message"></div>
        
        <p style="color:var(--text-muted);margin-bottom:1.5rem;">
          <?= e(t('passkeys_description')) ?>
        </p>
        
        <form id="passkey-form">
          <label>
            <span><?= e(t('passkey_name_label')) ?></span>
            <input 
              type="text" 
              id="passkey-name" 
              name="name" 
              placeholder="<?= e(t('passkey_name_placeholder')) ?>"
              required>
          </label>
          <div style="display:flex;gap:1rem;">
            <button type="submit" class="btn-primary" id="register-btn">
              <?= e(t('passkey_add_button')) ?>
            </button>
            <a href="/account/" class="btn-secondary" style="text-decoration:none;padding:0.75rem 1.5rem;">
              Abbrechen
            </a>
          </div>
        </form>
      </div>
    </div>
  </main>
  
  <script>
    const statusEl = document.getElementById('status-message');
    const form = document.getElementById('passkey-form');
    const nameInput = document.getElementById('passkey-name');
    const registerBtn = document.getElementById('register-btn');
    
    function showStatus(message, type = 'info') {
      statusEl.className = 'status-message status-' + type;
      statusEl.textContent = message;
      statusEl.style.display = 'block';
    }
    
    function hideStatus() {
      statusEl.style.display = 'none';
    }
    
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
    
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideStatus();
      
      const name = nameInput.value.trim();
      if (!name) {
        showStatus('Bitte gib einen Namen für den Passkey ein.', 'error');
        return;
      }
      
      registerBtn.disabled = true;
      showStatus('Passkey wird registriert...', 'info');
      
      try {
        // Step 1: Get challenge from server
        const optionsResponse = await fetch('/api/passkey-register-options.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' }
        });
        
        if (!optionsResponse.ok) {
          throw new Error('Failed to get registration options');
        }
        
        const options = await optionsResponse.json();
        
        // Convert challenge and user ID to ArrayBuffer
        const publicKeyCredentialCreationOptions = {
          challenge: base64urlToBuffer(options.challenge),
          rp: options.rp,
          user: {
            id: base64urlToBuffer(options.user.id),
            name: options.user.name,
            displayName: options.user.displayName
          },
          pubKeyCredParams: options.pubKeyCredParams,
          timeout: options.timeout,
          attestation: options.attestation,
          authenticatorSelection: options.authenticatorSelection
        };
        
        // Step 2: Create credential
        showStatus('Bitte verwende deinen Authenticator...', 'info');
        const credential = await navigator.credentials.create({
          publicKey: publicKeyCredentialCreationOptions
        });
        
        if (!credential) {
          throw new Error('Credential creation failed');
        }
        
        // Step 3: Send credential to server
        const credentialData = {
          id: credential.id,
          rawId: bufferToBase64url(credential.rawId),
          type: credential.type,
          response: {
            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
            attestationObject: bufferToBase64url(credential.response.attestationObject)
          },
          name: name
        };
        
        const verifyResponse = await fetch('/api/passkey-register-verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(credentialData)
        });
        
        const result = await verifyResponse.json();
        
        if (result.success) {
          showStatus('<?= e(t('passkey_added_success')) ?>', 'success');
          setTimeout(() => {
            window.location.href = '/account/';
          }, 1500);
        } else {
          throw new Error(result.error || 'Verification failed');
        }
        
      } catch (error) {
        console.error('Passkey registration error:', error);
        showStatus('Fehler: ' + error.message, 'error');
        registerBtn.disabled = false;
      }
    });
  </script>
</body>
</html>
