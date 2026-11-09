<?php
// room.php - public room details: gallery, 360 view, reviews
require_once __DIR__ . '/includes/auth.php';
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT r.*, t.type_name, t.capacity, t.price_per_month, t.description,
    (SELECT ROUND(AVG(rating),1) FROM reviews WHERE room_id = r.room_id) AS rating, (SELECT COUNT(*) FROM reviews WHERE room_id = r.room_id) AS reviews
    FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE r.room_id = ?');
$st->execute([$id]); $r = $st->fetch();
if (!$r) { http_response_code(404); exit('Room not found.'); }
$r['image_path'] = public_room_image((int)$r['room_id'], (string)$r['room_number'], $r['image_path']);
$publicName = public_room_name((int)$r['room_id'], (string)$r['room_number']);
$ph = $pdo->prepare('SELECT path FROM room_photos WHERE room_id = ? ORDER BY photo_id'); $ph->execute([$id]);
$photos = $ph->fetchAll(PDO::FETCH_COLUMN);
if ($r['image_path'] && !in_array($r['image_path'], $photos, true)) { array_unshift($photos, $r['image_path']); }
$photos = array_slice($photos, 0, 5);
$rv = $pdo->prepare('SELECT v.rating, v.comment, v.created_at, u.full_name FROM reviews v JOIN tenants t ON t.tenant_id = v.tenant_id JOIN users u ON u.user_id = t.user_id WHERE v.room_id = ? ORDER BY v.created_at DESC LIMIT 20');
$rv->execute([$id]); $reviews = $rv->fetchAll();
$pageTitle = 'Room ' . $publicName; $area = 'public';
include __DIR__ . '/includes/header.php';
?>
<p><a href="<?= BASE ?>/index.php#rooms">&larr; All rooms</a></p>
<div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px">
  <div><h1>Room <?= e($publicName) ?> <?= badge($r['status']) ?></h1>
    <p class="rating-line"><?php if ((int)$r['reviews'] > 0): ?><span class="stars"><?= e(stars($r['rating'])) ?></span> <?= e((string)$r['rating']) ?> from <?= (int)$r['reviews'] ?> review(s)<?php else: ?>No reviews yet<?php endif; ?></p></div>
  <div class="actions"><a class="btn btn2" href="<?= BASE ?>/map.php?room=<?= (int)$r['room_id'] ?>">Find on map</a><?php if ($r['status'] === 'vacant'): ?><a class="btn big" href="<?= BASE ?>/book.php?room_id=<?= (int)$r['room_id'] ?>">Request booking</a><?php endif; ?></div>
</div>
<div class="gallery" data-gallery>
  <?php if (!$photos): ?><div class="ph">No photos uploaded yet</div>
  <?php else: foreach ($photos as $i => $p): ?><img class="<?= $i === 0 ? 'big' : '' ?>" src="<?= BASE ?>/<?= e($p) ?>" data-full="<?= BASE ?>/<?= e($p) ?>" alt="Room <?= e($publicName) ?> photo <?= $i + 1 ?>"><?php endforeach; endif; ?>
</div>
<div class="grid g3" style="align-items:start">
  <div class="card"><h2>Details</h2>
    <table><tr><td class="muted">Type</td><td><?= e($r['type_name']) ?></td></tr><tr><td class="muted">Sleeps</td><td><?= (int)$r['capacity'] ?></td></tr><tr><td class="muted">Floor</td><td><?= (int)$r['floor'] ?></td></tr>
    <tr><td class="muted">Price</td><td><strong><?= e(money($r['price_per_month'])) ?></strong> / month</td></tr></table>
    <?php if ($r['description']): ?><p class="muted" style="margin-top:10px"><?= e($r['description']) ?></p><?php endif; ?></div>
  <div style="grid-column:span 2">
    <?php if ($r['pano_path']): ?>
    <div class="pano" data-src="<?= BASE ?>/<?= e($r['pano_path']) ?>" tabindex="0" role="img" aria-label="360 degree view of room <?= e($publicName) ?>. Drag or use the arrow keys to look around."><span class="tag">360&deg; tour</span><span class="hint">Drag to look around</span></div>
    <?php else: ?><div class="card"><h2>360&deg; tour</h2><p class="muted" style="margin-top:6px">The manager has not uploaded a 360&deg; photo for this room yet.</p></div><?php endif; ?>
  </div>
</div>
<div class="card"><h2>Tenant reviews</h2>
  <?php if (!$reviews): ?><p class="muted" style="margin-top:8px">No reviews yet. Tenants can review their room from their dashboard.</p><?php endif; ?>
  <?php foreach ($reviews as $v): ?><div class="review"><span class="stars"><?= e(stars($v['rating'])) ?></span> <strong><?= e(explode(' ', $v['full_name'])[0]) ?></strong> <span class="muted"><?= e(substr($v['created_at'], 0, 10)) ?></span><?php if ($v['comment']): ?><p><?= e($v['comment']) ?></p><?php endif; ?></div><?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
