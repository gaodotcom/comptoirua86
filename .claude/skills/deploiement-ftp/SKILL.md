---
name: deploiement-ftp
description: Prépare la mise en production du Comptoir sur l'hébergement Ionos mutualisé, accessible uniquement en FTP (pas de SSH, donc pas de composer ni de git côté serveur). Génère le paquet des fichiers à envoyer, le vendor/ de prod sans outils de dev, la liste des migrations SQL à passer dans phpMyAdmin, les variables .env à reporter, les fichiers à supprimer, et la checklist de vérification après envoi. À utiliser dès que l'utilisateur parle de déployer, mettre en prod, mettre en ligne, envoyer en FTP, « qu'est-ce que je dois envoyer / copier sur le serveur », mettre à jour le vendor ou une dépendance composer en prod, ou passer une migration en prod — même s'il ne prononce pas le mot « déploiement ».
---

# Déploiement FTP du Comptoir (Ionos)

La prod n'a ni SSH ni composer : tout part par FTP, les migrations passent à la
main dans phpMyAdmin, et le `.env` du serveur est édité à la main. Une erreur
(fichier oublié, vendor de dev, migration non passée) casse le site sans
qu'on puisse « relancer une commande » côté serveur : d'où ce paquet préparé
et vérifié en local avant chaque envoi.

Claude n'a pas accès à la prod (lectures bloquées) : toutes les vérifications
côté serveur sont faites par l'utilisateur, à qui on donne une checklist précise.

## 1. Trouver la référence du dernier déploiement

Par défaut, le tag git `prod`. S'il n'existe pas (`git tag -l prod` vide),
demander à l'utilisateur la date ou le commit du dernier envoi (s'aider de
`git log --oneline`), plutôt que de deviner : une référence trop récente fait
oublier des fichiers, une trop ancienne renvoie des fichiers inutilement et
surtout réexécute des migrations.

## 2. Générer le paquet

```bash
.claude/skills/deploiement-ftp/scripts/prepare-deploy.sh [REF]   # REF défaut : prod
```

Le conteneur `comptoir-app-1` doit tourner (PHPStan, PHPCS et le vendor de prod
s'y exécutent). Le script compare l'état **du disque** (commité ou non) à la
référence et produit `deploy/<horodatage>/` (ignoré par git) :

- `files/` : fichiers à envoyer, dans leur arborescence ; `files/vendor/` si
  `composer.lock` a changé (généré avec `--no-dev`) ;
- `migrations/` : scripts SQL à exécuter, dans l'ordre ;
- `RAPPORT.txt` : récapitulatif, fichiers à supprimer, clés `.env`, vérifications.

Seuls `index.php`, `.htaccess`, `favicon.ico`, `robots.txt`, `logo-blanc.png`,
`app/`, `controllers/`, `templates/` et `public/` (hors `public/uploads/`) sont
déployables. Le reste (md/, database/, docker/, outils de dev, .claude/...)
ne doit jamais partir sur le serveur.

## 3. Relire le rapport avec jugement

Le script est mécanique ; vérifier en plus ce qu'il ne peut pas savoir :

- **Vérifications en échec** (php -l, PHPStan, PHPCS) : ne pas proposer
  d'envoyer avant correction, ou le signaler clairement.
- **Modifications non commitées** : les signaler ; l'utilisateur commite
  lui-même (ne jamais commiter à sa place).
- **Migrations** : relire chaque script. Elles doivent être rejouables sans
  risque (`IF NOT EXISTS`, `MODIFY` idempotent...). Si le nouveau code lit une
  colonne ajoutée par une migration, la migration passe **avant** l'envoi des
  fichiers, sinon le site plante entre les deux.
- **`.env`** : `.env_Ionos` (copie locale du `.env` de prod) n'est pas suivi par
  git, le script ne voit donc que les clés manquantes, pas les valeurs
  modifiées. Chercher dans la conversation ou les commits si une valeur a changé
  (ex: `UPLOAD_MAX_SIZE`) et la lister. Le `.env` local ne part jamais en prod.
- **vendor/** : jamais le `vendor/` local, qui contient PHPStan/PHPCS et dont
  l'autoloader charge `phpstan/bootstrap.php` à chaque requête (site entier en
  erreur si le fichier manque en prod). Uniquement `files/vendor/`.
- **Nouvelle page** : elle doit être déclarée dans `app/routes.php`,
  `page_subdir()` de `app/render.php` et, si besoin d'un niveau d'accès
  particulier, `page_access_level()` de `app/auth.php`.
- CSS/JS : `asset_url()` versionne les URLs par date de fichier, et le cache
  Twig est désactivé : rien à vider après l'envoi.

## 4. Présenter le plan à l'utilisateur

Dans cet ordre, en ne gardant que les étapes utiles à ce déploiement :

1. **Migrations** : exécuter les fichiers de `migrations/` dans phpMyAdmin
   (Ionos), dans l'ordre.
2. **`.env` du serveur** : clés à ajouter ou valeurs à modifier, une par ligne.
3. **Envoi FTP** du contenu de `files/` à la racine du site, en écrasant.
   Si `files/vendor/` existe : l'envoyer sous le nom `vendor_new`, puis renommer
   `vendor` → `vendor_old` et `vendor_new` → `vendor` (coupure de quelques
   secondes au lieu de toute la durée du transfert) ; supprimer `vendor_old`
   une fois le site vérifié, ou renommer dans l'autre sens en cas de problème.
4. **Fichiers à supprimer** sur le serveur, s'il y en a.
5. **Vérifications après envoi**, adaptées à ce qui a changé :
   - toujours : page de connexion, accueil connecté, une page modifiée ;
   - `.htaccess` modifié : `https://<site>/.env` doit répondre 403 ou 404 ;
   - `app/email.php` modifié : se faire envoyer un email (mot de passe oublié) ;
   - migration passée : la page qui utilise les nouvelles colonnes ;
   - vendor/ remplacé : n'importe quelle page (Twig est chargé partout).

Rester concis : l'utilisateur va suivre la liste en faisant ses transferts.

## 5. Après un déploiement réussi

Proposer de poser le tag de référence pour la prochaine fois, sur le commit
effectivement envoyé :

```bash
git tag -f prod <commit>
```

Ne pas le poser sans accord : si l'envoi contenait des modifications non
commitées, il faut d'abord que l'utilisateur les commite, sinon la prochaine
comparaison serait fausse. Le dossier `deploy/` peut ensuite être supprimé.
