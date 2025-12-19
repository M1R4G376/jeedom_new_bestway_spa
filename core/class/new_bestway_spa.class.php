<?php
/* This file is part of Jeedom.
 *
 * Plugin : new_bestway_spa
 * Classe principale + commandes
 */

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class new_bestway_spa extends eqLogic {

    const VENV_PYTHON = '/var/www/html/plugins/new_bestway_spa/resources/venv/bin/python3';

    /* ============================================================
     *  HELPERS LOG
     * ============================================================ */

    private static function dbg($msg) {
        log::add('new_bestway_spa', 'debug', $msg);
    }

    private static function inf($msg) {
        log::add('new_bestway_spa', 'info', $msg);
    }

    private static function wrn($msg) {
        log::add('new_bestway_spa', 'warning', $msg);
    }

    private static function err($msg) {
        log::add('new_bestway_spa', 'error', $msg);
    }

    private static function dbgArr($prefix, $arr) {
        self::dbg($prefix . ' ' . json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function maskApiKeyInCmd($cmd) {
        return preg_replace("/--apikey\s+'[^']*'/", "--apikey '***'", $cmd);
    }

    /* ============================================================
     *  DEPENDANCES
     * ============================================================ */

    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'new_bestway_spa_dep';
        $return['progress_file'] = jeedom::getTmpFolder(__CLASS__) . '/dependency';

        self::dbg('[dependancy_info] start');
        self::dbgArr('[dependancy_info] return init:', $return);

        if (file_exists($return['progress_file'])) {
            self::dbg('[dependancy_info] progress_file exists => in_progress: ' . $return['progress_file']);
            $return['state'] = 'in_progress';
            return $return;
        }

        self::dbg('[dependancy_info] progress_file not found: ' . $return['progress_file']);

        if (!file_exists(self::VENV_PYTHON)) {
            self::dbg('[dependancy_info] VENV_PYTHON missing: ' . self::VENV_PYTHON);
            $return['state'] = 'nok';
            return $return;
        }

        self::dbg('[dependancy_info] VENV_PYTHON found: ' . self::VENV_PYTHON);

        $pyCheck = 'import requests; print(requests.__version__)';
        $cmd = escapeshellcmd(self::VENV_PYTHON) . ' -c ' . escapeshellarg($pyCheck);
        $output = array();
        $rc = 0;

        self::dbg('[dependancy_info] exec cmd: ' . $cmd);
        @exec($cmd . ' 2>&1', $output, $rc);

        self::dbg('[dependancy_info] exec rc=' . $rc);
        self::dbgArr('[dependancy_info] exec output:', $output);

        $return['state'] = ($rc === 0) ? 'ok' : 'nok';
        self::dbg('[dependancy_info] state=' . $return['state']);

        return $return;
    }

    public static function dependancy_install() {
        return array(
            'script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh',
            'log'    => 'new_bestway_spa_dep'
        );
    }
    /* ============================================================
     *  DEMON
     * ============================================================ */

    public static function deamon_info() {
        $return = array();
        $return['log'] = 'new_bestway_spa';
        $return['launchable'] = 'ok';
        $return['state'] = 'nok';

        self::dbg('[deamon_info] start');

        $dep = self::dependancy_info();
        self::dbgArr('[deamon_info] dependancy_info:', $dep);

        if (!isset($dep['state']) || $dep['state'] !== 'ok') {
            $return['launchable'] = 'nok';
            self::dbg('[deamon_info] launchable=nok (dependencies not ok)');
            return $return;
        }

        // Anti “auto-match” pgrep/pkill : [n]ew_...
        $pgrepCmd = "pgrep -f \"[n]ew_bestway_spa_daemon.py\" | head -n 1";
        self::dbg('[deamon_info] pgrep cmd: ' . $pgrepCmd);
        $pid = trim(shell_exec($pgrepCmd));
        self::dbg('[deamon_info] pgrep result pid=' . ($pid !== '' ? $pid : '(none)'));

        if ($pid !== '') {
            $return['state'] = 'ok';
        }

        self::dbgArr('[deamon_info] return:', $return);
        return $return;
    }

    public static function deamon_start() {
        self::inf('[deamon_start] Tentative de démarrage du démon...');
        self::dbg('[deamon_start] start');

        self::dbg('[deamon_start] calling deamon_stop() before start');
        self::deamon_stop();

        $dep = self::dependancy_info();
        self::dbgArr('[deamon_start] dependancy_info:', $dep);

        if (!isset($dep['state']) || $dep['state'] !== 'ok') {
            self::err('[deamon_start] dependencies not ok');
            throw new Exception("Dépendances non installées. Lancez d'abord l'installation des dépendances (venv + requests).");
        }

        if (!file_exists(self::VENV_PYTHON)) {
            self::err('[deamon_start] Venv introuvable: ' . self::VENV_PYTHON);
            throw new Exception("Venv introuvable: " . self::VENV_PYTHON);
        }

        $required = array('visitor_id', 'registration_id', 'device_id', 'product_id');
        foreach ($required as $key) {
            $value = config::byKey($key, 'new_bestway_spa');
            self::dbg("[deamon_start] config $key=" . ($value === null ? '(null)' : (string)$value));
            if ($value === '' || $value === null) {
                self::err("[deamon_start] Missing required config: $key");
                throw new Exception("Paramètre obligatoire manquant ou vide : $key");
            }
        }

        $apikey = jeedom::getApiKey('new_bestway_spa');
        self::dbg('[deamon_start] apikey length=' . strlen((string)$apikey) . ' (masked)');

        $ip = config::byKey('internalAddr');
        if ($ip == '' || $ip === null) {
            $ip = '127.0.0.1';
        }

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

        self::dbgArr('[deamon_start] params:', array(
            'jeedom_ip' => $ip,
            'refresh' => $refresh,
            'api_host' => $api_host,
            'visitor_id' => $visitor_id,
            'client_id' => $client_id,
            'registration_id' => $registration_id,
            'device_id' => $device_id,
            'product_id' => $product_id,
            'push_type' => $push_type,
            'location' => $location,
            'timezone' => $timezone,
        ));

        $daemonPath = realpath(dirname(__FILE__) . '/../../resources/new_bestway_spa_daemon.py');
        self::dbg('[deamon_start] daemonPath realpath=' . ($daemonPath ? $daemonPath : '(false)'));

        if ($daemonPath === false || !file_exists($daemonPath)) {
            self::err('[deamon_start] daemon script not found');
            throw new Exception("Script daemon introuvable : " . dirname(__FILE__) . '/../../resources/new_bestway_spa_daemon.py');
        }

        $cmd  = 'nohup ' . escapeshellcmd(self::VENV_PYTHON) . ' ' . escapeshellarg($daemonPath);
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

        $daemonLog = log::getPathToLog('new_bestway_spa_daemon');
        $cmd .= ' >> ' . $daemonLog . ' 2>&1 &';

        self::inf('[deamon_start] CMD LANCÉ (masked) : ' . self::maskApiKeyInCmd($cmd));
        self::dbg('[deamon_start] daemon log path=' . $daemonLog);

        $output = array();
        $rc = 0;
        exec($cmd, $output, $rc);
        self::dbg('[deamon_start] exec rc=' . $rc);
        self::dbgArr('[deamon_start] exec output:', $output);

        $i = 0;
        while ($i < 10) {
            $info = self::deamon_info();
            self::dbgArr('[deamon_start] poll deamon_info:', $info);
            if ($info['state'] === 'ok') {
                self::inf('[deamon_start] Démon démarré.');
                return true;
            }
            sleep(1);
            $i++;
        }

        self::err('[deamon_start] Le démon ne démarre pas. Vérifiez le log new_bestway_spa_daemon.');
        return false;
    }

    public static function deamon_stop() {
        self::inf('[deamon_stop] Arrêt du démon...');
        self::dbg('[deamon_stop] start');

        // Anti “auto-match” : [n]ew_...
        $cmd = "pkill -f \"[n]ew_bestway_spa_daemon.py\" 2>/dev/null";
        self::dbg('[deamon_stop] exec: ' . $cmd);

        $output = array();
        $rc = 0;
        exec($cmd, $output, $rc);

        self::dbg('[deamon_stop] rc=' . $rc . ' (0=killed, 1=not found)');
        self::dbgArr('[deamon_stop] output:', $output);

        usleep(200000);

        // Vérification post-stop
        $pid = trim(shell_exec("pgrep -f \"[n]ew_bestway_spa_daemon.py\" | head -n 1"));
        self::dbg('[deamon_stop] post-check pid=' . ($pid !== '' ? $pid : '(none)'));
    }

    /* ============================================================
     *  CYCLE DE VIE / postSave
     * ============================================================ */

    public function postSave() {
        self::dbg('[postSave] Début eqLogic #' . $this->getId() . ' name=' . $this->getName() . ' eqType=' . $this->getEqType_name());

        if ($this->getEqType_name() != 'new_bestway_spa') {
            self::dbg('[postSave] Correction eqType_name => new_bestway_spa');
            $this->setEqType_name('new_bestway_spa');
            $this->save();
            self::dbg('[postSave] eqType_name corrected + saved');
        }

        try {
            self::dbg('[postSave] calling createDefaultCommands()');
            $this->createDefaultCommands();
            self::dbg('[postSave] createDefaultCommands() OK');
        } catch (Exception $e) {
            self::err('[postSave] Erreur createDefaultCommands : ' . $e->getMessage());
        }

        self::dbg('[postSave] Fin.');
    }

    /* ============================================================
     *  COMMANDES PAR DÉFAUT (création + réparation)
     * ============================================================ */

    public function createDefaultCommands() {
        self::dbg('[createDefaultCommands] start eqLogic #' . $this->getId());

        $validInfoSubTypes = array('string', 'numeric', 'binary');
        $validActionSubTypes = array('other', 'slider', 'color', 'message', 'select');

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

        $createdInfo = 0;
        $updatedInfo = 0;

        foreach ($infos as $logicalId => $config) {
            list($name, $subtype, $unit, $historize) = $config;

            self::dbg("[createDefaultCommands] info loop logicalId=$logicalId name=$name subtype=$subtype unit=$unit historize=$historize");

            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new new_bestway_spaCmd();
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $createdInfo++;
                self::dbg("[createDefaultCommands] created info cmd logicalId=$logicalId");
            } else {
                $updatedInfo++;
                self::dbg("[createDefaultCommands] found existing info cmd logicalId=$logicalId id=" . $cmd->getId());
            }

            if (!in_array($subtype, $validInfoSubTypes)) {
                self::wrn("[createDefaultCommands] invalid info subType '$subtype' for '$logicalId' => fallback string");
                $subtype = 'string';
            }

            $cmd->setName($name);
            $cmd->setType('info');
            $cmd->setSubType($subtype);
            $cmd->setIsHistorized($historize ? 1 : 0);
            $cmd->setUnite($unit != '' ? $unit : '');
            $cmd->save();

            self::dbg("[createDefaultCommands] saved info cmd logicalId=$logicalId type=info subtype=$subtype historize=" . ($historize ? '1' : '0'));
        }

        $actions = array(
            'set_power'     => array('Alimentation ON/OFF', 'other',  ''),
            'set_heating'   => array('Activer chauffage',   'other',  ''),
            'set_filtering' => array('Activer filtration',  'other',  ''),
            'set_hydrojet'  => array('Activer hydrojet',    'other',  ''),
            'bubble_mode'   => array('Mode bulles',         'select', '0|Off;1|L1;2|L2'),
        );

        $createdAct = 0;
        $updatedAct = 0;

        foreach ($actions as $logicalId => $config) {
            list($name, $subtype, $listValue) = $config;

            self::dbg("[createDefaultCommands] action loop logicalId=$logicalId name=$name subtype=$subtype listValue=$listValue");

            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new new_bestway_spaCmd();
                $cmd->setEqLogic_id($this->getId());
                $cmd->setLogicalId($logicalId);
                $createdAct++;
                self::dbg("[createDefaultCommands] created action cmd logicalId=$logicalId");
            } else {
                $updatedAct++;
                self::dbg("[createDefaultCommands] found existing action cmd logicalId=$logicalId id=" . $cmd->getId());
            }

            if (!in_array($subtype, $validActionSubTypes)) {
                self::wrn("[createDefaultCommands] invalid action subType '$subtype' for '$logicalId' => fallback other");
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
            self::dbg("[createDefaultCommands] saved action cmd logicalId=$logicalId subtype=$subtype");
        }

        self::dbgArr('[createDefaultCommands] summary:', array(
            'info_created' => $createdInfo,
            'info_updated' => $updatedInfo,
            'action_created' => $createdAct,
            'action_updated' => $updatedAct,
        ));

        self::dbg('[createDefaultCommands] end');
    }

    /* ============================================================
     *  RECEPTION DU DEMON (PUSH ETAT)
     * ============================================================ */

    public static function updateFromDaemon($spaId, $data) {
        self::dbg('[updateFromDaemon] start spaId=' . $spaId);
        self::dbgArr('[updateFromDaemon] payload:', $data);

        $eq = self::byLogicalId($spaId, 'new_bestway_spa');
        if (!is_object($eq)) {
            self::wrn("[updateFromDaemon] Equipement introuvable pour spaId $spaId");
            return;
        }

        foreach ($data as $key => $value) {
            self::dbg("[updateFromDaemon] processing key=$key raw=" . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $cmd = $eq->getCmd('info', $key);
            if (!is_object($cmd)) {
                self::wrn("[updateFromDaemon] Commande info '$key' inexistante, ignorée.");
                continue;
            }

            $subType = $cmd->getSubType();
            $val = $value;

            if (is_bool($val)) {
                $val = $val ? 1 : 0;
            }

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

            if ($subType == 'numeric') {
                if ($val === '' || $val === null) {
                    $val = 0;
                }
                $val = floatval($val);
            }

            self::dbg("[updateFromDaemon] cmd=" . $cmd->getLogicalId() . " subtype=$subType => event(" . json_encode($val) . ")");
            $cmd->event($val);
        }

        self::dbg('[updateFromDaemon] end');
    }

    public static function createOrUpdateSpa($spaId, $data) {
        self::dbg('[createOrUpdateSpa] start spaId=' . $spaId);
        self::dbgArr('[createOrUpdateSpa] data:', $data);

        $eq = self::byLogicalId($spaId, 'new_bestway_spa');
        if (!is_object($eq)) {
            self::inf("[createOrUpdateSpa] Création équipement '$spaId'");

            $eq = new self();
            $eq->setLogicalId($spaId);
            $eq->setEqType_name('new_bestway_spa');

            $eq->setName('Mon SPA');
            $eq->setIsEnable(1);
            $eq->setIsVisible(1);
            $eq->setConfiguration('spa_id', $spaId);

            $eq->save();
            self::dbg("[createOrUpdateSpa] equipment created id=" . $eq->getId());
        } else {
            self::dbg("[createOrUpdateSpa] equipment exists id=" . $eq->getId());
        }

        $eq->createDefaultCommands();
        self::updateFromDaemon($spaId, $data);

        self::dbg('[createOrUpdateSpa] end');
    }
}

/* ============================================================
 *  CLASS CMD
 * ============================================================ */

class new_bestway_spaCmd extends cmd {

    public function execute($_options = array()) {
        log::add('new_bestway_spa', 'debug', '[cmd.execute] start logicalId=' . $this->getLogicalId());
        log::add('new_bestway_spa', 'debug', '[cmd.execute] options=' . json_encode($_options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $eq = $this->getEqLogic();
        if (!is_object($eq)) {
            log::add('new_bestway_spa', 'error', '[cmd.execute] Impossible de récupérer l\'équipement associé à la commande ' . $this->getLogicalId());
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
        log::add('new_bestway_spa', 'debug', '[cmd.execute] subType=' . $subType);

        if ($subType == 'select' && isset($_options['select'])) {
            $payload['value'] = $_options['select'];
        } elseif ($subType == 'slider' && isset($_options['slider'])) {
            $payload['value'] = $_options['slider'];
        } elseif ($subType == 'color' && isset($_options['color'])) {
            $payload['value'] = $_options['color'];
        } elseif ($subType == 'message' && isset($_options['message'])) {
            $payload['value'] = $_options['message'];
        }

        log::add('new_bestway_spa', 'debug', '[cmd.execute] payload=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $url = network::getNetworkAccess('internal') . '/plugins/new_bestway_spa/core/php/new_bestway_spa.api.php';

        $params = array(
            'apikey'  => jeedom::getApiKey('new_bestway_spa'),
            'payload' => json_encode($payload),
        );

        $paramsSafe = $params;
        $paramsSafe['apikey'] = '***';
        log::add('new_bestway_spa', 'debug', '[cmd.execute] url=' . $url);
        log::add('new_bestway_spa', 'debug', '[cmd.execute] paramsSafe=' . json_encode($paramsSafe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($result === false) {
            $curlError = curl_error($ch);
            log::add('new_bestway_spa', 'error', '[cmd.execute] Erreur CURL vers API new_bestway_spa : ' . $curlError);
        } else {
            log::add('new_bestway_spa', 'debug', '[cmd.execute] httpCode=' . $httpCode);
            log::add('new_bestway_spa', 'debug', '[cmd.execute] response=' . $result);
        }

        curl_close($ch);
        log::add('new_bestway_spa', 'debug', '[cmd.execute] end');
        return true;
    }
}