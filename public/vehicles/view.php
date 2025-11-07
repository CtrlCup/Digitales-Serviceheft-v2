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
    header('Location: /overview');
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
  <title>Fahrzeugdetails - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ],
    'cta' => ['label' => 'Zur Übersicht', 'href' => '/overview']
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
          <h2 class="card-title">Fehler</h2>
          <p><?= e($error) ?></p>
          <a href="/overview" class="btn-secondary">Zur Übersicht</a>
        </div>
      <?php else: ?>
        <?php $notesVal = trim((string)($vehicle['notes'] ?? '')); $hasNotes = ($notesVal !== ''); ?>
        <div class="detail-wrap">
        <div class="details-grid<?= $hasNotes ? '' : ' single' ?>">
        <div class="card" style="position:relative; margin: 0 auto; max-width: 1100px;">
          <a href="/vehicles/edit?id=<?= e((string)$id) ?>" class="header-link-with-text" style="position:absolute; right: 0.5rem; top: -2.0rem; z-index: 5;">
            <span class="link-text">Bearbeiten</span>
          </a>
          <?php 
            $title = trim((string)($vehicle['make'] ?? '')) . ' ' . trim((string)($vehicle['model'] ?? ''));
            $img = !empty($vehicle['profile_image'] ?? null) ? (string)$vehicle['profile_image'] : '/assets/files/logo-light.svg';
            $pairs = [];
            if (!empty($vehicle['first_registration'])) { $ts = strtotime((string)$vehicle['first_registration']); $pairs['Erstzulassung'] = $ts ? date('d.m.Y', $ts) : (string)$vehicle['first_registration']; }
            if (!empty($vehicle['license_plate'])) { $pairs['Kennzeichen'] = (string)$vehicle['license_plate']; }
            if (!empty($vehicle['vin'])) { $pairs['VIN'] = (string)$vehicle['vin']; }
            if (!empty($vehicle['color'])) { $pairs['Farbe'] = (string)$vehicle['color']; }
            if (!empty($vehicle['engine_code'])) { $pairs['Motorcode'] = (string)$vehicle['engine_code']; }
            if (!empty($vehicle['fuel_type'])) { $pairs['Kraftstoff'] = (string)$vehicle['fuel_type']; }
            if (isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== '' && $vehicle['odometer_km'] !== null) { $pairs['Kilometerstand'] = number_format((int)$vehicle['odometer_km'], 0, ',', '.') . ' km'; }
            if (!empty($vehicle['purchase_date'])) { $pt = strtotime((string)$vehicle['purchase_date']); $pairs['Kaufdatum'] = $pt ? date('d.m.Y', $pt) : (string)$vehicle['purchase_date']; }
            if (isset($vehicle['purchase_price']) && $vehicle['purchase_price'] !== '' && $vehicle['purchase_price'] !== null) { $pairs['Kaufpreis'] = number_format((float)$vehicle['purchase_price'], 2, ',', '.') . ' €'; }
          ?>
          <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:0.75rem;">
            <img src="<?= e($img) ?>" alt="Fahrzeugbild" style="width:160px;height:120px;object-fit:cover;border-radius:12px;background:var(--bg-secondary);" onerror="this.onerror=null;this.src='/assets/files/logo-light.svg'">
            <div style="min-width:220px;flex:1;">
              <h2 class="card-title" style="margin-bottom:0.25rem;"><?= e($title) ?></h2>
              <?php if (!empty($vehicle['license_plate'])): ?>
                <div class="kv-val" style="opacity:0.9;">Kennzeichen: <?= e((string)$vehicle['license_plate']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="brief-grid">
            <?php foreach ($pairs as $k => $v): ?>
              <div>
                <strong><?= e((string)$k) ?>:</strong><br>
                <span class="kv-val"><?= e((string)$v) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($hasNotes): ?>
        <div class="card">
          <h2 class="card-title">Notizen</h2>
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
