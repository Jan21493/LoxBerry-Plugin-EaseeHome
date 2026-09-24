<?php
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "loxberry_web.php";
require_once "Config/Lite.php";
include $lbphtmldir.'/easee_functions.php';

$file_config = $lbpconfigdir.'/easee_config.ini';

// Save the log level. The rest of the config is left untouched.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_level'])) {
    $existing_config = json_decode(@file_get_contents($file_config), true);
    if (!is_array($existing_config)) {
        $existing_config = array();
    }
    $existing_config['log_level'] = easee_normalize_log_level($_POST['log_level']);
    file_put_contents($file_config, json_encode($existing_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    header('Location: log.php');
    exit;
}

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

$config = json_decode(@file_get_contents($file_config), true);
if (!is_array($config)) {
    $config = array();
}
if (!isset($config['log_level'])) {
    $config['log_level'] = 'info';
}

echo '<img src="logo.png" alt="Easee Home">';

echo '<style>'
    .'h1.status-h1{font-size:26px;font-weight:bold;margin:0 0 6px;}'
	.'h2.charger-head{font-size:20px;font-weight:bold;margin:22px 0 10px;}'
	.'h3.status-h3{font-size:15px;font-weight:bold;color:#333;margin:10px 0 8px;}'
	.'</style>';

// Logging level (moved here from the settings tab).
echo '<fieldset style="margin-bottom:12px; padding:10px;">';
echo '<h1 class="status-h1">' . $L['LOGGING.HEAD'] . '</h1>';
echo '<small>' . $L['LOGGING.DESC'] . '</small><br><br>';
echo '<form method="post" action="log.php">';
echo '<label for="log_level">' . $L['LOGGING.LEVEL'] . '</label>';
echo '<select name="log_level" id="log_level">';
$log_levels = array('error' => $L['LOGGING.ERROR'], 'warn' => $L['LOGGING.WARN'], 'info' => $L['LOGGING.INFO'], 'debug' => $L['LOGGING.DEBUG']);
foreach ($log_levels as $level => $label) {
    $selected = (easee_normalize_log_level($config['log_level']) === $level) ? ' selected' : '';
    echo '<option value="' . $level . '"' . $selected . '>' . $label . '</option>';
}
echo '</select>';
echo '<br><small>' . $L['LOGGING.HINT'] . '</small>';
echo '<p><input data-role="button" data-inline="true" data-mini="true" type="submit" data-icon="check" value="' . $L['MAIN.SAVE'] . '"></p>';
echo '</form>';
echo '</fieldset>';

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
