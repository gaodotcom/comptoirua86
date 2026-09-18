-- Migration : favicon des courses stocké localement
--
-- Contexte : le favicon du site d'une course était auparavant récupéré en
-- direct depuis DuckDuckGo à chaque affichage (côté navigateur). Il est
-- désormais téléchargé une seule fois, côté serveur, à la création/modification
-- de la course, et stocké dans public/uploads/races/. Ce fichier ajoute la
-- colonne qui référence ce fichier local.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS) : si la colonne existe
-- déjà, la commande ne fait rien.

ALTER TABLE races ADD COLUMN IF NOT EXISTS favicon_path VARCHAR(255) NULL AFTER website_url;
