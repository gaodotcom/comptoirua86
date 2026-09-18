# Espace Adhérents (Association de course)

Application web simple en PHP pur + MariaDB, adaptee a un hebergement mutualise.

## Pourquoi ce choix technique

- PHP pur: tres compatible avec les serveurs mutualises et simple a maintenir.
- MariaDB: robuste et largement disponible en mutualise.
- Bootstrap: interface responsive rapidement exploitable.
- Docker en local: environnement de dev stable sans impacter l hebergement final.

## Fonctionnalites implantees

- Authentification:
  - identifiant = nom d utilisateur ou email
  - mot de passe = soit mot de passe personnel, soit date de naissance (si aucun mot de passe defini)
  - obligation de definir un mot de passe personnel apres connexion via date de naissance
  - recuperation de mot de passe oublie par email (lien valide 30 minutes)
  - gestion du nom d utilisateur depuis la page profil (optionnel, 3-30 caracteres)
- Roles:
  - adherent
  - coach
  - bureau
  - admin
- Adherent:
  - page d accueil avec les 3 dernieres semaines d entrainement
  - lien vers trombinoscope
  - 10 prochains anniversaires
  - calendrier mensuel (evenements UA86, courses, anniversaires), avec detail du jour en modale
  - liens Facebook / Instagram
  - trombinoscope (photo, nom, prenom)
  - gestion de sa propre photo
- Coach:
  - publication hebdomadaire d entrainement (titre, date, texte, image, commentaire optionnel)
- Bureau:
  - vue detaillee des adherents
  - historique des adhesions (annee scolaire, tarif, don)
- Administrateur:
  - ajout / modification / suppression d adherents
  - ajout / mise a jour d adhesions annuelles

## Structure

- `index.php`: routeur principal
- `app/bootstrap.php`: config, PDO, session, CSRF, flash, helpers globaux
- `app/auth.php`: authentification, session, permissions, mot de passe, reset tokens
- `app/email.php`: envoi d emails (oubli de mot de passe)
- `app/members.php`: CRUD adhérents, trombinoscope, anniversaires, adhésions
- `app/trainings.php`: CRUD entraînements
- `app/events.php`: CRUD événements
- `app/calendar.php`: agrégation calendrier (événements, courses, anniversaires)
- `app/news.php`: CRUD actualités
- `app/uploads.php`: upload d'images, dossiers
- `app/helpers.php`: URL, dates, avatar, formatage
- `app/render.php`: rendu des pages et composants
- `app/config.php`: configuration application + BDD
- `database/schema.sql`: création tables + compte admin initial
- `docker-compose.yml`: stack locale
- `docker/php/Dockerfile`: image PHP/Apache locale

## Lancement local avec Docker

1. Copier la configuration:

   cp .env.example .env

2. Lancer les conteneurs:

   docker compose up --build

3. Ouvrir:

- Application: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- MailHog (tests email): http://localhost:8082

## Compte administrateur initial

Le schema cree automatiquement:

- identifiant: `admin`
- email: `admin@example.org`
- date de naissance: `1970-01-01`

Comme aucun mot de passe n est defini au depart, vous pouvez vous connecter avec la date de naissance en mot de passe:

- `01011970` (ou `1970-01-01`)

Puis l application forcera la creation d un mot de passe personnel.

## Deploiement mutualise

- Importer `database/schema.sql` dans votre base MariaDB.
- Copier les fichiers de l application sur l hebergement.
- Adapter les variables DB dans `.env` (ou directement via variables serveur).
- Verifier les droits en ecriture sur `public/uploads/`.

## Points de securite deja inclus

- Hash de mot de passe (`password_hash` / `password_verify`)
- Verification CSRF sur formulaires sensibles
- Controle strict des roles
- Validation des uploads image (type + taille)
- Requetes SQL preparees (PDO)

## Evolutions conseillees (prochaine iteration)

- pagination + recherche adherents
- journal d activite admin
- sauvegarde automatique de la BDD
- compression/redimensionnement des photos
