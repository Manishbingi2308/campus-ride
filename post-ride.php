<?php
/**
 * CampusRide - Post a New Ride
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

$errors = [];
$rideData = [
    'origin' => '',
    'destination' => '',
    'departure_date' => date('Y-m-d', strtotime('+1 day')),
    'departure_time' => '08:30',
    'total_seats' => 3,
    'price_per_seat' => 40.00,
    'vehicle_type' => 'Car',
    'vehicle_model' => '',
    'vehicle_number' => '',
    'pickup_instructions' => '',
    'additional_notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    } else {
        $rideData['origin'] = trim($_POST['origin'] ?? '');
        $rideData['destination'] = trim($_POST['destination'] ?? '');
        $rideData['departure_date'] = trim($_POST['departure_date'] ?? '');
        $rideData['departure_time'] = trim($_POST['departure_time'] ?? '');
        $rideData['total_seats'] = (int)($_POST['total_seats'] ?? 1);
        $rideData['price_per_seat'] = (float)($_POST['price_per_seat'] ?? 0);
        $rideData['vehicle_type'] = trim($_POST['vehicle_type'] ?? 'Car');
        $rideData['vehicle_model'] = trim($_POST['vehicle_model'] ?? '');
        $rideData['vehicle_number'] = trim($_POST['vehicle_number'] ?? '');
        $rideData['pickup_instructions'] = trim($_POST['pickup_instructions'] ?? '');
        $rideData['additional_notes'] = trim($_POST['additional_notes'] ?? '');

        // Validation
        if (empty($rideData['origin'])) {
            $errors[] = 'Starting location (origin) is required.';
        }
        if (empty($rideData['destination'])) {
            $errors[] = 'Destination is required.';
        }
        if (strtolower($rideData['origin']) === strtolower($rideData['destination'])) {
            $errors[] = 'Starting location and destination cannot be identical.';
        }

        if (empty($rideData['departure_date']) || empty($rideData['departure_time'])) {
            $errors[] = 'Departure date and time are required.';
        } else {
            $departureTimestamp = strtotime($rideData['departure_date'] . ' ' . $rideData['departure_time']);
            if (!$departureTimestamp) {
                $errors[] = 'Invalid departure date or time.';
            } elseif ($departureTimestamp <= time()) {
                $errors[] = 'Departure time must be set in the future.';
            }
        }

        if ($rideData['total_seats'] < 1 || $rideData['total_seats'] > 8) {
            $errors[] = 'Available seats must be between 1 and 8.';
        }

        if ($rideData['price_per_seat'] < 0 || $rideData['price_per_seat'] > 10000) {
            $errors[] = 'Please specify a valid price per seat (₹0 or greater).';
        }

        $validVehicles = ['Bike', 'Scooter', 'Car', 'Auto', 'Cab Share', 'Other'];
        if (!in_array($rideData['vehicle_type'], $validVehicles, true)) {
            $errors[] = 'Please select a valid vehicle type.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                "INSERT INTO rides (
                    driver_id, origin, destination, departure_date, departure_time,
                    total_seats, available_seats, price_per_seat, vehicle_type,
                    vehicle_model, vehicle_number, pickup_instructions, additional_notes, status
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );

            $inserted = $stmt->execute([
                $userId,
                $rideData['origin'],
                $rideData['destination'],
                $rideData['departure_date'],
                $rideData['departure_time'],
                $rideData['total_seats'],
                $rideData['total_seats'], // available seats initially equals total
                $rideData['price_per_seat'],
                $rideData['vehicle_type'],
                $rideData['vehicle_model'] ?: null,
                $rideData['vehicle_number'] ?: null,
                $rideData['pickup_instructions'] ?: null,
                $rideData['additional_notes'] ?: null
            ]);

            if ($inserted) {
                $newRideId = (int)$pdo->lastInsertId();
                set_flash('success', 'Your ride has been successfully published! Other campus members can now find and request seats.');
                header('Location: ' . BASE_URL . '/ride-details.php?id=' . $newRideId);
                exit;
            } else {
                $errors[] = 'Database error while saving ride. Please try again.';
            }
        }
    }
}

$pageTitle = 'Post a Ride';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>Offer / Post a Ride</h1>
      <p>Share your vehicle with fellow students or colleagues and split commute expenses.</p>
    </div>
    <a href="<?= BASE_URL ?>/my-rides.php" class="btn sm outline">My Posted Rides &rarr;</a>
  </div>

  <div class="card" style="max-width:800px; margin: 16px auto 32px;">
    <?php if (!empty($errors)): ?>
      <div class="msg error" style="display:block;">
        <ul style="padding-left:18px; margin:0;">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/post-ride.php" novalidate>
      <?= csrf_field() ?>

      <h3 style="margin-bottom:16px; color:var(--brand-dark); border-bottom:1px solid var(--line); padding-bottom:8px;">
        1. Route Details
      </h3>

      <div class="row">
        <div class="field">
          <label for="origin">Starting Location (Origin) <span style="color:var(--danger)">*</span></label>
          <input id="origin" name="origin" type="text" required placeholder="e.g. Campus Main Gate 1, Hostel B, Uppal" value="<?= e($rideData['origin']) ?>">
          <small class="hint">Where will you begin your ride from?</small>
        </div>
        <div class="field">
          <label for="destination">Destination <span style="color:var(--danger)">*</span></label>
          <input id="destination" name="destination" type="text" required placeholder="e.g. Secunderabad Station, Metro Station" value="<?= e($rideData['destination']) ?>">
          <small class="hint">Final campus spot or drop location</small>
        </div>
      </div>

      <div class="field">
        <label for="pickup_instructions">Pickup Point &amp; Landmarks</label>
        <textarea id="pickup_instructions" name="pickup_instructions" rows="2" placeholder="e.g. Near the main entrance ATM, or outside Hostel C parking area"><?= e($rideData['pickup_instructions']) ?></textarea>
      </div>

      <h3 style="margin:24px 0 16px; color:var(--brand-dark); border-bottom:1px solid var(--line); padding-bottom:8px;">
        2. Schedule &amp; Capacity
      </h3>

      <div class="row">
        <div class="field">
          <label for="departure_date">Departure Date <span style="color:var(--danger)">*</span></label>
          <input id="departure_date" name="departure_date" type="date" min="<?= date('Y-m-d') ?>" required value="<?= e($rideData['departure_date']) ?>">
        </div>
        <div class="field">
          <label for="departure_time">Departure Time <span style="color:var(--danger)">*</span></label>
          <input id="departure_time" name="departure_time" type="time" required value="<?= e($rideData['departure_time']) ?>">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="total_seats">Available Passenger Seats <span style="color:var(--danger)">*</span></label>
          <input id="total_seats" name="total_seats" type="number" min="1" max="8" required value="<?= e($rideData['total_seats']) ?>">
          <small class="hint">Excluding driver (1 for Bike, 1-4 for Car)</small>
        </div>
        <div class="field">
          <label for="price_per_seat">Cost per Passenger (₹) <span style="color:var(--danger)">*</span></label>
          <input id="price_per_seat" name="price_per_seat" type="number" min="0" step="5" required value="<?= e($rideData['price_per_seat']) ?>">
          <small class="hint">Enter 0 if you are offering a free ride</small>
        </div>
      </div>

      <h3 style="margin:24px 0 16px; color:var(--brand-dark); border-bottom:1px solid var(--line); padding-bottom:8px;">
        3. Vehicle Information &amp; Preferences
      </h3>

      <div class="row">
        <div class="field">
          <label for="vehicle_type">Vehicle Type <span style="color:var(--danger)">*</span></label>
          <select id="vehicle_type" name="vehicle_type" required>
            <option value="Car" <?= $rideData['vehicle_type'] === 'Car' ? 'selected' : '' ?>>Car (Sedan / Hatchback / SUV)</option>
            <option value="Bike" <?= $rideData['vehicle_type'] === 'Bike' ? 'selected' : '' ?>>Motorcycle / Bike (1 passenger)</option>
            <option value="Scooter" <?= $rideData['vehicle_type'] === 'Scooter' ? 'selected' : '' ?>>Scooter (1 passenger)</option>
            <option value="Auto" <?= $rideData['vehicle_type'] === 'Auto' ? 'selected' : '' ?>>Auto Rickshaw Shared</option>
            <option value="Cab Share" <?= $rideData['vehicle_type'] === 'Cab Share' ? 'selected' : '' ?>>Pre-booked Taxi / Cab Share</option>
            <option value="Other" <?= $rideData['vehicle_type'] === 'Other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="field">
          <label for="vehicle_model">Vehicle Model (Optional)</label>
          <input id="vehicle_model" name="vehicle_model" type="text" placeholder="e.g. Swift White / Royal Enfield" value="<?= e($rideData['vehicle_model']) ?>">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="vehicle_number">Vehicle Number / Plate (Optional)</label>
          <input id="vehicle_number" name="vehicle_number" type="text" placeholder="e.g. TS 08 AB 1234" value="<?= e($rideData['vehicle_number']) ?>">
        </div>
        <div class="field">
          <label for="additional_notes">Additional Guidelines / Notes</label>
          <input id="additional_notes" name="additional_notes" type="text" placeholder="e.g. Helmet provided; non-smoking; AC on" value="<?= e($rideData['additional_notes']) ?>">
        </div>
      </div>

      <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn outline">Cancel</a>
        <button type="submit" class="btn">🚀 Publish Ride</button>
      </div>
    </form>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
