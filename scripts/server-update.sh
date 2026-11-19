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

# Geteilte Daten verbinden
rm -rf "$NEU/storage"
ln -s "$BASIS/shared/storage" "$NEU/storage"
ln -sfn "$BASIS/shared/.env" "$NEU/.env"
mkdir -p "$NEU/bootstrap/cache"

# Sicherung vor dem Update (mit der bisherigen Version)
if [ -L current ] && [ -f current/artisan ]; then
    "$PHP" current/artisan ok:sicherung vor-update || echo "Hinweis: Sicherung vor dem Update fehlgeschlagen."
fi

cd "$NEU"
"$PHP" artisan migrate --force
"$PHP" artisan optimize

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
