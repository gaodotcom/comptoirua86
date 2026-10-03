#!/usr/bin/env bash
# Prépare un paquet de déploiement FTP pour la prod Ionos (pas de SSH).
#
# Usage : prepare-deploy.sh [REF_DERNIER_DEPLOIEMENT]   (défaut : tag "prod")
#
# Produit deploy/<horodatage>/ (ignoré par git) contenant :
#   files/       les fichiers à envoyer, dans leur arborescence (vendor/ inclus si besoin)
#   migrations/  les scripts SQL à exécuter dans phpMyAdmin, dans l'ordre
#   RAPPORT.txt  le récapitulatif (fichiers supprimés, .env, vérifications)
# Compare l'état actuel du disque (commité ou non) à la référence donnée.

set -uo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

APP_CONTAINER="comptoir-app-1"
BASE="${1:-prod}"

if ! git rev-parse --verify --quiet "$BASE^{commit}" >/dev/null; then
    echo "ERREUR : référence '$BASE' introuvable." >&2
    echo "Indique le commit du dernier déploiement (ex: prepare-deploy.sh 30e0096)," >&2
    echo "ou pose le tag après un envoi réussi : git tag -f prod <commit>" >&2
    exit 2
fi

# Seuls ces chemins vivent en prod. Le reste (md/, database/, docker/, outils
# de dev, .claude/...) ne doit jamais partir sur le serveur.
is_deployable() {
    case "$1" in
        public/uploads/.htaccess) return 0 ;;
        public/uploads/*) return 1 ;;
        index.php|.htaccess|favicon.ico|robots.txt|logo-blanc.png) return 0 ;;
        app/*|controllers/*|templates/*|public/*) return 0 ;;
        *) return 1 ;;
    esac
}

OUT="deploy/$(date +%Y-%m-%d_%H%M)"
rm -rf "$OUT"
mkdir -p "$OUT/files" "$OUT/migrations"
REPORT="$OUT/RAPPORT.txt"
: > "$REPORT"
say() { echo "$*" | tee -a "$REPORT"; }

say "Déploiement préparé le $(date '+%d/%m/%Y %H:%M')"
say "Référence du dernier déploiement : $BASE ($(git rev-parse --short "$BASE"))"
say "Commit actuel : $(git rev-parse --short HEAD)"
if [ -n "$(git status --porcelain)" ]; then
    say "ATTENTION : modifications non commitées, elles sont incluses dans le paquet."
fi
say ""

# Changements depuis la référence : commités + non commités + nouveaux fichiers.
CHANGES="$( { git diff --name-status --no-renames "$BASE"; git ls-files --others --exclude-standard | sed 's/^/A\t/'; } | sort -u -k2)"

say "== Fichiers à envoyer (files/) =="
sent=0
while IFS=$'\t' read -r status path; do
    [ -z "${path:-}" ] && continue
    [ "$status" = "D" ] && continue
    is_deployable "$path" || continue
    mkdir -p "$OUT/files/$(dirname "$path")"
    cp -p "$path" "$OUT/files/$path"
    say "  $status  $path"
    sent=$((sent + 1))
done <<< "$CHANGES"
[ "$sent" -eq 0 ] && say "  (aucun)"
say ""

say "== Fichiers à SUPPRIMER sur le serveur =="
deleted=0
while IFS=$'\t' read -r status path; do
    [ "${status:-}" = "D" ] || continue
    is_deployable "$path" || continue
    say "  $path"
    deleted=$((deleted + 1))
done <<< "$CHANGES"
[ "$deleted" -eq 0 ] && say "  (aucun)"
say ""

say "== Migrations SQL à exécuter dans phpMyAdmin (migrations/, dans cet ordre) =="
migs=0
while IFS=$'\t' read -r status path; do
    case "$path" in database/migrations/*.sql) ;; *) continue ;; esac
    [ "$status" = "D" ] && continue
    cp -p "$path" "$OUT/migrations/"
    say "  $(basename "$path")"
    migs=$((migs + 1))
done <<< "$(echo "$CHANGES" | sort -k2)"
[ "$migs" -eq 0 ] && say "  (aucune)"
say ""

say "== vendor/ =="
if echo "$CHANGES" | grep -qP '\tcomposer\.(lock|json)$'; then
    say "  composer.lock modifié : vendor/ de prod (sans outils de dev) généré dans files/vendor/"
    docker exec "$APP_CONTAINER" sh -c 'rm -rf /tmp/vendor-prod && mkdir /tmp/vendor-prod && cp /var/www/html/composer.json /var/www/html/composer.lock /tmp/vendor-prod/ && cd /tmp/vendor-prod && composer install --no-dev --optimize-autoloader --no-interaction --quiet' \
        && docker cp "$APP_CONTAINER:/tmp/vendor-prod/vendor" "$OUT/files/vendor" \
        && docker exec "$APP_CONTAINER" rm -rf /tmp/vendor-prod \
        || say "  ERREUR : génération du vendor de prod impossible (conteneur $APP_CONTAINER démarré ?)"
    if grep -q phpstan "$OUT/files/vendor/composer/autoload_files.php" 2>/dev/null; then
        say "  ERREUR : le vendor généré référence encore PHPStan, ne pas l'envoyer."
    fi
else
    say "  inchangé, rien à envoyer"
fi
say ""

say "== Variables .env : clés de .env.example absentes de .env_Ionos =="
if [ -f .env_Ionos ]; then
    missing="$(comm -23 <(grep -oE '^[A-Z_]+=' .env.example | sort -u) <(grep -oE '^[A-Z_]+=' .env_Ionos | sort -u) | tr -d '=')"
    if [ -n "$missing" ]; then
        echo "$missing" | sed 's/^/  /' | tee -a "$REPORT"
    else
        say "  aucune"
    fi
else
    say "  .env_Ionos introuvable, vérification impossible"
fi
if echo "$CHANGES" | grep -qP '\t\.env\.example$'; then
    say "  .env.example a changé depuis le dernier déploiement : vérifier les valeurs à reporter dans le .env du serveur"
fi
say ""

say "== Vérifications =="
php_files="$(echo "$CHANGES" | awk -F'\t' '$1 != "D" && $2 ~ /\.php$/ {print $2}')"
lint_ko=0
for f in $php_files; do
    [ -f "$f" ] || continue
    php -l "$f" >/dev/null 2>&1 || { say "  php -l ÉCHEC : $f"; lint_ko=1; }
done
[ "$lint_ko" -eq 0 ] && say "  php -l : OK"

if docker exec "$APP_CONTAINER" true 2>/dev/null; then
    if docker exec "$APP_CONTAINER" php vendor/bin/phpstan analyse --no-progress --memory-limit=1G >/dev/null 2>&1; then
        say "  PHPStan : OK"
    else
        say "  PHPStan : ERREURS (lancer : docker exec $APP_CONTAINER php vendor/bin/phpstan analyse)"
    fi
    phpcs_errors="$(docker exec "$APP_CONTAINER" php vendor/bin/phpcs --report=summary 2>/dev/null | grep -oE 'TOTAL OF [0-9]+ ERROR' | grep -oE '[0-9]+')"
    say "  PHPCS : ${phpcs_errors:-0} erreur(s)"
else
    say "  PHPStan / PHPCS : non lancés (conteneur $APP_CONTAINER arrêté)"
fi

say ""
say "Paquet prêt : $OUT"
