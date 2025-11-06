<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
$vehicles = [];
$error = null;
try {
    $pdo = vehicle_db();
    $uid = (int)($user['id'] ?? 0);
    try {
        $sql = 'SELECT id, make, model, year, license_plate, color, odometer_km, profile_image FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $uid]);
        $vehicles = $stmt->fetchAll() ?: [];
    } catch (Throwable $inner) {
        if ($inner instanceof PDOException && $inner->getCode() === '42S22') {
            $sql = 'SELECT id, make, model, year, license_plate, color, odometer_km FROM vehicles WHERE (user_id = ? OR (? = 0 AND user_id IS NULL)) ORDER BY updated_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$uid, $uid]);
            $vehicles = $stmt->fetchAll() ?: [];
        } else {
            throw $inner;
        }
    }
} catch (Throwable $e) {
    $error = 'Fahrzeuge konnten nicht geladen werden.';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fahrzeugübersicht - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
</head>
<body class="page">
  <?php render_brand_header([
    'links' => [
      ['label' => t('account_link'), 'href' => '/account/', 'icon' => 'user', 'text' => $user['username'] ?? ''],
      ['label' => t('logout'), 'href' => '/logout', 'icon' => 'logout']
    ],
    'cta' => ['label' => 'Neues Auto anlegen', 'href' => '/vehicles/create']
  ]); ?>
  <main class="page-content">
    <div class="container reveal-enter">
      <div class="card">
        <h2 class="card-title">Deine Fahrzeuge</h2>
        <?php if ($error): ?>
          <div class="alert" style="border-color:var(--danger);color:var(--danger);"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (!$vehicles): ?>
          <p>Noch keine Fahrzeuge vorhanden.</p>
          <a href="/vehicles/create" class="btn-primary">Neues Auto anlegen</a>
        <?php else: ?>
          <style>
            @media (min-width: 1px) { .vehicle-tiles { display:grid; gap:1rem; grid-template-columns: 1fr; } }
            @media (min-width: 640px) { .vehicle-tiles { grid-template-columns: repeat(2, minmax(0,1fr)); } }
            @media (min-width: 1024px) { .vehicle-tiles { grid-template-columns: repeat(3, minmax(0,1fr)); } }
            .vehicle-tile { 
              display:flex; gap:0.875rem; align-items:center; text-decoration:none; 
              min-height: 120px; padding: 0.5rem; border-radius: 12px;
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
            /* Ensure all text inside the clickable tile uses foreground color */
            .vehicle-tile, .vehicle-tile * { color: rgb(var(--color-fg)) !important; }
          </style>
          <div class="vehicle-tiles">
            <?php foreach ($vehicles as $v): ?>
              <?php $img = !empty($v['profile_image'] ?? null) ? $v['profile_image'] : '/assets/files/logo-light.svg'; ?>
              <a class="tile card vehicle-tile" href="/vehicles/view?id=<?= e((string)$v['id']) ?>">
                <img src="<?= e($img) ?>" alt="Fahrzeugbild" class="vehicle-thumb" onerror="this.onerror=null;this.src='/assets/files/logo-light.svg'">
                <div class="vehicle-meta">
                  <div class="vehicle-title">
                    <?= e(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?>
                  </div>
                  <div class="vehicle-sub">
                    <?= e(($v['year'] ? (string)$v['year'] : '')) ?> <?= e($v['license_plate'] ? '· ' . $v['license_plate'] : '') ?>
                  </div>
                  <div class="vehicle-sub2">
                    <?= e($v['color'] ? $v['color'] : '') ?> <?= e($v['odometer_km'] ? '· ' . number_format((int)$v['odometer_km'], 0, ',', '.') . ' km' : '') ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
