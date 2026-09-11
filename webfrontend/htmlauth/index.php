<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");
$file_token  = $lbplogdir.'/easee_token.ini';
$file_config = $lbpconfigdir.'/easee_config.ini';
$file_log_e  = $lbplogdir.'/easee-error.log';
$file_log_i  = $lbplogdir.'/easee-info.log';
$url_base    = 'https://api.easee.cloud';
$url_tocken  = '/api/accounts/login';

if ($_POST) {
    $return_json = (isset($_POST['return_json']) && $_POST['return_json'] === 'on') ? '1' : '0';
    $return_mqtt = (isset($_POST['return_mqtt']) && $_POST['return_mqtt'] === 'on') ? '1' : '0';
    $return_udp = (isset($_POST['return_udp']) && $_POST['return_udp'] === 'on') ? '1' : '0';

    $log_level = easee_normalize_log_level(isset($_POST['log_level']) ? $_POST['log_level'] : 'info');
    $observation_ids = isset($_POST['observation_ids']) ? trim($_POST['observation_ids']) : '';

    if (file_exists($file_token)) {
        unlink($file_token);
    }

    $data = array(
        'user' => array(
            'username' => isset($_POST['username']) ? $_POST['username'] : '',
            'password' => isset($_POST['password']) ? $_POST['password'] : ''
        ),
        'miniserver' => array(
            'ip' => isset($_POST['miniserver']) ? $_POST['miniserver'] : '',
            'port' => isset($_POST['udpport']) ? $_POST['udpport'] : '0'
        ),
        'send_html' => '0',
        'send_udp' => $return_udp,
        'send_json' => $return_json,
        'send_mqtt' => $return_mqtt,
        'log_level' => $log_level,
        'observation_ids' => $observation_ids
    );

    file_put_contents($file_config, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    get_token($url_base, $url_tocken, $file_token, $data['user']['username'], $data['user']['password']);
    header("Location: timer.php");
    exit;
}

$config = json_decode(@file_get_contents($file_config), true);
$token = json_decode(@file_get_contents($file_token), true);
$token_str = @file_get_contents($file_token);
$token_str = ($token_str === false) ? '' : $token_str;

if (!is_array($config)) {
    $config = array();
}
if (!isset($config['user'])) {
    $config['user'] = array('username' => '', 'password' => '');
}
if (!isset($config['miniserver'])) {
    $config['miniserver'] = array('ip' => '', 'port' => '0');
}
if (!isset($config['send_mqtt'])) {
    $config['send_mqtt'] = '0';
}
if (!isset($config['send_json'])) {
    $config['send_json'] = '0';
}
if (!isset($config['send_udp'])) {
    $config['send_udp'] = '1';
}
if (!isset($config['log_level'])) {
    $config['log_level'] = 'info';
}
if (!isset($config['observation_ids'])) {
    $config['observation_ids'] = '';
}

$url_get_chargers = '/api/chargers';
$data = get_req($url_base, $url_get_chargers, isset($token['accessToken']) ? $token['accessToken'] : '');
if (!is_array($data)) {
    $data = array();
}

$charger_status = easee_read_all_charger_status($lbplogdir);
$observation_definitions = easee_get_observation_definitions();
ksort($observation_definitions);

$ii = 1;
foreach ($data as $datakey => $dataval) {
    if (!is_array($dataval) || !isset($dataval['id'])) {
        continue;
    }
    $url  = '/api/chargers/' . $dataval['id'] . '/site';
    $data2 = get_req($url_base, $url, isset($token['accessToken']) ? $token['accessToken'] : '');
    ${'sid_' . $ii} = isset($data2['circuits'][0]['id']) ? $data2['circuits'][0]['id'] : '';
    ${'cid_' . $ii} = isset($data2['circuits'][0]['siteId']) ? $data2['circuits'][0]['siteId'] : '';
    $ii++;
}

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.SECOND'];
$navbar[2]['URL'] = 'log.php';

// Navbar.
$navbar[1]['active'] = true;

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="' . htmlspecialchars($L['MAIN.INTRO'], ENT_QUOTES) . '">';

echo '<p>' . $L['MAIN.INTRO1'] . '</p>';
echo '<br>';
echo '<form action="index.php" method="post">';

// User credentials.
echo '<p class="wide">' . $L['USER.HEAD'] . '</p>';
echo '<label for="username">' . $L['USER.USER'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="username" id="username" value="' . htmlspecialchars($config['user']['username'], ENT_QUOTES) . '" type="text">';
echo '<label for="password">' . $L['USER.PASS'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="password" id="password" value="' . htmlspecialchars($config['user']['password'], ENT_QUOTES) . '" type="password">';

if (strpos($token_str, 'accessToken') === false) {
    echo '<a style="color:red;">' . $L['MAIN.TOKENERROR'] . '</a><br><br><br>';
    log_e($token, $url_tocken, $file_log_e);
} else {
    echo '<a style="color:green;">' . $L['MAIN.TOKENOK'] . '</a><br><br><br>';

    echo '<p class="wide">' . $L['WALLBOX.HEAD'] . '</p>';
    $i = 1;
    foreach ($data as $datakey => $dataval) {
        if (!is_array($dataval) || !isset($dataval['id'])) {
            continue;
        }
        $charger_id = $dataval['id'];
        echo '<b>' . $i . '</b> - ' . $L['WALLBOX.NAME'] . ': <b>' . htmlspecialchars($dataval['name'], ENT_QUOTES) . ' / </b>' . $L['WALLBOX.ID'] . ': <b>' . htmlspecialchars($charger_id, ENT_QUOTES) . '</b>';
        echo ' (' . $L['WALLBOX.SITE_ID'] . ': ' . htmlspecialchars(${'sid_' . $i}, ENT_QUOTES) . ' / ' . $L['WALLBOX.CIRCUIT_ID'] . ': ' . htmlspecialchars(${'cid_' . $i}, ENT_QUOTES) . ')<br>';

        if (isset($charger_status[$charger_id])) {
            $status = $charger_status[$charger_id];
            echo '<div style="padding:4px 0 10px 20px;">';
            echo '<small><b>' . $L['STATUS.HEAD'] . '</b><br>';
            echo $L['STATUS.UPDATED_AT'] . ': ' . htmlspecialchars(isset($status['updatedAtIso']) ? $status['updatedAtIso'] : '-', ENT_QUOTES) . '<br>';

            if (isset($status['lastState']) && is_array($status['lastState'])) {
                $last_state = $status['lastState'];
                echo $L['STATUS.OP_MODE'] . ': ' . htmlspecialchars(isset($last_state['chargerOpMode']) ? strval($last_state['chargerOpMode']) : '-', ENT_QUOTES) . ' | ';
                echo $L['STATUS.TOTAL_POWER'] . ': ' . htmlspecialchars(isset($last_state['totalPower']) ? strval($last_state['totalPower']) : '-', ENT_QUOTES) . ' | ';
                echo $L['STATUS.OUTPUT_PHASE'] . ': ' . htmlspecialchars(isset($last_state['outputPhase']) ? strval($last_state['outputPhase']) : '-', ENT_QUOTES) . '<br>';
            }

            if (isset($status['lastDynamicPowerChange']) && is_array($status['lastDynamicPowerChange'])) {
                $dynamic_status = $status['lastDynamicPowerChange'];
                $phase_mode_label = '-';
                if (isset($dynamic_status['phaseCount']) && intval($dynamic_status['phaseCount']) === 1) {
                    $phase_mode_label = $L['STATUS.PHASE_SINGLE'];
                } elseif (isset($dynamic_status['phaseCount']) && intval($dynamic_status['phaseCount']) === 3) {
                    $phase_mode_label = $L['STATUS.PHASE_THREE'];
                }
                echo $L['STATUS.LAST_POWER_UPDATE'] . ': ' . htmlspecialchars(isset($dynamic_status['timestamp']) ? strval($dynamic_status['timestamp']) : '-', ENT_QUOTES) . '<br>';
                echo $L['STATUS.REQUESTED_POWER'] . ': ' . htmlspecialchars(isset($dynamic_status['requestedPowerKw']) ? strval($dynamic_status['requestedPowerKw']) : '-', ENT_QUOTES) . 'kW | ';
                echo $L['STATUS.EFFECTIVE_POWER'] . ': ' . htmlspecialchars(isset($dynamic_status['effectivePowerKw']) ? strval($dynamic_status['effectivePowerKw']) : '-', ENT_QUOTES) . 'kW | ';
                echo $L['STATUS.PHASE_MODE'] . ': ' . htmlspecialchars($phase_mode_label, ENT_QUOTES) . '<br>';
                echo $L['STATUS.HYSTERESIS'] . ' (1→3/3→1): ' . htmlspecialchars(isset($dynamic_status['hys1to3Seconds']) ? strval($dynamic_status['hys1to3Seconds']) : '-', ENT_QUOTES) . 's / ' . htmlspecialchars(isset($dynamic_status['hys3to1Seconds']) ? strval($dynamic_status['hys3to1Seconds']) : '-', ENT_QUOTES) . 's';
            }
            echo '</small></div>';
        }

        $i++;
    }
    echo '<br><br>';
}

// Miniserver selection.
echo '<p class="wide">' . $L['MINISERVER.HEAD'] . '</p>';
$ms = LBSystem::get_miniservers();
$miniserver_name = '';
if (is_array($ms)) {
    foreach ($ms as $element) {
        if ($element['IPAddress'] == $config['miniserver']['ip']) {
            $miniserver_name = $element['Name'];
            break;
        }
    }
}
if (!is_array($ms)) {
    echo $L['MINISERVER.NOMS'];
} else {
    echo '<label for="miniserver">' . $L['MINISERVER.CHOOSE'] . '</label>';
    echo '<select name="miniserver" id="miniserver">';
    echo '<option select value="' . htmlspecialchars($config['miniserver']['ip'], ENT_QUOTES) . '">' . htmlspecialchars($miniserver_name, ENT_QUOTES) . '</option>';
    echo '<option></option>';
    foreach ($ms as $miniserver) {
        echo '<option value="' . htmlspecialchars($miniserver['IPAddress'], ENT_QUOTES) . '">' . htmlspecialchars($miniserver['Name'], ENT_QUOTES) . '</option>';
    }
    echo '</select>';
}

echo '<br><br><p class="wide">' . $L['RETURN.HEAD'] . '</p>';
echo '<label for="return_mqtt">' . $L['RETURN.MQTT'] . '</label>';
echo '<input type="checkbox" id="return_mqtt" name="return_mqtt"'; if ($config['send_mqtt'] > 0) { echo ' checked'; } echo '>';
echo '<label for="return_json">' . $L['RETURN.JSON'] . '</label>';
echo '<input type="checkbox" id="return_json" name="return_json"'; if ($config['send_json'] > 0) { echo ' checked'; } echo '>';
echo '<label for="return_udp">' . $L['RETURN.UDP'] . '</label>';
echo '<input type="checkbox" id="return_udp" name="return_udp"'; if ($config['send_udp'] > 0) { echo ' checked'; } echo '>';
echo '<label for="udpport">' . $L['RETURN.UDP_PORT'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="udpport" id="udpport" value="' . htmlspecialchars($config['miniserver']['port'], ENT_QUOTES) . '" type="text">';

echo '<br><br><p class="wide">' . $L['LOGGING.HEAD'] . '</p>';
echo '<label for="log_level">' . $L['LOGGING.LEVEL'] . '</label>';
echo '<select name="log_level" id="log_level">';
$log_levels = array('error' => $L['LOGGING.ERROR'], 'warn' => $L['LOGGING.WARN'], 'info' => $L['LOGGING.INFO'], 'debug' => $L['LOGGING.DEBUG']);
foreach ($log_levels as $level => $label) {
    $selected = (easee_normalize_log_level($config['log_level']) === $level) ? ' selected' : '';
    echo '<option value="' . $level . '"' . $selected . '>' . $label . '</option>';
}
echo '</select>';
echo '<br><small>' . $L['LOGGING.HINT'] . '</small>';

echo '<br><br><p class="wide">' . $L['OBSERVATIONS.HEAD'] . '</p>';
echo '<label for="observation_ids">' . $L['OBSERVATIONS.LIST_LABEL'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="observation_ids" id="observation_ids" value="' . htmlspecialchars($config['observation_ids'], ENT_QUOTES) . '" type="text">';
echo '<small>' . $L['OBSERVATIONS.HINT'] . '</small>';
echo '<div style="overflow-x:auto; margin-top:8px;">';
echo '<table style="width:100%; border-collapse:collapse;">';
echo '<tr><th style="text-align:left; border-bottom:1px solid #ccc;">' . $L['OBSERVATIONS.TABLE_ID'] . '</th><th style="text-align:left; border-bottom:1px solid #ccc;">' . $L['OBSERVATIONS.TABLE_PARAMETER'] . '</th><th style="text-align:left; border-bottom:1px solid #ccc;">' . $L['OBSERVATIONS.TABLE_DESCRIPTION'] . '</th></tr>';
foreach ($observation_definitions as $observation_id => $definition) {
    echo '<tr>';
    echo '<td style="padding:3px 6px 3px 0;">' . intval($observation_id) . '</td>';
    echo '<td style="padding:3px 6px 3px 0;">' . htmlspecialchars($definition['parameter'], ENT_QUOTES) . '</td>';
    echo '<td style="padding:3px 0;">' . htmlspecialchars($definition['description'], ENT_QUOTES) . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" name="save_new" data-icon="check" value="' . $L['MAIN.SAVE'] . '"> </center></p>';
echo '</form>';
LBWeb::lbfooter();
?>
