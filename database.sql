-- Qsystem database. Import via phpMyAdmin > Import
CREATE DATABASE IF NOT EXISTS qsystem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE qsystem_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','staff') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  prefix CHAR(1) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  service_id INT NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  staff_note VARCHAR(255) DEFAULT NULL,
  ticket_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id),
  INDEX (appt_date, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queue_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(10) NOT NULL,
  service_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  guest_name VARCHAR(100) NOT NULL,
  appointment_id INT DEFAULT NULL,
  queue_date DATE NOT NULL,
  seq INT NOT NULL,
  status ENUM('waiting','serving','done','skipped','cancelled') NOT NULL DEFAULT 'waiting',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  called_at DATETIME DEFAULT NULL,
  finished_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_daily (service_id, queue_date, seq),
  FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

INSERT INTO services (name, prefix) VALUES
('Registrar','R'),('Cashier','C'),('Guidance Office','G'),('Clinic','H'),('Admissions','A');
-- Staff/student demo accounts are created by setup.php (hashed passwords).
