---
name: vendor-prod
description: Génère le dossier vendor/ de production (sans les outils de dev) à envoyer par FTP sur l'hébergement Ionos du Comptoir, qui n'a ni SSH ni composer. À utiliser dès que composer.json ou composer.lock change (ajout, mise à jour ou suppression d'une dépendance), ou quand l'utilisateur demande le vendor de prod, comment mettre à jour une librairie PHP en prod, ou quoi envoyer après un `composer update`.
---

# vendor/ de production

La prod (Ionos mutualisé) n'a que le FTP : pas de `composer install` possible
sur le serveur, il faut envoyer un `vendor/` tout prêt. L'utilisateur gère ses
déploiements lui-même, au fur et à mesure : ne pas proposer de script ni de
procédure de déploiement globale, seulement ce vendor/.

Ne jamais faire envoyer le `vendor/` local : il contient PHPStan et PHPCS, et
son autoloader charge `phpstan/phpstan/bootstrap.php` à chaque requête. Si ce
fichier manque en prod, tout le site tombe en erreur.

## Générer

Le conteneur `comptoir-app-1` doit tourner (`docker compose up -d app db`).
Depuis la racine du projet :

```bash
OUT=deploy/vendor-prod && rm -rf "$OUT" && mkdir -p deploy \
&& docker exec comptoir-app-1 sh -c 'rm -rf /tmp/vendor-prod && mkdir /tmp/vendor-prod && cp /var/www/html/composer.json /var/www/html/composer.lock /tmp/vendor-prod/ && cd /tmp/vendor-prod && composer install --no-dev --optimize-autoloader --no-interaction --quiet' \
&& docker cp comptoir-app-1:/tmp/vendor-prod/vendor "$OUT" \
&& docker exec comptoir-app-1 rm -rf /tmp/vendor-prod
```

`deploy/` est ignoré par git et bloqué par le `.htaccess`. Éviter le
scratchpad `/tmp` : il disparaît au redémarrage de WSL avant que l'utilisateur
ait eu le temps de déployer.

## Vérifier

- `deploy/vendor-prod/composer/autoload_files.php` ne doit pas mentionner `phpstan`.
- Charger l'autoloader et instancier Twig (copier le dossier dans le conteneur
  puis `php -r 'require ".../autoload.php"; echo \Twig\Environment::VERSION;'`).

## Donner à l'utilisateur

- Le chemin Windows :
  `\\wsl.localhost\Debian\home\loicgao\sources\ua86\comptoir\deploy\vendor-prod`
- L'échange sans coupure longue : envoyer sous le nom `vendor_new`, renommer
  `vendor` → `vendor_old` puis `vendor_new` → `vendor`, vérifier le site,
  supprimer `vendor_old` (ou renommer dans l'autre sens en cas de problème).
- `composer.lock` n'a pas besoin d'aller en prod.
