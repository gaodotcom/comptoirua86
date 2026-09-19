-- Migration : import du calendrier FFA (courses "Running") du département 086
--
-- Contexte : en plus des courses partagées manuellement par les adhérents
-- (table `races`), le club veut afficher les courses officielles "hors
-- stade" publiées par la FFA sur athle.fr pour le département de la Vienne.
-- Un admin déclenche un import ponctuel (page /import-calendrier-ffa) qui
-- parse la page calendrier FFA et upsert les courses dans cette table par
-- `ffa_competition_id` (ré-import idempotent).
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

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
