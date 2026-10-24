<?php
/**
 * CampusRide - Ride Details & Booking Submission
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();
$rideId = (int)($_GET['id'] ?? 0);
$currentUserId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

if ($rideId <= 0) {
    set_flash('error', 'Invalid ride selected.');
    header('Location: ' . BASE_URL . '/find-ride.php');
    exit;
}

// Fetch ride details along with driver info
$stmt = $pdo->prepare(
    "SELECT r.*, u.name AS driver_name, u.email AS driver_email, u.phone AS driver_phone,
            u.college_id AS driver_college_id, u.user_type AS driver_type, u.department AS driver_dept
     FROM rides r
     JOIN users u ON r.driver_id = u.id
     WHERE r.id = ? LIMIT 1"
);
$stmt->execute([$rideId]);
$ride = $stmt->fetch();

if (!$ride) {
    set_flash('error', 'The requested ride could not be found or has been removed.');
    header('Location: ' . BASE_URL . '/find-ride.php');
    exit;
}

$isOwner = ($currentUserId === (int)$ride['driver_id']);
$isPast = (strtotime($ride['departure_date'] . ' ' . $ride['departure_time']) <= time());

// Check current user's existing booking status for this ride
$existingBooking = null;
if ($currentUserId > 0) {
    $stmtB = $pdo->prepare(
        "SELECT * FROM bookings WHERE ride_id = ? AND passenger_id = ? ORDER BY created_at DESC LIMIT 1"
    );
    $stmtB->execute([$rideId, $currentUserId]);
    $existingBooking = $stmtB->fetch();
}

$errors = [];

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_ride') {
    require_login();

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif ($isOwner) {
        $errors[] = 'You cannot book seats on your own posted ride.';
    } elseif ($ride['status'] !== 'active') {
        $errors[] = 'This ride is no longer active.';
    } elseif ($isPast) {
        $errors[] = 'This ride has already departed and cannot be booked.';
    } elseif ($ride['available_seats'] <= 0) {
        $errors[] = 'Sorry, this ride is already fully booked.';
    } elseif ($existingBooking && in_array($existingBooking['status'], ['pending', 'confirmed'])) {
        $errors[] = 'You already have an active ' . $existingBooking['status'] . ' booking for this ride.';
    } else {
        $requestedSeats = (int)($_POST['seats_booked'] ?? 1);
        $pickupLocation = trim($_POST['pickup_location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($requestedSeats < 1) {
            $errors[] = 'You must request at least 1 seat.';
        } elseif ($requestedSeats > $ride['available_seats']) {
            $errors[] = 'Only ' . $ride['available_seats'] . ' seat(s) currently available.';
        }

        if (empty($errors)) {
            // Calculate total price server-side
            $totalPrice = (float)$ride['price_per_seat'] * $requestedSeats;

            try {
                $pdo->beginTransaction();

                // Re-verify available seats with row lock
                $lockStmt = $pdo->prepare("SELECT available_seats, status FROM rides WHERE id = ? FOR UPDATE");
                $lockStmt->execute([$rideId]);
                $freshRide = $lockStmt->fetch();

                if (!$freshRide || $freshRide['status'] !== 'active' || $freshRide['available_seats'] < $requestedSeats) {
                    $pdo->rollBack();
                    $errors[] = 'Seat availability changed or ride is no longer available. Please reload and try again.';
                } else {
                    // Insert booking with status 'pending'
                    $ins = $pdo->prepare(
                        "INSERT INTO bookings (ride_id, passenger_id, seats_booked, total_price, pickup_location, notes, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                    );
                    $ins->execute([
                        $rideId,
                        $currentUserId,
                        $requestedSeats,
                        $totalPrice,
                        $pickupLocation ?: null,
                        $notes ?: null
                    ]);
                    $bookingId = (int)$pdo->lastInsertId();

                    // Notify Driver of new booking request
                    $currentUserData = current_user();
                    $driverNotifMsg = sprintf(
                        '%s (%s) requested %d seat(s) for your trip from %s to %s on %s.',
                        $currentUserData['name'],
                        $currentUserData['user_type'],
                        $requestedSeats,
                        $ride['origin'],
                        $ride['destination'],
                        format_date($ride['departure_date'])
                    );

                    create_notification(
                        $pdo,
                        (int)$ride['driver_id'],
                        'New Ride Request Received 📩',
                        $driverNotifMsg,
                        'booking_request',
                        BASE_URL . '/my-rides.php'
                    );

                    $pdo->commit();

                    set_flash('success', 'Your booking request for ' . $requestedSeats . ' seat(s) has been submitted! The driver has been notified and will review your request.');
                    header('Location: ' . BASE_URL . '/my-bookings.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Booking error: " . $e->getMessage());
                $errors[] = 'Failed to submit booking request. Please try again.';
            }
        }
    }
}

$pageTitle = 'Ride: ' . $ride['origin'] . ' to ' . $ride['destination'];
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <div style="font-size:0.9rem; color:var(--muted); margin-bottom:4px;">
        <a href="<?= BASE_URL ?>/find-ride.php">&larr; Back to all rides</a>
      </div>
      <h1>Ride Details &amp; Booking</h1>
    </div>
    <div>
      <?= render_status_badge($ride['status']) ?>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="msg error" style="display:block;">
      <ul style="padding-left:18px; margin:0;">
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row" style="align-items:start; gap:24px;">
    <!-- Left Column: Detailed Trip Info -->
    <div style="flex:1;">
      <div class="card" style="margin-bottom:24px;">
        <h2 style="font-size:1.4rem; color:var(--brand-dark); margin-bottom:16px;">
          <?= render_vehicle_icon($ride['vehicle_type']) ?> 
          <?= e($ride['origin']) ?> <span class="ride-arrow">&rarr;</span> <?= e($ride['destination']) ?>
        </h2>

        <div class="ride-meta" style="font-size:0.98rem; padding:16px 0; border-top:1px solid var(--line); border-bottom:1px solid var(--line);">
          <div>📅 <strong>Date:</strong> <?= format_date($ride['departure_date']) ?></div>
          <div>⏰ <strong>Departure Time:</strong> <?= format_time($ride['departure_time']) ?></div>
          <div>💺 <strong>Available Seats:</strong> <?= e($ride['available_seats']) ?> / <?= e($ride['total_seats']) ?></div>
          <div>💰 <strong>Cost:</strong> <span style="font-size:1.2rem; font-weight:800; color:var(--brand);"><?= format_currency($ride['price_per_seat']) ?></span> / seat</div>
        </div>

        <div style="margin-top:20px;">
          <h3 style="font-size:1.05rem; margin-bottom:10px; color:var(--ink);">Vehicle Information</h3>
          <p style="color:var(--muted);">
            <strong>Type:</strong> <?= e($ride['vehicle_type']) ?><br>
            <?php if (!empty($ride['vehicle_model'])): ?>
              <strong>Model:</strong> <?= e($ride['vehicle_model']) ?><br>
            <?php endif; ?>
            <?php if (!empty($ride['vehicle_number'])): ?>
              <strong>Vehicle Plate:</strong> <?= e($ride['vehicle_number']) ?><br>
            <?php endif; ?>
          </p>
        </div>

        <?php if (!empty($ride['pickup_instructions'])): ?>
          <div style="margin-top:20px; background:var(--leaf); padding:16px; border-radius:10px;">
            <strong style="color:var(--brand-dark); display:block; margin-bottom:4px;">📍 Pickup Landmark / Instructions:</strong>
            <p style="margin:0; font-size:0.95rem;"><?= nl2br(e($ride['pickup_instructions'])) ?></p>
          </div>
        <?php endif; ?>

        <?php if (!empty($ride['additional_notes'])): ?>
          <div style="margin-top:16px; background:#fafcfb; border:1px solid var(--line); padding:16px; border-radius:10px;">
            <strong style="display:block; margin-bottom:4px;">📝 Notes from Driver:</strong>
            <p style="margin:0; font-size:0.95rem; color:var(--muted);"><?= nl2br(e($ride['additional_notes'])) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Driver Information Card -->
      <div class="card">
        <h3 style="font-size:1.1rem; margin-bottom:12px; color:var(--ink);">Driver Profile</h3>
        <div style="display:flex; align-items:center; gap:16px;">
          <div style="width:52px; height:52px; border-radius:50%; background:var(--sun-light); color:#7d5a00; font-weight:800; font-size:1.3rem; display:grid; place-items:center;">
            <?= strtoupper(substr($ride['driver_name'], 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:700; font-size:1.1rem;"><?= e($ride['driver_name']) ?></div>
            <div style="color:var(--muted); font-size:0.9rem;">
              <?= e($ride['driver_type']) ?> &bull; <?= e($ride['driver_dept'] ?? 'Campus Member') ?> &bull; ID: <?= e($ride['driver_college_id']) ?>
            </div>
            <?php if ($isLoggedIn && ($existingBooking && $existingBooking['status'] === 'confirmed')): ?>
              <div style="margin-top:8px; font-size:0.9rem;">
                📞 <strong>Contact:</strong> <?= e($ride['driver_phone']) ?> | ✉️ <?= e($ride['driver_email']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Booking Action Card -->
    <div style="width:360px; max-width:100%;">
      <div class="card" style="position:sticky; top:88px;">
        <h3 style="margin-bottom:14px; font-size:1.2rem;">Reserve Your Seat</h3>

        <?php if ($isOwner): ?>
          <div class="alert alert-info">
            🚗 You posted this ride. You can view passenger requests and update this ride in <strong>My Rides</strong>.
          </div>
          <a href="<?= BASE_URL ?>/my-rides.php" class="btn" style="width:100%;">Manage This Ride</a>

        <?php elseif ($isPast): ?>
          <div class="alert alert-warning">
            ⏳ This trip has already departed. New bookings are closed.
          </div>
          <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline" style="width:100%;">Find Another Ride</a>

        <?php elseif ($ride['status'] !== 'active'): ?>
          <div class="alert alert-danger">
            🛑 This ride has been cancelled by the driver.
          </div>
          <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline" style="width:100%;">Find Another Ride</a>

        <?php elseif ($existingBooking && in_array($existingBooking['status'], ['pending', 'confirmed'])): ?>
          <div class="alert alert-<?= $existingBooking['status'] === 'confirmed' ? 'success' : 'warning' ?>">
            <?php if ($existingBooking['status'] === 'confirmed'): ?>
              ✅ Your booking is <strong>Confirmed</strong> for <?= e($existingBooking['seats_booked']) ?> seat(s)!
            <?php else: ?>
              ⏳ Your booking request for <?= e($existingBooking['seats_booked']) ?> seat(s) is <strong>Pending Driver Approval</strong>.
            <?php endif; ?>
          </div>
          <a href="<?= BASE_URL ?>/my-bookings.php" class="btn" style="width:100%;">View in My Bookings</a>

        <?php elseif ($ride['available_seats'] <= 0): ?>
          <div class="alert alert-warning">
            💺 All seats for this ride have been booked.
          </div>
          <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline" style="width:100%;">Find Other Rides</a>

        <?php else: ?>
          <?php if (!$isLoggedIn): ?>
            <p style="color:var(--muted); font-size:0.95rem; margin-bottom:16px;">
              Please log in to your verified campus account to request seats.
            </p>
            <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn" style="width:100%;">Log In to Book</a>
          <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>/ride-details.php?id=<?= $rideId ?>" id="bookingForm">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="book_ride">

              <div class="field">
                <label for="seats_booked">Number of Seats</label>
                <select id="seats_booked" name="seats_booked" required onchange="updatePrice(this.value, <?= (float)$ride['price_per_seat'] ?>)">
                  <?php for ($i = 1; $i <= $ride['available_seats']; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?> Seat<?= $i > 1 ? 's' : '' ?></option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="field">
                <label for="pickup_location">Preferred Pickup Landmark</label>
                <input type="text" id="pickup_location" name="pickup_location" placeholder="e.g. Near Bus Shelter, Gate 2">
              </div>

              <div class="field">
                <label for="notes">Notes for Driver (Optional)</label>
                <textarea id="notes" name="notes" rows="2" placeholder="e.g. Carrying small backpack"></textarea>
              </div>

              <div style="background:#f9fbfb; padding:12px; border-radius:8px; margin-bottom:16px; border:1px solid var(--line);">
                <div style="display:flex; justify-content:space-between; font-size:0.92rem; color:var(--muted);">
                  <span>Price per passenger:</span>
                  <span><?= format_currency($ride['price_per_seat']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-weight:800; font-size:1.15rem; color:var(--brand-dark); margin-top:4px;">
                  <span>Total Estimated Fare:</span>
                  <span id="totalFareDisplay"><?= format_currency($ride['price_per_seat']) ?></span>
                </div>
              </div>

              <button type="submit" class="btn" style="width:100%;">
                Request Booking
              </button>
            </form>

            <script>
            function updatePrice(seats, unitPrice) {
              const total = (parseInt(seats) || 1) * unitPrice;
              document.getElementById('totalFareDisplay').textContent = '₹' + total.toFixed(2);
            }
            </script>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
