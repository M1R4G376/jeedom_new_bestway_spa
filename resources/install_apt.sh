#!/bin/bash
set -e

PLUGIN="new_bestway_spa"
PROGRESS_FILE="/tmp/jeedom/${PLUGIN}/dependency"
if [ -n "$1" ]; then
  PROGRESS_FILE="$1"
fi

function log(){
  if [ -n "$1" ]; then
    echo "$(date +'[%F %T]') $1"
  else
    while IFS= read -r IN; do
      echo "$(date +'[%F %T]') $IN"
    done
  fi
}

BASE_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REQUIREMENTS_FILE="${BASE_DIR}/requirements.txt"

# Venv persistant (recommandé) : ne saute pas lors d'updates plugin
VENV_DIR="/var/www/html/data/${PLUGIN}/venv"

mkdir -p "$(dirname "${PROGRESS_FILE}")"
touch "${PROGRESS_FILE}"
echo 0 > "${PROGRESS_FILE}"

log "****************"
log "* ${PLUGIN} DEP *"
log "****************"

echo 5 > "${PROGRESS_FILE}"
log "* apt-get update *"
export DEBIAN_FRONTEND=noninteractive
apt-get clean | log
apt-get update | log

echo 20 > "${PROGRESS_FILE}"
log "* Install OS packages *"
apt-get install -y python3-full python3-venv python3-pip python3-setuptools | log

echo 40 > "${PROGRESS_FILE}"
log "* Create/Update venv: ${VENV_DIR} *"
mkdir -p "/var/www/html/data/${PLUGIN}"
chown -R www-data:www-data "/var/www/html/data/${PLUGIN}"

# Crée/upgrade le venv + deps pip
python3 -m venv --upgrade-deps "${VENV_DIR}" | log

echo 70 > "${PROGRESS_FILE}"
log "* Install Python deps (requirements.txt) *"
"${VENV_DIR}/bin/python3" -m pip install --upgrade pip wheel | log
"${VENV_DIR}/bin/python3" -m pip install -r "${REQUIREMENTS_FILE}" | log

echo 90 > "${PROGRESS_FILE}"
log "* Fix permissions (www-data) *"
chown -R www-data:www-data "/var/www/html/data/${PLUGIN}" | log

echo 100 > "${PROGRESS_FILE}"
log "* DONE *"
rm -f "${PROGRESS_FILE}"