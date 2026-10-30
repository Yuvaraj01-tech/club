-- HELLO HOBNOBBERS production-ready schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS checkins, ai_voice_calls, waitlist, notifications, announcements, event_passes, payments, bookings, event_slots, events, user_verifications, user_profiles, activity_logs, admins, users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(150) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending_profile','pending_review','approved','rejected','blocked') NOT NULL DEFAULT 'pending_profile',
  rejection_reason VARCHAR(500) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  country_code VARCHAR(5) NOT NULL DEFAULT '+91',
  mobile_number VARCHAR(10) NOT NULL,
  whatsapp_same TINYINT(1) NOT NULL DEFAULT 1,
  whatsapp_number VARCHAR(10) NULL,
  gender ENUM('male','female','other') NOT NULL,
  dob DATE NOT NULL,
  marital_status ENUM('never_married','separated','divorced','widowed') NOT NULL,
  instagram_handle VARCHAR(100) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('super_admin','staff') NOT NULL DEFAULT 'staff',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admins (username,password_hash,full_name,role)
VALUES ('admin','$2y$12$kZIST88b662b2owawIoqIOb9dX52elrqiZjdvkuQvES4szUC7i21e','Super Admin','super_admin');


CREATE TABLE user_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  doc_type ENUM('aadhaar','pan','voter_id','dl','any_govt') NOT NULL,
  doc_file_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_notes VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_verification_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(60) NULL,
  description TEXT NULL,
  venue VARCHAR(180) NOT NULL,
  organizer_name VARCHAR(120) NULL,
  event_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  eligibility_notes VARCHAR(500) NULL,
  important_info TEXT NULL,
  booking_closing_at DATETIME NULL,
  cover_image_path VARCHAR(255) NULL,
  status ENUM('draft','published','completed','cancelled') NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  slot_label VARCHAR(60) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 0,
  booked_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','full','closed') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_slot_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(30) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NOT NULL,
  slot_id INT UNSIGNED NOT NULL,
  status ENUM('pending_payment','confirmed','cancelled','waitlisted') NOT NULL DEFAULT 'pending_payment',
  booking_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id) REFERENCES event_slots(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_event (user_id,event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  razorpay_order_id VARCHAR(80) NOT NULL,
  razorpay_payment_id VARCHAR(80) NULL,
  razorpay_signature VARCHAR(255) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'INR',
  method VARCHAR(40) NULL,
  status ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
  paid_at DATETIME NULL,
  raw_response TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE event_passes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL UNIQUE,
  pass_code VARCHAR(40) NOT NULL UNIQUE,
  qr_payload VARCHAR(255) NOT NULL,
  qr_image_path VARCHAR(255) NULL,
  status ENUM('active','used','cancelled','expired') NOT NULL DEFAULT 'active',
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pass_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checkins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pass_id INT UNSIGNED NULL,
  booking_id INT UNSIGNED NULL,
  scanned_by INT UNSIGNED NULL,
  scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  result ENUM('approved','invalid','already_used','expired','wrong_event') NOT NULL,
  device_info VARCHAR(255) NULL,
  CONSTRAINT fk_checkin_pass FOREIGN KEY (pass_id) REFERENCES event_passes(id) ON DELETE SET NULL,
  CONSTRAINT fk_checkin_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_checkin_admin FOREIGN KEY (scanned_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  event_id INT UNSIGNED NULL,
  audience ENUM('all','event_attendees') NOT NULL DEFAULT 'all',
  sent_via_email TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcement_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  CONSTRAINT fk_announcement_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  type ENUM('system','event','booking','payment','announcement') NOT NULL DEFAULT 'system',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_voice_calls (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('scheduled','completed','failed') NOT NULL DEFAULT 'scheduled',
  provider_response TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_voice_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE waitlist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  slot_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('waiting','offered','expired') NOT NULL DEFAULT 'waiting',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_waitlist_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_waitlist_slot FOREIGN KEY (slot_id) REFERENCES event_slots(id) ON DELETE CASCADE,
  CONSTRAINT fk_waitlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','admin','system') NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
