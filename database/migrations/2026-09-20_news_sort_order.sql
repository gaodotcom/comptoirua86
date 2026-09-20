-- Migration : ordre d'affichage manuel des actualités
--
-- Contexte : les admins veulent pouvoir réordonner les actualités
-- (boutons haut/bas) plutôt que de subir le tri automatique par date de
-- création. La colonne sort_order pilote désormais l'affichage, aussi
-- bien sur la page /news que sur les dernières actualités de l'accueil.
--
-- Le backfill préserve l'ordre actuel (created_at DESC, le plus récent en
-- premier) pour qu'aucune actualité ne bouge visuellement tant qu'un admin
-- n'a pas cliqué sur une flèche.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS) : si la colonne
-- existe déjà, seul l'ALTER TABLE est ignoré ; le backfill peut être
-- rejoué sans dégât (il ne fait que renuméroter dans le même ordre).

ALTER TABLE news ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER published;

SET @rownum := 0;
UPDATE news SET sort_order = (@rownum := @rownum + 1) ORDER BY created_at DESC, id DESC;
