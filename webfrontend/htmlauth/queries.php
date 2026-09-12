<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");
$file_token = easee_get_token_file($lbplogdir);
$file_config = $lbpconfigdir.'/easee_config.ini';
$url_base = 'https://api.easee.com';
$chargers = [];

$config = json_decode(@file_get_contents($file_config), true);
if (!is_array($config)) {
	$config = array();
}

$accessToken = '';
if (file_exists($file_token)) {
	$token = json_decode(file_get_contents($file_token), true);
	if (is_array($token) && isset($token['accessToken'])) {
		$accessToken = $token['accessToken'];
		$data = get_req($url_base, '/api/chargers', $accessToken);
		if (is_array($data)) {
			foreach ($data as $charger) {
				if (isset($charger['id'])) {
					$chargers[] = $charger;
				}
			}
		}
	}
}

// Resolve site and circuit IDs per charger so the displayed Easee API request
// shows the exact endpoint (e.g. /api/sites/<siteId>/circuits/<circuitId>/...).
$chargerSites = array();
if ($accessToken !== '') {
	foreach ($chargers as $charger) {
		$cid = $charger['id'];
		$siteInfo = get_req($url_base, '/api/chargers/'.$cid.'/site', $accessToken);
		if (is_array($siteInfo) && isset($siteInfo['circuits'][0])) {
			$chargerSites[$cid] = array(
				'siteId'    => isset($siteInfo['circuits'][0]['siteId']) ? $siteInfo['circuits'][0]['siteId'] : '',
				'circuitId' => isset($siteInfo['circuits'][0]['id']) ? $siteInfo['circuits'][0]['id'] : ''
			);
		}
	}
}

// Compute the configured observation IDs used by the /state request.
$observationDefinitions = easee_get_observation_definitions();
$defaultObsIds = easee_get_default_observation_ids();
$stateIds = easee_get_requested_observation_ids(
	isset($config['observation_ids']) ? $config['observation_ids'] : '',
	$observationDefinitions,
	$defaultObsIds
);
$stateIdsCsv = implode(',', $stateIds);

// Metadata for every selectable command: HTTP method + Easee Cloud API endpoint,
// the relevant parameters and the matching Easee API documentation reference.
// See https://developer.easee.com/reference for the operation slugs.
$doc_base = 'https://developer.easee.com/reference/';
$doc_fallback = 'https://developer.easee.com/docs/integrations';

