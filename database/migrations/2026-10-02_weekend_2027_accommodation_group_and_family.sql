-- Migration : nouvelle option d'hébergement du week-end club Lozère Trail 2027
--
-- Contexte : ajout de "Avec le groupe et ma famille" (réservation gérée par
-- l'adhérent lui-même, mais en restant proche du groupe réservé par
-- Ultramical86), en plus des options existantes "Avec le groupe" et
-- "Indépendant".
--
-- Sans risque à rejouer plusieurs fois (même définition à chaque exécution).

ALTER TABLE weekend_2027_registrations
    MODIFY COLUMN accommodation ENUM('group', 'group_and_family', 'independent') NOT NULL;
