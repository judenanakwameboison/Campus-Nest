<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$statuses = ['vacant', 'occupied', 'maintenance'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = post('action');
    try {
        if ($a === 'add_type') {
            $name = post('type_name'); $cap = (int)post('capacity'); $price = (float)post('price_per_month');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 50 || $cap < 1 || $cap > 10 || $price <= 0) { throw new RuntimeException('Check the room type details.'); }
            $pdo->prepare('INSERT INTO room_types (type_name, capacity, price_per_month, description) VALUES (?,?,?,?)')->execute([$name, $cap, $price, post('description')]);
            audit($pdo, 'room_type_added', 'room_types', (int)$pdo->lastInsertId(), $name);
            flash('success', 'Room type added.');
        } elseif ($a === 'set_price') {
            $price = (float)post('price_per_month');
            if ($price <= 0) { throw new RuntimeException('Price must be greater than zero.'); }
            $pdo->prepare('UPDATE room_types SET price_per_month = ? WHERE room_type_id = ?')->execute([$price, (int)post('room_type_id')]);
            audit($pdo, 'price_changed', 'room_types', (int)post('room_type_id'), (string)$price);
            flash('success', 'Price updated. Existing leases keep their agreed rent.');
        } elseif ($a === 'add_room') {
            $num = strtoupper(post('room_number')); $floor = (int)post('floor'); $status = post('status');
            if (!preg_match('/^[A-Z0-9-]{1,10}$/', $num) || $floor < -1 || $floor > 20 || !in_array($status, $statuses, true) || $status === 'occupied') { throw new RuntimeException('Check the room details.'); }
            $pdo->prepare('INSERT INTO rooms (room_type_id, room_number, floor, status) VALUES (?,?,?,?)')->execute([(int)post('room_type_id'), $num, $floor, $status]);
            audit($pdo, 'room_added', 'rooms', (int)$pdo->lastInsertId(), $num);
            flash('success', "Room $num added.");
        } elseif ($a === 'set_status') {
            $status = post('status'); $rid = (int)post('room_id');
            $c = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE room_id = ? AND status = 'active'"); $c->execute([$rid]);
            $active = (int)$c->fetchColumn();
            if (!in_array($status, $statuses, true)) { throw new RuntimeException('Invalid status.'); }
            if ($active > 0 && $status !== 'occupied') { throw new RuntimeException('This room has an active tenant. End the lease first.'); }
            $pdo->prepare('UPDATE rooms SET status = ? WHERE room_id = ?')->execute([$status, $rid]);
            audit($pdo, 'room_status_changed', 'rooms', $rid, $status);
            flash('success', 'Room status updated.');
        } elseif ($a === 'upload_photo' || $a === 'upload_pano') {
            $rid = (int)post('room_id');
            $room = $pdo->prepare('SELECT image_path, pano_path FROM rooms WHERE room_id = ?'); $room->execute([$rid]); $room = $room->fetch();
            if (!$room) { throw new RuntimeException('Room not found.'); }
            $f = $_FILES['photo'] ?? null;
            if (!$f) { throw new RuntimeException('Choose an image to upload.'); }
            $multi = is_array($f['name']); $count = $multi ? count($f['name']) : 1; $saved = 0;
            for ($i = 0; $i < $count; $i++) {
                $err = $multi ? $f['error'][$i] : $f['error'];
                if ($err === UPLOAD_ERR_NO_FILE) { continue; }
                if ($err !== UPLOAD_ERR_OK) { throw new RuntimeException('An upload failed. Try a smaller file.'); }
                $path = save_room_image($multi ? $f['tmp_name'][$i] : $f['tmp_name'], (int)($multi ? $f['size'][$i] : $f['size']), $rid, $a === 'upload_pano' ? 8388608 : 3145728);
                if ($a === 'upload_pano') {
                    delete_room_file($room['pano_path']);
                    $pdo->prepare('UPDATE rooms SET pano_path = ? WHERE room_id = ?')->execute([$path, $rid]);
                } else {
                    $pdo->prepare('INSERT INTO room_photos (room_id, path) VALUES (?, ?)')->execute([$rid, $path]);
                    if (!$room['image_path']) { $pdo->prepare('UPDATE rooms SET image_path = ? WHERE room_id = ?')->execute([$path, $rid]); $room['image_path'] = $path; }
                }
                $saved++;
            }
            if ($saved === 0) { throw new RuntimeException('Choose an image to upload.'); }
            audit($pdo, $a === 'upload_pano' ? 'room_360_uploaded' : 'room_photo_uploaded', 'rooms', $rid, $saved . ' file(s)');
            flash('success', $saved . ' image(s) uploaded.');
            redirect('/admin/rooms.php?photos=' . $rid . '#photos');
        } elseif ($a === 'delete_photo') {
            $rid = (int)post('room_id'); $pid = (int)post('photo_id');
            $q = $pdo->prepare('SELECT path FROM room_photos WHERE photo_id = ? AND room_id = ?'); $q->execute([$pid, $rid]); $path = $q->fetchColumn();
            if ($path) {
                $pdo->prepare('DELETE FROM room_photos WHERE photo_id = ?')->execute([$pid]);
                $use = $pdo->prepare('SELECT COUNT(*) FROM room_photos WHERE path = ?'); $use->execute([$path]);
                if ((int)$use->fetchColumn() === 0) { delete_room_file($path); }
                $cover = $pdo->prepare('SELECT image_path FROM rooms WHERE room_id = ?'); $cover->execute([$rid]);
                if ($cover->fetchColumn() === $path) {
                    $nx = $pdo->prepare('SELECT path FROM room_photos WHERE room_id = ? ORDER BY photo_id LIMIT 1'); $nx->execute([$rid]);
                    $pdo->prepare('UPDATE rooms SET image_path = ? WHERE room_id = ?')->execute([$nx->fetchColumn() ?: null, $rid]);
                }
                flash('success', 'Photo removed.');
            }
            redirect('/admin/rooms.php?photos=' . $rid . '#photos');
        } elseif ($a === 'remove_pano') {
            $rid = (int)post('room_id');
            $q = $pdo->prepare('SELECT pano_path FROM rooms WHERE room_id = ?'); $q->execute([$rid]);
            delete_room_file($q->fetchColumn() ?: null);
            $pdo->prepare('UPDATE rooms SET pano_path = NULL WHERE room_id = ?')->execute([$rid]);
            flash('success', '360 view removed.');
            redirect('/admin/rooms.php?photos=' . $rid . '#photos');
        } elseif ($a === 'delete_room') {
            $rid = (int)post('room_id');
            $pdo->prepare('DELETE FROM rooms WHERE room_id = ?')->execute([$rid]);
            audit($pdo, 'room_deleted', 'rooms', $rid);
            flash('success', 'Room deleted.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('error', $ex->getCode() === '23000' ? 'That room number or type already exists, or the record is in use and cannot be deleted.' : 'Database error.');
    }
    redirect('/admin/rooms.php');
}
$types = $pdo->query('SELECT * FROM room_types ORDER BY price_per_month')->fetchAll();
$rooms = $pdo->query('SELECT r.*, t.type_name, t.price_per_month FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id ORDER BY r.room_number')->fetchAll();
$pageTitle = 'Rooms'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Rooms and room types</h1>
<div class="grid g2">
  <form class="card form" method="post" data-validate><h2>Add room type</h2><?= csrf_field() ?><input type="hidden" name="action" value="add_type">
    <div><label for="type_name">Name</label><input id="type_name" name="type_name" required minlength="2" maxlength="50"></div>
    <div class="row"><div><label for="capacity">Sleeps</label><input id="capacity" name="capacity" type="number" min="1" max="10" required></div>
    <div><label for="price_per_month">Price / month (GHS)</label><input id="price_per_month" name="price_per_month" type="number" min="1" step="0.01" required></div></div>
    <div><label for="description">Description</label><input id="description" name="description" maxlength="200"></div>
    <button class="btn" type="submit">Add type</button></form>
  <form class="card form" method="post" data-validate><h2>Add room</h2><?= csrf_field() ?><input type="hidden" name="action" value="add_room">
    <div class="row"><div><label for="room_number">Room number</label><input id="room_number" name="room_number" required maxlength="10" placeholder="A101"></div>
    <div><label for="floor">Floor</label><input id="floor" name="floor" type="number" min="-1" max="20" value="0" required></div></div>
    <div><label for="room_type_id">Type</label><select id="room_type_id" name="room_type_id" required><?php foreach ($types as $t): ?><option value="<?= (int)$t['room_type_id'] ?>"><?= e($t['type_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="vacant">Vacant</option><option value="maintenance">Under maintenance</option></select></div>
    <button class="btn" type="submit">Add room</button></form>
</div>
<div class="card"><h2>Room types</h2><table><tr><th>Type</th><th>Sleeps</th><th>Price / month</th><th>Change price</th></tr>
<?php foreach ($types as $t): ?><tr><td><?= e($t['type_name']) ?></td><td><?= (int)$t['capacity'] ?></td><td><?= e(money($t['price_per_month'])) ?></td>
<td><form method="post" class="actions" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="set_price"><input type="hidden" name="room_type_id" value="<?= (int)$t['room_type_id'] ?>">
<input name="price_per_month" type="number" min="1" step="0.01" required style="width:120px;min-height:36px" aria-label="New price"><button class="btn sm" type="submit">Save</button></form></td></tr><?php endforeach; ?></table></div>
<?php if ($mg = (int)($_GET['photos'] ?? 0)):
    $mr = $pdo->prepare('SELECT room_id, room_number, image_path, pano_path FROM rooms WHERE room_id = ?'); $mr->execute([$mg]); $mr = $mr->fetch();
    if ($mr): $mp = $pdo->prepare('SELECT photo_id, path FROM room_photos WHERE room_id = ? ORDER BY photo_id'); $mp->execute([$mg]); $mphotos = $mp->fetchAll(); ?>
<div class="card" id="photos"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px"><h2>Photos and 360 view: Room <?= e($mr['room_number']) ?></h2><a href="rooms.php">Close</a></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin:14px 0">
    <?php if (!$mphotos): ?><p class="muted">No photos yet.</p><?php endif; ?>
    <?php foreach ($mphotos as $ph): ?><div><img src="<?= BASE ?>/<?= e($ph['path']) ?>" alt="" style="width:100%;height:100px;object-fit:cover;border-radius:12px">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="room_id" value="<?= (int)$mr['room_id'] ?>"><input type="hidden" name="photo_id" value="<?= (int)$ph['photo_id'] ?>"><button class="btn btn4 sm" type="submit" style="margin-top:6px;width:100%">Remove</button></form></div><?php endforeach; ?>
  </div>
  <div class="grid g2">
    <form method="post" enctype="multipart/form-data" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="upload_photo"><input type="hidden" name="room_id" value="<?= (int)$mr['room_id'] ?>">
      <div><label for="photo">Add photos (JPG, PNG, WebP, up to 3 MB each; pick several)</label><input id="photo" type="file" name="photo[]" accept="image/jpeg,image/png,image/webp" multiple required></div><button class="btn" type="submit">Upload photos</button></form>
    <form method="post" enctype="multipart/form-data" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="upload_pano"><input type="hidden" name="room_id" value="<?= (int)$mr['room_id'] ?>">
      <div><label for="pano">360 view: one wide panorama photo (up to 8 MB)</label><input id="pano" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
      <div class="actions"><button class="btn" type="submit"><?= $mr['pano_path'] ? 'Replace 360 view' : 'Upload 360 view' ?></button></div></form>
  </div>
  <?php if ($mr['pano_path']): ?><form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="remove_pano"><input type="hidden" name="room_id" value="<?= (int)$mr['room_id'] ?>"><button class="btn btn4 sm" type="submit">Remove 360 view</button></form><?php endif; ?>
</div>
<?php endif; endif; ?>
<div class="card"><h2>All rooms</h2><table><tr><th>Room</th><th>Type</th><th>Floor</th><th>Price</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($rooms as $r): ?><tr><td><strong><?= e($r['room_number']) ?></strong></td><td><?= e($r['type_name']) ?></td><td><?= (int)$r['floor'] ?></td><td><?= e(money($r['price_per_month'])) ?></td><td><?= badge($r['status']) ?></td>
<td class="actions"><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="room_id" value="<?= (int)$r['room_id'] ?>">
<select name="status" style="width:150px;min-height:36px" aria-label="Status for room <?= e($r['room_number']) ?>"><?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select><button class="btn sm" type="submit">Update</button></form>
<a class="btn btn3 sm" href="rooms.php?photos=<?= (int)$r['room_id'] ?>#photos">Photos / 360</a>
<form method="post" onsubmit="return confirm('Delete room <?= e($r['room_number']) ?>?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_room"><input type="hidden" name="room_id" value="<?= (int)$r['room_id'] ?>"><button class="btn btn4 sm" type="submit">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
