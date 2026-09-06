<?php

require_once "loxberry_system.php";
include 'easee_functions.php';
error_reporting(0);
set_time_limit(15);

//CONFIG
$url_base    = 'https://api.easee.com';
$file_token  = $lbpconfigdir.'/easee_token.ini';
$file_config = $lbpconfigdir.'/easee_config.ini';
$file_log_e	 = $lbplogdir.'/easee-error.log';
$file_log_i	 = $lbplogdir.'/easee-info.log';
//---------------------------------------------------------------------------------------------------
$do          = ($_GET["do"]);
$chargerId   = ($_GET["id"]);
$type        = ($_GET["type"]);
$value       = ($_GET["value"]);
//---------------------------------------------------------------------------------------------------
//READ CONFIG-FILE
$config      = json_decode(file_get_contents($file_config), true);
$token       = json_decode(file_get_contents($file_token), true);
//START DO
if (!empty($do)) {
    //CHECK TOKEN
    $url_tocken = '/api/accounts/login';
	$url_refresh_tocken = '/api/accounts/refresh_token';
    if (array_key_exists('status',$token)) {
        $token_time_diff = 86001;
    } elseif (empty($token)){
		$token_time_diff = 86001;
	} else {
        $time_now        = time();
        $time_file       = filemtime($file_token);
        $token_time_diff = $time_now - $time_file;
    }
	if ($token_time_diff > 900 && $token_time_diff < 86000) {
		get_refresh_token($url_base, $url_refresh_tocken, $file_token, $token['accessToken'], $token['refreshToken']);
        $token = json_decode(file_get_contents($file_token), true);
		echo 'TOKEN: '.$token;
		if (array_key_exists('status',$token)) {
            check_data($token, $url_tocken, $file_log_e);
			exit;			
        } else {
			$text='Refresh Token created.';
			log_i($text, $url_tocken, $file_log_i);
		}
	}
    if ($token_time_diff >= 86000) {
        get_token($url_base, $url_tocken, $file_token, $config[user][username], $config[user][password]);
        $token = json_decode(file_get_contents($file_token), true);
		echo 'TOKEN: '.$token;
        if (array_key_exists('status',$token)) {
            check_data($token, $url_tocken, $file_log_e);
			exit;			
        } else {
			$text='New Token created.';
			log_i($text, $url_tocken, $file_log_i);
		}
    }
}

//Check ID / VALUE
$do_id = array(
    "site",
	"sites",
	"config",
	"circuits",
	"post_dynamicCurrent",
	"post_dynamicPower",
	"equalizer",			 			 
    "state",
    "start_charging",
    "stop_charging",
    "pause_charging",
    "resume_charging",
    "reboot",
    "force_reboot",
    "latest",
    "ongoing",
    "post_lock_state",
    "post_settings",
	"poll_all",
	"poll_lifetimeenergy"
	
);

$do_value      = array(
    "lock_state",
    "post_settings"
);

$settings_type = array(
    "enabled",
    "enableIdleCurrent",
    "limitToSinglePhaseCharging",
    "lockCablePermanently",
    "smartButtonEnabled",
    "phaseMode",
    "smartCharging",
    "localPreAuthorizeEnabled",
    "localAuthorizeOfflineEnabled",
    "allowOfflineTxForUnknownId",
    "offlineChargingMode",
    "authorizationRequired",
    "remoteStartRequired",
    "ledStripBrightness",
    "maxChargerCurrent",
    "dynamicChargerCurrent",
	"dynamicCircuitCurrent"
);

if (in_array("$do", $do_id)) { if (empty($chargerId)) { echo '!! id is missing !!'; exit; }}
if ($do == 'post_settings') { if (!in_array("$type", $settings_type)) { echo '!! type is missing !!'; exit; }}
if (in_array("$do", $do_value)) { if (empty($value)) { echo '!! value is missing !!'; exit; }}

