-- Migration : participations au test VMA du vendredi 16 octobre 2026
--
-- Contexte : page ponctuelle et hors menu (/test-vma-2026) où chaque adhérent
-- connecté indique s'il participe au test VMA organisé par Béruges Sport Nature
-- sur la piste du CREPS de Boivre. Une ligne = un participant ; « je ne
-- participe pas » supprime simplement la ligne.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS test_vma_2026_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_vma_2026_member (member_id),
    CONSTRAINT fk_test_vma_2026_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
