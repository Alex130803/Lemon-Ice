<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && verifyPassword($pass)) {
        $_SESSION['panifit_admin'] = true;
        $_SESSION['panifit_login_time'] = time();
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — Panifit</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect x='15' y='15' width='70' height='70' rx='18' fill='%23FFD84D' opacity='0.9'/></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root { --yellow:#FFD84D; --black:#0D0D0D; --dark:#141414; --dark2:#1c1c1c; --white:#FAFAF8; --gray:#888; --border:rgba(255,255,255,0.08); }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--black); color:var(--white); min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .login-card { width:100%; max-width:400px; padding:48px; background:var(--dark); border:1px solid var(--border); border-radius:20px; }
    .login-logo { font-family:'Bebas Neue',sans-serif; font-size:32px; letter-spacing:4px; text-align:center; margin-bottom:8px; }
    .login-logo span:first-child { color:var(--yellow); }
    .login-logo span:last-child { color:var(--white); }
    .login-sub { text-align:center; color:var(--gray); font-size:13px; margin-bottom:32px; font-family:'Space Mono',monospace; letter-spacing:1px; text-transform:uppercase; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:12px; color:var(--gray); font-weight:600; letter-spacing:1px; text-transform:uppercase; margin-bottom:6px; }
    .form-group input { width:100%; padding:14px 16px; background:var(--dark2); border:1px solid var(--border); border-radius:10px; color:var(--white); font-family:'DM Sans',sans-serif; font-size:15px; outline:none; transition:border-color 0.2s; }
    .form-group input:focus { border-color:var(--yellow); }
    .form-group input::placeholder { color:rgba(255,255,255,0.2); }
    .login-btn { width:100%; padding:16px; background:var(--yellow); color:var(--black); border:none; border-radius:12px; font-family:'Bebas Neue',sans-serif; font-size:20px; letter-spacing:3px; cursor:pointer; transition:all 0.2s; margin-top:8px; }
    .login-btn:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(255,216,77,0.3); }
    .error { background:rgba(255,85,85,0.1); border:1px solid rgba(255,85,85,0.2); color:#ff8888; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:20px; text-align:center; }
    .login-footer { text-align:center; margin-top:24px; color:var(--gray); font-size:12px; }
    .login-footer a { color:var(--yellow); text-decoration:none; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo"><span>PANI</span><span>FIT</span></div>
    <div class="login-sub">Admin Panel</div>

    <?php if ($error): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required placeholder="Enter username" autofocus />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter password" />
      </div>
      <button type="submit" class="login-btn">Sign In</button>
    </form>

    <div class="login-footer">
      <a href="../index.html">&larr; Back to site</a>
    </div>
  </div>
</body>
</html>
