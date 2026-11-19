#!/usr/bin/env bash
# Laeuft auf dem Webhosting. Wird von der GitHub-Pipeline aufgerufen:
#   server-update.sh <basis> <release> [php] [commit]
set -euo pipefail

BASIS="$1"
RELEASE="$2"
PHP="${3:-php}"
COMMIT="${4:-}"
NEU="$BASIS/releases/$RELEASE"

cd "$BASIS"
mkdir -p shared/storage/app/private shared/storage/app/public shared/storage/framework/cache/data \
         shared/storage/framework/sessions shared/storage/framework/views shared/storage/logs

if [ ! -f shared/.env ]; then
    echo "FEHLER: $BASIS/shared/.env fehlt. Siehe DEPLOY.md." >&2
    rm -rf "$NEU"
    exit 1
fi

# Webzugriff: Der Dokumentstamm zeigt auf $BASIS. Alles wird nach current/public
# weitergeleitet, damit shared/ und releases/ nie direkt erreichbar sind.
if [ ! -f .htaccess ]; then
    cat > .htaccess <<'HTA'
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ current/public/$1 [L]
</IfModule>
HTA
fi
printf 'Require all denied\n' > shared/.htaccess

# Geteilte Daten verbinden. Relative Links, weil SSH (chroot) und Webserver
# denselben Ordner unter verschiedenen absoluten Pfaden sehen.
rm -rf "$NEU/storage"
ln -s ../../shared/storage "$NEU/storage"
ln -sfn ../../shared/.env "$NEU/.env"
mkdir -p "$NEU/bootstrap/cache"

# Sicherung vor dem Update (mit der bisherigen Version)
if [ -L current ] && [ -f current/artisan ]; then
    "$PHP" current/artisan ok:sicherung vor-update || echo "Hinweis: Sicherung vor dem Update fehlgeschlagen."
fi

cd "$NEU"
"$PHP" artisan migrate --force
# Keine Konfiguration zwischenspeichern: Sie enthaelt absolute Pfade, und die
# unterscheiden sich zwischen SSH/Cron (chroot) und Webserver.
"$PHP" artisan optimize:clear
"$PHP" artisan route:cache
"$PHP" artisan event:cache

# Umschalten ohne Ausfall
cd "$BASIS"
VORHER="$(readlink current 2>/dev/null || true)"
ln -sfn "releases/$RELEASE" current.neu
mv -Tf current.neu current
[ -n "$VORHER" ] && echo "$VORHER" > vorherige-version

AENDERUNGEN="$(cat "$NEU/AENDERUNGEN.txt" 2>/dev/null || true)"
"$PHP" current/artisan ok:deploy "$(cat "$NEU/VERSION")" --commit="$COMMIT" --aenderungen="$AENDERUNGEN" || true
"$PHP" current/artisan queue:restart || true

# Nur die letzten 5 Versionen behalten
ls -1dt releases/*/ | tail -n +6 | xargs -r rm -rf

echo "Update auf $(cat "$NEU/VERSION") abgeschlossen."
