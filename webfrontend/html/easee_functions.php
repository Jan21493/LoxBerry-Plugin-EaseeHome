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
        21 => array('parameter' => 'detectedPowerGridType', 'description' => 'Detected power grid type according to grid type table.'),
        30 => array('parameter' => 'lockCablePermanently', 'description' => 'Lock type 2 cable permanently.'),
        31 => array('parameter' => 'isEnabled', 'description' => 'Set true to enable charger, false disables charger.'),
        38 => array('parameter' => 'phaseMode', 'description' => 'Phase mode: 1 locked to 1-phase, 2 auto, 3 locked to 3-phase.'),
        45 => array('parameter' => 'offlineChargingMode', 'description' => 'Charging mode while the charger is offline.'),
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
        89 => array('parameter' => 'rebootReason', 'description' => 'Reason for the last charger reboot.'),
        96 => array('parameter' => 'reasonForNoCurrent', 'description' => 'Why no current is offered while car is connected.'),
        100 => array('parameter' => 'pilotMode', 'description' => 'Control pilot state (A disconnected, B connected, C charging, D ventilation, F fault).'),
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
    return array(31, 103, 109, 120, 121, 122, 124, 250);
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

    if (strtolower($configuredObservationIds) === 'none') {
        return array();
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

// Enumeration tables, see https://developer.easee.com/docs/enumerations
function easee_get_enum_tables()
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }

    $ledMode = array(
        0 => array('name' => 'Charger Disabled', 'description' => 'Charger is disabled.'),
        18 => array('name' => 'Standby Master', 'description' => 'Standby master.'),
        19 => array('name' => 'Standby Secondary', 'description' => 'Standby secondary.'),
        20 => array('name' => 'Secondary Unit Searching', 'description' => 'Secondary unit searching for master.'),
        21 => array('name' => 'Smart Mode (Not Charging)', 'description' => 'Smart mode, not charging.'),
        22 => array('name' => 'Smart Mode (Charging)', 'description' => 'Smart mode, charging.'),
        23 => array('name' => 'Normal Mode (Not Charging)', 'description' => 'Normal mode, not charging.'),
        24 => array('name' => 'Normal Mode (Charging)', 'description' => 'Normal mode, charging.'),
        25 => array('name' => 'Waiting for Authorization', 'description' => 'Waiting for authorization.'),
        26 => array('name' => 'Verifying with Backend', 'description' => 'Verifying with backend.'),
        27 => array('name' => 'Check Configuration', 'description' => 'Check configuration (backplate chip defect).'),
        29 => array('name' => 'Pairing RFID Keys', 'description' => 'Pairing RFID keys.')
    );
    for ($ledValue = 1; $ledValue <= 15; $ledValue++) {
        $ledMode[$ledValue] = array('name' => 'Charger Updating', 'description' => 'Charger is updating.');
    }
    for ($ledValue = 16; $ledValue <= 17; $ledValue++) {
        $ledMode[$ledValue] = array('name' => 'Charger Faulty', 'description' => 'Charger is faulty.');
    }
    for ($ledValue = 43; $ledValue <= 44; $ledValue++) {
        $ledMode[$ledValue] = array('name' => 'Self Test Mode', 'description' => 'Self test mode.');
    }
    ksort($ledMode);

    $tables = array(
        'chargerColor' => array(
            1 => array('name' => 'Black', 'description' => ''),
            2 => array('name' => 'Red', 'description' => ''),
            3 => array('name' => 'Blue', 'description' => ''),
            4 => array('name' => 'White', 'description' => ''),
            5 => array('name' => 'Anthracite', 'description' => '')
        ),
        'offlineChargingMode' => array(
            0 => array('name' => 'Always', 'description' => 'Always allow charging if offline.'),
            1 => array('name' => 'IfWhitelisted', 'description' => 'Only allow charging if token is allowed in the local token cache.'),
            2 => array('name' => 'Never', 'description' => 'Never allow charging if offline.')
        ),
        'opMode' => array(
            0 => array('name' => 'Offline', 'description' => 'Offline.'),
            1 => array('name' => 'Disconnected', 'description' => 'No car connected.'),
            2 => array('name' => 'Awaiting Start', 'description' => 'Car connected, charger is waiting for EV or load balancing (SuspendedEVSE).'),
            3 => array('name' => 'Charging', 'description' => 'Charging.'),
            4 => array('name' => 'Completed', 'description' => 'Car has paused/stopped charging.'),
            5 => array('name' => 'Error', 'description' => 'Error in charger.'),
            6 => array('name' => 'Ready to Charge', 'description' => 'Charger is waiting for car to take energy (SuspendedEV).'),
            7 => array('name' => 'Awaiting Authentication', 'description' => 'Charger is waiting for authentication.'),
            8 => array('name' => 'De-authenticating', 'description' => 'Charger is de-authenticating.')
        ),
        'outputPhase' => array(
            0 => array('name' => 'UNASSIGNED', 'description' => 'Unassigned', 'phases' => 0),
            10 => array('name' => 'P1_T2_T3_TN', 'description' => '1-phase (N+L1)', 'phases' => 1),
            11 => array('name' => 'P1_T2_T3_IT', 'description' => '1-phase (L1+L2)', 'phases' => 1),
            12 => array('name' => 'P1_T2_T4_TN', 'description' => '1-phase (N+L2)', 'phases' => 1),
            13 => array('name' => 'P1_T2_T4_IT', 'description' => '1-phase (L1+L3)', 'phases' => 1),
            14 => array('name' => 'P1_T2_T5_TN', 'description' => '1-phase (N+L3)', 'phases' => 1),
            15 => array('name' => 'P1_T3_T4_IT', 'description' => '1-phase (L2+L3)', 'phases' => 1),
            20 => array('name' => 'P2_T2_T3_T4_TN', 'description' => '2-phases on TN (N+L1, N+L2)', 'phases' => 2),
            21 => array('name' => 'P2_T2_T4_T5_TN', 'description' => '2-phases on TN (N+L2, N+L3)', 'phases' => 2),
            22 => array('name' => 'P2_T2_T3_T4_IT', 'description' => '2-phases on IT (L1+L2, L2+L3)', 'phases' => 2),
            30 => array('name' => 'P3_T2_T3_T4_T5_TN', 'description' => '3-phases (N+L1, N+L2, N+L3)', 'phases' => 3)
        ),
        'pairingResultCode' => array(
            0 => array('name' => 'Success', 'description' => ''),
            1 => array('name' => 'WrongAccountType', 'description' => ''),
            2 => array('name' => 'TooManyAttempts', 'description' => ''),
            3 => array('name' => 'AlreadyPairedWithPartner', 'description' => ''),
            4 => array('name' => 'IncorrectPIN', 'description' => ''),
            5 => array('name' => 'AlreadyPairedWithUser', 'description' => ''),
            6 => array('name' => 'NotPairedWithPartner', 'description' => ''),
            7 => array('name' => 'NotPairedWithUser', 'description' => '')
        ),
        'reasonForNoCurrent' => array(
            0 => array('name' => 'Charger Fine', 'description' => 'Charger is OK, use main charger status.'),
            1 => array('name' => 'Load balancing', 'description' => 'Max circuit current too low, adjust power circuit up.'),
            2 => array('name' => 'Load balancing', 'description' => 'Max dynamic circuit current too low (partner load balancing).'),
            3 => array('name' => 'Load balancing', 'description' => 'Max dynamic offline fallback circuit current too low.'),
            4 => array('name' => 'Load balancing', 'description' => 'Circuit fuse too low.'),
            5 => array('name' => 'Load balancing', 'description' => 'Waiting in queue.'),
            6 => array('name' => 'Load balancing', 'description' => 'Waiting in fully charged queue (EV charging complete).'),
            7 => array('name' => 'Error', 'description' => 'Illegal grid type (fault in automatic grid type detection).'),
            8 => array('name' => 'Error', 'description' => 'Primary unit has not received current request from secondary unit (car).'),
            9 => array('name' => 'Error', 'description' => 'Master communication lost.'),
            10 => array('name' => 'Error', 'description' => 'No current, current from Equalizer too low.'),
            11 => array('name' => 'Error', 'description' => 'No current, phase not connected.'),
            25 => array('name' => 'Error', 'description' => 'Current limited by circuit fuse.'),
            26 => array('name' => 'Error', 'description' => 'Current limited by circuit max current.'),
            27 => array('name' => 'Error', 'description' => 'Current limited by dynamic circuit current.'),
            28 => array('name' => 'Error', 'description' => 'Current limited by Equalizer.'),
            29 => array('name' => 'Error', 'description' => 'Current limited by circuit load balancing.'),
            30 => array('name' => 'Error', 'description' => 'Current limited by offline settings.'),
            50 => array('name' => 'Load balancing circuit', 'description' => 'Secondary unit not requesting current (no car connected).'),
            51 => array('name' => 'Load balancing circuit', 'description' => 'Max charger current too low.'),
            52 => array('name' => 'Load balancing circuit', 'description' => 'Max dynamic charger current too low.'),
            53 => array('name' => 'Informational', 'description' => 'Charger disabled.'),
            54 => array('name' => 'Waiting', 'description' => 'Pending scheduled charging.'),
            55 => array('name' => 'Waiting', 'description' => 'Pending authorization.'),
            56 => array('name' => 'Error', 'description' => 'Charger in error state.'),
            57 => array('name' => 'Error', 'description' => 'Erratic EV.'),
            75 => array('name' => 'Cable', 'description' => 'Current limited by cable rating.'),
            76 => array('name' => 'Schedule', 'description' => 'Current limited by schedule.'),
            77 => array('name' => 'Charger Limit', 'description' => 'Current limited by charger max current.'),
            78 => array('name' => 'Charger Limit', 'description' => 'Current limited by dynamic charger current.'),
            79 => array('name' => 'Car Limit', 'description' => 'Current limited by car not charging.'),
            80 => array('name' => 'Local Adjustment', 'description' => 'Current limited by local adjustment (current is ramping up or limited by the car).'),
            81 => array('name' => 'Car Limit', 'description' => 'Current limited by car.'),
            100 => array('name' => 'UndefinedError', 'description' => 'Undefined error.')
        ),
        'ledMode' => $ledMode,
        'phaseMode' => array(
            0 => array('name' => 'Ignore, no phase mode reported', 'description' => ''),
            1 => array('name' => 'Locked to 1-phase', 'description' => ''),
            2 => array('name' => 'Auto phase mode', 'description' => ''),
            3 => array('name' => 'Locked to 3-phase', 'description' => '')
        ),
        'pilotMode' => array(
            'A' => array('name' => 'Car Disconnected', 'description' => 'Car disconnected.'),
            'B' => array('name' => 'Car Connected', 'description' => 'Car connected.'),
            'C' => array('name' => 'Car Charging', 'description' => 'Car charging.'),
            'D' => array('name' => 'Car Needs Ventilation', 'description' => 'Car needs ventilation.'),
            'F' => array('name' => 'Fault Detected', 'description' => 'Fault detected (LED goes red and charging stops).')
        ),
        'rebootReason' => array(
            0 => array('name' => 'FirewallReset', 'description' => ''),
            1 => array('name' => 'OptionByteLoaderReset', 'description' => ''),
            2 => array('name' => 'PinReset', 'description' => ''),
            3 => array('name' => 'BOR', 'description' => ''),
            4 => array('name' => 'SoftwareReset', 'description' => ''),
            5 => array('name' => 'IndependentWindowWatchdogReset', 'description' => ''),
            6 => array('name' => 'WindowWatchdogReset', 'description' => ''),
            7 => array('name' => 'LowPowerReset', 'description' => ''),
            20 => array('name' => 'Reboot', 'description' => '')
        ),
        'detectedPowerGridType' => array(
            1 => array('name' => 'TN3Phase', 'description' => ''),
            2 => array('name' => 'TN2PhasePin234', 'description' => ''),
            3 => array('name' => 'TN1Phase', 'description' => ''),
            4 => array('name' => 'IT3Phase', 'description' => ''),
            5 => array('name' => 'IT1Phase', 'description' => ''),
            30 => array('name' => 'WarningTN2PhasePin235', 'description' => ''),
            31 => array('name' => 'WarningTN1PhaseNeutralOnPin3', 'description' => ''),
            32 => array('name' => 'WARNING_IT_3_PHASE_GND_FAULT', 'description' => ''),
            33 => array('name' => 'WARNING_IT_1_PHASE_GND_FAULT', 'description' => ''),
            34 => array('name' => 'WARNING_IT_3_PHASE_GND_FAULT_L3', 'description' => ''),
            35 => array('name' => 'WARNING_IT_1_PHASE_GND_FAULT_L3', 'description' => ''),
            36 => array('name' => 'WARNING_TN_2_PHASE_PIN_2_3_4', 'description' => ''),
            37 => array('name' => 'WARNING_TN_3_PHASE_GND_FAULT', 'description' => ''),
            38 => array('name' => 'WARNING_TN_2_PHASE_GND_FAULT', 'description' => ''),
            50 => array('name' => 'ErrorNoValidPowerGridFound', 'description' => ''),
            51 => array('name' => 'ErrorTN400VNeutralOnWrongPin', 'description' => ''),
            52 => array('name' => 'ErrorITGroundConnectedToPin2Or3', 'description' => '')
        )
    );

    return $tables;
}

