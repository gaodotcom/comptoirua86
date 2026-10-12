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

## À savoir

- `md/`, `deploy/`, `docker/`, `.claude/`, `.vscode/`, `CLAUDE.md` et `README.md` sont exclus : inutiles en prod.
- Les fichiers supprimés en local ne sont pas supprimés en prod.
- Si `composer.json` ou `composer.lock` change : régénérer le `vendor/` de prod (skill `vendor-prod`) et l'envoyer à part.
- Migrations SQL : à passer dans phpMyAdmin.
- Si la clé `~/.ssh/id_ionos` est refusée : `ssh-copy-id -i ~/.ssh/id_ionos.pub Ionos-UA86`.
