# CampusRide — Campus Ride Sharing System
> A comprehensive, full-stack campus ride-sharing and carpooling web platform designed for college students and faculty members. Built with modern, secure PHP 8 and MySQL/MariaDB.

---

## 📌 Project Overview
**CampusRide** simplifies daily commutes between campus, transit hubs, and residential zones. It enables verified students and faculty to offer spare seats in their personal vehicles (cars, motorcycles, scooters, or auto/cab shares) or request rides from peers heading along identical routes.

### Key Objectives
- **Split Travel Costs**: Fairly distribute fuel and transport charges among fellow college members.
- **Decongest Campus**: Minimize traffic gridlock around college gates and parking areas.
- **Eco-Friendly Commuting**: Cut carbon footprint through organized carpooling.
- **Safe & Verified Community**: Only registered students and faculty carrying verified College Roll Numbers and campus emails can offer and book rides.

---

## 🛠️ Technology Stack
- **Backend**: Native PHP 8.1+ / 8.2 compatible (Object-Oriented & Procedural helpers, PDO abstraction).
- **Database**: MySQL 5.7+ / MariaDB 10.4+ (InnoDB engine, transactions with row-level locking `FOR UPDATE`, UTF-8mb4).
- **Frontend**: Clean semantic HTML5, Modern CSS3 with custom variables, Vanilla JavaScript (zero heavy bloated frameworks, zero Node.js server runtime required).
- **Security**: 
  - Centralized PDO prepared statements with parameter binding (100% protection against SQL Injection).
  - Cryptographic session IDs with regeneration (`session_regenerate_id`) to block session fixation.
  - Native Bcrypt / Argon2 password hashing (`password_hash`, `password_verify`).
  - Strict Cross-Site Request Forgery (CSRF) tokens on all state-changing forms.
  - Server-side input sanitization (`htmlspecialchars`) preventing XSS attacks.
  - Brute-force rate limiting on authentication attempts.
  - Role-based server-side authorization checks for user and administrative areas.
- **Environments**:
  - **Local Development**: Windows, XAMPP (Apache, PHP 8.2, MySQL on **Port 3308**).
  - **Online Deployment**: InfinityFree Shared Hosting (Apache, PHP, MariaDB, standard `htdocs` web root).

---

## 📁 Project Directory Structure
```text
campus-ride/
├── assets/
│   ├── css/
│   │   └── style.css            # Responsive custom styles, branding, badges, layout
│   └── js/
│       └── main.js              # Interactivity, flash alerts auto-dismiss, modal confirms
├── config/
│   ├── config.php               # Environment loader (.env), session security, base URL
│   └── database.php             # Centralized PDO database singleton
├── database/
│   ├── database.sql             # Complete schema for fresh installation
│   ├── migration_upgrade.sql    # Safe non-destructive migration script
│   └── seed_fictional_demo.sql  # Optional sample demo rides and drivers
├── includes/
│   ├── auth.php                 # Login, logout, rate limiter, session verification, admin checks
│   ├── functions.php            # Escaping, formatting, notifications, status badges
│   ├── header.php               # Global navigation, dynamic unread badge, active states
│   └── footer.php               # Global responsive footer
├── admin/
│   ├── index.php                # Admin dashboard overview & platform metrics
│   ├── users.php                # User verification, search, and account suspension
│   ├── rides.php                # Campus-wide ride moderation & cancellation
│   └── bookings.php             # Real-time booking audit log
├── tests/
│   └── test_suite.php           # Comprehensive 15-test automated CLI test runner
├── .env.example                 # Configuration template for XAMPP and InfinityFree
├── .gitignore                   # Ignores sensitive environment files & logs
├── .htaccess                    # Directory protection, UTF-8 charset, file security
├── index.php                    # Public landing page (Hero, Benefits, How it works, Safety)
├── login.php                    # User authentication (Email / College ID & Password)
├── logout.php                   # Secure session destruction
├── register.php                 # Registration for Students & Faculty with validations
├── dashboard.php                # Dynamic dashboard with real-time database stats
├── post-ride.php                # Form to offer/publish a new ride
├── find-ride.php                # Search & multi-parameter filter engine for rides
├── ride-details.php             # Detailed ride page with live seat booking submission
├── my-rides.php                 # Driver ride management (Accept/Decline requests, cancel ride)
├── my-bookings.php              # Passenger bookings (Status tracking, cancellation & seat refund)
├── profile.php                  # User profile view/edit and password change
├── notifications.php            # In-app notification center (mark read, direct links)
└── README.md                    # Setup and deployment documentation
```