//Start do
switch ($do) {
    //GET (Get settings)  
    case "sites":
        $url_get_chargers = '/api/sites';
        $data = get_req($url_base, $url_get_chargers, $token['accessToken']);
		check_data($data, $url, $file_log_e);
		if (array_key_exists('status',$data)) {
		echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		exit;
		}        
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }		
        break;
    case "chargers":
        $url_get_chargers = '/api/chargers';
        $data = get_req($url_base, $url_get_chargers, $token['accessToken']);
		check_data($data, $url, $file_log_e);
		if (array_key_exists('status',$data)) {
		echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		exit;
		}        
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }		
        break;
    case "config":
        $url  = '/api/chargers/' . $chargerId . '/config';
        $data = get_req($url_base, $url, $token['accessToken']);
		check_data($data, $url, $file_log_e);
		if (array_key_exists('status',$data)) {
		echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		exit;
		}
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
		if ($config['send_mqtt'] == 1) {
           $res_mqtt=send_mqtt($chargerId, $data);
        }
        if ($config['send_json'] == 1) {
            $res_json=send_json($chargerId, $data);
			echo $res_json;
        }
	
        break;
    case "equalizer":
            $url  = '/api/equalizers/' . $chargerId . '/state';
            $data = get_req($url_base, $url, $token['accessToken']);
            check_data($data, $url, $file_log_e);
            if (array_key_exists('status',$data)) {
            echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
        exit;
		}
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
		if ($config['send_mqtt'] == 1) {
           $res_mqtt=send_mqtt($chargerId, $data);
        }
        if ($config['send_json'] == 1) {
            $res_json=send_json($chargerId, $data);
			echo $res_json;
        }  
        break;
    case "site":
        $url  = '/api/chargers/' . $chargerId . '/site';
        $data = get_req($url_base, $url, $token['accessToken']);
		check_data($data, $url, $file_log_e);
		if (array_key_exists('status',$data)) {
		echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		exit;
		}        	
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }		
        break;	
    case "state":
        // Fetch the following Charger Observation Ids, see https://developer.easee.com/docs/charger-observation-ids
        // Complete observation ID => former /state field-name lookup table.
        $idToFieldMap = [
            31 => 'isEnabled', // Set true to enable charger, false disables charger.
            102 => 'smartCharging', // Smart charging state enabled by capacitive touch button.
            103 => 'cableLocked', // Cable lock state.
            109 => 'chargerOpMode', // Charger operation mode according to charger mode table.
            120 => 'totalPower', // Total power.
            121 => 'sessionEnergy', // Session accumulated energy.
            122 => 'energyPerHour', // Accumulated energy per hour.
            124 => 'lifetimeEnergy', // Accumulated energy in the lifetime of the charger.
            132 => 'wiFiRSSI', // WiFi signal strength.
            130 => 'cellRSSI', // Cellular signal strength.
            136 => 'localRSSI', // Local radio signal strength.
            110 => 'outputPhase', // Active output phase(s) to EV according to output phase type table.
            111 => 'dynamicCircuitCurrentP1', // Dynamically set circuit maximum current for phase 1.
            112 => 'dynamicCircuitCurrentP2', // Dynamically set circuit maximum current for phase 2.
            113 => 'dynamicCircuitCurrentP3', // Dynamically set circuit maximum current for phase 3.
            80 => 'chargerFirmware', // Embedded software package release id.
            131 => 'chargerRAT', // Radio access technology in use: 0 = cellular, 1 = wifi.
            30 => 'lockCablePermanently', // Lock type 2 cable permanently.
            182 => 'inCurrentT2', // Calculated current RMS for input T2.
            183 => 'inCurrentT3', // Current RMS for input T3.
            184 => 'inCurrentT4', // Current RMS for input T4.
            185 => 'inCurrentT5', // Current RMS for input T5.
            114 => 'outputCurrent', // Available current signaled to car with pilot tone.
            190 => 'inVoltageT1T2', // Input voltage RMS between T1 and T2.
            191 => 'inVoltageT1T3', // Input voltage RMS between T1 and T3.
            192 => 'inVoltageT1T4', // Input voltage RMS between T1 and T4.
            193 => 'inVoltageT1T5', // Input voltage RMS between T1 and T5.
            194 => 'inVoltageT2T3', // Input voltage RMS between T2 and T3.
            195 => 'inVoltageT2T4', // Input voltage RMS between T2 and T4.
            196 => 'inVoltageT2T5', // Input voltage RMS between T2 and T5.
            197 => 'inVoltageT3T4', // Input voltage RMS between T3 and T4.
            198 => 'inVoltageT3T5', // Input voltage RMS between T3 and T5.
            199 => 'inVoltageT4T5', // Input voltage RMS between T4 and T5.
            46 => 'ledMode', // Charger LED mode.
            104 => 'cableRating', // Cable rating read.
            48 => 'dynamicChargerCurrent', // Max current this charger is allowed to offer to car. Volatile.
            47 => 'maxChargerCurrent', // Max current this charger is allowed to offer to car. Non volatile.
            70 => 'circuitTotalAllocatedPhaseConductorCurrentL1', // Total current allocated to L1 by all chargers on the circuit. Sent in by master only.
            71 => 'circuitTotalAllocatedPhaseConductorCurrentL2', // Total current allocated to L2 by all chargers on the circuit. Sent in by master only.
            72 => 'circuitTotalAllocatedPhaseConductorCurrentL3', // Total current allocated to L3 by all chargers on the circuit. Sent in by master only.
            73 => 'circuitTotalPhaseConductorCurrentL1', // Total current in L1 (sum of all chargers on the circuit). Sent in by master only.
            74 => 'circuitTotalPhaseConductorCurrentL2', // Total current in L2 (sum of all chargers on the circuit). Sent in by master only.
            75 => 'circuitTotalPhaseConductorCurrentL3', // Total current in L3 (sum of all chargers on the circuit). Sent in by master only.
            96 => 'reasonForNoCurrent', // Enum describing why a charger with a car connected is not offering current to the car.
            50 => 'offlineMaxCircuitCurrentP1', // Maximum circuit current P1 when offline.
            51 => 'offlineMaxCircuitCurrentP2', // Maximum circuit current P2 when offline.
            52 => 'offlineMaxCircuitCurrentP3', // Maximum circuit current P3 when offline.
            119 => 'errorCode', // Error code according to error code table.
            230 => 'eqAvailableCurrentP1', // Available current for charging on P1 according to Equalizer.
            231 => 'eqAvailableCurrentP2', // Available current for charging on P2 according to Equalizer.
            232 => 'eqAvailableCurrentP3', // Available current for charging on P3 according to Equalizer.
            115 => 'deratedCurrent', // Available current after derating.
            116 => 'deratingActive', // Available current is limited by the charger due to high temperature.
            250 => 'connectedToCloud', // Device is connected to AWS.
        ];

        // Which observation IDs to request is configurable via the easee_config.ini
        // key "observation_ids":
        //   unset / empty  -> the minimal default set below (low overhead, unchanged
        //                      behaviour vs. the previous release)
        //   "all"          -> every mapped ID (full parity with the old /state response)
        //   "31,109,120"   -> an explicit comma-separated list
        $defaultObsIds = [31, 103, 109, 120, 121, 122, 124, 250];
        $cfgObsIds = isset($config['observation_ids']) ? trim($config['observation_ids']) : '';
        if ($cfgObsIds === '') {
            $requestedIds = $defaultObsIds;
        } elseif (strtolower($cfgObsIds) === 'all') {
            $requestedIds = array_keys($idToFieldMap);
        } else {
            $parts = preg_split('/\s*,\s*/', $cfgObsIds, -1, PREG_SPLIT_NO_EMPTY);
            $requestedIds = array_values(array_unique(array_filter(array_map('intval', $parts), function ($id) use ($idToFieldMap) {
                return $id > 0 && isset($idToFieldMap[$id]);
            })));
            if (empty($requestedIds)) { $requestedIds = $defaultObsIds; }
        }
        $url  = '/state/' . $chargerId . '/observations?ids=' . implode(',', $requestedIds);
        $apiResponse = get_req($url_base, $url, $token['accessToken']);

        // Pre-initialise every requested field so the response always contains the
        // requested set (avoids stale values when an observation is omitted);
        // present observations overwrite these defaults below.
        $data = [];
        foreach ($requestedIds as $reqId) { if (isset($idToFieldMap[$reqId])) { $data[$idToFieldMap[$reqId]] = 0; } }
        if (!isset($apiResponse['observations']) || !is_array($apiResponse['observations'])) {
            echo 'Somthing went wrong. Error: no \'observations\' in response for ' . $url . ' (API response: ' . print_r($apiResponse, true) . '). ';
            exit;
        }

        foreach ($apiResponse['observations'] as $obs) {
            if (!isset($obs['id']) || !array_key_exists('value', $obs)) {
                continue;
            }

            $id = (int)$obs['id'];

            // Onlymap known IDs, because we don't know how to handle unknown ones
            if (!isset($idToFieldMap[$id])) {
                continue;
            }

            $fieldName = $idToFieldMap[$id];
            $data[$fieldName] = $obs['value'];
            if ($obs['timestamp']) {
                if (!isset($data['latestPulse']) || $obs['timestamp'] > $data['latestPulse']) {
                    $data['latestPulse'] = $obs['timestamp'];
                }
            }
        }

        check_data($data, $url, $file_log_e);	
        // isOnline is kept for compatibility and mirrors connectedToCloud (observation id 250).
        // Note: the old /state fields 'voltage', 'wiFiAPEnabled', 'fatalErrorCode' and 'errors' have no
        // Observations equivalent and are intentionally NOT fabricated here.
        if (isset($data['connectedToCloud'])) { $data['isOnline'] = $data['connectedToCloud']; }
        $data[ 'sentAtTimeLox' ]= epoch2lox();
        $data[ 'sentAtTimeISO' ]= currtime();
        if (array_key_exists('status',$data)) {
		    echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		    exit;
		}		
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }
		if ($data[chargerOpMode] == 3){	
            $url  = '/api/chargers/' . $chargerId . '/commands/poll_all';
            $data = get_req($url_base, $url, $token['accessToken']);
		}
        break;	
    case "circuits":
		$url  = '/api/chargers/' . $chargerId . '/site';
		$data_tmp = get_req($url_base, $url, $token['accessToken']);
		$cid = $data_tmp['circuits'][0]['id'];
		$sid = $data_tmp['circuits'][0]['siteId'];
        $url  = '/api/sites/'.$sid.'/circuits/'.$cid.'/settings';
        $data = get_req($url_base, $url, $token['accessToken']);
		check_data($data, $url, $file_log_e);	
		if (array_key_exists('status',$data)) {
		echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
		exit;
		}		
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }
		if ($data[chargerOpMode] == 3){	
		$url  = '/api/chargers/' . $chargerId . '/commands/poll_all';
        $data = get_req($url_base, $url, $token['accessToken']);
		}
        break;		
    case "latest":
        $url  = '/api/chargers/' . $chargerId . '/sessions/latest';
        $data = get_req($url_base, $url, $token['accessToken']);
		if (array_key_exists('status',$data) && $data['status'] == 404) {
			$data = array(	'chargerId' => $chargerId,
							'sessionEnergy' => '0',
							'sessionStart' => '0',
							'sessionEnd' => '0',
							'sessionId' => '0');
		} else {
			check_data($data, $url, $file_log_e);
			if (array_key_exists('status',$data)) {
				echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
				exit;
			}			
		}
		foreach ($data as $k => $v)
		{
		$data['latest_'.$k] = $v;
		unset($data[$k]);
		}	
        $data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
            print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }		
        break;
    case "ongoing":
        $url  = '/api/chargers/' . $chargerId . '/sessions/ongoing';
        $data = get_req($url_base, $url, $token['accessToken']);
		if (array_key_exists('status',$data) && $data['status'] == 404) {
			$data = array(	'chargerId' => $chargerId,
							'sessionEnergy' => '0',
							'sessionStart' => '0',
							'sessionEnd' => '0',
							'sessionId' => '0');
		} else {
			check_data($data, $url, $file_log_e);
			if (array_key_exists('status',$data)) {
				echo 'Somthing went wrong. Error: '.$data['status'].' ('.$data['title'].')';
				exit;
			}
		}	
		foreach ($data as $k => $v)
		{
		$data['ongoing_'.$k] = $v;
		unset($data[$k]);
		}
		$data=change_booleans_to_numbers($data);
		if ($config['send_html'] == 1) {
			print_r($data);
        }
        if ($config['send_udp'] == 1) {
            send_udp($chargerId, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($chargerId, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($chargerId, $data);
        }		
        break;
		
    //POST (Set new settings)
    case "start_charging":
        $url  = '/api/chargers/' . $chargerId . '/commands/start_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
		check_data($data, $url, $file_log_e);	        
        break;
    case "stop_charging":
        $url  = '/api/chargers/' . $chargerId . '/commands/stop_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "pause_charging":
        $url  = '/api/chargers/' . $chargerId . '/commands/pause_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "resume_charging":
        $url  = '/api/chargers/' . $chargerId . '/commands/resume_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "post_settings":
        $url      = '/api/chargers/' . $chargerId . '/settings';
        $postdata = array(
            $type => $value
        );
		print_r($postdata);
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
		print_r($data);
        break;
		
    case "post_dynamicCurrent":
        
		$url  = '/api/chargers/' . $chargerId . '/site';
		$data_tmp = get_req($url_base, $url, $token['accessToken']);
		$cid = $data_tmp['circuits'][0]['id'];
		$sid = $data_tmp['circuits'][0]['siteId'];
		$value = explode ( ',', $value);
		$postdata = array(
			"phase1" => $value[0],
			"phase2" => $value[1],
			"phase3" => $value[2],
			"timeToLive" => 14400
        );
		$url      = '/api/sites/'.$sid.'/circuits/'.$cid.'/dynamicCurrent';
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
		print_r($data);
		break;		

    case "post_dynamicPower":
		$url  = '/api/chargers/' . $chargerId . '/site';
		$data_tmp = get_req($url_base, $url, $token['accessToken']);
		$cid = $data_tmp['circuits'][0]['id'];
		$sid = $data_tmp['circuits'][0]['siteId'];
        // input value is power in kW
        if ($value < 4.14) {
            // charging with one phase only - limited to 16A
            $phase1 = min($value/(230/1000), 16);
            $phase2 = 0;
            $phase3 = 0;
        } else {
            // three phases - minimum is 6A (=4.14 kW)
            $phase1 = $value/(230*3/1000);
            $phase2 = $phase1;
            $phase3 = $phase1;
        }
	$postdata = array(
		"phase1" => $phase1,
		"phase2" => $phase2,
		"phase3" => $phase3,
		"timeToLive" => 14400
        );
	$url      = '/api/sites/'.$sid.'/circuits/'.$cid.'/dynamicCurrent';
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
	print_r($data);
	break;	
		
    case "lock_state":
        $url      = '/api/chargers/' . $chargerId . '/commands/lock_state';
        $postdata = array(
            'state' => $value
        );
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "post_lock_state":
        $url      = '/api/chargers/' . $chargerId . '/commands/lock_state';
        $postdata = array(
            'state' => $value
        );
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;		
    case "override_schedule":
        $url  = '/api/chargers/' . $chargerId . '/commands/override_schedule';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "reboot":
        $url  = '/api/chargers/' . $chargerId . '/commands/reboot';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "force_reboot":
        $url  = '/api/chargers/' . $chargerId . '/commands/force_reboot';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "update_firmware":
        $url  = '/api/chargers/' . $chargerId . '/commands/update_firmware';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	;
        break;		
    case "poll_lifetimeenergy":
        $url  = '/api/chargers/' . $chargerId . '/commands/poll_lifetimeenergy';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	;
		break;	
    case "poll_all":
        $url  = '/api/chargers/' . $chargerId . '/commands/poll_all';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);
		break;

	default:
        echo "!! do is missing !!";
}
exit();
?>
