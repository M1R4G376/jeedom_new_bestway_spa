#!/bin/bash
set -e

PLUGIN="new_bestway_spa"
PROGRESS_FILE="/tmp/jeedom/${PLUGIN}/dependency"
if [ -n "$1" ]; then
  PROGRESS_FILE="$1"
fi

log() { echo "$(date +'[%F %T]') $*"; }

# Chemins
PLUGIN_DIR="/var/www/html/plugins/${PLUGIN}"
VENV_DIR="${PLUGIN_DIR}/resources/venv"
REQ_FILE="${PLUGIN_DIR}/resources/requirements.txt"

# Python 
PY="/opt/pyenv/versions/3.11.11/bin/python3"
if [ ! -x "$PY" ]; then
  PY="$(command -v python3)"
fi

mkdir -p "$(dirname "$PROGRESS_FILE")"
echo 0 > "$PROGRESS_FILE"

log "== ${PLUGIN} dependencies (apt) =="
log "Python used: ${PY}"
log "Venv target: ${VENV_DIR}"

echo 10 > "$PROGRESS_FILE"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y

echo 25 > "$PROGRESS_FILE"
apt-get install -y ca-certificates

echo 40 > "$PROGRESS_FILE"

chown -R www-data:www-data "${PLUGIN_DIR}/resources" || true

echo 60 > "$PROGRESS_FILE"
# Création de venv
"$PY" -m venv --upgrade-deps "${VENV_DIR}"

echo 80 > "$PROGRESS_FILE"
# Installe deps
"${VENV_DIR}/bin/python3" -m pip install --upgrade pip wheel
"${VENV_DIR}/bin/python3" -m pip install -r "${REQ_FILE}"

echo 100 > "$PROGRESS_FILE"
log "Done."
rm -f "$PROGRESS_FILE"
