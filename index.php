<?php
require_once __DIR__ . '/includes/auth.php';
function skyline(int $seed, string $fill, int $minH, int $maxH, string $op): string {
    mt_srand($seed); $x = 0; $out = '';
    while ($x < 1600) { $w = mt_rand(50, 120); $h = mt_rand($minH, $maxH); $out .= '<rect x="' . $x . '" y="' . (260 - $h) . '" width="' . ($w - 4) . '" height="' . $h . '" rx="3"/>'; $x += $w; }
    return '<svg viewBox="0 0 1600 260" preserveAspectRatio="none" fill="' . $fill . '" opacity="' . $op . '" aria-hidden="true">' . $out . '</svg>';
}
$q = trim((string)($_GET['q'] ?? ''));
$type = (int)($_GET['type'] ?? 0);
$max = (float)($_GET['max'] ?? 0);
$rooms = search_rooms($pdo, $q, $type, $max);
$types = $pdo->query('SELECT room_type_id, type_name, price_per_month FROM room_types ORDER BY price_per_month')->fetchAll();
$s = $pdo->query("SELECT COUNT(*) AS total, SUM(status = 'vacant') AS vacant FROM rooms")->fetch();
$feat = $pdo->query("SELECT r.room_id, r.room_number, t.type_name, t.price_per_month FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE r.status = 'vacant' ORDER BY r.room_id LIMIT 3")->fetchAll();
$calcTypes = array_map(fn($t) => ['id' => (int)$t['room_type_id'], 'name' => $t['type_name'], 'price' => (float)$t['price_per_month']], $types);
$pageTitle = 'Find your room'; $area = 'public'; $extraJs = 'rooms.js'; $bodyClass = 'home'; $full = true;
include __DIR__ . '/includes/header.php';
?>
<section class="hero" id="top">
  <div class="layer blob b1" data-speed="0.25" data-depth="0.6"></div>
  <div class="layer blob b2" data-speed="0.4" data-depth="1"></div>
  <div class="layer blob b3" data-speed="0.15" data-depth="0.4"></div>
  <div class="layer sky" data-speed="0.5"><?= skyline(7, '#1FAE74', 90, 200, '0.35') ?></div>
  <div class="layer sky" data-speed="0.3"><?= skyline(21, '#0F6B46', 70, 150, '0.7') ?></div>
  <div class="layer sky" data-speed="0.12"><?= skyline(42, '#0A3D2A', 40, 100, '1') ?></div>
  <div class="hero-in">
    <div>
      <span class="kicker reveal">Student hostels, made simple</span>
      <h1 class="reveal" style="--d:.1s">Your room near campus, <em>booked in minutes.</em></h1>
      <p class="lead reveal" style="--d:.2s">See what is available right now, send a request, and manage your rent, receipts and repairs online. No more WhatsApp chaos.</p>
      <div class="cta reveal" style="--d:.3s"><a class="btn btn3 big glow" href="#rooms">Browse rooms</a><a class="btn btn2 big" href="<?= BASE ?>/book.php">Request a booking</a></div>
      <div class="stats reveal" style="--d:.4s">
        <div><div class="n" data-count="<?= (int)$s['total'] ?>">0</div><div class="l">Rooms</div></div>
        <div><div class="n" data-count="<?= (int)$s['vacant'] ?>">0</div><div class="l">Available now</div></div>
        <div><div class="n" data-count="<?= $types ? (float)$types[0]['price_per_month'] : 0 ?>" data-prefix="GHS ">0</div><div class="l">Starting from / month</div></div>
      </div>
    </div>
    <div class="float-stack" data-speed="-0.1" data-depth="0.8" aria-label="Rooms available now">
      <?php foreach ($feat as $f): ?>
      <div class="fcard"><div class="t">Available now</div><h3>Room <?= e(public_room_name((int)$f['room_id'], (string)$f['room_number'])) ?></h3><div class="t"><?= e($f['type_name']) ?></div><div class="pr"><?= e(money($f['price_per_month'])) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="scroll-hint">Scroll<i></i></div>
</section>

<div class="marquee" aria-hidden="true"><div><?php for ($i = 0; $i < 2; $i++): ?><span>Live availability</span><span>Instant booking requests</span><span>Numbered receipts</span><span>Repair tracking</span><span>Secure logins</span><span>Rent reminders</span><?php endfor; ?></div></div>

<section class="section" style="background:#fff"><div class="in-w center">
  <span class="kicker reveal">How it works</span>
  <h2 class="title reveal" style="--d:.1s">From search to move-in in four steps</h2>
  <div class="grid g4 steps">
    <?php foreach ([['Browse', 'Filter rooms by type and price and see live availability.'], ['Request', 'Send your details and the dates you want to move in.'], ['Get approved', 'The manager reviews it and gives you a tenant login.'], ['Move in', 'Pay rent, download receipts and report repairs online.']] as $i => $st): ?>
    <div class="step reveal" style="--d:<?= $i * .15 ?>s"><div class="num"><?= $i + 1 ?></div><h3><?= e($st[0]) ?></h3><p><?= e($st[1]) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="band"><div class="in-w">
  <h2 class="reveal">Less paperwork. Fewer disputes. One honest record.</h2>
  <div class="stats">
    <div class="reveal zoom"><div class="n" data-count="<?= (int)$s['total'] ?>">0</div><div class="l">rooms managed</div></div>
    <div class="reveal zoom" style="--d:.15s"><div class="n" data-count="<?= count($types) ?>">0</div><div class="l">room types</div></div>
    <div class="reveal zoom" style="--d:.3s"><div class="n" data-count="100" data-suffix="%">0</div><div class="l">payments on record</div></div>
  </div>
</div></section>

