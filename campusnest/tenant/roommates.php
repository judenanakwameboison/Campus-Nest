<?php
// tenant/roommates.php - lifestyle profile and compatibility matches
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
function match_score(array $a, array $b): int {
    $sleep = ['early' => 0, 'flexible' => 1, 'night' => 2]; $study = ['quiet' => 0, 'mixed' => 1, 'social' => 2];
    $p = abs($sleep[$a['sleep_pattern']] - $sleep[$b['sleep_pattern']]) * 14
       + abs($study[$a['study_style']] - $study[$b['study_style']]) * 12
       + abs((int)$a['tidiness'] - (int)$b['tidiness']) * 7
       + abs((int)$a['social_level'] - (int)$b['social_level']) * 5;
    return max(0, 100 - $p);
}
$st = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = ? AND status = 'active'");
$st->execute([$_SESSION['user_id']]); $tid = (int)$st->fetchColumn();
if ($tid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sleep = post('sleep_pattern'); $study = post('study_style'); $tidy = (int)post('tidiness'); $soc = (int)post('social_level'); $bio = mb_substr(post('bio'), 0, 200);
    if (!in_array($sleep, ['early', 'flexible', 'night'], true) || !in_array($study, ['quiet', 'mixed', 'social'], true) || $tidy < 1 || $tidy > 5 || $soc < 1 || $soc > 5) { flash('error', 'Please answer every question.'); }
    else {
        $pdo->prepare('INSERT INTO roommate_profiles (tenant_id, sleep_pattern, study_style, tidiness, social_level, bio) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sleep_pattern = VALUES(sleep_pattern), study_style = VALUES(study_style), tidiness = VALUES(tidiness), social_level = VALUES(social_level), bio = VALUES(bio)')
            ->execute([$tid, $sleep, $study, $tidy, $soc, $bio !== '' ? $bio : null]);
        flash('success', 'Your lifestyle profile is saved.');
    }
    redirect('/tenant/roommates.php');
}
$me = null; $matches = [];
if ($tid) {
    $q = $pdo->prepare('SELECT * FROM roommate_profiles WHERE tenant_id = ?'); $q->execute([$tid]); $me = $q->fetch();
    if ($me) {
        $others = $pdo->prepare("SELECT p.*, u.full_name, rt.type_name FROM roommate_profiles p JOIN tenants t ON t.tenant_id = p.tenant_id AND t.status = 'active' JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id JOIN room_types rt ON rt.room_type_id = r.room_type_id WHERE p.tenant_id <> ?");
        $others->execute([$tid]);
        foreach ($others->fetchAll() as $o) { $o['score'] = match_score($me, $o); $matches[] = $o; }
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
    }
}
$labels = ['early' => 'Early bird', 'flexible' => 'Flexible sleeper', 'night' => 'Night owl', 'quiet' => 'Quiet studier', 'mixed' => 'Mixed study', 'social' => 'Group studier'];
$pageTitle = 'Roommate matching'; $area = 'public';
include __DIR__ . '/../includes/header.php';
?>
<h1>Roommate matching</h1>
<?php if (!$tid): ?><div class="card"><p>You need an active lease to use roommate matching.</p></div><?php else: ?>
<div class="grid g2" style="align-items:start">
  <form class="card form" method="post" data-validate><h2>Your lifestyle</h2><?= csrf_field() ?>
    <div><label for="sleep_pattern">Sleep pattern</label><select id="sleep_pattern" name="sleep_pattern" required><?php foreach (['early' => 'Early bird', 'flexible' => 'Flexible', 'night' => 'Night owl'] as $k => $v): ?><option value="<?= $k ?>" <?= ($me['sleep_pattern'] ?? 'flexible') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div><label for="study_style">Study style</label><select id="study_style" name="study_style" required><?php foreach (['quiet' => 'Quiet and alone', 'mixed' => 'A bit of both', 'social' => 'In groups'] as $k => $v): ?><option value="<?= $k ?>" <?= ($me['study_style'] ?? 'mixed') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="row"><div><label for="tidiness">Tidiness (1 relaxed, 5 spotless)</label><input id="tidiness" name="tidiness" type="number" min="1" max="5" required value="<?= (int)($me['tidiness'] ?? 3) ?>"></div>
    <div><label for="social_level">Social energy (1 homebody, 5 party)</label><input id="social_level" name="social_level" type="number" min="1" max="5" required value="<?= (int)($me['social_level'] ?? 3) ?>"></div></div>
    <div><label for="bio">About you (optional)</label><input id="bio" name="bio" maxlength="200" value="<?= e($me['bio'] ?? '') ?>"></div>
    <button class="btn" type="submit">Save and find matches</button></form>
  <div class="grid">
    <?php if (!$me): ?><div class="card"><p class="muted">Save your profile to see how well you match with other tenants.</p></div>
    <?php elseif (!$matches): ?><div class="card"><p class="muted">No other tenants have filled in a profile yet.</p></div><?php endif; ?>
    <?php foreach ($matches as $m): ?>
    <div class="card match"><div style="display:flex;justify-content:space-between;align-items:center"><div><h2><?= e(explode(' ', $m['full_name'])[0]) ?></h2><span class="muted"><?= e($m['type_name']) ?> room</span></div><span class="pct"><?= (int)$m['score'] ?>%</span></div>
      <div class="mbar" role="img" aria-label="<?= (int)$m['score'] ?> percent match"><i data-w="<?= (int)$m['score'] ?>"></i></div>
      <div><span class="chipx"><?= e($labels[$m['sleep_pattern']]) ?></span><span class="chipx"><?= e($labels[$m['study_style']]) ?></span><span class="chipx">Tidy <?= (int)$m['tidiness'] ?>/5</span><span class="chipx">Social <?= (int)$m['social_level'] ?>/5</span></div>
      <?php if ($m['bio']): ?><p class="muted"><?= e($m['bio']) ?></p><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
</div>
<p class="muted">Only first names and lifestyle answers are shown. Contact details are never shared. Ask the manager if you would like to be paired.</p>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
