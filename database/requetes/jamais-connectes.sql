-- Adhérents qui ne se sont jamais connectés au Comptoir (aucun mot de passe
-- personnel défini : password_hash NULL). Comptes génériques et adhérents
-- supprimés exclus. "derniere_saison" = dernière adhésion enregistrée.
SELECT m.last_name  AS nom,
       m.first_name AS prenom,
       m.email,
       (SELECT MAX(ms.school_year) FROM memberships ms WHERE ms.member_id = m.id) AS derniere_saison
FROM members m
WHERE m.deleted_at IS NULL
  AND m.generic_account = 0
  AND m.password_hash IS NULL
ORDER BY derniere_saison DESC, m.last_name, m.first_name;
