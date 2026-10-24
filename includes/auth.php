<?php
/**
 * CampusRide - Authentication & Authorization Layer
 * Secure session handling, brute-force mitigation, and role checks
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function is_rate_limited(PDO $pdo, string $ip): bool {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts 
             WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        $count = (int)$stmt->fetchColumn();
        return $count >= 5; // Lock out after 5 consecutive failures for 15 minutes
    } catch (Exception $e) {
        return false;
    }
}

function record_failed_attempt(PDO $pdo, string $ip): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        $stmt->execute([$ip]);
    } catch (Exception $e) {
        // Silently fail if table issue
    }
}

function clear_failed_attempts(PDO $pdo, string $ip): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    } catch (Exception $e) {
        // Silently continue
    }
}

function login_user(string $identifier, string $password): array {
    $pdo = get_db_connection();
    $ip = get_client_ip();

    if (is_rate_limited($pdo, $ip)) {
        return [
            'success' => false,
            'message' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
        ];
    }

    $identifier = trim($identifier);
    if (empty($identifier) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Please enter your email or College ID and password.'
        ];
    }

    // Look up by email OR college_id
    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE (LOWER(email) = LOWER(?) OR LOWER(college_id) = LOWER(?)) LIMIT 1"
    );
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        record_failed_attempt($pdo, $ip);
        return [
            'success' => false,
            'message' => 'Invalid email/college ID or password.'
        ];
    }

    if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
        return [
            'success' => false,
            'message' => 'Your account has been deactivated. Please contact campus administration.'
        ];
    }

    // Optional rehash if password algorithm updated
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upStmt->execute([$newHash, $user['id']]);
    }

    // Clear failed attempts upon successful login
    clear_failed_attempts($pdo, $ip);

    // Prevent session fixation
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);

    return [
        'success' => true,
        'user' => $user
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && !empty($_SESSION['is_admin']);
}

function current_user(): ?array {
    static $currentUser = null;
    if ($currentUser !== null) {
        return $currentUser;
    }
    if (!is_logged_in()) {
        return null;
    }
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $currentUser = $user;
            // Sync session admin status
            $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
            return $currentUser;
        }
    } catch (Exception $e) {
        error_log("current_user error: " . $e->getMessage());
    }
    return null;
}

function require_login(?string $redirect = null): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        $target = $redirect ?: ($_SERVER['REQUEST_URI'] ?? '');
        $param = $target ? '?redirect=' . urlencode($target) : '';
        header('Location: ' . BASE_URL . '/login.php' . $param);
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Access denied. Administrator privileges required.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}
