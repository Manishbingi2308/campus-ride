<?php
/**
 * CampusRide - My Rides Management (Driver View)
 * View rides, accept/reject booking requests, cancel rides
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

// Process actions: accept booking, reject booking, cancel ride
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/my-rides.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Accept Passenger Booking Request
    if ($action === 'accept_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            // Lock booking and ride rows
            $stmt = $pdo->prepare(
                "SELECT b.*, r.driver_id, r.available_seats, r.origin, r.destination, r.departure_date,
                        u.name AS passenger_name, u.email AS passenger_email
                 FROM bookings b
                 JOIN rides r ON b.ride_id = r.id
                 JOIN users u ON b.passenger_id = u.id
                 WHERE b.id = ? FOR UPDATE"
            );
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if (!$booking || (int)$booking['driver_id'] !== $userId) {
                $pdo->rollBack();
                set_flash('error', 'Unauthorized or booking request not found.');
            } elseif ($booking['status'] !== 'pending') {
                $pdo->rollBack();
                set_flash('warning', 'This booking request is already marked as ' . $booking['status'] . '.');
            } elseif ($booking['available_seats'] < $booking['seats_booked']) {
                $pdo->rollBack();
                set_flash('error', 'Cannot accept: Available seats (' . $booking['available_seats'] . ') are insufficient for requested seats (' . $booking['seats_booked'] . ').');
            } else {
                // Deduct available seats
                $newSeats = $booking['available_seats'] - $booking['seats_booked'];
                $upRide = $pdo->prepare("UPDATE rides SET available_seats = ? WHERE id = ?");
                $upRide->execute([$newSeats, $booking['ride_id']]);

                // Update booking status to confirmed
                $upBook = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                $upBook->execute([$bookingId]);

                // Send notification to passenger
                $msg = sprintf(
                    'Great news! %s accepted your booking for %d seat(s) from %s to %s on %s.',
                    $user['name'],
                    $booking['seats_booked'],
                    $booking['origin'],
                    $booking['destination'],
                    format_date($booking['departure_date'])
                );
                create_notification(
                    $pdo,
                    (int)$booking['passenger_id'],
                    'Booking Confirmed! ✅',
                    $msg,
                    'booking_confirmed',
                    BASE_URL . '/my-bookings.php'
                );

                $pdo->commit();
                set_flash('success', 'Booking request for ' . htmlspecialchars($booking['passenger_name']) . ' confirmed successfully!');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Accept booking error: " . $e->getMessage());
            set_flash('error', 'Could not accept booking. Database error occurred.');
        }
        header('Location: ' . BASE_URL . '/my-rides.php');
        exit;
    }

    // 2. Reject Passenger Booking Request
    if ($action === 'reject_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        try {
            $stmt = $pdo->prepare(
                "SELECT b.*, r.driver_id, r.origin, r.destination, r.departure_date, u.name AS passenger_name 
                 FROM bookings b
                 JOIN rides r ON b.ride_id = r.id
                 JOIN users u ON b.passenger_id = u.id
                 WHERE b.id = ?"
            );
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if (!$booking || (int)$booking['driver_id'] !== $userId) {
                set_flash('error', 'Unauthorized action.');
            } elseif ($booking['status'] !== 'pending') {
                set_flash('warning', 'Booking is already ' . $booking['status'] . '.');
            } else {
                $up = $pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
                $up->execute([$bookingId]);

                $msg = sprintf(
                    'Your booking request for %s to %s on %s was not accepted by the driver.',
                    $booking['origin'],
                    $booking['destination'],
                    format_date($booking['departure_date'])
                );
                create_notification(
                    $pdo,
                    (int)$booking['passenger_id'],
                    'Booking Request Declined ❌',
                    $msg,
                    'booking_rejected',
                    BASE_URL . '/find-ride.php'
                );

                set_flash('info', 'Booking request rejected.');
            }
        } catch (Exception $e) {
            error_log("Reject booking error: " . $e->getMessage());
            set_flash('error', 'Could not reject booking.');
        }
        header('Location: ' . BASE_URL . '/my-rides.php');
        exit;
    }

    // 3. Cancel Ride by Driver
    if ($action === 'cancel_ride') {
        $rideId = (int)($_POST['ride_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND driver_id = ? FOR UPDATE");
            $stmt->execute([$rideId, $userId]);
            $ride = $stmt->fetch();

            if (!$ride) {
                $pdo->rollBack();
                set_flash('error', 'Ride not found or you are not authorized to cancel it.');
            } elseif ($ride['status'] === 'cancelled') {
                $pdo->rollBack();
                set_flash('warning', 'This ride has already been cancelled.');
            } else {
                // Mark ride as cancelled
                $upRide = $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?");
                $upRide->execute([$rideId]);

                // Find all active passengers (pending or confirmed)
                $bStmt = $pdo->prepare("SELECT * FROM bookings WHERE ride_id = ? AND status IN ('pending', 'confirmed')");
                $bStmt->execute([$rideId]);
                $affectedBookings = $bStmt->fetchAll();

                // Update bookings to cancelled
                $upB = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status IN ('pending', 'confirmed')");
                $upB->execute([$rideId]);

                // Notify all affected passengers
                foreach ($affectedBookings as $b) {
                    $notifMsg = sprintf(
                        'Driver %s has cancelled the trip from %s to %s scheduled for %s.',
                        $user['name'],
                        $ride['origin'],
                        $ride['destination'],
                        format_date($ride['departure_date'])
                    );
                    create_notification(
                        $pdo,
                        (int)$b['passenger_id'],
                        'Ride Cancelled by Driver 🛑',
                        $notifMsg,
                        'ride_cancelled',
                        BASE_URL . '/find-ride.php'
                    );
                }

                $pdo->commit();
                set_flash('info', 'The ride has been cancelled. Affected passengers have been notified.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Cancel ride error: " . $e->getMessage());
            set_flash('error', 'Database error while cancelling ride.');
        }
        header('Location: ' . BASE_URL . '/my-rides.php');
        exit;
    }
}

// Fetch user's posted rides
$stmtRides = $pdo->prepare("SELECT * FROM rides WHERE driver_id = ? ORDER BY departure_date DESC, departure_time DESC");
$stmtRides->execute([$userId]);
$myRides = $stmtRides->fetchAll();

// For each ride, fetch booking requests
$ridesWithBookings = [];
foreach ($myRides as $r) {
    $rId = (int)$r['id'];
    $stmtB = $pdo->prepare(
        "SELECT b.*, u.name AS passenger_name, u.college_id AS passenger_college_id, 
                u.phone AS passenger_phone, u.user_type AS passenger_type, u.department AS passenger_dept
         FROM bookings b
         JOIN users u ON b.passenger_id = u.id
         WHERE b.ride_id = ?
         ORDER BY b.created_at DESC"
    );
    $stmtB->execute([$rId]);
    $r['bookings'] = $stmtB->fetchAll();
    $ridesWithBookings[] = $r;
}

$pageTitle = 'My Rides';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>My Offered Rides</h1>
      <p>Manage the rides you have published and review incoming passenger requests.</p>
    </div>
    <a href="<?= BASE_URL ?>/post-ride.php" class="btn sm">🚗 Post a New Ride</a>
  </div>

  <?php if (empty($ridesWithBookings)): ?>
    <div class="empty-state">
      <div class="icon">🚗</div>
      <h3>You have not posted any rides yet</h3>
      <p>Have an empty car or bike seat on your journey to campus? Offer a ride and help fellow college members.</p>
      <a href="<?= BASE_URL ?>/post-ride.php" class="btn">Offer a Ride Now</a>
    </div>
  <?php else: ?>
    <?php foreach ($ridesWithBookings as $ride): ?>
      <div class="card" style="margin-bottom:28px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
          <div>
            <h2 style="font-size:1.35rem; color:var(--brand-dark); display:flex; align-items:center; gap:8px;">
              <span><?= render_vehicle_icon($ride['vehicle_type']) ?></span>
              <?= e($ride['origin']) ?> <span class="ride-arrow">&rarr;</span> <?= e($ride['destination']) ?>
            </h2>
            <div style="font-size:0.92rem; color:var(--muted); margin-top:4px;">
              📅 <?= format_date($ride['departure_date']) ?> at ⏰ <?= format_time($ride['departure_time']) ?> &bull; 
              <?= e($ride['vehicle_type']) ?> <?= $ride['vehicle_model'] ? '(' . e($ride['vehicle_model']) . ')' : '' ?>
            </div>
          </div>
          <div style="display:flex; align-items:center; gap:10px;">
            <?= render_status_badge($ride['status']) ?>
            <?php if ($ride['status'] === 'active'): ?>
              <form method="POST" action="<?= BASE_URL ?>/my-rides.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_ride">
                <input type="hidden" name="ride_id" value="<?= $ride['id'] ?>">
                <button type="submit" class="btn sm danger-outline confirm-action" data-confirm="Are you sure you want to cancel this entire ride? All passengers will be notified.">
                  Cancel Ride
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="ride-meta" style="font-size:0.92rem; margin-bottom:20px;">
          <span>💺 <strong>Available Seats:</strong> <?= e($ride['available_seats']) ?> / <?= e($ride['total_seats']) ?></span>
          <span>💰 <strong>Fare:</strong> <?= format_currency($ride['price_per_seat']) ?> / seat</span>
          <span>🎟️ <strong>Total Requests:</strong> <?= count($ride['bookings']) ?></span>
        </div>

        <!-- Passenger Requests Section -->
        <h3 style="font-size:1.05rem; margin-bottom:12px; color:var(--ink);">
          Passenger Bookings &amp; Requests (<?= count($ride['bookings']) ?>)
        </h3>

        <?php if (empty($ride['bookings'])): ?>
          <p style="color:var(--muted); font-size:0.92rem; font-style:italic;">No booking requests received for this ride yet.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Passenger</th>
                  <th>College ID / Role</th>
                  <th>Seats</th>
                  <th>Total Cost</th>
                  <th>Pickup Landmark</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ride['bookings'] as $b): ?>
                  <tr>
                    <td>
                      <strong><?= e($b['passenger_name']) ?></strong><br>
                      <?php if ($b['status'] === 'confirmed'): ?>
                        <small style="color:var(--muted);">📞 <?= e($b['passenger_phone']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <code><?= e($b['passenger_college_id']) ?></code><br>
                      <small style="color:var(--muted);"><?= e($b['passenger_type']) ?> &bull; <?= e($b['passenger_dept'] ?? '') ?></small>
                    </td>
                    <td><strong><?= e($b['seats_booked']) ?></strong></td>
                    <td><strong><?= format_currency($b['total_price']) ?></strong></td>
                    <td><?= e($b['pickup_location'] ?: 'Standard origin') ?></td>
                    <td><?= render_status_badge($b['status']) ?></td>
                    <td>
                      <?php if ($b['status'] === 'pending' && $ride['status'] === 'active'): ?>
                        <div style="display:flex; gap:6px;">
                          <!-- Accept Button -->
                          <form method="POST" action="<?= BASE_URL ?>/my-rides.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="accept_booking">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn sm" <?= $ride['available_seats'] < $b['seats_booked'] ? 'disabled title="Not enough seats"' : '' ?>>
                              ✓ Accept
                            </button>
                          </form>

                          <!-- Reject Button -->
                          <form method="POST" action="<?= BASE_URL ?>/my-rides.php" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject_booking">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn sm danger-outline confirm-action" data-confirm="Decline this passenger request?">
                              ✗ Decline
                            </button>
                          </form>
                        </div>
                      <?php elseif ($b['status'] === 'confirmed'): ?>
                        <span style="color:var(--success); font-weight:700; font-size:0.88rem;">Confirmed Seat</span>
                      <?php else: ?>
                        <span style="color:var(--muted); font-size:0.85rem;">No action</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
