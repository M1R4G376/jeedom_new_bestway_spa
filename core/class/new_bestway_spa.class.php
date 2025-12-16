<?php
/* This file is part of Jeedom.
 *
 * Plugin : new_bestway_spa
 * Classe principale + commandes
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class new_bestway_spa extends eqLogic {

    /* ============================================================
     *  DEPENDANCES
     * ============================================================ */

    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'new_bestway_spa_dep';
        $return['state'] = 'ok';

        log::add('new_bestway_spa', 'debug', '[dependancy_info] Vérification des dépendances Python (python3)...');

        if (trim(shell_exec('which python3')) == '') {
            log::add('new_bestway_spa', 'warning', '[dependancy_info] python3 introuvable sur le système.');
            $return['state'] = 'nok';
        } else {
            log::add('new_bestway_spa', 'debug', '[dependancy_info] python3 trouvé.');
        }

        return $return;
    }

    public static function dependancy_install() {
        log::add('new_bestway_spa', 'info', '[dependancy_install] Installation / mise à jour des dépendances Python (requests, aiohttp)...');

        $logFile = log::getPathToLog('new_bestway_spa_dep');
        $cmd = 'sudo -u www-data python3 -m pip install --upgrade requests aiohttp > ' . $logFile . ' 2>&1 &';

        log::add('new_bestway_spa', 'info', '[dependancy_install] Commande lancée : ' . $cmd);
        exec($cmd);
    }

    /* ============================================================
     *  DEMON
     * ============================================================ */

    public static function deamon_info() {
        $return = array();
        $return['log'] = 'new_bestway_spa';
        $return['launchable'] = 'ok';
        $return['state'] = 'nok';

        log::add('new_bestway_spa', 'debug', '[deamon_info] Vérification état du démon...');

        $pid = trim(shell_exec("ps aux | grep new_bestway_spa_daemon.py | grep -v grep | awk '{print \$2}'"));
        log::add('new_bestway_spa', 'debug', '[deamon_info] PID détecté: ' . $pid);

        if ($pid != '') {
            $return['state'] = 'ok';
        }

        return $return;
    }

    public static function deamon_start() {
        log::add('new_bestway_spa', 'info', '[deamon_start] Tentative de démarrage du démon...');

        // On arrête d’abord le démon existant le cas échéant
        self::deamon_stop();

        // Paramètres SmartHub obligatoires
        $required = array(
            'visitor_id',
            'registration_id',
            'device_id',
            'product_id',
        );

        foreach ($required as $key) {
            $value = config::byKey($key, 'new_bestway_spa');
            log::add('new_bestway_spa', 'debug', "[deamon_start] Vérif paramètre '$key' = \"" . $value . '"');
            if ($value === '' || $value === null) {
                throw new Exception("Paramètre obligatoire manquant ou vide : $key");
            }
        }

        $apikey = jeedom::getApiKey('new_bestway_spa');
        $ip = config::byKey('internalAddr');
        if ($ip == '' || $ip === null) {
            $ip = '127.0.0.1';
        }
        log::add('new_bestway_spa', 'debug', '[deamon_start] IP Jeedom utilisée : ' . $ip);

        $refresh         = config::byKey('refresh_interval', 'new_bestway_spa', 30);
        $api_host        = config::byKey('api_host', 'new_bestway_spa', 'smarthub-eu.bestwaycorp.com');
        $visitor_id      = config::byKey('visitor_id', 'new_bestway_spa');
        $client_id       = config::byKey('client_id', 'new_bestway_spa', '');
        $registration_id = config::byKey('registration_id', 'new_bestway_spa');
        $device_id       = config::byKey('device_id', 'new_bestway_spa');
        $product_id      = config::byKey('product_id', 'new_bestway_spa');
        $push_type       = config::byKey('push_type', 'new_bestway_spa', 'fcm');
        $location        = config::byKey('location', 'new_bestway_spa', 'GB');
        $timezone        = config::byKey('timezone', 'new_bestway_spa', 'GMT');

        $daemonPath = realpath(dirname(__FILE__) . '/../../resources/new_bestway_spa_daemon.py');
        log::add('new_bestway_spa', 'debug', '[deamon_start] Chemin daemon = ' . $daemonPath);

        if ($daemonPath === false || !file_exists($daemonPath)) {
            throw new Exception("Script daemon introuvable : " . dirname(__FILE__) . '/../../resources/new_bestway_spa_daemon.py');
        }

        // Construction de la commande de lancement
        $cmd  = 'nohup python3 ' . $daemonPath;
        $cmd .= ' --apikey ' . escapeshellarg($apikey);
        $cmd .= ' --jeedom_ip ' . escapeshellarg($ip);
        $cmd .= ' --refresh ' . intval($refresh);
        $cmd .= ' --api_host ' . escapeshellarg($api_host);
        $cmd .= ' --visitor_id ' . escapeshellarg($visitor_id);
        if ($client_id != '') {
            $cmd .= ' --client_id ' . escapeshellarg($client_id);
        }
        $cmd .= ' --registration_id ' . escapeshellarg($registration_id);
        $cmd .= ' --device_id ' . escapeshellarg($device_id);
        $cmd .= ' --product_id ' . escapeshellarg($product_id);
        $cmd .= ' --push_type ' . escapeshellarg($push_type);
        $cmd .= ' --location ' . escapeshellarg($location);
        $cmd .= ' --timezone ' . escapeshellarg($timezone);
        $cmd .= ' >> ' . log::getPathToLog('new_bestway_spa') . ' 2>&1 &';

        log::add('new_bestway_spa', 'info', '[deamon_start] CMD LANCÉ : ' . $cmd);
        exec($cmd);

        // On laisse un peu de temps au démon pour démarrer
        sleep(1);
        $info = self::deamon_info();
        if ($info['state'] != 'ok') {
            log::add('new_bestway_spa', 'warning', '[deamon_start] Le démon semble ne pas être démarré (state != ok).');
        }

        return true;
    }

    public static function deamon_stop() {
        log::add('new_bestway_spa', 'info', '[deamon_stop] Arrêt du démon...');

        // Récupération éventuelle du PID
        $pidList = trim(shell_exec("ps aux | grep new_bestway_spa_daemon.py | grep -v grep | awk '{print \$2}'"));

        if ($pidList == '') {
            log::add('new_bestway_spa', 'debug', '[deamon_stop] Aucun PID détecté pour new_bestway_spa_daemon.py');
            return;
        }

        $pids = explode("\n", $pidList);
        foreach ($pids as $pid) {
            $pid = trim($pid);
            if ($pid == '') {
                continue;
            }
            log::add('new_bestway_spa', 'info', '[deamon_stop] kill PID ' . $pid);
            exec('kill ' . escapeshellarg($pid));
        }
    }

    /* ============================================================
     *  CYCLE DE VIE / postSave
     * ============================================================ */

    public function postSave() {
        log::add('new_bestway_spa', 'debug', '[postSave] Début pour eqLogic #' . $this->getId() . ' (' . $this->getName() . ')');

        // On s'assure que l'equipement est bien du bon eqType
        if ($this->getEqType_name() != 'new_bestway_spa') {
            log::add('new_bestway_spa', 'debug', '[postSave] Correction eqType_name => new_bestway_spa');
            $this->setEqType_name('new_bestway_spa');
            $this->save();
        }

        // Auto-création / réparation des commandes
        try {
            $this->createDefaultCommands();
            log::add('new_bestway_spa', 'debug', '[postSave] createDefaultCommands() exécuté avec succès.');
        } catch (Exception $e) {
            log::add('new_bestway_spa', 'error', '[postSave] Erreur lors de createDefaultCommands : ' . $e->getMessage());
        }

        log::add('new_bestway_spa', 'debug', '[postSave] Fin.');
    }

    /* ============================================================
     *  COMMANDES PAR DÉFAUT (création + réparation)
     * ============================================================ */

	public function createDefaultCommands() {
	    log::add('new_bestway_spa', 'debug', '[createDefaultCommands] Création / réparation des commandes pour eqLogic #' . $this->getId());

    // Liste des sous-types valides
	    $validInfoSubTypes = array('string', 'numeric', 'binary');
	    $validActionSubTypes = array('other', 'slider', 'color', 'message', 'select');

    // --- COMMANDES INFO ---
	    $infos = array(
	        'wifi_version'        => array('Version WiFi',         'numeric', '',   0),
	        'ota_status'          => array('Statut OTA',           'numeric', '',   0),
	        'mcu_version'         => array('Version MCU',          'string',  '',   0),
	        'trd_version'         => array('Version TRD',          'string',  '',   0),
	        'connect_type'        => array('Type connexion',       'string',  '',   0),
	        'power_state'         => array('Alimentation',         'binary',  '',   1),
	        'heater_state'        => array('Chauffage',            'binary',  '',   1),
	        'wave_state'          => array('Bulles',               'binary',  '',   1),
	        'filter_state'        => array('Filtration',           'binary',  '',   1),
	        'temperature_setting' => array('Température cible',    'numeric', '°C', 1),
	        'temperature_unit'    => array('Unité température',    'numeric', '',   0),
	        'water_temperature'   => array('Température eau',      'numeric', '°C', 1),
	        'warning'             => array('Avertissement',        'string',  '',   0),
	        'error_code'          => array('Code erreur',          'string',  '',   0),
	        'hydrojet_state'      => array('Hydrojet',             'binary',  '',   1),
	        'is_online'           => array('En ligne',             'binary',  '',   0),
	    );
	
	    foreach ($infos as $logicalId => $config) {
	        list($name, $subtype, $unit, $historize) = $config;

	        $cmd = $this->getCmd(null, $logicalId);

	        if (!is_object($cmd)) {
	            $cmd = new new_bestway_spaCmd();
	            $cmd->setEqLogic_id($this->getId());
	            $cmd->setLogicalId($logicalId);
	            log::add('new_bestway_spa', 'debug', "[createDefaultCommands] Info '$logicalId' créée.");
	        } else {
	            log::add('new_bestway_spa', 'debug', "[createDefaultCommands] Info '$logicalId' existante, vérification.");
	        }

        // Validation du subtype
	        if (!in_array($subtype, $validInfoSubTypes)) {
	            log::add('new_bestway_spa', 'error', "[createDefaultCommands] subType '$subtype' invalide pour info '$logicalId'. Défini par défaut à 'string'");
	            $subtype = 'string';
	        }

	        $cmd->setName($name);
	        $cmd->setType('info');
	        $cmd->setSubType($subtype);
	        $cmd->setIsHistorized($historize ? 1 : 0);
	        $cmd->setUnite($unit != '' ? $unit : '');

	        $cmd->save();
	    }

    // --- COMMANDES ACTION ---
	    $actions = array(
	        'set_power'     => array('Alimentation ON/OFF', 'other',  ''),
	        'set_heating'   => array('Activer chauffage',   'other',  ''),
	        'set_filtering' => array('Activer filtration',  'other',  ''),
	        'set_hydrojet'  => array('Activer hydrojet',    'other',  ''),
	        'bubble_mode'   => array('Mode bulles',         'select', '0|Off;1|L1;2|L2'),
	    );

	    foreach ($actions as $logicalId => $config) {
	        list($name, $subtype, $listValue) = $config;

	        $cmd = $this->getCmd(null, $logicalId);

	        if (!is_object($cmd)) {
	            $cmd = new new_bestway_spaCmd();
	            $cmd->setEqLogic_id($this->getId());
	            $cmd->setLogicalId($logicalId);
	            log::add('new_bestway_spa', 'debug', "[createDefaultCommands] Action '$logicalId' créée.");
	        } else {
	            log::add('new_bestway_spa', 'debug', "[createDefaultCommands] Action '$logicalId' existante, vérification.");
	        }

        // Validation du subtype
	        if (!in_array($subtype, $validActionSubTypes)) {
	            log::add('new_bestway_spa', 'error', "[createDefaultCommands] subType '$subtype' invalide pour action '$logicalId'. Défini par défaut à 'other'");
	            $subtype = 'other';
	        }

	        $cmd->setName($name);
	        $cmd->setType('action');
	        $cmd->setSubType($subtype);

	        if ($subtype == 'select' && $listValue != '') {
	            $cmd->setConfiguration('listValue', $listValue);
	        } else {
	            $cmd->setConfiguration('listValue', '');
	        }

	        $cmd->save();
	    }

	    log::add('new_bestway_spa', 'debug', '[createDefaultCommands] Fin de création / réparation des commandes.');
	}
    /* ============================================================
     *  RECEPTION DU DEMON (PUSH ETAT)
     * ============================================================ */

    public static function updateFromDaemon($spaId, $data) {
        log::add(
            'new_bestway_spa',
            'debug',
            '[updateFromDaemon] spaId=' . $spaId . ' / data=' . json_encode($data)
        );

        $eq = self::byLogicalId($spaId, 'new_bestway_spa');
        if (!is_object($eq)) {
            log::add(
                'new_bestway_spa',
                'warning',
                "[updateFromDaemon] Equipement introuvable pour spaId $spaId"
            );
            return;
        }

        foreach ($data as $key => $value) {
            log::add(
                'new_bestway_spa',
                'debug',
                "[updateFromDaemon] MAJ cmd '$key' => " . json_encode($value)
            );

            // On ne met à jour que les commandes info
            $cmd = $eq->getCmd('info', $key);
            if (!is_object($cmd)) {
                log::add(
                    'new_bestway_spa',
                    'warning',
                    "[updateFromDaemon] Commande info '$key' inexistante, ignorée."
                );
                continue;
            }

            // Normalisation des valeurs selon le sous-type
            $subType = $cmd->getSubType();
            $val = $value;

            // Booleans => 0/1
            if (is_bool($val)) {
                $val = $val ? 1 : 0;
            }

            // Pour les binary : tout ce qui est "truthy" => 1, sinon 0
            if ($subType == 'binary') {
                if ($val === '' || $val === null) {
                    $val = 0;
                } elseif (is_string($val)) {
                    $lower = strtolower($val);
                    $val = ($val === '1' || $lower === 'true' || $lower === 'on') ? 1 : 0;
                } else {
                    $val = intval($val) ? 1 : 0;
                }
            }

            // Pour les numeric : cast en float
            if ($subType == 'numeric') {
                if ($val === '' || $val === null) {
                    $val = 0;
                }
                $val = floatval($val);
            }

            log::add(
                'new_bestway_spa',
                'debug',
                "[updateFromDaemon] -> event(" . json_encode($val) . ') sur cmd ' . $key
            );
            $cmd->event($val);
        }
    }

    public static function createOrUpdateSpa($spaId, $data) {
        log::add('new_bestway_spa', 'debug', '[createOrUpdateSpa] spaId=' . $spaId);

        // On recherche l'équipement existant
        $eq = self::byLogicalId($spaId, 'new_bestway_spa');
        $isNew = false;

        if (!is_object($eq)) {
            log::add(
                'new_bestway_spa',
                'info',
                "[createOrUpdateSpa] Création équipement '$spaId'"
            );

            $eq = new self();
            $eq->setLogicalId($spaId);
            $eq->setEqType_name('new_bestway_spa');

            // Nom par défaut
            $eq->setName('Mon SPA');
            $eq->setIsEnable(1);
            $eq->setIsVisible(1);
            $eq->setConfiguration('spa_id', $spaId);

            $eq->save();
            $isNew = true;
        }

        // Création / réparation des commandes à chaque appel,
        // pour être robuste aux modifications dans le panel.
        log::add('new_bestway_spa', 'debug', '[createOrUpdateSpa] Création / réparation des commandes par défaut...');
        $eq->createDefaultCommands();

        log::add('new_bestway_spa', 'debug', '[createOrUpdateSpa] Mise à jour des valeurs...');
        self::updateFromDaemon($spaId, $data);
    }
}

