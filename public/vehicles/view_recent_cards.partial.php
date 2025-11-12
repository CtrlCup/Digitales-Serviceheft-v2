<?php
// Partial: echoes only service entry cards for recent list (used by AJAX load-more)
// Expects variables: $recentEntries, $recentItemsByEntry
if (empty($recentEntries)) { return; }
foreach ($recentEntries as $re):
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
  $sum = 0.0;
  foreach ($items as $it) { $sum += (float)($it['amount'] ?? 0.0); }
  $totalStr = number_format($sum > 0 ? $sum : (float)($re['total_amount'] ?? 0.0), 2, ',', '.') . ' €';
?>
  <div class="svc-card">
    <div class="svc-head">
      <div class="svc-type"><?= e($typeLabel) ?></div>
    </div>
    <div class="svc-body">
      <div class="svc-left">
        <?php if (!empty($items)): ?>
          <table class="svc-items-list"><tbody>
            <?php foreach ($items as $it): $lbl = trim((string)$it['label']); $amt=(float)($it['amount'] ?? 0.0); ?>
            <tr>
              <td><?= e($lbl !== '' ? $lbl : '-') ?></td>
              <td><?= e(number_format($amt, 2, ',', '.')) ?> €</td>
            </tr>
            <?php endforeach; ?>
          </tbody></table>
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
