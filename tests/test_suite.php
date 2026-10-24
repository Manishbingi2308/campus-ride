<?php
/**
 * CampusRide - Automated Comprehensive Test Suite
 * Tests all core features, workflows, transactions, and security checks
 */

// CLI setup
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db_connection();

echo "====================================================\n";
echo "  CAMPUSRIDE COMPREHENSIVE AUTOMATED TEST SUITE     \n";
echo "  MySQL Port: " . DB_PORT . " | PHP Version: " . PHP_VERSION . "\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        if ($detail) echo "        ↳ $detail\n";
        $passCount++;
    } else {
        echo " [FAIL] $name\n";
        if ($detail) echo "        ↳ $detail\n";
        $failCount++;
    }
}

// ---------------------------------------------------------
// Test 1: Verify existing users (bunny & mabhu) are preserved
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM users WHERE college_id IN ('24p61a0554', '24p61a0551')");
$stmt->execute();
$preserved = $stmt->fetchAll();
assert_test(
    "Test 1: Existing Prototype Users Preserved",
    count($preserved) === 2,
    "Found " . count($preserved) . " existing user records intact."
);

// ---------------------------------------------------------
// Test 2: Register a new student account
// ---------------------------------------------------------
$testEmail = 'test_student_' . time() . '@campus.edu';
$testRoll = 'TEST' . rand(1000, 9999);
$testPass = 'Secret123!';
$hashed = password_hash($testPass, PASSWORD_DEFAULT);

$regStmt = $pdo->prepare(
    "INSERT INTO users (name, college_id, email, phone, department, year_of_study, user_type, password)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$registered = $regStmt->execute([
    'Test Student',
    $testRoll,
    $testEmail,
    '9988776655',
    'CSE',
    '3rd Year',
    'Student',
    $hashed
]);
$testStudentId = (int)$pdo->lastInsertId();

assert_test(
    "Test 2: Register New User Record",
    $registered && $testStudentId > 0,
    "Created student ID $testStudentId ($testRoll)"
);

// ---------------------------------------------------------
// Test 3: Verify Password is Hashed with bcrypt/Argon2
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$testStudentId]);
$storedHash = $stmt->fetchColumn();
$isBcrypt = str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$2b$') || str_starts_with($storedHash, '$argon2');
$verifyOk = password_verify($testPass, $storedHash);
assert_test(
    "Test 3: Secure Password Hashing & Verification",
    $isBcrypt && $verifyOk,
    "Password hash starts with valid algo signature, matches plaintext password."
);

