<?php
// Qsystem data-structures demonstration.
// Data structure 1: array (summary list).
$summary = [
    "Array" => "Stores a collection of values by index.",
    "Queue" => "Processes items in First-In, First-Out (FIFO) order."
];

// Data structure 2: queue implemented with a PHP array.
$activityQueue = [];
$actions = ["Create student", "View student records", "Search student records", "Update student", "Delete student"];
foreach ($actions as $action) {
    $activityQueue[] = $action; // enqueue: add at the back
}
$nextAction = array_shift($activityQueue); // dequeue: remove from the front

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Qsystem | Data Structures</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
  <div class="brand-mark">Q</div>
  <div><h1>Qsystem</h1><p>Data Structures Demonstration</p></div>
</header>
<main class="container">
  <section class="intro">
    <div><span class="eyebrow">PROJECT DOCUMENTATION</span><h2>Data Structures Used</h2>
    <p>This page explains the two data structures included in Qsystem.</p></div>
    <a class="btn btn-light" href="index.php">← Back to Dashboard</a>
  </section>
  <section class="panel">
    <div class="panel-heading"><div><h3>1. Array</h3><p>An array keeps related values together for easy access.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Structure</th><th>Purpose in Qsystem</th></tr></thead><tbody>
    <?php foreach ($summary as $name => $purpose): ?>
      <tr><td class="student-name"><?= h($name) ?></td><td><?= h($purpose) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </section>
  <section class="panel">
    <div class="panel-heading"><div><h3>2. Queue (FIFO)</h3><p>First In, First Out: the first item added is the first item removed.</p></div></div>
    <p><strong>Dequeued item:</strong> <?= h($nextAction) ?></p>
    <p><strong>Items remaining in queue:</strong></p>
    <ol>
      <?php foreach ($activityQueue as $action): ?><li><?= h($action) ?></li><?php endforeach; ?>
    </ol>
    <p class="intro-note">The code adds actions at the back of the queue and removes the first action with <code>array_shift()</code>.</p>
  </section>
  <footer>Qsystem · PHP data structures demonstration</footer>
</main>
</body>
</html>
