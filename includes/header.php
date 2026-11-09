<?php
// includes/header.php - expects $pageTitle and $area ('public' | 'admin' | 'tenant')
$area = $area ?? 'public';
$cur = basename($_SERVER['SCRIPT_NAME']);
$role = $_SESSION['role'] ?? '';
if ($role === 'admin') {
    $cmds = [['Dashboard', '/admin/dashboard.php'], ['Rooms', '/admin/rooms.php'], ['Bookings', '/admin/bookings.php'], ['Tenants', '/admin/tenants.php'], ['Payments', '/admin/payments.php'], ['Chat', '/admin/chat.php'], ['Maintenance', '/admin/maintenance.php'], ['Reports', '/admin/reports.php'], ['Audit log', '/admin/audit_log.php'], ['Hostel map', '/map.php'], ['Change password', '/password.php']];
} elseif ($role === 'tenant') {
    $cmds = [['My dashboard', '/tenant/dashboard.php'], ['Pay rent', '/tenant/pay.php'], ['Chat with manager', '/tenant/chat.php'], ['Roommate matches', '/tenant/roommates.php'], ['Hostel map', '/map.php'], ['Browse rooms', '/index.php#rooms'], ['Change password', '/password.php']];
} else {
    $cmds = [['Browse rooms', '/index.php#rooms'], ['Hostel map', '/map.php'], ['Request a booking', '/book.php'], ['Log in', '/login.php']];
}
$navJson = json_encode(array_map(fn($c) => ['t' => $c[0], 'u' => $c[1]], $cmds), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$tools = '<button type="button" class="tool" id="searchBtn" aria-label="Search (Ctrl+K)"><span aria-hidden="true">&#128269;</span></button>'
    . (is_logged_in() ? '<span class="bellwrap"><button type="button" class="tool" id="bell" aria-label="Notifications" aria-haspopup="true"><span aria-hidden="true">&#128276;</span><span class="badge-n" hidden>0</span></button><div id="bellMenu" hidden></div></span>' : '')
    . '<button type="button" class="tool theme-toggle" aria-label="Toggle dark mode">&#9790;</button>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? APP_NAME) ?> | <?= APP_NAME ?></title>
<script>try{var t=localStorage.getItem('cn-theme');if(!t&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches){t='dark'}if(t){document.documentElement.setAttribute('data-theme',t)}}catch(e){}</script>
<link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE ?>/assets/css/modern.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>" data-base="<?= e(BASE) ?>" data-role="<?= e($role) ?>" data-nav="<?= e($navJson) ?>">
<div id="progress"></div>
<noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
<?php if ($area === 'admin'): ?>
<div class="layout">
  <aside class="side">
    <a class="logo" href="<?= BASE ?>/admin/dashboard.php">Campus<b>Nest</b></a>
    <div class="tools"><?= $tools ?></div>
    <?php foreach (['dashboard'=>'Dashboard','rooms'=>'Rooms','bookings'=>'Bookings','tenants'=>'Tenants','payments'=>'Payments','chat'=>'Chat','maintenance'=>'Maintenance','reports'=>'Reports','audit_log'=>'Audit log'] as $f => $label): ?>
      <a href="<?= BASE ?>/admin/<?= $f ?>.php" class="<?= $cur === $f . '.php' ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <a href="<?= BASE ?>/map.php">Hostel map</a>
    <a href="<?= BASE ?>/password.php">Change password</a>
    <a href="<?= BASE ?>/logout.php">Log out</a>
  </aside>
  <main class="content">
<?php else: ?>
<header class="nav">
  <a class="logo" href="<?= BASE ?>/index.php">Campus<b>Nest</b></a>
  <button id="navToggle" type="button" aria-label="Menu" aria-expanded="false">&#9776;</button>
  <nav class="links" aria-label="Main">
    <a href="<?= BASE ?>/index.php#rooms">Rooms</a>
    <a href="<?= BASE ?>/map.php">Map</a>
    <?php if ($role === 'tenant'): ?>
      <a href="<?= BASE ?>/tenant/dashboard.php">Dashboard</a>
      <a href="<?= BASE ?>/tenant/pay.php">Pay rent</a>
      <a href="<?= BASE ?>/tenant/chat.php">Chat</a>
      <a href="<?= BASE ?>/tenant/roommates.php">Roommates</a>
    <?php elseif ($role === 'admin'): ?>
      <a href="<?= BASE ?>/admin/dashboard.php">Admin</a>
    <?php endif; ?>
    <?= $tools ?>
    <?php if (is_logged_in()): ?>
      <a class="btn btn2" href="<?= BASE ?>/logout.php">Log out</a>
    <?php else: ?>
      <a href="<?= BASE ?>/book.php">Request a booking</a>
      <a class="btn btn2" href="<?= BASE ?>/login.php">Log in</a>
    <?php endif; ?>
  </nav>
</header>
<main class="<?= !empty($full) ? 'full' : 'wrap' ?>">
<?php endif; ?>
<?php show_flash(); ?>
