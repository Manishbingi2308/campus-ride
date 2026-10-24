<?php
/**
 * CampusRide - My Bookings Management (Passenger View)
 * View bookings, status, cancel bookings with seat restoration
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

// Process Cancel Booking Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/my-bookings.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT b.*, r.driver_id, r.origin, r.destination, r.departure_date, r.available_seats
                 FROM bookings b
                 JOIN rides r ON b.ride_id = r.id
                 WHERE b.id = ? AND b.passenger_id = ? FOR UPDATE"
            );
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $pdo->rollBack();
                set_flash('error', 'Booking not found or not owned by you.');
            } elseif (in_array($booking['status'], ['cancelled', 'rejected'])) {
                $pdo->rollBack();
                set_flash('warning', 'Booking is already ' . $booking['status'] . '.');
            } else {
                // If booking was confirmed, restore seats back to the ride!
                if ($booking['status'] === 'confirmed') {
                    $newSeats = $booking['available_seats'] + $booking['seats_booked'];
                    $upRide = $pdo->prepare("UPDATE rides SET available_seats = ? WHERE id = ?");
                    $upRide->execute([$newSeats, $booking['ride_id']]);
                }

                // Update booking status
                $upB = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                $upB->execute([$bookingId]);

                // Notify driver
                $notifMsg = sprintf(
                    '%s cancelled their booking (%d seat%s) for %s to %s on %s.',
                    $user['name'],
                    $booking['seats_booked'],
                    $booking['seats_booked'] > 1 ? 's' : '',
                    $booking['origin'],
                    $booking['destination'],
                    format_date($booking['departure_date'])
                );
                create_notification(
                    $pdo,
                    (int)$booking['driver_id'],
                    'Passenger Cancelled Booking ⚠️',
                    $notifMsg,
                    'booking_cancelled',
                    BASE_URL . '/my-rides.php'
                );

                $pdo->commit();
                set_flash('info', 'Your booking has been cancelled and seats restored to the ride.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Cancel booking error: " . $e->getMessage());
            set_flash('error', 'Could not cancel booking. Database error occurred.');
        }

        header('Location: ' . BASE_URL . '/my-bookings.php');
        exit;
    }
}

// Fetch all passenger bookings
$stmt = $pdo->prepare(
    "SELECT b.*, r.origin, r.destination, r.departure_date, r.departure_time,
            r.vehicle_type, r.vehicle_model, r.status AS ride_status,
            u.name AS driver_name, u.phone AS driver_phone, u.college_id AS driver_college_id
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users u ON r.driver_id = u.id
     WHERE b.passenger_id = ?
     ORDER BY b.created_at DESC"
);
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Bookings';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>My Bookings</h1>
      <p>View your ride requests, statuses, and manage your travel schedule.</p>
    </div>
    <a href="<?= BASE_URL ?>/find-ride.php" class="btn sm">🔍 Find a Ride</a>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="icon">🎟️</div>
      <h3>You have not requested any rides yet</h3>
      <p>Need a ride to or from campus? Search for active rides offered by verified college students and faculty.</p>
      <a href="<?= BASE_URL ?>/find-ride.php" class="btn">Find a Ride Now</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Route &amp; Vehicle</th>
            <th>Date &amp; Time</th>
            <th>Driver</th>
            <th>Seats Booked</th>
            <th>Total Cost</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <?php 
              $isPast = (strtotime($b['departure_date'] . ' ' . $b['departure_time']) <= time());
            ?>
            <tr>
              <td>
                <strong style="color:var(--brand-dark); font-size:1rem;">
                  <?= render_vehicle_icon($b['vehicle_type']) ?> <?= e($b['origin']) ?> &rarr; <?= e($b['destination']) ?>
                </strong><br>
                <small style="color:var(--muted);">
                  <?= e($b['vehicle_type']) ?> <?= $b['vehicle_model'] ? '(' . e($b['vehicle_model']) . ')' : '' ?>
                </small>
              </td>
              <td>
                📅 <?= format_date($b['departure_date']) ?><br>
                ⏰ <?= format_time($b['departure_time']) ?>
              </td>
              <td>
                <strong><?= e($b['driver_name']) ?></strong><br>
                <small style="color:var(--muted);">ID: <?= e($b['driver_college_id']) ?></small>
                <?php if ($b['status'] === 'confirmed'): ?>
                  <br><small style="color:var(--brand);">📞 <?= e($b['driver_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($b['seats_booked']) ?> seat<?= $b['seats_booked'] > 1 ? 's' : '' ?></strong>
                <?php if (!empty($b['pickup_location'])): ?>
                  <br><small style="color:var(--muted);">Spot: <?= e($b['pickup_location']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color:var(--brand);"><?= format_currency($b['total_price']) ?></strong>
              </td>
              <td>
                <?= render_status_badge($b['status']) ?>
                <?php if ($b['ride_status'] === 'cancelled'): ?>
                  <br><small style="color:var(--danger); font-weight:700;">(Ride Cancelled)</small>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex; flex-direction:column; gap:6px;">
                  <a href="<?= BASE_URL ?>/ride-details.php?id=<?= $b['ride_id'] ?>" class="btn sm outline" style="padding:4px 10px; font-size:0.82rem;">
                    View Ride
                  </a>

                  <?php if (in_array($b['status'], ['pending', 'confirmed']) && !$isPast && $b['ride_status'] === 'active'): ?>
                    <form method="POST" action="<?= BASE_URL ?>/my-bookings.php" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="cancel_booking">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn sm danger-outline confirm-action" style="padding:4px 10px; font-size:0.82rem;" data-confirm="Cancel this booking? If confirmed, your seat will be returned to the driver.">
                        Cancel Booking
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
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
