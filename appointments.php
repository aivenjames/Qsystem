<?php
require_once __DIR__ . '/functions.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'book' && $user['role'] === 'student') {
            $service = trim(isset($_POST['service']) ? $_POST['service'] : '');
            $date = trim(isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '');
            $time = trim(isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '');
            $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');
            $services = array('Registrar', 'Guidance Office', 'Cashier', 'IT Support', 'Library', 'Other');

            if (!in_array($service, $services, true) || !valid_appointment_date($date) || !valid_appointment_time($time)) {
                flash('Please choose a valid service, date, and time.', 'error');
            } else {
                $stmt = $pdo->prepare('INSERT INTO appointments (user_id, service, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute(array($user['id'], $service, $date, $time, $notes));
                flash('Appointment request submitted. Please wait for staff approval.');
            }
        } elseif ($action === 'edit' && $user['role'] === 'student') {
            $id = (int)(isset($_POST['appointment_id']) ? $_POST['appointment_id'] : 0);
            $date = trim(isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '');
            $time = trim(isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '');

            if ($id < 1 || !valid_appointment_date($date) || !valid_appointment_time($time)) {
                flash('Please choose a valid appointment date and time.', 'error');
            } else {
                $stmt = $pdo->prepare("SELECT id, status FROM appointments WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Approved', 'Waiting')");
                $stmt->execute(array($id, $user['id']));
                $appointment = $stmt->fetch();

                if (!$appointment) {
                    flash('This appointment can no longer be edited.', 'error');
                } else {
                    // If an approved appointment is rescheduled, remove its old queue assignment
                    // and send it back for approval so the queue remains accurate.
                    $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, queue_number = NULL, reviewed_by = NULL, reviewed_at = NULL, status = CASE WHEN status IN ('Approved', 'Waiting') THEN 'Pending' ELSE status END WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Approved', 'Waiting')");
                    $stmt->execute(array($date, $time, $id, $user['id']));
                    if ($stmt->rowCount() > 0) {
                        flash('Appointment date and time updated. Rescheduled approved appointments must be approved again.');
                    } else {
                        flash('No changes were saved. Refresh the page and try again.', 'error');
                    }
                }
            }
        } elseif ($action === 'cancel' && $user['role'] === 'student') {
            $id = (int)(isset($_POST['appointment_id']) ? $_POST['appointment_id'] : 0);
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled', queue_number = NULL WHERE id = ? AND user_id = ? AND status IN ('Pending', 'Approved', 'Waiting')");
            $stmt->execute(array($id, $user['id']));
            flash($stmt->rowCount() ? 'Appointment cancelled.' : 'This appointment can no longer be cancelled.', $stmt->rowCount() ? 'success' : 'error');
        } else {
            flash('You do not have permission to perform that action.', 'error');
        }
    } catch (PDOException $ex) {
        error_log('Qsystem appointments error: ' . $ex->getMessage());
        flash('The appointment could not be saved. Check that the Qsystem database is imported correctly, then try again.', 'error');
    }

    go('appointments.php');
}

if ($user['role'] === 'student') {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC');
    $stmt->execute(array($user['id']));
} else {
    $stmt = $pdo->query('SELECT a.*, u.name AS student_name FROM appointments a JOIN users u ON u.id = a.user_id ORDER BY a.appointment_date DESC, a.appointment_time DESC');
}
$rows = $stmt->fetchAll();
layout_start('Appointments');
?>
<div class="page-heading">
    <div><div class="eyebrow">APPOINTMENTS</div><h1>Appointments</h1><p class="muted">Book a school service and manage your appointment date and time.</p></div>
</div>

<?php if ($user['role'] === 'student'): ?>
<section id="book" class="panel booking-panel">
    <h2>Book a new appointment</h2><p class="muted">Your request stays pending until it is approved by school staff.</p>
    <form method="post" class="booking-form">
        <?php csrf_field(); ?><input type="hidden" name="action" value="book">
        <div><label>School service</label><select name="service" required><option value="">Choose a service</option><?php foreach (array('Registrar', 'Guidance Office', 'Cashier', 'IT Support', 'Library', 'Other') as $service): ?><option value="<?= e($service) ?>"><?= e($service) ?></option><?php endforeach; ?></select></div>
        <div><label>Date</label><input type="date" name="appointment_date" min="<?= e(date('Y-m-d')) ?>" required></div>
        <div><label>Preferred time</label><input type="time" name="appointment_time" required></div>
        <div class="wide"><label>Reason / notes (optional)</label><input name="notes" maxlength="500" placeholder="Briefly describe what you need"></div>
        <button class="btn" type="submit">Submit request &rarr;</button>
    </form>
</section>
<?php endif; ?>

<div class="panel table-panel">
    <div class="panel-heading"><div><h2><?= $user['role'] === 'student' ? 'Your requests' : 'All requests' ?></h2><p class="muted"><?= count($rows) ?> appointment(s)</p></div></div>
    <?php if (!$rows): ?><div class="empty"><h3>Nothing here yet</h3><p>Appointment requests will appear here.</p></div>
    <?php else: ?>
    <div class="table-scroll"><table><thead><tr><?php if ($user['role'] !== 'student') echo '<th>Student</th>'; ?><th>Service</th><th>Date</th><th>Time</th><th>Queue</th><th>Status</th><?php if ($user['role'] === 'student') echo '<th>Actions</th>'; ?></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr>
        <?php if ($user['role'] !== 'student') echo '<td>' . e($row['student_name']) . '</td>'; ?>
        <td><strong><?= e($row['service']) ?></strong><?php if (!empty($row['notes'])): ?><div class="tiny muted"><?= e($row['notes']) ?></div><?php endif; ?></td>
        <td><?= e($row['appointment_date']) ?></td><td><?= e(substr($row['appointment_time'], 0, 5)) ?></td>
        <td><?= !empty($row['queue_number']) ? 'Q-' . str_pad($row['queue_number'], 3, '0', STR_PAD_LEFT) : '&mdash;' ?></td>
        <td><span class="status <?= e(strtolower($row['status'])) ?>"><?= e($row['status']) ?></span></td>
        <?php if ($user['role'] === 'student'): ?><td>
            <?php if (in_array($row['status'], array('Pending', 'Approved', 'Waiting'), true)): ?>
            <details class="appointment-edit"><summary class="btn small secondary">Edit date/time</summary>
                <form method="post" class="edit-appointment-form">
                    <?php csrf_field(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                    <label>New date</label><input type="date" name="appointment_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($row['appointment_date']) ?>" required>
                    <label>New time</label><input type="time" name="appointment_time" value="<?= e(substr($row['appointment_time'], 0, 5)) ?>" required>
                    <button class="btn small" type="submit">Save changes</button>
                </form>
            </details>
            <form method="post" onsubmit="return confirm('Cancel this appointment?')" style="margin-top:6px"><?php csrf_field(); ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>"><button class="btn small secondary" type="submit">Cancel</button></form>
            <?php else: ?>&mdash;<?php endif; ?>
        </td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
<?php layout_end(); ?>
