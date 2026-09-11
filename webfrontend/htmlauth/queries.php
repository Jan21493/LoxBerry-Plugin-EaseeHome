<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");
$file_token = easee_get_token_file($lbplogdir);
$url_base = 'https://api.easee.com';
$chargers = [];

if (file_exists($file_token)) {
	$token = json_decode(file_get_contents($file_token), true);
	if (is_array($token) && isset($token['accessToken'])) {
		$data = get_req($url_base, '/api/chargers', $token['accessToken']);
		if (is_array($data)) {
			foreach ($data as $charger) {
				if (isset($charger['id'])) {
					$chargers[] = $charger;
				}
			}
		}
	}
}

$doOptions = [
	'sites',
	'chargers',
	'site',
	'config',
	'circuits',
	'equalizer',
	'state',
	'latest',
	'ongoing',
	'start_charging',
	'stop_charging',
	'pause_charging',
	'resume_charging',
	'post_dynamicCurrent',
	'post_dynamicPower',
	'post_lock_state',
	'post_settings',
	'reboot',
	'force_reboot',
	'poll_all',
	'poll_lifetimeenergy'
];

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

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="Easee Home">';
echo '<p>'.$L['MAIN.INTRO1'].'</p><br>';
echo '<p class="wide">'.$L['NAVBAR.THIRD'].'</p>';
echo '<form action="/plugins/easee_home/easee.php" method="get" target="query_result">';
echo '<input type="hidden" name="query_view" value="1">';
echo '<label for="do">do</label>';
echo '<select name="do" id="do">';
foreach ($doOptions as $doOption) {
	echo '<option value="'.$doOption.'">'.$doOption.'</option>';
}
echo '</select>';
echo '<label for="id">id</label>';
if (!empty($chargers)) {
	echo '<select name="id" id="id">';
	echo '<option value=""></option>';
	foreach ($chargers as $charger) {
		$label = isset($charger['name']) ? $charger['name'].' (' . $charger['id'] . ')' : $charger['id'];
		echo '<option value="'.$charger['id'].'">'.$label.'</option>';
	}
	echo '</select>';
} else {
	echo '<input data-inline="true" data-mini="true" name="id" id="id" value="" type="text">';
}
echo '<label for="type">type (nur für post_settings)</label>';
echo '<input data-inline="true" data-mini="true" name="type" id="type" value="" type="text">';
echo '<label for="value">value</label>';
echo '<input data-inline="true" data-mini="true" name="value" id="value" value="" type="text">';
echo '<label for="hys1to3">hys1to3 (optional, nur für post_dynamicPower)</label>';
echo '<input data-inline="true" data-mini="true" name="hys1to3" id="hys1to3" value="" type="text">';
echo '<label for="hys3to1">hys3to1 (optional, nur für post_dynamicPower)</label>';
echo '<input data-inline="true" data-mini="true" name="hys3to1" id="hys3to1" value="" type="text">';
echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" data-icon="search" value="Abfrage ausführen"></center></p>';
echo '</form>';
echo '<p><small>Parameter je Funktion: do + id, optional type/value und hys1to3/hys3to1.</small></p>';
echo '<p><small><b>type</b> wird nur bei <code>do=post_settings</code> genutzt und enthält den Einstellungsnamen (z. B. <code>maxChargerCurrent</code>).</small></p>';
echo '<p><small><b>value</b> enthält den Zielwert für den gewählten Befehl (z. B. <code>post_settings</code>, <code>post_lock_state</code>, <code>post_dynamicCurrent</code>, <code>post_dynamicPower</code>).</small></p>';
echo '<p><small>Weitere Infos: <a href="https://wiki.loxberry.de/plugins/easee_home_wallbox/start" target="_blank">LoxBerry Wiki</a> und <a href="https://developer.easee.com/docs/integrations" target="_blank">Easee API Doku</a>.</small></p>';
echo '<iframe name="query_result" style="width:100%;min-height:420px;border:1px solid #ccc;background:#fff;"></iframe>';
LBWeb::lbfooter();
?>
