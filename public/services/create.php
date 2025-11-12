<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';

if (!is_logged_in()) {
    header('Location: /login/');
    exit;
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : (isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0);
if ($vehicleId <= 0) {
    header('Location: /dashboard');
    exit;
}

$error = null;
$messages = [];
$vehicle = null;
// Load per-user intervals (fallback to config defaults)
$oilIntKm = defined('DEFAULT_OIL_INTERVAL_KM') ? (int)DEFAULT_OIL_INTERVAL_KM : 15000;
$oilIntYears = defined('DEFAULT_OIL_INTERVAL_YEARS') ? (int)DEFAULT_OIL_INTERVAL_YEARS : 1;
$srvIntKm = defined('DEFAULT_SERVICE_INTERVAL_KM') ? (int)DEFAULT_SERVICE_INTERVAL_KM : 30000;
$srvIntYears = defined('DEFAULT_SERVICE_INTERVAL_YEARS') ? (int)DEFAULT_SERVICE_INTERVAL_YEARS : 2;
try {
    if ($uid > 0) {
        $updo = db();
        try {
            $ustmt = $updo->prepare('SELECT oil_interval_km, oil_interval_years, service_interval_km, service_interval_years FROM users WHERE id = ? LIMIT 1');
            $ustmt->execute([$uid]);
            $urow = $ustmt->fetch();
            if ($urow) {
                if (isset($urow['oil_interval_km']) && $urow['oil_interval_km'] !== null) $oilIntKm = (int)$urow['oil_interval_km'];
                if (isset($urow['oil_interval_years']) && $urow['oil_interval_years'] !== null) $oilIntYears = (int)$urow['oil_interval_years'];
                if (isset($urow['service_interval_km']) && $urow['service_interval_km'] !== null) $srvIntKm = (int)$urow['service_interval_km'];
                if (isset($urow['service_interval_years']) && $urow['service_interval_years'] !== null) $srvIntYears = (int)$urow['service_interval_years'];
            }
        } catch (Throwable $ignored) {}
    }
} catch (Throwable $ignored) {}

try {
    $vpdo = vehicle_db();
    try {
        $dbn = $vpdo->query('SELECT DATABASE() AS dbname')->fetch();
        if ($dbn && isset($dbn['dbname'])) {
            @error_log('[services/create] Connected to DB: ' . (string)$dbn['dbname']);
        }
    } catch (Throwable $ignored) {}
    $stmt = $vpdo->prepare('SELECT * FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1');
    $stmt->execute([$vehicleId, $uid, $uid]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        $error = t('vehicle_not_found');
    }
} catch (Throwable $e) {
    @error_log('[services/create] Vehicle load failed: ' . $e->getMessage());
    $error = t('vehicle_load_failed');
}

// Handle submission
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $_SESSION['form_errors'] = [t('csrf_invalid')];
        header('Location: /vehicles/view.php?id=' . urlencode((string)$vehicleId));
        exit;
    }

    $type = trim((string)($_POST['type'] ?? ''));
    $serviceDate = trim((string)($_POST['service_date'] ?? ''));
    $odoRaw = isset($_POST['odometer_km']) ? (string)$_POST['odometer_km'] : '';
    $odoSan = preg_replace('/\D+/', '', $odoRaw ?? '');
    $odometerKm = ($odoSan !== '') ? (int)$odoSan : null;
    $nextKmRaw = isset($_POST['next_due_km']) ? (string)$_POST['next_due_km'] : '';
    $nextKmSan = preg_replace('/\D+/', '', $nextKmRaw ?? '');
    $nextDueKm = ($nextKmSan !== '') ? (int)$nextKmSan : null;
    $nextDueDate = trim((string)($_POST['next_due_date'] ?? ''));

    // Validate odometer vs current vehicle
    try {
        $currKm = isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== null ? (int)$vehicle['odometer_km'] : null;
        $confirmLower = isset($_POST['confirm_lower_odo']) && $_POST['confirm_lower_odo'] === '1';
        if ($odometerKm !== null && $currKm !== null && $odometerKm < $currKm && !$confirmLower) {
            $error = 'Der eingegebene Kilometerstand ist niedriger als der aktuelle. Bitte bestätigen, dass dieser Wert übernommen werden soll.';
        }
    } catch (Throwable $ignored) {}

    // Backend fallback: compute next due based on intervals when type is service/oil_change and fields are empty
    if ($type === 'service' || $type === 'oil_change') {
        $ikm = ($type === 'oil_change') ? $oilIntKm : $srvIntKm;
        $iy = ($type === 'oil_change') ? $oilIntYears : $srvIntYears;
        if ($nextDueKm === null && $odometerKm !== null && $ikm > 0) {
            $nextDueKm = $odometerKm + $ikm;
        }
        if ($nextDueDate === '' && $serviceDate !== '' && $iy > 0) {
            try {
                $dt = new DateTime($serviceDate);
                $dt->modify('+' . (int)$iy . ' years');
                $nextDueDate = $dt->format('Y-m-d');
            } catch (Throwable $ignored) {}
        }
    }

    $labels = $_POST['item_label'] ?? [];
    $amounts = $_POST['item_amount'] ?? [];
    $notesArr = $_POST['item_note'] ?? [];

    $items = [];
    $totalAmount = 0.0;
    if (is_array($labels) && is_array($amounts)) {
        $count = max(count($labels), count($amounts));
        for ($i = 0; $i < $count; $i++) {
            $label = isset($labels[$i]) ? trim((string)$labels[$i]) : '';
            $amount = isset($amounts[$i]) ? (float)str_replace([','], ['.'], (string)$amounts[$i]) : 0.0;
            $note = isset($notesArr[$i]) ? trim((string)$notesArr[$i]) : '';
            if ($label !== '' && $amount !== 0.0) {
                $items[] = ['label' => $label, 'amount' => $amount, 'note' => $note];
                $totalAmount += $amount;
            }
        }
    }

    // Basic validation
    if ($type === '' || $serviceDate === '') {
        $error = t('form_invalid');
    }

    if (!$error) {
        try {
            $vpdo = vehicle_db();
            $spdo = service_db();

            // Diagnostics: log which DBs are targeted by each connection
            try {
                $vdbn = $vpdo->query('SELECT DATABASE() AS dbname')->fetch();
                if ($vdbn && isset($vdbn['dbname'])) {
                    @error_log('[services/create] vehicle_db() DATABASE()=' . (string)$vdbn['dbname']);
                }
            } catch (Throwable $ignored) {}
            try {
                $sdbn = $spdo->query('SELECT DATABASE() AS dbname')->fetch();
                if ($sdbn && isset($sdbn['dbname'])) {
                    @error_log('[services/create] service_db() DATABASE()=' . (string)$sdbn['dbname']);
                }
            } catch (Throwable $ignored) {}

            // Ensure tables exist (best-effort, in case update script not yet run)
            try {
                $spdo->exec('CREATE TABLE IF NOT EXISTS `service_entries` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `vehicle_id` INT UNSIGNED NOT NULL,
                    `type` VARCHAR(50) NOT NULL,
                    `service_date` DATE NOT NULL,
                    `odometer_km` INT NULL,
                    `next_due_km` INT NULL,
                    `next_due_date` DATE NULL,
                    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    INDEX (`vehicle_id`),
                    INDEX (`service_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
                $spdo->exec('CREATE TABLE IF NOT EXISTS `service_items` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `entry_id` INT UNSIGNED NOT NULL,
                    `label` VARCHAR(255) NOT NULL,
                    `amount` DECIMAL(12,2) NOT NULL,
                    `note` TEXT NULL,
                    PRIMARY KEY (`id`),
                    INDEX (`entry_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
                // vehicles.total_spent column (best-effort)
                try {
                    $vpdo->exec("ALTER TABLE `vehicles` ADD COLUMN `total_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00");
                } catch (Throwable $ignored) {}

                // Add foreign keys with ON DELETE CASCADE when both modules use the same DB
                try {
                    $vehDb = '';
                    $srvDb = '';
                    try { $tmp = $vpdo->query('SELECT DATABASE() AS dbname')->fetch(); if ($tmp && isset($tmp['dbname'])) { $vehDb = (string)$tmp['dbname']; } } catch (Throwable $__) {}
                    try { $tmp2 = $spdo->query('SELECT DATABASE() AS dbname')->fetch(); if ($tmp2 && isset($tmp2['dbname'])) { $srvDb = (string)$tmp2['dbname']; } } catch (Throwable $__) {}
                    if ($vehDb !== '' && $vehDb === $srvDb) {
                        // service_entries.vehicle_id -> vehicles.id
                        try { $spdo->exec('ALTER TABLE `service_entries` ADD CONSTRAINT `fk_service_entries_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE'); } catch (Throwable $ignored) {}
                        // service_items.entry_id -> service_entries.id
                        try { $spdo->exec('ALTER TABLE `service_items` ADD CONSTRAINT `fk_service_items_entry` FOREIGN KEY (`entry_id`) REFERENCES `service_entries`(`id`) ON DELETE CASCADE'); } catch (Throwable $ignored) {}
                    }
                } catch (Throwable $ignored) {}
            } catch (Throwable $ignored) {}

            // Insert entry (canonical schema)
            $stmt = $spdo->prepare('INSERT INTO `service_entries` (`vehicle_id`,`type`,`service_date`,`odometer_km`,`next_due_km`,`next_due_date`,`total_amount`) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([
                $vehicleId,
                $type,
                $serviceDate,
                ($odometerKm !== null ? $odometerKm : null),
                ($nextDueKm !== null ? $nextDueKm : null),
                ($nextDueDate !== '' ? $nextDueDate : null),
                $totalAmount,
            ]);
            $entryId = (int)$spdo->lastInsertId();
            @error_log('[services/create] Inserted service_entries id=' . $entryId . ' total=' . number_format($totalAmount,2,'.',''));

            if (!empty($items)) {
                $istmt = $spdo->prepare('INSERT INTO `service_items` (`entry_id`,`label`,`amount`,`note`) VALUES (?,?,?,?)');
                foreach ($items as $it) {
                    $istmt->execute([$entryId, $it['label'], $it['amount'], ($it['note'] !== '' ? $it['note'] : null)]);
                }
            }

            // Update total_spent as cumulative add
            try {
                $vpdo->prepare('UPDATE vehicles SET total_spent = COALESCE(total_spent,0) + ? WHERE id = ?')->execute([$totalAmount, $vehicleId]);
            } catch (Throwable $e) {
                @error_log('[services/create] total_spent update failed: ' . $e->getMessage());
                // Fallback: compute from items
                try {
                    $sumStmt = $spdo->prepare('SELECT SUM(si.amount) AS s FROM `service_items` si JOIN `service_entries` se ON se.id = si.entry_id WHERE se.vehicle_id = ?');
                    $sumStmt->execute([$vehicleId]);
                    $row = $sumStmt->fetch();
                    $sum = (float)($row['s'] ?? 0.0);
                    $vpdo->prepare('UPDATE vehicles SET total_spent = ? WHERE id = ?')->execute([$sum, $vehicleId]);
                } catch (Throwable $ignored) {}
            }

            // Update vehicle odometer if needed (higher or confirmed lower)
            try {
                $currKm2 = isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== null ? (int)$vehicle['odometer_km'] : null;
                $confirmLower2 = isset($_POST['confirm_lower_odo']) && $_POST['confirm_lower_odo'] === '1';
                if ($odometerKm !== null) {
                    if ($currKm2 === null || $odometerKm > $currKm2 || ($odometerKm < $currKm2 && $confirmLower2)) {
                        $vpdo->prepare('UPDATE vehicles SET odometer_km = ? WHERE id = ?')->execute([$odometerKm, $vehicleId]);
                    }
                }
            } catch (Throwable $ignored) {}

            // autocommit handles persistence; no explicit transaction commit needed

            $_SESSION['form_success'] = [t('service_saved_success')];
            header('Location: /vehicles/view.php?id=' . urlencode((string)$vehicleId));
            exit;
        } catch (Throwable $e) {
            @error_log('[services/create] Save failed: ' . $e->getMessage());
            $error = t('service_save_failed');
        }
    }
}