// Map observation IDs to the matching enumeration table.
function easee_get_observation_enum_map()
{
    return array(
        21 => 'detectedPowerGridType',
        38 => 'phaseMode',
        45 => 'offlineChargingMode',
        46 => 'ledMode',
        89 => 'rebootReason',
        96 => 'reasonForNoCurrent',
        100 => 'pilotMode',
        109 => 'opMode',
        110 => 'outputPhase'
    );
}

// Map JSON field names (state, config, ...) to the matching enumeration table.
function easee_get_field_enum_map()
{
    return array(
        'chargerOpMode' => 'opMode',
        'opMode' => 'opMode',
        'outputPhase' => 'outputPhase',
        'reasonForNoCurrent' => 'reasonForNoCurrent',
        'ledMode' => 'ledMode',
        'ledStripMode' => 'ledMode',
        'phaseMode' => 'phaseMode',
        'pilotMode' => 'pilotMode',
        'offlineChargingMode' => 'offlineChargingMode',
        'detectedPowerGridType' => 'detectedPowerGridType',
        'rebootReason' => 'rebootReason',
        'color' => 'chargerColor',
        'chargerColor' => 'chargerColor',
        'pairingResultCode' => 'pairingResultCode'
    );
}

// Decode a value of an enumeration table.
function easee_decode_enum($tableName, $value)
{
    $tables = easee_get_enum_tables();
    if (!isset($tables[$tableName])) {
        return null;
    }
    if ($value === null || $value === '') {
        return null;
    }

    $table = $tables[$tableName];
    $key = $value;
    if (!isset($table[$key]) && is_numeric($value)) {
        $key = intval($value);
    }
    if (!isset($table[$key]) && is_string($value)) {
        $key = strtoupper(trim($value));
    }
    if (!isset($table[$key])) {
        return array(
            'value' => $value,
            'name' => '',
            'description' => '',
            'known' => false
        );
    }

    $entry = $table[$key];
    $entry['value'] = $key;
    $entry['known'] = true;
    if (!isset($entry['description'])) {
        $entry['description'] = '';
    }
    return $entry;
}

