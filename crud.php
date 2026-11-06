<?php
require_once 'includes/config.php';
require_roles(['admin','staff']);
$role = $_SESSION['user']['role'];
$error = '';
$tab = ($_GET['tab'] ?? 'appointments') === 'users' ? 'users' : 'appointments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $kind = $_POST['kind'] ?? 'appointments';
    $tab = $kind === 'users' ? 'users' : 'appointments';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($kind === 'users') {
            if ($role !== 'admin') { http_response_code(403); exit('Only administrators can manage user accounts.'); }
            if ($action === 'create') {
                $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? ''));
                $password = $_POST['password'] ?? ''; $newRole = $_POST['role'] ?? 'student';
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !in_array($newRole, ['admin','staff','student'], true)) throw new Exception('Enter a name, valid email, password of at least 8 characters, and valid role.');
                $q = db()->prepare('INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,?)');
                $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$newRole]);
                flash('User account created.');
            } elseif ($action === 'update') {
                $name = trim($_POST['full_name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $newRole = $_POST['role'] ?? 'student';
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($newRole, ['admin','staff','student'], true)) throw new Exception('Enter a valid name, email, and role.');
                if ($id === current_user_id() && $newRole !== 'admin') throw new Exception('You cannot remove your own administrator role.');
                $q = db()->prepare('UPDATE users SET full_name=?,email=?,role=? WHERE id=?'); $q->execute([$name,$email,$newRole,$id]);
                if (!empty($_POST['password'])) { if (strlen($_POST['password']) < 8) throw new Exception('New password must contain at least 8 characters.'); db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'],PASSWORD_DEFAULT),$id]); }
                if ($id === current_user_id()) { $_SESSION['user']['full_name']=$name; $_SESSION['user']['email']=$email; $_SESSION['user']['role']=$newRole; }
                flash('User account updated.');
            } elseif ($action === 'delete') {
                if ($id === current_user_id()) throw new Exception('You cannot delete your own account.');
                db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]); flash('User account deleted. Related appointments and queue entries were also removed.');
            }
        } else {
            $studentId = (int)($_POST['user_id'] ?? 0); $service = trim($_POST['service'] ?? ''); $date = $_POST['appointment_date'] ?? ''; $time = $_POST['appointment_time'] ?? ''; $purpose = trim($_POST['purpose'] ?? ''); $status = $_POST['status'] ?? 'pending';
            if ($service === '' || !$studentId || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !preg_match('/^\d{2}:\d{2}$/',$time) || !in_array($status,['pending','approved','rejected','completed','cancelled'],true)) throw new Exception('Please complete all required appointment fields.');
            $check = db()->prepare("SELECT id FROM users WHERE id=? AND role='student'"); $check->execute([$studentId]); if (!$check->fetch()) throw new Exception('Choose a valid student account.');
            if ($action === 'create') {
                $q = db()->prepare('INSERT INTO appointments(user_id,service,appointment_date,appointment_time,purpose,status) VALUES(?,?,?,?,?,?)'); $q->execute([$studentId,$service,$date,$time.':00',$purpose ?: null,$status]);
                flash('Appointment record created. If it needs a queue number, approve it from Manage.');
            } elseif ($action === 'update') {
                $q = db()->prepare('UPDATE appointments SET user_id=?,service=?,appointment_date=?,appointment_time=?,purpose=?,status=? WHERE id=?'); $q->execute([$studentId,$service,$date,$time.':00',$purpose ?: null,$status,$id]);
                flash('Appointment record updated. Check the queue page if you changed its date or status.');
            } elseif ($action === 'delete') {
                db()->prepare('DELETE FROM appointments WHERE id=?')->execute([$id]); flash('Appointment record deleted.');
            }
        }
        header('Location: crud.php?tab='.$tab); exit;
    } catch (Throwable $ex) {
        if ($ex instanceof PDOException && str_contains(strtolower($ex->getMessage()), 'duplicate')) $error = 'That email address is already registered.';
        else $error = $ex instanceof Exception ? $ex->getMessage() : 'The action could not be completed. Check the record and try again.';
    }
}

