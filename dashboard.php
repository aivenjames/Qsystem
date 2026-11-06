<?php require 'functions.php'; require_role('student');
$uid = user()['id'];
$a = db()->prepare("SELECT a.*, s.name sname FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.user_id=? ORDER BY a.appt_date DESC, a.appt_time DESC");
$a->execute([$uid]); $appts = $a->fetchAll();
$t = db()->prepare("SELECT t.*, s.name sname FROM queue_tickets t JOIN services s ON s.id=t.service_id WHERE t.user_id=? AND t.queue_date=? AND t.status IN('waiting','serving') ORDER BY t.id DESC LIMIT 1");
$t->execute([$uid, today()]); $ticket = $t->fetch();
$count = fn($st) => count(array_filter($appts, fn($x) => $x['status'] === $st));
page_header('Dashboard'); ?>
<h2>Hello, <?= e(user()['name']) ?></h2>
<div class="grid">
  <div class="card"><small>Pending</small><div class="big"><?= $count('pending') ?></div></div>
  <div class="card"><small>Approved</small><div class="big"><?= $count('approved') ?></div></div>
  <div class="card"><small>Completed</small><div class="big"><?= $count('completed') ?></div></div>
  <?php if ($ticket) ticket_card($ticket); ?>
</div>
<div class="row"><h2>My Appointments</h2><a class="btn" href="book.php">+ Book Appointment</a></div>
<div class="scroll"><table>
<tr><th>Service</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Staff note</th><th>Actions</th></tr>
<?php foreach ($appts as $x): ?>
<tr><td><?= e($x['sname']) ?></td><td><?= e($x['appt_date']) ?></td><td><?= e(substr($x['appt_time'], 0, 5)) ?></td>
<td><?= e($x['purpose']) ?></td><td><?= badge($x['status']) ?></td><td><?= e($x['staff_note']) ?></td>
<td class="acts">
<?php if (in_array($x['status'], ['pending', 'approved', 'rejected'])): ?><a class="btn sm" href="edit_appointment.php?id=<?= $x['id'] ?>">Edit</a><?php endif; ?>
<?php if ($x['status'] === 'approved' && $x['appt_date'] === today() && !$x['ticket_id']): ?>
  <form method="post" action="appointment_action.php"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><button name="action" value="checkin" class="btn sm ok">Check-in</button></form><?php endif; ?>
<?php if (in_array($x['status'], ['pending', 'approved'])): ?>
  <form method="post" action="appointment_action.php" onsubmit="return confirm('Cancel this appointment?')"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><button name="action" value="cancel" class="btn sm bad">Cancel</button></form><?php endif; ?>
</td></tr>
<?php endforeach; if (!$appts) echo '<tr><td colspan="7">No appointments yet.</td></tr>'; ?>
</table></div>
<?php if ($ticket): ?><script>setTimeout(()=>location.reload(),10000)</script><?php endif;
page_footer();
