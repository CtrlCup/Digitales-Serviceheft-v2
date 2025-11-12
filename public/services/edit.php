<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
require_auth();

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$entryId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($entryId <= 0) { header('Location: /dashboard'); exit; }

$vpdo = vehicle_db();
$spdo = service_db();
$error = null; $entry = null; $vehicle = null; $items = [];

try {
  $s = $spdo->prepare('SELECT * FROM service_entries WHERE id = ? LIMIT 1');
  $s->execute([$entryId]);
  $entry = $s->fetch();
  if (!$entry) { throw new RuntimeException(t('service_not_found') ?? 'Service not found'); }
  $vehicleId = (int)$entry['vehicle_id'];
  $v = $vpdo->prepare('SELECT * FROM vehicles WHERE id = ? LIMIT 1');
  $v->execute([$vehicleId]);
  $vehicle = $v->fetch();
  if (!$vehicle || (int)($vehicle['user_id'] ?? 0) !== $uid) { http_response_code(403); die('Forbidden'); }
  $it = $spdo->prepare('SELECT * FROM service_items WHERE entry_id = ? ORDER BY id ASC');
  $it->execute([$entryId]);
  $items = $it->fetchAll() ?: [];
} catch (Throwable $e) { $error = $e->getMessage(); }

// Defaults -> user overrides for intervals
$oilKm = defined('DEFAULT_OIL_INTERVAL_KM') ? (int)DEFAULT_OIL_INTERVAL_KM : 15000;
$oilYr = defined('DEFAULT_OIL_INTERVAL_YEARS') ? (int)DEFAULT_OIL_INTERVAL_YEARS : 1;
$srvKm = defined('DEFAULT_SERVICE_INTERVAL_KM') ? (int)DEFAULT_SERVICE_INTERVAL_KM : 30000;
$srvYr = defined('DEFAULT_SERVICE_INTERVAL_YEARS') ? (int)DEFAULT_SERVICE_INTERVAL_YEARS : 2;
try { $u = db()->prepare('SELECT oil_interval_km,oil_interval_years,service_interval_km,service_interval_years FROM users WHERE id=?'); $u->execute([$uid]); $r=$u->fetch(); if($r){ if($r['oil_interval_km']!==null)$oilKm=(int)$r['oil_interval_km']; if($r['oil_interval_years']!==null)$oilYr=(int)$r['oil_interval_years']; if($r['service_interval_km']!==null)$srvKm=(int)$r['service_interval_km']; if($r['service_interval_years']!==null)$srvYr=(int)$r['service_interval_years']; } } catch(Throwable $__){}

$types = [ 'repair'=>t('service_type_repair'),'oil_change'=>t('service_type_oil_change'),'service'=>t('service_type_service'),'tuev'=>t('service_type_tuev') ];

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!csrf_validate($_POST['csrf'] ?? '')) { throw new RuntimeException(t('csrf_invalid')); }
    $type = trim((string)($_POST['type'] ?? ''));
    $date = trim((string)($_POST['service_date'] ?? ''));
    $odoRaw = (string)($_POST['odometer_km'] ?? ''); $odoSan = preg_replace('/\D+/', '', $odoRaw); $odo = ($odoSan!=='')?(int)$odoSan:null;
    $nxKmRaw = (string)($_POST['next_due_km'] ?? ''); $nxKmSan = preg_replace('/\D+/', '', $nxKmRaw); $nxKm = ($nxKmSan!=='')?(int)$nxKmSan:null;
    $nxDt = trim((string)($_POST['next_due_date'] ?? ''));

    $currKm = isset($vehicle['odometer_km']) && $vehicle['odometer_km']!==null ? (int)$vehicle['odometer_km'] : null;
    $confirmLower = isset($_POST['confirm_lower_odo']) && $_POST['confirm_lower_odo'] === '1';
    if ($odo!==null && $currKm!==null && $odo<$currKm && !$confirmLower) { throw new RuntimeException('Der eingegebene Kilometerstand ist niedriger als der aktuelle.'); }

    if ($type==='service' || $type==='oil_change') {
      $ikm = $type==='oil_change' ? $oilKm : $srvKm; $iy = $type==='oil_change' ? $oilYr : $srvYr;
      if ($nxKm===null && $odo!==null && $ikm>0) $nxKm=$odo+$ikm;
      if ($nxDt==='' && $date!=='' && $iy>0) { $dt=new DateTime($date); $dt->modify('+'.(int)$iy.' years'); $nxDt=$dt->format('Y-m-d'); }
    }

    $labels = $_POST['item_label'] ?? []; $amts = $_POST['item_amount'] ?? []; $notes = $_POST['item_note'] ?? [];
    $newItems=[]; $total=0.0; $n=max(count($labels),count($amts));
    for($i=0;$i<$n;$i++){ $l=isset($labels[$i])?trim((string)$labels[$i]):''; $a=isset($amts[$i])?trim((string)$amts[$i]):''; $no=isset($notes[$i])?trim((string)$notes[$i]):''; if($l==='' && $a==='' && $no==='') continue; $val=(float)str_replace([',',' '],['.',''],$a); if(!is_finite($val))$val=0.0; $newItems[]=['label'=>$l,'amount'=>$val,'note'=>$no]; $total+=$val; }

    $spdo->prepare('UPDATE service_entries SET type=?, service_date=?, odometer_km=?, next_due_km=?, next_due_date=?, total_amount=? WHERE id=?')
         ->execute([$type,$date,($odo!==null?$odo:null),($nxKm!==null?$nxKm:null),($nxDt!==''?$nxDt:null),$total,$entryId]);
    $spdo->prepare('DELETE FROM service_items WHERE entry_id=?')->execute([$entryId]);
    if(!empty($newItems)){ $ins=$spdo->prepare('INSERT INTO service_items (entry_id,label,amount,note) VALUES (?,?,?,?)'); foreach($newItems as $it){ $ins->execute([$entryId,$it['label'],$it['amount'],($it['note']!==''?$it['note']:null)]); } }

    // totals + odometer
    try{ $sum=$spdo->prepare('SELECT SUM(total_amount) s FROM service_entries WHERE vehicle_id=?'); $sum->execute([(int)$entry['vehicle_id']]); $row=$sum->fetch(); $vpdo->prepare('UPDATE vehicles SET total_spent=? WHERE id=?')->execute([(float)($row['s']??0.0),(int)$entry['vehicle_id']]); }catch(Throwable $__){}
    try{
      if($odo!==null){
        $fresh = $vpdo->prepare('SELECT odometer_km FROM vehicles WHERE id = ? LIMIT 1');
        $fresh->execute([(int)$entry['vehicle_id']]);
        $vr = $fresh->fetch();
        $cur = isset($vr['odometer_km']) && $vr['odometer_km']!==null ? (int)$vr['odometer_km'] : null;
        if($cur===null || $odo>$cur || ($odo<$cur && $confirmLower)){
          $vpdo->prepare('UPDATE vehicles SET odometer_km=? WHERE id=?')->execute([$odo,(int)$entry['vehicle_id']]);
        }
      }
    }catch(Throwable $__){}

    $_SESSION['form_success'] = [t('service_updated_success')];
    header('Location: /vehicles/view.php?id='.urlencode((string)$entry['vehicle_id'])); exit;
  } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars(APP_LOCALE) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(t('service_edit_title')) ?> - <?= e(APP_NAME) ?></title>
