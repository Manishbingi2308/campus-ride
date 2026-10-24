-- CampusRide - Campus Ride Sharing System
-- Complete Database Schema (PHP 8 & MySQL / MariaDB compatible)
-- Supports Local XAMPP and InfinityFree Shared Hosting

CREATE DATABASE IF NOT EXISTS `campus_ride_sharing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campus_ride_sharing`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `college_id` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(15) NOT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `year_of_study` VARCHAR(20) DEFAULT NULL,
  `user_type` ENUM('Student', 'Faculty') NOT NULL DEFAULT 'Student',
  `password` VARCHAR(255) NOT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_email` (`email`),
  INDEX `idx_user_college_id` (`college_id`),
  INDEX `idx_user_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Rides Table
CREATE TABLE IF NOT EXISTS `rides` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `driver_id` INT NOT NULL,
  `origin` VARCHAR(150) NOT NULL,
  `destination` VARCHAR(150) NOT NULL,
  `departure_date` DATE NOT NULL,
  `departure_time` TIME NOT NULL,
  `total_seats` INT NOT NULL,
  `available_seats` INT NOT NULL,
  `price_per_seat` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `vehicle_type` ENUM('Bike', 'Scooter', 'Car', 'Auto', 'Cab Share', 'Other') NOT NULL DEFAULT 'Car',
  `vehicle_model` VARCHAR(100) DEFAULT NULL,
  `vehicle_number` VARCHAR(30) DEFAULT NULL,
  `pickup_instructions` TEXT DEFAULT NULL,
  `additional_notes` TEXT DEFAULT NULL,
  `status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_ride_route` (`origin`, `destination`),
  INDEX `idx_ride_datetime` (`departure_date`, `departure_time`),
  INDEX `idx_ride_driver` (`driver_id`),
  INDEX `idx_ride_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bookings Table
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ride_id` INT NOT NULL,
  `passenger_id` INT NOT NULL,
  `seats_booked` INT NOT NULL DEFAULT 1,
  `total_price` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `pickup_location` VARCHAR(200) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`ride_id`) REFERENCES `rides`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`passenger_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_booking_ride` (`ride_id`),
  INDEX `idx_booking_passenger` (`passenger_id`),
  INDEX `idx_booking_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('booking_request', 'booking_confirmed', 'booking_rejected', 'booking_cancelled', 'ride_cancelled', 'system') NOT NULL DEFAULT 'system',
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`),
  INDEX `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Rate Limiting / Security Log Table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