<section class="section" id="rooms"><div class="in-w">
  <span class="kicker reveal">Rooms</span>
  <h2 class="title reveal">Find the room that fits</h2>
  <div class="chips reveal" role="group" aria-label="Room type">
    <button type="button" class="chip <?= $type === 0 ? 'on' : '' ?>" data-type="0" aria-pressed="<?= $type === 0 ? 'true' : 'false' ?>">All</button>
    <?php foreach ($types as $t): ?><button type="button" class="chip <?= $type === (int)$t['room_type_id'] ? 'on' : '' ?>" data-type="<?= (int)$t['room_type_id'] ?>" aria-pressed="<?= $type === (int)$t['room_type_id'] ? 'true' : 'false' ?>"><?= e($t['type_name']) ?></button><?php endforeach; ?>
  </div>
  <form id="roomFilter" class="filterbar reveal" method="get" action="<?= BASE ?>/index.php#rooms">
    <div><label for="q">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Room name, number or type"></div>
    <div><label for="type">Room type</label><select id="type" name="type"><option value="0">All types</option><?php foreach ($types as $t): ?><option value="<?= (int)$t['room_type_id'] ?>" <?= $type === (int)$t['room_type_id'] ? 'selected' : '' ?>><?= e($t['type_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="max">Max price (GHS / month)</label><input id="max" name="max" type="number" min="0" step="1" value="<?= $max > 0 ? e((string)$max) : '' ?>"></div>
    <div><button class="btn" type="submit">Search</button></div>
  </form>
  <div id="roomGrid" class="grid g3" data-base="<?= BASE ?>" style="margin-top:24px">
  <?php if (!$rooms): ?><p class="muted">No rooms match your search.</p><?php endif; ?>
  <?php foreach ($rooms as $i => $r):
      $pend = (int)$r['pending'] > 0;
      $label = $r['status'] === 'vacant' ? ($pend ? 'Pending request' : 'Available') : ucfirst($r['status']);
      $cls = $r['status'] === 'vacant' ? ($pend ? 'warn' : 'ok') : ($r['status'] === 'occupied' ? 'bad' : 'warn'); ?>
    <div class="card room tilt reveal" style="--d:<?= ($i % 3) * .1 ?>s">
      <div class="ph"><?php if ($r['image_path']): ?><img src="<?= BASE ?>/<?= e($r['image_path']) ?>" alt="Room <?= e($r['public_name']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?>Room photo<?php endif; ?></div>
      <div class="in">
        <div style="display:flex;justify-content:space-between;align-items:center"><h2>Room <?= e($r['public_name']) ?></h2><span class="b <?= $cls ?>"><?= e($label) ?></span></div>
        <p class="muted"><?= e($r['type_name']) ?> (sleeps <?= (int)$r['capacity'] ?>) &middot; Floor <?= (int)$r['floor'] ?></p>
        <p class="rating-line"><?php if ((int)$r['reviews'] > 0): ?><span class="stars"><?= e(stars($r['rating'])) ?></span> <?= e((string)$r['rating']) ?> (<?= (int)$r['reviews'] ?>)<?php else: ?>No reviews yet<?php endif; ?></p>
        <p class="price"><?= e(money($r['price_per_month'])) ?> <span class="muted" style="font-weight:400">/ month</span></p>
        <div class="actions"><a class="btn btn2" href="<?= BASE ?>/room.php?id=<?= (int)$r['room_id'] ?>">Details</a><?php if ($r['status'] === 'vacant'): ?><a class="btn" href="<?= BASE ?>/book.php?room_id=<?= (int)$r['room_id'] ?>">Request booking</a><?php endif; ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</div></section>

<section class="section" style="background:#fff"><div class="in-w">
  <div class="center"><span class="kicker reveal">Rent calculator</span><h2 class="title reveal">What will your stay cost?</h2></div>
  <div id="calc" class="calc reveal zoom" data-types="<?= e(json_encode($calcTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <div class="l">
      <div><label for="calcType">Room type</label><select id="calcType"><?php foreach ($types as $t): ?><option value="<?= (int)$t['room_type_id'] ?>"><?= e($t['type_name']) ?></option><?php endforeach; ?></select></div>
      <div><label for="calcMonths">Length of stay: <span id="calcMo">9 months</span></label><input id="calcMonths" type="range" min="1" max="12" value="9"></div>
    </div>
    <div class="r"><span class="muted">Total for your stay</span><div class="big-n" id="calcTotal">GHS 0.00</div><span class="muted" id="calcPer"></span></div>
  </div>
</div></section>

<section class="section"><div class="in-w" style="max-width:760px">
  <div class="center"><span class="kicker reveal">FAQ</span><h2 class="title reveal">Quick answers</h2></div>
  <?php foreach ([['How do I book a room?', 'Pick an available room, press Request booking and fill in the form. The manager reviews it and contacts you.'], ['Can I pay online?', 'Not yet. You pay the manager by cash, mobile money or bank transfer, and the payment is recorded and a numbered receipt is issued.'], ['How long can I stay?', 'You choose from 1 to 12 months when you request the booking.'], ['How do I report a repair?', 'Log in with the tenant account you receive after approval, and send a maintenance request. You can follow its progress.']] as $i => $f): ?>
  <details class="reveal" style="--d:<?= $i * .08 ?>s"><summary><?= e($f[0]) ?></summary><p><?= e($f[1]) ?></p></details>
  <?php endforeach; ?>
</div></section>

<section class="section" style="padding-top:0"><div class="in-w"><div class="cta-band reveal zoom">
  <h2>Ready to find your room?</h2><p style="opacity:.9">Send your request today. It takes about a minute.</p>
  <a class="btn big" href="<?= BASE ?>/book.php">Request a booking</a>
</div></div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
