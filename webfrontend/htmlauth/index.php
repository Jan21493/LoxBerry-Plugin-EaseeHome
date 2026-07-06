<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
include $lbphtmldir.'/easee_functions.php';

$L = LBWeb::readlanguage("language.ini");
$file_token  = $lbpconfigdir.'/easee_token.ini';
$file_config = $lbpconfigdir.'/easee_config.ini';
$file_log_e	 = $lbplogdir.'/easee-error.log';
$file_log_i	 = $lbplogdir.'/easee-info.log';
$url_base    = 'https://api.easee.cloud';
$url_tocken = '/api/accounts/login';


if ($_POST) {
	if ($_POST['return_json'] == "on") { $return_json = "1"; } else { $return_json = "0"; }
	if ($_POST['return_mqtt'] == "on") { $return_mqtt = "1"; } else { $return_mqtt = "0"; }
	if ($_POST['return_udp'] == "on") { $return_udp = "1"; } else { $return_udp = "0"; }

unlink ($file_token);
	$data='{
		"user": {
			"username": "'.$_POST['username'].'",
			"password": "'.$_POST['password'].'"
		},
		"miniserver": {
			"ip": "'.$_POST['miniserver'].'",
			"port": "'.$_POST['udpport'].'"
		},
		"send_html": "0",
		"send_udp": "'.$return_udp.'",
		"send_json": "'.$return_json.'",
		"send_mqtt": "'.$return_mqtt.'"
	}';

	$handle = fopen ( $file_config, "w" ); 
	fwrite ( $handle, $data );
	fclose ( $handle );
	get_token($url_base, $url_tocken, $file_token, $_POST['username'], $_POST['password']);
	header("Location: timer.php");
	}

$config      = json_decode(file_get_contents($file_config), true);
$token       = json_decode(file_get_contents($file_token), true);
$token_str   = file_get_contents($file_token);

$url_get_chargers = '/api/chargers';
$data = get_req($url_base, $url_get_chargers, $token['accessToken']);

$ii=1;
foreach ($data as $datakey => $dataval) {
	$url  = '/api/chargers/' . $dataval["id"] . '/site';
	$data2 = get_req($url_base, $url, $token['accessToken']);
	${'sid_' . $ii}  = $data2['circuits'][0]['id'];
	${'cid_' . $ii}  = $data2['circuits'][0]['siteId'];
	$ii++;
}

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];

$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';
$navbar[2]['Name'] = $L['NAVBAR.SECOND'];
$navbar[2]['URL'] = 'log.php';

// NAVBAR
$navbar[1]['active'] = True;

LBWeb::lbheader($template_title, $helplink, $helptemplate);
echo '<img src="logo.png" alt="Easee Home">';

echo '<p>'. $L['MAIN.INTRO1']. '</p>';
echo '<br>';
echo '<form action="index.php" method="post">';
  //GATEWAYS
  echo '<p class="wide">'. $L['USER.HEAD']. '</p>';
  echo '<label for="port">'. $L['USER.USER'].'</label>';
  echo'<input data-inline="true" data-mini="true" name="username" id="username"  value="'. $config['user']['username']. '" type="text">';
  echo '<label for="port">'. $L['USER.PASS'].'</label>';
  echo'<input data-inline="true" data-mini="true" name="password" id="password"  value="'. $config['user']['password']. '" type="password">'; 
  if(strpos($token_str, 'accessToken') === false){
  echo '<a style="color:red;">'.$L['MAIN.TOKENERROR'].'</a><br><br><br>';
  log_e($token, $url_tocken, $file_log_e);
  } else {
	  if ($_POST) {
	  $text='New Token created.';
	  log_i($text, $url_tocken, $file_log_i);
	  }
	  echo '<a style="color:green;">'.$L['MAIN.TOKENOK'].'</a><br><br><br>';

	  echo '<p class="wide">'. $L['WALLBOX.HEAD']. '</p>';
	  $i=1;
	  foreach ($data as $datakey => $dataval) {
		echo '<b>'.$i.'</b> - '.$L['WALLBOX.NAME'].': <b>'.$dataval["name"] . ' / </b>'.$L['WALLBOX.ID'].': <b>'. $dataval["id"].'</b> (Site-ID: '.${'sid_' . $i}.' / Circuit-ID: '.${'cid_' . $i}.')<br>';
		$i++;
		}
	echo '<br><br>';
  }
  echo '<p class="wide">'. $L['MINISERVER.HEAD']. '</p>';
//MiniServers 
$ms = LBSystem::get_miniservers();
foreach ($ms as $element) { if ($element['IPAddress'] == $config['miniserver']['ip']) { $miniserver_name= $element['Name']; }}
if (!is_array($ms)) {
    echo $L['MINISERVER.NOMS'];
} else {
	echo '<label for="miniserver">'.$L['MINISERVER.CHOOSE'].'</label>';
	echo '<select name= "miniserver" id="miniserver">';
	echo '<option select value="'.$config['miniserver']['ip'].'">'.$miniserver_name.'</option>';
	echo '<option></option>';
	foreach ($ms as $miniserver) {
	echo '<option value="'.$miniserver['IPAddress'].'">'.$miniserver['Name'].'</option>';
	}	
	echo '</select>';
}
echo '<br><br><p class="wide">'. $L['RETURN.HEAD']. '</p>';
echo '<label for="return_mqtt">'.$L['RETURN.MQTT'].'</label>';
echo '<input type="checkbox" id="return_mqtt" name="return_mqtt"'; if ($config['send_mqtt'] > 0) { echo"checked"; }; echo '>';
echo '<label for="return_json">'.$L['RETURN.JSON'].'</label>';
echo '<input type="checkbox" id="return_json" name="return_json"'; if ($config['send_json'] > 0) { echo"checked"; }; echo '>';
echo '<label for="return_udp">'.$L['RETURN.UDP'].'</label>';
echo '<input type="checkbox" id="return_udp" name="return_udp"'; if ($config['send_udp'] > 0) { echo"checked"; }; echo '>';
echo '<label for="port">'. $L['RETURN.UDP_PORT'].'</label>';
echo '<input data-inline="true"  data-mini="true" name="udpport" id="udpport"  value='. $config['miniserver']['port']. ' type="text">';
echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" name="save_new" data-icon="check" value='.$L['MAIN.SAVE'].'> </center></p>';
echo '</form>';
LBWeb::lbfooter();
?>
