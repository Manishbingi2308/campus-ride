<?php
/**
 * CampusRide - User Registration
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$errors = [];
$formData = [
    'name' => '',
    'college_id' => '',
    'email' => '',
    'phone' => '',
    'department' => '',
    'year_of_study' => '',
    'user_type' => 'Student'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired security token. Please try again.';
    } else {
        $formData['name'] = trim($_POST['name'] ?? '');
        $formData['college_id'] = trim($_POST['college_id'] ?? '');
        $formData['email'] = trim(strtolower($_POST['email'] ?? ''));
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['department'] = trim($_POST['department'] ?? '');
        $formData['year_of_study'] = trim($_POST['year_of_study'] ?? '');
        $formData['user_type'] = trim($_POST['user_type'] ?? 'Student');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $terms = !empty($_POST['terms']);

        // Field validations
        if (empty($formData['name'])) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($formData['name']) < 2 || strlen($formData['name']) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }

        if (empty($formData['college_id'])) {
            $errors[] = 'College ID / Roll Number is required.';
        } elseif (strlen($formData['college_id']) > 50) {
            $errors[] = 'College ID must not exceed 50 characters.';
        }

        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($formData['phone']) || !preg_match('/^[0-9]{10}$/', $formData['phone'])) {
            $errors[] = 'Phone number must be exactly 10 digits.';
        }

        if (!in_array($formData['user_type'], ['Student', 'Faculty'], true)) {
            $errors[] = 'Please select a valid user type (Student or Faculty).';
        }

        if (empty($formData['department'])) {
            $errors[] = 'Department / Branch is required.';
        }

        if ($formData['user_type'] === 'Student' && empty($formData['year_of_study'])) {
            $errors[] = 'Please specify your year of study.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!$terms) {
            $errors[] = 'You must accept the CampusRide community guidelines and terms.';
        }

        // Database uniqueness check
        if (empty($errors)) {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, email, college_id FROM users WHERE LOWER(email) = ? OR LOWER(college_id) = ? LIMIT 1");
            $stmt->execute([$formData['email'], strtolower($formData['college_id'])]);
            $existing = $stmt->fetch();

            if ($existing) {
                if (strtolower($existing['email']) === $formData['email']) {
                    $errors[] = 'An account with this email address already exists. Please log in.';
                } else {
                    $errors[] = 'An account with this College ID already exists. Please log in.';
                }
            } else {
                // Hash password securely
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $pdo->prepare(
                    "INSERT INTO users (name, college_id, email, phone, department, year_of_study, user_type, password) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $yearVal = ($formData['user_type'] === 'Student') ? $formData['year_of_study'] : null;

                $inserted = $insertStmt->execute([
                    $formData['name'],
                    $formData['college_id'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['department'],
                    $yearVal,
                    $formData['user_type'],
                    $hashedPassword
                ]);

                if ($inserted) {
                    $newUserId = (int)$pdo->lastInsertId();
                    // Create welcome notification
                    create_notification(
                        $pdo,
                        $newUserId,
                        'Welcome to CampusRide! 🚗',
                        'Your campus account has been registered successfully. You can now post and search for shared rides.',
                        'system',
                        BASE_URL . '/find-ride.php'
                    );

                    set_flash('success', 'Registration successful! Please log in with your credentials.');
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;
                } else {
                    $errors[] = 'Unable to complete registration. Please try again later.';
                }
            }
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<main class="auth-wrap">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="sub">Join your verified campus ride-sharing community.</p>

    <?php if (!empty($errors)): ?>
      <div class="msg error" style="display:block;">
        <ul style="padding-left:18px; margin:0;">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/register.php" novalidate id="registerForm">
      <?= csrf_field() ?>

      <div class="field">
        <label for="name">Full Name <span style="color:var(--danger)">*</span></label>
        <input id="name" name="name" type="text" required placeholder="e.g. Ananya Rao" value="<?= e($formData['name']) ?>">
      </div>

      <div class="row">
        <div class="field">
          <label for="college_id">College ID / Roll No <span style="color:var(--danger)">*</span></label>
          <input id="college_id" name="college_id" type="text" required placeholder="e.g. 24P61A0554" value="<?= e($formData['college_id']) ?>">
        </div>
        <div class="field">
          <label for="user_type">User Type <span style="color:var(--danger)">*</span></label>
          <select id="user_type" name="user_type" required onchange="toggleYearField(this.value)">
            <option value="Student" <?= $formData['user_type'] === 'Student' ? 'selected' : '' ?>>Student</option>
            <option value="Faculty" <?= $formData['user_type'] === 'Faculty' ? 'selected' : '' ?>>Faculty</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label for="department">Department / Branch <span style="color:var(--danger)">*</span></label>
          <input id="department" name="department" type="text" required placeholder="e.g. CSE / ECE / Mech" value="<?= e($formData['department']) ?>">
        </div>
        <div class="field" id="yearGroup">
          <label for="year_of_study">Year of Study</label>
          <select id="year_of_study" name="year_of_study">
            <option value="">Select Year</option>
            <option value="1st Year" <?= $formData['year_of_study'] === '1st Year' ? 'selected' : '' ?>>1st Year</option>
            <option value="2nd Year" <?= $formData['year_of_study'] === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
            <option value="3rd Year" <?= $formData['year_of_study'] === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
            <option value="4th Year" <?= $formData['year_of_study'] === '4th Year' ? 'selected' : '' ?>>4th Year</option>
            <option value="Postgraduate" <?= $formData['year_of_study'] === 'Postgraduate' ? 'selected' : '' ?>>Postgraduate</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="email">Campus Email Address <span style="color:var(--danger)">*</span></label>
        <input id="email" name="email" type="email" required placeholder="e.g. you@college.edu or rollno@vbithyd.ac.in" value="<?= e($formData['email']) ?>">
      </div>

      <div class="field">
        <label for="phone">Phone Number (10 digits) <span style="color:var(--danger)">*</span></label>
        <input id="phone" name="phone" type="tel" maxlength="10" required placeholder="e.g. 9876543210" value="<?= e($formData['phone']) ?>">
      </div>

      <div class="row">
        <div class="field">
          <label for="password">Password <span style="color:var(--danger)">*</span></label>
          <input id="password" name="password" type="password" required minlength="6" placeholder="Min. 6 characters">
        </div>
        <div class="field">
          <label for="confirm_password">Confirm Password <span style="color:var(--danger)">*</span></label>
          <input id="confirm_password" name="confirm_password" type="password" required minlength="6" placeholder="Re-type password">
        </div>
      </div>

      <div class="checkbox-field">
        <input type="checkbox" id="terms" name="terms" value="1" required>
        <label for="terms">
          I agree to the CampusRide community safety guidelines, verify that my College ID is accurate, and accept terms of service.
        </label>
      </div>

      <button class="btn" type="submit" id="btnSubmit">Create Account</button>
    </form>

    <p class="alt">Already registered? <a href="<?= BASE_URL ?>/login.php">Login here</a></p>
  </div>
</main>

<script>
function toggleYearField(type) {
  const yg = document.getElementById('yearGroup');
  yg.style.display = (type === 'Faculty') ? 'none' : 'block';
}
toggleYearField(document.getElementById('user_type').value);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