---

## 🗄️ Database Architecture & Relationships

Database Name: `campus_ride_sharing`

### Schema Overview
1. **`users`**:
   - `id` (PK, INT AUTO_INCREMENT)
   - `name` (VARCHAR 100)
   - `college_id` (VARCHAR 50 UNIQUE) — College Roll Number
   - `email` (VARCHAR 100 UNIQUE) — Campus email
   - `phone` (VARCHAR 15)
   - `department` (VARCHAR 100)
   - `year_of_study` (VARCHAR 20, NULL for faculty)
   - `user_type` (ENUM 'Student', 'Faculty')
   - `password` (VARCHAR 255) — Bcrypt hash
   - `is_admin` (TINYINT 1, default 0)
   - `is_active` (TINYINT 1, default 1)
   - `created_at`, `updated_at` (Timestamps)

2. **`rides`**:
   - `id` (PK, INT AUTO_INCREMENT)
   - `driver_id` (FK -> `users.id` ON DELETE CASCADE)
   - `origin` (VARCHAR 150)
   - `destination` (VARCHAR 150)
   - `departure_date` (DATE)
   - `departure_time` (TIME)
   - `total_seats` (INT)
   - `available_seats` (INT)
   - `price_per_seat` (DECIMAL 8,2)
   - `vehicle_type` (ENUM 'Bike', 'Scooter', 'Car', 'Auto', 'Cab Share', 'Other')
   - `vehicle_model`, `vehicle_number` (VARCHAR)
   - `pickup_instructions`, `additional_notes` (TEXT)
   - `status` (ENUM 'active', 'completed', 'cancelled')
   - `created_at`, `updated_at` (Timestamps)

3. **`bookings`**:
   - `id` (PK, INT AUTO_INCREMENT)
   - `ride_id` (FK -> `rides.id` ON DELETE CASCADE)
   - `passenger_id` (FK -> `users.id` ON DELETE CASCADE)
   - `seats_booked` (INT)
   - `total_price` (DECIMAL 8,2) — Calculated on server
   - `pickup_location`, `notes` (TEXT)
   - `status` (ENUM 'pending', 'confirmed', 'rejected', 'cancelled', 'completed')
   - `created_at`, `updated_at` (Timestamps)

4. **`notifications`**:
   - `id` (PK, INT AUTO_INCREMENT)
   - `user_id` (FK -> `users.id` ON DELETE CASCADE)
   - `title` (VARCHAR 150)
   - `message` (TEXT)
   - `type` (ENUM 'booking_request', 'booking_confirmed', 'booking_rejected', 'booking_cancelled', 'ride_cancelled', 'system')
   - `link` (VARCHAR 255)
   - `is_read` (TINYINT 1, default 0)
   - `created_at` (Timestamp)

5. **`login_attempts`**:
   - `id` (PK, INT AUTO_INCREMENT)
   - `ip_address` (VARCHAR 45)
   - `attempted_at` (Timestamp)

---

## 💻 Local Setup Instructions (Windows / XAMPP)

### Step 1: Start XAMPP Services
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.
4. Confirm that MySQL is running on port **3308** (as configured in your XAMPP `my.ini`).

### Step 2: Database Setup in phpMyAdmin
1. Open your browser and go to: `http://localhost/phpmyadmin/` (or `http://localhost:80/phpmyadmin/`).
2. If the database `campus_ride_sharing` already exists:
   - Click on `campus_ride_sharing` in the left sidebar.
   - Go to the **Import** tab.
   - Choose `database/migration_upgrade.sql` and click **Go**.
   - *(This upgrades the schema safely without deleting any existing registered users or passwords).*
