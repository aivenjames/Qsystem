<?php require 'functions.php';
$staff = (($_GET['as'] ?? '') === 'staff'); $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $s = db()->prepare("SELECT * FROM users WHERE email=?"); $s->execute([strtolower(trim($_POST['email'] ?? ''))]); $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash']) && (($u['role'] === 'staff') === $staff)) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['full_name'], 'role' => $u['role']];
        go($u['role'] === 'staff' ? 'staff.php' : 'dashboard.php');
    }
    $err = 'Invalid email or password for this login type.';
}
page_header($staff ? 'Staff Login' : 'Login'); ?>
<form method="post" class="panel narrow"><h2><?= $staff ? 'Staff Login' : 'Student Login' ?></h2>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?><?= csrf() ?>
<label>Email <input type="email" name="email" required></label>
<label>Password <input type="password" name="password" required></label>
<button class="btn">Login</button>
<p class="muted"><?= $staff ? '<a href="login.php">Student login</a>' : 'No account? <a href="register.php">Register</a> · <a href="guest.php">Continue as guest</a>' ?></p></form>
<?php page_footer();
