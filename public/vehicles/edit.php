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
    header('Location: /overview');
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
        $error = 'Fahrzeug wurde nicht gefunden.';
    }
} catch (Throwable $e) {
    $error = 'Fahrzeug konnte nicht geladen werden.';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fahrzeug bearbeiten - <?= e(APP_NAME) ?></title>
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
          <span class="link-text">Zurück zur Detailansicht</span>
        </a>
        <button type="button" id="open-delete-modal" class="header-link-with-text danger" onclick="(function(){var m=document.getElementById('delete-modal'); if(m){ m.style.display='flex'; var i=document.getElementById('delete-confirm-input'); if(i){ setTimeout(function(){ i.focus(); }, 30); } } })();">
          <span class="link-text">Entfernen</span>
        </button>
      </div>

      <?php if ($error): ?>
        <div class="card" style="border-color:var(--danger);color:var(--danger);">
          <h2 class="card-title">Fehler</h2>
          <p><?= e($error) ?></p>
          <a href="/overview" class="btn-secondary">Zur Übersicht</a>
        </div>
      <?php else: ?>
        <?php $v = $vehicle; ?>
        <?php if (!empty($errors)): ?>
          <div class="card" style="border-color:var(--danger);color:var(--danger);">
            <h2 class="card-title">Fehler</h2>
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
            <h2 class="card-title">Fahrzeugdaten</h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span>FIN (VIN)</span>
                <input type="text" name="vin" maxlength="17" pattern="[A-HJ-NPR-Z0-9]{1,17}" value="<?= e((array_key_exists('vin', $values) && $values['vin'] !== '') ? $values['vin'] : ($v['vin'] ?? '')) ?>">
              </label>
              <label>
                <span>Kennzeichen</span>
                <input type="text" name="license_plate" value="<?= e((array_key_exists('license_plate', $values) && $values['license_plate'] !== '') ? $values['license_plate'] : ($v['license_plate'] ?? '')) ?>">
              </label>
              <label>
                <span>Hersteller</span>
                <input type="text" name="make" required value="<?= e((array_key_exists('make', $values) && $values['make'] !== '') ? $values['make'] : ($v['make'] ?? '')) ?>">
              </label>
              <label>
                <span>Modell</span>
                <input type="text" name="model" required value="<?= e((array_key_exists('model', $values) && $values['model'] !== '') ? $values['model'] : ($v['model'] ?? '')) ?>">
              </label>
              <label>
                <span>Erstzulassung (TT.MM.JJJJ)</span>
                <?php 
                  $fr_prefill = '';
                  if (array_key_exists('first_registration', $values)) {
                    $fr_prefill = (string)$values['first_registration'];
                  } elseif (!empty($v['first_registration'] ?? '')) {
                    $ts = strtotime((string)$v['first_registration']);
                    $fr_prefill = $ts ? date('d.m.Y', $ts) : '';
                  }
                ?>
                <input type="text" name="first_registration" inputmode="numeric" placeholder="z.B. 23.06.1999" value="<?= e($fr_prefill) ?>">
              </label>
              <label>
                <span>Farbe</span>
                <input type="text" name="color" value="<?= e((array_key_exists('color', $values) && $values['color'] !== '') ? $values['color'] : ($v['color'] ?? '')) ?>">
              </label>
              <label>
                <span>Profilbild (JPG/PNG/WEBP, max. 5 MB) — leer lassen, um nicht zu ändern</span>
                <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                <input type="hidden" name="profile_image_path" id="profile_image_path_edit" value="">
                <input type="hidden" name="profile_image_delete" id="profile_image_delete" value="0">
                <?php if (!empty($v['profile_image'] ?? '')): ?>
                  <div id="current_image_wrap" style="display:flex;gap:0.75rem;align-items:flex-start;margin-top:0.5rem;">
                    <img id="current_image_preview" src="<?= e((string)$v['profile_image']) ?>" alt="Aktuelles Bild" style="width:120px;height:90px;object-fit:cover;border-radius:10px;background:var(--bg-secondary);" onerror="this.onerror=null;this.src='/assets/files/logo-light.svg'">
                    <button type="button" id="delete_image_btn" class="btn-danger" title="Aktuelles Bild löschen" style="white-space:nowrap;"><?= icon_svg('trash') ?> Bild löschen</button>
                  </div>
                <?php endif; ?>
                <div id="upload_wrap_edit" style="margin-top:0.5rem;display:none;gap:0.5rem;align-items:center;">
                  <div style="flex:1;height:8px;background:rgba(var(--color-fg),0.12);border-radius:9999px;overflow:hidden;">
                    <div id="upload_bar_edit" style="width:0%;height:100%;background:linear-gradient(90deg,#8b5cf6,#a78bfa);transition:width .15s ease;"></div>
                  </div>
                  <span id="upload_pct_edit" style="min-width:3rem;text-align:right;font-weight:600;">0%</span>
                </div>
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title">Technische Daten</h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span>Motorcode</span>
                <input type="text" name="engine_code" value="<?= e((array_key_exists('engine_code', $values) && $values['engine_code'] !== '') ? $values['engine_code'] : ($v['engine_code'] ?? '')) ?>">
              </label>
              <label>
                <span>Kraftstofftyp</span>
                <select name="fuel_type">
                  <?php $ft = (array_key_exists('fuel_type', $values) && $values['fuel_type'] !== '') ? $values['fuel_type'] : ($v['fuel_type'] ?? 'petrol'); ?>
                  <option value="petrol" <?= $ft==='petrol'?'selected':'' ?>>Benzin</option>
                  <option value="diesel" <?= $ft==='diesel'?'selected':'' ?>>Diesel</option>
                  <option value="electric" <?= $ft==='electric'?'selected':'' ?>>Elektrisch</option>
                  <option value="hybrid" <?= $ft==='hybrid'?'selected':'' ?>>Hybrid</option>
                  <option value="lpg" <?= $ft==='lpg'?'selected':'' ?>>LPG</option>
                  <option value="cng" <?= $ft==='cng'?'selected':'' ?>>CNG</option>
                  <option value="hydrogen" <?= $ft==='hydrogen'?'selected':'' ?>>Wasserstoff</option>
                  <option value="other" <?= $ft==='other'?'selected':'' ?>>Sonstiges</option>
                </select>
              </label>
              <label>
                <span>Kilometerstand</span>
                <input type="number" name="odometer_km" inputmode="numeric" min="0" step="1" autocomplete="off" placeholder="z.B. 125000" value="<?= e((array_key_exists('odometer_km', $values) && $values['odometer_km'] !== '') ? (string)$values['odometer_km'] : ((isset($v['odometer_km']) && $v['odometer_km'] !== null) ? (string)$v['odometer_km'] : '')) ?>">
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title">Kaufdetails</h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span>Kaufdatum</span>
                <input type="date" name="purchase_date" value="<?= e((array_key_exists('purchase_date', $values) && $values['purchase_date'] !== '') ? $values['purchase_date'] : ($v['purchase_date'] ?? '')) ?>">
              </label>
              <label>
                <span>Kaufpreis</span>
                <input type="text" name="purchase_price" inputmode="decimal" placeholder="z.B. 12500,00" value="<?= e((array_key_exists('purchase_price', $values) && $values['purchase_price'] !== '') ? $values['purchase_price'] : ($v['purchase_price'] ?? '')) ?>">
              </label>
            </div>
          </div>

          <div class="card">
            <h2 class="card-title">Zusätzliche Notizen</h2>
            <label>
              <span>Notizen</span>
              <textarea name="notes" rows="5"><?= e((array_key_exists('notes', $values) && $values['notes'] !== '') ? $values['notes'] : ($v['notes'] ?? '')) ?></textarea>
            </label>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-primary">Speichern</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <div id="delete-modal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:4000;">
    <div style="width:min(92vw,560px);background:var(--bg-card, #0b1220);color:rgb(var(--color-fg));border-radius:18px;padding:1.25rem;box-shadow:0 10px 30px rgba(0,0,0,0.35);">
      <h2 style="margin:0 0 0.5rem 0;color:#ef4444;">Bist du dir sicher?</h2>
      <p style="margin:0 0 0.75rem 0;">Diese Aktion löscht dieses Fahrzeug unwiderruflich. Bilddatei und alle zugehörigen Einträge werden entfernt.</p>
      <p style="margin:0 0 0.5rem 0;">Gib zur Bestätigung <strong>LÖSCHEN</strong> ein:</p>
      <form method="post" action="/vehicles/delete" id="delete-form" style="display:flex;flex-direction:column;gap:0.75rem;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <input type="text" id="delete-confirm-input" placeholder="LÖSCHEN" style="padding:0.65rem;border-radius:10px;border:1px solid rgba(var(--color-border),0.5);background:rgba(var(--color-fg),0.06);color:rgb(var(--color-fg));">
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:flex-end;">
          <button type="button" class="btn-secondary" id="cancel-delete">Abbrechen</button>
          <button type="submit" class="btn-danger" id="confirm-delete" disabled>Fahrzeug endgültig löschen</button>
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
      if (openBtn) openBtn.addEventListener('click', function(ev){ ev.preventDefault(); modal.style.display = 'flex'; setTimeout(function(){ input && input.focus(); }, 50); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ modal.style.display = 'none'; if (input) { input.value=''; submitBtn.disabled=true; } });
      if (input) input.addEventListener('input', function(){ submitBtn.disabled = (input.value.trim() !== 'LÖSCHEN'); });
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
        var ok = confirm('Änderungen wurden noch nicht gespeichert. Seite wirklich verlassen?');
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
        var ok = confirm('Aktuelles Bild wirklich löschen?');
        if (!ok) return;
        delField.value = '1';
        if (wrap) { wrap.style.opacity = '0.6'; delBtn.disabled = true; delBtn.textContent = 'Wird beim Speichern gelöscht'; }
      });
    })();
  </script>
  <script>
    (function(){
      var vin = document.querySelector("input[name='vin']");
      if (!vin) return;
      vin.addEventListener('input', function(){
        var v = vin.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
        v = v.replace(/[IOQ]/g,'');
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
      var file = form.querySelector("input[name='profile_image']");
      var csrf = form.querySelector("input[name='csrf']");
      var hidden = document.getElementById('profile_image_path_edit');
      var wrap = document.getElementById('upload_wrap_edit');
      var bar = document.getElementById('upload_bar_edit');
      var pct = document.getElementById('upload_pct_edit');
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
        xhr.upload.onprogress = function(e){ if (e.lengthComputable){ var p = Math.round((e.loaded/e.total)*100); bar.style.width = p+'%'; pct.textContent = p+'%'; }};
        xhr.onreadystatechange = function(){ if (xhr.readyState===4){ try{ var res = JSON.parse(xhr.responseText||'{}'); if (res.ok && res.path){ hidden.value = res.path; bar.style.width='100%'; pct.textContent='100%'; } }catch(_){} } };
        xhr.send(fd);
      });
    })();
  </script>
</body>
</html>
