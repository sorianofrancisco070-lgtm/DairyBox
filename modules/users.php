<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('farm_manager');

$root      = '../';
$pageTitle = 'User Management';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['full_name'] ?? '');
    $role     = $_POST['role'] ?? '';
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$username || !$fullname || !$role) { $error = 'Username, name and role are required.'; $action = 'add'; }
    else {
        if ($id > 0) {
            if ($pass) {
                $db->prepare("UPDATE users SET username=?,full_name=?,role=?,email=?,phone=?,is_active=?,password=? WHERE id=?")
                   ->execute([$username,$fullname,$role,$email,$phone,$active,password_hash($pass,PASSWORD_DEFAULT),$id]);
            } else {
                $db->prepare("UPDATE users SET username=?,full_name=?,role=?,email=?,phone=?,is_active=? WHERE id=?")
                   ->execute([$username,$fullname,$role,$email,$phone,$active,$id]);
            }
            $msg = 'User updated.';
        } else {
            if (!$pass) { $error = 'Password is required for new user.'; $action = 'add'; }
            else {
                $db->prepare("INSERT INTO users (username,password,full_name,role,email,phone,is_active) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$username,password_hash($pass,PASSWORD_DEFAULT),$fullname,$role,$email,$phone,$active]);
                $msg = 'User created.';
            }
        }
        if (!$error) $action = 'list';
    }
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    if ($did !== $user['id']) { $db->prepare("DELETE FROM users WHERE id=?")->execute([$did]); $msg='User deleted.'; }
    else { $msg='Cannot delete yourself.'; }
}

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM users WHERE id=?"); $s->execute([(int)$_GET['id']]); $editing = $s->fetch();
}

$users = $db->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-user-plus me-2"></i><?= $editing ? 'Edit User' : 'Add User' ?></div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Username *</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($editing['username'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($editing['full_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Role *</label>
                <select name="role" class="form-select" required>
                    <?php foreach (['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker','dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'] as $val=>$lbl): ?>
                    <option value="<?= $val ?>" <?= ($editing['role']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Password <?= $editing ? '(leave blank to keep)' : '*' ?></label>
                <input type="password" name="password" class="form-control" <?= !$editing ? 'required' : '' ?>>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editing['phone'] ?? '') ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= ($editing['is_active']??1)?'checked':'' ?>>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i><?= $editing ? 'Update' : 'Create User' ?></button>
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="fa fa-users me-2"></i>Users</div>
        <a href="?action=add" class="btn btn-success btn-sm"><i class="fa fa-plus me-1"></i>Add User</a>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Username</th><th>Full Name</th><th>Role</th><th>Email</th><th>Active</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php 
        $roleLabels = ['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker','dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'];
        foreach ($users as $i => $u): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><span class="badge bg-success"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
            <td><?= htmlspecialchars($u['email']??'-') ?></td>
            <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
                <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <?php if ($u['id'] !== $user['id']): ?>
                <a href="?delete=<?= $u['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete user?')"><i class="fa fa-trash"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
