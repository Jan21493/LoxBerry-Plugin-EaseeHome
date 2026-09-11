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
    $observation_definitions = easee_get_observation_definitions();
    $observation_ids_selected = isset($_POST['observation_ids_selected']) && is_array($_POST['observation_ids_selected']) ? $_POST['observation_ids_selected'] : array();
    $selected_observation_ids = array();
    foreach ($observation_ids_selected as $selected_id) {
        $selected_id = trim((string)$selected_id);
        if (!ctype_digit($selected_id)) {
            continue;
        }
        $selected_id_int = intval($selected_id);
        if (isset($observation_definitions[$selected_id_int])) {
            $selected_observation_ids[] = $selected_id_int;
        }
    }
    $selected_observation_ids = array_values(array_unique($selected_observation_ids));
    sort($selected_observation_ids, SORT_NUMERIC);
    $observation_ids = implode(',', $selected_observation_ids);

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
$default_observation_ids = easee_get_default_observation_ids();
$selected_observation_ids = easee_get_requested_observation_ids($config['observation_ids'], $observation_definitions, $default_observation_ids);
$selected_observation_lookup = array_fill_keys($selected_observation_ids, true);
$observation_groups = array(
    'charger_control' => array(
        'label' => $L['OBSERVATIONS.GROUP_CHARGER_CONTROL'],
        'ids' => array(30, 31, 46, 47, 48, 50, 51, 52)
    ),
    'circuit_and_load' => array(
        'label' => $L['OBSERVATIONS.GROUP_CIRCUIT_LOAD'],
        'ids' => array(70, 71, 72, 73, 74, 75, 111, 112, 113, 230, 231, 232)
    ),
    'charging_state' => array(
        'label' => $L['OBSERVATIONS.GROUP_CHARGING_STATE'],
        'ids' => array(96, 102, 103, 104, 109, 110, 114, 115, 116, 119, 120, 121, 122, 124, 250)
    ),
    'connectivity' => array(
        'label' => $L['OBSERVATIONS.GROUP_CONNECTIVITY'],
        'ids' => array(80, 130, 131, 132, 136)
    ),
    'measurements' => array(
        'label' => $L['OBSERVATIONS.GROUP_MEASUREMENTS'],
        'ids' => array(182, 183, 184, 185, 190, 191, 192, 193, 194, 195, 196, 197, 198, 199)
    )
);

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
echo '<fieldset style="margin-bottom:12px; padding:10px;"><legend><b>' . $L['SETTINGS.GROUP_ACCOUNT'] . '</b></legend>';
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
echo '</fieldset>';

// Miniserver selection.
echo '<fieldset style="margin-bottom:12px; padding:10px;"><legend><b>' . $L['SETTINGS.GROUP_OUTPUT'] . '</b></legend>';
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
echo '</fieldset>';

echo '<fieldset style="margin-bottom:12px; padding:10px;"><legend><b>' . $L['SETTINGS.GROUP_DIAGNOSTICS'] . '</b></legend>';
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
echo '</fieldset>';

echo '<fieldset style="margin-bottom:12px; padding:10px;"><legend><b>' . $L['SETTINGS.GROUP_OBSERVATIONS'] . '</b></legend>';
echo '<br><br><p class="wide">' . $L['OBSERVATIONS.HEAD'] . '</p>';
echo '<label for="observation_ids">' . $L['OBSERVATIONS.SELECTED_LABEL'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="observation_ids" id="observation_ids" value="' . htmlspecialchars(implode(',', $selected_observation_ids), ENT_QUOTES) . '" type="text" readonly>';
echo '<small>' . $L['OBSERVATIONS.HINT'] . '</small>';
echo '<br><br><label>' . $L['OBSERVATIONS.LIST_LABEL'] . '</label>';
$grouped_observation_ids = array();
foreach ($observation_groups as $group) {
    echo '<div style="margin-top:10px; border:1px solid #ddd; padding:8px;">';
    echo '<b>' . $group['label'] . '</b>';
    foreach ($group['ids'] as $observation_id) {
        if (!isset($observation_definitions[$observation_id])) {
            continue;
        }
        $grouped_observation_ids[$observation_id] = true;
        $definition = $observation_definitions[$observation_id];
        $is_checked = isset($selected_observation_lookup[$observation_id]) ? ' checked' : '';
        echo '<label title="' . htmlspecialchars($definition['description'], ENT_QUOTES) . '" style="display:block; margin-top:4px;">';
        echo '<input class="observation-checkbox" type="checkbox" name="observation_ids_selected[]" value="' . intval($observation_id) . '"' . $is_checked . '> ';
        echo intval($observation_id) . ' - ' . htmlspecialchars($definition['parameter'], ENT_QUOTES);
        echo '</label>';
    }
    echo '</div>';
}

$other_observation_ids = array();
foreach ($observation_definitions as $observation_id => $definition) {
    if (!isset($grouped_observation_ids[$observation_id])) {
        $other_observation_ids[] = $observation_id;
    }
}
if (!empty($other_observation_ids)) {
    echo '<div style="margin-top:10px; border:1px solid #ddd; padding:8px;">';
    echo '<b>' . $L['OBSERVATIONS.GROUP_OTHER'] . '</b>';
    foreach ($other_observation_ids as $observation_id) {
        $definition = $observation_definitions[$observation_id];
        $is_checked = isset($selected_observation_lookup[$observation_id]) ? ' checked' : '';
        echo '<label title="' . htmlspecialchars($definition['description'], ENT_QUOTES) . '" style="display:block; margin-top:4px;">';
        echo '<input class="observation-checkbox" type="checkbox" name="observation_ids_selected[]" value="' . intval($observation_id) . '"' . $is_checked . '> ';
        echo intval($observation_id) . ' - ' . htmlspecialchars($definition['parameter'], ENT_QUOTES);
        echo '</label>';
    }
    echo '</div>';
}
echo '</fieldset>';

echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" name="save_new" data-icon="check" value="' . $L['MAIN.SAVE'] . '"> </center></p>';
echo '</form>';
echo '<script>';
echo 'document.addEventListener("DOMContentLoaded", function () {';
echo '  var output = document.getElementById("observation_ids");';
echo '  var checkboxes = document.querySelectorAll(".observation-checkbox");';
echo '  var syncSelectedIds = function () {';
echo '    var ids = [];';
echo '    for (var i = 0; i < checkboxes.length; i++) {';
echo '      if (checkboxes[i].checked) {';
echo '        ids.push(parseInt(checkboxes[i].value, 10));';
echo '      }';
echo '    }';
echo '    ids.sort(function (a, b) { return a - b; });';
echo '    output.value = ids.join(",");';
echo '  };';
echo '  for (var i = 0; i < checkboxes.length; i++) {';
echo '    checkboxes[i].addEventListener("change", syncSelectedIds);';
echo '  }';
echo '  syncSelectedIds();';
echo '});';
echo '</script>';
LBWeb::lbfooter();
?>
