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
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_news_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS races (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    location VARCHAR(255) NULL,
    distances VARCHAR(255) NOT NULL,
    website_url VARCHAR(500) NULL,
    registration_info TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_races_author
        FOREIGN KEY (created_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO members (role, last_name, first_name, username, email, password_hash, date_of_birth, phone, address, postal_code, city, whatsapp_opt_in)
SELECT 'admin', 'Association', 'Admin', 'admin', 'admin@example.org', NULL, '1970-01-01', '', '', '', '', 0
WHERE NOT EXISTS (
    SELECT 1 FROM members WHERE username = 'admin'
);
