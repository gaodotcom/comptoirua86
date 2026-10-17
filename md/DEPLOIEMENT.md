# Déploiement en prod (Ionos UA86)

Alias SSH `Ionos-UA86` (voir `~/.ssh/config`), dossier distant `~/comptoir`.
On n'envoie que les fichiers modifiés non commités, depuis la racine du projet.

## Simulation (dry-run)

```bash
git diff --name-only | rsync -avzn --itemize-changes \
  --exclude=md/ --exclude=deploy/ --exclude=docker/ --exclude=.claude/ --exclude=.vscode/ --exclude=CLAUDE.md --exclude=README.md \
  --files-from=- ./ Ionos-UA86:~/comptoir/
```

## Envoi réel

```bash
git diff --name-only | rsync -avz \
  --exclude=md/ --exclude=deploy/ --exclude=docker/ --exclude=.claude/ --exclude=.vscode/ --exclude=CLAUDE.md --exclude=README.md \
  --files-from=- ./ Ionos-UA86:~/comptoir/
```

## Script

`~/bin/deployComptoir` (hors dépôt) fait la même chose, en incluant aussi les nouveaux fichiers non suivis :

- `deployComptoir` : simulation
- `deployComptoir -y` (ou `--go`) : envoi réel

## Récupérer les uploads de la prod

`public/uploads/` n'est pas versionné (images des adhérents, pictos des courses, etc.). Pour le rapatrier en local :

`~/bin/pullUploadsComptoir` (hors dépôt) :

- `pullUploadsComptoir` : simulation
- `pullUploadsComptoir -y` (ou `--go`) : copie réelle de `Ionos-UA86:~/comptoir/public/uploads/` vers `public/uploads/`

Aucune suppression en local, et un fichier local plus récent n'est pas écrasé. Une erreur rsync « failed to set times » sur `uploads/races` est sans gravité (dossier créé par Docker en `www-data` ; les pictos sont régénérés à la modification d'une course).

## À savoir

- `md/`, `deploy/`, `docker/`, `.claude/`, `.vscode/`, `CLAUDE.md` et `README.md` sont exclus : inutiles en prod.
- Les fichiers supprimés en local ne sont pas supprimés en prod.
- Si `composer.json` ou `composer.lock` change : régénérer le `vendor/` de prod (skill `vendor-prod`) et l'envoyer à part.
- Migrations SQL : à passer dans phpMyAdmin.
- Si la clé `~/.ssh/id_ionos` est refusée : `ssh-copy-id -i ~/.ssh/id_ionos.pub Ionos-UA86`.
