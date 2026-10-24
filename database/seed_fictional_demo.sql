-- CampusRide - Optional Seed Script with Fictional Campus Rides
-- For testing search, filtering, and booking features
-- DO NOT RUN IN PRODUCTION

USE `campus_ride_sharing`;

-- Ensure at least one demo driver exists if none present
INSERT IGNORE INTO `users` (`id`, `name`, `college_id`, `email`, `phone`, `department`, `year_of_study`, `user_type`, `password`, `is_admin`, `is_active`)
VALUES 
(101, 'Prof. Sharma', 'FAC-CSE-01', 'sharma@college.edu', '9876543210', 'Computer Science', NULL, 'Faculty', '$2y$10$w099k1V8Q2y/Zg61BwBvEedzYQ4B2R076R3jV.F4d98Xy.pkmkX7G', 0, 1),
(102, 'Rohan Verma', '23CS1012', 'rohan@college.edu', '9123456780', 'Information Technology', '3rd Year', 'Student', '$2y$10$w099k1V8Q2y/Zg61BwBvEedzYQ4B2R076R3jV.F4d98Xy.pkmkX7G', 0, 1);

-- Sample upcoming rides from campus to popular hubs and vice versa
INSERT INTO `rides` (`driver_id`, `origin`, `destination`, `departure_date`, `departure_time`, `total_seats`, `available_seats`, `price_per_seat`, `vehicle_type`, `vehicle_model`, `vehicle_number`, `pickup_instructions`, `additional_notes`, `status`)
VALUES
(101, 'Main Campus Gate 1', 'Metro Central Station', CURDATE() + INTERVAL 1 DAY, '08:30:00', 3, 3, 40.00, 'Car', 'Honda City (Silver)', 'TS08AB1234', 'Near ATM kiosk at Gate 1', 'AC car, please arrive 5 mins early', 'active'),
(102, 'Hostel Block B', 'City Railway Station', CURDATE() + INTERVAL 1 DAY, '17:15:00', 1, 1, 20.00, 'Bike', 'Royal Enfield Classic', 'TS07CD5678', 'Hostel B Bike parking', 'Helmet provided for pillion rider', 'active'),
(101, 'Tech Zone / Sector 5', 'Campus Main Campus', CURDATE() + INTERVAL 2 DAY, '09:00:00', 4, 4, 50.00, 'Car', 'Hyundai Creta (White)', 'TS08EF9012', 'Sector 5 Bus Stop', 'Daily morning commute', 'active');