$commandMeta = array(
	'sites'               => array('method' => 'GET',  'api' => '/api/sites',                                             'doc' => 'site_getall',                          'params' => array()),
	'chargers'            => array('method' => 'GET',  'api' => '/api/chargers',                                          'doc' => 'charger_getall',                       'params' => array()),
	'site'                => array('method' => 'GET',  'api' => '/api/chargers/{id}/site',                                'doc' => 'charger_getchargersite',               'params' => array('id')),
	'config'              => array('method' => 'GET',  'api' => '/api/chargers/{id}/config',                              'doc' => 'charger_getcachedchargerconfig',       'params' => array('id')),
	'circuits'            => array('method' => 'GET',  'api' => '/api/sites/{siteId}/circuits/{circuitId}/settings',      'doc' => 'site_getcircuitsettings',              'params' => array('id')),
	'equalizer'           => array('method' => 'GET',  'api' => '/api/equalizers/{id}/state',                             'doc' => 'equalizer_getequalizer',               'params' => array('id')),
	'state'               => array('method' => 'GET',  'api' => '/state/{id}/observations?ids='.$stateIdsCsv,              'doc' => 'getobservations',                      'params' => array('id')),
	'latest'              => array('method' => 'GET',  'api' => '/api/chargers/{id}/sessions/latest',                     'doc' => 'chargers_getlastupdatedchargesession', 'params' => array('id')),
	'ongoing'             => array('method' => 'GET',  'api' => '/api/chargers/{id}/sessions/ongoing',                    'doc' => 'chargers_getongoingsessiondetails',    'params' => array('id')),
	'start_charging'      => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/start_charging',             'doc' => 'charger_startcharging',                'params' => array('id')),
	'stop_charging'       => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/stop_charging',              'doc' => 'charger_stop_charging',                'params' => array('id')),
	'pause_charging'      => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/pause_charging',             'doc' => 'charger_pausesession',                 'params' => array('id')),
	'resume_charging'     => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/resume_charging',            'doc' => 'charger_resumesession',                'params' => array('id')),
	'post_dynamicCurrent' => array('method' => 'POST', 'api' => '/api/sites/{siteId}/circuits/{circuitId}/dynamicCurrent','doc' => 'site_setdynamiccircuitcurrent',        'params' => array('id', 'value'), 'valueHint' => $L['QUERIES.VALUE_DYNCURRENT'], 'body' => '{"phase1":<A1>,"phase2":<A2>,"phase3":<A3>,"timeToLive":14400}'),
	'post_dynamicPower'   => array('method' => 'POST', 'api' => '/api/sites/{siteId}/circuits/{circuitId}/dynamicCurrent','doc' => 'site_setdynamiccircuitcurrent',        'params' => array('id', 'value', 'hys1to3', 'hys3to1'), 'valueHint' => $L['QUERIES.VALUE_DYNPOWER'], 'body' => '{"phase1":<A>,"phase2":<A>,"phase3":<A>,"timeToLive":14400}'),
	'post_lock_state'     => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/lock_state',                 'doc' => 'charger_set_cable_lock_state',         'params' => array('id', 'value'), 'valueHint' => $L['QUERIES.VALUE_LOCK'], 'body' => '{"state":<value>}'),
	'post_settings'       => array('method' => 'POST', 'api' => '/api/chargers/{id}/settings',                           'doc' => 'charger_setchargersetting',            'params' => array('id', 'type', 'value'), 'valueHint' => $L['QUERIES.VALUE_SETTINGS'], 'body' => '{"<type>":<value>}'),
	'reboot'              => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/reboot',                     'doc' => 'charger_reboot',                       'params' => array('id')),
	'force_reboot'        => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/force_reboot',               'doc' => 'charger_reboot',                       'params' => array('id')),
	'poll_all'            => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/poll_all',                   'doc' => null,                                   'params' => array('id')),
	'poll_lifetimeenergy' => array('method' => 'POST', 'api' => '/api/chargers/{id}/commands/poll_lifetimeenergy',        'doc' => null,                                   'params' => array('id'))
);

$doOptions = array_keys($commandMeta);

// Attach per-command help text (used by the info popup) from the language file.
foreach ($commandMeta as $cmdName => &$cmdMeta) {
	$helpKey = 'QUERIES.CMD_' . $cmdName;
	$cmdMeta['help'] = isset($L[$helpKey]) ? $L[$helpKey] : '';
}
unset($cmdMeta);

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.SECOND'];
$navbar[2]['URL'] = 'log.php';
$navbar[3]['Name'] = $L['NAVBAR.THIRD'];
$navbar[3]['URL'] = 'queries.php';
$navbar[3]['active'] = True;

$copy_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M0 6.75C0 5.784.784 5 1.75 5h1.5a.75.75 0 0 1 0 1.5h-1.5a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-1.5a.75.75 0 0 1 1.5 0v1.5A1.75 1.75 0 0 1 9.25 16h-7.5A1.75 1.75 0 0 1 0 14.25Z"></path><path d="M5 1.75C5 .784 5.784 0 6.75 0h7.5C15.216 0 16 .784 16 1.75v7.5A1.75 1.75 0 0 1 14.25 11h-7.5A1.75 1.75 0 0 1 5 9.25Zm1.75-.25a.25.25 0 0 0-.25.25v7.5c0 .138.112.25.25.25h7.5a.25.25 0 0 0 .25-.25v-7.5a.25.25 0 0 0-.25-.25Z"></path></svg>';

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="Easee Home">';
echo '<p>'.$L['MAIN.INTRO1'].'</p><br>';

