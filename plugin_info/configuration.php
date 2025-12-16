<?php
if (!isConnect()) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<form class="form-horizontal">
  <fieldset>
    <legend><i class="fas fa-cog"></i> {{Paramètres Cloud Bestway / SmartHub}}</legend>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{API Host}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="api_host" placeholder="smarthub-eu.bestwaycorp.com" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Visitor ID}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="visitor_id" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Client ID (FCM / push)}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="client_id" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Registration ID}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="registration_id" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Device ID}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="device_id" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Product ID}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="product_id" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Push type}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="push_type" placeholder="fcm" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Location (pays)}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="location" placeholder="GB" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Timezone}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="timezone" placeholder="GMT" />
      </div>
    </div>

    <div class="form-group">
      <label class="col-sm-4 control-label">{{Période de rafraîchissement (s)}}</label>
      <div class="col-sm-6">
        <input class="configKey form-control" data-l1key="refresh_interval" placeholder="30" />
      </div>
    </div>

  </fieldset>
</form>
