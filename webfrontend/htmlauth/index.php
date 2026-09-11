<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");
$file_token  = easee_get_token_file($lbplogdir);
$file_config = $lbpconfigdir.'/easee_config.ini';
$url_base    = 'https://api.easee.cloud';
$url_tocken  = '/api/accounts/login';

if ($_POST) {
    $existing_config = json_decode(@file_get_contents($file_config), true);
    if (!is_array($existing_config)) {
        $existing_config = array();
    }
    if (!isset($existing_config['user']) || !is_array($existing_config['user'])) {
        $existing_config['user'] = array('username' => '', 'password' => '');
    }
    if (!isset($existing_config['miniserver']) || !is_array($existing_config['miniserver'])) {
        $existing_config['miniserver'] = array('ip' => '', 'port' => '0');
    }
    if (!isset($existing_config['send_json'])) {
        $existing_config['send_json'] = '0';
    }
    if (!isset($existing_config['send_mqtt'])) {
        $existing_config['send_mqtt'] = '0';
    }
    if (!isset($existing_config['send_udp'])) {
        $existing_config['send_udp'] = '1';
    }
    if (!isset($existing_config['log_level'])) {
        $existing_config['log_level'] = 'info';
    }

    $return_json = isset($_POST['return_json']) ? (($_POST['return_json'] === 'on') ? '1' : '0') : $existing_config['send_json'];
    $return_mqtt = isset($_POST['return_mqtt']) ? (($_POST['return_mqtt'] === 'on') ? '1' : '0') : $existing_config['send_mqtt'];
    $return_udp = isset($_POST['return_udp']) ? (($_POST['return_udp'] === 'on') ? '1' : '0') : $existing_config['send_udp'];

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
    $observation_ids = empty($selected_observation_ids) ? 'none' : implode(',', $selected_observation_ids);

    if (file_exists($file_token)) {
        unlink($file_token);
    }

    $data = array(
        'user' => array(
            'username' => isset($_POST['username']) ? $_POST['username'] : $existing_config['user']['username'],
            'password' => isset($_POST['password']) ? $_POST['password'] : $existing_config['user']['password']
        ),
        'miniserver' => array(
            'ip' => isset($_POST['miniserver']) ? $_POST['miniserver'] : $existing_config['miniserver']['ip'],
            'port' => isset($_POST['udpport']) ? $_POST['udpport'] : $existing_config['miniserver']['port']
        ),
        'send_html' => '0',
        'send_udp' => $return_udp,
        'send_json' => $return_json,
        'send_mqtt' => $return_mqtt,
        'log_level' => isset($_POST['log_level']) ? $log_level : $existing_config['log_level'],
        'observation_ids' => $observation_ids
    );

    file_put_contents($file_config, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    get_token($url_base, $url_tocken, $file_token, $data['user']['username'], $data['user']['password']);
    header("Location: timer.php");
    exit;
}

$config = json_decode(@file_get_contents($file_config), true);

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

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.SECOND'];
$navbar[2]['URL'] = 'log.php';
$navbar[3]['Name'] = $L['NAVBAR.THIRD'];
$navbar[3]['URL'] = 'queries.php';

// Navbar.
$navbar[1]['active'] = true;

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="' . htmlspecialchars($L['MAIN.INTRO'], ENT_QUOTES) . '">';

echo '<p>' . $L['MAIN.INTRO1'] . '</p>';
echo '<br>';
echo '<form action="index.php" method="post">';

echo '<fieldset style="margin-bottom:12px; padding:10px;"><legend><b>' . $L['OBSERVATION.HEAD'] . '</b></legend>';
echo '<br><br><p class="wide">' . $L['OBSERVATIONS.HEAD'] . '</p>';
echo '<div style="margin-top:6px;">';
echo '<button type="button" id="observation-preset-none" data-inline="true" data-mini="true">' . $L['OBSERVATION.PRESET_NONE'] . '</button> ';
echo '<button type="button" id="observation-preset-standard" data-inline="true" data-mini="true">' . $L['OBSERVATION.PRESET_STANDARD'] . '</button> ';
echo '<button type="button" id="observation-preset-all" data-inline="true" data-mini="true">' . $L['OBSERVATION.PRESET_ALL'] . '</button>';
echo '</div>';
echo '<small>' . $L['OBSERVATION.ALL_WARNING'] . '</small>';
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
echo '<br><label for="observation_ids">' . $L['OBSERVATIONS.SELECTED_LABEL'] . '</label>';
echo '<input data-inline="true" data-mini="true" name="observation_ids" id="observation_ids" value="' . htmlspecialchars(implode(',', $selected_observation_ids), ENT_QUOTES) . '" type="text" readonly>';
echo '<small>' . $L['OBSERVATIONS.HINT'] . '</small>';
echo '</fieldset>';

echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" name="save_new" data-icon="check" value="' . $L['MAIN.SAVE'] . '"> </center></p>';
echo '</form>';
echo '<script>';
echo 'document.addEventListener("DOMContentLoaded", function () {';
echo '  var output = document.getElementById("observation_ids");';
echo '  var checkboxes = document.querySelectorAll(".observation-checkbox");';
echo '  var defaultIds = {"31":true,"103":true,"109":true,"120":true,"121":true,"122":true,"124":true,"250":true};';
echo '  var applyPreset = function (preset) {';
echo '    for (var i = 0; i < checkboxes.length; i++) {';
echo '      if (preset === "none") {';
echo '        checkboxes[i].checked = false;';
echo '      } else if (preset === "all") {';
echo '        checkboxes[i].checked = true;';
echo '      } else if (preset === "standard") {';
echo '        checkboxes[i].checked = !!defaultIds[checkboxes[i].value];';
echo '      }';
echo '    }';
echo '    syncSelectedIds();';
echo '  };';
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
echo '  document.getElementById("observation-preset-none").addEventListener("click", function () { applyPreset("none"); });';
echo '  document.getElementById("observation-preset-standard").addEventListener("click", function () { applyPreset("standard"); });';
echo '  document.getElementById("observation-preset-all").addEventListener("click", function () { applyPreset("all"); });';
echo '  syncSelectedIds();';
echo '});';
echo '</script>';
LBWeb::lbfooter();
?>
