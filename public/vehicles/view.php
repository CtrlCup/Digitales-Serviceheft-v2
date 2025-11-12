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
$recentEntries = [];
$recentItemsByEntry = [];
$limit = 10;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$isRecentAjax = isset($_GET['recent']) && (int)$_GET['recent'] === 1;
try {
    $pdo = vehicle_db();
    $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ? AND (user_id = ? OR (? = 0 AND user_id IS NULL)) LIMIT 1');
    $stmt->execute([$id, $uid, $uid]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        $error = t('vehicle_not_found');
    }
    // Load recent service entries from service DB
    if (!$error) {
        try {
            $spdo = service_db();
            // total count
            $cntStmt = $spdo->prepare('SELECT COUNT(*) AS c FROM service_entries WHERE vehicle_id = ?');
            $cntStmt->execute([$id]);
            $totalCount = (int)($cntStmt->fetch()['c'] ?? 0);

            $s = $spdo->prepare('SELECT se.id, se.type, se.service_date, se.odometer_km, se.total_amount, se.next_due_km, se.next_due_date
                                  FROM service_entries se
                                  WHERE se.vehicle_id = ?
                                  ORDER BY se.service_date DESC, se.id DESC
                                  LIMIT ? OFFSET ?');
            $s->bindValue(1, $id, PDO::PARAM_INT);
            $s->bindValue(2, $limit, PDO::PARAM_INT);
            $s->bindValue(3, $offset, PDO::PARAM_INT);
            $s->execute();
            $recentEntries = $s->fetchAll() ?: [];
            if (!empty($recentEntries)) {
                $ids = array_map(function($r){ return (int)$r['id']; }, $recentEntries);
                $in = implode(',', array_fill(0, count($ids), '?'));
                $it = $spdo->prepare('SELECT entry_id, label, amount, note FROM service_items WHERE entry_id IN (' . $in . ') ORDER BY entry_id ASC, id ASC');
                $it->execute($ids);
                foreach ($it->fetchAll() as $row) {
                    $eid = (int)$row['entry_id'];
                    if (!isset($recentItemsByEntry[$eid])) $recentItemsByEntry[$eid] = [];
                    $recentItemsByEntry[$eid][] = $row;
                }
            }
            // If it's an AJAX request for recent entries, output only the cards and exit
            if ($isRecentAjax) {
                ob_start();
                include __DIR__ . '/view_recent_cards.partial.php';
                $html = ob_get_clean();
                $hasMoreAjax = ($totalCount > ($offset + $limit)) ? '1' : '0';
                header('Content-Type: text/html; charset=utf-8');
                echo '<div class="svc-batch" data-has-more="' . e($hasMoreAjax) . '" data-batch-size="' . e((string)count($recentEntries)) . '">' . $html . '</div>';
                exit;
            }
        } catch (Throwable $ignored) {}
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
          <a href="/services/create.php?vehicle_id=<?= e((string)$id) ?>" class="btn-secondary" title="<?= e(t('add_service')) ?>" style="flex:0 0 auto; margin-left:auto;">
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
            $spentVal = (isset($vehicle['total_spent']) && $vehicle['total_spent'] !== '' && $vehicle['total_spent'] !== null)
              ? number_format((float)$vehicle['total_spent'], 2, ',', '.') . ' €' : '';
            $specs = [];
            if ($hsnTsn !== '') { $specs[] = ['label' => t('hsn') . '/' . t('tsn'), 'value' => $hsnTsn]; }
            if ($vinVal !== '') { $specs[] = ['label' => t('vin'), 'value' => $vinVal]; }
            if ($frVal !== '') { $specs[] = ['label' => t('first_registration'), 'value' => $frVal]; }
            if ($colorVal !== '') { $specs[] = ['label' => t('color'), 'value' => $colorVal]; }
            if ($fuelVal !== '') { $specs[] = ['label' => t('fuel_type'), 'value' => $fuelVal]; }
            if ($engineCodeVal !== '') { $specs[] = ['label' => t('engine_code'), 'value' => $engineCodeVal]; }
            if ($pdVal !== '') { $specs[] = ['label' => t('purchase_date'), 'value' => $pdVal]; }
            if ($ppVal !== '') { $specs[] = ['label' => t('purchase_price'), 'value' => $ppVal]; }
            if ($spentVal !== '') { $specs[] = ['label' => t('service_total_spent'), 'value' => $spentVal]; }
          ?>
          <?php if ($spentVal !== ''): ?>
            <div style="position:absolute;top:35px;right:50px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
              <div style="font-size:0.8rem;color:var(--text-muted);text-align:right;"><?= e(t('service_total_spent')) ?></div>
              <div style="padding:0.35rem 0.6rem;border:1px solid rgba(var(--color-border),0.45);border-radius:12px;background:rgba(var(--color-bg),0.5);backdrop-filter:blur(2px);font-weight:700;"><?= e($spentVal) ?></div>
            </div>
          <?php endif; ?>
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
        
        <!-- Recent service entries (full width under details/notes) -->
        <div class="card" style="grid-column: 1 / -1; margin-top:1rem;">
          <h2 class="card-title" style="margin-bottom:0.5rem;"><?= e(t('recent_service_entries') ?? 'Letzte Service-Einträge') ?></h2>
          <?php if (empty($recentEntries)): ?>
            <p style="color:var(--text-muted);"><?= e(t('no_service_entries_yet') ?? 'Noch keine Einträge vorhanden.') ?></p>
          <?php else: ?>
            <style>
              .svc-grid { display:grid; gap:0.9rem; grid-template-columns: 1fr; }
              .svc-card {
                padding:1rem;
                border:1px solid rgba(var(--color-border), 0.35);
                border-radius:14px;
                background: rgba(var(--color-bg), 0.6);
                box-shadow: 0 8px 24px rgba(0,0,0,.25);
                backdrop-filter: blur(2px);
                transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
              }
              .svc-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(0,0,0,.32); border-color: rgba(var(--color-border), 0.5); }
              .svc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:0.75rem; margin-bottom:0.75rem; }
              .svc-type { font-weight:700; letter-spacing:.1px; font-size:1.05rem; }
              .svc-body { display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }
              .svc-left { flex:1 1 0; min-width:260px; }
              .svc-right { flex:0 0 280px; max-width:280px; margin-left:auto; align-self:flex-start; margin-top:-0.15rem; }
              @media (max-width: 900px) { .svc-body { flex-direction:column; } .svc-right { max-width:none; } }
              .svc-items-list { width:100%; border-collapse:collapse; }
              .svc-items-list td { padding:0.35rem 0; border-bottom:1px dashed rgba(var(--color-border),0.25); }
              .svc-items-list td:last-child { text-align:right; white-space:nowrap; padding-left:0.75rem; font-variant-numeric: tabular-nums; }
              .svc-info-grid { display:grid; grid-template-columns: 1fr 1fr; gap:0.4rem; align-items:start; justify-items:stretch; }
              .tile { display:flex; gap:0.35rem; align-items:center; padding:0.35rem 0.45rem; border:1px solid rgba(var(--color-border),0.35); border-radius:9px; background: rgba(var(--color-bg), 0.45); width:100%; min-height:40px; }
              .tile svg { width:14px; height:14px; opacity:0.9; }
              .tile .t-content { line-height:1.1; text-align:left; }
              .tile .t-label { font-size:0.72rem; color: var(--text-muted); }
              .tile .t-value { font-weight:700; font-size:0.9rem; }
              .svc-divider { flex-basis:100%; height:1px; background: linear-gradient(90deg, rgba(var(--color-border),0), rgba(var(--color-border),0.45), rgba(var(--color-border),0)); opacity:0.85; margin:0.6rem 0 0.4rem; }
              .svc-grand-total { width:100%; text-align:right; font-weight:800; font-size:1.05rem; }
            </style>
            <div id="svcGrid" class="svc-grid">
              <?php foreach ($recentEntries as $re): ?>
                <?php
                  $eid = (int)$re['id'];
                  $dateStr = '';
                  if (!empty($re['service_date'])) { $tsd = strtotime((string)$re['service_date']); $dateStr = $tsd ? date('d.m.Y', $tsd) : (string)$re['service_date']; }
                  $odoStr = isset($re['odometer_km']) && $re['odometer_km'] !== null ? number_format((int)$re['odometer_km'], 0, ',', '.') . ' km' : '';
                  $nextDueKmStr = isset($re['next_due_km']) && $re['next_due_km'] !== null ? number_format((int)$re['next_due_km'], 0, ',', '.') . ' km' : '';
                  $nextDueDateStr = '';
                  if (!empty($re['next_due_date'])) { $tsn = strtotime((string)$re['next_due_date']); $nextDueDateStr = $tsn ? date('d.m.Y', $tsn) : (string)$re['next_due_date']; }
                  $typeMap = [
                    'repair' => t('service_type_repair'),
                    'oil_change' => t('service_type_oil_change'),
                    'service' => t('service_type_service'),
                    'tuev' => t('service_type_tuev'),
                  ];
                  $typeLabel = $typeMap[$re['type']] ?? (string)$re['type'];
                  $items = $recentItemsByEntry[$eid] ?? [];
                  $labels = [];
                  $sum = 0.0;
                  foreach ($items as $it) { $lbl = trim((string)$it['label']); if ($lbl !== '') $labels[] = $lbl; $sum += (float)($it['amount'] ?? 0.0); }
                  $labelsStr = !empty($labels) ? implode(', ', array_slice($labels, 0, 5)) : t('service_items') . ': -';
                  $totalStr = number_format($sum > 0 ? $sum : (float)($re['total_amount'] ?? 0.0), 2, ',', '.') . ' €';
                ?>
                <div class="svc-card">
                  <div class="svc-head">
                    <div class="svc-type"><?= e($typeLabel) ?></div>
                  </div>
                  <div class="svc-body">
                    <div class="svc-left">
                      <?php if (!empty($items)): ?>
                        <table class="svc-items-list">
                          <tbody>
                          <?php foreach ($items as $it): ?>
                            <?php $lbl = trim((string)$it['label']); $amt = (float)($it['amount'] ?? 0.0); ?>
                            <tr>
                              <td><?= e($lbl !== '' ? $lbl : '-') ?></td>
                              <td><?= e(number_format($amt, 2, ',', '.')) ?> €</td>
                            </tr>
                          <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php else: ?>
                        <div class="svc-items" style="color:var(--text-muted);">-</div>
                      <?php endif; ?>
                    </div>
                    <div class="svc-right">
                      <div class="svc-info-grid">
                        <div class="tile">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                          <div class="t-content"><div class="t-label"><?= e(t('service_date')) ?></div><div class="t-value"><?= e($dateStr ?: '-') ?></div></div>
                        </div>
                        <div class="tile">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path d="M12 7v5l3 3"/></svg>
                          <div class="t-content"><div class="t-label"><?= e(t('odometer_km')) ?></div><div class="t-value"><?= e($odoStr ?: '-') ?></div></div>
                        </div>
                        <div class="tile">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                          <div class="t-content"><div class="t-label"><?= e(t('next_due_date')) ?></div><div class="t-value"><?= e($nextDueDateStr ?: '-') ?></div></div>
                        </div>
                        <div class="tile">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/><path d="M12 7v5l3 3"/></svg>
                          <div class="t-content"><div class="t-label"><?= e(t('next_due_km')) ?></div><div class="t-value"><?= e($nextDueKmStr ?: '-') ?></div></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="svc-divider"></div>
                  <div class="svc-grand-total"><?= e($totalStr) ?></div>
                  <div class="svc-actions" style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.8rem;">
                    <a href="/services/edit.php?id=<?= e((string)$eid) ?>" class="btn-secondary" style="padding:0.4rem 0.7rem;display:inline-flex;align-items:center;gap:0.4rem;">
                      <?= icon_svg('edit') ?> <span><?= e(t('edit_service')) ?></span>
                    </a>
                    <form method="post" action="/services/delete.php" onsubmit="return confirm('<?= e(t('confirm_delete_service')) ?>');" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= e((string)$eid) ?>">
                      <button type="submit" class="btn-secondary" style="padding:0.4rem 0.7rem;display:inline-flex;align-items:center;gap:0.4rem;background:transparent;color:#dc3545;border-color:#dc3545;">
                        <?= icon_svg('trash') ?> <span><?= e(t('delete_service')) ?></span>
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php $hasMore = ($totalCount > $limit + $offset); ?>
            <?php if ($hasMore): ?>
              <div style="display:flex;justify-content:center;margin-top:0.75rem;">
                <button id="loadMoreSvc" class="btn-secondary"><?= e(t('load_more') ?? 'Mehr laden') ?></button>
              </div>
              <script>
                (function(){
                  var btn = document.getElementById('loadMoreSvc');
                  var grid = document.getElementById('svcGrid');
                  var offset = <?= (int)($offset + $limit) ?>;
                  var limit = <?= (int)$limit ?>;
                  var vid = <?= (int)$id ?>;
                  if(btn && grid){
                    btn.addEventListener('click', function(){
                      btn.disabled = true;
                      fetch('/vehicles/view.php?id='+vid+'&recent=1&offset='+offset, {headers:{'X-Requested-With':'XMLHttpRequest'}})
                        .then(function(r){ return r.text(); })
                        .then(function(html){
                          var tmp = document.createElement('div');
                          tmp.innerHTML = html;
                          var batch = tmp.querySelector('.svc-batch');
                          var cards = batch ? batch.querySelectorAll('.svc-card') : tmp.querySelectorAll('.svc-card');
                          if(cards.length === 0){ btn.style.display='none'; return; }
                          cards.forEach(function(c){ grid.appendChild(c); });
                          offset += limit;
                          var hasMore = batch ? (batch.getAttribute('data-has-more') === '1') : (cards.length === limit);
                          if (!hasMore) { btn.style.display='none'; } else { btn.disabled = false; }
                        })
                        .catch(function(){ btn.disabled=false; });
                    });
                  }
                })();
              </script>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