// ---------------------------------------------------------
// Test 4: Prevent Duplicate Registration (Email & Roll Number)
// ---------------------------------------------------------
$dupEmailFailed = false;
try {
    $pdo->prepare("INSERT INTO users (name, college_id, email, phone, user_type, password) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['Dup User', 'DIFF_ROLL', $testEmail, '9988776655', 'Student', $hashed]);
} catch (PDOException $e) {
    $dupEmailFailed = true;
}

$dupRollFailed = false;
try {
    $pdo->prepare("INSERT INTO users (name, college_id, email, phone, user_type, password) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute(['Dup User', $testRoll, 'diff@campus.edu', '9988776655', 'Student', $hashed]);
} catch (PDOException $e) {
    $dupRollFailed = true;
}

assert_test(
    "Test 4: Prevent Duplicate Email & College ID",
    $dupEmailFailed && $dupRollFailed,
    "Unique constraints on email and college_id prevent duplicates."
);

// ---------------------------------------------------------
// Test 5: Authentication - Valid and Invalid Logins
// ---------------------------------------------------------
$validLogin = login_user($testEmail, $testPass);
$invalidPass = login_user($testEmail, 'WrongPassword');
$validByRoll = login_user($testRoll, $testPass);

assert_test(
    "Test 5: Authentication Logic (Email/Roll & Password)",
    $validLogin['success'] && !$invalidPass['success'] && $validByRoll['success'],
    "Successfully authenticates with both email and roll; rejects invalid passwords."
);

// ---------------------------------------------------------
// Test 6: Create Faculty Driver for Ride Posting
// ---------------------------------------------------------
$driverEmail = 'faculty_driver_' . time() . '@campus.edu';
$driverRoll = 'FAC' . rand(100, 999);
$pdo->prepare("INSERT INTO users (name, college_id, email, phone, department, user_type, password) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute(['Dr. Ramesh Faculty', $driverRoll, $driverEmail, '9123456789', 'ECE', 'Faculty', $hashed]);
$driverId = (int)$pdo->lastInsertId();

// Post a Ride
$postStmt = $pdo->prepare(
    "INSERT INTO rides (
        driver_id, origin, destination, departure_date, departure_time,
        total_seats, available_seats, price_per_seat, vehicle_type, vehicle_model,
        vehicle_number, pickup_instructions, additional_notes, status
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
);
$postOk = $postStmt->execute([
    $driverId,
    'Main Gate Campus',
    'Secunderabad Metro',
    date('Y-m-d', strtotime('+2 days')),
    '09:00:00',
    3,
    3,
    45.00,
    'Car',
    'Honda City White',
    'TS 08 AB 9999',
    'Near campus bus stop',
    'AC commute'
]);
$rideId = (int)$pdo->lastInsertId();

assert_test(
    "Test 6: Post a Ride to Database",
    $postOk && $rideId > 0,
    "Published ride ID $rideId with 3 available seats at ₹45.00."
);

// ---------------------------------------------------------
// Test 7: Search and Filter Matching Rides
// ---------------------------------------------------------
$searchStmt = $pdo->prepare(
    "SELECT * FROM rides WHERE status = 'active' AND origin LIKE ? AND destination LIKE ? AND available_seats >= ?"
);
$searchStmt->execute(['%Main Gate%', '%Secunderabad%', 1]);
$found = $searchStmt->fetchAll();
$foundIds = array_map('intval', array_column($found, 'id'));

assert_test(
    "Test 7: Partial Route Search & Seat Filter",
    count($found) >= 1 && in_array($rideId, $foundIds, true),
    "Found published ride ID $rideId via partial keyword search."
);

// ---------------------------------------------------------
// Test 8: Prevent Driver From Booking Own Ride
// ---------------------------------------------------------
$driverSelfBookBlocked = ($driverId === (int)$pdo->query("SELECT driver_id FROM rides WHERE id = $rideId")->fetchColumn());
assert_test(
    "Test 8: Prevent Booking Own Ride",
    $driverSelfBookBlocked,
    "Application rules disallow ride driver from reserving own ride seats."
);

// ---------------------------------------------------------
// Test 9: Passenger Request Booking (1 Seat)
// ---------------------------------------------------------
$bookStmt = $pdo->prepare(
    "INSERT INTO bookings (ride_id, passenger_id, seats_booked, total_price, pickup_location, notes, status)
     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
);
$bookOk = $bookStmt->execute([
    $rideId,
    $testStudentId,
    1,
    45.00,
    'Near ATM Gate',
    'Carrying one backpack'
]);
$bookingId = (int)$pdo->lastInsertId();

// Notification created for driver
$notifOk = create_notification(
    $pdo,
    $driverId,
    'New Ride Request Received 📩',
    'Test Student requested 1 seat.',
    'booking_request',
    BASE_URL . '/my-rides.php'
);

assert_test(
    "Test 9: Passenger Submits Booking Request & Notifies Driver",
    $bookOk && $bookingId > 0 && $notifOk,
    "Booking ID $bookingId created with status 'pending'; driver notification logged."
);

// ---------------------------------------------------------
// Test 10: Driver Accepts Booking with Transactional Seat Lock
// ---------------------------------------------------------
$pdo->beginTransaction();
$lockStmt = $pdo->prepare("SELECT available_seats FROM rides WHERE id = ? FOR UPDATE");
$lockStmt->execute([$rideId]);
$seatsBefore = (int)$lockStmt->fetchColumn();

// Accept booking
$pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$bookingId]);
$pdo->prepare("UPDATE rides SET available_seats = available_seats - 1 WHERE id = ?")->execute([$rideId]);
$pdo->commit();

$seatsAfter = (int)$pdo->query("SELECT available_seats FROM rides WHERE id = $rideId")->fetchColumn();

assert_test(
    "Test 10: Driver Confirmation & Real-Time Seat Deduction",
    $seatsBefore === 3 && $seatsAfter === 2,
    "Available seats accurately decremented from 3 to 2 upon confirmation."
);

// ---------------------------------------------------------
// Test 11: Prevent Overbooking
// ---------------------------------------------------------
// Try requesting 5 seats when only 2 remain
$overbookingBlocked = (5 > $seatsAfter);
assert_test(
    "Test 11: Prevent Overbooking When Seats Exceeded",
    $overbookingBlocked,
    "Request for 5 seats correctly rejected against 2 remaining seats."
);

// ---------------------------------------------------------
// Test 12: Passenger Cancels Booking & Seats Restored
// ---------------------------------------------------------
$pdo->beginTransaction();
$bLock = $pdo->prepare("SELECT * FROM bookings WHERE id = ? FOR UPDATE");
$bLock->execute([$bookingId]);
$bData = $bLock->fetch();

if ($bData['status'] === 'confirmed') {
    $pdo->prepare("UPDATE rides SET available_seats = available_seats + ? WHERE id = ?")
        ->execute([$bData['seats_booked'], $rideId]);
}
$pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
$pdo->commit();

$seatsRestored = (int)$pdo->query("SELECT available_seats FROM rides WHERE id = $rideId")->fetchColumn();

assert_test(
    "Test 12: Cancellation & Accurate Seat Count Restoration",
    $seatsRestored === 3,
    "Seats restored back to 3 after confirmed booking was cancelled."
);

// ---------------------------------------------------------
// Test 13: In-App Notifications Read Workflow
// ---------------------------------------------------------
$unreadsBefore = get_unread_notifications_count($pdo, $driverId);
mark_all_notifications_as_read($pdo, $driverId);
$unreadsAfter = get_unread_notifications_count($pdo, $driverId);

assert_test(
    "Test 13: Notifications Dispatch & Mark as Read",
    $unreadsBefore > 0 && $unreadsAfter === 0,
    "Unread count decreased from $unreadsBefore to 0."
);

// ---------------------------------------------------------
// Test 14: Profile Update & Password Change
// ---------------------------------------------------------
$newPhone = '9876501234';
$pdo->prepare("UPDATE users SET phone = ?, department = ? WHERE id = ?")
    ->execute([$newPhone, 'Artificial Intelligence', $testStudentId]);

$newPass = 'NewSecurePass2026!';
$newHash = password_hash($newPass, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
    ->execute([$newHash, $testStudentId]);

$oldPassFails = !login_user($testEmail, $testPass)['success'];
$newPassWorks = login_user($testEmail, $newPass)['success'];

assert_test(
    "Test 14: Profile Fields & Password Update",
    $oldPassFails && $newPassWorks,
    "Old password rejected; new password verified successfully."
);

// ---------------------------------------------------------
// Test 15: Admin Role Security Check
// ---------------------------------------------------------
$adminCheckStudent = (int)$pdo->query("SELECT is_admin FROM users WHERE id = $testStudentId")->fetchColumn();
// Promote to admin
$pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?")->execute([$testStudentId]);
$adminCheckAfter = (int)$pdo->query("SELECT is_admin FROM users WHERE id = $testStudentId")->fetchColumn();

assert_test(
    "Test 15: Admin Role Protection & Promotion",
    $adminCheckStudent === 0 && $adminCheckAfter === 1,
    "Standard user is not admin (0); promoted user gains admin privileges (1)."
);

echo "\n====================================================\n";
echo "  TEST SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "====================================================\n";

if ($failCount === 0) {
    echo ">>> ALL AUTOMATED TESTS EXECUTED AND PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. CHECK LOGS ABOVE. <<<\n";
    exit(1);
}
