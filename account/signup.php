<?php
require_once __DIR__ . '/../admin/config.php';

$db = getDB();
$error = '';

if (currentCustomer()) {
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must have at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (findCustomerByEmail($db, $email)) {
        $error = 'An account with this email already exists.';
    } else {
        createCustomer($db, $email, $password, $firstName, $lastName);
        $customer = authenticateCustomer($db, $email, $password);
        if ($customer) {
            customerLogin($customer);
            header('Location: orders.php');
            exit;
        }
        $error = 'Account was created, but login failed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up - Panifit</title>
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
          <p class="auth-sub">Create an account with the same email you use at checkout to track every order.</p>
          <div class="auth-panel-card">
            <div class="auth-panel-title">Why create one</div>
            <div class="auth-benefits">
              <span>Orders appear automatically after checkout</span>
              <span>Status changes are easier to follow</span>
              <span>One place for delivery updates and history</span>
            </div>
          </div>
        </aside>

        <section class="auth-main">
          <div class="auth-kicker">Create Account</div>
          <h1 class="auth-heading">Track every order</h1>
          <p class="auth-copy">Use your checkout email so Panifit can connect current and future orders.</p>

          <?php if ($error): ?>
            <div class="auth-error"><?= e($error) ?></div>
          <?php endif; ?>

          <form method="POST" class="auth-form">
            <div class="auth-grid">
              <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required placeholder="John" />
              </div>
              <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required placeholder="Doe" />
              </div>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required placeholder="you@example.com" />
            </div>
            <div class="auth-grid">
              <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Minimum 6 characters" />
              </div>
              <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password" />
              </div>
            </div>
            <button type="submit" class="auth-btn">Sign Up</button>
          </form>

          <div class="auth-trust">
            <span></span>
            Your order tracking unlocks as soon as you sign in
          </div>

          <div class="auth-footer">
            Already have an account? <a href="login.php">Login</a>
          </div>
        </section>
      </div>
    </div>
  </main>

  <script src="../app.js"></script>
</body>
</html>
