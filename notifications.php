<?php
/**
 * CampusRide - Notifications Center
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

// Process actions: mark as read or mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid security token.');
        header('Location: ' . BASE_URL . '/notifications.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        mark_all_notifications_as_read($pdo, $userId);
        set_flash('info', 'All notifications marked as read.');
    } elseif ($action === 'mark_read') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        mark_notification_as_read($pdo, $notifId, $userId);
    }

    header('Location: ' . BASE_URL . '/notifications.php');
    exit;
}

$notifications = get_user_notifications($pdo, $userId, 50);

$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>Campus Notifications &amp; Alerts</h1>
      <p>Stay updated on seat requests, trip confirmations, and schedule updates.</p>
    </div>
    <?php if (!empty($notifications)): ?>
      <form method="POST" action="<?= BASE_URL ?>/notifications.php" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn sm outline">Mark All as Read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <div class="icon">🔔</div>
      <h3>Your inbox is all caught up!</h3>
      <p>No notifications at the moment. As soon as you book a ride or someone requests seats on your ride, alerts will appear here.</p>
      <a href="<?= BASE_URL ?>/find-ride.php" class="btn sm">Find a Ride</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <?php foreach ($notifications as $n): ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
          <div class="notif-icon">
            <?= match($n['type']) {
              'booking_request' => '📩',
              'booking_confirmed' => '✅',
              'booking_rejected' => '❌',
              'booking_cancelled' => '⚠️',
              'ride_cancelled' => '🛑',
              default => '🔔'
            } ?>
          </div>
          <div class="notif-content">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <div class="notif-msg"><?= e($n['message']) ?></div>
            <div class="notif-time"><?= format_datetime($n['created_at']) ?></div>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <?php if (!empty($n['link'])): ?>
              <a href="<?= e($n['link']) ?>" class="btn sm outline">View</a>
            <?php endif; ?>
            <?php if (!$n['is_read']): ?>
              <form method="POST" action="<?= BASE_URL ?>/notifications.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn sm outline" style="padding:6px 10px;" title="Mark read">✓</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
