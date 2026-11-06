<?php
session_start();
require __DIR__ . '/config.php';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    return $pdo;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function today() { return date('Y-m-d'); }
function go($u) { header("Location: $u"); exit; }
function user() { return $_SESSION['user'] ?? null; }
function require_role($r) { $u = user(); if (!$u || $u['role'] !== $r) go('login.php' . ($r === 'staff' ? '?as=staff' : '')); }
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function check_csrf() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) { http_response_code(400); exit('Invalid request token.'); }
}
function flash($m = null) {
    if ($m !== null) { $_SESSION['flash'] = $m; return; }
    $f = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $f;
}
function services() { return db()->query("SELECT * FROM services WHERE active=1 ORDER BY name")->fetchAll(); }
function badge($s) { return '<span class="badge ' . e($s) . '">' . e(ucfirst($s)) . '</span>'; }

function slots() {
    $o = [];
    for ($m = 8 * 60; $m <= 16 * 60 + 30; $m += 30) $o[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    return $o;
}

/* Returns '' if valid, otherwise an error message. */
function validate_appt($sid, $date, $time, $purpose, $exclude = 0) {
    $s = db()->prepare("SELECT COUNT(*) FROM services WHERE id=? AND active=1"); $s->execute([$sid]);
    if (!$s->fetchColumn()) return 'Choose a valid service.';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return 'Invalid date.';
    if ($date < today()) return 'Date cannot be in the past.';
    if ((int)$d->format('N') > 5) return 'Appointments are Monday to Friday only.';
    if (!in_array($time, slots(), true)) return 'Choose a valid time slot.';
    if (trim($purpose) === '' || strlen($purpose) > 255) return 'Purpose is required (max 255 characters).';
    $c = db()->prepare("SELECT COUNT(*) FROM appointments WHERE service_id=? AND appt_date=? AND appt_time=? AND status IN('pending','approved') AND id<>?");
    $c->execute([$sid, $date, $time . ':00', $exclude]);
    if ($c->fetchColumn() >= SLOT_CAPACITY) return 'That time slot is full. Please pick another.';
    return '';
}

function appt_fields($a) { ?>
  <label>Service
    <select name="service_id" required>
      <?php foreach (services() as $s): ?>
        <option value="<?= $s['id'] ?>" <?= ($a['service_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Date <input type="date" name="appt_date" min="<?= today() ?>" value="<?= e($a['appt_date'] ?? '') ?>" required></label>
  <label>Time
    <select name="appt_time">
      <?php foreach (slots() as $t): ?><option <?= substr($a['appt_time'] ?? '', 0, 5) === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
    </select></label>
  <label>Purpose <textarea name="purpose" maxlength="255" required><?= e($a['purpose'] ?? '') ?></textarea></label>
<?php }

/* Automatic queue numbering: per service, per day (e.g. R001, R002...). Row lock prevents duplicates. */
function new_ticket($service_id, $user_id, $name, $appt_id = null) {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT prefix FROM services WHERE id=? FOR UPDATE"); $s->execute([$service_id]); $p = $s->fetchColumn();
        $m = $pdo->prepare("SELECT COALESCE(MAX(seq),0)+1 FROM queue_tickets WHERE service_id=? AND queue_date=?");
        $m->execute([$service_id, today()]); $seq = (int)$m->fetchColumn();
        $no = $p . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO queue_tickets(ticket_no,service_id,user_id,guest_name,appointment_id,queue_date,seq) VALUES(?,?,?,?,?,?,?)")
            ->execute([$no, $service_id, $user_id, $name, $appt_id, today(), $seq]);
        $id = $pdo->lastInsertId(); $pdo->commit();
        return ['id' => $id, 'no' => $no];
    } catch (Throwable $x) { $pdo->rollBack(); throw $x; }
}

function ticket_card($t) {
    $n = 0;
    if ($t['status'] === 'waiting') {
        $a = db()->prepare("SELECT COUNT(*) FROM queue_tickets WHERE service_id=? AND queue_date=? AND status='waiting' AND seq<?");
        $a->execute([$t['service_id'], $t['queue_date'], $t['seq']]); $n = $a->fetchColumn();
    }
    echo '<div class="card ticket"><small>' . e($t['sname']) . '</small><div class="big">' . e($t['ticket_no']) . '</div>' . badge($t['status']);
    if ($t['status'] === 'waiting') echo '<p>' . $n . ' ahead of you</p>';
    if ($t['status'] === 'serving') echo '<p><b>It\'s your turn!</b></p>';
    echo '</div>';
}

function page_header($title) { $u = user(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · Qsystem</title><link rel="stylesheet" href="assets/style.css"></head><body>
<header class="top"><a class="brand" href="index.php">🎫 Qsystem</a>
<button class="burger" onclick="document.getElementById('nav').classList.toggle('open')">☰</button>
<nav id="nav">
<?php if (!$u): ?>
  <a href="guest.php">Walk-in Ticket</a><a href="login.php">Login</a><a href="register.php">Register</a><a href="login.php?as=staff">Staff</a>
<?php elseif ($u['role'] === 'staff'): ?>
  <a href="staff.php">Queue</a><a href="staff_appointments.php">Appointments</a><a href="logout.php">Logout (<?= e($u['name']) ?>)</a>
<?php else: ?>
  <a href="dashboard.php">Dashboard</a><a href="book.php">Book</a><a href="logout.php">Logout (<?= e($u['name']) ?>)</a>
<?php endif; ?>
</nav></header><main>
<?php if ($f = flash()): ?><div class="alert"><?= e($f) ?></div><?php endif;
}
function page_footer() { echo '</main><footer>Qsystem · School Queueing &amp; Appointment Management</footer></body></html>'; }
