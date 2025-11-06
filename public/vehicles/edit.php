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
    <div class="container reveal-enter">
      <a href="/vehicles/view?id=<?= e((string)$id) ?>" class="header-link-with-text" style="margin-bottom:1rem;">
        <?= icon_svg('arrow-left') ?>
        <span class="link-text">Zurück zur Detailansicht</span>
      </a>

      <div style="display:flex;justify-content:flex-end;margin-bottom:0.75rem;">
        <button type="button" class="btn-danger" id="open-delete-modal">Entfernen</button>
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

          <div class="card">
            <h2 class="card-title">Fahrzeugdaten</h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
              <label>
                <span>FIN (VIN)</span>
                <input type="text" name="vin" value="<?= e((array_key_exists('vin', $values) && $values['vin'] !== '') ? $values['vin'] : ($v['vin'] ?? '')) ?>">
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
                <span>Baujahr (YYYY)</span>
                <input type="number" name="year" inputmode="numeric" pattern="\d{4}" min="1900" max="2100" value="<?= e((array_key_exists('year', $values) && $values['year'] !== '') ? $values['year'] : ($v['year'] ?? '')) ?>">
              </label>
              <label>
                <span>Farbe</span>
                <input type="text" name="color" value="<?= e((array_key_exists('color', $values) && $values['color'] !== '') ? $values['color'] : ($v['color'] ?? '')) ?>">
              </label>
              <label>
                <span>Profilbild (JPG/PNG/WEBP, max. 5 MB) — leer lassen, um nicht zu ändern</span>
                <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
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
                <input type="number" name="odometer_km" inputmode="numeric" min="0" step="1" value="<?= e((array_key_exists('odometer_km', $values) && $values['odometer_km'] !== '') ? $values['odometer_km'] : ($v['odometer_km'] ?? '')) ?>">
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

          <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="btn-primary">Speichern</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </main>

  <div id="delete-modal" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:1000;">
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
      if (openBtn) openBtn.addEventListener('click', function(){ modal.style.display = 'flex'; setTimeout(function(){ input && input.focus(); }, 50); });
      if (cancelBtn) cancelBtn.addEventListener('click', function(){ modal.style.display = 'none'; if (input) { input.value=''; submitBtn.disabled=true; } });
      if (input) input.addEventListener('input', function(){ submitBtn.disabled = (input.value.trim() !== 'LÖSCHEN'); });
    })();
  </script>
</body>
</html>
