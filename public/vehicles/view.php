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
    <div class="container reveal-enter">
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin:0 0 0.75rem 0;">
        <a href="/vehicles/edit?id=<?= e((string)$id) ?>" class="header-link-with-text">
          <span class="link-text">Bearbeiten</span>
        </a>
      </div>
      <?php if ($error): ?>
        <div class="card" style="border-color:var(--danger);color:var(--danger);">
          <h2 class="card-title">Fehler</h2>
          <p><?= e($error) ?></p>
          <a href="/overview" class="btn-secondary">Zur Übersicht</a>
        </div>
      <?php else: ?>
        <div class="card">
          <h2 class="card-title"><?= e(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?></h2>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
            <div><strong>Jahr:</strong><br><?= e($vehicle['year'] ?? '') ?></div>
            <div><strong>Kennzeichen:</strong><br><?= e($vehicle['license_plate'] ?? '') ?></div>
            <div><strong>VIN:</strong><br><?= e($vehicle['vin'] ?? '') ?></div>
            <div><strong>Farbe:</strong><br><?= e($vehicle['color'] ?? '') ?></div>
            <div><strong>Motorcode:</strong><br><?= e($vehicle['engine_code'] ?? '') ?></div>
            <div><strong>Kraftstoff:</strong><br><?= e($vehicle['fuel_type'] ?? '') ?></div>
            <div><strong>Kilometerstand:</strong><br><?= e(isset($vehicle['odometer_km']) ? number_format((int)$vehicle['odometer_km'], 0, ',', '.') . ' km' : '') ?></div>
            <div><strong>Kaufdatum:</strong><br><?= e($vehicle['purchase_date'] ?? '') ?></div>
            <div><strong>Kaufpreis:</strong><br><?= e(isset($vehicle['purchase_price']) ? number_format((float)$vehicle['purchase_price'], 2, ',', '.') . ' €' : '') ?></div>
          </div>
        </div>

        <div class="card">
          <h2 class="card-title">Notizen</h2>
          <p><?= nl2br(e($vehicle['notes'] ?? '')) ?></p>
        </div>

      <?php endif; ?>
    </div>
  </main>
</body>
</html>
