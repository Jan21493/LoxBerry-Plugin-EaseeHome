<?php
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";

$L = LBSystem::readlanguage("language.ini");

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.SETTINGS'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.STATUS'];
$navbar[2]['URL'] = 'status.php';
$navbar[3]['Name'] = $L['NAVBAR.TESTAREA'];
$navbar[3]['URL'] = 'queries.php';
$navbar[4]['Name'] = $L['NAVBAR.LOG'];
$navbar[4]['URL'] = 'log.php';

$navbar[4]['active'] = True;

LBWeb::lbheader($template_title, $helplink, $helptemplate);

echo '<img src="logo.png" alt="Easee Home">';

echo '<style>'
    .'h1.status-h1{font-size:26px;font-weight:bold;margin:0 0 6px;}'
	.'h2.charger-head{font-size:20px;font-weight:bold;margin:22px 0 10px;}'
	.'h3.status-h3{font-size:15px;font-weight:bold;color:#333;margin:10px 0 8px;}'
	.'</style>';

// Logfiles, grouped like in the TeslaCmd plugin: one group per log name.
echo '<h1 class="status-h1">' . $L['LOGFILES.HEAD'] . '</h1>';

echo '<h2 class="charger-head">Easee API Calls</h2>';
echo '<small>' . $L['LOGFILES.GROUP_API_DESC'] . '</small>';
echo LBWeb::loglist_html(array('NAME' => 'Easee API Calls'));

//echo '<h2 class="charger-head">Testbereich</h2>';
//echo '<small>' . $L['LOGFILES.GROUP_TEST_DESC'] . '</small>';
//echo LBWeb::loglist_html(array('NAME' => 'Testbereich'));

//echo '<h2 class="charger-head">Sonstiges</h2>';
//echo '<small>' . $L['LOGFILES.GROUP_OTHER_DESC'] . '</small>';
//echo LBWeb::loglist_html(array('NAME' => 'Sonstiges'));

LBWeb::lbfooter();
?>
