<?php
/**
 * CampusRide - Utility Functions & Helpers
 * XSS escaping, date/currency formatting, notifications, status badges
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date(?string $date): string {
    if (!$date) return 'N/A';
    $t = strtotime($date);
    return $t ? date('D, d M Y', $t) : $date;
}

function format_time(?string $time): string {
    if (!$time) return 'N/A';
    $t = strtotime($time);
    return $t ? date('h:i A', $t) : $time;
}

function format_datetime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    $t = strtotime($datetime);
    return $t ? date('d M Y, h:i A', $t) : $datetime;
}

function format_currency(float|int|string $amount): string {
    $num = (float)$amount;
    return '₹' . number_format($num, 2);
}

function create_notification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'system',
    ?string $link = null
): bool {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link, is_read) 
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        return $stmt->execute([$userId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

function get_unread_notifications_count(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function get_user_notifications(PDO $pdo, int $userId, int $limit = 30): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM notifications 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function mark_notification_as_read(PDO $pdo, int $notifId, int $userId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notifId, $userId]);
    } catch (Exception $e) {
        return false;
    }
}

function mark_all_notifications_as_read(PDO $pdo, int $userId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        return false;
    }
}

function render_status_badge(string $status): string {
    $status = strtolower(trim($status));
    $map = [
        'active'    => ['badge badge-success', 'Active'],
        'confirmed' => ['badge badge-success', 'Confirmed'],
        'pending'   => ['badge badge-warning', 'Pending'],
        'rejected'  => ['badge badge-danger', 'Rejected'],
        'cancelled' => ['badge badge-muted', 'Cancelled'],
        'completed' => ['badge badge-info', 'Completed'],
    ];

    $info = $map[$status] ?? ['badge badge-muted', ucfirst($status)];
    return sprintf('<span class="%s">%s</span>', e($info[0]), e($info[1]));
}

function render_vehicle_icon(string $vehicleType): string {
    $vt = strtolower($vehicleType);
    return match (true) {
        str_contains($vt, 'bike') || str_contains($vt, 'scooter') => '🏍️',
        str_contains($vt, 'auto') => '🛺',
        str_contains($vt, 'cab') || str_contains($vt, 'share') => '🚕',
        default => '🚗',
    };
}
