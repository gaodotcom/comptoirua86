-- Migration : mémorise aussi le refus « je ne participe pas » au test VMA
--
-- Contexte : jusqu'ici une ligne = un participant et un refus supprimait la
-- ligne. On garde désormais la réponse (participates = 1 ou 0) pour distinguer
-- « a répondu non » de « n'a pas encore répondu ». Les lignes existantes sont
-- des participants (valeur par défaut 1).
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

ALTER TABLE test_vma_2026_participants
    ADD COLUMN IF NOT EXISTS participates TINYINT(1) NOT NULL DEFAULT 1 AFTER member_id;
