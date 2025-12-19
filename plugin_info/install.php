<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function new_bestway_spa_install() {
    log::add('new_bestway_spa', 'info', 'Installation du plugin Bestway Spa');

    // Optionnel : lancer automatiquement les dépendances si elles ne sont pas OK
    try {
        $dependencyInfo = new_bestway_spa::dependancy_info();
        if (isset($dependencyInfo['state']) && $dependencyInfo['state'] == 'nok') {
            $plugin = plugin::byId('new_bestway_spa');
            $plugin->dependancy_install();
        }
    } catch (Throwable $e) {
        log::add('new_bestway_spa', 'warning', 'Auto-install dépendances impossible : ' . $e->getMessage());
    }
}

function new_bestway_spa_update() {
    log::add('new_bestway_spa', 'info', 'Mise à jour du plugin Bestway Spa');

    // Optionnel : relancer dépendances si besoin
    try {
        $dependencyInfo = new_bestway_spa::dependancy_info();
        if (isset($dependencyInfo['state']) && $dependencyInfo['state'] == 'nok') {
            $plugin = plugin::byId('new_bestway_spa');
            $plugin->dependancy_install();
        }
    } catch (Throwable $e) {
        log::add('new_bestway_spa', 'warning', 'Auto-update dépendances impossible : ' . $e->getMessage());
    }
}

function new_bestway_spa_remove() {
    log::add('new_bestway_spa', 'info', 'Suppression du plugin Bestway Spa');
}