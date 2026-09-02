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
        // Map observation ID => old field name
        $idToFieldMap = [
            31  => 'isEnabled',           // whether the charger is enabled
            103 => 'cableLocked',         // lock status
            109 => 'chargerOpMode',       // operational mode of the charger
            120 => 'totalPower',          // Total power (kW)
            121 => 'sessionEnergy',       // session accumulated energy (kWh)
            122 => 'energyPerHour',       // accumulated energy per hour
            124 => 'lifetimeEnergy',      // accumulated energy in the lifetime of the charger (kWh)
            250 => 'isOnline'             // indicates if the charger is 'connected to cloud'
        ];

        $url  = '/state/' . $chargerId . '/observations?ids=31,103,109,120,121,122,124,250';
        $apiResponse = get_req($url_base, $url, $token['accessToken']);

        $data = [];
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
