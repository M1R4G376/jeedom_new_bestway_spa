/* eslint-env jquery, browser */
/* global jeedom, init, is_numeric */

// Type d'équipement Jeedom pour ce plugin
var eqType = 'new_bestway_spa'

// ---------------------------------------------------------------------------
//  NAVIGATION / LISTE DES EQLOGIC
// ---------------------------------------------------------------------------

$('#bt_resetEqlogicSearch').off('click').on('click', function () {
  $('#in_searchEqlogic').val('')
  $('#in_searchEqlogic').keyup()
})

$('#in_searchEqlogic').off('keyup').on('keyup', function () {
  var search = $(this).val().toLowerCase()
  if (search === '') {
    $('.eqLogicDisplayCard').show()
    return
  }
  $('.eqLogicDisplayCard').hide()
  $('.eqLogicDisplayCard .name').each(function () {
    var text = $(this).text().toLowerCase()
    if (text.indexOf(search) >= 0) {
      $(this).closest('.eqLogicDisplayCard').show()
    }
  })
})

// ---------------------------------------------------------------------------
//  TABLE DES COMMANDES
// ---------------------------------------------------------------------------

$('#table_cmd').sortable({
  axis: 'y',
  cursor: 'move',
  items: '.cmd',
  placeholder: 'ui-state-highlight',
  tolerance: 'intersect',
  forcePlaceholderSize: true
})

$('#tab_cmd').off('click', '.cmdAction[data-action=add]').on('click', '.cmdAction[data-action=add]', function () {
  addCmdToTable()
})

$('#table_cmd').off('click', '.cmdAction[data-action=remove]').on('click', '.cmdAction[data-action=remove]', function () {
  $(this).closest('tr.cmd').remove()
})

// ---------------------------------------------------------------------------
//  FONCTION STANDARD : addCmdToTable
// ---------------------------------------------------------------------------

function addCmdToTable (_cmd) {
  if (!isset(_cmd)) _cmd = {}
  if (!isset(_cmd.configuration)) _cmd.configuration = {}

  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'

  // Nom + icône
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display:none;">'
  tr += '<div class="row">'
  tr += '  <div class="col-sm-6">'
  tr += '    <input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '  </div>'
  tr += '  <div class="col-sm-6">'
  tr += '    <a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon">'
  tr += '      <i class="fas fa-flag"></i> {{Icône}}'
  tr += '    </a>'
  tr += '    <span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left:10px;"></span>'
  tr += '  </div>'
  tr += '</div>'
  tr += '</td>'

  // Type / Sous-type
  tr += '<td>'
  tr += '  <span class="label label-primary cmdAttr" data-l1key="type"></span> / '
  tr += '  <span class="label label-info cmdAttr" data-l1key="subType"></span>'
  tr += '</td>'

  // Paramètres (logicalId, unité)
  tr += '<td>'
  tr += '  <span class="cmdAttr" data-l1key="logicalId"></span>'
  tr += '  <span class="cmdAttr" data-l1key="unite" style="margin-left:5px;"></span>'
  tr += '</td>'

  // Actions
  tr += '<td>'
  tr += '  <input class="cmdAttr form-control input-sm" data-l1key="type" style="display:none;">'
  tr += '  <input class="cmdAttr form-control input-sm" data-l1key="subType" style="display:none;">'

  tr += '  <span><label class="checkbox-inline">'
  tr += '    <input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked />{{Afficher}}'
  tr += '  </label></span> '

  tr += '  <span><label class="checkbox-inline">'
  tr += '    <input type="checkbox" class="cmdAttr" data-l1key="isHistorized" />{{Historiser}}'
  tr += '  </label></span> '

  if (is_numeric(_cmd.id)) {
    tr += '  <a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure">'
    tr += '    <i class="fas fa-cogs"></i>'
    tr += '  </a> '
    tr += '  <a class="btn btn-default btn-xs cmdAction" data-action="test">'
    tr += '    <i class="fas fa-rss"></i> {{Tester}}'
    tr += '  </a> '
  }

  tr += '  <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>'
  tr += '</td>'
  tr += '</tr>'

  $('#table_cmd tbody').append(tr)
  var $last = $('#table_cmd tbody tr:last')

  $last.setValues(_cmd, '.cmdAttr')
  jeedom.cmd.changeType($last, init(_cmd.subType))

  if (_cmd.id !== undefined && init(_cmd.type) === 'info') {
    jeedom.cmd.execute({
      id: _cmd.id,
      cache: 0,
      notify: false
    })
  }
}

// ---------------------------------------------------------------------------
//  HOOKS OPTIONNELS POUR EQLOGIC
// ---------------------------------------------------------------------------

function saveEqLogic (_eqLogic) {
  return _eqLogic
}

function printEqLogic (_eqLogic) {
  if (!isset(_eqLogic.cmd)) return

  $('.spa-state').each(function () {
    $(this).text('-')
  })

  for (var i in _eqLogic.cmd) {
    (function (cmd) {
      if (cmd.type !== 'info') return
      var logicalId = cmd.logicalId
      var $span = $('.spa-state[data-logicalid="' + logicalId + '"]')
      if (!$span.length) return

      jeedom.cmd.execute({
        id: cmd.id,
        cache: 0,
        notify: false,
        success: function (result) {
          $span.text(result)
        }
      })

      jeedom.cmd.update[cmd.id] = function (_options) {
        $span.text(_options.display_value)
      }
    })(_eqLogic.cmd[i])
  }
}

// ---------------------------------------------------------------------------
//  AFFICHAGE FICHE / TUYLES - déplace la fiche hors du conteneur masqué
// ---------------------------------------------------------------------------

$(function () {
  console.log('🧠 Initialisation des événements du plugin Bestway SPA')

  // --- clic sur une tuile ---
  $(document).off('click', '.eqLogicDisplayCard').on('click', '.eqLogicDisplayCard', function () {
    var eqLogic_id = $(this).attr('data-eqLogic_id')
    console.log('🌊 Clic détecté sur l’équipement ID:', eqLogic_id)

    // ✅ Déplacer la fiche hors du bloc masqué (row-overflow)
    if ($('.eqLogic').parent().hasClass('row-overflow')) {
      $('.eqLogic').appendTo('#div_pageContainer')
    }

    // Masquer la liste et afficher la fiche
    $('.row-overflow').fadeOut(200, function () {
      $('.eqLogic').fadeIn(200)
      $('html, body').animate({ scrollTop: 0 }, 'fast')
    })

    // Charger les infos de l’équipement
    jeedom.eqLogic.byId({
      id: eqLogic_id,
      error: function (error) {
        $('#div_alert').showAlert({ message: error.message, level: 'danger' })
      },
      success: function (data) {
        $('.eqLogicAttr').setValues(data, '.eqLogicAttr')
        printEqLogic(data)
        $('#eqlogic-tab a[href="#eqlogictab"]').click()
      }
    })
  })

  // --- retour à la liste ---
  $(document).off('click', '.eqLogicAction[data-action=returnToThumbnailDisplay]')
    .on('click', '.eqLogicAction[data-action=returnToThumbnailDisplay]', function () {
      console.log('↩️ Retour à la liste des équipements')

      $('.eqLogic').fadeOut(200, function () {
        $('.row-overflow').fadeIn(200)
        $('html, body').animate({ scrollTop: 0 }, 'fast')
      })
    })
})