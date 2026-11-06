<?php
require 'functions.php';
header('Content-Type: application/json');
// Kiosk ticket creation (used by Python app): POST api_queue.php?action=take
if (($_GET['action'] ?? '') === 'take' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['key'] ?? '') !== KIOSK_KEY) { http_response_code(403); exit(json_encode(['error' => 'bad key'])); }
    $sid = (int)($_POST['service_id'] ?? 0);
    $c = db()->prepare("SELECT COUNT(*) FROM services WHERE id=? AND active=1"); $c->execute([$sid]);
    if (!$c->fetchColumn()) { http_response_code(400); exit(json_encode(['error' => 'bad service'])); }
    $name = trim($_POST['name'] ?? '') ?: 'Kiosk guest';
    $t = new_ticket($sid, null, mb_substr($name, 0, 100));
    exit(json_encode(['ticket' => $t['no']]));
}
$q = db()->prepare("SELECT s.id, s.name, s.prefix,
  (SELECT ticket_no FROM queue_tickets t WHERE t.service_id=s.id AND t.queue_date=? AND t.status='serving' ORDER BY called_at DESC LIMIT 1) AS serving,
  (SELECT COUNT(*) FROM queue_tickets t WHERE t.service_id=s.id AND t.queue_date=? AND t.status='waiting') AS waiting
  FROM services s WHERE s.active=1 ORDER BY s.name");
$q->execute([today(), today()]);
echo json_encode(['services' => $q->fetchAll(), 'time' => date('H:i:s')]);
