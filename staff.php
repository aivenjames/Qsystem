<?php require 'functions.php'; require_role('staff');
$t0 = today(); $sid = (int)($_GET['sid'] ?? 0);

/* Queue status management: waiting -> serving -> done / skipped / cancelled */
function set_status($tid, $to) {
    $s = db()->prepare("SELECT * FROM queue_tickets WHERE id=?"); $s->execute([$tid]); $t = $s->fetch();
    if (!$t) return 'Ticket not found.';
    $ok = ['waiting' => ['serving', 'skipped', 'cancelled'], 'serving' => ['done', 'skipped', 'waiting'], 'skipped' => ['waiting']];
    if (!in_array($to, $ok[$t['status']] ?? [], true)) return 'Invalid status change.';
    if ($to === 'serving') {
        $c = db()->prepare("SELECT COUNT(*) FROM queue_tickets WHERE service_id=? AND queue_date=? AND status='serving'");
        $c->execute([$t['service_id'], $t['queue_date']]);
        if ($c->fetchColumn()) return 'Finish the currently serving ticket first.';
    }
    db()->prepare("UPDATE queue_tickets SET status=?, called_at=IF(?='serving',NOW(),called_at), finished_at=IF(?='done',NOW(),finished_at) WHERE id=?")
        ->execute([$to, $to, $to, $tid]);
    if ($to === 'done' && $t['appointment_id'])
        db()->prepare("UPDATE appointments SET status='completed' WHERE id=?")->execute([$t['appointment_id']]);
    return $t['ticket_no'] . ' is now ' . $to . '.';
}
function qbtn($id, $to, $label, $cls = '') {
    echo '<form method="post">' . csrf() . '<input type="hidden" name="act" value="set"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="to" value="' . $to . '"><button class="btn sm ' . $cls . '">' . $label . '</button></form>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf(); $a = $_POST['act'] ?? '';
    if ($a === 'next') {
        $q = db()->prepare("SELECT id FROM queue_tickets WHERE service_id=? AND queue_date=? AND status='waiting' ORDER BY seq LIMIT 1");
        $q->execute([(int)$_POST['service_id'], $t0]); $id = $q->fetchColumn();
        flash($id ? set_status($id, 'serving') : 'No one is waiting.');
    } elseif ($a === 'set') flash(set_status((int)$_POST['id'], $_POST['to'] ?? ''));
    elseif ($a === 'walkin') { $n = new_ticket((int)$_POST['service_id'], null, mb_substr(trim($_POST['name'] ?? '') ?: 'Walk-in', 0, 100)); flash('Created ticket ' . $n['no']); }
    go('staff.php' . ($sid ? "?sid=$sid" : ''));
}
$sum = db()->prepare("SELECT s.id, s.name,
 (SELECT ticket_no FROM queue_tickets t WHERE t.service_id=s.id AND t.queue_date=? AND t.status='serving' LIMIT 1) serving,
 (SELECT COUNT(*) FROM queue_tickets t WHERE t.service_id=s.id AND t.queue_date=? AND t.status='waiting') waiting,
 (SELECT COUNT(*) FROM queue_tickets t WHERE t.service_id=s.id AND t.queue_date=? AND t.status='done') done
 FROM services s WHERE s.active=1 ORDER BY s.name");
$sum->execute([$t0, $t0, $t0]); $cards = $sum->fetchAll();
$sql = "SELECT t.*, s.name sname FROM queue_tickets t JOIN services s ON s.id=t.service_id WHERE t.queue_date=?"; $p = [$t0];
if ($sid) { $sql .= " AND t.service_id=?"; $p[] = $sid; }
$l = db()->prepare($sql . " ORDER BY FIELD(t.status,'serving','waiting','skipped','done','cancelled'), t.seq"); $l->execute($p); $tickets = $l->fetchAll();
page_header('Queue Management'); ?>
<h2>Queue Management <small class="muted"><?= $t0 ?></small></h2>
<div class="grid">
<?php foreach ($cards as $c): ?>
<div class="card"><small><a href="?sid=<?= $c['id'] ?>"><?= e($c['name']) ?></a></small>
  <div class="big"><?= e($c['serving'] ?: '—') ?></div><small><?= $c['waiting'] ?> waiting · <?= $c['done'] ?> done</small>
  <form method="post"><?= csrf() ?><input type="hidden" name="act" value="next"><input type="hidden" name="service_id" value="<?= $c['id'] ?>"><button class="btn sm">Call Next</button></form></div>
<?php endforeach; ?></div>
<form method="post" class="inline panel"><?= csrf() ?><input type="hidden" name="act" value="walkin"><strong>Add walk-in:</strong>
<input name="name" placeholder="Name (optional)"><select name="service_id"><?php foreach ($cards as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select><button class="btn sm">Create Ticket</button>
<?php if ($sid): ?><a class="btn sm alt" href="staff.php">Show all services</a><?php endif; ?></form>
<div class="scroll"><table><tr><th>No.</th><th>Name</th><th>Service</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($tickets as $t): ?>
<tr><td><b><?= e($t['ticket_no']) ?></b><?= $t['appointment_id'] ? ' 📅' : '' ?></td><td><?= e($t['guest_name']) ?></td><td><?= e($t['sname']) ?></td><td><?= badge($t['status']) ?></td>
<td class="acts"><?php
 if ($t['status'] === 'waiting') { qbtn($t['id'], 'serving', 'Serve', 'ok'); qbtn($t['id'], 'skipped', 'Skip'); qbtn($t['id'], 'cancelled', 'Cancel', 'bad'); }
 elseif ($t['status'] === 'serving') { qbtn($t['id'], 'done', 'Complete', 'ok'); qbtn($t['id'], 'skipped', 'Skip'); qbtn($t['id'], 'waiting', 'Hold'); }
 elseif ($t['status'] === 'skipped') qbtn($t['id'], 'waiting', 'Requeue'); ?></td></tr>
<?php endforeach; if (!$tickets) echo '<tr><td colspan="5">No tickets today.</td></tr>'; ?></table></div>
<script>setTimeout(()=>{if(document.activeElement===document.body)location.reload()},20000)</script>
<?php page_footer();
