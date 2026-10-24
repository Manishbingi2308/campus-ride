<?php
/**
 * CampusRide - Admin User Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();
$currentAdminId = (int)$_SESSION['user_id'];

// Process Toggle Status or Toggle Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);

    if ($targetUserId === $currentAdminId) {
        set_flash('error', 'You cannot alter your own administrative status or deactivate yourself.');
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }

    if ($action === 'toggle_active') {
        $stmt = $pdo->prepare("SELECT is_active, name FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $u = $stmt->fetch();
        if ($u) {
            $newActive = $u['is_active'] ? 0 : 1;
            $up = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $up->execute([$newActive, $targetUserId]);
            $msg = $newActive ? 'User ' . htmlspecialchars($u['name']) . ' has been activated.' : 'User ' . htmlspecialchars($u['name']) . ' has been suspended.';
            set_flash('info', $msg);
        }
    }

    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM rides WHERE driver_id = u.id) AS rides_count,
               (SELECT COUNT(*) FROM bookings WHERE passenger_id = u.id) AS bookings_count
        FROM users u";
$params = [];

if (!empty($search)) {
    $sql .= " WHERE u.name LIKE ? OR u.email LIKE ? OR u.college_id LIKE ? OR u.phone LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}

$sql .= " ORDER BY u.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <div style="font-size:0.9rem; color:var(--muted); margin-bottom:4px;">
        <a href="<?= BASE_URL ?>/admin/index.php">&larr; Admin Dashboard</a>
      </div>
      <h1>Manage Campus Users</h1>
      <p>Verify accounts, view ride activity, and suspend violators.</p>
    </div>
  </div>

  <div class="filter-card">
    <form method="GET" action="<?= BASE_URL ?>/admin/users.php">
      <div style="display:flex; gap:12px; max-width:600px;">
        <input type="text" name="q" placeholder="Search by Name, Roll No, Email, or Phone..." value="<?= e($search) ?>" style="flex:1; padding:10px 14px; border:1.5px solid var(--line); border-radius:8px;">
        <button type="submit" class="btn sm">Search</button>
        <?php if (!empty($search)): ?>
          <a href="<?= BASE_URL ?>/admin/users.php" class="btn sm outline">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>User</th>
          <th>College ID</th>
          <th>Department / Year</th>
          <th>Contact</th>
          <th>Type / Admin</th>
          <th>Rides / Bookings</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <strong><?= e($u['name']) ?></strong><br>
              <small style="color:var(--muted);"><?= e($u['email']) ?></small>
            </td>
            <td><code><?= e($u['college_id']) ?></code></td>
            <td>
              <?= e($u['department'] ?? 'N/A') ?><br>
              <small style="color:var(--muted);"><?= e($u['year_of_study'] ?? 'N/A') ?></small>
            </td>
            <td><?= e($u['phone']) ?></td>
            <td>
              <span class="badge badge-info"><?= e($u['user_type']) ?></span>
              <?php if (!empty($u['is_admin'])): ?>
                <span class="badge badge-success" style="margin-left:4px;">Admin</span>
              <?php endif; ?>
            </td>
            <td>
              <?= e($u['rides_count']) ?> posted<br>
              <?= e($u['bookings_count']) ?> booked
            </td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
              </span>
            </td>
            <td>
              <?php if ((int)$u['id'] !== $currentAdminId): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/users.php" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn sm <?= $u['is_active'] ? 'danger-outline' : 'outline' ?> confirm-action" data-confirm="Are you sure you want to change this user's active status?">
                    <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                  </button>
                </form>
              <?php else: ?>
                <small style="color:var(--muted); font-weight:700;">(Current Admin)</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
