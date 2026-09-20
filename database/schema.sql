-- ============================================================
-- Barbershop Queue & Appointment Management System
-- Database Schema (MySQL 8.0+ / MariaDB 10.6+)
-- Compatible with PostgreSQL 15+ with minor type adjustments
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name`         VARCHAR(120)    NOT NULL,
    `phone`             VARCHAR(20)     NULL UNIQUE,
    `role`              ENUM('admin','stylist','customer') NOT NULL DEFAULT 'customer',
    `preferred_lang`    ENUM('en','am') NOT NULL DEFAULT 'en',
    `member_tier`       ENUM('regular','vip','platinum') NOT NULL DEFAULT 'regular',
    `password_hash`     VARCHAR(255)    NULL,
    `avatar_url`        VARCHAR(500)    NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stylists
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stylists` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           BIGINT UNSIGNED NOT NULL,
    `chair_number`      TINYINT UNSIGNED NULL,
    `bio_en`            TEXT            NULL,
    `bio_am`            TEXT            NULL,
    `specialties`       JSON            NULL,
    `status`            ENUM('active','break','offline') NOT NULL DEFAULT 'offline',
    `rating`            DECIMAL(2,1)    NOT NULL DEFAULT 5.0,
    `is_available`      BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stylists_user` (`user_id`),
    UNIQUE KEY `uk_stylists_chair` (`chair_number`),
    CONSTRAINT `fk_stylists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: services
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name_en`           VARCHAR(100)    NOT NULL,
    `name_am`           VARCHAR(100)    NOT NULL,
    `duration_minutes`  SMALLINT UNSIGNED NOT NULL,
    `price_etb`         DECIMAL(10,2)   NOT NULL,
    `description_en`    TEXT            NULL,
    `description_am`    TEXT            NULL,
    `is_active`         BOOLEAN         NOT NULL DEFAULT TRUE,
    `sort_order`        SMALLINT        NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_services_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_code`           VARCHAR(20)     NOT NULL,
    `customer_id`           BIGINT UNSIGNED NULL,
    `customer_name`         VARCHAR(120)    NOT NULL,
    `customer_phone`        VARCHAR(20)     NOT NULL,
    `stylist_id`            BIGINT UNSIGNED NULL,
    `status`                ENUM('waiting','called','in_chair','completed','cancelled') NOT NULL DEFAULT 'waiting',
    `total_price_etb`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `estimated_wait_minutes` SMALLINT UNSIGNED NULL,
    `priority`              TINYINT         NOT NULL DEFAULT 0,
    `notes`                 TEXT            NULL,
    `joined_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `called_at`             TIMESTAMP       NULL,
    `started_at`            TIMESTAMP       NULL,
    `completed_at`          TIMESTAMP       NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tickets_code` (`ticket_code`),
    INDEX `idx_tickets_status` (`status`),
    INDEX `idx_tickets_stylist_status` (`stylist_id`, `status`),
    INDEX `idx_tickets_joined` (`joined_at`),
    CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_stylist`  FOREIGN KEY (`stylist_id`)  REFERENCES `stylists` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ticket_services
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_services` (
    `ticket_id`     BIGINT UNSIGNED NOT NULL,
    `service_id`    BIGINT UNSIGNED NOT NULL,
    `price_etb`     DECIMAL(10,2)   NOT NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`ticket_id`, `service_id`),
    CONSTRAINT `fk_ts_ticket`  FOREIGN KEY (`ticket_id`)  REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ts_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: appointments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`       BIGINT UNSIGNED NULL,
    `customer_name`     VARCHAR(120)    NOT NULL,
    `customer_phone`    VARCHAR(20)     NOT NULL,
    `stylist_id`        BIGINT UNSIGNED NULL,
    `service_id`        BIGINT UNSIGNED NOT NULL,
    `appointment_date`  DATE            NOT NULL,
    `start_time`        TIME            NOT NULL,
    `end_time`          TIME            NULL,
    `status`            ENUM('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
    `notes`             TEXT            NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_appointments_date` (`appointment_date`, `start_time`),
    INDEX `idx_appointments_stylist` (`stylist_id`, `appointment_date`),
    CONSTRAINT `fk_appt_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appt_stylist`  FOREIGN KEY (`stylist_id`)  REFERENCES `stylists` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appt_service`  FOREIGN KEY (`service_id`)  REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: shop_settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_settings` (
    `id`                        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `is_queue_paused`           BOOLEAN         NOT NULL DEFAULT FALSE,
    `max_queue_size`            SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    `buffer_time_minutes`       TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `announcement_banner_en`    VARCHAR(500)    NULL,
    `announcement_banner_am`    VARCHAR(500)    NULL,
    `shop_name_en`              VARCHAR(120)    NOT NULL DEFAULT 'Elite Cuts',
    `shop_name_am`              VARCHAR(120)    NOT NULL DEFAULT 'ኤሊት ካትስ',
    `currency`                  VARCHAR(10)     NOT NULL DEFAULT 'ETB',
    `timezone`                  VARCHAR(50)     NOT NULL DEFAULT 'Africa/Addis_Ababa',
    `updated_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: events (for SSE / real-time)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type`    VARCHAR(50)     NOT NULL,
    `payload`       JSON            NOT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_events_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Shop Settings
INSERT INTO `shop_settings` (`id`, `is_queue_paused`, `max_queue_size`, `buffer_time_minutes`,
    `announcement_banner_en`, `announcement_banner_am`, `shop_name_en`, `shop_name_am`)
VALUES (1, FALSE, 40, 3,
    'Welcome to Elite Cuts — Walk-ins welcome!',
    'ወደ ኤሊት ካትስ እንኳን በደህና መጡ — Walk-in ይቀበላል!',
    'Elite Cuts', 'ኤሊት ካትስ');

-- Admin User
INSERT INTO `users` (`id`, `full_name`, `phone`, `role`, `preferred_lang`, `member_tier`, `password_hash`)
VALUES (1, 'Admin User', '+251911000001', 'admin', 'en', 'platinum',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: password

-- Stylist Users
INSERT INTO `users` (`id`, `full_name`, `phone`, `role`, `preferred_lang`, `member_tier`, `password_hash`, `avatar_url`) VALUES
(2, 'Abebe Kebede',   '+251911000002', 'stylist', 'am', 'regular', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL),
(3, 'Dawit Haile',    '+251911000003', 'stylist', 'en', 'regular', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL),
(4, 'Yonas Tesfaye',  '+251911000004', 'stylist', 'am', 'regular', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL),
(5, 'Michael Bekele', '+251911000005', 'stylist', 'en', 'regular', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL);

-- Stylists (Chairs)
INSERT INTO `stylists` (`id`, `user_id`, `chair_number`, `bio_en`, `bio_am`, `specialties`, `status`, `rating`) VALUES
(1, 2, 1, 'Master of classic fades and beard sculpting. 8 years experience.',
     'የክላሲክ ፌድ እና ጺም ቅርጽ ማስተር። 8 ዓመት ልምድ።',
     '["Skin Fade", "Beard Trim", "Classic Cut"]', 'active', 4.9),
(2, 3, 2, 'Creative stylist specializing in modern textures and color.',
     'በዘመናዊ ቴክስቸር እና ቀለም የተካነ ክሪኤቲቭ ስታይሊስት።',
     '["Textured Crop", "Hair Color", "Pompadour"]', 'active', 4.8),
(3, 4, 3, 'Precision barber — clean lines and sharp finishes.',
     'ትክክለኛነት ባርበር — ንጹህ መስመሮች እና ሹል ማጠናቀቅ።',
     '["Skin Fade", "Line Up", "Beard Design"]', 'break', 4.7),
(4, 5, 4, 'All-rounder with a calm, professional approach.',
     'በረጋ የሙያ አቀራረብ ሁሉንም ዓይነት አገልግሎት።',
     '["Any Style", "Kids Cut", "Senior Cut"]', 'offline', 4.9);

-- Services (ETB pricing)
INSERT INTO `services` (`id`, `name_en`, `name_am`, `duration_minutes`, `price_etb`, `description_en`, `sort_order`) VALUES
(1,  'Classic Haircut',          'ክላሲክ የፀጉር መቁረጥ',     30, 250.00, 'Traditional clean haircut with clippers and scissors', 1),
(2,  'Skin Fade',                'ስኪን ፌድ',               40, 350.00, 'Ultra-clean skin fade with seamless blend', 2),
(3,  'Skin Fade + Beard',        'ስኪን ፌድ + ጺም',          55, 480.00, 'Full skin fade combined with professional beard trim', 3),
(4,  'Beard Trim & Shape',       'ጺም መቁረጥና ቅርጽ',       20, 180.00, 'Detailed beard shaping and edge work', 4),
(5,  'Hot Towel Shave',          'ሞቅ ያለ ጨርቅ ሻቭ',      35, 300.00, 'Traditional hot towel straight razor shave', 5),
(6,  'Kids Cut (under 12)',      'የልጆች መቁረጥ',          25, 180.00, 'Patient and careful cut for children', 6),
(7,  'Senior Cut',               'የአረጋውያን መቁረጥ',      25, 200.00, 'Comfortable classic style for seniors', 7),
(8,  'Hair Wash & Style',        'ማጠብና ስታይል',          20, 150.00, 'Wash, condition and light styling', 8),
(9,  'Line Up / Edge Up',        'ላይን አፕ',               15, 120.00, 'Sharp line-up and edge work only', 9),
(10, 'Full Grooming Package',    'ሙሉ ግሩሚንግ',          75, 650.00, 'Haircut + beard + hot towel + style', 10);

-- Sample waiting tickets (for demo)
INSERT INTO `tickets` (`ticket_code`, `customer_name`, `customer_phone`, `stylist_id`, `status`,
                       `total_price_etb`, `estimated_wait_minutes`, `joined_at`) VALUES
('A-01', 'Yohannes M.', '+251912345678', NULL, 'waiting', 350.00, 12, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
('A-02', 'Samuel T.',   '+251911223344', 1,    'waiting', 480.00, 8,  DATE_SUB(NOW(), INTERVAL 11 MINUTE)),
('A-03', 'Biruk A.',    '+251913334455', NULL, 'waiting', 250.00, 22, DATE_SUB(NOW(), INTERVAL 6 MINUTE)),
('A-04', 'Kidus H.',    '+251914445566', 2,    'in_chair', 350.00, 0, DATE_SUB(NOW(), INTERVAL 25 MINUTE));

UPDATE `tickets` SET `started_at` = DATE_SUB(NOW(), INTERVAL 18 MINUTE), `called_at` = DATE_SUB(NOW(), INTERVAL 20 MINUTE)
WHERE `ticket_code` = 'A-04';

INSERT INTO `ticket_services` (`ticket_id`, `service_id`, `price_etb`, `duration_minutes`) VALUES
(1, 2, 350.00, 40),
(2, 3, 480.00, 55),
(3, 1, 250.00, 30),
(4, 2, 350.00, 40);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
