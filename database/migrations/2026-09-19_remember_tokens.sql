-- Migration : connexion mémorisée ("Rester connecté")
--
-- Contexte : la session PHP seule ne suffit pas à garder les adhérents
-- connectés durablement, en particulier sur mobile via un raccourci
-- d'écran d'accueil (le cookie de session expire à la fermeture du
-- navigateur/app). Un cookie dédié, long et pivotant (sélecteur + hash du
-- validateur, jamais le secret en clair en base) permet de reconnecter
-- automatiquement l'adhérent tant qu'il revient dans les 90 jours.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    selector VARCHAR(24) NOT NULL,
    validator_hash VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_member (member_id),
    CONSTRAINT fk_remember_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
