<?php
// map.php - interactive hostel map built from the rooms table
require_once __DIR__ . '/includes/auth.php';
$rows = $pdo->query('SELECT r.room_id, r.room_number, r.floor, r.status, t.type_name, t.price_per_month FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id ORDER BY r.room_number')->fetchAll();
$blocks = [];
foreach ($rows as $r) { $b = strtoupper(substr($r['room_number'], 0, 1)); $blocks[$b][(int)$r['floor']][] = $r; }
ksort($blocks);
$hl = (int)($_GET['room'] ?? 0);
$pageTitle = 'Hostel map'; $area = 'public';
include __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px">
  <div><h1>Hostel map</h1><p class="muted">Every room, live. Hover for details and click a room to open it.</p></div>
  <a class="btn btn2" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(urlencode(HOSTEL_ADDRESS)) ?>">Open location in Google Maps</a>
</div>
<div class="legend2"><span><i style="background:#4CC58F"></i>Vacant</span><span><i style="background:#F0A5A5"></i>Occupied</span><span><i style="background:#EBC77F"></i>Under maintenance</span></div>
<div class="grid g4">
  <?php foreach ($blocks as $b => $floors): $all = array_merge(...array_values($floors)); $vac = count(array_filter($all, fn($x) => $x['status'] === 'vacant')); ?>
  <a class="card stat" href="#block-<?= e($b) ?>" style="text-decoration:none"><p class="muted">Block <?= e($b) ?></p><p class="n"><?= count($all) ?> rooms</p><p class="muted"><?= $vac ?> vacant</p></a>
  <?php endforeach; ?>
</div>
<div class="hmap">
  <?php foreach ($blocks as $b => $floors): krsort($floors); ?>
  <section class="block reveal" id="block-<?= e($b) ?>"><h2>Block <?= e($b) ?></h2>
    <?php foreach ($floors as $fl => $list): ?>
    <div class="floorrow"><span class="lab"><?= (int)$fl === 0 ? 'Ground' : 'Floor ' . (int)$fl ?></span>
      <?php foreach ($list as $r): ?>
      <?php $publicName = public_room_name((int)$r['room_id'], (string)$r['room_number']); ?>
      <a class="mroom <?= e($r['status']) ?>" href="<?= BASE ?>/room.php?id=<?= (int)$r['room_id'] ?>" style="<?= $hl === (int)$r['room_id'] ? 'outline:4px solid #C9F55A;outline-offset:3px' : '' ?>"
         data-tip="Room <?= e($publicName) ?> - <?= e($r['type_name']) ?> - <?= e(money($r['price_per_month'])) ?> - <?= e(ucfirst($r['status'])) ?>" aria-label="Room <?= e($publicName) ?>, <?= e($r['status']) ?>"><?= e($publicName) ?><small><?= e(substr($r['type_name'], 0, 8)) ?></small></a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
