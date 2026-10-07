-- Adhérents 2025-2026 qui n'ont pas renouvelé en 2026-2027.
-- Colonne "connecte" : 'oui' si un mot de passe personnel est défini
-- (password_hash non NULL), donc si la personne s'est connectée au Comptoir.
-- Les adhérents supprimés et les comptes génériques sont exclus.
SELECT m.last_name  AS nom,
       m.first_name AS prenom,
       m.email,
       IF(m.password_hash IS NULL, 'non', 'oui') AS connecte
FROM members m
WHERE m.deleted_at IS NULL
  AND m.generic_account = 0
  AND EXISTS (SELECT 1 FROM memberships ms
              WHERE ms.member_id = m.id AND ms.school_year = '2025-2026')
  AND NOT EXISTS (SELECT 1 FROM memberships ms
                  WHERE ms.member_id = m.id AND ms.school_year = '2026-2027')
ORDER BY m.last_name, m.first_name;
