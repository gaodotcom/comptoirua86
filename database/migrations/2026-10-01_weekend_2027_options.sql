-- Migration : options payantes supplémentaires du week-end club Lozère Trail 2027
--
-- Contexte : ajout de deux options à la préinscription :
-- - sunday_lunch : repas du dimanche midi (12 €), réservé aux coureurs du
--   Salta Bartas (12 km) ou de la Skyrace sans course le dimanche ;
-- - cancellation_insurance : assurance annulation (10% du prix de
--   l'inscription), proposée à tous quelle que soit la course choisie.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

ALTER TABLE weekend_2027_registrations ADD COLUMN IF NOT EXISTS sunday_lunch TINYINT(1) NOT NULL DEFAULT 0 AFTER bivouac;
ALTER TABLE weekend_2027_registrations ADD COLUMN IF NOT EXISTS cancellation_insurance TINYINT(1) NOT NULL DEFAULT 0 AFTER sunday_lunch;
