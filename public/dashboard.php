<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Redirect to login if not authenticated
if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
// Load vehicles for current user
$vehicles = [];
$vehicleError = null;
try {
    $vdb = vehicle_db();
    // Ensure table exists (lightweight safety)
    $vdb->exec('CREATE TABLE IF NOT EXISTS vehicles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        vin VARCHAR(64) NULL,
        license_plate VARCHAR(32) NULL,
        make VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        trim_level VARCHAR(100) NULL,
        year SMALLINT UNSIGNED NULL,
        engine_code VARCHAR(64) NULL,
        fuel_type ENUM(\'petrol\',\'diesel\',\'electric\',\'hybrid\',\'lpg\',\'cng\',\'hydrogen\',\'other\') NOT NULL DEFAULT \'petrol\',
        color VARCHAR(64) NULL,
        odometer_km INT UNSIGNED NULL,
        odometer_unit ENUM(\'km\',\'mi\') NOT NULL DEFAULT \'km\',
        profile_image VARCHAR(512) NULL,
        purchase_date DATE NULL,
        purchase_price DECIMAL(10,2) NULL,
        purchase_mileage_km INT UNSIGNED NULL,
        sale_date DATE NULL,
        sale_price DECIMAL(10,2) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vehicles_vin (vin),
        KEY idx_vehicles_license_plate (license_plate),
        KEY idx_vehicles_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $uid = (int)($user['id'] ?? 0);
    try {
        // Try with optional profile_image column
        $sql = 'SELECT id, make, model, year, license_plate, profile_image FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
        $stmt = $vdb->prepare($sql);
        $stmt->execute([$uid, $uid]);
        $vehicles = $stmt->fetchAll() ?: [];
    } catch (Throwable $inner) {
        // If unknown column error, retry without profile_image
        if ($inner instanceof PDOException && $inner->getCode() === '42S22') {
            $sql = 'SELECT id, make, model, year, license_plate FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
            $stmt = $vdb->prepare($sql);
            $stmt->execute([$uid, $uid]);
            $vehicles = $stmt->fetchAll() ?: [];
        } else {
            throw $inner;
        }
    }
    // If there is at least one vehicle, show the exact same page as overview
    if (is_array($vehicles) && count($vehicles) > 0) {
        header('Location: /overview');
        exit;
    }
} catch (Throwable $e) {
    error_log('Dashboard vehicles load failed: ' . $e->getMessage());
    $vehicleError = 'Fahrzeugdaten konnten nicht geladen werden.';
}
?><!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>" class="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('page_title_dashboard')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php 
    $showCta = is_array($vehicles) && count($vehicles) === 0;
    render_brand_header([
      'links' => [
        ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
        ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
      ],
      'cta' => $showCta ? ['label' => 'Neues Auto anlegen', 'href' => '/vehicles/create'] : null,
    ]); 
  ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <?php $hasVehicles = count($vehicles) > 0; ?>

      <?php if ($vehicleError): ?>
        <div class="card" style="border-color: var(--danger);">
          <h2 class="card-title">Hinweis</h2>
          <p><?= e($vehicleError) ?></p>
        </div>
      <?php else: ?>
        <?php if (!$hasVehicles): ?>
          <div class="card">
            <h2 class="card-title"><?= e(get_time_based_greeting()) ?>, <?= e($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'User'))) ?></h2>
            <p>Hier wird später dein digitales Serviceheft erscheinen.</p>
            <p style="margin-top:1rem;">Aktuell hast du kein Fahrzeug angelegt</p>
            <p>Lege jetzt dein erstes Auto an, um Wartungen und Einträge zu verwalten.</p>
            <a href="/vehicles/create" class="btn-primary">Neues Auto anlegen</a>
          </div>
        <?php else: ?>
          <div class="card">
            <h2 class="card-title"><?= e(get_time_based_greeting()) ?>, <?= e($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'User'))) ?></h2>
            <p><?= e(t('dashboard_intro')) ?></p>
          </div>
          <div class="card">
            <h2 class="card-title">Deine Fahrzeuge</h2>
            <style>
              @media (min-width: 1px) { .vehicle-tiles { display:grid; gap:1rem; grid-template-columns: 1fr; } }
              @media (min-width: 640px) { .vehicle-tiles { grid-template-columns: repeat(2, minmax(0,1fr)); } }
              @media (min-width: 1024px) { .vehicle-tiles { grid-template-columns: repeat(3, minmax(0,1fr)); } }
              .vehicle-tile { display:flex; gap:0.75rem; align-items:stretch; text-decoration:none; }
              .vehicle-thumb { width:96px; height:72px; border-radius:8px; object-fit:cover; background:var(--bg-secondary); flex-shrink:0; }
              .vehicle-meta { display:flex; flex-direction:column; gap:0.25rem; overflow:hidden; }
              .vehicle-title { font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color: rgb(var(--color-fg)); }
              .vehicle-sub { color: rgba(var(--color-fg), 0.8); font-size:0.95rem; }
            </style>
            <div class="vehicle-tiles">
              <?php foreach ($vehicles as $v): ?>
                <a href="/vehicles/view?id=<?= e((string)$v['id']) ?>" class="tile card vehicle-tile">
                  <?php $img = (!empty($v['profile_image'] ?? null)) ? $v['profile_image'] : '/assets/files/vehicle-placeholder.svg'; ?>
                  <img src="<?= e($img) ?>" alt="Fahrzeugbild" class="vehicle-thumb" onerror="this.onerror=null;this.src='/assets/files/vehicle-placeholder.svg'">
                  <div class="vehicle-meta">
                    <div class="vehicle-title">
                      <?= e(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?>
                    </div>
                    <div class="vehicle-sub">
                      <?= e(($v['year'] ? (string)$v['year'] : '')) ?> <?= e($v['license_plate'] ? '· ' . $v['license_plate'] : '') ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
