<?php
/**
 * CampusRide - User Login
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$identifier = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired session security token. Please try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = trim($_POST['redirect'] ?? '');

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter your email or College ID and password.';
        } else {
            $result = login_user($identifier, $password);
            if ($result['success']) {
                set_flash('success', 'Welcome back, ' . htmlspecialchars($result['user']['name']) . '!');
                // Prevent open redirect attack
                if (!empty($redirect) && str_starts_with($redirect, BASE_URL)) {
                    header('Location: ' . $redirect);
                } elseif (!empty($redirect) && str_starts_with($redirect, '/')) {
                    header('Location: ' . BASE_URL . $redirect);
                } else {
                    header('Location: ' . BASE_URL . '/dashboard.php');
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<main class="auth-wrap">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="sub">Log in to your CampusRide account.</p>

    <?php if (!empty($error)): ?>
      <div class="msg error" style="display:block;">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate id="loginForm">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

      <div class="field">
        <label for="identifier">Email or College ID</label>
        <input id="identifier" name="identifier" type="text" required placeholder="you@college.edu or 24P61A0554" value="<?= e($identifier) ?>" autofocus>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required placeholder="Your password">
      </div>

      <button class="btn" type="submit">Log In</button>
    </form>

    <p class="alt">New here? <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