// Number of active phases of an outputPhase (observation 110) value.
function easee_get_output_phase_count($value)
{
    $decoded = easee_decode_enum('outputPhase', $value);
    if (!is_array($decoded) || !isset($decoded['phases'])) {
        return null;
    }
    return intval($decoded['phases']);
}

// Directory for cached API responses inside the (RAM based) log directory.
function easee_get_cache_dir($lbplogdir)
{
    $cacheDir = rtrim($lbplogdir, '/') . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    return $cacheDir;
}

// Build a safe file name part.
function easee_sanitize_cache_part($part)
{
    $part = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$part);
    return ($part === '') ? 'unknown' : $part;
}

// Get file path of a cached API response.
function easee_get_cache_file($lbplogdir, $scopeId, $cacheKey)
{
    return easee_get_cache_dir($lbplogdir) . '/easee_cache_'
        . easee_sanitize_cache_part($scopeId) . '__'
        . easee_sanitize_cache_part($cacheKey) . '.json';
}

// Persist the JSON response of a plugin call so the GUI can show the status
// without sending additional requests to the Easee Cloud API.
function easee_cache_response($lbplogdir, $scopeId, $cacheKey, $payload, $context = array())
{
    if (empty($scopeId) || empty($cacheKey)) {
        return false;
    }

    $entry = array(
        'scopeId' => (string)$scopeId,
        'key' => (string)$cacheKey,
        'fetchedAtEpoch' => time(),
        'fetchedAtIso' => date('c'),
        'context' => is_array($context) ? $context : array(),
        'data' => $payload
    );

    $encoded = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return false;
    }

    $cacheFile = easee_get_cache_file($lbplogdir, $scopeId, $cacheKey);
    $tempFile = tempnam(dirname($cacheFile), 'easee_cache_');
    if ($tempFile === false) {
        return false;
    }
    $written = file_put_contents($tempFile, $encoded, LOCK_EX) !== false
        && @chmod($tempFile, 0664) !== false
        && rename($tempFile, $cacheFile);
    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }
    return $written;
}

