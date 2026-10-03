# Test VMA 2026 — Participations

## Objectif

Page ponctuelle et hors menu pour savoir quels adhérents participent au
test VMA du **vendredi 16 octobre 2026**, proposé par l'association
Béruges Sport Nature sur la piste du CREPS de Boivre. Chaque adhérent
répond simplement « je participe » ou « je ne participe pas » : pas
d'autre information demandée, pas de paiement.

À supprimer (pages, routes, tables, logos) une fois le test passé.

## Accès

- `/test-vma-2026` : l'adhérent connecté donne (ou change) sa réponse
  tant que le formulaire est ouvert.
- `/test-vma-2026-inscrits` : liste des participants (avatar, nom,
  prénom), du dernier inscrit au premier, la sienne en tête. Coordonnées,
  export CSV (nom, prénom, email, téléphone) et clôture/réouverture
  réservés aux admins, vérifiés côté serveur dans le contrôleur.

Les deux pages ne sont pas dans le menu (URL directe uniquement) et
exigent une **adhésion 2026-2027** (`require_test_vma_2026_membership()`,
constante `TEST_VMA_2026_REQUIRED_SCHOOL_YEAR`) : sinon, réponse 403 avec
la page explicative `/test-vma-2026-adhesion`. Les rôles
coach/bureau/admin et les comptes génériques sont exemptés.

## Structure technique

| Rôle | Fichier |
|---|---|
| Logique métier (réponses, clôture, contrôle d'adhésion) | `app/test_vma_2026.php` |
| Contrôleur formulaire | `controllers/test-vma-2026/test-vma-2026.php` |
| Contrôleur liste des participants (export/clôture admin) | `controllers/test-vma-2026/test-vma-2026-inscrits.php` |
| Contrôleur accès refusé (pas d'adhésion 2026-2027) | `controllers/test-vma-2026/test-vma-2026-adhesion.php` |
| Vues | `templates/pages/test-vma-2026/*.twig` |
| Logos (CREPS, Béruges Sport Nature + blason UA86) | `public/img/vma-creps-logo.png`, `public/img/vma-bsn-logo.jpg`, classe `.vma-logo` dans `public/css/theme.css` |
| Schéma (nouvelles installations) | `database/schema.sql` |
| Migrations (bases déjà déployées) | `database/migrations/2026-09-29_test_vma_2026.sql`, `2026-09-29_test_vma_2026_answer.sql`, `2026-09-30_test_vma_2026_settings.sql` |

### Tables

- `test_vma_2026_participants` : une ligne par adhérent ayant répondu
  (clé unique sur `member_id`). `participates` = 1 (participe) ou 0 (a
  répondu non) ; pas de ligne = pas encore répondu. `created_at` n'est
  mis à jour que quand la réponse change : il sert à ordonner la liste.
- `test_vma_2026_settings` : ligne unique (id=1), `manual_state` = NULL
  (clôture automatique), 1 (clos par un admin) ou 0 (rouvert par un admin).

## Clôture

Automatique le **12/10/2026 à 0h** (heure de Paris), calculée dans
`test_vma_2026_is_closed()`. Un admin peut forcer la clôture ou la
réouverture depuis la liste des participants ; ce choix manuel prime
alors sur la date. Une fois clos, le formulaire affiche la réponse sans
permettre de la modifier, et une réponse postée quand même est refusée
côté serveur.
