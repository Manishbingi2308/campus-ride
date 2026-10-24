<?php
/**
 * CampusRide - Admin Rides Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();

// Admin can deactivate/cancel an inappropriate ride
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/admin/rides.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'deactivate_ride') {
        $rideId = (int)($_POST['ride_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? FOR UPDATE");
            $stmt->execute([$rideId]);
            $ride = $stmt->fetch();

            if ($ride) {
                $up = $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?");
                $up->execute([$rideId]);

                // Cancel bookings and notify driver and passengers
                $upB = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status IN ('pending', 'confirmed')");
                $upB->execute([$rideId]);

                // Notify driver
                create_notification(
                    $pdo,
                    (int)$ride['driver_id'],
                    'Ride Moderation Notice ⚠️',
                    'Your ride (' . $ride['origin'] . ' to ' . $ride['destination'] . ') has been deactivated by the campus administrator.',
                    'ride_cancelled',
                    BASE_URL . '/my-rides.php'
                );

                $pdo->commit();
                set_flash('info', 'Ride deactivated and cancelled by administrator.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Admin ride cancel error: " . $e->getMessage());
            set_flash('error', 'Error cancelling ride.');
        }
    }

    header('Location: ' . BASE_URL . '/admin/rides.php');
    exit;
}

$stmt = $pdo->query(
    "SELECT r.*, u.name AS driver_name, u.college_id AS driver_college_id,
            (SELECT COUNT(*) FROM bookings WHERE ride_id = r.id) AS bookings_count
     FROM rides r
     JOIN users u ON r.driver_id = u.id
     ORDER BY r.created_at DESC LIMIT 100"
);
$rides = $stmt->fetchAll();

$pageTitle = 'Manage Rides - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <div style="font-size:0.9rem; color:var(--muted); margin-bottom:4px;">
        <a href="<?= BASE_URL ?>/admin/index.php">&larr; Admin Dashboard</a>
      </div>
      <h1>Manage All Campus Rides</h1>
      <p>Audit and moderate published rides across all departments.</p>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Route</th>
          <th>Driver</th>
          <th>Departure</th>
          <th>Seats Available</th>
          <th>Fare</th>
          <th>Vehicle</th>
          <th>Bookings</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rides as $r): ?>
          <tr>
            <td>
              <strong><?= e($r['origin']) ?> &rarr; <?= e($r['destination']) ?></strong>
            </td>
            <td>
              <strong><?= e($r['driver_name']) ?></strong><br>
              <code><?= e($r['driver_college_id']) ?></code>
            </td>
            <td>
              📅 <?= format_date($r['departure_date']) ?><br>
              ⏰ <?= format_time($r['departure_time']) ?>
            </td>
            <td><?= e($r['available_seats']) ?> / <?= e($r['total_seats']) ?></td>
            <td><strong><?= format_currency($r['price_per_seat']) ?></strong></td>
            <td><?= e($r['vehicle_type']) ?> <?= $r['vehicle_model'] ? '(' . e($r['vehicle_model']) . ')' : '' ?></td>
            <td><?= e($r['bookings_count']) ?></td>
            <td><?= render_status_badge($r['status']) ?></td>
            <td>
              <div style="display:flex; gap:6px;">
                <a href="<?= BASE_URL ?>/ride-details.php?id=<?= $r['id'] ?>" class="btn sm outline">View</a>
                <?php if ($r['status'] === 'active'): ?>
                  <form method="POST" action="<?= BASE_URL ?>/admin/rides.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="deactivate_ride">
                    <input type="hidden" name="ride_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn sm danger-outline confirm-action" data-confirm="Deactivate this ride?">
                      Cancel Ride
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