// Read a single cached API response.
function easee_read_cached_response($lbplogdir, $scopeId, $cacheKey)
{
    $cacheFile = easee_get_cache_file($lbplogdir, $scopeId, $cacheKey);
    if (!file_exists($cacheFile)) {
        return null;
    }
    $entry = json_decode(@file_get_contents($cacheFile), true);
    return is_array($entry) ? $entry : null;
}

// Read all cached API responses, grouped by scope (charger ID or "account").
function easee_read_all_cached_responses($lbplogdir)
{
    $cachedByScope = array();
    $cacheFiles = glob(easee_get_cache_dir($lbplogdir) . '/easee_cache_*.json');
    if (!is_array($cacheFiles)) {
        return $cachedByScope;
    }
    foreach ($cacheFiles as $cacheFile) {
        $entry = json_decode(@file_get_contents($cacheFile), true);
        if (!is_array($entry) || empty($entry['scopeId']) || empty($entry['key'])) {
            continue;
        }
        $cachedByScope[$entry['scopeId']][$entry['key']] = $entry;
    }
    return $cachedByScope;
}

// Scope name used for account wide responses (chargers, sites).
function easee_get_account_scope()
{
    return 'account';
}

// Get file path of the phase switch history.
function easee_get_phase_history_file($lbplogdir, $chargerId)
{
    return easee_get_cache_dir($lbplogdir) . '/easee_phases_' . easee_sanitize_cache_part($chargerId) . '.json';
}

