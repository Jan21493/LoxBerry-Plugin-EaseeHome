<?php

function easee_get_token_file($lbplogdir)
{
    $tokenDir = rtrim($lbplogdir, '/') . '/token';
    if (!is_dir($tokenDir)) {
        @mkdir($tokenDir, 0700, true);
    }
    return $tokenDir . '/easee_token.ini';
}

// Get token.
function get_token($url_base, $url_tocken, $file_token, $username, $password)
{
    $data = array(
        "userName" => "$username",
        "password" => "$password"
    );
    $postdata = json_encode($data);
    $ch = curl_init($url_base . $url_tocken);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
    ));
    $result = curl_exec($ch);
    curl_close($ch);
    file_put_contents($file_token, $result);
    return json_decode($result, true);
}

// Get refresh token.
function get_refresh_token($url_base, $url_tocken, $file_token, $token, $refresh_token)
{
    $data = array(
        "accessToken" => "$token",
        "refreshToken" => "$refresh_token"
    );
    $postdata = json_encode($data);
    $ch = curl_init($url_base . $url_tocken);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json'
    ));
    $result = curl_exec($ch);
    curl_close($ch);
    file_put_contents($file_token, $result);
    return json_decode($result, true);
}

// Get requests.
function get_req($url_base, $url_req, $token)
{
    $ch = curl_init($url_base . $url_req);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

// Post requests.
function post_req($url_base, $url_req, $token, $data)
{
    $data_string = json_encode($data);
    $ch = curl_init($url_base . $url_req);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

// Send JSON.
function send_json($id, $message)
{
    foreach ($message as $k => $v) {
        $message[$id . '_' . $k] = $v;
        unset($message[$k]);
    }
    foreach ($message as $i => $value) {
        if (empty($value)) {
            $message[$i] = 0;
        }
    }
    $message = json_encode($message);
    echo $message;
}

// Send UDP.
function send_udp($id, $message, $ms_ip, $ms_port)
{
    foreach ($message as $k => $v) {
        $message[$id . '_' . $k] = $v;
        unset($message[$k]);
    }
    foreach ($message as $i => $value) {
        if (empty($value)) {
            $message[$i] = 0;
        }
    }
    $message = implode(' ', array_map(function ($v, $k) {
        return sprintf('%s=%s', $k, $v);
    }, $message, array_keys($message)));

    if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
        socket_sendto($socket, $message, strlen($message), 0, $ms_ip, $ms_port);
    } else {
        print("can't create socket\n");
    }
}

// Send MQTT.
function send_mqtt($id, $message)
{
    require_once "loxberry_io.php";
    require_once "phpMQTT.php";

    foreach ($message as $i => $value) {
        if (empty($value)) {
            $message[$i] = 0;
        }
    }

    $creds = mqtt_connectiondetails();
    $client_id = uniqid(gethostname() . "_client");
    $mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'], $creds['brokerport'], $client_id);
    if ($mqtt->connect(true, null, $creds['brokeruser'], $creds['brokerpass'])) {
        foreach ($message as $x => $val) {
            $mqtt->publish("easee/" . $id . "/" . $x, $val, 0, 1);
        }
        $mqtt->close();
    } else {
        echo "MQTT connection failed";
    }
}

// Convert booleans to numbers.
function change_booleans_to_numbers($data)
{
    array_walk_recursive($data, function (&$value) {
        if (is_bool($value)) {
            $value = ($value ? 1 : 0);
        }
    });
    return $data;
}

// Normalize configured log level.
function easee_normalize_log_level($level)
{
    $allowed = array('error', 'warn', 'info', 'debug');
    $normalized = strtolower(trim((string)$level));
    if (!in_array($normalized, $allowed, true)) {
        return 'info';
    }
    return $normalized;
}

// Check if message level should be logged.
function easee_should_log($messageLevel, $configuredLevel)
{
    $levels = array(
        'error' => 0,
        'warn' => 1,
        'info' => 2,
        'debug' => 3
    );

    $messageLevel = easee_normalize_log_level($messageLevel);
    $configuredLevel = easee_normalize_log_level($configuredLevel);
    return $levels[$messageLevel] <= $levels[$configuredLevel];
}

// Write log line with level and context.
function easee_log($level, $message, $context, $file_log_i, $file_log_e, $configuredLevel = 'info')
{
    $level = easee_normalize_log_level($level);
    if (!easee_should_log($level, $configuredLevel)) {
        return false;
    }

    $time = date("Y-m-d H:i:s");
    $line = strtoupper($level) . ': ' . $time . ' - ' . $message;
    if (is_array($context) && !empty($context)) {
        $line .= ' - ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }

    $target = ($level === 'error') ? $file_log_e : $file_log_i;
    file_put_contents($target, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    return true;
}

// Get charger observation id definitions.
function easee_get_observation_definitions()
{
    return array(
        30 => array('parameter' => 'lockCablePermanently', 'description' => 'Lock type 2 cable permanently.'),
        31 => array('parameter' => 'isEnabled', 'description' => 'Set true to enable charger, false disables charger.'),
        46 => array('parameter' => 'ledMode', 'description' => 'Charger LED mode.'),
        47 => array('parameter' => 'maxChargerCurrent', 'description' => 'Maximum charger current (non-volatile).'),
        48 => array('parameter' => 'dynamicChargerCurrent', 'description' => 'Maximum charger current (volatile).'),
        50 => array('parameter' => 'offlineMaxCircuitCurrentP1', 'description' => 'Maximum offline circuit current on phase 1.'),
        51 => array('parameter' => 'offlineMaxCircuitCurrentP2', 'description' => 'Maximum offline circuit current on phase 2.'),
        52 => array('parameter' => 'offlineMaxCircuitCurrentP3', 'description' => 'Maximum offline circuit current on phase 3.'),
        70 => array('parameter' => 'circuitTotalAllocatedPhaseConductorCurrentL1', 'description' => 'Total allocated current on L1 for all chargers in circuit.'),
        71 => array('parameter' => 'circuitTotalAllocatedPhaseConductorCurrentL2', 'description' => 'Total allocated current on L2 for all chargers in circuit.'),
        72 => array('parameter' => 'circuitTotalAllocatedPhaseConductorCurrentL3', 'description' => 'Total allocated current on L3 for all chargers in circuit.'),
        73 => array('parameter' => 'circuitTotalPhaseConductorCurrentL1', 'description' => 'Total actual current on L1 for all chargers in circuit.'),
        74 => array('parameter' => 'circuitTotalPhaseConductorCurrentL2', 'description' => 'Total actual current on L2 for all chargers in circuit.'),
        75 => array('parameter' => 'circuitTotalPhaseConductorCurrentL3', 'description' => 'Total actual current on L3 for all chargers in circuit.'),
        80 => array('parameter' => 'chargerFirmware', 'description' => 'Embedded software package release id.'),
        96 => array('parameter' => 'reasonForNoCurrent', 'description' => 'Why no current is offered while car is connected.'),
        102 => array('parameter' => 'smartCharging', 'description' => 'Smart charging status from touch button.'),
        103 => array('parameter' => 'cableLocked', 'description' => 'Cable lock state.'),
        104 => array('parameter' => 'cableRating', 'description' => 'Detected cable rating.'),
        109 => array('parameter' => 'chargerOpMode', 'description' => 'Charger operation mode.'),
        110 => array('parameter' => 'outputPhase', 'description' => 'Active output phase(s) to EV.'),
        111 => array('parameter' => 'dynamicCircuitCurrentP1', 'description' => 'Dynamic circuit max current phase 1.'),
        112 => array('parameter' => 'dynamicCircuitCurrentP2', 'description' => 'Dynamic circuit max current phase 2.'),
        113 => array('parameter' => 'dynamicCircuitCurrentP3', 'description' => 'Dynamic circuit max current phase 3.'),
        114 => array('parameter' => 'outputCurrent', 'description' => 'Current offered to EV via pilot tone.'),
        115 => array('parameter' => 'deratedCurrent', 'description' => 'Available current after derating.'),
        116 => array('parameter' => 'deratingActive', 'description' => 'Current is reduced due to high temperature.'),
        119 => array('parameter' => 'errorCode', 'description' => 'Error code according to error code table.'),
        120 => array('parameter' => 'totalPower', 'description' => 'Total power.'),
        121 => array('parameter' => 'sessionEnergy', 'description' => 'Accumulated energy for active session.'),
        122 => array('parameter' => 'energyPerHour', 'description' => 'Accumulated energy per hour.'),
        124 => array('parameter' => 'lifetimeEnergy', 'description' => 'Accumulated lifetime energy.'),
        130 => array('parameter' => 'cellRSSI', 'description' => 'Cellular signal strength.'),
        131 => array('parameter' => 'chargerRAT', 'description' => 'Radio access technology (0 cellular, 1 wifi).'),
        132 => array('parameter' => 'wiFiRSSI', 'description' => 'WiFi signal strength.'),
        136 => array('parameter' => 'localRSSI', 'description' => 'Local radio signal strength.'),
        182 => array('parameter' => 'inCurrentT2', 'description' => 'Calculated input current RMS for T2.'),
        183 => array('parameter' => 'inCurrentT3', 'description' => 'Calculated input current RMS for T3.'),
        184 => array('parameter' => 'inCurrentT4', 'description' => 'Calculated input current RMS for T4.'),
        185 => array('parameter' => 'inCurrentT5', 'description' => 'Calculated input current RMS for T5.'),
        190 => array('parameter' => 'inVoltageT1T2', 'description' => 'Input voltage RMS between T1 and T2.'),
        191 => array('parameter' => 'inVoltageT1T3', 'description' => 'Input voltage RMS between T1 and T3.'),
        192 => array('parameter' => 'inVoltageT1T4', 'description' => 'Input voltage RMS between T1 and T4.'),
        193 => array('parameter' => 'inVoltageT1T5', 'description' => 'Input voltage RMS between T1 and T5.'),
        194 => array('parameter' => 'inVoltageT2T3', 'description' => 'Input voltage RMS between T2 and T3.'),
        195 => array('parameter' => 'inVoltageT2T4', 'description' => 'Input voltage RMS between T2 and T4.'),
        196 => array('parameter' => 'inVoltageT2T5', 'description' => 'Input voltage RMS between T2 and T5.'),
        197 => array('parameter' => 'inVoltageT3T4', 'description' => 'Input voltage RMS between T3 and T4.'),
        198 => array('parameter' => 'inVoltageT3T5', 'description' => 'Input voltage RMS between T3 and T5.'),
        199 => array('parameter' => 'inVoltageT4T5', 'description' => 'Input voltage RMS between T4 and T5.'),
        230 => array('parameter' => 'eqAvailableCurrentP1', 'description' => 'Available charging current on phase 1 from Equalizer.'),
        231 => array('parameter' => 'eqAvailableCurrentP2', 'description' => 'Available charging current on phase 2 from Equalizer.'),
        232 => array('parameter' => 'eqAvailableCurrentP3', 'description' => 'Available charging current on phase 3 from Equalizer.'),
        250 => array('parameter' => 'connectedToCloud', 'description' => 'Device is connected to cloud backend.')
    );
}

// Return default observation IDs for lightweight polling.
function easee_get_default_observation_ids()
{
    return array(31, 103, 109, 110, 120, 121, 122, 124, 250);
}

// Parse configured observation IDs.
function easee_get_requested_observation_ids($configuredObservationIds, $availableDefinitions, $defaultObservationIds)
{
    $configuredObservationIds = trim((string)$configuredObservationIds);
    $availableIds = array_map('intval', array_keys($availableDefinitions));

    if ($configuredObservationIds === '') {
        return $defaultObservationIds;
    }

    if (strtolower($configuredObservationIds) === 'all') {
        return $availableIds;
    }

    $parts = preg_split('/\s*,\s*/', $configuredObservationIds, -1, PREG_SPLIT_NO_EMPTY);
    $requested = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if (!ctype_digit($part)) {
            continue;
        }
        $id = intval($part);
        if ($id > 0 && isset($availableDefinitions[$id])) {
            $requested[] = $id;
        }
    }
    $requested = array_values(array_unique($requested));

    if (empty($requested)) {
        return $defaultObservationIds;
    }

    return $requested;
}

// Get file path for persisted charger status.
function easee_get_status_file($lbplogdir, $chargerId)
{
    return $lbplogdir . '/easee_status_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$chargerId) . '.json';
}

// Merge and persist charger status for GUI view.
function easee_update_charger_status($lbplogdir, $chargerId, $statusPatch)
{
    if (empty($chargerId) || !is_array($statusPatch)) {
        return false;
    }

    $statusFile = easee_get_status_file($lbplogdir, $chargerId);
    $lockHandle = fopen($statusFile . '.lock', 'c');
    if ($lockHandle === false) {
        return false;
    }
    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return false;
    }

    $statusData = array();
    if (file_exists($statusFile)) {
        $stored = json_decode(file_get_contents($statusFile), true);
        if (is_array($stored)) {
            $statusData = $stored;
        }
    }

    $statusData = array_merge($statusData, $statusPatch);
    $statusData['chargerId'] = (string)$chargerId;
    $statusData['updatedAtIso'] = isset($statusPatch['updatedAtIso']) ? $statusPatch['updatedAtIso'] : gmdate('c');

    $encoded = json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tempFile = tempnam($lbplogdir, 'easee_status_');
    $updated = $encoded !== false
        && $tempFile !== false
        && file_put_contents($tempFile, $encoded, LOCK_EX) !== false
        && rename($tempFile, $statusFile);
    if ($tempFile !== false && file_exists($tempFile)) {
        unlink($tempFile);
    }
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    return $updated;
}

// Read all persisted charger status files.
function easee_read_all_charger_status($lbplogdir)
{
    $statusByCharger = array();
    foreach (glob($lbplogdir . '/easee_status_*.json') as $statusFile) {
        $statusData = json_decode(file_get_contents($statusFile), true);
        if (is_array($statusData) && !empty($statusData['chargerId'])) {
            $statusByCharger[$statusData['chargerId']] = $statusData;
        }
    }
    return $statusByCharger;
}

// Check response data for API errors and stop on error.
function check_data($data, $url, $file_log, $file_log_i = null, $configuredLogLevel = 'info')
{
    if (array_key_exists('status', $data)) {
        $status = isset($data['status']) ? $data['status'] : 'unknown';
        $title = isset($data['title']) ? $data['title'] : 'unknown';
        echo 'Something went wrong. Error: ' . $status . ' (' . $title . ')';

        if ($file_log_i === null) {
            $file_log_i = $file_log;
        }

        easee_log('error', 'API request failed', array(
            'url' => $url,
            'response' => $data
        ), $file_log_i, $file_log, $configuredLogLevel);
        exit;
    }
}

// Log error.
function log_e($text, $url, $file_log)
{
    easee_log('error', (string)$url, array('message' => $text), $file_log, $file_log, 'debug');
}

// Log info.
function log_i($text, $url, $file_log)
{
    easee_log('info', (string)$url, array('message' => $text), $file_log, $file_log, 'debug');
}
