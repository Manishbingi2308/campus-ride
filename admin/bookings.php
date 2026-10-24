<?php
/**
 * CampusRide - Admin Bookings Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$pdo = get_db_connection();

$stmt = $pdo->query(
    "SELECT b.*, 
            r.origin, r.destination, r.departure_date, r.departure_time, r.price_per_seat,
            d.name AS driver_name, d.college_id AS driver_college_id,
            p.name AS passenger_name, p.college_id AS passenger_college_id, p.phone AS passenger_phone
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users d ON r.driver_id = d.id
     JOIN users p ON b.passenger_id = p.id
     ORDER BY b.created_at DESC LIMIT 100"
);
$bookings = $stmt->fetchAll();

$pageTitle = 'Manage Bookings - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <div style="font-size:0.9rem; color:var(--muted); margin-bottom:4px;">
        <a href="<?= BASE_URL ?>/admin/index.php">&larr; Admin Dashboard</a>
      </div>
      <h1>Campus Bookings Audit Log</h1>
      <p>Real-time audit log of all ride requests and confirmations.</p>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Booking ID</th>
          <th>Route &amp; Date</th>
          <th>Driver</th>
          <th>Passenger</th>
          <th>Seats</th>
          <th>Fare Total</th>
          <th>Status</th>
          <th>Requested At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><code>#<?= $b['id'] ?></code></td>
            <td>
              <strong><?= e($b['origin']) ?> &rarr; <?= e($b['destination']) ?></strong><br>
              <small style="color:var(--muted);">📅 <?= format_date($b['departure_date']) ?> at <?= format_time($b['departure_time']) ?></small>
            </td>
            <td>
              <strong><?= e($b['driver_name']) ?></strong><br>
              <code><?= e($b['driver_college_id']) ?></code>
            </td>
            <td>
              <strong><?= e($b['passenger_name']) ?></strong><br>
              <code><?= e($b['passenger_college_id']) ?></code> &bull; 📞 <?= e($b['passenger_phone']) ?>
            </td>
            <td><strong><?= e($b['seats_booked']) ?></strong></td>
            <td><strong style="color:var(--brand);"><?= format_currency($b['total_price']) ?></strong></td>
            <td><?= render_status_badge($b['status']) ?></td>
            <td><?= format_datetime($b['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