// CSRF token helper
// Use csrf_token() consistent with other pages

$types = [
    'repair' => t('service_type_repair'),
    'oil_change' => t('service_type_oil_change'),
    'service' => t('service_type_service'),
    'tuev' => t('service_type_tuev'),
];

?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(t('service_create_title')) ?> - <?= e(APP_NAME) ?></title>
  <?php render_common_head_links(); ?>
  <style>
    .row { display:flex; gap:0.75rem; flex-wrap:wrap; }
    .field { flex:1; min-width:220px; }
    .items-table { width:100%; border-collapse: collapse; }
    .items-table th, .items-table td { padding: 0.5rem; border-bottom:1px solid rgba(0,0,0,.06); }
    .btn-icon { display:inline-flex; align-items:center; gap:0.35rem; }
  </style>
</head>
<body class="page">
<?php render_brand_header([
  'links' => [
    ['label' => t('vehicles_back_to_detail'), 'href' => '/vehicles/view?id=' . e((string)$vehicleId), 'icon' => 'arrow-left'],
  ],
  'cta' => ['label' => t('vehicles_back_to_overview'), 'href' => '/dashboard']
]); ?>
<main class="page-content">
  <div class="container-wide reveal-enter">
    <div class="card" style="max-width:900px;margin:0 auto;">
      <h2 class="card-title"><?= e(t('service_create_title')) ?></h2>

      <?php if ($error): ?>
        <div class="alert" style="color:var(--danger);"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/services/create.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="vehicle_id" value="<?= e((string)$vehicleId) ?>">
        <input type="hidden" id="confirm_lower_odo" name="confirm_lower_odo" value="0">

        <div class="row">
          <div class="field">
            <label for="type"><?= e(t('service_type')) ?></label>
            <select id="type" name="type" required>
              <option value="">--</option>
              <?php foreach ($types as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="odometer_km"><?= e(t('odometer_km')) ?></label>
            <input id="odometer_km" name="odometer_km" type="text" placeholder="0" value="<?= e(isset($_POST['odometer_km']) && $_POST['odometer_km'] !== '' ? (string)$_POST['odometer_km'] : ((isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== null) ? (string)$vehicle['odometer_km'] : '')) ?>">
          </div>
          <div class="field">
            <label for="service_date"><?= e(t('service_date')) ?></label>
            <input id="service_date" name="service_date" type="date" required value="<?= e(isset($_POST['service_date']) && $_POST['service_date'] !== '' ? (string)$_POST['service_date'] : date('Y-m-d')) ?>">
          </div>
        </div>

        <div id="intervalWrap" style="display:none; margin-top:0.5rem;">
          <div class="row">
            <div class="field" id="field_next_due_km">
              <label for="next_due_km"><?= e(t('next_due_km')) ?></label>
              <input id="next_due_km" name="next_due_km" type="text" placeholder="0">
            </div>
            <div class="field">
              <label for="next_due_date"><?= e(t('next_due_date')) ?></label>
              <input id="next_due_date" name="next_due_date" type="date">
            </div>
          </div>
        </div>

        <div style="margin-top:1rem;">
          <h3 class="card-title" style="font-size:1rem;"><?= e(t('service_items')) ?></h3>
          <table class="items-table" id="itemsTable">
            <thead>
              <tr>
                <th style="width:50%;"><?= e(t('item_label')) ?></th>
                <th style="width:20%;text-align:right;"><?= e(t('item_amount')) ?></th>
                <th style="width:25%;"><?= e(t('item_note')) ?></th>
                <th style="width:5%;"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input name="item_label[]" type="text" placeholder="<?= e(t('item_label_ph')) ?>"></td>
                <td><input name="item_amount[]" type="number" step="0.01" min="0" placeholder="0.00" style="text-align:right;"></td>
                <td><input name="item_note[]" type="text" placeholder="<?= e(t('item_note_ph')) ?>"></td>
                <td style="text-align:center;"><button type="button" class="btn-secondary btn-icon" id="addItemBtn"><?= icon_svg('plus') ?></button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem;">
          <a href="/vehicles/view.php?id=<?= e((string)$vehicleId) ?>" class="btn-secondary"><?= e(t('cancel')) ?></a>
          <button type="submit" class="btn-primary"><?= e(t('save')) ?></button>
        </div>
      </form>
    </div>
  </div>
</main>
<script>
(function(){
  var typeEl = document.getElementById('type');
  var intervalWrap = document.getElementById('intervalWrap');
  var nextDueKmDiv = document.getElementById('field_next_due_km');
  var nextDueKmInput = document.getElementById('next_due_km');
  var nextDueDateInput = document.getElementById('next_due_date');
  var odoInput = document.getElementById('odometer_km');
  var serviceDateInput = document.getElementById('service_date');
  var OIL_KM = <?= (int)$oilIntKm ?>;
  var OIL_YEARS = <?= (int)$oilIntYears ?>;
  var SRV_KM = <?= (int)$srvIntKm ?>;
  var SRV_YEARS = <?= (int)$srvIntYears ?>;

  function addYearsToDateStr(dateStr, years){
    if (!dateStr) return '';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) d = new Date();
    d.setFullYear(d.getFullYear() + (parseInt(years,10)||0));
    var m = String(d.getMonth() + 1).padStart(2,'0');
    var day = String(d.getDate()).padStart(2,'0');
    return d.getFullYear() + '-' + m + '-' + day;
  }

  function recalcIntervals(){
    var v = (typeEl.value||'');
    if (!(v === 'service' || v === 'oil_change')) return;
    var ikm = (v === 'oil_change') ? OIL_KM : SRV_KM;
    var iy = (v === 'oil_change') ? OIL_YEARS : SRV_YEARS;
    // next km only if empty
    if (nextDueKmInput && (!nextDueKmInput.value || nextDueKmInput.value.trim() === '')){
      var odoDigits = (odoInput && odoInput.value) ? (odoInput.value+'').replace(/\D+/g,'') : '';
      if (odoDigits && ikm > 0){
        var sum = (parseInt(odoDigits,10) || 0) + (parseInt(ikm,10) || 0);
        nextDueKmInput.value = formatKm(String(sum));
      }
    }
    // next date only if empty
    if (nextDueDateInput && (!nextDueDateInput.value || nextDueDateInput.value.trim() === '')){
      if (iy > 0){
        nextDueDateInput.value = addYearsToDateStr(serviceDateInput ? serviceDateInput.value : '', iy);
      }
    }
  }
  function updateInterval(){
    var v = (typeEl.value||'');
    if (v === 'tuev' || v === 'service' || v === 'oil_change') {
      intervalWrap.style.display = '';
      if (v === 'tuev') {
        if (nextDueKmDiv) nextDueKmDiv.style.display = 'none';
        if (nextDueKmInput) nextDueKmInput.value = '';
        if (nextDueDateInput && !nextDueDateInput.value) {
          var d = new Date();
          d.setFullYear(d.getFullYear() + 2);
          var m = String(d.getMonth() + 1).padStart(2,'0');
          var day = String(d.getDate()).padStart(2,'0');
          nextDueDateInput.value = d.getFullYear() + '-' + m + '-' + day;
        }
      } else {
        if (nextDueKmDiv) nextDueKmDiv.style.display = '';
        recalcIntervals();
      }
    } else {
      intervalWrap.style.display = 'none';
      if (nextDueKmInput) nextDueKmInput.value = '';
      if (nextDueDateInput) nextDueDateInput.value = '';
    }
  }
  typeEl.addEventListener('change', updateInterval);
  updateInterval();

  function formatKm(val){
    var digits = (val||'').replace(/\D+/g,'');
    if (!digits) return '';
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }
  function attachKmFormatting(input){
    if (!input) return;
    input.addEventListener('input', function(){
      var pos = input.selectionStart;
      var before = input.value;
      input.value = formatKm(input.value);
      try { input.setSelectionRange(pos, pos); } catch(e) {}
    });
    input.addEventListener('blur', function(){ input.value = formatKm(input.value); });
  }
  attachKmFormatting(odoInput);
  attachKmFormatting(nextDueKmInput);
  if (odoInput) odoInput.value = formatKm(odoInput.value);
  if (nextDueKmInput) nextDueKmInput.value = formatKm(nextDueKmInput.value);
  if (serviceDateInput) serviceDateInput.addEventListener('change', function(){ recalcIntervals(); });
  if (odoInput) odoInput.addEventListener('input', function(){ recalcIntervals(); });

  var formEl = document.querySelector('form[action="/services/create.php"]');
  if (formEl) {
    formEl.addEventListener('submit', function(e){
      if (odoInput) odoInput.value = (odoInput.value||'').replace(/\D+/g,'');
      if (nextDueKmInput) nextDueKmInput.value = (nextDueKmInput.value||'').replace(/\D+/g,'');
      var confirmLowerEl = document.getElementById('confirm_lower_odo');
      var currentVehicleKm = <?= isset($vehicle['odometer_km']) && $vehicle['odometer_km'] !== null ? (int)$vehicle['odometer_km'] : 0 ?>;
      var enteredKm = odoInput && odoInput.value ? parseInt(odoInput.value, 10) : null;
      if (enteredKm !== null && currentVehicleKm && enteredKm < currentVehicleKm) {
        var msg = 'Der eingegebene Kilometerstand (' + enteredKm + ' km) ist niedriger als der aktuelle (' + currentVehicleKm + ' km). Möchtest du diesen Wert wirklich übernehmen?';
        if (!confirm(msg)) {
          if (confirmLowerEl) confirmLowerEl.value = '0';
          e.preventDefault();
          return false;
        }
        if (confirmLowerEl) confirmLowerEl.value = '1';
      }
    });
  }

  var addBtn = document.getElementById('addItemBtn');
  var tbody = document.querySelector('#itemsTable tbody');
  var labelPh = <?= json_encode(t('item_label_ph')) ?>;
  var notePh = <?= json_encode(t('item_note_ph')) ?>;
  function addRow(){
    var tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input name="item_label[]" type="text" placeholder="${labelPh}"></td>
      <td><input name="item_amount[]" type="number" step="0.01" min="0" placeholder="0.00" style="text-align:right;"></td>
      <td><input name="item_note[]" type="text" placeholder="${notePh}"></td>
      <td style="text-align:center;"><button type="button" class="btn-secondary btn-icon remove">&times;</button></td>`;
    tbody.appendChild(tr);
  }
  addBtn.addEventListener('click', function(e){ e.preventDefault(); addRow(); });
  tbody.addEventListener('click', function(e){
    var t = e.target;
    if (t && t.classList && t.classList.contains('remove')) {
      e.preventDefault();
      var tr = t.closest('tr'); if (tr) tr.remove();
    }
  });
})();
</script>
</body>
</html>
