<?php require 'functions.php';
if (isset($_GET['new'])) { unset($_SESSION['my_ticket']); go('guest.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $c = db()->prepare("SELECT COUNT(*) FROM services WHERE id=? AND active=1"); $c->execute([(int)$_POST['service_id']]);
    if ($c->fetchColumn()) {
        $t = new_ticket((int)$_POST['service_id'], null, mb_substr(trim($_POST['name'] ?? '') ?: 'Guest', 0, 100));
        $_SESSION['my_ticket'] = $t['id'];
    }
    go('guest.php');
}
$ticket = null;
if (!empty($_SESSION['my_ticket'])) {
    $s = db()->prepare("SELECT t.*, s.name sname FROM queue_tickets t JOIN services s ON s.id=t.service_id WHERE t.id=?");
    $s->execute([$_SESSION['my_ticket']]); $ticket = $s->fetch();
}
page_header('Walk-in Ticket');
if ($ticket && $ticket['queue_date'] === today() && in_array($ticket['status'], ['waiting', 'serving'])): ?>
  <h2>Your Ticket</h2><div class="grid"><?php ticket_card($ticket); ?></div>
  <p class="muted">This page refreshes automatically.</p><a class="btn alt" href="guest.php?new=1">Get another ticket</a>
  <script>setTimeout(()=>location.reload(),8000)</script>
<?php else: if ($ticket) unset($_SESSION['my_ticket']); ?>
  <form method="post" class="panel narrow"><h2>Get a Queue Number</h2><?= csrf() ?>
  <label>Your name <input name="name" placeholder="Optional"></label>
  <label>Service <select name="service_id"><?php foreach (services() as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
  <button class="btn">Get Ticket</button></form>
<?php endif; page_footer();
