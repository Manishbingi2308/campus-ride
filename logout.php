<?php
/**
 * CampusRide - User Logout
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been logged out successfully.');
header('Location: ' . BASE_URL . '/index.php');
exit;