echo '<style>'
	.'.info-badge{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;line-height:1;text-align:center;border-radius:50%;background:#0079c1;color:#fff;font-size:11px;font-weight:bold;font-style:italic;font-family:serif;text-decoration:none;margin-left:6px;cursor:pointer;vertical-align:middle;}'
	.'.info-badge:hover{background:#005f96;}'
	.'.param-row{margin-top:10px;}'
	.'.param-desc{color:#555;}'
	.'.sec-head{font-size:11px;font-weight:bold;color:#777;text-transform:uppercase;letter-spacing:.04em;margin:0 0 4px;}'
	.'.sub-head{font-size:14px;font-weight:bold;margin:12px 0 4px;}'
	.'.cmdbox{display:flex;align-items:flex-start;gap:6px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:6px;padding:8px;overflow:hidden;}'
	.'.cmdbox code{white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;font-family:monospace;font-size:12px;flex:1 1 auto;min-width:0;}'
	.'.copy-btn{flex:0 0 auto;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;border:1px solid #d0d7de;border-radius:6px;background:#fff;cursor:pointer;}'
	.'.copy-btn:hover{background:#eef1f4;}'
	.'.copy-btn svg{fill:#24292f;display:block;}'
	.'.copy-ok{color:#1a7f37;font-size:12px;margin-left:6px;visibility:hidden;flex:0 0 auto;align-self:center;}'
	.'.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:100000;}'
	.'.modal-box{background:#fff;max-width:520px;width:calc(100% - 32px);margin:12vh auto;padding:16px 18px;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.35);position:relative;max-height:70vh;overflow:auto;}'
	.'.modal-close{position:absolute;top:4px;right:10px;border:none;background:transparent;font-size:24px;line-height:1;cursor:pointer;color:#555;}'
	.'.modal-title{margin:0 0 10px;font-size:16px;font-weight:bold;padding-right:24px;}'
	.'.modal-body{font-size:13px;line-height:1.45;color:#333;}'
	.'.modal-body a{color:#0079c1;}'
	.'.modal-meta{margin-top:10px;font-family:monospace;font-size:12px;color:#555;word-break:break-all;}'
	.'</style>';

// Heading + intro.
echo '<p class="wide">'.$L['QUERIES.HEAD'].'</p>';
echo '<p><small>'.$L['QUERIES.INTRO'].'</small></p>';

// Command + parameters.
echo '<form action="/plugins/easee_home/easee.php" method="get" target="query_result" id="query_form">';
echo '<input type="hidden" name="query_view" value="1">';

echo '<fieldset style="margin-bottom:12px; padding:10px;">';
echo '<label for="do">'.$L['QUERIES.COMMAND'].' <a id="do-info" class="info-badge" href="javascript:void(0)" data-title="'.htmlspecialchars($L['QUERIES.COMMAND'], ENT_QUOTES).'">i</a></label>';
echo '<select name="do" id="do">';
foreach ($doOptions as $doOption) {
	echo '<option value="'.$doOption.'">'.$doOption.'</option>';
}
echo '</select>';

echo '<div id="params-wrap" style="margin-top:8px;">';

// id (charger selection).
echo '<div class="param-row" data-param="id">';
echo '<label for="id">id <a class="info-badge" href="javascript:void(0)" data-title="id" data-help="'.htmlspecialchars($L['QUERIES.PARAM_ID'], ENT_QUOTES).'">i</a></label>';
if (!empty($chargers)) {
	echo '<select name="id" id="id">';
	echo '<option value=""></option>';
	foreach ($chargers as $charger) {
		$label = isset($charger['name']) ? $charger['name'].' (' . $charger['id'] . ')' : $charger['id'];
		echo '<option value="'.htmlspecialchars($charger['id'], ENT_QUOTES).'">'.htmlspecialchars($label, ENT_QUOTES).'</option>';
	}
	echo '</select>';
} else {
	echo '<input data-inline="true" data-mini="true" name="id" id="id" value="" type="text">';
}
echo '<small class="param-desc">'.$L['QUERIES.PARAM_ID'].'</small>';
echo '</div>';

