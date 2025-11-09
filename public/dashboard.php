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
        // Try full set incl. VIN/HSN/TSN and optional profile_image
        $sql = 'SELECT id, make, model, year, vin, hsn, tsn, license_plate, color, odometer_km, profile_image FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
        $stmt = $vdb->prepare($sql);
        $stmt->execute([$uid, $uid]);
        $vehicles = $stmt->fetchAll() ?: [];
    } catch (Throwable $inner) {
        if ($inner instanceof PDOException && $inner->getCode() === '42S22') {
            try {
                // Fallback: without profile_image but with VIN/HSN/TSN
                $sql = 'SELECT id, make, model, year, vin, hsn, tsn, license_plate, color, odometer_km FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
                $stmt = $vdb->prepare($sql);
                $stmt->execute([$uid, $uid]);
                $vehicles = $stmt->fetchAll() ?: [];
            } catch (Throwable $inner2) {
                if ($inner2 instanceof PDOException && $inner2->getCode() === '42S22') {
                    // Final fallback: legacy minimal set (no hsn/tsn, no profile_image)
                    $sql = 'SELECT id, make, model, year, vin, license_plate, color, odometer_km FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
                    $stmt = $vdb->prepare($sql);
                    $stmt->execute([$uid, $uid]);
                    $vehicles = $stmt->fetchAll() ?: [];
                } else {
                    throw $inner2;
                }
            }
        } else {
            throw $inner;
        }
    }
    
} catch (Throwable $e) {
    error_log('Dashboard vehicles load failed: ' . $e->getMessage());
    $vehicleError = t('dashboard_vehicles_load_failed');
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
    render_brand_header([
      'links' => [
        ['label' => t('vehicles_create_cta'), 'href' => '/vehicles/create', 'icon' => 'car-plus', 'text' => t('vehicles_create_cta')],
        ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
        ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
      ],
    ]); 
  ?>
  <main class="page-content">
    <div class="container reveal-enter">

      <?php $hasVehicles = count($vehicles) > 0; ?>

      <?php if ($vehicleError): ?>
        <div class="card" style="border-color: var(--danger);">
          <h2 class="card-title"><?= e(t('notice_title')) ?></h2>
          <p><?= e($vehicleError) ?></p>
        </div>
      <?php else: ?>
        <?php if (!$hasVehicles): ?>
          <div class="card">
            <h2 class="card-title"><?= e(get_time_based_greeting()) ?>, <?= e($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'User'))) ?></h2>
            <p><?= e(t('dashboard_intro')) ?></p>
            <p style="margin-top:1rem;"><?= e(t('no_vehicles_yet')) ?></p>
            <p><?= e(t('create_first_vehicle_hint')) ?></p>
            <a href="/vehicles/create" class="btn-primary"><?= e(t('vehicles_create_cta')) ?></a>
            </div>
        <?php else: ?>
          <div class="card">
            <h2 class="card-title"><?= e(get_time_based_greeting()) ?>, <?= e($user['name'] ?? ($user['username'] ?? ($user['email'] ?? 'User'))) ?></h2>
            <h3 class="card-title" style="font-size:1.05rem;margin-top:0.25rem;opacity:0.9;"><?= e(t('your_vehicles')) ?>: </h3>
            <style>
              .vehicle-tiles { 
                display: grid; gap: 1rem; 
                grid-template-columns: repeat(auto-fit, minmax(260px, 560px));
                justify-content: center; /* center the grid tracks horizontally */
                justify-items: center;   /* center tile within its track */
              }
              .vehicle-tile { 
                display:flex; gap:0.875rem; align-items:center; text-decoration:none; 
                min-height: 120px; padding: 0.5rem; border-radius: 12px; max-width: 560px;
              }
              .vehicle-thumb { 
                width: 120px; height: 90px; border-radius:12px; 
                object-fit: cover; object-position: center; 
                background: var(--bg-secondary); flex-shrink:0; 
              }
              .vehicle-meta { display:flex; flex-direction:column; gap:0.3rem; overflow:hidden; }
              .vehicle-title { font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color: rgb(var(--color-fg)); }
              .vehicle-sub { color: rgba(var(--color-fg), 0.8); font-size:0.95rem; }
              .vehicle-sub2 { color: rgba(var(--color-fg), 0.7); font-size:0.9rem; }
              .vehicle-tile, .vehicle-tile * { color: rgb(var(--color-fg)) !important; }
            </style>
            <div class="vehicle-tiles">
              <?php foreach ($vehicles as $v): ?>
                <a href="/vehicles/view?id=<?= e((string)$v['id']) ?>" class="tile card vehicle-tile">
                  <?php $img = (!empty($v['profile_image'] ?? null)) ? $v['profile_image'] : '/assets/files/vehicle-placeholder.svg'; ?>
                  <img src="<?= e($img) ?>" alt="<?= e(t('vehicle_image_alt')) ?>" class="vehicle-thumb" onerror="this.onerror=null;this.src='/assets/files/vehicle-placeholder.svg'">
                  <div class="vehicle-meta">
                    <!-- Zeile 1: Marke Modell -->
                    <div class="vehicle-title">
                      <?php 
                        $title = trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''));
                        echo e($title !== '' ? $title : t('vehicle'));
                      ?>
                    </div>
                    <!-- Zeile 2: Kennzeichen · KM-Stand (mit Labels) -->
                    <div class="vehicle-sub">
                      <?php 
                        $km = isset($v['odometer_km']) && $v['odometer_km'] !== null ? number_format((int)$v['odometer_km'], 0, ',', '.') . ' km' : '';
                        $lp = trim((string)($v['license_plate'] ?? ''));
                        $parts2 = [];
                        if ($lp !== '') { $parts2[] = t('license_plate') . ': ' . $lp; }
                        if ($km !== '') { $parts2[] = t('odometer_km') . ': ' . $km; }
                        echo e(implode(' · ', $parts2));
                      ?>
                    </div>
                    <!-- Zeile 3: HSN/TSN · FIN (mit Labels) -->
                    <div class="vehicle-sub2">
                      <?php 
                        $vin = trim((string)($v['vin'] ?? ''));
                        $hsn = isset($v['hsn']) ? trim((string)$v['hsn']) : '';
                        $tsn = isset($v['tsn']) ? trim((string)$v['tsn']) : '';
                        $hsnTsn = ($hsn !== '' || $tsn !== '') ? ($hsn . '/' . $tsn) : '';
                        $parts = [];
                        if ($hsnTsn !== '' && $hsnTsn !== '/') { $parts[] = t('hsn') . '/' . t('tsn') . ': ' . $hsnTsn; }
                        if ($vin !== '') { $parts[] = t('vin') . ': ' . $vin; }
                        echo e(implode(' · ', $parts));
                      ?>
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
