## Bestway Smart Spa

This Home Assistant integration allows you to control your Bestway SmartHub-enabled spa via the Bestway cloud API.

---

## Installation

1. Copy the `new_bestway_spa/` folder into `custom_components/` in your Home Assistant configuration directory.
2. Restart Home Assistant.
3. Go to **Settings > Devices & Services** and click **Add Integration**.
4. Search for **Bestway Spa** and follow the configuration flow.

---

Prérequis
Équipement requis

Un PC ou Mac avec Charles Proxy
 installé

Deux smartphones (Android ou iOS) :

Téléphone A : avec l’application Bestway Smart Hub installée et déjà connectée au spa (utilisé pour partager le QR code)

Téléphone B : utilisé pour capturer le trafic via Charles Proxy (le certificat SSL sera installé sur cet appareil)

Sur le téléphone B, vous devez utiliser une version plus ancienne de l’application (Bestway Connect 1.0.4), téléchargeable depuis APKPURE, afin de pouvoir intercepter le trafic réseau.
Réseau

Le PC et les deux smartphones doivent être connectés au même réseau Wi-Fi

Étape 1 – Configuration de Charles Proxy

Lancez Charles Proxy sur votre PC

Allez dans Proxy > Proxy Settings et notez le port HTTP (par défaut : 8888)

Allez dans Help > SSL Proxying > Install Charles Root Certificate (installez-le sur votre PC)

Étape 2 – Configuration du téléphone B
A. Configurer le proxy Wi-Fi

Sur le téléphone B, ouvrez les paramètres Wi-Fi

Appui long sur le réseau connecté > Modifier > Options avancées

Réglez le proxy sur Manuel :

Hôte du proxy : adresse IP de votre PC

Port : 8888

B. Installer le certificat SSL

Nécessaire pour déchiffrer le trafic HTTPS

Android (à confirmer)

Ouvrez http://charlesproxy.com/getssl sur le téléphone B

Téléchargez le certificat

Installez-le via :
Paramètres > Sécurité > Chiffrement et identifiants > Installer depuis le stockage

iOS

Ouvrez Safari : https://chls.pro/ssl

Acceptez et installez le profil

Allez dans :
Réglages > Général > VPN et gestion des appareils > Charles Proxy CA

Activez-le dans :
Réglages > Général > Informations > Réglages de confiance des certificats

Étape 3 – Activer le proxy SSL

Dans Charles :

Allez dans Proxy > SSL Proxying Settings

Cliquez sur Add

Configurez :

Hôte : *

Port : 443

Étape 4 – Capture des données
⚠️ Il est important de suivre ces étapes dans cet ordre afin de récupérer tous les identifiants nécessaires.

Démarrez l’enregistrement dans Charles (bouton ●)

Installez l’application Bestway Smart Hub sur le téléphone B

Ouvrez Bestway Smart Hub

Sélectionnez la région Royaume-Uni et scannez le QR code

Surveillez les requêtes telles que thing_shadow, command ou celles vers api.bestwaycorp

Étape 5 – Récupération des identifiants

Recherchez une requête POST vers /enduser/visitor :

https://smarthub-eu.bestwaycorp.com

Ouvrez-la et consultez Request > JSON ou Text

Identifiants utiles à extraire :

visitor_id

client_id (pour Android)

device_id

product_id

Informations supplémentaires :

registration_id et client_id peuvent être trouvés dans /api/enduser/visitor

device_id et product_id peuvent se trouver dans /api/enduser/home/room/devices

Nettoyage

Désactiver le proxy sur le téléphone B

Supprimer le certificat SSL Charles s’il n’est plus nécessaire

---

## Configuration Options

| Field            | Required | Notes                                      |
|------------------|----------|--------------------------------------------|
| `device_name`    | ✅       | Display name in Home Assistant             |
| `visitor_id`     | ✅       | From intercepted app traffic               |
| `registration_id`| ✅       | Same as above                              |
| `client_id`      | ❌       | Only for Android (`push_type = fcm`)       |
| `device_id`      | ✅       | Needed to control the spa                  |
| `product_id`     | ✅       | Needed to control the spa                  |
| `push_type`      | ❌       | `fcm` (Android) or `apns` (iOS), default `fcm` |

---

## API Notes

- `filter_state` returns `2` when active, `0` when off — the integration handles this automatically.
- `select` gives the possibility to choose  the bubble/wave mode OFF/L1/L2 (not available from the official app)
- To **turn on** any feature, the integration sends `1`. To **turn off**, it sends `0`.
- All values are polled from `/api/device/thing_shadow/`

---

## Features

- Toggle spa power, filter, heater, and wave jets
- Adjust water target temperature
- View current water temperature
- Monitor connection status, warnings, and error codes

---


## Disclaimer
This is a community-made integration. It is not affiliated with or endorsed by Bestway.
Use at your own risk — the code interacts with a private API which may change.