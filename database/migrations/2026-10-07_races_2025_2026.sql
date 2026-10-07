-- Courses 2025-2026 (anciennes courses, import du fichier database/courses 2025-2026.csv).
-- Idempotent : une course déjà présente (même nom et même date de début) n'est pas réinsérée.
-- Rattachées au compte « Admin Ultramical86 » (compte générique admin).
SET @author_id = (SELECT id FROM members WHERE role = 'admin' AND generic_account = 1 AND last_name = 'Ultramical86' ORDER BY id LIMIT 1);

INSERT INTO races (title, start_date, end_date, distances, website_url, created_by)
SELECT * FROM (
    SELECT 'Ultra trail du vercors' AS title, CAST('2025-09-06' AS DATE) AS start_date, CAST(NULL AS DATE) AS end_date, '48k / 80k solo ou équipe de 4' AS distances, 'https://www.ultratrailvercors.com/' AS website_url, @author_id AS created_by
    UNION ALL SELECT 'ALEFPA Trail', '2025-09-20', NULL, '6 / 13 / 22 / 32 / 53', 'https://alefpatrail.com/concept/', @author_id
    UNION ALL SELECT 'Utmb Nice', '2025-09-27', NULL, '50k/100k/160k', 'https://nice.utmb.world/fr', @author_id
    UNION ALL SELECT 'Trail estival du Sancy', '2025-09-28', NULL, '10 / 18 / 33 / 61', NULL, @author_id
    UNION ALL SELECT 'Trail Aiguilles Rouges', '2025-09-28', NULL, '52k/16k/9k', 'http://www.aiguillesrouges.fr/en', @author_id
    UNION ALL SELECT 'Grand Raid de la Réunion', '2025-10-16', NULL, '50 / 70 / 109 / 151 / 170', 'https://www.grandraid-reunion.com/fr/', @author_id
    UNION ALL SELECT 'Les Templiers', '2025-10-16', '2025-10-19', '100/80/62/48/34/36/28/23/17/12/8', 'https://www.festivaldestempliers.com/sinscrire/', @author_id
    UNION ALL SELECT '24h de royan', '2025-10-18', NULL, '12 h /24h /48 h', 'https://www.48hderoyan.fr/', @author_id
    UNION ALL SELECT 'Marathon Vert de Rennes', '2025-10-26', NULL, '42,195 km', 'https://www.lemarathonvert.org/', @author_id
    UNION ALL SELECT 'Festival des hospitaliers', '2025-10-25', '2025-10-26', '75 km', 'https://www.festival-des-hospitaliers.com/', @author_id
    UNION ALL SELECT 'Marathon La Rochelle', '2025-11-30', NULL, '42,195 km', NULL, @author_id
    UNION ALL SELECT 'Trail de la Rose', '2025-11-30', NULL, '9, 18, 27, 42 km', 'https://trail-de-la-rose-2025.onsinscrit.com/accueil.php', @author_id
    UNION ALL SELECT 'Hivernale des Templiers', '2025-12-06', '2025-12-07', '11,4-12,9-16,8-26-35-65,95 km', 'https://www.hivernaledestempliers.com', @author_id
    UNION ALL SELECT 'Trail du loup blanc', '2025-12-13', '2025-12-14', '16/25/30/53', 'https://trailduloupblanc.fr/', @author_id
    UNION ALL SELECT 'Hivernale du Sancy', '2026-01-11', NULL, '23/33', 'https://orga.xttr63.com/organisations/trail-hivernal-sancy-mont-dore/presentation-trail-hivernal', @author_id
    UNION ALL SELECT 'Trail Hivernal Les Mathes', '2026-01-11', NULL, '25/50', NULL, @author_id
    UNION ALL SELECT 'Trail de Vulcain', '2026-03-01', NULL, '13/27/48/77', 'https://www.trail-de-vulcain.fr/', @author_id
    UNION ALL SELECT 'Grand Trail des Cadourques', '2026-03-27', '2026-03-29', '15 / 30 / 65 / 120, Rando 12', NULL, @author_id
    UNION ALL SELECT 'Ultra Trail de l''Ile d''Oléron', '2026-04-04', NULL, '25 relais/50 relais/100', 'https://ut-oleron.fr/', @author_id
    UNION ALL SELECT 'Trail du vignoble nantais', '2026-04-11', '2026-04-12', '12/18/24/45/75, 2 defis 42 / 105', 'https://www.trailduvignoblenantais.fr/', @author_id
    UNION ALL SELECT 'Marathon du futuroscope', '2026-04-12', NULL, '42,195 km', NULL, @author_id
    UNION ALL SELECT 'Trail des Monédières', '2026-04-25', NULL, '57km/ 22 ou 30 km', 'https://mmrt.fr', @author_id
    UNION ALL SELECT 'Trail du Mourtis', '2026-05-02', NULL, '48 et 16', 'https://www.traildumourtis.fr', @author_id
    UNION ALL SELECT 'Ardèche Trail - La voie Romaine', '2026-05-02', NULL, '20/41/60', 'https://ardeche-trail-la-voie-romaine.com/', @author_id
    UNION ALL SELECT 'Grand Trail du Périgord', '2026-05-09', NULL, '18/30/46/90 km', NULL, @author_id
    UNION ALL SELECT 'Raidlight festival trail', '2026-05-15', '2026-05-17', 'KV-24-27-33-46', 'https://raidlight-trailfestival.com/', @author_id
    UNION ALL SELECT 'Lozere Trail', '2026-05-23', '2026-05-24', '100/52/27/25/12', 'https://www.lozeretrail.com', @author_id
    UNION ALL SELECT 'Andorra trail', '2026-06-13', NULL, '21-50-80-105', 'https://andorra.utmb.world/races', @author_id
    UNION ALL SELECT 'Trail de la Grande Champagne', '2026-06-20', NULL, '9/15/30/55/85', 'https://www.klikego.com/inscription/trail-de-la-grande-champagne-2026/running-course-a-pied/1666821547546-7', @author_id
    UNION ALL SELECT 'Trail des balcons de Cauterets', '2026-06-27', NULL, '11/20/47/80', 'https://pyreneeschrono.fr/evenement/trail-des-balcons-de-cauterets/', @author_id
    UNION ALL SELECT 'Tour du lac de Vassivière', '2026-06-28', NULL, '23,4 km', 'https://vassiviere-tourdulac.km42enlimousin.fr/', @author_id
    UNION ALL SELECT 'Val D''Aran', '2026-07-01', '2026-07-06', '20k au 100M', 'https://valdaran.utmb.world/fr/', @author_id
    UNION ALL SELECT 'Luchon Aneto Trail', '2026-07-03', '2026-07-05', '10 à 85 kms', 'https://www.luchonanetotrail.fr', @author_id
    UNION ALL SELECT 'Grand Raid Guillestrois-Queyras', '2026-07-03', '2026-07-05', '25/48/65/90/105/170', 'https://grandraidduguillestrois-queyras.com/', @author_id
    UNION ALL SELECT 'La Montagn''hard', '2026-07-04', '2026-07-05', '20/50/70/100', 'https://www.montagnhard.com/', @author_id
    UNION ALL SELECT 'Monte Rosa Walserwaeg', '2026-07-17', NULL, '120/82/43/15', 'https://mrww.utmb.world/fr/races', @author_id
    UNION ALL SELECT 'Ultra tour du Beauforrain', '2026-07-18', NULL, '110 km / 7600 D+', NULL, @author_id
    UNION ALL SELECT 'Challenge du montcalm', '2026-08-13', '2026-08-16', 'KV/8/15/25/40/70/110', NULL, @author_id
    UNION ALL SELECT 'L''Echappée belle', '2026-08-21', '2026-08-23', '42/63/96/152', 'https://www.lechappeebelledonne.com/', @author_id
    UNION ALL SELECT 'Grand raid des Pyrénées', '2026-08-19', '2026-08-23', '40/50/60/80/120/160', 'https://www.grandraidpyrenees.com/fr/', @author_id
    UNION ALL SELECT 'Trace des ducs de Savoie', '2026-08-24', NULL, '145 km', 'https://montblanc.utmb.world/fr/races/TDS', @author_id
) AS new_races
WHERE NOT EXISTS (
    SELECT 1 FROM races r WHERE r.title = new_races.title AND r.start_date = new_races.start_date
);
