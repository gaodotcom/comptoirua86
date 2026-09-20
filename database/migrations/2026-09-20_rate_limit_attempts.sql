-- Migration : anti brute-force sur la connexion et le mot de passe oublié
--
-- Contexte : rien n'empêchait jusqu'ici de tenter un nombre illimité de
-- connexions (dangereux vu que les comptes sans mot de passe personnel
-- acceptent la date de naissance) ni de demandes de réinitialisation
-- (spam de l'email d'un adhérent ciblé). Cette table générique enregistre
-- les tentatives par "bucket" (ex: "login:<identifiant>",
-- "password-reset:<email>") pour pouvoir les compter sur une fenêtre de
-- temps glissante.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS rate_limit_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate_limit_bucket (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
