## Bestway Smart Spa

Le plugin Bestway Smart Spa pour Jeedom permet de piloter un spa Bestway compatible Smart Hub via l’API cloud officielle Bestway.

Il offre le contrôle des principales fonctions du spa (chauffage, filtration, bulles, température) ainsi que la remontée des états et alertes directement dans Jeedom.

⚠️ Ce plugin utilise une API privée Bestway. Son fonctionnement dépend du maintien de cette API.
---

Fonctionnalités

Allumage / extinction du spa

Activation / désactivation :

Chauffage

Filtration

Jets de bulles / vagues

Sélection du mode bulles : OFF / L1 / L2

Réglage de la température cible

Lecture :

Température actuelle de l’eau

État de connexion

Codes d’erreur et avertissements

Rafraîchissement automatique des états

# Prérequis
Équipement requis

Un PC ou Mac avec Charles Proxy installé
https://www.charlesproxy.com/download/

Deux smartphones (Android ou iOS) :

Téléphone A

Application Bestway Smart Hub installée

Spa déjà associé

Sert uniquement à afficher le QR code

Téléphone B

Utilisé pour intercepter le trafic réseau

Le certificat SSL Charles sera installé sur cet appareil

⚠️ Sur le téléphone B, vous devez impérativement utiliser une ancienne version de l’application :
Bestway Connect 1.0.4, téléchargeable depuis APKPure, afin de pouvoir intercepter le trafic réseau.

# Réseau

. Le PC et les deux smartphones doivent être connectés au même réseau Wi-Fi


Récupération des identifiants Bestway

Ces étapes sont nécessaires une seule fois pour obtenir les identifiants requis par le plugin.

Étape 1 – Configuration de Charles Proxy

Lancez Charles Proxy sur votre PC

Allez dans Proxy > Proxy Settings

Notez le port HTTP (par défaut : 8888)

Allez dans
Help > SSL Proxying > Install Charles Root Certificate
et installez le certificat sur votre PC

Étape 2 – Configuration du téléphone B
A. Configurer le proxy Wi-Fi

Sur le téléphone B, ouvrez les paramètres Wi-Fi

Appui long sur le réseau connecté → Modifier

Ouvrez Options avancées

Proxy : Manuel

Hôte : adresse IP de votre PC

Port : 8888

B. Installer le certificat SSL Charles

Indispensable pour déchiffrer le trafic HTTPS

Android (à confirmer selon version)

Ouvrez :
http://charlesproxy.com/getssl

Téléchargez le certificat

Installez-le via :
Paramètres > Sécurité > Chiffrement et identifiants > Installer depuis le stockage

iOS

Ouvrez Safari :
https://chls.pro/ssl

Acceptez et installez le profil

Allez dans :
Réglages > Général > VPN et gestion des appareils > Charles Proxy CA

Activez la confiance dans :
Réglages > Général > Informations > Réglages de confiance des certificats

Étape 3 – Activer le proxy SSL dans Charles

Dans Charles Proxy :

Allez dans Proxy > SSL Proxying Settings

Cliquez sur Add

Configurez :

Host : *

Port : 443

Étape 4 – Capture des données

⚠️ Respectez impérativement l’ordre des étapes

Démarrez l’enregistrement dans Charles (bouton ●)

Installez l’application Bestway Smart Hub sur le téléphone B

Ouvrez l’application

Sélectionnez la région Royaume-Uni

Scannez le QR code affiché sur le téléphone A

Surveillez les requêtes vers :

thing_shadow

command

api.bestwaycorp

Étape 5 – Récupération des identifiants

Recherchez une requête POST vers :

/enduser/visitor


Domaine :

https://smarthub-eu.bestwaycorp.com


Ouvrez la requête et consultez :

Request > JSON

ou Request > Text

Identifiants à récupérer

visitor_id

registration_id

device_id

product_id

client_id (Android uniquement)

Informations complémentaires

registration_id et client_id :
/api/enduser/visitor

device_id et product_id :
/api/enduser/home/room/devices

Nettoyage

Désactiver le proxy Wi-Fi sur le téléphone B

Supprimer le certificat SSL Charles si non nécessaire

Avertissement

Ce plugin est développé par la communauté Jeedom.
Il n’est ni affilié, ni soutenu par Bestway.

L’utilisation se fait à vos risques et périls.
L’API utilisée étant privée, son fonctionnement peut évoluer ou cesser sans préavis.
