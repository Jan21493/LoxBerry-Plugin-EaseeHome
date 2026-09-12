<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");

// This page never queries the Easee Cloud API. All values come from the JSON
// responses that easee.php cached in the (RAM based) log directory.
$cachedByScope = easee_read_all_cached_responses($lbplogdir);
$chargerStatus = easee_read_all_charger_status($lbplogdir);
$accountScope = easee_get_account_scope();

// Charger names from the cached /api/chargers response (no extra API call).
$chargerNames = array();
if (isset($cachedByScope[$accountScope]['chargers']['data']) && is_array($cachedByScope[$accountScope]['chargers']['data'])) {
    foreach ($cachedByScope[$accountScope]['chargers']['data'] as $chargerEntry) {
        if (is_array($chargerEntry) && isset($chargerEntry['id'])) {
            $chargerNames[$chargerEntry['id']] = isset($chargerEntry['name']) ? $chargerEntry['name'] : '';
        }
    }
}

// Collect every charger ID we have data for.
$chargerIds = array();
foreach (array_keys($cachedByScope) as $scopeId) {
    if ($scopeId !== $accountScope) {
        $chargerIds[$scopeId] = true;
    }
}
foreach (array_keys($chargerStatus) as $scopeId) {
    $chargerIds[$scopeId] = true;
}
$chargerIds = array_keys($chargerIds);
sort($chargerIds);

// Observation ids that the status page can display. Ids that are not polled
// are reported to the user so missing values can be explained.
$statusObservationIds = array(
    21, 30, 31, 38, 45, 46, 47, 48, 73, 74, 75, 80, 89, 96, 100, 102, 103, 104,
    109, 110, 111, 112, 113, 114, 115, 116, 119, 120, 121, 122, 124, 130, 131,
    132, 136, 182, 183, 184, 185, 194, 195, 196, 197, 198, 199, 230, 231, 232, 250
);
$pluginConfig = json_decode(@file_get_contents($lbpconfigdir . '/easee_config.ini'), true);
$observationDefinitions = easee_get_observation_definitions();
$activeObservationIds = easee_get_requested_observation_ids(
    isset($pluginConfig['observation_ids']) ? $pluginConfig['observation_ids'] : '',
    $observationDefinitions,
    easee_get_default_observation_ids()
);
$missingObservationIds = array_values(array_diff($statusObservationIds, $activeObservationIds));
sort($missingObservationIds);

// ---------------------------------------------------------------------------
// Helper functions (display only).
// ---------------------------------------------------------------------------

function easee_status_duration_text($seconds, $L)
{
    $seconds = intval($seconds);
    if ($seconds < 0) {
        $seconds = 0;
    }
    if ($seconds < 60) {
        return $seconds . ' ' . $L['STATUS.UNIT_SECONDS'];
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' ' . $L['STATUS.UNIT_MINUTES'] . ' ' . ($seconds % 60) . ' ' . $L['STATUS.UNIT_SECONDS'];
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' ' . $L['STATUS.UNIT_HOURS'] . ' ' . floor(($seconds % 3600) / 60) . ' ' . $L['STATUS.UNIT_MINUTES'];
    }
    return floor($seconds / 86400) . ' ' . $L['STATUS.UNIT_DAYS'] . ' ' . floor(($seconds % 86400) / 3600) . ' ' . $L['STATUS.UNIT_HOURS'];
}

function easee_status_to_epoch($timestamp)
{
    if ($timestamp === null || $timestamp === '' || $timestamp === 0 || $timestamp === '0') {
        return null;
    }
    if (is_numeric($timestamp)) {
        return intval($timestamp);
    }
    $epoch = strtotime((string)$timestamp);
    return ($epoch === false) ? null : $epoch;
}

// Local time plus relative age, e.g. "2026-09-12 21:04:11 (vor 35 s)".
function easee_status_timestamp_text($timestamp, $L)
{
    $epoch = easee_status_to_epoch($timestamp);
    if ($epoch === null) {
        return null;
    }
    return date('Y-m-d H:i:s', $epoch) . ' (' . sprintf($L['STATUS.AGO'], easee_status_duration_text(time() - $epoch, $L)) . ')';
}

function easee_status_bool_text($value, $L)
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_string($value)) {
        $lower = strtolower(trim($value));
        if ($lower === 'true' || $lower === 'false') {
            $value = ($lower === 'true');
        }
    }
    return ((is_bool($value) ? $value : intval($value) > 0)) ? $L['STATUS.VAL_YES'] : $L['STATUS.VAL_NO'];
}

function easee_status_number_text($value, $unit = '', $decimals = 2)
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    $text = number_format(floatval($value), $decimals, ',', '.');
    return ($unit === '') ? $text : $text . ' ' . $unit;
}

// Decode an enumeration value and translate the name if a translation exists.
function easee_status_enum_text($table, $value, $L)
{
    if ($value === null || $value === '') {
        return null;
    }
    $decoded = easee_decode_enum($table, $value);
    if ($decoded === null) {
        return null;
    }
    if (empty($decoded['known'])) {
        return (string)$value . ' – ' . $L['STATUS.UNKNOWN'];
    }
    $langKey = 'ENUM.' . strtoupper($table) . '_' . strtoupper(str_replace(array('-', ' '), '_', (string)$decoded['value']));
    $name = (isset($L[$langKey]) && $L[$langKey] !== '') ? $L[$langKey] : $decoded['name'];
    return (string)$decoded['value'] . ' – ' . $name;
}

