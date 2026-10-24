<?php
/**
 * CampusRide - Dynamic User Dashboard
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

// 1. Total rides posted by current user
$stmtRides = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE driver_id = ?");
$stmtRides->execute([$userId]);
$totalRidesPosted = (int)$stmtRides->fetchColumn();

// 2. Pending booking requests on user's posted rides
$stmtPending = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b 
     JOIN rides r ON b.ride_id = r.id 
     WHERE r.driver_id = ? AND b.status = 'pending'"
);
$stmtPending->execute([$userId]);
$pendingRequests = (int)$stmtPending->fetchColumn();

// 3. Confirmed bookings as passenger
$stmtConfirmed = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings WHERE passenger_id = ? AND status = 'confirmed'"
);
$stmtConfirmed->execute([$userId]);
$confirmedBookings = (int)$stmtConfirmed->fetchColumn();

// 4. Total active available rides in campus right now
$stmtAvailable = $pdo->query(
    "SELECT COUNT(*) FROM rides 
     WHERE status = 'active' AND available_seats > 0 
     AND (departure_date > CURDATE() OR (departure_date = CURDATE() AND departure_time >= CURTIME()))"
);
$availableRides = (int)$stmtAvailable->fetchColumn();

// 5. User's upcoming rides (either as driver or as confirmed passenger)
$stmtUpcomingDriver = $pdo->prepare(
    "SELECT r.*, 'Driver' AS role, NULL AS booking_status, NULL AS seats_booked
     FROM rides r 
     WHERE r.driver_id = ? AND r.status = 'active' 
       AND (r.departure_date > CURDATE() OR (r.departure_date = CURDATE() AND r.departure_time >= CURTIME()))
     ORDER BY r.departure_date ASC, r.departure_time ASC 
     LIMIT 3"
);
$stmtUpcomingDriver->execute([$userId]);
$driverRides = $stmtUpcomingDriver->fetchAll();

$stmtUpcomingPassenger = $pdo->prepare(
    "SELECT r.*, 'Passenger' AS role, b.status AS booking_status, b.seats_booked
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     WHERE b.passenger_id = ? AND b.status IN ('pending', 'confirmed') AND r.status = 'active'
       AND (r.departure_date > CURDATE() OR (r.departure_date = CURDATE() AND r.departure_time >= CURTIME()))
     ORDER BY r.departure_date ASC, r.departure_time ASC
     LIMIT 3"
);
$stmtUpcomingPassenger->execute([$userId]);
$passengerRides = $stmtUpcomingPassenger->fetchAll();

$upcomingRides = array_merge($driverRides, $passengerRides);
usort($upcomingRides, function($a, $b) {
    $t1 = strtotime($a['departure_date'] . ' ' . $a['departure_time']);
    $t2 = strtotime($b['departure_date'] . ' ' . $b['departure_time']);
    return $t1 <=> $t2;
});
$upcomingRides = array_slice($upcomingRides, 0, 4);

// 6. Recent notifications
$recentNotifications = get_user_notifications($pdo, $userId, 4);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>Welcome back, <span><?= e($user['name']) ?></span>!</h1>
      <p>
        <?= e($user['user_type']) ?> &bull; 
        <?= e($user['department'] ?? 'Department') ?> &bull; 
        ID: <code><?= e($user['college_id']) ?></code>
      </p>
    </div>
    <div style="display:flex; gap:10px;">
      <a href="<?= BASE_URL ?>/post-ride.php" class="btn sm">🚗 Offer a Ride</a>
      <a href="<?= BASE_URL ?>/find-ride.php" class="btn outline sm">🔍 Find a Ride</a>
    </div>
  </div>

  <!-- Real Statistics from Database -->
  <div class="grid stats">
    <div class="card stat">
      <div class="num"><?= $totalRidesPosted ?></div>
      <div class="lbl">Rides Posted by You</div>
    </div>
    <div class="card stat gold">
      <div class="num"><?= $pendingRequests ?></div>
      <div class="lbl">
        Pending Requests 
        <?php if ($pendingRequests > 0): ?>
          <a href="<?= BASE_URL ?>/my-rides.php" style="color:var(--brand); font-weight:700; margin-left:4px;">(Review)</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card stat green">
      <div class="num"><?= $confirmedBookings ?></div>
      <div class="lbl">Confirmed Bookings</div>
    </div>
    <div class="card stat blue">
      <div class="num"><?= $availableRides ?></div>
      <div class="lbl">Campus Rides Available</div>
    </div>
  </div>

  <!-- Quick Functional Actions -->
  <section style="padding: 24px 0;">
    <h2 class="title">Quick Actions</h2>
    <p class="sub">Access all core ride-sharing functional modules in one click.</p>
    <div class="grid">
      <div class="card action-card">
        <div>
          <div class="icon">🔍</div>
          <h3>Find a Ride</h3>
          <p>Search active campus rides by route, date, max fare, and available seats.</p>
        </div>
        <a href="<?= BASE_URL ?>/find-ride.php" class="btn sm">Search Rides</a>
      </div>
      <div class="card action-card">
        <div>
          <div class="icon">🚗</div>
          <h3>Post a Ride</h3>
          <p>Heading to campus or home? Share your empty seats and split petrol costs.</p>
        </div>
        <a href="<?= BASE_URL ?>/post-ride.php" class="btn sm">Post New Ride</a>
      </div>
      <div class="card action-card">
        <div>
          <div class="icon">📋</div>
          <h3>My Rides</h3>
          <p>Manage your offered rides, view incoming seat requests, accept or reject.</p>
        </div>
        <a href="<?= BASE_URL ?>/my-rides.php" class="btn sm">Manage Rides</a>
      </div>
      <div class="card action-card">
        <div>
          <div class="icon">🎟️</div>
          <h3>My Bookings</h3>
          <p>View your ride requests, confirmation status, and cancel eligible trips.</p>
        </div>
        <a href="<?= BASE_URL ?>/my-bookings.php" class="btn sm">View Bookings</a>
      </div>
    </div>
  </section>

  <!-- Upcoming Trips Section -->
  <section style="padding: 24px 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div>
        <h2 class="title">Your Upcoming Trips</h2>
        <p class="sub" style="margin-bottom:0;">Rides you are driving or joined as passenger.</p>
      </div>
      <?php if (!empty($upcomingRides)): ?>
        <a href="<?= BASE_URL ?>/my-rides.php" style="color:var(--brand); font-weight:700;">View All &rarr;</a>
      <?php endif; ?>
    </div>

    <?php if (empty($upcomingRides)): ?>
      <div class="empty-state">
        <div class="icon">🗓️</div>
        <h3>No upcoming trips scheduled</h3>
        <p>You have not posted any upcoming rides and do not have active passenger bookings.</p>
        <a href="<?= BASE_URL ?>/find-ride.php" class="btn sm">Find a Ride Now</a>
      </div>
    <?php else: ?>
      <div class="grid" style="margin-top:16px;">
        <?php foreach ($upcomingRides as $trip): ?>
          <div class="card ride-card">
            <div class="ride-header">
              <div class="ride-route">
                <div class="ride-place">
                  <span><?= render_vehicle_icon($trip['vehicle_type']) ?></span>
                  <?= e($trip['origin']) ?>
                </div>
                <div class="ride-place" style="font-size:0.95rem; color:var(--muted);">
                  <span class="ride-arrow">&darr;</span> <?= e($trip['destination']) ?>
                </div>
              </div>
              <div>
                <?php if ($trip['role'] === 'Driver'): ?>
                  <span class="badge badge-info">You are Driving</span>
                <?php else: ?>
                  <?= render_status_badge($trip['booking_status']) ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="ride-meta">
              <span>📅 <?= format_date($trip['departure_date']) ?></span>
              <span>⏰ <?= format_time($trip['departure_time']) ?></span>
              <span>💺 <?= e($trip['available_seats']) ?> seats left</span>
            </div>

            <div class="ride-footer">
              <div class="ride-price"><?= format_currency($trip['price_per_seat']) ?> <small>/seat</small></div>
              <a href="<?= BASE_URL ?>/ride-details.php?id=<?= $trip['id'] ?>" class="btn sm outline">Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Recent Notifications Preview -->
  <section style="padding: 24px 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div>
        <h2 class="title">Recent Alerts</h2>
        <p class="sub" style="margin-bottom:0;">Latest notifications regarding your ride requests and bookings.</p>
      </div>
      <a href="<?= BASE_URL ?>/notifications.php" style="color:var(--brand); font-weight:700;">View All Alerts &rarr;</a>
    </div>

    <?php if (empty($recentNotifications)): ?>
      <div class="empty-state" style="padding:32px 16px;">
        <div class="icon">🔔</div>
        <h3>No alerts yet</h3>
        <p>Notifications about new booking requests and ride confirmations will appear here.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <?php foreach ($recentNotifications as $notif): ?>
          <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">
            <div class="notif-icon">
              <?= match($notif['type']) {
                'booking_request' => '📩',
                'booking_confirmed' => '✅',
                'booking_rejected' => '❌',
                'booking_cancelled' => '⚠️',
                'ride_cancelled' => '🛑',
                default => '🔔'
              } ?>
            </div>
            <div class="notif-content">
              <div class="notif-title"><?= e($notif['title']) ?></div>
              <div class="notif-msg"><?= e($notif['message']) ?></div>
              <div class="notif-time"><?= format_datetime($notif['created_at']) ?></div>
            </div>
            <?php if (!empty($notif['link'])): ?>
              <div style="align-self:center;">
                <a href="<?= e($notif['link']) ?>" class="btn sm outline">View</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Profile Quick Glance -->
  <section id="profile" style="padding-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h2 class="title" style="margin-bottom:0;">Your Campus Profile</h2>
      <a href="<?= BASE_URL ?>/profile.php" class="btn sm outline">Edit Profile / Change Password</a>
    </div>
    <div class="profile">
      <div><small>Name</small><strong><?= e($user['name']) ?></strong></div>
      <div><small>Email</small><strong><?= e($user['email']) ?></strong></div>
      <div><small>User Type</small><strong><?= e($user['user_type']) ?></strong></div>
      <div><small>College ID</small><strong><?= e($user['college_id']) ?></strong></div>
      <div><small>Department</small><strong><?= e($user['department'] ?? 'Not specified') ?></strong></div>
      <div><small>Phone</small><strong><?= e($user['phone']) ?></strong></div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
