<?php
/**
 * CampusRide - User Profile Management
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();
$userId = (int)$user['id'];
$pdo = get_db_connection();

$profileErrors = [];
$passwordErrors = [];

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErrors[] = 'Invalid security token.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $year = trim($_POST['year_of_study'] ?? '');

        if (empty($name) || strlen($name) < 2) {
            $profileErrors[] = 'Please enter a valid full name.';
        }
        if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
            $profileErrors[] = 'Phone number must be exactly 10 digits.';
        }
        if (empty($department)) {
            $profileErrors[] = 'Department / Branch is required.';
        }

        if (empty($profileErrors)) {
            $yearVal = ($user['user_type'] === 'Student') ? $year : null;
            $stmt = $pdo->prepare(
                "UPDATE users SET name = ?, phone = ?, department = ?, year_of_study = ? WHERE id = ?"
            );
            $stmt->execute([$name, $phone, $department, $yearVal, $userId]);

            // Refresh session name
            $_SESSION['user_name'] = $name;

            set_flash('success', 'Profile information updated successfully!');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $passwordErrors[] = 'Invalid security token.';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $passwordErrors[] = 'All password fields are required.';
        } elseif (!password_verify($currentPass, $user['password'])) {
            $passwordErrors[] = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $passwordErrors[] = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $passwordErrors[] = 'New password and confirmation do not match.';
        } elseif ($currentPass === $newPass) {
            $passwordErrors[] = 'New password must be different from current password.';
        }

        if (empty($passwordErrors)) {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $up->execute([$newHash, $userId]);

            create_notification(
                $pdo,
                $userId,
                'Password Changed 🔒',
                'Your account password was updated successfully. If you did not make this change, please report to administration.',
                'system',
                BASE_URL . '/profile.php'
            );

            set_flash('success', 'Password updated successfully!');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    }
}

$pageTitle = 'Profile Settings';
require_once __DIR__ . '/includes/header.php';
?>

<main class="container">
  <div class="dash-head">
    <div>
      <h1>Campus Profile &amp; Settings</h1>
      <p>Review your verified organization details and security credentials.</p>
    </div>
  </div>

  <div class="row" style="align-items:start; gap:24px;">
    <!-- Profile Info Form -->
    <div style="flex:1;">
      <div class="card">
        <h2 style="font-size:1.3rem; margin-bottom:16px; color:var(--brand-dark);">
          👤 Personal &amp; Department Information
        </h2>

        <?php if (!empty($profileErrors)): ?>
          <div class="msg error" style="display:block;">
            <ul style="padding-left:18px; margin:0;">
              <?php foreach ($profileErrors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/profile.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="field">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required value="<?= e($user['name']) ?>">
          </div>

          <div class="row">
            <div class="field">
              <label>College ID (Locked for Verification)</label>
              <input type="text" value="<?= e($user['college_id']) ?>" disabled style="background:#eef2f3; cursor:not-allowed;">
              <small class="hint">College IDs cannot be altered after registration.</small>
            </div>
            <div class="field">
              <label>Account Role</label>
              <input type="text" value="<?= e($user['user_type']) ?>" disabled style="background:#eef2f3; cursor:not-allowed;">
              <small class="hint">Role is verified at registration.</small>
            </div>
          </div>

          <div class="field">
            <label>Campus Email (Locked)</label>
            <input type="text" value="<?= e($user['email']) ?>" disabled style="background:#eef2f3; cursor:not-allowed;">
          </div>

          <div class="row">
            <div class="field">
              <label for="phone">Phone Number (10 digits)</label>
              <input type="tel" id="phone" name="phone" maxlength="10" required value="<?= e($user['phone']) ?>">
            </div>
            <div class="field">
              <label for="department">Department / Branch</label>
              <input type="text" id="department" name="department" required value="<?= e($user['department'] ?? '') ?>">
            </div>
          </div>

          <?php if ($user['user_type'] === 'Student'): ?>
            <div class="field">
              <label for="year_of_study">Year of Study</label>
              <select id="year_of_study" name="year_of_study">
                <option value="">Select Year</option>
                <option value="1st Year" <?= ($user['year_of_study'] ?? '') === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                <option value="2nd Year" <?= ($user['year_of_study'] ?? '') === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                <option value="3rd Year" <?= ($user['year_of_study'] ?? '') === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                <option value="4th Year" <?= ($user['year_of_study'] ?? '') === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                <option value="Postgraduate" <?= ($user['year_of_study'] ?? '') === 'Postgraduate' ? 'selected' : '' ?>>Postgraduate</option>
              </select>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn">Save Profile Changes</button>
        </form>
      </div>
    </div>

    <!-- Password Change Form -->
    <div style="width:400px; max-width:100%;">
      <div class="card">
        <h2 style="font-size:1.3rem; margin-bottom:16px; color:var(--brand-dark);">
          🔒 Change Password
        </h2>

        <?php if (!empty($passwordErrors)): ?>
          <div class="msg error" style="display:block;">
            <ul style="padding-left:18px; margin:0;">
              <?php foreach ($passwordErrors as $err): ?>
                <li><?= e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/profile.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="field">
            <label for="current_password">Current Password <span style="color:var(--danger)">*</span></label>
            <input type="password" id="current_password" name="current_password" required placeholder="Verify current password">
          </div>

          <div class="field">
            <label for="new_password">New Password <span style="color:var(--danger)">*</span></label>
            <input type="password" id="new_password" name="new_password" minlength="6" required placeholder="Min 6 characters">
          </div>

          <div class="field">
            <label for="confirm_password">Confirm New Password <span style="color:var(--danger)">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required placeholder="Re-type new password">
          </div>

          <button type="submit" class="btn outline" style="width:100%;">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
