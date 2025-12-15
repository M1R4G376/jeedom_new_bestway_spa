<?php
if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
}
$plugin = plugin::byId('new_bestway_spa');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>


<div class="row row-overflow">

    <!-- ===================== LISTE DES ÉQUIPEMENTS ===================== -->
    <div class="col-xs-12 eqLogicThumbnailDisplay">
         <!-- Gestion -->
        <legend><i class="fas fa-plus-circle"></i> {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <!-- Lien vers configuration du plugin -->
            <div class="cursor logoSecondary eqLogicAction" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br/>
                <span>{{Configuration}}</span>
            </div>
        </div>
    </div>

 
    <legend><i class="fas fa-spa"></i> {{Mes spas Bestway}}</legend>

        <!-- Barre de recherche (gérée par plugin.template.js) -->
        <div class="input-group" style="margin-bottom:5px;">
            <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
            <div class="input-group-btn">
                <a id="bt_resetEqlogicSearch" class="btn roundedRight" style="width:30px">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>

        <!-- Tuiles des équipements -->
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : ' disableCard';
                $img = $eqLogic->getImage(); // icône standard Jeedom de l’équipement
                ?>
                <div class="eqLogicDisplayCard cursor<?php echo $opacity; ?>"
                     data-eqLogic_id="<?php echo $eqLogic->getId(); ?>">
                    <img class="lazy" src="<?php echo $img; ?>" style="min-height:75px !important;" />
                    <br/>
                    <span class="name"><?php echo $eqLogic->getHumanName(true, true); ?></span>
                </div>
            <?php } ?>
        </div>
        
    <!-- ===================== PAGE ÉQUIPEMENT ===================== -->
    <div class="col-xs-12 eqLogic" style="display:none;">

        <!-- Barre d’actions en haut à droite -->
        <div class="input-group pull-right" style="display:inline-flex;margin-top:10px;">
            <span class="input-group-btn">
                <a class="btn btn-default btn-sm eqLogicAction roundedLeft"
                   data-action="configure">
                    <i class="fas fa-cogs"></i> {{Configuration avancée}}
                </a>
                <a class="btn btn-success btn-sm eqLogicAction"
                   data-action="save">
                    <i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a>
                <a class="btn btn-danger btn-sm eqLogicAction roundedRight"
                   data-action="remove">
                    <i class="fas fa-trash"></i> {{Supprimer}}
                </a>
            </span>
        </div>

        <!-- Onglets -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation">
                <a class="eqLogicAction cursor" data-action="returnToThumbnailDisplay">
                    <i class="fas fa-arrow-circle-left"></i>
                </a>
            </li>
            <li role="presentation" class="active">
                <a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab">
                    <i class="fas fa-tachometer-alt"></i> {{Équipement}}
                </a>
            </li>
            <li role="presentation">
                <a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab">
                    <i class="fas fa-list-alt"></i> {{Commandes}}
                </a>
            </li>
        </ul>

        <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x:hidden;">

            <!-- ===================== ONGLET ÉQUIPEMENT ===================== -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <br/>
                <div class="row">

                    <!-- Colonne gauche : configuration Jeedom standard -->
                    <div class="col-sm-6">
                        <form class="form-horizontal">
                            <fieldset>

                                <!-- ID caché -->
                                <input type="hidden" class="eqLogicAttr" data-l1key="id"/>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                                    <div class="col-sm-6">
                                        <input type="text" class="eqLogicAttr form-control"
                                               data-l1key="name"
                                               placeholder="{{Nom de l'équipement}}"/>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Objet parent}}</label>
                                    <div class="col-sm-6">
                                        <select id="sel_object" class="eqLogicAttr form-control"
                                                data-l1key="object_id">
                                            <option value="">{{Aucun}}</option>
                                            <?php
                                            foreach ((jeeObject::buildTree(null, false)) as $object) {
                                                echo '<option value="' . $object->getId() . '">'
                                                    . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber'))
                                                    . $object->getName()
                                                    . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">&nbsp;</label>
                                    <div class="col-sm-6">
                                        <label class="checkbox-inline">
                                            <input type="checkbox" class="eqLogicAttr"
                                                   data-l1key="isEnable" checked/>
                                            {{Activer}}
                                        </label>
                                        <label class="checkbox-inline">
                                            <input type="checkbox" class="eqLogicAttr"
                                                   data-l1key="isVisible" checked/>
                                            {{Visible}}
                                        </label>
                                    </div>
                                </div>

                                <br/>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                    <div class="col-sm-8">
                                        <?php
                                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                            echo '<label class="checkbox-inline">';
                                            echo '<input type="checkbox" class="eqLogicAttr" '
                                                . 'data-l1key="category" data-l2key="' . $key . '" />'
                                                . $value['name'];
                                            echo '</label>';
                                        }
                                        ?>
                                    </div>
                                </div>

                            </fieldset>
                        </form>
                    </div>

                    <!-- Colonne droite : état du spa (affichage info) -->
                    <div class="col-sm-6">
                        <form class="form-horizontal">
                            <fieldset>
                                <legend>{{État du spa}}</legend>

                                <!--
                                    Les <span> sont mis à jour côté JS
                                    à partir des commandes info correspondantes.
                                    On les identifie par data-logicalid.
                                -->

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Température eau}}</label>
                                    <div class="col-sm-8">
                                        <span class="spa-state" data-logicalid="water_temperature">-</span> °C
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Température cible}}</label>
                                    <div class="col-sm-8">
                                        <span class="spa-state" data-logicalid="temperature_setting">-</span> °C
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Chauffage}}</label>
                                    <div class="col-sm-8">
                                        <span class="spa-state" data-logicalid="heater_state">-</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Bulles}}</label>
                                    <div class="col-sm-8">
                                        <span class="spa-state" data-logicalid="wave_state">-</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Filtration}}</label>
                                    <div class="col-sm-8">
                                        <span class="spa-state" data-logicalid="filter_state">-</span>
                                    </div>
                                </div>

                            </fieldset>
                        </form>
                    </div>

                </div>
            </div>

            <!-- ===================== ONGLET COMMANDES ===================== -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <br/>
                <legend><i class="fas fa-list-alt"></i> {{Tableau de commandes}}</legend>

                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                    <tr>
                        <th>{{Nom}}</th>
                        <th>{{Type}}</th>
                        <th>{{Paramètres}}</th>
                        <th>{{Action}}</th>
                    </tr>
                    </thead>
                    <tbody>
                        <!-- Rempli automatiquement par addCmdToTable() côté JS -->
                    </tbody>
                </table>

                <a class="btn btn-success btn-sm cmdAction" data-action="add">
                    <i class="fas fa-plus-circle"></i> {{Ajouter une commande}}
                </a>
            </div>

        </div>
    </div>

</div>

<?php
// Gestion générique des eqLogics / commandes
include_file('core', 'plugin.template', 'js');
// JS spécifique du plugin
include_file('desktop', 'new_bestway_spa', 'js', 'new_bestway_spa');

?>