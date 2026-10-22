-- Migration : historique des envois de la newsletter hebdomadaire
--
-- Contexte : la page /newsletter (admin) enregistre chaque envoi (semaine,
-- destinataire, auteur) pour afficher « dernier envoi » à titre d'indication
-- et éviter les doublons du lundi. Aucun blocage n'est appliqué.
--
-- Sans risque à rejouer plusieurs fois (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS newsletter_sends (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_start DATE NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    sent_by INT UNSIGNED NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_newsletter_sends_week (week_start),
    CONSTRAINT fk_newsletter_sends_member
        FOREIGN KEY (sent_by)
        REFERENCES members(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