3. If setting up on a completely fresh XAMPP machine:
   - Click **New** in phpMyAdmin to create database `campus_ride_sharing`.
   - Go to the **Import** tab.
   - Choose `database/database.sql` and click **Go**.
   - *(Optional)*: To add sample upcoming rides for testing, import `database/seed_fictional_demo.sql`.

### Step 3: Place Files in XAMPP
1. Extract or copy the `campus-ride` folder into your XAMPP web root:
   ```text
   C:\xampp\htdocs\campus-ride
   ```
2. Verify configuration:
   - A default `.env` is already configured for Port `3308`:
     ```ini
     DB_HOST=127.0.0.1
     DB_PORT=3308
     DB_NAME=campus_ride_sharing
     DB_USER=root
     DB_PASSWORD=
     ```

### Step 4: Run the Application Locally
1. Open your web browser.
2. Navigate to:
   ```text
   http://localhost/campus-ride/
   ```
3. You will see the CampusRide landing page!

---

## 🔑 Safe Administrator Account Creation
CampusRide enforces secure role-based authorization without predictable default credentials.

To promote any registered user to an **Administrator**:
1. Register your personal account via the website at `http://localhost/campus-ride/register.php`.
2. Open **phpMyAdmin** -> database `campus_ride_sharing` -> table `users`.
3. Locate your user record and edit it:
   - Set the `is_admin` column to `1`.
   - Click **Go** to save.
   *(Alternatively, run SQL: `UPDATE users SET is_admin = 1 WHERE email = 'your_email@college.edu';`)*
4. Log out and log back in.
5. You will now see the **⚙️ Admin** link in the top navigation bar with full access to `admin/index.php`, user suspension tools, and ride moderation controls!

---

## 🌐 InfinityFree Online Deployment Guide (Step-by-Step)

InfinityFree provides free Apache + PHP + MySQL shared hosting. Because CampusRide is written in clean, modern PHP 8 and native MySQL with zero Node.js dependencies, it deploys seamlessly.

