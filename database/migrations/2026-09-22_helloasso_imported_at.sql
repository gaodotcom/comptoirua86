-- Migration : date réelle d'adhésion HelloAsso vs date d'import
--
-- Contexte : lors d'un import HelloAsso, created_at (members et memberships)
-- était jusqu'ici la date à laquelle l'import était exécuté, pas la date
-- réelle de la commande HelloAsso (donc de l'adhésion). On sépare désormais
-- les deux notions : created_at = date de la commande HelloAsso (ou date de
-- création manuelle pour les fiches non importées), imported_at = date à
-- laquelle l'import a effectivement eu lieu.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS) : si la colonne
-- existe déjà, la commande ne fait rien.
--
-- À exécuter avant scripts/backfill-helloasso-dates.php.

ALTER TABLE members ADD COLUMN IF NOT EXISTS imported_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
ALTER TABLE memberships ADD COLUMN IF NOT EXISTS imported_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;
