<?php
/**
 * CampusRide - Find / Search Rides
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db_connection();
$currentUserId = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

// Filter params
$origin = trim($_GET['origin'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$date = trim($_GET['date'] ?? '');
$minSeats = (int)($_GET['min_seats'] ?? 1);
$maxPrice = trim($_GET['max_price'] ?? '');
$vehicleType = trim($_GET['vehicle_type'] ?? '');

// Base query for active future rides with remaining seats
$sql = "SELECT r.*, u.name AS driver_name, u.college_id AS driver_college_id, u.user_type AS driver_type, u.department AS driver_dept
        FROM rides r
        JOIN users u ON r.driver_id = u.id
        WHERE r.status = 'active'
          AND r.available_seats >= ?
          AND (r.departure_date > CURDATE() OR (r.departure_date = CURDATE() AND r.departure_time >= CURTIME()))";

$params = [$minSeats];

if (!empty($origin)) {
    $sql .= " AND r.origin LIKE ?";
    $params[] = '%' . $origin . '%';
}

if (!empty($destination)) {
    $sql .= " AND r.destination LIKE ?";
    $params[] = '%' . $destination . '%';
}

if (!empty($date)) {
    $sql .= " AND r.departure_date = ?";
    $params[] = $date;
}

if ($maxPrice !== '' && is_numeric($maxPrice)) {
    $sql .= " AND r.price_per_seat <= ?";
    $params[] = (float)$maxPrice;
}

if (!empty($vehicleType)) {
    $sql .= " AND r.vehicle_type = ?";
    $params[] = $vehicleType;
}

$sql .= " ORDER BY r.departure_date ASC, r.departure_time ASC LIMIT 60";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rides = $stmt->fetchAll();

$pageTitle = 'Find a Ride';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>Find a Campus Ride</h1>
      <p>Search verified student &amp; faculty drivers heading along your route.</p>
    </div>
    <?php if (is_logged_in()): ?>
      <a href="<?= BASE_URL ?>/post-ride.php" class="btn sm">🚗 Offer a Ride</a>
    <?php endif; ?>
  </div>

  <!-- Search & Filter Bar -->
  <div class="filter-card">
    <form method="GET" action="<?= BASE_URL ?>/find-ride.php">
      <div class="filter-grid">
        <div class="field" style="margin-bottom:0;">
          <label for="origin">From (Origin)</label>
          <input type="text" id="origin" name="origin" placeholder="e.g. Campus Gate, Uppal" value="<?= e($origin) ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="destination">To (Destination)</label>
          <input type="text" id="destination" name="destination" placeholder="e.g. Metro, Secunderabad" value="<?= e($destination) ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="date">Date</label>
          <input type="date" id="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="vehicle_type">Vehicle</label>
          <select id="vehicle_type" name="vehicle_type">
            <option value="">Any Vehicle</option>
            <option value="Car" <?= $vehicleType === 'Car' ? 'selected' : '' ?>>Car</option>
            <option value="Bike" <?= $vehicleType === 'Bike' ? 'selected' : '' ?>>Bike</option>
            <option value="Scooter" <?= $vehicleType === 'Scooter' ? 'selected' : '' ?>>Scooter</option>
            <option value="Auto" <?= $vehicleType === 'Auto' ? 'selected' : '' ?>>Auto</option>
            <option value="Cab Share" <?= $vehicleType === 'Cab Share' ? 'selected' : '' ?>>Cab Share</option>
          </select>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="max_price">Max Price (₹)</label>
          <input type="number" id="max_price" name="max_price" min="0" placeholder="e.g. 50" value="<?= e($maxPrice) ?>">
        </div>
        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn" style="width:100%; height:46px;">🔍 Filter</button>
          <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline" style="height:46px;" title="Reset Filters">↺</a>
        </div>
      </div>
    </form>
  </div>

  <!-- Rides Result List -->
  <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
    <h2 class="title" style="margin-bottom:0; font-size:1.4rem;">
      Available Rides (<?= count($rides) ?>)
    </h2>
    <?php if (!empty($origin) || !empty($destination) || !empty($date) || !empty($vehicleType) || $maxPrice !== ''): ?>
      <span class="badge badge-info">Filtered Results</span>
    <?php endif; ?>
  </div>

  <?php if (empty($rides)): ?>
    <div class="empty-state">
      <div class="icon">🚗💨</div>
      <h3>No matching rides found</h3>
      <p>
        No upcoming rides match your exact route or criteria. Try broadening your search or offering a ride yourself!
      </p>
      <div style="display:flex; justify-content:center; gap:12px; margin-top:16px;">
        <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline sm">Clear All Filters</a>
        <?php if (is_logged_in()): ?>
          <a href="<?= BASE_URL ?>/post-ride.php" class="btn sm">Offer This Ride Instead</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rides as $r): ?>
        <?php $isOwnRide = ($currentUserId === (int)$r['driver_id']); ?>
        <div class="card ride-card">
          <div class="ride-header">
            <div class="ride-route">
              <div class="ride-place">
                <span><?= render_vehicle_icon($r['vehicle_type']) ?></span>
                <?= e($r['origin']) ?>
              </div>
              <div class="ride-place" style="font-size:0.95rem; color:var(--muted);">
                <span class="ride-arrow">&darr;</span> <?= e($r['destination']) ?>
              </div>
            </div>
            <div>
              <span class="badge <?= $r['available_seats'] > 1 ? 'badge-success' : 'badge-warning' ?>">
                <?= e($r['available_seats']) ?> seat<?= $r['available_seats'] > 1 ? 's' : '' ?> left
              </span>
            </div>
          </div>

          <div class="ride-meta">
            <span>📅 <?= format_date($r['departure_date']) ?></span>
            <span>⏰ <?= format_time($r['departure_time']) ?></span>
            <span>🚘 <?= e($r['vehicle_type']) ?> <?= $r['vehicle_model'] ? '(' . e($r['vehicle_model']) . ')' : '' ?></span>
          </div>

          <div style="font-size:0.88rem; color:var(--muted); display:flex; align-items:center; gap:8px;">
            <span>Driver: <strong><?= e($r['driver_name']) ?></strong> (<?= e($r['driver_type']) ?>)</span>
            <?php if ($isOwnRide): ?>
              <span class="badge badge-info">Your Ride</span>
            <?php endif; ?>
          </div>

          <div class="ride-footer">
            <div class="ride-price">
              <?= format_currency($r['price_per_seat']) ?>
              <small>/passenger</small>
            </div>
            <div>
              <a href="<?= BASE_URL ?>/ride-details.php?id=<?= $r['id'] ?>" class="btn sm <?= $isOwnRide ? 'outline' : '' ?>">
                <?= $isOwnRide ? 'Manage Ride' : 'View &amp; Book' ?>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
