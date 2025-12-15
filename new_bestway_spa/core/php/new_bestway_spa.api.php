<?php

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');

header('Content-Type: application/json');

// ---------------------------------------------------------
// 1. Vérification API KEY
// ---------------------------------------------------------
if (!jeedom::apiAccess(init('apikey'), 'new_bestway_spa')) {
    log::add('new_bestway_spa', 'error', 'Accès API refusé : apikey invalide (' . init('apikey') . ')');
    echo json_encode(array('success' => false, 'message' => 'Invalid API key'));
    die();
}

// ---------------------------------------------------------
// 2. Gestion spéciale : le démon demande la file de commandes
//    (optionnel, pour plus tard côté Python)
//    GET/POST action=get_commands
// ---------------------------------------------------------
$action = init('action', '');
if ($action == 'get_commands') {
    $queueKey = cache::byKey('new_bestway_spa::command_queue');
    $queueStr = $queueKey->getValue('[]');
    // on vide la file après lecture
    cache::set('new_bestway_spa::command_queue', json_encode(array()));
    echo $queueStr;
    die();
}

// ---------------------------------------------------------
// 3. Récupération du payload (plusieurs formats possibles)
//    - champs POST: payload
//    - JSON brut dans php://input
//    - ou vieux format: spa_id + data
// ---------------------------------------------------------
$payloadStr = init('payload', '');
if ($payloadStr == '') {
    // cas JSON brut
    $raw = file_get_contents('php://input');
    if ($raw != '') {
        $payloadStr = $raw;
    }
}

// cas legacy : spa_id + data directement en POST
$legacySpaId = init('spa_id', init('device_id', ''));
$legacyData  = init('data', '');

$payload = null;
if ($payloadStr != '') {
    $payload = json_decode($payloadStr, true);
    if (!is_array($payload)) {
        $payload = null;
    }
}

// ---------------------------------------------------------
// 4. Si c'est une COMMANDE venant d'une cmd Jeedom
//    (new_bestway_spaCmd::execute envoie type=command)
// ---------------------------------------------------------
if (is_array($payload) && isset($payload['type']) && $payload['type'] == 'command') {

    // On empile dans une file pour le démon (polling ultérieur)
    $queueKey = cache::byKey('new_bestway_spa::command_queue');
    $queueStr = $queueKey->getValue('[]');
    $queue    = json_decode($queueStr, true);
    if (!is_array($queue)) {
        $queue = array();
    }

    $queue[] = $payload;
    cache::set('new_bestway_spa::command_queue', json_encode($queue));

    log::add('new_bestway_spa', 'debug', 'Commande ajoutée à la file : ' . json_encode($payload));

    echo json_encode(array('success' => true, 'message' => 'command queued'));
    die();
}

// ---------------------------------------------------------
// 5. Sinon : c’est un PUSH D’ÉTAT du démon vers Jeedom
//    On accepte plusieurs formats pour être tolérant.
// ---------------------------------------------------------

try {
    // a) format moderne : payload["spa_id"] + payload["data"]
    if (is_array($payload) && isset($payload['spa_id']) && isset($payload['data'])) {
        $spaId = $payload['spa_id'];
        $data  = $payload['data'];

    // b) autre format possible : payload["device_id"] + payload["state"]
    } elseif (is_array($payload) && isset($payload['device_id']) && isset($payload['state'])) {
        $spaId = $payload['device_id'];
        $data  = $payload['state'];

    // c) format legacy : champs POST spa_id + data (JSON)
    } elseif ($legacySpaId != '' && $legacyData != '') {
        $spaId = $legacySpaId;
        $data  = json_decode($legacyData, true);
        if (!is_array($data)) {
            throw new Exception('Format data invalide (legacy)');
        }
    } else {
        throw new Exception('Aucun payload valide trouvé');
    }

    if ($spaId == '') {
        throw new Exception('spa_id / device_id manquant');
    }
    if (!is_array($data)) {
        throw new Exception('Données d’état invalides (non tableau)');
    }

    // On crée / met à jour l’équipement et les commandes
    new_bestway_spa::createOrUpdateSpa($spaId, $data);

    echo json_encode(array('success' => true, 'message' => 'state updated'));
    die();

} catch (Exception $e) {
    log::add('new_bestway_spa', 'error', 'Erreur API Bestway : ' . $e->getMessage());
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    die();
}