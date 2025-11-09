<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header('Location: /dashboard');
    exit;
}

$errors = $_SESSION['form_errors'] ?? [];
$values = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../../src/vehicles/update_vehicle.php';
    exit;
}

$vehicle = null;
$error = null;
try {
    $pdo = vehicle_db();
    $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1');
    $stmt->execute([$id, $uid, $uid]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        $error = t('vehicle_not_found');
    }
} catch (Throwable $e) {
    $error = t('vehicle_load_failed');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('vehicle_edit_title')) ?> - <?= e(APP_NAME) ?></title>
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
    <div class="container-wide reveal-enter">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;gap:0.5rem;">
        <a href="/vehicles/view?id=<?= e((string)$id) ?>" class="header-link-with-text">
          <?= icon_svg('arrow-left') ?>
          <span class="link-text"><?= e(t('vehicles_back_to_detail')) ?></span>
        </a>
        <button type="button" id="open-delete-modal" class="header-link-with-text danger" onclick="(function(){var m=document.getElementById('delete-modal'); if(m){ m.style.display='flex'; var i=document.getElementById('delete-confirm-input'); if(i){ setTimeout(function(){ i.focus(); }, 30); } } })();">
          <span class="link-text"><?= e(t('remove')) ?></span>
        </button>
      </div>

      <?php if ($error): ?>
        <div class="card" style="border-color:var(--danger);color:var(--danger);">
          <h2 class="card-title"><?= e(t('error_title')) ?></h2>
          <p><?= e($error) ?></p>
          <a href="/dashboard" class="btn-secondary"><?= e(t('vehicles_back_to_overview')) ?></a>
        </div>
      <?php else: ?>
        <?php $v = $vehicle; ?>
        <?php if (!empty($errors)): ?>
          <div class="card" style="border-color:var(--danger);color:var(--danger);">
            <h2 class="card-title"><?= e(t('error_title')) ?></h2>
            <ul style="margin:0;padding-left:1.2rem;">
              <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="/vehicles/edit?id=<?= e((string)$id) ?>" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= e((string)$id) ?>">
          <style>
            .cards-grid { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items:start; }
            @media (max-width: 420px) { .cards-grid { grid-template-columns: 1fr; } }
            .cards-grid .card { height: 100%; }
            /* Ensure the buttons row is above any overlapping content */
            .form-actions { position: sticky; bottom: 0.5rem; z-index: 20; display:flex; gap:0.5rem; justify-content:flex-start; padding-top: 0.25rem; }
            .form-actions .btn-primary { width: auto; min-width: 180px; margin-top: 0; }
            .container-wide { padding-bottom: 3.5rem; }
            /* Keep odometer input interactable above neighbors */
            input[name='odometer_km'] { position: relative; z-index: 5; }
          </style>
          
          <div class="cards-grid">
          <div class="card">
            <h2 class="card-title"><?= e(t('vehicle_data')) ?></h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <div style="display:grid;grid-template-columns:repeat(2,minmax(120px,1fr));gap:0.75rem;">
                <label>
                  <span><?= e(t('hsn')) ?></span>
                  <input type="text" name="hsn" inputmode="numeric" pattern="\\d{1,4}" maxlength="4" value="<?= e((array_key_exists('hsn', $values) && $values['hsn'] !== '') ? $values['hsn'] : ($v['hsn'] ?? '')) ?>">
                </label>
                <label>
                  <span><?= e(t('tsn')) ?></span>
                  <input type="text" name="tsn" pattern="[A-Za-z0-9]{1,4}" maxlength="4" value="<?= e((array_key_exists('tsn', $values) && $values['tsn'] !== '') ? $values['tsn'] : ($v['tsn'] ?? '')) ?>" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,4)">
                </label>
              </div>
              <label>
                <span><?= e(t('vin')) ?></span>
                <input type="text" name="vin" maxlength="17" pattern="[A-Z0-9]{1,17}" value="<?= e((array_key_exists('vin', $values) && $values['vin'] !== '') ? $values['vin'] : ($v['vin'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('license_plate')) ?></span>
                <input type="text" name="license_plate" value="<?= e((array_key_exists('license_plate', $values) && $values['license_plate'] !== '') ? $values['license_plate'] : ($v['license_plate'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('make')) ?></span>
                <input type="text" name="make" required value="<?= e((array_key_exists('make', $values) && $values['make'] !== '') ? $values['make'] : ($v['make'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('model')) ?></span>
                <input type="text" name="model" required value="<?= e((array_key_exists('model', $values) && $values['model'] !== '') ? $values['model'] : ($v['model'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('first_registration_with_format')) ?></span>
                <?php 
                  $fr_prefill = '';
                  if (array_key_exists('first_registration', $values)) {
                    $fr_prefill = (string)$values['first_registration'];
                  } elseif (!empty($v['first_registration'] ?? '')) {
                    $ts = strtotime((string)$v['first_registration']);
                    $fr_prefill = $ts ? date('d.m.Y', $ts) : '';
                  }
                ?>
                <input type="text" name="first_registration" inputmode="numeric" placeholder="<?= e(t('example_date_de')) ?>" value="<?= e($fr_prefill) ?>">
              </label>
              <label>
                <span><?= e(t('color')) ?></span>
                <input type="text" name="color" value="<?= e((array_key_exists('color', $values) && $values['color'] !== '') ? $values['color'] : ($v['color'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('profile_image_hint')) ?></span>
                <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                <input type="hidden" name="profile_image_path" id="profile_image_path_edit" value="">
                <input type="hidden" name="profile_image_delete" id="profile_image_delete" value="0">
                <?php if (!empty($v['profile_image'] ?? '')): ?>
                  <div id="current_image_wrap" style="display:flex;gap:0.75rem;align-items:flex-start;margin-top:0.5rem;">
                    <img id="current_image_preview" src="<?= e((string)$v['profile_image']) ?>" alt="<?= e(t('current_image_alt')) ?>" style="width:120px;height:90px;object-fit:cover;border-radius:10px;background:var(--bg-secondary);" onerror="this.onerror=null;this.src='/assets/files/logo-light.svg'">
                  </div>
                <?php endif; ?>
                <div id="upload_wrap_edit" style="margin-top:0.5rem;display:none;gap:0.5rem;align-items:center;">
                  <div style="flex:1;height:8px;background:rgba(var(--color-fg),0.12);border-radius:9999px;overflow:hidden;">
                    <div id="upload_bar_edit" style="width:0%;height:100%;background:linear-gradient(90deg,#8b5cf6,#a78bfa);transition:width .15s ease;"></div>
                  </div>
                  <span id="upload_pct_edit" style="min-width:3rem;text-align:right;font-weight:600;">0%</span>
                </div>
                <?php if (!empty($v['profile_image'] ?? '')): ?>
                  <div style="display:flex;justify-content:flex-start;margin-top:0.5rem;">
                    <button type="button" id="delete_image_btn" class="btn-danger" title="<?= e(t('delete_current_image')) ?>">
                      <?= icon_svg('trash') ?> <?= e(t('delete_image')) ?>
                    </button>
                  </div>
                <?php endif; ?>
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title"><?= e(t('technical_data')) ?></h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span><?= e(t('engine_code')) ?></span>
                <input type="text" name="engine_code" value="<?= e((array_key_exists('engine_code', $values) && $values['engine_code'] !== '') ? $values['engine_code'] : ($v['engine_code'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('fuel_type')) ?></span>
                <select name="fuel_type">
                  <?php $ft = (array_key_exists('fuel_type', $values) && $values['fuel_type'] !== '') ? $values['fuel_type'] : ($v['fuel_type'] ?? 'petrol'); ?>
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
                <input type="number" name="odometer_km" inputmode="numeric" min="0" step="1" autocomplete="off" placeholder="<?= e(t('example_odometer')) ?>" value="<?= e((array_key_exists('odometer_km', $values) && $values['odometer_km'] !== '') ? (string)$values['odometer_km'] : ((isset($v['odometer_km']) && $v['odometer_km'] !== null) ? (string)$v['odometer_km'] : '')) ?>">
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title"><?= e(t('purchase_details')) ?></h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span><?= e(t('purchase_date')) ?></span>
                <input type="date" name="purchase_date" value="<?= e((array_key_exists('purchase_date', $values) && $values['purchase_date'] !== '') ? $values['purchase_date'] : ($v['purchase_date'] ?? '')) ?>">
              </label>
              <label>
                <span><?= e(t('purchase_price')) ?></span>
                <input type="text" name="purchase_price" inputmode="decimal" placeholder="z.B. 12500,00" value="<?= e((array_key_exists('purchase_price', $values) && $values['purchase_price'] !== '') ? $values['purchase_price'] : ($v['purchase_price'] ?? '')) ?>">
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title"><?= e(t('additional_notes')) ?></h2>
            <label>
              <span><?= e(t('notes')) ?></span>
              <textarea name="notes" rows="5"><?= e((array_key_exists('notes', $values) && $values['notes'] !== '') ? $values['notes'] : ($v['notes'] ?? '')) ?></textarea>
            </label>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary"><?= e(t('save')) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <div id="delete-modal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:4000;">
    <div style="width:min(92vw,560px);background:rgb(var(--color-bg));color:rgb(var(--color-fg));border:1px solid rgba(var(--color-border),0.8);border-radius:18px;padding:1.25rem;box-shadow:0 10px 30px rgba(0,0,0,0.18);">
      <h2 style="margin:0 0 0.5rem 0;color:#ef4444;"><?= e(t('confirm_title')) ?></h2>
      <p style="margin:0 0 0.75rem 0;"><?= e(t('vehicle_delete_warning')) ?></p>
      <p style="margin:0 0 0.5rem 0;"><?= e(t('vehicle_delete_instruction_prefix')) ?> <strong><?= e((string)($v['license_plate'] ?? '')) ?: 'LÖSCHEN' ?></strong> <?= e(t('vehicle_delete_instruction_suffix')) ?></p>
      <form method="post" action="/vehicles/delete" id="delete-form" style="display:flex;flex-direction:column;gap:0.75rem;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <input type="text" id="delete-confirm-input" placeholder="<?= e((string)($v['license_plate'] ?? '')) ?: 'LÖSCHEN' ?>" style="padding:0.65rem;border-radius:10px;border:1px solid rgba(var(--color-border),0.5);background:rgb(var(--color-bg));color:rgb(var(--color-fg));">
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
          <button type="button" class="btn-secondary" id="cancel-delete"><?= e(t('cancel')) ?></button>
          <button type="submit" class="btn-danger" id="confirm-delete" disabled><?= e(t('vehicle_delete_confirm_button')) ?></button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function(){
      var openBtn = document.getElementById('open-delete-modal');
      var modal = document.getElementById('delete-modal');
      var cancelBtn = document.getElementById('cancel-delete');
      var input = document.getElementById('delete-confirm-input');
      var submitBtn = document.getElementById('confirm-delete');
      var requiredPlate = <?php echo json_encode((string)($v['license_plate'] ?? '')); ?>;
      if (!requiredPlate) { requiredPlate = 'LÖSCHEN'; }
      if (openBtn) openBtn.addEventListener('click', function(ev){ ev.preventDefault(); modal.style.display = 'flex'; setTimeout(function(){ input && input.focus(); }, 50); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ modal.style.display = 'none'; if (input) { input.value=''; submitBtn.disabled=true; } });
      if (input) input.addEventListener('input', function(){ submitBtn.disabled = (input.value.trim() !== requiredPlate); });
    })();
  </script>
  <script>
    // Warnung bei ungespeicherten Änderungen
    (function(){
      var form = document.querySelector('form[action^="/vehicles/edit"]');
      if (!form) return;
      var dirty = false, submitting = false;
      form.addEventListener('input', function(){ dirty = true; }, {capture:true});
      form.addEventListener('change', function(){ dirty = true; }, {capture:true});
      form.addEventListener('submit', function(){ submitting = true; dirty = false; });
      var back = document.querySelector('a.header-link-with-text[href^="/vehicles/view"]');
      function confirmLeave(ev){
        if (!dirty) return;
        var ok = confirm('<?= e(t('unsaved_changes_leave_prompt')) ?>');
        if (!ok){ ev.preventDefault(); return false; }
      }
      if (back) back.addEventListener('click', confirmLeave);
      window.addEventListener('beforeunload', function(e){ if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; } });
      // Wenn erfolgreich gespeichert wird (Server redirect), verhindert beforeunload ohnehin den Prompt nicht.
    })();
  </script>
  <script>
    // Bild löschen Button
    (function(){
      var delBtn = document.getElementById('delete_image_btn');
      var delField = document.getElementById('profile_image_delete');
      var wrap = document.getElementById('current_image_wrap');
      if (!delBtn || !delField) return;
      delBtn.addEventListener('click', function(){
        var ok = confirm('<?= e(t('confirm_delete_current_image')) ?>');
        if (!ok) return;
        delField.value = '1';
        if (wrap) { wrap.style.opacity = '0.6'; delBtn.disabled = true; delBtn.textContent = '<?= e(t('image_will_be_deleted_on_save')) ?>'; }
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
  <script>
    (function(){
      var fr = document.querySelector("input[name='first_registration']");
      if (!fr) return;
      fr.addEventListener('input', function(){
        var v = fr.value.replace(/[^0-9]/g,'');
        if (v.length > 8) v = v.slice(0,8);
        if (v.length < 3) { fr.value = v; return; }
        if (v.length < 5) { fr.value = v.slice(0,2) + '.' + v.slice(2); return; }
        fr.value = v.slice(0,2) + '.' + v.slice(2,4) + '.' + v.slice(4);
      });
    })();
  </script>
  <script>
    (function(){
      var form = document.querySelector('form[action^="/vehicles/edit"]');
      if (!form) return;
      var submitBtn = form.querySelector("button[type='submit']");
      var file = form.querySelector("input[name='profile_image']");
      var csrf = form.querySelector("input[name='csrf']");
      var hidden = document.getElementById('profile_image_path_edit');
      var wrap = document.getElementById('upload_wrap_edit');
      var bar = document.getElementById('upload_bar_edit');
      var pct = document.getElementById('upload_pct_edit');
      var uploading = false;
      var pendingSubmit = false;
      form.addEventListener('submit', function(e){
        if (uploading) {
          e.preventDefault();
          pendingSubmit = true;
          return false;
        }
      });
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
            try{ var res = JSON.parse(xhr.responseText||'{}'); if (res.ok && res.path){ hidden.value = res.path; bar.style.width='100%'; pct.textContent='100%'; } }catch(_){ }
            uploading = false;
            if (submitBtn) { submitBtn.disabled = false; if (submitBtn.dataset.originalText){ submitBtn.textContent = submitBtn.dataset.originalText; delete submitBtn.dataset.originalText; } }
            if (pendingSubmit) { pendingSubmit = false; form.submit(); }
        } };
        xhr.send(fd);
      });
    })();
  </script>
</body>
</html>
