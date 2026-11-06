<?php require 'functions.php'; require_role('staff');
$f = $_GET['status'] ?? 'pending'; $all = ['pending', 'approved', 'rejected', 'cancelled', 'completed'];
$sql = "SELECT a.*, s.name sname, u.full_name FROM appointments a JOIN services s ON s.id=a.service_id JOIN users u ON u.id=a.user_id";
$p = []; if (in_array($f, $all)) { $sql .= " WHERE a.status=?"; $p[] = $f; }
$q = db()->prepare($sql . " ORDER BY a.appt_date, a.appt_time"); $q->execute($p); $rows = $q->fetchAll();
page_header('Appointments'); ?>
<h2>Appointments</h2>
<div class="tabs"><?php foreach ($all as $s): ?><a class="<?= $f === $s ? 'on' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?></a><?php endforeach; ?><a class="<?= $f === 'all' ? 'on' : '' ?>" href="?status=all">All</a></div>
<div class="scroll"><table>
<tr><th>Student</th><th>Service</th><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Review</th></tr>
<?php foreach ($rows as $x): ?>
<tr><td><?= e($x['full_name']) ?></td><td><?= e($x['sname']) ?></td><td><?= e($x['appt_date']) ?></td><td><?= e(substr($x['appt_time'], 0, 5)) ?></td>
<td><?= e($x['purpose']) ?></td><td><?= badge($x['status']) ?></td>
<td><form method="post" action="appointment_action.php" class="inline"><?= csrf() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
<input name="staff_note" placeholder="Note" value="<?= e($x['staff_note']) ?>">
<button name="action" value="approve" class="btn sm ok">Approve</button>
<button name="action" value="reject" class="btn sm bad">Reject</button>
<?php if ($x['status'] === 'approved'): ?><button name="action" value="complete" class="btn sm ok">Complete</button><?php endif; ?>
<a class="btn sm" href="edit_appointment.php?id=<?= $x['id'] ?>">Edit</a></form></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7">Nothing here.</td></tr>'; ?>
</table></div>
<?php page_footer();
