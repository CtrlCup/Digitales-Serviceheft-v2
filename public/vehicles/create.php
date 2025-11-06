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
  <title>Neues Fahrzeug anlegen - <?= e(APP_NAME) ?></title>
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
      <a href="/overview" class="header-link-with-text" style="margin-bottom:1rem;">
        <?= icon_svg('arrow-left') ?>
        <span class="link-text">Zurück zur Übersicht</span>
      </a>

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

      <form method="post" action="/vehicles/create" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="card">
          <h2 class="card-title">Fahrzeugdaten</h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <label>
              <span>FIN (VIN)</span>
              <input type="text" name="vin" value="<?= e($values['vin'] ?? '') ?>">
            </label>
            <label>
              <span>Kennzeichen</span>
              <input type="text" name="license_plate" value="<?= e($values['license_plate'] ?? '') ?>">
            </label>
            <label>
              <span>Hersteller</span>
              <input type="text" name="make" required value="<?= e($values['make'] ?? '') ?>">
            </label>
            <label>
              <span>Modell</span>
              <input type="text" name="model" required value="<?= e($values['model'] ?? '') ?>">
            </label>
            <label>
              <span>Baujahr (YYYY)</span>
              <input type="number" name="year" inputmode="numeric" pattern="\d{4}" min="1900" max="2100" value="<?= e($values['year'] ?? '') ?>">
            </label>
            <label>
              <span>Farbe</span>
              <input type="text" name="color" value="<?= e($values['color'] ?? '') ?>">
            </label>
            <label>
              <span>Profilbild (JPG/PNG/WEBP, max. 5 MB)</span>
              <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title">Technische Daten</h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <label>
              <span>Motorcode</span>
              <input type="text" name="engine_code" value="<?= e($values['engine_code'] ?? '') ?>">
            </label>
            <label>
              <span>Kraftstofftyp</span>
              <select name="fuel_type">
                <?php $ft = $values['fuel_type'] ?? 'petrol'; ?>
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
              <input type="number" name="odometer_km" inputmode="numeric" min="0" step="1" value="<?= e($values['odometer_km'] ?? '') ?>">
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title">Kaufdetails</h2>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <label>
              <span>Kaufdatum</span>
              <input type="date" name="purchase_date" value="<?= e($values['purchase_date'] ?? '') ?>">
            </label>
            <label>
              <span>Kaufpreis</span>
              <input type="text" name="purchase_price" inputmode="decimal" placeholder="z.B. 12500,00" value="<?= e($values['purchase_price'] ?? '') ?>">
            </label>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title">Zusätzliche Notizen</h2>
          <label>
            <span>Notizen</span>
            <textarea name="notes" rows="5"><?= e($values['notes'] ?? '') ?></textarea>
          </label>
        </div>

        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <button type="submit" class="btn-primary">Speichern</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