// type (post_settings only).
echo '<div class="param-row" data-param="type">';
echo '<label for="type">type <a class="info-badge" href="javascript:void(0)" data-title="type" data-help="'.htmlspecialchars($L['QUERIES.PARAM_TYPE'], ENT_QUOTES).'">i</a></label>';
echo '<input data-inline="true" data-mini="true" name="type" id="type" value="" type="text">';
echo '<small class="param-desc">'.$L['QUERIES.PARAM_TYPE'].'</small>';
echo '</div>';

// value.
echo '<div class="param-row" data-param="value">';
echo '<label for="value">value <a class="info-badge" id="value-info" href="javascript:void(0)" data-title="value" data-help="'.htmlspecialchars($L['QUERIES.PARAM_VALUE'], ENT_QUOTES).'">i</a></label>';
echo '<input data-inline="true" data-mini="true" name="value" id="value" value="" type="text">';
echo '<small class="param-desc" id="value-desc">'.$L['QUERIES.PARAM_VALUE'].'</small>';
echo '</div>';

// hys1to3 (post_dynamicPower only).
echo '<div class="param-row" data-param="hys1to3">';
echo '<label for="hys1to3">hys1to3 <a class="info-badge" href="javascript:void(0)" data-title="hys1to3" data-help="'.htmlspecialchars($L['QUERIES.PARAM_HYS1TO3'], ENT_QUOTES).'">i</a></label>';
echo '<input data-inline="true" data-mini="true" name="hys1to3" id="hys1to3" value="" type="text">';
echo '<small class="param-desc">'.$L['QUERIES.PARAM_HYS1TO3'].'</small>';
echo '</div>';

// hys3to1 (post_dynamicPower only).
echo '<div class="param-row" data-param="hys3to1">';
echo '<label for="hys3to1">hys3to1 <a class="info-badge" href="javascript:void(0)" data-title="hys3to1" data-help="'.htmlspecialchars($L['QUERIES.PARAM_HYS3TO1'], ENT_QUOTES).'">i</a></label>';
echo '<input data-inline="true" data-mini="true" name="hys3to1" id="hys3to1" value="" type="text">';
echo '<small class="param-desc">'.$L['QUERIES.PARAM_HYS3TO1'].'</small>';
echo '</div>';

echo '<small id="no-params" class="param-desc" style="display:none;">'.$L['QUERIES.NO_PARAMS'].'</small>';
echo '</div>'; // params-wrap

echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" data-icon="search" value="'.htmlspecialchars($L['QUERIES.RUN'], ENT_QUOTES).'"></center></p>';
echo '</fieldset>';
echo '</form>';

// Loxone Config command.
echo '<fieldset style="margin-bottom:12px; padding:10px;">';
echo '<p class="sec-head">'.$L['QUERIES.LOX_HEAD'].'</p>';
echo '<small>'.$L['QUERIES.LOX_HINT'].'</small>';
echo '<div class="sub-head">'.$L['QUERIES.LOX_BASE'].'</div>';
echo '<div class="cmdbox">';
echo '<code id="lox-base"></code>';
echo '<button type="button" data-role="none" class="copy-btn" data-copy-target="lox-base" data-ok="lox-base-ok" title="'.htmlspecialchars($L['QUERIES.COPY'], ENT_QUOTES).'">'.$copy_icon.'</button>';
echo '<span class="copy-ok" id="lox-base-ok">'.$L['QUERIES.COPIED'].'</span>';
echo '</div>';
echo '<div class="sub-head" id="lox-cmd-head">'.$L['QUERIES.LOX_CMD'].'</div>';
echo '<div class="cmdbox">';
echo '<code id="lox-cmd"></code>';
echo '<button type="button" data-role="none" class="copy-btn" data-copy-target="lox-cmd" data-ok="lox-ok" title="'.htmlspecialchars($L['QUERIES.COPY'], ENT_QUOTES).'">'.$copy_icon.'</button>';
echo '<span class="copy-ok" id="lox-ok">'.$L['QUERIES.COPIED'].'</span>';
echo '</div>';
echo '</fieldset>';