/* ============================================================
 *  CLASS CMD
 * ============================================================ */

class new_bestway_spaCmd extends cmd {

    public function execute($_options = array()) {
        $eq = $this->getEqLogic();
        if (!is_object($eq)) {
            log::add(
                'new_bestway_spa',
                'error',
                '[execute] Impossible de récupérer l\'équipement associé à la commande ' . $this->getLogicalId()
            );
            return;
        }

        $spaId = $eq->getLogicalId();

        $payload = array(
            'type'    => 'command',
            'spa_id'  => $spaId,
            'command' => $this->getLogicalId(),
            'value'   => null,
        );

        $subType = $this->getSubType();
        log::add(
            'new_bestway_spa',
            'debug',
            '[execute] Commande ' . $this->getLogicalId() . ' / subtype=' . $subType . ' / options=' . json_encode($_options)
        );

        if ($subType == 'select' && isset($_options['select'])) {
            $payload['value'] = $_options['select'];
        } elseif ($subType == 'slider' && isset($_options['slider'])) {
            $payload['value'] = $_options['slider'];
        } elseif ($subType == 'color' && isset($_options['color'])) {
            $payload['value'] = $_options['color'];
        } elseif ($subType == 'message' && isset($_options['message'])) {
            $payload['value'] = $_options['message'];
        } else {
            // Pour les actions type "other" (toggle, on/off, etc.),
            // on laisse value = null, le démon interprétera.
        }

        $url = network::getNetworkAccess('internal') . '/plugins/new_bestway_spa/core/php/new_bestway_spa.api.php';

        $params = array(
            'apikey'  => jeedom::getApiKey('new_bestway_spa'),
            'payload' => json_encode($payload),
        );

        log::add(
            'new_bestway_spa',
            'debug',
            '[execute] Envoi POST à ' . $url . ' avec payload=' . json_encode($payload)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        if ($result === false) {
            $curlError = curl_error($ch);
            log::add(
                'new_bestway_spa',
                'error',
                '[execute] Erreur CURL vers API new_bestway_spa : ' . $curlError
            );
        } else {
            log::add(
                'new_bestway_spa',
                'debug',
                '[execute] Réponse API : ' . $result
            );
        }

        curl_close($ch);
        return true;
    }
}