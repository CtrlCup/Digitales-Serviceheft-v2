<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

// Require login
if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
$errors = $_SESSION['form_errors'] ?? [];
$values = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);

// Handle POST by delegating to src handler (not directly accessible via public URL)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../../src/vehicles/create_vehicle.php';
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('vehicle_create_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
      'links' => [
        ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
        ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
      ]
  ]); ?>
  <main class="page-content">
    <div class="container reveal-enter">
      <a href="/dashboard" class="header-link-with-text" style="margin-bottom:1rem;">
        <?= icon_svg('arrow-left') ?>
        <span class="link-text"><?= e(t('vehicles_back_to_overview')) ?></span>
      </a>

      <?php if (!empty($errors)): ?>
        <div class="card" style="border-color:var(--danger);color:var(--danger);">
          <h2 class="card-title"><?= e(t('error_title')) ?></h2>
          <ul style="margin:0;padding-left:1.2rem;">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        </div>
      <?php endif; ?>

      <form method="post" action="/vehicles/create" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <style>
          .cards-grid { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items:start; }
          @media (max-width: 420px) { .cards-grid { grid-template-columns: 1fr; } }
          .cards-grid .card { height: 100%; }
        </style>
        <div class="cards-grid">
        <div class="card">
          <h2 class="card-title"><?= e(t('vehicle_data')) ?></h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(120px,1fr));gap:0.75rem;">
              <label>
                <span><?= e(t('hsn')) ?></span>
                <input type="text" name="hsn" inputmode="numeric" pattern="\\d{1,4}" maxlength="4" value="<?= e($values['hsn'] ?? '') ?>">
              </label>
              <label>
                <span><?= e(t('tsn')) ?></span>
                <input type="text" name="tsn" pattern="[A-Za-z0-9]{1,4}" maxlength="4" value="<?= e($values['tsn'] ?? '') ?>" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,4)">
              </label>
            </div>
            <label>
              <span><?= e(t('vin')) ?></span>
              <input type="text" name="vin" maxlength="17" pattern="[A-Z0-9]{1,17}" value="<?= e($values['vin'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('license_plate')) ?></span>
              <input type="text" name="license_plate" value="<?= e($values['license_plate'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('make')) ?></span>
              <input type="text" name="make" required value="<?= e($values['make'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('model')) ?></span>
              <input type="text" name="model" required value="<?= e($values['model'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('first_registration_with_format')) ?></span>
              <input type="text" name="first_registration" inputmode="numeric" placeholder="<?= e(t('example_date_de')) ?>" value="<?= e($values['first_registration'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('color')) ?></span>
              <input type="text" name="color" value="<?= e($values['color'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('profile_image_hint_create')) ?></span>
              <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
              <input type="hidden" name="profile_image_path" id="profile_image_path_create" value="">
              <div id="upload_wrap_create" style="margin-top:0.5rem;display:none;gap:0.5rem;align-items:center;">
                <div style="flex:1;height:8px;background:rgba(var(--color-fg),0.12);border-radius:9999px;overflow:hidden;">
                  <div id="upload_bar_create" style="width:0%;height:100%;background:linear-gradient(90deg,#8b5cf6,#a78bfa);transition:width .15s ease;"></div>
                </div>
                <span id="upload_pct_create" style="min-width:3rem;text-align:right;font-weight:600;">0%</span>
              </div>
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title"><?= e(t('technical_data')) ?></h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <label>
              <span><?= e(t('engine_code')) ?></span>
              <input type="text" name="engine_code" value="<?= e($values['engine_code'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('fuel_type')) ?></span>
              <select name="fuel_type">
                <?php $ft = $values['fuel_type'] ?? 'petrol'; ?>
                <option value="petrol" <?= $ft==='petrol'?'selected':'' ?>><?= e(t('fuel_petrol')) ?></option>
                <option value="diesel" <?= $ft==='diesel'?'selected':'' ?>><?= e(t('fuel_diesel')) ?></option>
                <option value="electric" <?= $ft==='electric'?'selected':'' ?>><?= e(t('fuel_electric')) ?></option>
                <option value="hybrid" <?= $ft==='hybrid'?'selected':'' ?>><?= e(t('fuel_hybrid')) ?></option>
                <option value="lpg" <?= $ft==='lpg'?'selected':'' ?>><?= e(t('fuel_lpg')) ?></option>
                <option value="cng" <?= $ft==='cng'?'selected':'' ?>><?= e(t('fuel_cng')) ?></option>
                <option value="hydrogen" <?= $ft==='hydrogen'?'selected':'' ?>><?= e(t('fuel_hydrogen')) ?></option>
                <option value="other" <?= $ft==='other'?'selected':'' ?>><?= e(t('fuel_other')) ?></option>
              </select>
            </label>
            <label>
              <span><?= e(t('odometer_km')) ?></span>
              <input type="number" name="odometer_km" inputmode="numeric" min="0" step="1" value="<?= e($values['odometer_km'] ?? '') ?>">
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title"><?= e(t('purchase_details')) ?></h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <label>
              <span><?= e(t('purchase_date')) ?></span>
              <input type="date" name="purchase_date" value="<?= e($values['purchase_date'] ?? '') ?>">
            </label>
            <label>
              <span><?= e(t('purchase_price')) ?></span>
              <input type="text" name="purchase_price" inputmode="decimal" placeholder="<?= e(t('example_price')) ?>" value="<?= e($values['purchase_price'] ?? '') ?>">
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title"><?= e(t('additional_notes')) ?></h2>
          <label>
            <span><?= e(t('notes')) ?></span>
            <textarea name="notes" rows="5"><?= e($values['notes'] ?? '') ?></textarea>
          </label>
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <button type="submit" class="btn-primary"><?= e(t('save')) ?></button>
        </div>
      </form>
    </div>
  </main>
  <script>
    (function(){
      var form = document.querySelector('form[action="/vehicles/create"]');
      var submitBtn = form ? form.querySelector("button[type='submit']") : null;
      var file = document.querySelector("form input[name='profile_image']");
      var csrf = document.querySelector("form input[name='csrf']");
      var hidden = document.getElementById('profile_image_path_create');
      var wrap = document.getElementById('upload_wrap_create');
      var bar = document.getElementById('upload_bar_create');
      var pct = document.getElementById('upload_pct_create');
      var uploading = false;
      var pendingSubmit = false;
      if (form) {
        form.addEventListener('submit', function(e){
          if (uploading) {
            e.preventDefault();
            pendingSubmit = true;
            return false;
          }
        });
      }
      if (!file || !csrf) return;
      file.addEventListener('change', function(){
        if (!file.files || !file.files[0]) return;
        var fd = new FormData();
        fd.append('csrf', csrf.value);
        fd.append('file', file.files[0]);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/vehicles/upload');
        wrap.style.display = 'flex';
        bar.style.width = '0%'; pct.textContent = '0%';
        uploading = true;
        if (submitBtn) { submitBtn.disabled = true; submitBtn.dataset.originalText = submitBtn.textContent; submitBtn.textContent = '<?= e(t('uploading')) ?>'; }
        xhr.upload.onprogress = function(e){ if (e.lengthComputable){ var p = Math.round((e.loaded/e.total)*100); bar.style.width = p+'%'; pct.textContent = p+'%'; }};
        xhr.onreadystatechange = function(){ if (xhr.readyState===4){
            try{ var res = JSON.parse(xhr.responseText||'{}'); if (res.ok && res.path){ hidden.value = res.path; bar.style.width='100%'; pct.textContent='100%'; } }catch(_){}
            uploading = false;
            if (submitBtn) { submitBtn.disabled = false; if (submitBtn.dataset.originalText){ submitBtn.textContent = submitBtn.dataset.originalText; delete submitBtn.dataset.originalText; } }
            if (pendingSubmit && form){ pendingSubmit = false; form.submit(); }
        } };
        xhr.send(fd);
      });
    })();
  </script>
  <script>
    (function(){
      var fr = document.querySelector("input[name='first_registration']");
      if (!fr) return;
      fr.addEventListener('input', function(){
        var v = fr.value.replace(/[^0-9]/g,'');
        if (v.length > 8) v = v.slice(0,8);
        var parts = [];
        if (v.length >= 2) { parts.push(v.slice(0,2)); }
        if (v.length >= 4) { parts.push(v.slice(2,4)); }
        if (v.length > 4) { parts.push(v.slice(4)); }
        if (v.length < 2) { fr.value = v; return; }
        if (v.length >= 2 && v.length < 4) { fr.value = v.slice(0,2) + '.' + v.slice(2); return; }
        if (v.length >= 4 && v.length < 5) { fr.value = v.slice(0,2) + '.' + v.slice(2,4) + '.'; return; }
        fr.value = parts.join('.');
      });
    })();
  </script>
  <script>
    (function(){
      var vin = document.querySelector("input[name='vin']");
      if (!vin) return;
      vin.addEventListener('input', function(){
        var v = vin.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
        if (v.length > 17) v = v.slice(0,17);
        vin.value = v;
      });
    })();
  </script>
</body>
</html>
