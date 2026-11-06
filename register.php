<?php require 'functions.php'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $pw = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email.';
    elseif (strlen($pw) < 6) $err = 'Password must be at least 6 characters.';
    else {
        $c = db()->prepare("SELECT COUNT(*) FROM users WHERE email=?"); $c->execute([$email]);
        if ($c->fetchColumn()) $err = 'Email already registered.';
        else {
            db()->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,'student')")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            flash('Account created. Please log in.'); go('login.php');
        }
    }
}
page_header('Register'); ?>
<form method="post" class="panel narrow"><h2>Create Account</h2>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?><?= csrf() ?>
<label>Full name <input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
<label>Email <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
<label>Password <input type="password" name="password" minlength="6" required></label>
<button class="btn">Register</button></form>
<?php page_footer();
