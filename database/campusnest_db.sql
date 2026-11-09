-- CampusNest: Hostel & Rent Management System
-- Import in phpMyAdmin (Import tab) or: mysql -u root < campusnest_db.sql
-- Demo logins:  admin@campusnest.test / Admin@123   |   ama@student.test / Tenant@123 (all tenants use Tenant@123)

CREATE DATABASE IF NOT EXISTS campusnest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campusnest_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, messages, reviews, roommate_profiles, room_photos, audit_log, maintenance_requests, payments, tenants, bookings, rooms, room_types, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','tenant') NOT NULL DEFAULT 'tenant',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE room_types (
  room_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type_name VARCHAR(50) NOT NULL UNIQUE,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  price_per_month DECIMAL(10,2) NOT NULL,
  description TEXT
) ENGINE=InnoDB;

CREATE TABLE rooms (
  room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_type_id INT UNSIGNED NOT NULL,
  room_number VARCHAR(10) NOT NULL UNIQUE,
  floor TINYINT NOT NULL DEFAULT 0,
  status ENUM('vacant','occupied','maintenance') NOT NULL DEFAULT 'vacant',
  image_path VARCHAR(255),
  pano_path VARCHAR(255) NULL,
  FOREIGN KEY (room_type_id) REFERENCES room_types(room_type_id)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  move_in_date DATE NOT NULL,
  duration_months TINYINT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE tenants (
  tenant_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  room_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NULL UNIQUE,
  lease_start DATE NOT NULL,
  lease_end DATE NOT NULL,
  monthly_rent DECIMAL(10,2) NOT NULL,
  total_due DECIMAL(10,2) NOT NULL,
  status ENUM('active','ended') NOT NULL DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL CHECK (amount > 0),
  payment_date DATE NOT NULL,
  method ENUM('cash','mobile_money','bank_transfer') NOT NULL,
  reference VARCHAR(60),
  receipt_no VARCHAR(20) NOT NULL UNIQUE,
  recorded_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
  FOREIGN KEY (recorded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE maintenance_requests (
  request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
  log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  table_name VARCHAR(40),
  record_id INT UNSIGNED,
  details VARCHAR(255),
  ip_address VARCHAR(45),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE room_photos (
  photo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL UNIQUE,
  rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment VARCHAR(500),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE roommate_profiles (
  tenant_id INT UNSIGNED PRIMARY KEY,
  sleep_pattern ENUM('early','flexible','night') NOT NULL DEFAULT 'flexible',
  study_style ENUM('quiet','mixed','social') NOT NULL DEFAULT 'mixed',
  tidiness TINYINT UNSIGNED NOT NULL DEFAULT 3,
  social_level TINYINT UNSIGNED NOT NULL DEFAULT 3,
  bio VARCHAR(200),
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body VARCHAR(1000) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(255),
  link VARCHAR(200),
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Sample data ----------
INSERT INTO users (user_id, full_name, email, phone, password_hash, role) VALUES
(1, 'Hostel Manager', 'admin@campusnest.test', '0240000000', '$2b$12$d.v0OXiSJeq1UaJb9s4BOO6PER9r0VGkSdv2NjIttmKpOeIBy8Esu', 'admin'),
(2, 'Ama Boateng', 'ama@student.test', '0240000002', '$2b$12$jHWjM0OeMeTUapwryi0sz.rBa0l5bPOC5FAYSR59CWtHVlYws5lOy', 'tenant'),
(3, 'Kofi Asare', 'kofi@student.test', '0240000003', '$2b$12$jHWjM0OeMeTUapwryi0sz.rBa0l5bPOC5FAYSR59CWtHVlYws5lOy', 'tenant'),
(4, 'Abena Owusu', 'abena@student.test', '0240000004', '$2b$12$jHWjM0OeMeTUapwryi0sz.rBa0l5bPOC5FAYSR59CWtHVlYws5lOy', 'tenant'),
(5, 'Yaw Mensah', 'yaw@student.test', '0240000005', '$2b$12$jHWjM0OeMeTUapwryi0sz.rBa0l5bPOC5FAYSR59CWtHVlYws5lOy', 'tenant');

INSERT INTO room_types (room_type_id, type_name, capacity, price_per_month, description) VALUES
(1, 'Single', 1, 900.00, 'Private room with desk and wardrobe'),
(2, 'Double', 2, 650.00, 'Shared by two students, price per person'),
(3, 'Four-in-a-room', 4, 450.00, 'Shared by four students, price per person'),
(4, 'Executive Single', 1, 1200.00, 'Private room with ensuite bathroom');

INSERT INTO rooms (room_id, room_type_id, room_number, floor, status, image_path, pano_path) VALUES
(1, 1, 'A101', 0, 'vacant', 'assets/img/rooms/sample_1.jpg', 'assets/img/rooms/sample_pano.jpg'),
(2, 2, 'A103', 0, 'vacant', 'assets/img/rooms/sample_2.jpg', NULL),
(3, 1, 'A112', 1, 'occupied', 'assets/img/rooms/sample_3.jpg', NULL),
(4, 2, 'A204', 2, 'occupied', 'assets/img/rooms/sample_2.jpg', NULL),
(5, 2, 'A205', 2, 'vacant', 'assets/img/rooms/sample_2.jpg', NULL),
(6, 3, 'B110', 1, 'vacant', 'assets/img/rooms/sample_3.jpg', NULL),
(7, 3, 'B111', 1, 'vacant', 'assets/img/rooms/sample_pano.jpg', NULL),
(8, 1, 'B205', 2, 'occupied', 'assets/img/rooms/sample_1.jpg', NULL),
(9, 1, 'B206', 2, 'vacant', 'assets/img/rooms/sample_3.jpg', NULL),
(10, 2, 'C103', 0, 'vacant', 'assets/img/rooms/sample_2.jpg', NULL),
(11, 3, 'C208', 2, 'occupied', 'assets/img/rooms/sample_3.jpg', NULL),
(12, 1, 'C210', 2, 'vacant', 'assets/img/rooms/sample_1.jpg', NULL),
(13, 4, 'D101', 0, 'vacant', 'assets/img/rooms/sample_4.jpg', 'assets/img/rooms/sample_pano.jpg'),
(14, 4, 'D102', 0, 'maintenance', 'assets/img/rooms/sample_4.jpg', NULL);

INSERT INTO bookings (booking_id, room_id, full_name, email, phone, move_in_date, duration_months, status, reviewed_by) VALUES
(1, 8, 'Ama Boateng', 'ama@student.test', '0240000002', '2026-09-01', 9, 'approved', 1),
(2, 3, 'Kofi Asare', 'kofi@student.test', '0240000003', '2026-09-01', 9, 'approved', 1),
(3, 11, 'Abena Owusu', 'abena@student.test', '0240000004', '2026-09-01', 9, 'approved', 1),
(4, 4, 'Yaw Mensah', 'yaw@student.test', '0240000005', '2026-10-01', 8, 'approved', 1),
(5, 10, 'Efua Adjei', 'efua@applicant.test', '0240000006', '2026-12-01', 9, 'pending', NULL),
(6, 1, 'Kwesi Mensah', 'kwesi@applicant.test', '0240000007', '2026-12-05', 9, 'pending', NULL),
(7, 2, 'Nana Yeboah', 'nana@applicant.test', '0240000008', '2026-11-20', 6, 'rejected', 1);

INSERT INTO tenants (tenant_id, user_id, room_id, booking_id, lease_start, lease_end, monthly_rent, total_due, status) VALUES
(1, 2, 8, 1, '2026-09-01', '2027-05-31', 900.00, 8100.00, 'active'),
(2, 3, 3, 2, '2026-09-01', '2027-05-31', 900.00, 8100.00, 'active'),
(3, 4, 11, 3, '2026-09-01', '2027-05-31', 450.00, 4050.00, 'active'),
(4, 5, 4, 4, '2026-10-01', '2027-05-31', 650.00, 5200.00, 'active');

INSERT INTO payments (payment_id, tenant_id, amount, payment_date, method, reference, receipt_no, recorded_by) VALUES
(1, 1, 900.00, '2026-09-02', 'mobile_money', 'MM-77120', 'RC-0001', 1),
(2, 1, 900.00, '2026-10-03', 'cash', NULL, 'RC-0002', 1),
(3, 1, 450.00, '2026-11-02', 'mobile_money', 'MM-80412', 'RC-0003', 1),
(4, 2, 900.00, '2026-09-04', 'cash', NULL, 'RC-0004', 1),
(5, 3, 450.00, '2026-09-03', 'mobile_money', 'MM-77305', 'RC-0005', 1),
(6, 3, 450.00, '2026-10-02', 'mobile_money', 'MM-79001', 'RC-0006', 1),
(7, 3, 450.00, '2026-11-03', 'cash', NULL, 'RC-0007', 1),
(8, 4, 650.00, '2026-10-05', 'bank_transfer', 'TR-5531', 'RC-0008', 1),
(9, 4, 650.00, '2026-11-04', 'bank_transfer', 'TR-6120', 'RC-0009', 1);

INSERT INTO maintenance_requests (request_id, tenant_id, room_id, title, description, priority, status, resolved_at) VALUES
(1, 1, 8, 'Leaking tap in bathroom', 'The tap drips all night and wastes water.', 'medium', 'in_progress', NULL),
(2, 3, 11, 'Broken ceiling light', 'The light above the study area stopped working.', 'low', 'resolved', NOW()),
(3, 2, 3, 'Window will not close', 'The window latch is broken and rain gets in.', 'high', 'open', NULL);

INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) VALUES
(1, 'booking_approved', 'tenants', 1, 'Booking 1', '127.0.0.1'),
(1, 'payment_recorded', 'payments', 1, 'GHS 900.00', '127.0.0.1'),
(1, 'booking_rejected', 'bookings', 7, NULL, '127.0.0.1');

INSERT INTO room_photos (room_id, path) VALUES
(1, 'assets/img/rooms/sample_1.jpg'), (1, 'assets/img/rooms/sample_2.jpg'), (1, 'assets/img/rooms/sample_3.jpg'),
(13, 'assets/img/rooms/sample_4.jpg'), (13, 'assets/img/rooms/sample_2.jpg'), (2, 'assets/img/rooms/sample_2.jpg');

INSERT INTO reviews (room_id, tenant_id, rating, comment) VALUES
(8, 1, 5, 'Quiet floor and a great desk. Perfect for studying.'),
(3, 2, 4, 'Good room. The window latch needs fixing.'),
(11, 3, 5, 'Great value and a clean block.'),
(4, 4, 4, 'Spacious, and the manager responds fast.');

INSERT INTO roommate_profiles (tenant_id, sleep_pattern, study_style, tidiness, social_level, bio) VALUES
(1, 'early', 'quiet', 4, 2, 'Early riser, studies in the library, loves jollof.'),
(2, 'night', 'mixed', 3, 4, 'Night owl, football fan, always up for a chat.'),
(3, 'flexible', 'quiet', 5, 3, 'Neat and calm. Gospel music while cleaning.'),
(4, 'early', 'social', 3, 5, 'Group study lover. Cooks on weekends.');

INSERT INTO messages (tenant_id, sender_id, body, is_read) VALUES
(1, 2, 'Good morning. The bathroom tap is still leaking.', 1),
(1, 1, 'Good morning Ama. A plumber is booked for tomorrow.', 1),
(1, 2, 'Thank you! Is it okay if I pay the balance on Friday?', 0);

INSERT INTO notifications (user_id, title, body, link, is_read) VALUES
(1, 'New booking request', 'Efua Adjei requested room C103.', '/admin/bookings.php?status=pending', 0),
(1, 'New message', 'Ama Boateng sent you a message.', '/admin/chat.php?tenant_id=1', 0),
(2, 'Payment received', 'GHS 450.00 recorded. Receipt RC-0003.', '/tenant/dashboard.php', 0);
