#!/bin/bash
set -e

PLUGIN="new_bestway_spa"
BASE="/var/www/html"
VENV="${BASE}/data/${PLUGIN}/venv"

echo "[${PLUGIN}] Installing OS prerequisites..."
apt-get update
apt-get install -y python3-full python3-venv

echo "[${PLUGIN}] Creating venv: ${VENV}"
mkdir -p "${BASE}/data/${PLUGIN}"
chown -R www-data:www-data "${BASE}/data/${PLUGIN}"

sudo -u www-data -H python3 -m venv "${VENV}"

echo "[${PLUGIN}] Installing python deps..."
sudo -u www-data -H bash -lc "
  source '${VENV}/bin/activate'
  python -m pip install --upgrade pip
  pip install -r '${BASE}/plugins/${PLUGIN}/resources/requirements.txt'
"

echo "[${PLUGIN}] Done."
