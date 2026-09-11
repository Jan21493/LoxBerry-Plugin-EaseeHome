<?php

require_once "loxberry_system.php";
include 'easee_functions.php';
error_reporting(0);
set_time_limit(15);

// Configuration.
$url_base    = 'https://api.easee.com';
$file_token  = $lbplogdir.'/easee_token.ini';
$file_config = $lbpconfigdir.'/easee_config.ini';
$file_log_e	 = $lbplogdir.'/easee-error.log';
$file_log_i	 = $lbplogdir.'/easee-info.log';
//---------------------------------------------------------------------------------------------------
$do          = ($_GET["do"]);
$chargerId   = ($_GET["id"]);
$type        = ($_GET["type"]);
$value       = ($_GET["value"]);
//---------------------------------------------------------------------------------------------------
// Read config and token files.
$configRaw = @file_get_contents($file_config);
$tokenRaw = @file_get_contents($file_token);
$config = json_decode($configRaw, true);
$token = json_decode($tokenRaw, true);
if (!is_array($config)) {
    $config = array();
}
if (!is_array($token)) {
    $token = array();
}
$log_level = easee_normalize_log_level(isset($config['log_level']) ? $config['log_level'] : 'info');
$max_lifetime = 0;
easee_log('debug', 'Received request', array(
    'do' => $do,
    'chargerId' => $chargerId,
    'type' => $type,
    'value' => $value
), $file_log_i, $file_log_e, $log_level);

