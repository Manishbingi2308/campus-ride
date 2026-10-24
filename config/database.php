<?php
/**
 * CampusRide - Database Connection (Centralized PDO)
 * Supports XAMPP (Port 3308) and InfinityFree MySQL/MariaDB
 */

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            // Log error safely without exposing raw database password to visitors
            error_log("CampusRide Database Connection Failed: " . $e->getMessage());

            // Provide a helpful, friendly message for setup
            http_response_code(500);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Database Connection Error - CampusRide</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f8f7; color: #14232b; padding: 40px 20px; display: grid; place-items: center; min-height: 80vh; margin: 0; }
                    .card { background: #fff; max-width: 580px; padding: 32px; border-radius: 14px; border: 1px solid #dce5e8; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
                    h1 { color: #b3261e; margin-top: 0; font-size: 1.5rem; }
                    p { line-height: 1.6; color: #5b6b73; }
                    code { background: #e3f2ec; color: #084f50; padding: 3px 6px; border-radius: 6px; font-weight: bold; }
                    ul { padding-left: 20px; color: #5b6b73; }
                    li { margin-bottom: 8px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>Database Connection Error</h1>
                    <p>CampusRide could not connect to the MySQL database server.</p>
                    <p><strong>Troubleshooting steps:</strong></p>
                    <ul>
                        <li>If running locally on <strong>XAMPP</strong>, ensure MySQL is running. Note: Your configured MySQL port is <code><?= htmlspecialchars(DB_PORT) ?></code>.</li>
                        <li>Ensure the database <code><?= htmlspecialchars(DB_NAME) ?></code> has been created or imported via phpMyAdmin.</li>
                        <li>If deploying to <strong>InfinityFree</strong>, check your <code>.env</code> file or <code>config/config.php</code> with the exact MySQL hostname, username, and password from the InfinityFree control panel.</li>
                    </ul>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    return $pdo;
}
