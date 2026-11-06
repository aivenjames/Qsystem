<?php require 'functions.php';
$u = user(); if (!$u) go('login.php');
$isStaff = $u['role'] === 'staff';
$back = $isStaff ? 'staff_appointments.php' : 'dashboard.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = db()->prepare("SELECT * FROM appointments WHERE id=?"); $s->execute([$id]); $a = $s->fetch();
if (!$a || (!$isStaff && $a['user_id'] != $u['id'])) { flash('Appointment not found.'); go($back); }
if (in_array($a['status'], ['cancelled', 'completed'])) { flash('This appointment can no longer be edited.'); go($back); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $sid = (int)$_POST['service_id']; $d = $_POST['appt_date'] ?? ''; $t = $_POST['appt_time'] ?? ''; $p = trim($_POST['purpose'] ?? '');
    $err = validate_appt($sid, $d, $t, $p, $id);
    if (!$err) {
        $status = $a['status']; $note = $a['staff_note'];
        if ($isStaff) {
            $st = $_POST['status'] ?? $status;
            if (in_array($st, ['pending', 'approved', 'rejected', 'cancelled'])) $status = $st;
            $note = mb_substr(trim($_POST['staff_note'] ?? ''), 0, 255) ?: null;
        } elseif (in_array($status, ['approved', 'rejected'])) {
            $status = 'pending'; // student changes require re-approval
        }
        db()->prepare("UPDATE appointments SET service_id=?, appt_date=?, appt_time=?, purpose=?, status=?, staff_note=?, updated_at=NOW() WHERE id=?")
            ->execute([$sid, $d, $t . ':00', $p, $status, $note, $id]);
        flash($isStaff ? 'Changes saved.' : 'Changes saved. Your appointment is pending approval again.' ); go($back);
    }
    $a = array_merge($a, $_POST);
}
page_header('Edit Appointment'); ?>
<form method="post" class="panel narrow"><h2>Edit Appointment #<?= $id ?></h2>
<p>Status: <?= badge($a['status']) ?></p>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?><?= csrf() ?>
<input type="hidden" name="id" value="<?= $id ?>">
<?php appt_fields($a); ?>
<?php if ($isStaff): ?>
  <label>Status <select name="status"><?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $st): ?><option <?= $a['status'] === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select></label>
  <label>Staff note <input name="staff_note" maxlength="255" value="<?= e($a['staff_note'] ?? '') ?>"></label>
<?php else: ?><p class="muted">Editing an approved appointment sends it back for approval.</p><?php endif; ?>
<button class="btn">Save Changes</button> <a class="btn alt" href="<?= $back ?>">Cancel</a></form>
<?php page_footer();
