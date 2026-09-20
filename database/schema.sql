CREATE TABLE IF NOT EXISTS members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('adherent', 'coach', 'bureau', 'admin') NOT NULL DEFAULT 'adherent',
    gender ENUM('M', 'F') NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    username VARCHAR(30) NULL DEFAULT NULL,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    postal_code VARCHAR(15) NULL,
    city VARCHAR(100) NULL,
    whatsapp_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    is_bureau TINYINT(1) NOT NULL DEFAULT 0,
    is_coach TINYINT(1) NOT NULL DEFAULT 0,
    generic_account TINYINT(1) NOT NULL DEFAULT 0,
    bureau_role VARCHAR(50) NULL,
    photo_path VARCHAR(255) NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_members_username (username),
    UNIQUE KEY uq_members_email (email),
    KEY idx_members_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    fee INT NOT NULL DEFAULT 0,
    donation INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership_school_year (member_id, school_year),
    CONSTRAINT fk_memberships_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS active_school_years (
    school_year VARCHAR(9) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS helloasso_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(200) NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    url VARCHAR(500) NULL,
    last_imported_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ha_campaigns_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    title VARCHAR(150) NOT NULL,
    presentation_text TEXT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    comment_text TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_trainings_week (week_start),
    CONSTRAINT fk_trainings_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    location VARCHAR(255) NULL,
    description TEXT NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_news_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout a posteriori pour les bases déjà déployées avant l'introduction du
-- tri manuel des actualités (schema.sql n'étant importé qu'une seule fois à
-- l'installation, le CREATE TABLE ci-dessus ne suffit pas à le rajouter).
ALTER TABLE news ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER published;

CREATE TABLE IF NOT EXISTS races (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    location VARCHAR(255) NULL,
    distances VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) NULL,
    favicon_path VARCHAR(255) NULL,
    registration_info TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_races_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout a posteriori pour les bases déjà déployées avant l'introduction du
-- favicon stocké localement (schema.sql n'étant importé qu'une seule fois à
-- l'installation, le CREATE TABLE ci-dessus ne suffit pas à le rajouter).
ALTER TABLE races ADD COLUMN IF NOT EXISTS favicon_path VARCHAR(255) NULL AFTER website_url;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prt_token (token),
    UNIQUE KEY uq_prt_member (member_id),
    CONSTRAINT fk_prt_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    selector VARCHAR(24) NOT NULL,
    validator_hash VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_member (member_id),
    CONSTRAINT fk_remember_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS race_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    race_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    status ENUM('interested', 'registered') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_race_member (race_id, member_id),
    CONSTRAINT fk_responses_race
        FOREIGN KEY (race_id)
        REFERENCES races(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_responses_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS local_races (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ffa_competition_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    city VARCHAR(150) NULL,
    department_code VARCHAR(3) NULL,
    level VARCHAR(50) NULL,
    detail_url VARCHAR(500) NULL,
    season INT UNSIGNED NOT NULL,
    imported_by INT UNSIGNED NULL,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_local_races_ffa_id (ffa_competition_id),
    CONSTRAINT fk_local_races_imported_by
        FOREIGN KEY (imported_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO members (role, last_name, first_name, username, email, password_hash, date_of_birth, phone, address, postal_code, city, whatsapp_opt_in)
SELECT 'admin', 'Association', 'Admin', 'admin', 'admin@example.org', NULL, '1970-01-01', '', '', '', '', 0
WHERE NOT EXISTS (
    SELECT 1 FROM members WHERE username = 'admin'
);