<?php render_common_head_links(); ?>
</head><body class="page">
<?php render_brand_header(['links'=>[['label'=>t('account_link'),'href'=>'/account/','icon'=>'user','text'=>$user['username']??''],['label'=>t('logout'),'href'=>'/logout','icon'=>'logout']], 'cta'=>['label'=>t('vehicles_back_to_overview'),'href'=>'/dashboard']]); ?>
<main class="page-content"><div class="container-wide reveal-enter">
<div class="card" style="max-width:900px;margin:0 auto;">
<h2 class="card-title"><?= e(t('service_edit_title')) ?></h2>
<?php if ($error): ?><div class="alert" style="color:var(--danger);"><?= e($error) ?></div><?php endif; ?>
<?php if ($entry): ?>
<form method="post" action="/services/edit.php" novalidate>
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="id" value="<?= e((string)$entryId) ?>">
<input type="hidden" id="confirm_lower_odo" name="confirm_lower_odo" value="0">

<div class="row">
  <div class="field"><label><?= e(t('service_type')) ?></label><select name="type" id="type" required>
    <?php foreach ($types as $k=>$lbl): ?><option value="<?= e($k) ?>" <?= $entry['type']===$k?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?>
  </select></div>
  <div class="field"><label><?= e(t('odometer_km')) ?></label><input id="odometer_km" name="odometer_km" type="text" value="<?= e(isset($entry['odometer_km'])&&$entry['odometer_km']!==null?number_format((int)$entry['odometer_km'],0,'','.'):'') ?>" placeholder="0"></div>
  <div class="field"><label><?= e(t('service_date')) ?></label><input id="service_date" name="service_date" type="date" required value="<?= e((string)$entry['service_date']) ?>"></div>
</div>
<div class="row">
  <div class="field" id="field_next_due_km"><label><?= e(t('next_due_km')) ?></label><input id="next_due_km" name="next_due_km" type="text" value="<?= e(isset($entry['next_due_km'])&&$entry['next_due_km']!==null?number_format((int)$entry['next_due_km'],0,'','.'):'') ?>" placeholder="0"></div>
  <div class="field"><label><?= e(t('next_due_date')) ?></label><input id="next_due_date" name="next_due_date" type="date" value="<?= e((string)($entry['next_due_date']??'')) ?>"></div>
</div>

<h3 style="margin-top:1rem;"><?= e(t('service_items') ?? 'Ausgaben/Positionen') ?></h3>
<table id="itemsTable" class="table" style="width:100%;margin-top:0.5rem;"><thead><tr>
  <th style="text-align:left;"><?= e(t('item_label') ?? 'Bezeichnung') ?></th>
  <th style="text-align:right;"><?= e(t('item_amount') ?? 'Betrag') ?></th>
  <th style="text-align:left;"><?= e(t('item_note') ?? 'Notiz') ?></th>
  <th></th>
