-- Clôture du formulaire du test VMA 2026 : automatique le 12/10/2026 à 0h,
-- avec possibilité pour un admin de forcer la clôture ou la réouverture.
-- manual_state : NULL = automatique (date limite), 1 = clos, 0 = ouvert.
CREATE TABLE IF NOT EXISTS test_vma_2026_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    manual_state TINYINT(1) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO test_vma_2026_settings (id, manual_state) VALUES (1, NULL);