// Easee Cloud API request.
echo '<fieldset style="margin-bottom:12px; padding:10px;">';
echo '<p class="sec-head">'.$L['QUERIES.API_HEAD'].'</p>';
echo '<small>'.$L['QUERIES.API_HINT'].'</small>';
echo '<div class="cmdbox" style="margin-top:6px;">';
echo '<code id="api-cmd"></code>';
echo '<button type="button" data-role="none" class="copy-btn" data-copy-target="api-cmd" data-ok="api-ok" title="'.htmlspecialchars($L['QUERIES.COPY'], ENT_QUOTES).'">'.$copy_icon.'</button>';
echo '<span class="copy-ok" id="api-ok">'.$L['QUERIES.COPIED'].'</span>';
echo '</div>';
echo '</fieldset>';

// Response.
echo '<fieldset style="margin-bottom:12px; padding:10px;">';
echo '<p class="sec-head">'.$L['QUERIES.RESULT_HEAD'].'</p>';
echo '<iframe name="query_result" style="width:100%;min-height:360px;border:1px solid #ccc;background:#fff;"></iframe>';
echo '</fieldset>';

// Help popup (modal).
echo '<div id="help-modal" class="modal-overlay">';
echo '<div class="modal-box">';
echo '<button type="button" data-role="none" class="modal-close" id="help-close" aria-label="Close">&times;</button>';
echo '<div class="modal-title" id="help-title"></div>';
echo '<div class="modal-body" id="help-body"></div>';
echo '</div>';
echo '</div>';

echo '<p><small>'.$L['MAIN.INTRO1'].' <a href="'.htmlspecialchars($L['LINKS.WIKI'], ENT_QUOTES).'" target="_blank">LoxBerry Wiki</a> &middot; <a href="https://developer.easee.com/docs/integrations" target="_blank">Easee API</a></small></p>';

