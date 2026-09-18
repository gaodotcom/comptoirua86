-- Migration : préinscriptions week-end club Lozère Trail 2027
--
-- Contexte : page ponctuelle et hors menu (/week-end-club-2027) permettant aux
-- adhérents connectés de se préinscrire au week-end club au Lozère Trail
-- (15-16 mai 2027). Crée les tables de préinscription, leurs courses
-- sélectionnées, et le réglage de clôture manuelle par un admin.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS / INSERT IGNORE).

CREATE TABLE IF NOT EXISTS weekend_2027_registrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    team_mode ENUM('solo', 'duo') NULL,
    duo_partner_member_id INT UNSIGNED NULL,
    bivouac TINYINT(1) NOT NULL DEFAULT 0,
    shirt_size ENUM('XS', 'S', 'M', 'L', 'XL', 'XXL') NOT NULL,
    emergency_contact_name VARCHAR(200) NOT NULL,
    emergency_contact_phone VARCHAR(30) NOT NULL,
    accommodation ENUM('group', 'independent') NOT NULL,
    license_number VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_weekend_2027_member (member_id),
    CONSTRAINT fk_weekend_2027_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_weekend_2027_partner
        FOREIGN KEY (duo_partner_member_id)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekend_2027_registration_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT UNSIGNED NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_weekend_2027_reg_course (registration_id, course_code),
    CONSTRAINT fk_weekend_2027_reg
        FOREIGN KEY (registration_id)
        REFERENCES weekend_2027_registrations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekend_2027_settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    closed_at TIMESTAMP NULL,
    closed_by INT UNSIGNED NULL,
    CONSTRAINT fk_weekend_2027_closed_by
        FOREIGN KEY (closed_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO weekend_2027_settings (id, is_closed) VALUES (1, 0);
