<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /dashboard');
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
  <title><?= e(t('vehicles_details_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ],
    'cta' => ['label' => t('vehicles_back_to_overview'), 'href' => '/dashboard']
  ]); ?>
  <main class="page-content">
    <div class="container-wide reveal-enter">
      <style>
        /* Responsive Layout: Details + Notizen nebeneinander bei Platz */
        .detail-wrap { max-width: 1400px; margin: 0 auto; }
        @media (min-width: 1px) { .details-grid { display:grid; gap:1rem; grid-template-columns: 1fr; justify-content:center; } }
        /* ab 900px: linke Spalte flexibel, rechte (Notizen) begrenzt */
        @media (min-width: 900px) { .details-grid { grid-template-columns: 1fr minmax(300px, 420px); } }
        /* ab 1280px: mehr Breite links, Notes bleiben gedeckelt */
        @media (min-width: 1280px) { .details-grid { grid-template-columns: 1fr minmax(320px, 500px); } }
        /* Brief-Daten in Spalten umbrechen */
        .brief-grid { display:grid; grid-template-columns: 1fr; gap:0.75rem; }
        @media (min-width: 700px) { .brief-grid { grid-template-columns: repeat(2, minmax(220px,1fr)); } }
        @media (min-width: 1200px) { .brief-grid { grid-template-columns: repeat(3, minmax(240px,1fr)); } }
        .kv-val { color: rgb(var(--color-fg)); }
        /* Wenn nur eine Karte vorhanden ist (keine Notizen), Karte mittig halten */
        .details-grid.single { grid-template-columns: 1fr !important; justify-items: center; }
      </style>
      
      <?php if ($error): ?>
        <div class="card" style="border-color:var(--danger);color:var(--danger);">
          <h2 class="card-title"><?= e(t('error_title')) ?></h2>
          <p><?= e($error) ?></p>
          <a href="/dashboard" class="btn-secondary"><?= e(t('vehicles_back_to_overview')) ?></a>
        </div>
      <?php else: ?>
        <?php $notesVal = trim((string)($vehicle['notes'] ?? '')); $hasNotes = ($notesVal !== ''); ?>
        <div class="detail-wrap">
        <div class="actions-bar" style="display:flex;align-items:center;gap:0.5rem;justify-content:space-between;margin:0 auto 0.5rem auto;max-width:1100px;flex-wrap:wrap;">
          <a href="/vehicles/edit?id=<?= e((string)$id) ?>" class="btn-secondary" title="<?= e(t('edit')) ?>" style="flex:0 0 auto;">
            <?= icon_svg('edit') ?> <?= e(t('edit')) ?>
          </a>
          <a href="#" class="btn-secondary" title="<?= e(t('add_service')) ?>" style="flex:0 0 auto; margin-left:auto;" onclick="window.open('/services/create?vehicle_id=<?= e((string)$id) ?>','_blank','noopener'); return false;">
            <?= icon_svg('plus') ?> <?= e(t('add_service')) ?>
          </a>
        </div>
        <div class="details-grid<?= $hasNotes ? '' : ' single' ?>">
        <div class="card" style="position:relative; margin: 0 auto; max-width: 1100px;">
          <?php 
            $title = trim((string)($vehicle['make'] ?? '')) . ' ' . trim((string)($vehicle['model'] ?? ''));
            $img = !empty($vehicle['profile_image'] ?? null) ? (string)$vehicle['profile_image'] : '/assets/files/vehicle-placeholder.svg';
            $hsnVal = trim((string)($vehicle['hsn'] ?? ''));
            $tsnVal = trim((string)($vehicle['tsn'] ?? ''));
            $hsnTsn = ($hsnVal !== '' || $tsnVal !== '') ? trim($hsnVal . ($hsnVal!=='' && $tsnVal!=='' ? '/' : '') . $tsnVal) : '';
            $vinVal = trim((string)($vehicle['vin'] ?? ''));
            $frVal = '';
            if (!empty($vehicle['first_registration'])) { $ts = strtotime((string)$vehicle['first_registration']); $frVal = $ts ? date('d.m.Y', $ts) : (string)$vehicle['first_registration']; }
            $colorVal = trim((string)($vehicle['color'] ?? ''));
            $fuelRaw = trim((string)($vehicle['fuel_type'] ?? ''));
            $fuelMap = [
              'petrol' => t('fuel_petrol'),
              'diesel' => t('fuel_diesel'),
              'electric' => t('fuel_electric'),
              'hybrid' => t('fuel_hybrid'),
              'lpg' => t('fuel_lpg'),
              'cng' => t('fuel_cng'),
              'hydrogen' => t('fuel_hydrogen'),
              'other' => t('fuel_other'),
            ];
            $fuelVal = $fuelRaw !== '' ? (string)($fuelMap[$fuelRaw] ?? $fuelRaw) : '';
            $engineCodeVal = trim((string)($vehicle['engine_code'] ?? ''));
            $kmVal = (isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== '' && $vehicle['odometer_km'] !== null)
              ? number_format((int)$vehicle['odometer_km'], 0, ',', '.') . ' km' : '';
            $lpVal = trim((string)($vehicle['license_plate'] ?? ''));
            $pdVal = '';
            if (!empty($vehicle['purchase_date'])) { $pt = strtotime((string)$vehicle['purchase_date']); $pdVal = $pt ? date('d.m.Y', $pt) : (string)$vehicle['purchase_date']; }
            $ppVal = (isset($vehicle['purchase_price']) && $vehicle['purchase_price'] !== '' && $vehicle['purchase_price'] !== null)
              ? number_format((float)$vehicle['purchase_price'], 2, ',', '.') . ' €' : '';
            $specs = [];
            if ($hsnTsn !== '') { $specs[] = ['label' => t('hsn') . '/' . t('tsn'), 'value' => $hsnTsn]; }
            if ($vinVal !== '') { $specs[] = ['label' => t('vin'), 'value' => $vinVal]; }
            if ($frVal !== '') { $specs[] = ['label' => t('first_registration'), 'value' => $frVal]; }
            if ($colorVal !== '') { $specs[] = ['label' => t('color'), 'value' => $colorVal]; }
            if ($fuelVal !== '') { $specs[] = ['label' => t('fuel_type'), 'value' => $fuelVal]; }
            if ($engineCodeVal !== '') { $specs[] = ['label' => t('engine_code'), 'value' => $engineCodeVal]; }
            if ($pdVal !== '') { $specs[] = ['label' => t('purchase_date'), 'value' => $pdVal]; }
            if ($ppVal !== '') { $specs[] = ['label' => t('purchase_price'), 'value' => $ppVal]; }
          ?>
          <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:0.75rem;">
            <img src="<?= e($img) ?>" alt="<?= e(t('vehicle_image_alt')) ?>" style="width:160px;height:120px;object-fit:cover;border-radius:12px;background:var(--bg-secondary);" onerror="this.onerror=null;this.src='/assets/files/vehicle-placeholder.svg'">
            <div style="min-width:220px;flex:1;">
              <h2 class="card-title" style="margin-bottom:0.25rem;"><?= e($title) ?></h2>
              <?php if ($lpVal !== ''): ?>
                <div class="kv-val" style="opacity:0.9;"><?= e(t('license_plate')) ?>: <?= e($lpVal) ?></div>
              <?php endif; ?>
              <?php if ($kmVal !== ''): ?>
                <div class="kv-val" style="opacity:0.9;"><?= e(t('odometer_km')) ?>: <?= e($kmVal) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <style>
            .spec-grid { display:grid; gap:0.75rem; grid-template-columns: 1fr; }
            @media (min-width: 700px) {
              .spec-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); }
            }
            @media (min-width: 1100px) {
              .spec-grid { grid-template-columns: repeat(3, minmax(220px, 1fr)); }
            }
            .spec-item-title { font-weight:600; opacity:0.9; }
          </style>
          <div class="spec-grid">
            <?php foreach ($specs as $it): ?>
              <div>
                <div class="spec-item-title"><?= e((string)$it['label']) ?></div>
                <div class="kv-val"><?= e((string)$it['value']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($hasNotes): ?>
        <div class="card">
          <h2 class="card-title"><?= e(t('notes')) ?></h2>
          <p><?= nl2br(e($notesVal)) ?></p>
        </div>
        <?php endif; ?>
        </div>
        </div>

      <?php endif; ?>
    </div>
  </main>
</body>
</html>