</tr></thead><tbody>
<?php if(!empty($items)): foreach($items as $it): ?>
<tr><td><input name="item_label[]" type="text" value="<?= e((string)$it['label']) ?>" placeholder="<?= e(t('item_label_ph') ?? 'z.B. Ölfilter') ?>"></td>
<td><input name="item_amount[]" type="text" inputmode="decimal" value="<?= e(number_format((float)$it['amount'],2,',','')) ?>" style="text-align:right;" placeholder="0,00"></td>
<td><input name="item_note[]" type="text" value="<?= e((string)($it['note']??'')) ?>" placeholder="<?= e(t('item_note_ph') ?? 'optional') ?>"></td>
<td><button type="button" class="btn-secondary removeRow" style="padding:0.25rem 0.5rem;">&times;</button></td></tr>
<?php endforeach; else: ?>
<tr><td><input name="item_label[]" type="text" placeholder="<?= e(t('item_label_ph') ?? 'z.B. Ölfilter') ?>"></td>
<td><input name="item_amount[]" type="text" inputmode="decimal" style="text-align:right;" placeholder="0,00"></td>
<td><input name="item_note[]" type="text" placeholder="<?= e(t('item_note_ph') ?? 'optional') ?>"></td>
<td><button type="button" class="btn-secondary removeRow" style="padding:0.25rem 0.5rem;">&times;</button></td></tr>
<?php endif; ?></tbody></table>
<button type="button" id="addItemBtn" class="btn-secondary" style="margin-top:0.5rem;">+ <?= e(t('add_item') ?? 'Position hinzufügen') ?></button>

<div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
  <a href="/vehicles/view.php?id=<?= e((string)$entry['vehicle_id']) ?>" class="btn-secondary" style="opacity:.85;"><?= e(t('cancel') ?? 'Abbrechen') ?></a>
  <button type="submit" class="btn-primary"><?= e(t('save') ?? 'Speichern') ?></button>
</div>
</form>
<?php endif; ?>
</div></div></main>
<script>
(function(){
  function fmtKm(v){var d=(v||'').replace(/\D+/g,'');return d?d.replace(/\B(?=(\d{3})+(?!\d))/g,'.'):''}
  function bindKm(id){var el=document.getElementById(id);if(!el)return;el.addEventListener('input',function(){var p=el.selectionStart;el.value=fmtKm(el.value);try{el.setSelectionRange(p,p);}catch(e){}});el.addEventListener('blur',function(){el.value=fmtKm(el.value)});el.value=fmtKm(el.value)}
  bindKm('odometer_km'); bindKm('next_due_km');
  var typeEl=document.getElementById('type'), ndKm=document.getElementById('field_next_due_km');
  function update(){ if((typeEl.value||'')==='tuev'){ ndKm.style.display='none'; document.getElementById('next_due_km').value=''; } else { ndKm.style.display=''; } }
  typeEl.addEventListener('change',update); update();
  var form=document.querySelector('form[action="/services/edit.php"]');
  if(form){ form.addEventListener('submit',function(e){
    var odo=document.getElementById('odometer_km'); var nx=document.getElementById('next_due_km');
    if(odo) odo.value=(odo.value||'').replace(/\D+/g,''); if(nx) nx.value=(nx.value||'').replace(/\D+/g,'');
    var cur=<?= isset($vehicle['odometer_km'])&&$vehicle['odometer_km']!==null?(int)$vehicle['odometer_km']:0 ?>; var val=odo&&odo.value?parseInt(odo.value,10):null; var c=document.getElementById('confirm_lower_odo');
    if(val!==null && cur && val<cur){ if(!confirm('Der eingegebene Kilometerstand ist niedriger als der aktuelle. Wirklich übernehmen?')){ if(c) c.value='0'; e.preventDefault(); return false; } if(c) c.value='1'; }
  }); }
  var addBtn=document.getElementById('addItemBtn'); var tbody=document.querySelector('#itemsTable tbody');
  addBtn&&addBtn.addEventListener('click',function(){ var tr=document.createElement('tr'); tr.innerHTML='<td><input name="item_label[]" type="text" placeholder="<?= e(t('item_label_ph') ?? 'z.B. Ölfilter') ?>"></td><td><input name="item_amount[]" type="text" inputmode="decimal" style="text-align:right;" placeholder="0,00"></td><td><input name="item_note[]" type="text" placeholder="<?= e(t('item_note_ph') ?? 'optional') ?>"></td><td><button type="button" class="btn-secondary removeRow" style="padding:0.25rem 0.5rem;">&times;</button></td>'; tbody.appendChild(tr); });
  tbody&&tbody.addEventListener('click',function(e){ if(e.target&&e.target.classList.contains('removeRow')){ var tr=e.target.closest('tr'); if(tr) tr.remove(); } });
})();
</script>
</body></html>
