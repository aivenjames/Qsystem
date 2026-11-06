<?php require 'functions.php'; page_header('Home'); ?>
<section class="hero"><h1>School Queue &amp; Appointments</h1>
<p>Book an appointment, get a queue number, and watch your turn live.</p>
<div class="actions"><a class="btn" href="guest.php">Get Walk-in Ticket</a>
<a class="btn alt" href="login.php">Student Login</a><a class="btn alt" href="login.php?as=staff">Staff Login</a></div></section>
<h2>Now Serving</h2><div id="board" class="grid"></div>
<script>
async function load(){
  try{const d=await (await fetch('api_queue.php')).json();
  document.getElementById('board').innerHTML=d.services.map(s=>
   `<div class="card"><small>${s.name}</small><div class="big">${s.serving||'—'}</div><small>${s.waiting} waiting</small></div>`).join('');}catch(e){}
}
load(); setInterval(load,5000);
</script>
<?php page_footer();
