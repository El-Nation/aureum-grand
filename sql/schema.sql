-- ============================================================
-- AUREUM HOTEL PLATFORM — DATABASE SCHEMA
-- ============================================================
-- Import this file into phpMyAdmin / MySQL to create all tables.
-- Charset: utf8mb4 (full emoji + multi-language support)
-- Engine: InnoDB (foreign keys, transactions)
--
-- IMPORTANT: if importing via the mysql CLI, use:
--   mysql -u root -p --default-character-set=utf8mb4 < sql/schema.sql
-- Without this flag, some MySQL CLI builds default to latin1 for the
-- connection and will corrupt special characters (em-dashes, accented
-- letters) in the seed data below. phpMyAdmin handles this correctly
-- by default, so this only matters for command-line imports.
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS hotel_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_website;

-- ------------------------------------------------------------
-- 1. PROPERTIES (multi-property support)
-- ------------------------------------------------------------
CREATE TABLE properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    address VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(150),
    timezone VARCHAR(50) DEFAULT 'Africa/Lagos',
    star_rating TINYINT DEFAULT 5,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. ROLES & STAFF USERS
-- ------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO roles (name, description) VALUES
('administrator', 'Full system control'),
('general_manager', 'Operations and revenue oversight'),
('front_desk', 'Reservations and guest management'),
('housekeeping', 'Cleaning and inspections'),
('concierge', 'Guest services and requests'),
('revenue_manager', 'Pricing and forecasting'),
('maintenance', 'Issue tracking and repairs');

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    two_factor_secret VARCHAR(255) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. GUESTS (public-facing accounts)
-- ------------------------------------------------------------
CREATE TABLE guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash VARCHAR(255) NOT NULL,
    loyalty_points INT DEFAULT 0,
    loyalty_tier ENUM('Silver','Gold','Platinum','VIP') DEFAULT 'Silver',
    preferences TEXT,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE guest_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (guest_id, room_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. ROOM CATEGORIES & ROOMS
-- ------------------------------------------------------------
CREATE TABLE room_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    max_occupancy INT NOT NULL DEFAULT 2,
    bed_configuration VARCHAR(150),
    size_sqm INT,
    view_type VARCHAR(100),
    is_accessible TINYINT(1) DEFAULT 0,
    amenities TEXT COMMENT 'JSON array of amenity strings',
    main_image VARCHAR(255),
    bathroom_image VARCHAR(255) DEFAULT NULL,
    toilet_image VARCHAR(255) DEFAULT NULL,
    gallery_images TEXT COMMENT 'JSON array of image paths',
    virtual_tour_url VARCHAR(255),
    video_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    floor INT,
    status ENUM('clean','dirty','in_progress','inspected','out_of_order') DEFAULT 'clean',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_number (category_id, room_number)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. PRICING RULES (seasonal / weekend / holiday / promo)
-- ------------------------------------------------------------
CREATE TABLE pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    rule_type ENUM('seasonal','weekend','holiday','promo','corporate','group','early_bird','last_minute') NOT NULL,
    name VARCHAR(150) NOT NULL,
    start_date DATE,
    end_date DATE,
    adjustment_type ENUM('fixed_price','percent_discount','percent_increase','fixed_discount') NOT NULL,
    adjustment_value DECIMAL(10,2) NOT NULL,
    promo_code VARCHAR(50) DEFAULT NULL,
    min_stay_nights INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE blackout_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. RESERVATIONS
-- ------------------------------------------------------------
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    guest_id INT NULL,
    guest_name VARCHAR(150) NOT NULL,
    guest_email VARCHAR(150) NOT NULL,
    guest_phone VARCHAR(30),
    room_id INT NULL,
    category_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    nights INT NOT NULL,
    rate_per_night DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    promo_code VARCHAR(50) DEFAULT NULL,
    status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'pending',
    payment_status ENUM('unpaid','partial','paid','refunded') DEFAULT 'unpaid',
    payment_method ENUM('paystack','pay_at_property') DEFAULT 'pay_at_property',
    special_requests TEXT,
    source ENUM('direct','ota_booking_com','ota_expedia','corporate','walk_in') DEFAULT 'direct',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES room_categories(id)
) ENGINE=InnoDB;

CREATE TABLE reservation_status_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    old_status VARCHAR(30),
    new_status VARCHAR(30),
    changed_by INT COMMENT 'staff id',
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. PAYMENTS (Paystack)
-- ------------------------------------------------------------
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    paystack_reference VARCHAR(100) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'NGN',
    type ENUM('full','deposit','refund') DEFAULT 'full',
    status ENUM('pending','success','failed','refunded') DEFAULT 'pending',
    gateway_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. LOYALTY LEDGER
-- ------------------------------------------------------------
CREATE TABLE loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guest_id INT NOT NULL,
    points INT NOT NULL COMMENT 'positive=earned, negative=redeemed',
    reason VARCHAR(150),
    reservation_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. GUEST SERVICE REQUESTS (concierge)
-- ------------------------------------------------------------
CREATE TABLE service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NULL,
    guest_id INT NULL,
    request_type ENUM('airport_transfer','chauffeur','spa','restaurant','tour','event_ticket','room_service','other') NOT NULL,
    details TEXT,
    requested_for DATETIME,
    status ENUM('new','acknowledged','in_progress','completed','cancelled') DEFAULT 'new',
    assigned_to INT NULL COMMENT 'staff id',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. HOUSEKEEPING TASKS