function easee_status_enum_hint($table, $value)
{
    $decoded = easee_decode_enum($table, $value);
    if ($decoded === null || empty($decoded['known'])) {
        return '';
    }
    return isset($decoded['description']) ? $decoded['description'] : '';
}

// Hint for values that were not part of the latest call but kept from an
// earlier cached response.
function easee_status_stale_hint($fieldFetchedAt, $latestFetchEpoch, $field, $L)
{
    if (!is_array($fieldFetchedAt) || !isset($fieldFetchedAt[$field])) {
        return '';
    }
    $fieldEpoch = intval($fieldFetchedAt[$field]);
    if ($fieldEpoch <= 0 || $latestFetchEpoch <= 0 || $fieldEpoch >= ($latestFetchEpoch - 5)) {
        return '';
    }
    return sprintf($L['STATUS.STALE_FIELD'], easee_status_timestamp_text($fieldEpoch, $L));
}

function easee_status_get($source, $key, $default = null)
{
    if (!is_array($source) || !array_key_exists($key, $source)) {
        return $default;
    }
    $value = $source[$key];
    return ($value === '') ? $default : $value;
}

// Render a group of rows. Rows with a null value are skipped.
function easee_status_render_group($title, $rows, $hint = '')
{
    $visibleRows = array();
    foreach ($rows as $row) {
        if (isset($row[1]) && $row[1] !== null && $row[1] !== '') {
            $visibleRows[] = $row;
        }
    }
    if (empty($visibleRows)) {
        return;
    }

    echo '<fieldset class="status-card">';
    echo '<p class="sec-head">' . htmlspecialchars($title, ENT_QUOTES) . '</p>';
    if ($hint !== '') {
        echo '<small class="status-hint">' . $hint . '</small>';
    }
    echo '<table class="status-table"><tbody>';
    foreach ($visibleRows as $row) {
        $label = $row[0];
        $value = $row[1];
        $rowHint = isset($row[2]) ? $row[2] : '';
        echo '<tr><th>' . htmlspecialchars($label, ENT_QUOTES) . '</th><td>' . htmlspecialchars((string)$value, ENT_QUOTES);
        if ($rowHint !== '') {
            echo '<br><small class="status-hint">' . htmlspecialchars($rowHint, ENT_QUOTES) . '</small>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
    echo '</fieldset>';
}

// ---------------------------------------------------------------------------
// Page.
// ---------------------------------------------------------------------------

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.STATUS'];
$navbar[2]['URL'] = 'status.php';
$navbar[3]['Name'] = $L['NAVBAR.SECOND'];
$navbar[3]['URL'] = 'log.php';
$navbar[4]['Name'] = $L['NAVBAR.THIRD'];
$navbar[4]['URL'] = 'queries.php';
$navbar[2]['active'] = True;

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="Easee Home">';

echo '<style>'
    . '.status-card{margin-bottom:12px;padding:10px;}'
    . '.sec-head{font-size:11px;font-weight:bold;color:#777;text-transform:uppercase;letter-spacing:.04em;margin:0 0 6px;}'
    . '.status-table{width:100%;border-collapse:collapse;font-size:13px;}'
    . '.status-table th{text-align:left;font-weight:normal;color:#555;width:45%;vertical-align:top;padding:3px 8px 3px 0;border-bottom:1px solid #eee;}'
    . '.status-table td{text-align:left;font-weight:bold;vertical-align:top;padding:3px 0;border-bottom:1px solid #eee;}'
    . '.status-hint{color:#777;font-weight:normal;}'
    . '.status-headline{border-radius:8px;padding:12px 14px;margin:0 0 12px;color:#fff;}'
    . '.status-headline .hl-main{font-size:18px;font-weight:bold;}'
    . '.status-headline .hl-sub{font-size:13px;opacity:.95;margin-top:4px;}'
    . '.hl-green{background:#1a7f37;}.hl-orange{background:#bf8700;}.hl-blue{background:#0079c1;}'
    . '.hl-red{background:#b42318;}.hl-grey{background:#6e7781;}'
    . '.charger-head{font-size:17px;font-weight:bold;margin:18px 0 8px;}'
    . '.status-raw pre{white-space:pre-wrap;word-break:break-word;background:#f6f8fa;border:1px solid #d0d7de;border-radius:6px;padding:8px;font-size:12px;margin:6px 0 0;}'
    . '.status-raw summary{cursor:pointer;font-size:13px;padding:4px 0;}'
    . '.status-toolbar{margin-bottom:10px;font-size:13px;}'
    . '</style>';

echo '<p class="wide">' . $L['STATUS.HEAD'] . '</p>';
echo '<p><small>' . $L['STATUS.INTRO'] . '</small></p>';

echo '<div class="status-toolbar">';
echo '<button type="button" data-inline="true" data-mini="true" onclick="window.location.reload();">' . $L['STATUS.REFRESH'] . '</button> ';
echo '<label style="display:inline-block;margin-left:8px;"><input type="checkbox" id="status-autorefresh"> ' . $L['STATUS.AUTOREFRESH'] . '</label>';
echo '<br><small class="status-hint">' . $L['STATUS.GENERATED'] . ': ' . date('Y-m-d H:i:s') . '</small>';
echo '</div>';

if (!empty($missingObservationIds)) {
    $missingList = array();
    foreach ($missingObservationIds as $missingId) {
        $missingName = isset($observationDefinitions[$missingId]['parameter'])
            ? $observationDefinitions[$missingId]['parameter']
            : '';
        $missingList[] = $missingId . ($missingName !== '' ? ' (' . $missingName . ')' : '');
    }
    echo '<fieldset class="status-card">';
    echo '<p class="sec-head">' . $L['STATUS.MISSING_OBS'] . '</p>';
    echo '<small class="status-hint">' . $L['STATUS.MISSING_OBS_HINT'] . '</small>';
    echo '<details class="status-raw"><summary>' . sprintf($L['STATUS.MISSING_OBS_COUNT'], count($missingObservationIds)) . '</summary>';
    echo '<p><small>' . htmlspecialchars(implode(', ', $missingList), ENT_QUOTES) . '</small></p>';
    echo '</details>';
    echo '</fieldset>';
}

if (empty($chargerIds)) {
    echo '<fieldset class="status-card">';
    echo '<p>' . $L['STATUS.NO_DATA'] . '</p>';
    echo '<small class="status-hint">' . $L['STATUS.NO_DATA_HINT'] . '</small>';
    echo '</fieldset>';
    LBWeb::lbfooter();
    exit;
}

foreach ($chargerIds as $chargerId) {
    $cache = isset($cachedByScope[$chargerId]) ? $cachedByScope[$chargerId] : array();
    $state = (isset($cache['state']['data']) && is_array($cache['state']['data'])) ? $cache['state']['data'] : array();
    $chargerConfig = (isset($cache['config']['data']) && is_array($cache['config']['data'])) ? $cache['config']['data'] : array();
    $circuits = (isset($cache['circuits']['data']) && is_array($cache['circuits']['data'])) ? $cache['circuits']['data'] : array();
    $site = (isset($cache['site']['data']) && is_array($cache['site']['data'])) ? $cache['site']['data'] : array();
    $latest = (isset($cache['latest']['data']) && is_array($cache['latest']['data'])) ? $cache['latest']['data'] : array();
    $ongoing = (isset($cache['ongoing']['data']) && is_array($cache['ongoing']['data'])) ? $cache['ongoing']['data'] : array();
    $equalizer = (isset($cache['equalizer']['data']) && is_array($cache['equalizer']['data'])) ? $cache['equalizer']['data'] : array();
    $persistedStatus = isset($chargerStatus[$chargerId]) ? $chargerStatus[$chargerId] : array();
    $dynamicPower = (isset($persistedStatus['lastDynamicPowerChange']) && is_array($persistedStatus['lastDynamicPowerChange']))
        ? $persistedStatus['lastDynamicPowerChange']
        : array();
    $phaseState = easee_read_phase_state($lbplogdir, $chargerId);
    $phaseStats = easee_get_phase_statistics($lbplogdir, $chargerId, 86400);
    $observationTimestamps = (isset($cache['state']['context']['observationTimestamps']) && is_array($cache['state']['context']['observationTimestamps']))
        ? $cache['state']['context']['observationTimestamps']
        : array();
    $fieldFetchedAt = (isset($cache['state']['context']['fieldFetchedAt']) && is_array($cache['state']['context']['fieldFetchedAt']))
        ? $cache['state']['context']['fieldFetchedAt']
        : array();
    $latestStateFetch = isset($cache['state']['fetchedAtEpoch']) ? intval($cache['state']['fetchedAtEpoch']) : 0;

    // Session data is stored with a "latest_"/"ongoing_" prefix by easee.php.
    $latestSession = array();
    foreach ($latest as $sessionKey => $sessionValue) {
        $latestSession[preg_replace('/^latest_/', '', $sessionKey)] = $sessionValue;
    }
    $ongoingSession = array();
    foreach ($ongoing as $sessionKey => $sessionValue) {
        $ongoingSession[preg_replace('/^ongoing_/', '', $sessionKey)] = $sessionValue;
    }

    $chargerName = isset($chargerNames[$chargerId]) ? $chargerNames[$chargerId] : '';
    echo '<div class="charger-head">' . $L['STATUS.CHARGER'] . ': ' . htmlspecialchars(($chargerName !== '' ? $chargerName . ' – ' : '') . $chargerId, ENT_QUOTES) . '</div>';

    // -----------------------------------------------------------------------
    // Headline: what is the charger doing right now?
    // -----------------------------------------------------------------------
    $opMode = easee_status_get($state, 'chargerOpMode');
    $opModeInt = ($opMode === null) ? null : intval($opMode);
    $totalPower = easee_status_get($state, 'totalPower');
    $outputPhase = easee_status_get($state, 'outputPhase');
    $phaseCount = ($outputPhase === null) ? null : easee_get_output_phase_count($outputPhase);
    $phaseCountEstimated = false;
    if (($phaseCount === null || $phaseCount === 0) && isset($dynamicPower['phaseCount'])) {
        $phaseCount = intval($dynamicPower['phaseCount']);
        $phaseCountEstimated = true;
    }
    $isCharging = ($opModeInt === 3);
    $cableConnected = null;
    if ($opModeInt !== null) {
        if (in_array($opModeInt, array(2, 3, 4, 6, 7, 8), true)) {
            $cableConnected = true;
        } elseif ($opModeInt === 1) {
            $cableConnected = false;
        }
    }

    $headlineClass = 'hl-grey';
    if ($opModeInt === 3) {
        $headlineClass = 'hl-green';
    } elseif ($opModeInt === 2 || $opModeInt === 6 || $opModeInt === 7 || $opModeInt === 8) {
        $headlineClass = 'hl-orange';
    } elseif ($opModeInt === 4) {
        $headlineClass = 'hl-blue';
    } elseif ($opModeInt === 5) {
        $headlineClass = 'hl-red';
    }

    $headlineMain = ($opModeInt === null)
        ? $L['STATUS.NO_STATE']
        : easee_status_enum_text('opMode', $opModeInt, $L);
    $headlineParts = array();
    if ($isCharging) {
        $powerText = easee_status_number_text($totalPower, 'kW', 2);
        if ($powerText !== null) {
            $headlineParts[] = $L['STATUS.TOTAL_POWER'] . ': ' . $powerText;
        }
    }
    if ($phaseCount !== null && $phaseCount > 0) {
        $headlineParts[] = sprintf($L['STATUS.PHASE_COUNT_TEXT'], $phaseCount)
            . ($phaseCountEstimated ? ' (' . $L['STATUS.PHASE_COUNT_REQUESTED'] . ')' : '');
    }
    $outputCurrent = easee_status_get($state, 'outputCurrent');
    $outputCurrentText = easee_status_number_text($outputCurrent, 'A', 1);
    if ($outputCurrentText !== null) {
        $headlineParts[] = $L['STATUS.OUTPUT_CURRENT'] . ': ' . $outputCurrentText;
    }
    if ($cableConnected !== null) {
        $headlineParts[] = $L['STATUS.CABLE_CONNECTED'] . ': ' . ($cableConnected ? $L['STATUS.VAL_YES'] : $L['STATUS.VAL_NO']);
    }

    echo '<div class="status-headline ' . $headlineClass . '">';
    echo '<div class="hl-main">' . htmlspecialchars((string)$headlineMain, ENT_QUOTES) . '</div>';
    if (!empty($headlineParts)) {
        echo '<div class="hl-sub">' . htmlspecialchars(implode(' · ', $headlineParts), ENT_QUOTES) . '</div>';
    }
    if (isset($cache['state']['fetchedAtEpoch'])) {
        echo '<div class="hl-sub">' . htmlspecialchars($L['STATUS.STATE_FETCHED'] . ': ' . easee_status_timestamp_text($cache['state']['fetchedAtEpoch'], $L), ENT_QUOTES) . '</div>';
    }
    echo '</div>';

    // -----------------------------------------------------------------------
    // Group: charging state.
    // -----------------------------------------------------------------------
    $reasonNoCurrent = easee_status_get($state, 'reasonForNoCurrent');
    easee_status_render_group($L['STATUS.GROUP_CHARGING'], array(
        array($L['STATUS.OP_MODE'], $headlineMain, easee_status_enum_hint('opMode', $opModeInt) . easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'chargerOpMode', $L)),
        array($L['STATUS.CHARGING_NOW'], ($opModeInt === null) ? null : ($isCharging ? $L['STATUS.VAL_YES'] : $L['STATUS.VAL_NO'])),
        array($L['STATUS.CABLE_CONNECTED'], ($cableConnected === null) ? null : ($cableConnected ? $L['STATUS.VAL_YES'] : $L['STATUS.VAL_NO'])),
        array($L['STATUS.CABLE_LOCKED'], easee_status_bool_text(easee_status_get($state, 'cableLocked'), $L), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'cableLocked', $L)),
        array($L['STATUS.CABLE_RATING'], easee_status_number_text(easee_status_get($state, 'cableRating'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'cableRating', $L)),
        array($L['STATUS.TOTAL_POWER'], easee_status_number_text($totalPower, 'kW', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'totalPower', $L)),
        array($L['STATUS.OUTPUT_CURRENT'], $outputCurrentText, easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'outputCurrent', $L)),
        array($L['STATUS.PHASE_COUNT'], ($phaseCount === null || $phaseCount === 0) ? null : sprintf($L['STATUS.PHASE_COUNT_TEXT'], $phaseCount), $phaseCountEstimated ? $L['STATUS.PHASE_COUNT_HINT'] : ''),
        array($L['STATUS.OUTPUT_PHASE'], easee_status_enum_text('outputPhase', $outputPhase, $L), easee_status_enum_hint('outputPhase', $outputPhase) . easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'outputPhase', $L)),
        array($L['STATUS.SESSION_ENERGY'], easee_status_number_text(easee_status_get($state, 'sessionEnergy'), 'kWh', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'sessionEnergy', $L)),
        array($L['STATUS.ENERGY_PER_HOUR'], easee_status_number_text(easee_status_get($state, 'energyPerHour'), 'kWh/h', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'energyPerHour', $L)),
        array($L['STATUS.LIFETIME_ENERGY'], easee_status_number_text(easee_status_get($state, 'lifetimeEnergy'), 'kWh', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'lifetimeEnergy', $L)),
        array($L['STATUS.REASON_NO_CURRENT'], easee_status_enum_text('reasonForNoCurrent', $reasonNoCurrent, $L), easee_status_enum_hint('reasonForNoCurrent', $reasonNoCurrent) . easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'reasonForNoCurrent', $L)),
        array($L['STATUS.DERATING'], easee_status_bool_text(easee_status_get($state, 'deratingActive'), $L), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'deratingActive', $L)),
        array($L['STATUS.DERATED_CURRENT'], easee_status_number_text(easee_status_get($state, 'deratedCurrent'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'deratedCurrent', $L)),
        array($L['STATUS.ERROR_CODE'], easee_status_get($state, 'errorCode'), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'errorCode', $L)),
        array($L['STATUS.IS_ENABLED'], easee_status_bool_text(easee_status_get($state, 'isEnabled'), $L), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'isEnabled', $L)),
        array($L['STATUS.SMART_CHARGING'], easee_status_bool_text(easee_status_get($state, 'smartCharging'), $L))
    ));

    // -----------------------------------------------------------------------
    // Group: currents and voltages per phase.
    // -----------------------------------------------------------------------
    easee_status_render_group($L['STATUS.GROUP_PHASES'], array(
        array($L['STATUS.CURRENT_L1'], easee_status_number_text(easee_status_get($state, 'inCurrentT3'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inCurrentT3', $L)),
        array($L['STATUS.CURRENT_L2'], easee_status_number_text(easee_status_get($state, 'inCurrentT4'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inCurrentT4', $L)),
        array($L['STATUS.CURRENT_L3'], easee_status_number_text(easee_status_get($state, 'inCurrentT5'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inCurrentT5', $L)),
        array($L['STATUS.CURRENT_N'], easee_status_number_text(easee_status_get($state, 'inCurrentT2'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inCurrentT2', $L)),
        array($L['STATUS.VOLTAGE_L1'], easee_status_number_text(easee_status_get($state, 'inVoltageT2T3'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT2T3', $L)),
        array($L['STATUS.VOLTAGE_L2'], easee_status_number_text(easee_status_get($state, 'inVoltageT2T4'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT2T4', $L)),
        array($L['STATUS.VOLTAGE_L3'], easee_status_number_text(easee_status_get($state, 'inVoltageT2T5'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT2T5', $L)),
        array($L['STATUS.VOLTAGE_L1L2'], easee_status_number_text(easee_status_get($state, 'inVoltageT3T4'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT3T4', $L)),
        array($L['STATUS.VOLTAGE_L1L3'], easee_status_number_text(easee_status_get($state, 'inVoltageT3T5'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT3T5', $L)),
        array($L['STATUS.VOLTAGE_L2L3'], easee_status_number_text(easee_status_get($state, 'inVoltageT4T5'), 'V', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'inVoltageT4T5', $L)),
        array($L['STATUS.CIRCUIT_CURRENT_L1'], easee_status_number_text(easee_status_get($state, 'circuitTotalPhaseConductorCurrentL1'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'circuitTotalPhaseConductorCurrentL1', $L)),
        array($L['STATUS.CIRCUIT_CURRENT_L2'], easee_status_number_text(easee_status_get($state, 'circuitTotalPhaseConductorCurrentL2'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'circuitTotalPhaseConductorCurrentL2', $L)),
        array($L['STATUS.CIRCUIT_CURRENT_L3'], easee_status_number_text(easee_status_get($state, 'circuitTotalPhaseConductorCurrentL3'), 'A', 2), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'circuitTotalPhaseConductorCurrentL3', $L)),
        array($L['STATUS.DYN_CIRCUIT_P1'], easee_status_number_text(easee_status_get($state, 'dynamicCircuitCurrentP1'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'dynamicCircuitCurrentP1', $L)),
        array($L['STATUS.DYN_CIRCUIT_P2'], easee_status_number_text(easee_status_get($state, 'dynamicCircuitCurrentP2'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'dynamicCircuitCurrentP2', $L)),
        array($L['STATUS.DYN_CIRCUIT_P3'], easee_status_number_text(easee_status_get($state, 'dynamicCircuitCurrentP3'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'dynamicCircuitCurrentP3', $L)),
        array($L['STATUS.EQ_AVAILABLE_P1'], easee_status_number_text(easee_status_get($state, 'eqAvailableCurrentP1'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'eqAvailableCurrentP1', $L)),
        array($L['STATUS.EQ_AVAILABLE_P2'], easee_status_number_text(easee_status_get($state, 'eqAvailableCurrentP2'), 'A', 1), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'eqAvailableCurrentP2', $L)),
        array($L['STATUS.EQ_AVAILABLE_P3'], easee_status_number_text(easee_status_get($state, 'eqAvailableCurrentP3'), 'A', 1))
    ), $L['STATUS.PHASES_HINT']);

    // -----------------------------------------------------------------------
    // Group: phase switching and hysteresis.
    // -----------------------------------------------------------------------
    $hys1to3 = isset($dynamicPower['hys1to3Seconds']) ? intval($dynamicPower['hys1to3Seconds']) : 0;
    $hys3to1 = isset($dynamicPower['hys3to1Seconds']) ? intval($dynamicPower['hys3to1Seconds']) : 0;
    $lastSwitchEpoch = intval($phaseState['last_switch']);
    $lastRequestedPhase = intval($phaseState['last_phase']);
    $secondsSinceSwitch = ($lastSwitchEpoch > 0) ? (time() - $lastSwitchEpoch) : null;

    // The blocking window depends on the direction of the next possible switch.
    $hysteresisText = $L['STATUS.HYS_DISABLED'];
    if ($hys1to3 > 0 || $hys3to1 > 0) {
        $activeHysteresis = ($lastRequestedPhase === 1) ? $hys1to3 : $hys3to1;
        if ($activeHysteresis > 0 && $secondsSinceSwitch !== null && $secondsSinceSwitch < $activeHysteresis) {
            $hysteresisText = sprintf(
                $L['STATUS.HYS_BLOCKING'],
                easee_status_duration_text($activeHysteresis - $secondsSinceSwitch, $L)
            );
        } else {
            $hysteresisText = $L['STATUS.HYS_FREE'];
        }
    }

    $switchReason = isset($dynamicPower['switchReason']) ? $dynamicPower['switchReason'] : null;
    $switchReasonKey = 'STATUS.REASON_' . strtoupper((string)$switchReason);
    $switchReasonText = ($switchReason === null)
        ? null
        : ((isset($L[$switchReasonKey]) && $L[$switchReasonKey] !== '') ? $L[$switchReasonKey] : $switchReason);

    $phaseModeText = null;
    if (isset($dynamicPower['phaseCount'])) {
        $phaseModeText = (intval($dynamicPower['phaseCount']) === 1) ? $L['STATUS.PHASE_SINGLE'] : $L['STATUS.PHASE_THREE'];
    }

    easee_status_render_group($L['STATUS.GROUP_HYSTERESIS'], array(
        array($L['STATUS.HYS_STATE'], $hysteresisText),
        array($L['STATUS.LAST_SWITCH'], ($lastSwitchEpoch > 0) ? easee_status_timestamp_text($lastSwitchEpoch, $L) : $L['STATUS.NEVER']),
        array($L['STATUS.LAST_PHASE_REQUEST'], ($lastRequestedPhase > 0) ? sprintf($L['STATUS.PHASE_COUNT_TEXT'], $lastRequestedPhase) : null),
        array($L['STATUS.SWITCHES_24H'], strval(max($phaseStats['switchesTotal'], $phaseStats['observedTotal']))),
        array($L['STATUS.SWITCHES_REQUESTED'], $phaseStats['switch_1_to_3'] . ' / ' . $phaseStats['switch_3_to_1']),
        array($L['STATUS.SWITCHES_OBSERVED'], $phaseStats['observed_1_to_3'] . ' / ' . $phaseStats['observed_3_to_1']),
        array($L['STATUS.HOLDS_24H'], $phaseStats['hold_1_to_3'] . ' / ' . $phaseStats['hold_3_to_1']),
        array($L['STATUS.LAST_POWER_UPDATE'], isset($dynamicPower['timestamp']) ? easee_status_timestamp_text($dynamicPower['timestamp'], $L) : null),
        array($L['STATUS.REQUESTED_POWER'], easee_status_number_text(isset($dynamicPower['requestedPowerKw']) ? $dynamicPower['requestedPowerKw'] : null, 'kW', 2)),
        array($L['STATUS.EFFECTIVE_POWER'], easee_status_number_text(isset($dynamicPower['effectivePowerKw']) ? $dynamicPower['effectivePowerKw'] : null, 'kW', 2)),
        array($L['STATUS.PHASE_MODE'], $phaseModeText),
        array($L['STATUS.AMPERE_PER_PHASE'], easee_status_number_text(isset($dynamicPower['amperePerActivePhase']) ? $dynamicPower['amperePerActivePhase'] : null, 'A', 2)),
        array($L['STATUS.SWITCH_REASON'], $switchReasonText),
        array($L['STATUS.HYS_1TO3'], ($hys1to3 > 0) ? easee_status_duration_text($hys1to3, $L) : $L['STATUS.HYS_OFF']),
        array($L['STATUS.HYS_3TO1'], ($hys3to1 > 0) ? easee_status_duration_text($hys3to1, $L) : $L['STATUS.HYS_OFF'])
    ), $L['STATUS.HYSTERESIS_HINT']);

    // -----------------------------------------------------------------------
    // Group: charging sessions.
    // -----------------------------------------------------------------------
    $ongoingStart = easee_status_to_epoch(easee_status_get($ongoingSession, 'sessionStart'));
    $ongoingDuration = ($ongoingStart !== null) ? easee_status_duration_text(time() - $ongoingStart, $L) : null;
    $latestStart = easee_status_to_epoch(easee_status_get($latestSession, 'sessionStart'));
    $latestEnd = easee_status_to_epoch(easee_status_get($latestSession, 'sessionEnd'));
    $latestDuration = ($latestStart !== null && $latestEnd !== null && $latestEnd >= $latestStart)
        ? easee_status_duration_text($latestEnd - $latestStart, $L)
        : null;

    easee_status_render_group($L['STATUS.GROUP_SESSION'], array(
        array($L['STATUS.SESSION_ONGOING_ENERGY'], easee_status_number_text(easee_status_get($ongoingSession, 'sessionEnergy'), 'kWh', 2)),
        array($L['STATUS.SESSION_ONGOING_START'], ($ongoingStart !== null) ? easee_status_timestamp_text($ongoingStart, $L) : null),
        array($L['STATUS.SESSION_ONGOING_DURATION'], $ongoingDuration),
        array($L['STATUS.SESSION_LATEST_ENERGY'], easee_status_number_text(easee_status_get($latestSession, 'sessionEnergy'), 'kWh', 2)),
        array($L['STATUS.SESSION_LATEST_START'], ($latestStart !== null) ? easee_status_timestamp_text($latestStart, $L) : null),
        array($L['STATUS.SESSION_LATEST_END'], ($latestEnd !== null) ? easee_status_timestamp_text($latestEnd, $L) : null),
        array($L['STATUS.SESSION_LATEST_DURATION'], $latestDuration),
        array($L['STATUS.SESSION_ID'], easee_status_get($latestSession, 'sessionId'))
    ));

    // -----------------------------------------------------------------------
    // Group: connectivity and device.
    // -----------------------------------------------------------------------
    $rat = easee_status_get($state, 'chargerRAT');
    $ratText = null;
    if ($rat !== null && is_numeric($rat)) {
        $ratText = (intval($rat) === 1) ? $L['STATUS.RAT_WIFI'] : $L['STATUS.RAT_CELL'];
    }
    $gridType = easee_status_get($chargerConfig, 'detectedPowerGridType');

    easee_status_render_group($L['STATUS.GROUP_CONNECTION'], array(
        array($L['STATUS.ONLINE'], easee_status_bool_text(easee_status_get($state, 'connectedToCloud', easee_status_get($state, 'isOnline')), $L), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'connectedToCloud', $L)),
        array($L['STATUS.RAT'], $ratText),
        array($L['STATUS.WIFI_RSSI'], easee_status_number_text(easee_status_get($state, 'wiFiRSSI'), 'dBm', 0), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'wiFiRSSI', $L)),
        array($L['STATUS.CELL_RSSI'], easee_status_number_text(easee_status_get($state, 'cellRSSI'), 'dBm', 0), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'cellRSSI', $L)),
        array($L['STATUS.LOCAL_RSSI'], easee_status_number_text(easee_status_get($state, 'localRSSI'), 'dBm', 0), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'localRSSI', $L)),
        array($L['STATUS.WIFI_SSID'], easee_status_get($chargerConfig, 'wiFiSSID')),
        array($L['STATUS.FIRMWARE'], easee_status_get($state, 'chargerFirmware'), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'chargerFirmware', $L)),
        array($L['STATUS.LATEST_PULSE'], easee_status_timestamp_text(easee_status_get($state, 'latestPulse'), $L), easee_status_stale_hint($fieldFetchedAt, $latestStateFetch, 'latestPulse', $L)),
        array($L['STATUS.GRID_TYPE'], easee_status_enum_text('detectedPowerGridType', $gridType, $L)),
        array($L['STATUS.SITE_NAME'], easee_status_get($site, 'name')),
        array($L['STATUS.SITE_ID'], isset($site['circuits'][0]['siteId']) ? $site['circuits'][0]['siteId'] : null),
        array($L['STATUS.CIRCUIT_ID'], isset($site['circuits'][0]['id']) ? $site['circuits'][0]['id'] : null),
        array($L['STATUS.EQ_STATE'], isset($equalizer['clampImportActivePower']) ? easee_status_number_text($equalizer['clampImportActivePower'], 'kW', 2) : null)
    ));

    // -----------------------------------------------------------------------
    // Group: limits and configuration.
    // -----------------------------------------------------------------------
    $configPhaseMode = easee_status_get($chargerConfig, 'phaseMode');
    $offlineMode = easee_status_get($chargerConfig, 'offlineChargingMode');
    $ledMode = easee_status_get($state, 'ledMode');

    easee_status_render_group($L['STATUS.GROUP_LIMITS'], array(
        array($L['STATUS.MAX_CHARGER_CURRENT'], easee_status_number_text(easee_status_get($chargerConfig, 'maxChargerCurrent', easee_status_get($state, 'maxChargerCurrent')), 'A', 1)),
        array($L['STATUS.DYN_CHARGER_CURRENT'], easee_status_number_text(easee_status_get($chargerConfig, 'dynamicChargerCurrent', easee_status_get($state, 'dynamicChargerCurrent')), 'A', 1)),
        array($L['STATUS.CIRCUIT_FUSE'], easee_status_number_text(easee_status_get($circuits, 'ratedCurrent'), 'A', 1)),
        array($L['STATUS.MAX_CIRCUIT_P1'], easee_status_number_text(easee_status_get($circuits, 'maxCircuitCurrentP1', easee_status_get($chargerConfig, 'circuitMaxCurrentP1')), 'A', 1)),
        array($L['STATUS.MAX_CIRCUIT_P2'], easee_status_number_text(easee_status_get($circuits, 'maxCircuitCurrentP2', easee_status_get($chargerConfig, 'circuitMaxCurrentP2')), 'A', 1)),
        array($L['STATUS.MAX_CIRCUIT_P3'], easee_status_number_text(easee_status_get($circuits, 'maxCircuitCurrentP3', easee_status_get($chargerConfig, 'circuitMaxCurrentP3')), 'A', 1)),
        array($L['STATUS.PHASE_MODE_CFG'], easee_status_enum_text('phaseMode', $configPhaseMode, $L)),
        array($L['STATUS.LIMIT_SINGLE_PHASE'], easee_status_bool_text(easee_status_get($chargerConfig, 'limitToSinglePhaseCharging'), $L)),
        array($L['STATUS.OFFLINE_MODE'], easee_status_enum_text('offlineChargingMode', $offlineMode, $L), easee_status_enum_hint('offlineChargingMode', $offlineMode)),
        array($L['STATUS.AUTH_REQUIRED'], easee_status_bool_text(easee_status_get($chargerConfig, 'authorizationRequired'), $L)),
        array($L['STATUS.LOCK_CABLE'], easee_status_bool_text(easee_status_get($chargerConfig, 'lockCablePermanently', easee_status_get($state, 'lockCablePermanently')), $L)),
        array($L['STATUS.IDLE_CURRENT'], easee_status_bool_text(easee_status_get($chargerConfig, 'enableIdleCurrent'), $L)),
        array($L['STATUS.SMART_BUTTON'], easee_status_bool_text(easee_status_get($chargerConfig, 'smartButtonEnabled'), $L)),
        array($L['STATUS.LED_BRIGHTNESS'], easee_status_number_text(easee_status_get($chargerConfig, 'ledStripBrightness'), '', 0)),
        array($L['STATUS.LED_MODE'], easee_status_enum_text('ledMode', $ledMode, $L), easee_status_enum_hint('ledMode', $ledMode))
    ));

    // -----------------------------------------------------------------------
    // Group: data freshness.
    // -----------------------------------------------------------------------
    echo '<fieldset class="status-card">';
    echo '<p class="sec-head">' . $L['STATUS.GROUP_FRESHNESS'] . '</p>';
    echo '<small class="status-hint">' . $L['STATUS.FRESHNESS_HINT'] . '</small>';
    echo '<table class="status-table"><tbody>';
    echo '<tr><th>' . $L['STATUS.SOURCE'] . '</th><td>' . $L['STATUS.TIMESTAMP'] . '</td></tr>';
    $cacheKeys = array_keys($cache);
    sort($cacheKeys);
    foreach ($cacheKeys as $cacheKey) {
        $entry = $cache[$cacheKey];
        $entryUrl = isset($entry['context']['url']) ? $entry['context']['url'] : '';
        echo '<tr><th>easee.php?do=' . htmlspecialchars($cacheKey, ENT_QUOTES);
        if ($entryUrl !== '') {
            echo '<br><small class="status-hint">' . htmlspecialchars($entryUrl, ENT_QUOTES) . '</small>';
        }
        echo '</th><td>' . htmlspecialchars((string)easee_status_timestamp_text(isset($entry['fetchedAtEpoch']) ? $entry['fetchedAtEpoch'] : null, $L), ENT_QUOTES) . '</td></tr>';
    }
    echo '</tbody></table>';

    if (!empty($observationTimestamps)) {
        echo '<details class="status-raw"><summary>' . $L['STATUS.OBSERVATION_TIMESTAMPS'] . '</summary>';
        echo '<table class="status-table"><tbody>';
        ksort($observationTimestamps);
        foreach ($observationTimestamps as $fieldName => $fieldTimestamp) {
            echo '<tr><th>' . htmlspecialchars($fieldName, ENT_QUOTES) . '</th><td>'
                . htmlspecialchars((string)easee_status_timestamp_text($fieldTimestamp, $L), ENT_QUOTES) . '</td></tr>';
        }
        echo '</tbody></table></details>';
    }
    echo '</fieldset>';

    // -----------------------------------------------------------------------
    // Group: all decoded enumeration values found in the cached responses.
    // -----------------------------------------------------------------------
    $enumFieldMap = easee_get_field_enum_map();
    $decodedRows = array();
    foreach ($cacheKeys as $cacheKey) {
        $entryData = isset($cache[$cacheKey]['data']) ? $cache[$cacheKey]['data'] : null;
        if (!is_array($entryData)) {
            continue;
        }
        foreach ($entryData as $fieldName => $fieldValue) {
            if (is_array($fieldValue) || is_bool($fieldValue)) {
                continue;
            }
            $plainName = preg_replace('/^(latest|ongoing)_/', '', (string)$fieldName);
            if (!isset($enumFieldMap[$plainName])) {
                continue;
            }
            $tableName = $enumFieldMap[$plainName];
            $decoded = easee_decode_enum($tableName, $fieldValue);
            if ($decoded === null) {
                continue;
            }
            $rowLabel = $fieldName . ' <small class="status-hint">(' . $tableName . ' / do=' . $cacheKey . ')</small>';
            $decodedRows[] = array(
                $rowLabel,
                easee_status_enum_text($tableName, $fieldValue, $L),
                easee_status_enum_hint($tableName, $fieldValue),
                true
            );
        }
    }
    if (!empty($decodedRows)) {
        echo '<fieldset class="status-card">';
        echo '<p class="sec-head">' . $L['STATUS.GROUP_ENUMS'] . '</p>';
        echo '<small class="status-hint">' . $L['STATUS.ENUMS_HINT'] . '</small>';
        echo '<table class="status-table"><tbody>';
        foreach ($decodedRows as $decodedRow) {
            echo '<tr><th>' . $decodedRow[0] . '</th><td>' . htmlspecialchars((string)$decodedRow[1], ENT_QUOTES);
            if ($decodedRow[2] !== '') {
                echo '<br><small class="status-hint">' . htmlspecialchars((string)$decodedRow[2], ENT_QUOTES) . '</small>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></fieldset>';
    }

    // -----------------------------------------------------------------------
    // Group: raw cached responses.
    // -----------------------------------------------------------------------
    if (!empty($cache)) {
        echo '<fieldset class="status-card status-raw">';
        echo '<p class="sec-head">' . $L['STATUS.GROUP_RAW'] . '</p>';
        foreach ($cacheKeys as $cacheKey) {
            $entry = $cache[$cacheKey];
            echo '<details><summary>' . htmlspecialchars($cacheKey, ENT_QUOTES) . '</summary>';
            echo '<pre>' . htmlspecialchars(json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</pre>';
            echo '</details>';
        }
        echo '</fieldset>';
    }
}

echo '<script>';
echo 'document.addEventListener("DOMContentLoaded", function () {';
echo '  var box = document.getElementById("status-autorefresh");';
echo '  if (!box) { return; }';
echo '  var timer = null;';
echo '  var apply = function () {';
echo '    if (timer) { window.clearInterval(timer); timer = null; }';
echo '    if (box.checked) { timer = window.setInterval(function () { window.location.reload(); }, 30000); }';
echo '  };';
echo '  try { box.checked = window.localStorage.getItem("easeeStatusAutoRefresh") === "1"; } catch (e) {}';
echo '  if (window.jQuery && jQuery(box).checkboxradio) { try { jQuery(box).checkboxradio("refresh"); } catch (e) {} }';
echo '  box.addEventListener("change", function () {';
echo '    try { window.localStorage.setItem("easeeStatusAutoRefresh", box.checked ? "1" : "0"); } catch (e) {}';
echo '    apply();';
echo '  });';
echo '  apply();';
echo '});';
echo '</script>';

LBWeb::lbfooter();
?>
