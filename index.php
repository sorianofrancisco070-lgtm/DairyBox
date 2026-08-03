<?php
session_start();
if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    if ($role === 'farm_manager') header('Location: Farm_Managers_User/dashboard.php');
    elseif ($role === 'farm_caretaker') header('Location: Farm_Caretakers_USer/dashboard.php');
    elseif ($role === 'dairy_cooperative') header('Location: Dairy_Cooperatives_USer/dashboard.php');
    elseif ($role === 'veterinarian') header('Location: Veterinarians_User/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DairyBox – Production & Herd Health System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-card shadow-lg">
            <div class="login-header text-center">
                <img src="assets/img/logo.png" alt="DairyBox Logo" class="login-logo" onerror="this.style.display='none'">
                <h2 class="mt-2 fw-bold text-success">🐃 DairyBox</h2>
                <p class="text-muted small">Production & Herd Health System</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger py-2 text-center small">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success py-2 text-center small">
                    <?= htmlspecialchars($_GET['msg']) ?>
                </div>
            <?php endif; ?>

            <form action="auth/login.php" method="POST" class="mt-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()">
                            <i class="fa fa-eye" id="pwd-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Login As</label>
                    <select name="role" class="form-select" required>
                        <option value="">-- Select Role --</option>
                        <option value="farm_manager">Farm Manager</option>
                        <option value="farm_caretaker">Farm Caretaker</option>
                        <option value="dairy_cooperative">Dairy Cooperative</option>
                        <option value="veterinarian">Veterinarian</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold">
                    <i class="fa fa-sign-in-alt me-1"></i> Login
                </button>
            </form>
            <hr>
            <p class="text-center text-muted small mb-0">
                South East Asian Institute of Technology &copy; <?= date('Y') ?>
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd() {
            const p = document.getElementById('password');
            const e = document.getElementById('pwd-eye');
            if (p.type === 'password') { p.type = 'text'; e.classList.replace('fa-eye','fa-eye-slash'); }
            else { p.type = 'password'; e.classList.replace('fa-eye-slash','fa-eye'); }
        }
    </script>
</body>
</html>
