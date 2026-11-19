#!/usr/bin/env bash
# Schaltet auf die vorherige Version zurueck:  bash scripts/zurueckrollen.sh <basis>
# Achtung: Datenbank-Migrationen werden NICHT zurueckgenommen. Bei Bedarf eine
# Sicherung "vor-update" im Admin-Bereich einspielen.
set -euo pipefail
BASIS="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$BASIS"
ZIEL="$(cat vorherige-version 2>/dev/null || ls -1dt releases/*/ | sed -n 2p)"
ZIEL="${ZIEL%/}"
[ -d "$ZIEL" ] || { echo "Keine vorherige Version gefunden." >&2; exit 1; }
ln -sfn "$ZIEL" current.neu
mv -Tf current.neu current
echo "Zurueckgeschaltet auf $ZIEL"
