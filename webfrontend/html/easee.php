<?php

require_once "loxberry_system.php";
include 'easee_functions.php';
error_reporting(0);
set_time_limit(15);

//CONFIG
$url_base    = 'https://api.easee.cloud';
$file_token  = $lbpconfigdir.'/easee_token.ini';
$file_config = $lbpconfigdir.'/easee_config.ini';
$file_log_e	 = $lbplogdir.'/easee-error.log';
$file_log_i	 = $lbplogdir.'/easee-info.log';
//---------------------------------------------------------------------------------------------------
$do          = ($_GET["do"]);
$id          = ($_GET["id"]);
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

if (in_array("$do", $do_id)) { if (empty($id)) { echo '!! id is missing !!'; exit; }}
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }		
        break;
    case "config":
        $url  = '/api/chargers/' . $id . '/config';
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
		if ($config['send_mqtt'] == 1) {
           $res_mqtt=send_mqtt($id, $data);
        }
        if ($config['send_json'] == 1) {
            $res_json=send_json($id, $data);
			echo $res_json;
        }
	
        break;
    case "equalizer":
            $url  = '/api/equalizers/' . $id . '/state';
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
		if ($config['send_mqtt'] == 1) {
           $res_mqtt=send_mqtt($id, $data);
        }
        if ($config['send_json'] == 1) {
            $res_json=send_json($id, $data);
			echo $res_json;
        }  
        break;
    case "site":
        $url  = '/api/chargers/' . $id . '/site';
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }		
        break;	
    case "state":
        $url  = '/api/chargers/' . $id . '/state';
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }
		if ($data[chargerOpMode] == 3){	
		$url  = '/api/chargers/' . $id . '/commands/poll_all';
        $data = get_req($url_base, $url, $token['accessToken']);
		}
        break;	
    case "circuits":
		$url  = '/api/chargers/' . $id . '/site';
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }
		if ($data[chargerOpMode] == 3){	
		$url  = '/api/chargers/' . $id . '/commands/poll_all';
        $data = get_req($url_base, $url, $token['accessToken']);
		}
        break;		
    case "latest":
        $url  = '/api/chargers/' . $id . '/sessions/latest';
        $data = get_req($url_base, $url, $token['accessToken']);
		if (array_key_exists('status',$data) && $data['status'] == 404) {
			$data = array(	'chargerId' => $id,
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }		
        break;
    case "ongoing":
        $url  = '/api/chargers/' . $id . '/sessions/ongoing';
        $data = get_req($url_base, $url, $token['accessToken']);
		if (array_key_exists('status',$data) && $data['status'] == 404) {
			$data = array(	'chargerId' => $id,
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
            send_udp($id, $data, $config['miniserver']['ip'], $config['miniserver']['port']);
        }
        if ($config['send_json'] == 1) {
            send_json($id, $data);
        }
		if ($config['send_mqtt'] == 1) {
            send_mqtt($id, $data);
        }		
        break;
		
    //POST (Set new settings)
    case "start_charging":
        $url  = '/api/chargers/' . $id . '/commands/start_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
		check_data($data, $url, $file_log_e);	        
        break;
    case "stop_charging":
        $url  = '/api/chargers/' . $id . '/commands/stop_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "pause_charging":
        $url  = '/api/chargers/' . $id . '/commands/pause_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "resume_charging":
        $url  = '/api/chargers/' . $id . '/commands/resume_charging';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "post_settings":
        $url      = '/api/chargers/' . $id . '/settings';
        $postdata = array(
            $type => $value
        );
		print_r($postdata);
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
		print_r($data);
        break;
		
    case "post_dynamicCurrent":
        
		$url  = '/api/chargers/' . $id . '/site';
		$data_tmp = get_req($url_base, $url, $token['accessToken']);
		$cid = $data_tmp['circuits'][0]['id'];
		$sid = $data_tmp['circuits'][0]['siteId'];
        $url  = '/api/sites/'.$sid.'/circuits/'.$cid.'/dynamicCurrent';
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


		
		
		
		
    case "lock_state":
        $url      = '/api/chargers/' . $id . '/commands/lock_state';
        $postdata = array(
            'state' => $value
        );
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "post_lock_state":
        $url      = '/api/chargers/' . $id . '/commands/lock_state';
        $postdata = array(
            'state' => $value
        );
        $data     = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;		
    case "override_schedule":
        $url  = '/api/chargers/' . $id . '/commands/override_schedule';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "reboot":
        $url  = '/api/chargers/' . $id . '/commands/reboot';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "force_reboot":
        $url  = '/api/chargers/' . $id . '/commands/force_reboot';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	
        break;
    case "update_firmware":
        $url  = '/api/chargers/' . $id . '/commands/update_firmware';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	;
        break;		
    case "poll_lifetimeenergy":
        $url  = '/api/chargers/' . $id . '/commands/poll_lifetimeenergy';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);	;
		break;	
    case "poll_all":
        $url  = '/api/chargers/' . $id . '/commands/poll_all';
        $data = post_req($url_base, $url, $token['accessToken'], $postdata);
        check_data($data, $url, $file_log_e);
		break;

	default:
        echo "!! do is missing !!";
}
exit();
?>
