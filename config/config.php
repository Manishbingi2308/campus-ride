<?php
/**
 * CampusRide - Configuration & Session Initialization
 * Compatible with PHP 8.x, Local XAMPP (Port 3308), and InfinityFree Shared Hosting
 */

// Prevent multiple inclusion
if (defined('CAMPUSRIDE_CONFIG_LOADED')) {
    return;
}
define('CAMPUSRIDE_CONFIG_LOADED', true);

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Helper to load simple .env file if present
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Remove surrounding quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Database configuration defaults
// Local default MySQL port is 3308 as requested for user's XAMPP setup
$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '127.0.0.1'));
$dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? '3308'));
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'campus_ride_sharing'));
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? 'root'));
$dbPass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? ''));

define('DB_HOST', $dbHost);
define('DB_PORT', (int)$dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASSWORD', $dbPass);

define('APP_NAME', 'CampusRide');

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie parameters for security
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Auto-determine Base URL for clean links in root or subdirectories
if (!defined('BASE_URL')) {
    $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? ($_SERVER['APP_URL'] ?? null));
    if ($envUrl) {
        define('BASE_URL', rtrim($envUrl, '/'));
    } else {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Find path relative to project root
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
        
        $subPath = '';
        if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
            $subPath = substr($projectRoot, strlen($docRoot));
        }
        $subPath = rtrim(str_replace('\\', '/', $subPath), '/');
        
        define('BASE_URL', $protocol . $host . $subPath);
    }
}

// CSRF Token Management
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// Flash Message System
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
