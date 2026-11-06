<?php require 'functions.php';
$u = user(); if (!$u || $_SERVER['REQUEST_METHOD'] !== 'POST') go('login.php');
check_csrf();
$staff = $u['role'] === 'staff'; $back = $staff ? 'staff_appointments.php' : 'dashboard.php';
$id = (int)($_POST['id'] ?? 0); $act = $_POST['action'] ?? '';
$s = db()->prepare("SELECT * FROM appointments WHERE id=?"); $s->execute([$id]); $a = $s->fetch();
if (!$a) { flash('Appointment not found.'); go($back); }
$own = $a['user_id'] == $u['id']; $st = $a['status'];
$note = mb_substr(trim($_POST['staff_note'] ?? ''), 0, 255) ?: null;
$set = fn($status, $n = null) => db()->prepare("UPDATE appointments SET status=?, staff_note=COALESCE(?,staff_note), updated_at=NOW() WHERE id=?")->execute([$status, $n, $id]);

if ($act === 'cancel' && ($own || $staff) && in_array($st, ['pending', 'approved'])) { $set('cancelled'); flash('Appointment cancelled.'); }
elseif ($act === 'checkin' && $own && $st === 'approved' && $a['appt_date'] === today() && !$a['ticket_id']) {
    $t = new_ticket($a['service_id'], $u['id'], $u['name'], $id);
    db()->prepare("UPDATE appointments SET ticket_id=? WHERE id=?")->execute([$t['id'], $id]);
    flash('Checked in! Your queue number is ' . $t['no'] . '.');
}
elseif ($staff && $act === 'approve' && in_array($st, ['pending', 'rejected'])) { $set('approved', $note); flash('Appointment approved.'); }
elseif ($staff && $act === 'reject' && in_array($st, ['pending', 'approved'])) { $set('rejected', $note); flash('Appointment rejected.'); }
elseif ($staff && $act === 'complete' && $st === 'approved') {
    $set('completed', $note);
    if ($a['ticket_id']) db()->prepare("UPDATE queue_tickets SET status='done', finished_at=NOW() WHERE id=? AND status IN('waiting','serving')")->execute([$a['ticket_id']]);
    flash('Appointment marked completed.');
}
else flash('Action not allowed.');
go($back);