// Start request handling.
if (!empty($do)) {
    // Check token.
    $url_token = '/api/accounts/login';
	$url_refresh_tocken = '/api/accounts/refresh_token';
    if (array_key_exists('status',$token)) {
        $token_time_diff = 86001;
    } elseif (empty($token)){
		$token_time_diff = 86001;
	} else {
        $time_now        = time();
        $time_file       = filemtime($file_token);
        $token_time_diff = $time_now - $time_file;
        // Calculate the maximum lifetime of the token considering the safety buffer
        $safety_buffer = 300; // 5 minutes
        $max_lifetime = intval($token['expiresIn']) - $safety_buffer;
    }
    // Refresh token if still inside refresh window.
	if ($token_time_diff > $max_lifetime && $token_time_diff < 86000) {
		get_refresh_token($url_base, $url_refresh_tocken, $file_token, $token['accessToken'], $token['refreshToken']);
        $token = json_decode(file_get_contents($file_token), true);
        if (!is_array($token)) {
            $token = array();
        }
		if (array_key_exists('status',$token)) {
            check_data($token, $url_token, $file_log_e, $file_log_i, $log_level);
			exit;			
        } else {
            easee_log('info', 'Refresh token created', array(
                'url' => $url_token
            ), $file_log_i, $file_log_e, $log_level);
		}
	}
    // Request a new token if refresh window is over.
    if ($token_time_diff >= 86000) {
        get_token(
            $url_base,
            $url_token,
            $file_token,
            isset($config['user']['username']) ? $config['user']['username'] : '',
            isset($config['user']['password']) ? $config['user']['password'] : ''
        );
        $token = json_decode(file_get_contents($file_token), true);
        if (!is_array($token)) {
            $token = array();
        }
        if (array_key_exists('status',$token)) {
            check_data($token, $url_token, $file_log_e, $file_log_i, $log_level);
			exit;			
        } else {
            easee_log('info', 'New token created', array(
                'url' => $url_token
            ), $file_log_i, $file_log_e, $log_level);
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
		check_data($data, $url_get_chargers, $file_log_e, $file_log_i, $log_level);
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
		check_data($data, $url_get_chargers, $file_log_e, $file_log_i, $log_level);
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
        // Build observation ID mappings from shared definitions.
        $observationDefinitions = easee_get_observation_definitions();
        $idToFieldMap = array();
        foreach ($observationDefinitions as $observationId => $definition) {
            $idToFieldMap[$observationId] = $definition['parameter'];
        }

        // Parse configured observation IDs.
        $defaultObsIds = easee_get_default_observation_ids();
        $requestedIds = easee_get_requested_observation_ids(
            isset($config['observation_ids']) ? $config['observation_ids'] : '',
            $observationDefinitions,
            $defaultObsIds
        );

        easee_log('debug', 'Requesting observations', array(
            'chargerId' => $chargerId,
            'requestedObservationIds' => $requestedIds,
            'configuredObservationIds' => isset($config['observation_ids']) ? $config['observation_ids'] : ''
        ), $file_log_i, $file_log_e, $log_level);

        $url  = '/state/' . $chargerId . '/observations?ids=' . implode(',', $requestedIds);
        $apiResponse = get_req($url_base, $url, $token['accessToken']);

        // Pre-initialize all requested fields with defaults.
        $data = [];
        foreach ($requestedIds as $reqId) { if (isset($idToFieldMap[$reqId])) { $data[$idToFieldMap[$reqId]] = 0; } }
        if (!isset($apiResponse['observations']) || !is_array($apiResponse['observations'])) {
            easee_log('error', 'No observations in response', array(
                'chargerId' => $chargerId,
                'url' => $url,
                'apiResponse' => $apiResponse
            ), $file_log_i, $file_log_e, $log_level);
            echo 'Somthing went wrong. Error: no \'observations\' in response for ' . $url . ' (API response: ' . print_r($apiResponse, true) . '). ';
            exit;
        }

        foreach ($apiResponse['observations'] as $obs) {
            if (!isset($obs['id']) || !array_key_exists('value', $obs)) {
                continue;
            }

            $id = (int)$obs['id'];

            // Keep only known IDs.
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

        check_data($data, $url, $file_log_e, $file_log_i, $log_level);

        // Keep isOnline for compatibility and mirror connectedToCloud.
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
        easee_update_charger_status($lbplogdir, $chargerId, array(
            'updatedAtIso' => currtime(),
            'lastState' => array(
                'totalPower' => isset($data['totalPower']) ? $data['totalPower'] : null,
                'chargerOpMode' => isset($data['chargerOpMode']) ? $data['chargerOpMode'] : null,
                'outputPhase' => isset($data['outputPhase']) ? $data['outputPhase'] : null,
                'sessionEnergy' => isset($data['sessionEnergy']) ? $data['sessionEnergy'] : null,
                'connectedToCloud' => isset($data['connectedToCloud']) ? $data['connectedToCloud'] : null,
                'latestPulse' => isset($data['latestPulse']) ? $data['latestPulse'] : null
            )
        ));

        if (isset($data['chargerOpMode']) && intval($data['chargerOpMode']) === 3) {
            $url = '/api/chargers/' . $chargerId . '/commands/poll_all';
            $pollAllResponse = get_req($url_base, $url, $token['accessToken']);
            easee_log('debug', 'Triggered poll_all after active charger operation mode', array(
                'chargerId' => $chargerId,
                'chargerOpMode' => $data['chargerOpMode'],
                'response' => $pollAllResponse
            ), $file_log_i, $file_log_e, $log_level);
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
        // Input value is target power in kW.
        $requested_power_kw = floatval(str_replace(',', '.', $value));
        $value = $requested_power_kw;
		$url  = '/api/chargers/' . $chargerId . '/site';
		$data_tmp = get_req($url_base, $url, $token['accessToken']);
		$cid = $data_tmp['circuits'][0]['id'];
		$sid = $data_tmp['circuits'][0]['siteId'];

        // Read hysteresis parameters (default: 0 = disabled).
        $hys_1to3 = isset($_GET['hys1to3']) ? intval($_GET['hys1to3']) : 0; 
        $hys_3to1 = isset($_GET['hys3to1']) ? intval($_GET['hys3to1']) : 0;
        
        $now = time();
        // Keep the latest phase and switch timestamp in log directory.
        $state_file = $lbplogdir . "/easee_" . $chargerId . "_state.log";

        // Use defaults if state file does not exist or cannot be parsed.
        $state_data = array("last_phase" => 1, "last_switch" => 0);
        
        if (file_exists($state_file)) {
            $file_content = file_get_contents($state_file);
            if ($file_content !== false) {
                $decoded = json_decode($file_content, true);
                if (is_array($decoded)) {
                    $state_data = $decoded;
                }
            }
        }
        $last_phase = intval($state_data['last_phase']);
        $last_switch = intval($state_data['last_switch']);
        $seconds_since_switch = $now - $last_switch;

        // Calculate threshold (3 phases * 6A * 230V = 4.14 kW).
        $target_phase = ($value < 4.14) ? 1 : 3;

        // Hysteresis check is active only if parameter is > 0.
        $write_state = false;
        $phase_switch_reason = 'none';

        if ($last_phase == 1 && $target_phase == 3) {
            if ($hys_1to3 > 0 && $seconds_since_switch < $hys_1to3) {
                // Hysteresis lock active, stay on 1 phase.
                $target_phase = 1;
                // Limit 1 phase to 16A * 230V = 3.68 kW.
                if ($value > 3.68) $value = 3.68; 
                $phase_switch_reason = 'hysteresis_hold_1to3';
            } else {
                // Switch to 3 phases and update state.
                $state_data['last_phase'] = 3;
                $state_data['last_switch'] = $now;
                $write_state = true;
                $phase_switch_reason = 'switch_to_3_phase';
            }
        } elseif ($last_phase == 3 && $target_phase == 1) {
            if ($hys_3to1 > 0 && $seconds_since_switch < $hys_3to1) {
                // Hysteresis lock active, stay on 3 phases.
                $target_phase = 3;
                if ($value < 4.14) $value = 4.14; 
                $phase_switch_reason = 'hysteresis_hold_3to1';
            } else {
                // Switch to 1 phase and update state.
                $state_data['last_phase'] = 1;
                $state_data['last_switch'] = $now;
                $write_state = true;
                $phase_switch_reason = 'switch_to_1_phase';
            }
        }
        // Persist state if it changed.
        if ($write_state) {
            file_put_contents($state_file, json_encode($state_data));
        }

        // Calculate current for each phase based on target phase and power.
        if ($target_phase == 1) {
            // Single phase is capped at 16A (=3.68 kW) when required.
            $ampere = min(round(($value * 1000) / 230, 2), 16);
            $postdata = array("phase1" => $ampere, "phase2" => 0, "phase3" => 0, "timeToLive" => 14400);
        } else {
            $ampere = round(($value * 1000) / (230 * 3), 2);
            $postdata = array("phase1" => $ampere, "phase2" => $ampere, "phase3" => $ampere, "timeToLive" => 14400);
        }

        easee_log('info', 'Dynamic charging power calculation', array(
            'chargerId' => $chargerId,
            'timestamp' => currtime(),
            'requestedPowerKw' => round($requested_power_kw, 3),
            'effectivePowerKw' => round(floatval($value), 3),
            'phaseMode' => ($target_phase == 1 ? 'single-phase' : 'three-phase'),
            'phaseCount' => $target_phase,
            'amperePerActivePhase' => $ampere,
            'hysteresis' => array(
                'hys1to3Seconds' => $hys_1to3,
                'hys3to1Seconds' => $hys_3to1,
                'secondsSinceLastSwitch' => $seconds_since_switch
            ),
            'switchReason' => $phase_switch_reason,
            'config' => array(
                'logLevel' => $log_level,
                'sendUdp' => isset($config['send_udp']) ? $config['send_udp'] : '0',
                'sendJson' => isset($config['send_json']) ? $config['send_json'] : '0',
                'sendMqtt' => isset($config['send_mqtt']) ? $config['send_mqtt'] : '0',
                'observationIds' => isset($config['observation_ids']) ? $config['observation_ids'] : ''
            )
        ), $file_log_i, $file_log_e, $log_level);

        // Send calculated current values to the charger API.
        $url     = '/api/sites/'.$sid.'/circuits/'.$cid.'/dynamicCurrent';
        $data    = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e, $file_log_i, $log_level);
        easee_update_charger_status($lbplogdir, $chargerId, array(
            'updatedAtIso' => currtime(),
            'lastDynamicPowerChange' => array(
                'timestamp' => currtime(),
                'requestedPowerKw' => round($requested_power_kw, 3),
                'effectivePowerKw' => round(floatval($value), 3),
                'phaseMode' => ($target_phase == 1 ? 'single-phase' : 'three-phase'),
                'phaseCount' => $target_phase,
                'amperePerActivePhase' => $ampere,
                'hys1to3Seconds' => $hys_1to3,
                'hys3to1Seconds' => $hys_3to1,
                'secondsSinceLastSwitch' => $seconds_since_switch,
                'switchReason' => $phase_switch_reason
            )
        ));
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
