# Le Comptoir — Espace adhérents Ultramical86

Application web en PHP + MariaDB, adaptée à un hébergement mutualisé (Ionos,
accès FTP uniquement).

## Choix techniques

- PHP 8.3 procédural (pas de framework) : compatible mutualisé, simple à maintenir.
- Twig 3 pour les templates (seule dépendance de prod, via Composer).
- MariaDB, requêtes préparées PDO.
- Bootstrap 5 + Bootstrap Icons pour l'interface responsive.
- Docker en local : environnement de dev stable sans impacter l'hébergement.

## Fonctionnalités

- **Authentification**
  - identifiant = nom d'utilisateur ou email
  - mot de passe = mot de passe personnel, ou date de naissance si aucun
    mot de passe n'est encore défini (adhérents jamais connectés) ;
    création d'un mot de passe personnel obligatoire à la première connexion
  - mot de passe oublié par email (lien valable 30 minutes)
  - connexion permanente (« se souvenir de moi »)
  - limitation des tentatives : 10 / 15 min, 5 / heure pour les comptes
    encore sur la date de naissance
- **Rôles** : adhérent, coach, bureau, admin.
- **Adhérent**
  - accueil : dernier entraînement, prochains anniversaires, 3 prochains
    événements, actualités, prochaines courses
  - calendrier mensuel (événements UA86, courses, anniversaires)
  - trombinoscope, gestion de son profil et de sa photo
  - courses partagées (inscriptions entre adhérents), agenda UA86, actualités
- **Coach** : publication des entraînements.
- **Bureau** : vue détaillée des adhérents, historique des adhésions.
- **Admin**
  - gestion des adhérents et des adhésions annuelles, saisons actives,
    adhérents désactivés
  - import des adhésions HelloAsso
  - import du calendrier FFA (courses de la Vienne)
  - flux iCal des événements publiés (`/flux-calendrier?token=...`, jeton
    `CALENDAR_FEED_TOKEN` du `.env`) pour s'abonner depuis Google Agenda ;
    l'URL complète est affichée sur la page agenda (bureau et admin)
  - page d'aide (`/aide`)
- **Pages ponctuelles** (hors menu, réservées aux adhérents de la saison
  2026-2027) : préinscriptions au week-end club Lozère Trail 2027
  (voir [md/WEEKEND_CLUB_2027.md](md/WEEKEND_CLUB_2027.md)) et test VMA 2026
  (voir [md/TEST_VMA_2026.md](md/TEST_VMA_2026.md)).

## Structure

- `index.php` : contrôleur frontal (toutes les URLs passent par lui)
- `app/routes.php` : correspondance route ↔ slug d'URL
- `app/render.php` : sous-dossier de contrôleur/template de chaque page
- `app/auth.php` : authentification, sessions, permissions
  (`page_access_level()`), mots de passe, limitation des tentatives
- `app/bootstrap.php` : config, PDO, session, CSRF, messages flash
- `app/twig.php` : environnement Twig et fonctions exposées aux templates
- `app/*.php` : logique métier par domaine (`members`, `trainings`,
  `events`, `news`, `races`, `local_races`, `calendar`, `helloasso`,
  `email`, `uploads`, `helpers`, `weekend_club_2027`, `test_vma_2026`…)
- `controllers/<domaine>/<page>.php` : un contrôleur par page
- `templates/` : `layout.twig`, `pages/<domaine>/`, `components/`
- `public/` : CSS, JS, images, `uploads/` (fichiers des adhérents)
- `database/schema.sql` : création complète de la base + compte admin initial
- `database/migrations/` : scripts SQL datés pour les bases déjà déployées
- `database/requetes/` : requêtes SQL d'extraction (lecture seule) à lancer
  dans phpMyAdmin, ex. adhérents non renouvelés
- `md/` : documentation fonctionnelle et dette technique

Une nouvelle page se déclare à trois endroits : `app/routes.php` (slug),
`app/render.php` (sous-dossier) et `page_access_level()` dans
`app/auth.php` si elle n'est pas réservée aux adhérents connectés.
Pour réserver une page aux adhérents d'une saison, appeler
`require_school_year_membership($user, '2026-2027', [...])` en tête du
contrôleur (affiche la page générique « adhésion requise » sinon ; voir
`require_weekend_2027_membership()` pour un exemple).

## Lancement local avec Docker

1. Copier la configuration : `cp .env.example .env`
2. Lancer les conteneurs : `docker compose up --build`
3. Ouvrir :
   - application : http://localhost:8080
   - phpMyAdmin : http://localhost:8081
   - MailHog (emails de test) : http://localhost:8082
   - MariaDB depuis l'hôte : port 3311

### Qualité du code

```bash
docker exec comptoir-app-1 php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
docker exec comptoir-app-1 php vendor/bin/phpcs
docker exec comptoir-app-1 php vendor/bin/phpcbf
```

## Compte administrateur initial

Le schéma crée :

- identifiant : `admin`
- email : `admin@example.org`
- date de naissance : `1970-01-01`

Aucun mot de passe n'étant défini, se connecter avec la date de naissance
(`01011970` ou `1970-01-01`) : l'application demande ensuite un mot de
passe personnel.

## Déploiement (mutualisé, FTP)

- Nouvelle installation : importer `database/schema.sql`. Base existante :
  passer dans phpMyAdmin les fichiers de `database/migrations/` pas encore
  appliqués (ils sont idempotents).
- Envoyer les fichiers modifiés par FTP.
- `vendor/` : **ne pas envoyer le vendor local** (il contient PHPStan et
  PHPCS, et son autoloader charge un fichier de PHPStan : le site plante
  s'il manque). Quand `composer.json`/`composer.lock` changent, générer un
  vendor de prod sans les outils de dev dans `deploy/vendor-prod` (skill
  Claude `vendor-prod`, ou `composer install --no-dev --optimize-autoloader`).
- Configurer `.env` sur le serveur et vérifier les droits en écriture sur
  `public/uploads/`.

## Sécurité

- `.htaccess` racine en liste blanche : seuls `index.php`, `public/` et
  quelques fichiers publics sont servis ; `.env`, `.git`, `app/`,
  `vendor/`, `database/`, `deploy/`… sont inaccessibles.
- Mots de passe hachés (`password_hash` / `password_verify`).
- Jeton CSRF sur tous les formulaires.
- Contrôle des rôles côté serveur (`page_access_level()` + vérifications
  dans les contrôleurs).
- Limitation des tentatives (connexion, mot de passe oublié).
- Validation des uploads d'images (type + taille, 5 Mo max).
- Requêtes SQL préparées (PDO), échappement automatique Twig.
- Identifiants (base, SMTP, HelloAsso) non exposés aux templates.
- Emails encodés en base64 (UTF-8, pas de corruption des lignes longues).