// Read the phase switch history.
function easee_read_phase_history($lbplogdir, $chargerId)
{
    $historyFile = easee_get_phase_history_file($lbplogdir, $chargerId);
    if (!file_exists($historyFile)) {
        return array('events' => array());
    }
    $history = json_decode(@file_get_contents($historyFile), true);
    if (!is_array($history) || !isset($history['events']) || !is_array($history['events'])) {
        return array('events' => array());
    }
    return $history;
}

// Append a phase event. Repeated identical events within 10 minutes are
// aggregated to keep the history file small.
function easee_record_phase_event($lbplogdir, $chargerId, $eventType, $details = array())
{
    if (empty($chargerId) || empty($eventType)) {
        return false;
    }

    $historyFile = easee_get_phase_history_file($lbplogdir, $chargerId);
    $lockHandle = fopen($historyFile . '.lock', 'c');
    if ($lockHandle === false) {
        return false;
    }
    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        return false;
    }

    $history = easee_read_phase_history($lbplogdir, $chargerId);
    $events = $history['events'];
    $now = time();
    $isSwitch = (strpos($eventType, 'switch_') === 0 || strpos($eventType, 'observed_') === 0);
    $lastIndex = count($events) - 1;

    if (!$isSwitch
        && $lastIndex >= 0
        && isset($events[$lastIndex]['type'])
        && $events[$lastIndex]['type'] === $eventType
        && ($now - intval($events[$lastIndex]['lastEpoch'])) < 600) {
        $events[$lastIndex]['lastEpoch'] = $now;
        $events[$lastIndex]['lastIso'] = date('c');
        $events[$lastIndex]['count'] = intval($events[$lastIndex]['count']) + 1;
        if (is_array($details)) {
            $events[$lastIndex]['details'] = $details;
        }
    } else {
        $events[] = array(
            'type' => $eventType,
            'firstEpoch' => $now,
            'lastEpoch' => $now,
            'firstIso' => date('c'),
            'lastIso' => date('c'),
            'count' => 1,
            'details' => is_array($details) ? $details : array()
        );
    }

    // Keep one week of history, limited to 500 entries.
    $cutoff = $now - (7 * 86400);
    $events = array_values(array_filter($events, function ($event) use ($cutoff) {
        return isset($event['lastEpoch']) && intval($event['lastEpoch']) >= $cutoff;
    }));
    if (count($events) > 500) {
        $events = array_slice($events, -500);
    }

    $history['events'] = $events;
    $history['chargerId'] = (string)$chargerId;
    $history['updatedAtEpoch'] = $now;
    $history['updatedAtIso'] = date('c');

    $encoded = json_encode($history, JSON_UNESCAPED_SLASHES);
    $written = ($encoded !== false) && (file_put_contents($historyFile, $encoded, LOCK_EX) !== false);

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    return $written;
}