echo '<script>';
echo 'var COMMAND_META = '.json_encode($commandMeta, JSON_UNESCAPED_SLASHES).';';
echo 'var CHARGER_SITES = '.json_encode($chargerSites, JSON_UNESCAPED_SLASHES).';';
echo 'var DOC_BASE = '.json_encode($doc_base, JSON_UNESCAPED_SLASHES).';';
echo 'var DOC_FALLBACK = '.json_encode($doc_fallback, JSON_UNESCAPED_SLASHES).';';
echo 'var DOC_LINK_LABEL = '.json_encode($L['QUERIES.DOC_LINK'], JSON_UNESCAPED_SLASHES).';';
echo 'var LOX_CMD_VAR = '.json_encode($L['QUERIES.LOX_CMD_VAR'], JSON_UNESCAPED_SLASHES).';';
echo <<<'JS'
(function () {
	var doSel = document.getElementById('do');
	var idEl = document.getElementById('id');
	var typeEl = document.getElementById('type');
	var valueEl = document.getElementById('value');
	var hys1El = document.getElementById('hys1to3');
	var hys3El = document.getElementById('hys3to1');
	var doInfo = document.getElementById('do-info');
	var valueDesc = document.getElementById('value-desc');
	var valueInfo = document.getElementById('value-info');
	var valueDescDefault = valueDesc ? valueDesc.textContent : '';
	var valueHelpDefault = valueInfo ? (valueInfo.getAttribute('data-help') || '') : '';
	var loxCmd = document.getElementById('lox-cmd');
	var loxBase = document.getElementById('lox-base');
	var loxCmdHead = document.getElementById('lox-cmd-head');
	var loxCmdHeadBase = loxCmdHead ? loxCmdHead.textContent : '';
	var apiCmd = document.getElementById('api-cmd');
	var noParams = document.getElementById('no-params');
	var rows = {};
	var wrap = document.getElementById('params-wrap');
	var rowEls = wrap.querySelectorAll('.param-row');
	for (var i = 0; i < rowEls.length; i++) {
		rows[rowEls[i].getAttribute('data-param')] = rowEls[i];
	}

	function val(el) { return el ? el.value.trim() : ''; }

	function currentMeta() { return COMMAND_META[doSel.value] || { method: 'GET', api: '', doc: null, params: [] }; }

	function updateParams() {
		var meta = currentMeta();
		var active = meta.params || [];
		var name;
		for (name in rows) {
			if (rows.hasOwnProperty(name)) {
				rows[name].style.display = (active.indexOf(name) !== -1) ? '' : 'none';
			}
		}
		noParams.style.display = active.length ? 'none' : '';
		var vHint = meta.valueHint || valueDescDefault;
		if (valueDesc) { valueDesc.textContent = vHint; }
		if (valueInfo) { valueInfo.setAttribute('data-help', meta.valueHint || valueHelpDefault); }
		if (loxCmdHead) {
			loxCmdHead.textContent = loxCmdHeadBase + (active.indexOf('value') !== -1 ? LOX_CMD_VAR : '');
		}
	}

	function loxBaseUrl() {
		return window.location.protocol + '//' + window.location.host;
	}

	function loxCommandPath() {
		var meta = currentMeta();
		var active = meta.params || [];
		var q = ['do=' + encodeURIComponent(doSel.value)];
		if (active.indexOf('id') !== -1 && val(idEl)) { q.push('id=' + encodeURIComponent(val(idEl))); }
		if (active.indexOf('type') !== -1 && val(typeEl)) { q.push('type=' + encodeURIComponent(val(typeEl))); }
		if (active.indexOf('value') !== -1) { q.push('value=<v>'); }
		if (active.indexOf('hys1to3') !== -1 && val(hys1El)) { q.push('hys1to3=' + encodeURIComponent(val(hys1El))); }
		if (active.indexOf('hys3to1') !== -1 && val(hys3El)) { q.push('hys3to1=' + encodeURIComponent(val(hys3El))); }
		return '/plugins/easee_home/easee.php?' + q.join('&');
	}

	function notEmpty(v) { return v !== undefined && v !== null && v !== ''; }

	function buildBody(cmd) {
		if (cmd === 'post_settings') {
			return '{"' + (val(typeEl) || '<type>') + '":' + (val(valueEl) || '<value>') + '}';
		}
		if (cmd === 'post_lock_state') {
			return '{"state":' + (val(valueEl) || '<value>') + '}';
		}
		if (cmd === 'post_dynamicCurrent') {
			var parts = val(valueEl).split(',');
			var p1 = (parts[0] || '<A1>').trim();
			var p2 = (parts[1] || '<A2>').trim();
			var p3 = (parts[2] || '<A3>').trim();
			return '{"phase1":' + p1 + ',"phase2":' + p2 + ',"phase3":' + p3 + ',"timeToLive":14400}';
		}
		if (cmd === 'post_dynamicPower') {
			var raw = val(valueEl);
			var placeholder = '{"phase1":<A>,"phase2":<A>,"phase3":<A>,"timeToLive":14400}';
			if (!raw) { return placeholder; }
			var power = parseFloat(raw.replace(',', '.'));
			if (isNaN(power)) { return placeholder; }
			// Base conversion (kW -> A) mirroring easee.php; server-side hysteresis may adjust this.
			if (power < 4.14) {
				var a1 = Math.min(Math.round((power * 1000) / 230 * 100) / 100, 16);
				return '{"phase1":' + a1 + ',"phase2":0,"phase3":0,"timeToLive":14400}';
			}
			var a = Math.round((power * 1000) / (230 * 3) * 100) / 100;
			return '{"phase1":' + a + ',"phase2":' + a + ',"phase3":' + a + ',"timeToLive":14400}';
		}
		return null;
	}

	function apiCommand() {
		var meta = currentMeta();
		var idv = val(idEl);
		var site = CHARGER_SITES[idv] || {};
		var url = 'https://api.easee.com' + (meta.api || '');
		url = url.replace('{id}', idv || '{id}');
		url = url.replace('{siteId}', notEmpty(site.siteId) ? site.siteId : '{siteId}');
		url = url.replace('{circuitId}', notEmpty(site.circuitId) ? site.circuitId : '{circuitId}');
		var line = meta.method + ' ' + url;
		if (meta.body) {
			var body = buildBody(doSel.value);
			if (body) { line += '\nBody: ' + body; }
		}
		return line;
	}

	function refresh() {
		loxBase.textContent = loxBaseUrl();
		loxCmd.textContent = loxCommandPath();
		apiCmd.textContent = apiCommand();
	}

	function onChange() { updateParams(); refresh(); }

	// --- Help popup ---
	var modal = document.getElementById('help-modal');
	var modalTitle = document.getElementById('help-title');
	var modalBody = document.getElementById('help-body');
	var modalClose = document.getElementById('help-close');

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function showHelp(title, bodyHtml) {
		modalTitle.textContent = title || '';
		modalBody.innerHTML = bodyHtml || '';
		modal.style.display = 'block';
	}

	function closeHelp() { modal.style.display = 'none'; }

	function showCommandHelp() {
		var meta = currentMeta();
		var docUrl = meta.doc ? (DOC_BASE + meta.doc) : DOC_FALLBACK;
		var html = '<p>' + escapeHtml(meta.help || '') + '</p>';
		html += '<div class="modal-meta">' + escapeHtml(meta.method + ' https://api.easee.com' + (meta.api || '')) + '</div>';
		html += '<p style="margin-top:12px;"><a href="' + docUrl + '" target="_blank" rel="noopener">' + escapeHtml(DOC_LINK_LABEL) + ' &#8599;</a></p>';
		showHelp(doSel.value, html);
	}

	if (doInfo) {
		doInfo.addEventListener('click', function (e) { e.preventDefault(); showCommandHelp(); });
	}

	var paramBadges = document.querySelectorAll('.info-badge[data-help]');
	for (var p = 0; p < paramBadges.length; p++) {
		paramBadges[p].addEventListener('click', function (e) {
			e.preventDefault();
			showHelp(this.getAttribute('data-title') || '', '<p>' + escapeHtml(this.getAttribute('data-help') || '') + '</p>');
		});
	}

	modalClose.addEventListener('click', closeHelp);
	modal.addEventListener('click', function (e) { if (e.target === modal) { closeHelp(); } });
	document.addEventListener('keydown', function (e) {
		if ((e.key === 'Escape' || e.keyCode === 27) && modal.style.display === 'block') { closeHelp(); }
	});

	doSel.addEventListener('change', onChange);
	[idEl, typeEl, valueEl, hys1El, hys3El].forEach(function (el) {
		if (!el) { return; }
		el.addEventListener('input', refresh);
		el.addEventListener('change', refresh);
	});

	var btns = document.querySelectorAll('.copy-btn');
	for (var b = 0; b < btns.length; b++) {
		btns[b].addEventListener('click', function () {
			var target = document.getElementById(this.getAttribute('data-copy-target'));
			if (!target) { return; }
			var ok = document.getElementById(this.getAttribute('data-ok'));
			var text = target.textContent;
			var done = function () { if (ok) { ok.style.visibility = 'visible'; setTimeout(function () { ok.style.visibility = 'hidden'; }, 1500); } };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done, function () {});
			} else {
				var ta = document.createElement('textarea');
				ta.value = text; document.body.appendChild(ta); ta.select();
				try { document.execCommand('copy'); done(); } catch (e) {}
				document.body.removeChild(ta);
			}
		});
	}

	onChange();
})();
JS;
echo '</script>';
LBWeb::lbfooter();
?>