$editUser = null; $editAppointment = null;
if ($tab === 'users' && $role === 'admin' && isset($_GET['edit_user'])) { $q=db()->prepare('SELECT id,full_name,email,role FROM users WHERE id=?'); $q->execute([(int)$_GET['edit_user']]); $editUser=$q->fetch() ?: null; }
if ($tab === 'appointments' && isset($_GET['edit_appointment'])) { $q=db()->prepare('SELECT * FROM appointments WHERE id=?'); $q->execute([(int)$_GET['edit_appointment']]); $editAppointment=$q->fetch() ?: null; }
$students = db()->query("SELECT id,full_name,email FROM users WHERE role='student' ORDER BY full_name")->fetchAll();
$appointments = db()->query('SELECT a.*,u.full_name,u.email FROM appointments a JOIN users u ON u.id=a.user_id ORDER BY a.appointment_date DESC,a.appointment_time DESC')->fetchAll();
$users = $role === 'admin' ? db()->query('SELECT id,full_name,email,role,created_at FROM users ORDER BY created_at DESC')->fetchAll() : [];
$pageTitle = 'CRUD Management'; require 'includes/header.php';
?>
<div class="page-heading"><div><div class="eyebrow">RECORD MANAGEMENT</div><h1>CRUD Management</h1><p class="muted">Create, read, update, and delete school appointment records. Administrators can also manage user accounts.</p></div></div>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<div class="inline-actions" style="margin-bottom:18px"><a class="btn <?=$tab==='appointments'?'primary':'secondary'?>" href="crud.php?tab=appointments">Appointment CRUD</a><?php if($role==='admin'): ?><a class="btn <?=$tab==='users'?'primary':'secondary'?>" href="crud.php?tab=users">User CRUD</a><?php endif; ?></div>
<?php if ($tab === 'appointments'): ?>
<section class="panel form-panel"><div class="panel-heading"><div><h2><?=$editAppointment?'Edit appointment':'Create appointment'?></h2><p class="muted">Use this form to add or update a student appointment.</p></div></div>
<form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="kind" value="appointments"><input type="hidden" name="action" value="<?=$editAppointment?'update':'create'?>"><?php if($editAppointment): ?><input type="hidden" name="id" value="<?=(int)$editAppointment['id']?>"><?php endif; ?>
<label>Student<select name="user_id" required><option value="">Select student</option><?php foreach($students as $s): ?><option value="<?=(int)$s['id']?>" <?=((int)($editAppointment['user_id']??0)===(int)$s['id'])?'selected':''?>><?=e($s['full_name'].' ('.$s['email'].')')?></option><?php endforeach; ?></select></label>
<label>School service<input name="service" required maxlength="120" value="<?=e($editAppointment['service']??'')?>" placeholder="e.g. Registrar"></label>
<label>Date<input type="date" name="appointment_date" min="<?=date('Y-m-d')?>" required value="<?=e($editAppointment['appointment_date']??date('Y-m-d'))?>"></label>
<label>Time<input type="time" name="appointment_time" required value="<?=e(isset($editAppointment['appointment_time'])?substr($editAppointment['appointment_time'],0,5):'08:00')?>"></label>
<label>Status<select name="status"><?php foreach(['pending','approved','rejected','completed','cancelled'] as $st): ?><option value="<?=$st?>" <?=($editAppointment['status']??'pending')===$st?'selected':''?>><?=ucfirst($st)?></option><?php endforeach; ?></select></label>
<label class="span-2">Purpose / notes<textarea name="purpose" rows="3" maxlength="1000"><?=e($editAppointment['purpose']??'')?></textarea></label><div class="span-2 form-actions"><button class="btn primary" type="submit"><?=$editAppointment?'Save changes':'Create appointment'?></button><?php if($editAppointment): ?><a class="btn secondary" href="crud.php">Cancel edit</a><?php endif; ?></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Appointment records</h2><p class="muted">Showing <?=count($appointments)?> record(s).</p></div></div><div class="table-wrap"><table><thead><tr><th>Student</th><th>Service</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if(!$appointments): ?><tr><td colspan="5" class="empty">No appointment records yet.</td></tr><?php else: foreach($appointments as $a): ?><tr><td><strong><?=e($a['full_name'])?></strong><small><?=e($a['email'])?></small></td><td><?=e($a['service'])?><small><?=e($a['purpose']?:'No notes')?></small></td><td><?=e(date('M j, Y',strtotime($a['appointment_date'])))?><small><?=e(date('g:i A',strtotime($a['appointment_time'])))?></small></td><td><span class="badge <?=e($a['status'])?>"><?=e(ucfirst($a['status']))?></span></td><td><div class="inline-actions"><a class="btn small secondary" href="crud.php?tab=appointments&edit_appointment=<?=(int)$a['id']?>">Edit</a><form method="post" onsubmit="return confirm('Permanently delete this appointment?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="kind" value="appointments"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="btn small danger" type="submit">Delete</button></form></div></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
<?php else: ?>
<?php if($role!=='admin'): ?><div class="alert error">Only administrators can manage user accounts.</div><?php else: ?>
<section class="panel form-panel"><div class="panel-heading"><div><h2><?=$editUser?'Edit user account':'Create user account'?></h2><p class="muted">Passwords are securely hashed before saving.</p></div></div><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="kind" value="users"><input type="hidden" name="action" value="<?=$editUser?'update':'create'?>"><?php if($editUser): ?><input type="hidden" name="id" value="<?=(int)$editUser['id']?>"><?php endif; ?><label>Full name<input name="full_name" required maxlength="120" value="<?=e($editUser['full_name']??'')?>"></label><label>Email<input type="email" name="email" required maxlength="160" value="<?=e($editUser['email']??'')?>"></label><label>Password<?=$editUser?' (leave blank to keep current)':''?><input type="password" name="password" <?=$editUser?'':'required'?> minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></label><label>Role<select name="role"><option value="student" <?=($editUser['role']??'student')==='student'?'selected':''?>>Student</option><option value="staff" <?=($editUser['role']??'')==='staff'?'selected':''?>>Staff</option><option value="admin" <?=($editUser['role']??'')==='admin'?'selected':''?>>Admin</option></select></label><div class="span-2 form-actions"><button class="btn primary" type="submit"><?=$editUser?'Save changes':'Create account'?></button><?php if($editUser): ?><a class="btn secondary" href="crud.php?tab=users">Cancel edit</a><?php endif; ?></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>User accounts</h2><p class="muted">Showing <?=count($users)?> account(s).</p></div></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?=e($u['full_name'])?></td><td><?=e($u['email'])?></td><td><span class="badge <?=e($u['role'])?>"><?=e(ucfirst($u['role']))?></span></td><td><?=e(date('M j, Y',strtotime($u['created_at'])))?></td><td><div class="inline-actions"><a class="btn small secondary" href="crud.php?tab=users&edit_user=<?=(int)$u['id']?>">Edit</a><?php if((int)$u['id']!==current_user_id()): ?><form method="post" onsubmit="return confirm('Delete this account and all its appointment records?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="kind" value="users"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><button class="btn small danger" type="submit">Delete</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