// Count phase events within the given time window (default: last 24 hours).
function easee_get_phase_statistics($lbplogdir, $chargerId, $windowSeconds = 86400)
{
    $history = easee_read_phase_history($lbplogdir, $chargerId);
    $cutoff = time() - intval($windowSeconds);
    $statistics = array(
        'switch_1_to_3' => 0,
        'switch_3_to_1' => 0,
        'observed_1_to_3' => 0,
        'observed_3_to_1' => 0,
        'hold_1_to_3' => 0,
        'hold_3_to_1' => 0,
        'switchesTotal' => 0,
        'observedTotal' => 0,
        'holdsTotal' => 0,
        'lastSwitch' => null,
        'lastHold' => null
    );

    foreach ($history['events'] as $event) {
        if (!isset($event['type']) || !isset($event['lastEpoch'])) {
            continue;
        }
        $type = $event['type'];
        if (!isset($statistics[$type])) {
            continue;
        }
        if (intval($event['lastEpoch']) >= $cutoff) {
            // Aggregated events that started before the window are counted once.
            $count = (intval($event['firstEpoch']) >= $cutoff) ? max(1, intval($event['count'])) : 1;
            $statistics[$type] += $count;
        }
        if (strpos($type, 'switch_') === 0 || strpos($type, 'observed_') === 0) {
            if ($statistics['lastSwitch'] === null || intval($event['lastEpoch']) > intval($statistics['lastSwitch']['lastEpoch'])) {
                $statistics['lastSwitch'] = $event;
            }
        }
        if (strpos($type, 'hold_') === 0) {
            if ($statistics['lastHold'] === null || intval($event['lastEpoch']) > intval($statistics['lastHold']['lastEpoch'])) {
                $statistics['lastHold'] = $event;
            }
        }
    }

    $statistics['switchesTotal'] = $statistics['switch_1_to_3'] + $statistics['switch_3_to_1'];
    $statistics['observedTotal'] = $statistics['observed_1_to_3'] + $statistics['observed_3_to_1'];
    $statistics['holdsTotal'] = $statistics['hold_1_to_3'] + $statistics['hold_3_to_1'];
    $statistics['windowSeconds'] = intval($windowSeconds);
    $statistics['events'] = $history['events'];
    return $statistics;
}

// File that keeps the last requested phase count and switch time.
function easee_get_phase_state_file($lbplogdir, $chargerId)
{
    return $lbplogdir . '/easee_' . $chargerId . '_state.log';
}

// Read the last requested phase count and switch time.
function easee_read_phase_state($lbplogdir, $chargerId)
{
    $stateFile = easee_get_phase_state_file($lbplogdir, $chargerId);
    $state = array('last_phase' => 0, 'last_switch' => 0);
    if (file_exists($stateFile)) {
        $decoded = json_decode(@file_get_contents($stateFile), true);
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
        }
    }
    return $state;
}

// Track output phase changes reported by the charger (observation 110).
function easee_track_output_phase($lbplogdir, $chargerId, $outputPhase)
{
    $phaseCount = easee_get_output_phase_count($outputPhase);
    if ($phaseCount === null || $phaseCount < 1) {
        return false;
    }

    $trackFile = easee_get_cache_dir($lbplogdir) . '/easee_outputphase_' . easee_sanitize_cache_part($chargerId) . '.json';
    $previous = json_decode(@file_get_contents($trackFile), true);
    $previousCount = (is_array($previous) && isset($previous['phaseCount'])) ? intval($previous['phaseCount']) : null;

    if ($previousCount !== null && $previousCount !== $phaseCount) {
        if ($previousCount === 1 && $phaseCount === 3) {
            easee_record_phase_event($lbplogdir, $chargerId, 'observed_1_to_3', array('outputPhase' => $outputPhase));
        } elseif ($previousCount === 3 && $phaseCount === 1) {
            easee_record_phase_event($lbplogdir, $chargerId, 'observed_3_to_1', array('outputPhase' => $outputPhase));
        }
    }

    file_put_contents($trackFile, json_encode(array(
        'chargerId' => (string)$chargerId,
        'phaseCount' => $phaseCount,
        'outputPhase' => $outputPhase,
        'updatedAtEpoch' => time(),
        'updatedAtIso' => date('c')
    ), JSON_UNESCAPED_SLASHES), LOCK_EX);
    return true;
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
