<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'My Profile';
$db        = getDB();
$user      = currentUser();
$msg = $error = '';

// Reload full user record
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// ---- Update profile ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $fullname = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        if (!$fullname) { $error = 'Full name is required.'; }
        else {
            $db->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?")
               ->execute([$fullname, $email, $phone, $user['id']]);
            $_SESSION['user']['full_name'] = $fullname;
            $msg = 'Profile updated.';
            // Refresh
            $stmt->execute([$user['id']]); $profile = $stmt->fetch();
        }
    }

    if ($_POST['action'] === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $profile['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")
               ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Password changed successfully.';
        }
    }
}

// Recent activity
$activity = $db->prepare("SELECT * FROM activity_log WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$activity->execute([$user['id']]);
$activity = $activity->fetchAll();

$roleLabels = ['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker','dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'];

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
    <!-- Profile Info -->
    <div class="col-md-6">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-user-circle me-2"></i>Profile Information</div>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3 text-center">
                    <div style="width:80px;height:80px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:2rem">
                        🐃
                    </div>
                    <span class="badge bg-success mt-2"><?= $roleLabels[$profile['role']] ?? $profile['role'] ?></span>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($profile['username']) ?>" disabled>
                    <small class="text-muted">Username cannot be changed.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="fa fa-save me-1"></i>Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Change Password + Activity -->
    <div class="col-md-6">
        <div class="card-section mb-3">
            <div class="section-title"><i class="fa fa-lock me-2"></i>Change Password</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                    <small class="text-muted">Minimum 6 characters.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning w-100"><i class="fa fa-key me-1"></i>Change Password</button>
            </form>
        </div>

        <div class="card-section">
            <div class="section-title"><i class="fa fa-history me-2"></i>Recent Activity</div>
            <?php if (empty($activity)): ?>
            <p class="text-muted small">No activity recorded yet.</p>
            <?php else: ?>
            <table class="table table-sm">
                <thead><tr><th>Action</th><th>Module</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($activity as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['action']) ?></td>
                    <td><?= htmlspecialchars($a['module']) ?></td>
                    <td><small class="text-muted"><?= date('M d, H:i', strtotime($a['created_at'])) ?></small></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