### Step 1: Create Website on InfinityFree
1. Log in to [InfinityFree Dashboard](https://dash.infinityfree.com/).
2. Click **Create Account** to set up a new free hosting account.
3. Choose a free subdomain (e.g., `mycampusride.infinityfreeapp.com` or `campusride.rf.gd`).
4. Wait 1–2 minutes for the hosting account to be created.

### Step 2: Create MySQL Database on InfinityFree
1. From your InfinityFree account control panel, click **MySQL Databases** (or access via **vPanel**).
2. Under "Create New Database", enter database name suffix, e.g., `campus_ride_sharing`.
3. Note the connection details displayed on the page:
   - **MySQL Hostname**: e.g., `sql123.infinityfree.com` *(Do NOT use localhost or 127.0.0.1 on InfinityFree!)*
   - **Database Name**: e.g., `epiz_12345678_campus_ride_sharing`
   - **MySQL Username**: e.g., `epiz_12345678`
   - **MySQL Password**: Your vPanel hosting account password (found under Account Details in dashboard)
   - **Port**: `3306` (standard MySQL port on InfinityFree)

### Step 3: Import Database Schema via phpMyAdmin
1. In the InfinityFree vPanel / Databases section, click the **phpMyAdmin** link next to your newly created database.
2. Inside phpMyAdmin, click the **Import** tab.
3. Select `database/database.sql` from the extracted project ZIP.
4. Click **Go** at the bottom to create all tables (`users`, `rides`, `bookings`, `notifications`, `login_attempts`).

### Step 4: Upload Application Files
1. In your InfinityFree Dashboard, open the **Online File Manager** (or connect via FileZilla FTP using the FTP details shown in your dashboard).
2. Navigate into the **`htdocs`** folder of your website.
   *(Ensure you are inside `htdocs` — files outside `htdocs` are not publicly served).*
3. Extract `CampusRide_Complete.zip` on your computer.
4. Upload all files and folders from inside `campus-ride` directly into `htdocs`:
   - `index.php`
   - `login.php`
   - `register.php`
   - `dashboard.php`
   - `post-ride.php`
   - `find-ride.php`
   - `ride-details.php`
   - `my-rides.php`
   - `my-bookings.php`
   - `profile.php`
   - `notifications.php`
   - `logout.php`
   - `.htaccess`
   - `assets/` folder
   - `config/` folder
   - `includes/` folder
   - `admin/` folder
   - `database/` folder

### Step 5: Configure Production Database Connection
1. In the File Manager inside `htdocs`, create a `.env` file (or copy `.env.example` to `.env`).
2. Put your exact InfinityFree credentials inside `.env`:
   ```ini
   DB_HOST=sql123.infinityfree.com
   DB_PORT=3306
   DB_NAME=epiz_12345678_campus_ride_sharing
   DB_USER=epiz_12345678
   DB_PASSWORD=your_actual_vpanel_password
   ```
3. Alternatively, you can edit `config/config.php` default constants if you prefer not using a `.env` file.
4. Ensure `.htaccess` is present in `htdocs` to protect `.env` and `database/` from public download.

### Step 6: Enable HTTPS & Test Live Website
1. In your InfinityFree dashboard, go to **Free SSL Certificates**.
2. Issue a free certificate (Let's Encrypt or GoGetSSL) for your domain and install it with one click.
3. Open your live domain in your browser: `https://your-subdomain.infinityfreeapp.com`.
4. Test:
   - Register a student or faculty account.
   - Log in and verify dashboard statistics.
   - Offer a ride and search for it.
   - Test seat booking and in-app notifications.

---

## 🧪 Testing Summary & Verification
A 15-test automated end-to-end test suite (`tests/test_suite.php`) was executed directly against MySQL:

| # | Test Case | Scope | Result |
|---|-----------|-------|:------:|
| 1 | Existing User Preservation | Confirmed `bunny` & `mabhu` intact | **PASS** |
| 2 | User Registration | Validated field persistence & ID generation | **PASS** |
| 3 | Password Security | Verified Bcrypt hash compatibility | **PASS** |
| 4 | Duplicate Prevention | Verified unique constraints on Email & College ID | **PASS** |
| 5 | Authentication Logic | Tested login by Email and College ID; rejected bad passwords | **PASS** |
| 6 | Post a Ride | Validated route, date, fare, and vehicle types | **PASS** |
| 7 | Route Search & Filters | Tested partial search (`LIKE %...%`) and capacity filter | **PASS** |
| 8 | Self-Booking Restriction | Verified driver cannot reserve seats on own ride | **PASS** |
| 9 | Booking Request Workflow | Verified status `pending` and driver alert | **PASS** |
| 10 | Real-Time Seat Lock | Row-lock transaction decremented seats on accept | **PASS** |
| 11 | Overbooking Protection | Blocked requests exceeding remaining seats | **PASS** |
| 12 | Seat Restoration | Cancelled booking restored available seats to ride | **PASS** |
| 13 | In-App Notifications | Tested unread counter and mark as read | **PASS** |
| 14 | Profile & Password Update | Tested current password verification & rehash | **PASS** |
| 15 | Admin Role Authorization | Tested role verification and admin privileges | **PASS** |

To re-run the automated test suite locally at any time:
```powershell
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\campus-ride\tests\test_suite.php"
```

---

## 🛡️ Security Best Practices Implemented
1. **Zero Raw Password Storage**: Passwords hashed using PHP's native `password_hash()` with auto-rehash support.
2. **SQL Injection Immune**: 100% of queries use PDO prepared statements with parameter binding; emulated prepares are disabled.
3. **Session Fixation Proof**: `session_regenerate_id(true)` called upon authentication; cookies use `HttpOnly` and `SameSite=Lax`.
4. **CSRF Tokens**: Every POST form generates and verifies cryptographically secure tokens.
5. **No Client Trust for Fares**: Fares and booking totals are strictly calculated server-side.
6. **Concurrent Overbooking Prevention**: Database transactions utilize `SELECT ... FOR UPDATE` row locks during confirmation and cancellations.
7. **Rate Limiting**: Brute-force protection temporarily locks IP addresses exhibiting frequent consecutive login failures.

---

## 📄 License & Academic Attribution
Developed as an academic engineering prototype for campus mobility and sustainable university transportation.
© 2026 CampusRide. All rights reserved.
=======
