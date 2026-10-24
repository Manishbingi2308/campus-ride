<?php
/**
 * CampusRide - Global Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = $pageTitle ?? 'Share Your Ride. Save Money. Travel Together.';
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isLoggedIn = is_logged_in();
$user = $isLoggedIn ? current_user() : null;
$unreadNotifCount = 0;

if ($isLoggedIn && $user) {
    $pdo = get_db_connection();
    $unreadNotifCount = get_unread_notifications_count($pdo, (int)$user['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> - CampusRide</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="nav">
  <div class="container">
    <a href="<?= BASE_URL ?>/index.php" class="logo">
      🚗 Campus<span>Ride</span>
    </a>
    <ul class="nav-links">
      <?php if ($isLoggedIn): ?>
        <li><a href="<?= BASE_URL ?>/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="<?= BASE_URL ?>/find-ride.php" class="<?= $currentPage === 'find-ride.php' ? 'active' : '' ?>">Find Ride</a></li>
        <li><a href="<?= BASE_URL ?>/post-ride.php" class="<?= $currentPage === 'post-ride.php' ? 'active' : '' ?>">Post Ride</a></li>
        <li><a href="<?= BASE_URL ?>/my-rides.php" class="<?= $currentPage === 'my-rides.php' ? 'active' : '' ?>">My Rides</a></li>
        <li><a href="<?= BASE_URL ?>/my-bookings.php" class="<?= $currentPage === 'my-bookings.php' ? 'active' : '' ?>">My Bookings</a></li>
        <li>
          <a href="<?= BASE_URL ?>/notifications.php" class="<?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
            Alerts
            <?php if ($unreadNotifCount > 0): ?>
              <span class="nav-badge alert"><?= $unreadNotifCount ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li><a href="<?= BASE_URL ?>/profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"><?= e(explode(' ', $user['name'] ?? 'User')[0]) ?></a></li>
        <?php if (!empty($user['is_admin'])): ?>
          <li><a href="<?= BASE_URL ?>/admin/index.php" class="<?= str_contains($_SERVER['PHP_SELF'], '/admin/') ? 'active' : '' ?>" style="color:var(--brand);font-weight:700;">⚙️ Admin</a></li>
        <?php endif; ?>
        <li><a href="<?= BASE_URL ?>/logout.php" class="logout confirm-action" data-confirm="Are you sure you want to log out?">Logout</a></li>
      <?php else: ?>
        <li><a href="<?= BASE_URL ?>/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
        <li><a href="<?= BASE_URL ?>/find-ride.php" class="<?= $currentPage === 'find-ride.php' ? 'active' : '' ?>">Find a Ride</a></li>
        <li><a href="<?= BASE_URL ?>/login.php" class="<?= $currentPage === 'login.php' ? 'active' : '' ?>">Login</a></li>
        <li><a href="<?= BASE_URL ?>/register.php" class="btn sm <?= $currentPage === 'register.php' ? 'active' : '' ?>">Register</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<div class="container" style="padding-top:16px;">
  <?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endforeach; ?>
</div>
