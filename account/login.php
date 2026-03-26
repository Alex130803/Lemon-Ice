<?php
require_once __DIR__ . '/../admin/config.php';

$db = getDB();
$error = '';

if (currentCustomer()) {
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $customer = authenticateCustomer($db, $email, $password);

    if ($customer) {
        customerLogin($customer);
        header('Location: orders.php');
        exit;
    }

    $error = 'Wrong email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Panifit</title>
  <link rel="stylesheet" href="../style.css" />
</head>
<body>
  <nav id="navbar" class="scrolled">
    <a class="nav-logo" href="../index.html">PANI<span>FIT</span></a>
    <div class="nav-right">
      <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
      </button>
    </div>
  </nav>

  <main class="auth-page">
    <div class="auth-card">
      <div class="auth-shell">
        <aside class="auth-panel">
          <div class="auth-logo"><span>PANI</span><span>FIT</span></div>
          <p class="auth-sub">Sign in to track your orders and delivery status.</p>
          <div class="auth-panel-card">
            <div class="auth-panel-title">Inside your account</div>
            <div class="auth-benefits">
              <span>See every order status in one place</span>
              <span>Match orders automatically by checkout email</span>
              <span>Get faster access to shipping updates</span>
            </div>
          </div>
        </aside>

        <section class="auth-main">
          <div class="auth-kicker">Customer Login</div>
          <h1 class="auth-heading">Welcome back</h1>
          <p class="auth-copy">Use the same email address you entered at checkout.</p>

          <?php if ($error): ?>
            <div class="auth-error"><?= e($error) ?></div>
          <?php endif; ?>

          <form method="POST" class="auth-form">
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required placeholder="you@example.com" />
            </div>
            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" required placeholder="Enter your password" />
            </div>
            <button type="submit" class="auth-btn">Login</button>
          </form>

          <div class="auth-trust">
            <span></span>
            Secure access to your Panifit order history
          </div>

          <div class="auth-footer">
            No account yet? <a href="signup.php">Create one</a>
          </div>
        </section>
      </div>
    </div>
  </main>

  <script src="../app.js"></script>
</body>
</html>
