<?php
/**
 * CampusRide - Admin Dashboard Overview
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();

// Overall platform metrics
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'Student'")->fetchColumn();
$totalFaculty = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'Faculty'")->fetchColumn();

$totalRides = (int)$pdo->query("SELECT COUNT(*) FROM rides")->fetchColumn();
$activeRides = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE status = 'active'")->fetchColumn();
$totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$confirmedBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();

// Latest users registered
$latestUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Latest rides posted
$latestRides = $pdo->query(
    "SELECT r.*, u.name AS driver_name 
     FROM rides r 
     JOIN users u ON r.driver_id = u.id 
     ORDER BY r.created_at DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'CampusRide Administration';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <div style="font-size:0.9rem; color:var(--muted); margin-bottom:4px;">
        <a href="<?= BASE_URL ?>/dashboard.php">&larr; Back to User Dashboard</a>
      </div>
      <h1>Campus Administration Control Center</h1>
      <p>System overview, user verification, and ride moderation.</p>
    </div>
    <div style="display:flex; gap:10px;">
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn sm outline">👥 Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/rides.php" class="btn sm outline">🚗 Manage Rides</a>
      <a href="<?= BASE_URL ?>/admin/bookings.php" class="btn sm outline">🎟️ View Bookings</a>
    </div>
  </div>

  <!-- Admin Stats Grid -->
  <div class="grid stats">
    <div class="card stat blue">
      <div class="num"><?= $totalUsers ?></div>
      <div class="lbl">Total Users (<?= $totalStudents ?> Students, <?= $totalFaculty ?> Faculty)</div>
    </div>
    <div class="card stat green">
      <div class="num"><?= $activeRides ?></div>
      <div class="lbl">Active Rides (<?= $totalRides ?> Total)</div>
    </div>
    <div class="card stat gold">
      <div class="num"><?= $confirmedBookings ?></div>
      <div class="lbl">Confirmed Bookings (<?= $totalBookings ?> Requests)</div>
    </div>
    <div class="card stat">
      <div class="num"><?= date('H:i') ?></div>
      <div class="lbl">Local System Time (Port: <?= DB_PORT ?>)</div>
    </div>
  </div>

  <div class="row" style="align-items:start; gap:24px; margin-top:24px;">
    <!-- Recent Users -->
    <div style="flex:1;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="color:var(--brand-dark);">Recently Registered Users</h3>
        <a href="<?= BASE_URL ?>/admin/users.php" style="color:var(--brand); font-weight:700; font-size:0.9rem;">View All &rarr;</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>College ID</th>
              <th>Role</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latestUsers as $u): ?>
              <tr>
                <td><strong><?= e($u['name']) ?></strong><br><small style="color:var(--muted);"><?= e($u['email']) ?></small></td>
                <td><code><?= e($u['college_id']) ?></code></td>
                <td><?= e($u['user_type']) ?></td>
                <td>
                  <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Rides -->
    <div style="flex:1;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="color:var(--brand-dark);">Recently Published Rides</h3>
        <a href="<?= BASE_URL ?>/admin/rides.php" style="color:var(--brand); font-weight:700; font-size:0.9rem;">View All &rarr;</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Route</th>
              <th>Driver</th>
              <th>Departure</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latestRides as $r): ?>
              <tr>
                <td><strong><?= e($r['origin']) ?> &rarr; <?= e($r['destination']) ?></strong></td>
                <td><?= e($r['driver_name']) ?></td>
                <td><?= format_date($r['departure_date']) ?></td>
                <td><?= render_status_badge($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
