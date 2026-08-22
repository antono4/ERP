<?php
// -----------------------------------------------------
// Modul Login
// -----------------------------------------------------

function module_handle(): void
{
    if (Auth::check()) {
        redirect('index.php?page=dashboard');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (Auth::attempt($username, $password)) {
            logActivity('auth', 'LOGIN', "User {$username} login");
            redirect('index.php?page=dashboard');
        }
        setFlash('danger', 'Username atau password salah!');
        redirect('index.php?page=login');
    }
}

function module_render(): void
{
    $flash = getFlash();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <span class="h3"><i class="fas fa-cubes"></i> <b>Mini</b>ERP</span>
            <p class="mb-0 text-muted">Enterprise Resource Planning System</p>
        </div>
        <div class="card-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="input-group mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <hr>
            <p class="text-muted text-center mb-0">
                <small>Demo login: <b>admin</b> / <b>admin123</b></small>
            </p>
        </div>
    </div>
</div>
<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>
<?php
}