-- ------------------------------------------------------------
CREATE TABLE housekeeping_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    task_type ENUM('checkout_clean','daily_clean','inspection','turndown') DEFAULT 'daily_clean',
    status ENUM('pending','in_progress','done','verified') DEFAULT 'pending',
    assigned_to INT NULL,
    notes VARCHAR(255),
    scheduled_for DATE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. MAINTENANCE TICKETS
-- ------------------------------------------------------------
CREATE TABLE maintenance_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NULL,
    property_id INT NOT NULL,
    issue_type ENUM('hvac','plumbing','electrical','furniture','other') NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','assigned','in_progress','resolved','closed') DEFAULT 'open',
    assigned_to INT NULL,
    cost DECIMAL(10,2) DEFAULT 0,
    reported_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. NOTIFICATIONS LOG
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('guest','staff') NOT NULL,
    recipient_id INT NOT NULL,
    channel ENUM('email','sms','whatsapp') DEFAULT 'email',
    subject VARCHAR(150),
    message TEXT,
    status ENUM('queued','sent','failed') DEFAULT 'queued',
    related_reservation_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 13. ACTIVITY / AUDIT LOG
-- ------------------------------------------------------------
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('staff','guest','system') DEFAULT 'staff',
    actor_id INT NULL,
    action VARCHAR(150) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT NULL,
    ip_address VARCHAR(45),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 14. OTA CHANNEL MAP (stub for third-party sync)
-- ------------------------------------------------------------
CREATE TABLE ota_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    external_listing_id VARCHAR(150),
    sync_enabled TINYINT(1) DEFAULT 0,
    last_synced_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE / SEED DATA — so the site has content out of the box
-- ============================================================

INSERT INTO properties (name, slug, address, city, country, phone, email, star_rating) VALUES
('Aureum Grand Hotel', 'aureum-grand', '12 Victoria Island Boulevard', 'Lagos', 'Nigeria', '+234 1 234 5678', 'reservations@aureumgrand.com', 5);

INSERT INTO room_categories (property_id, name, description, base_price, max_occupancy, bed_configuration, size_sqm, view_type, is_accessible, amenities, main_image) VALUES
(1, 'Deluxe Garden Room', 'A calm, light-filled room overlooking the hotel gardens, finished in warm oak and linen.', 85000, 2, '1 King Bed', 32, 'Garden View', 0, '["Free Wi-Fi","Air Conditioning","Smart TV","Minibar","Rain Shower","Daily Housekeeping"]', 'room-deluxe.jpg'),
(1, 'Executive Ocean Suite', 'A spacious suite with a private balcony facing the Atlantic, designed for the discerning business traveller.', 165000, 3, '1 King Bed + Sofa Bed', 54, 'Ocean View', 0, '["Free Wi-Fi","Air Conditioning","Smart TV","Minibar","Rain Shower","Private Balcony","Work Desk","Espresso Machine"]', 'room-executive.jpg'),
(1, 'Presidential Penthouse', 'The pinnacle of Aureum hospitality — a full-floor penthouse with a private terrace, plunge pool and dedicated butler service.', 480000, 4, '2 King Beds', 140, 'Panoramic City & Ocean View', 1, '["Free Wi-Fi","Private Plunge Pool","Butler Service","Smart TV","Full Bar","Private Terrace","Dining Room","Jacuzzi"]', 'room-penthouse.jpg'),
(1, 'Accessible Family Room', 'A wheelchair-accessible room with connecting layout, built for families travelling together in comfort.', 95000, 4, '2 Queen Beds', 40, 'City View', 1, '["Free Wi-Fi","Air Conditioning","Smart TV","Roll-in Shower","Connecting Room Option","Crib Available"]', 'room-family.jpg');

INSERT INTO rooms (category_id, room_number, floor, status) VALUES
(1, '201', 2, 'clean'), (1, '202', 2, 'clean'), (1, '203', 2, 'dirty'), (1, '204', 2, 'clean'),
(2, '501', 5, 'clean'), (2, '502', 5, 'inspected'), (2, '503', 5, 'in_progress'),
(3, '1801', 18, 'clean'),
(4, '110', 1, 'clean'), (4, '112', 1, 'out_of_order');

INSERT INTO pricing_rules (category_id, rule_type, name, start_date, end_date, adjustment_type, adjustment_value, promo_code, min_stay_nights) VALUES
(1, 'early_bird', 'Book 30 Days Ahead', '2026-01-01', '2026-12-31', 'percent_discount', 15, 'EARLY15', 2),
(2, 'weekend', 'Weekend Escape', NULL, NULL, 'percent_increase', 10, NULL, 1),
(3, 'last_minute', 'Penthouse Flash Offer', '2026-06-01', '2026-07-31', 'percent_discount', 20, 'FLASH20', 3);

-- Demo password for all 3 seed accounts below is: Aureum2026!
-- (hash generated with PHP's password_hash() — change this before going live)
INSERT INTO staff (property_id, role_id, full_name, email, password_hash) VALUES
(1, 1, 'Adaeze Okafor', 'admin@aureumgrand.com', '$2y$10$HqfzHavWa7IS3H8B3akJDe8EA8DtKFyOw5bnzTe8QrhzXbESb0LT.'),
(1, 3, 'Tunde Bello', 'frontdesk@aureumgrand.com', '$2y$10$HqfzHavWa7IS3H8B3akJDe8EA8DtKFyOw5bnzTe8QrhzXbESb0LT.'),
(1, 4, 'Ngozi Eze', 'housekeeping@aureumgrand.com', '$2y$10$HqfzHavWa7IS3H8B3akJDe8EA8DtKFyOw5bnzTe8QrhzXbESb0LT.');

-- ============================================================
-- END OF SCHEMA
-- ============================================================
