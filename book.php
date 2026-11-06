<?php require 'functions.php'; require_role('student'); $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $sid = (int)$_POST['service_id']; $d = $_POST['appt_date'] ?? ''; $t = $_POST['appt_time'] ?? ''; $p = trim($_POST['purpose'] ?? '');
    $err = validate_appt($sid, $d, $t, $p);
    if (!$err) {
        db()->prepare("INSERT INTO appointments(user_id,service_id,appt_date,appt_time,purpose) VALUES(?,?,?,?,?)")
            ->execute([user()['id'], $sid, $d, $t . ':00', $p]);
        flash('Appointment submitted. Waiting for staff approval.'); go('dashboard.php');
    }
}
page_header('Book Appointment'); ?>
<form method="post" class="panel narrow"><h2>Book Appointment</h2>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?><?= csrf() ?>
<?php appt_fields($_POST); ?>
<button class="btn">Submit Request</button> <a class="btn alt" href="dashboard.php">Back</a></form>
<?php page_footer();
