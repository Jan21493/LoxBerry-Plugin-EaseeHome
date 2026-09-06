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
            31 => 'isEnabled', 102 => 'smartCharging', 103 => 'cableLocked', 109 => 'chargerOpMode',
            120 => 'totalPower', 121 => 'sessionEnergy', 122 => 'energyPerHour', 124 => 'lifetimeEnergy',
            132 => 'wiFiRSSI', 130 => 'cellRSSI', 136 => 'localRSSI', 110 => 'outputPhase',
            111 => 'dynamicCircuitCurrentP1', 112 => 'dynamicCircuitCurrentP2', 113 => 'dynamicCircuitCurrentP3',
            80 => 'chargerFirmware', 131 => 'chargerRAT', 30 => 'lockCablePermanently',
            182 => 'inCurrentT2', 183 => 'inCurrentT3', 184 => 'inCurrentT4', 185 => 'inCurrentT5',
            114 => 'outputCurrent',
            190 => 'inVoltageT1T2', 191 => 'inVoltageT1T3', 192 => 'inVoltageT1T4', 193 => 'inVoltageT1T5',
            194 => 'inVoltageT2T3', 195 => 'inVoltageT2T4', 196 => 'inVoltageT2T5',
            197 => 'inVoltageT3T4', 198 => 'inVoltageT3T5', 199 => 'inVoltageT4T5',
            46 => 'ledMode', 104 => 'cableRating', 48 => 'dynamicChargerCurrent', 47 => 'maxChargerCurrent',
            70 => 'circuitTotalAllocatedPhaseConductorCurrentL1', 71 => 'circuitTotalAllocatedPhaseConductorCurrentL2',
            72 => 'circuitTotalAllocatedPhaseConductorCurrentL3',
            73 => 'circuitTotalPhaseConductorCurrentL1', 74 => 'circuitTotalPhaseConductorCurrentL2',
            75 => 'circuitTotalPhaseConductorCurrentL3', 96 => 'reasonForNoCurrent',
            50 => 'offlineMaxCircuitCurrentP1', 51 => 'offlineMaxCircuitCurrentP2', 52 => 'offlineMaxCircuitCurrentP3',
            119 => 'errorCode', 230 => 'eqAvailableCurrentP1', 231 => 'eqAvailableCurrentP2', 232 => 'eqAvailableCurrentP3',
            115 => 'deratedCurrent', 116 => 'deratingActive', 250 => 'isOnline'
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
            $requestedIds = array_values(array_filter(array_map('intval', explode(',', $cfgObsIds))));
            if (empty($requestedIds)) { $requestedIds = $defaultObsIds; }
        }
        $url  = '/state/' . $chargerId . '/observations?ids=' . implode(',', $requestedIds);
        $apiResponse = get_req($url_base, $url, $token['accessToken']);

        // Pre-initialise every mapped field so the response always contains the
        // full set (parity with the old /state, which always returned every field);
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
        // connectedToCloud has no own observation; it equals the cloud-connection state (id 250 -> isOnline).
        // Note: the old /state fields 'voltage', 'wiFiAPEnabled', 'fatalErrorCode' and 'errors' have no
        // Observations equivalent and are intentionally NOT fabricated here.
        if (isset($data['isOnline'])) { $data['connectedToCloud'] = $data['isOnline']; }
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
