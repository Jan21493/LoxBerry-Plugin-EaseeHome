<?php
//GET TOKEN
function get_token($url_base, $url_tocken, $file_token, $username, $password)
{
    $data     = array(
        "userName" => "$username",
        "password" => "$password"
    );
    $postdata = json_encode($data);
    $ch       = curl_init($url_base . $url_tocken);
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
//GET_REFRESH_TOKEN
function get_refresh_token($url_base, $url_tocken, $file_token, $token, $refresh_token)
{
    $data     = array(
		"accessToken" => "$token",
		"refreshToken" => "$refresh_token"
    );
    $postdata = json_encode($data);
	echo $postdata;
    $ch       = curl_init($url_base . $url_tocken);
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
//GET REQUESTS
function get_req($url_base, $url_req, $token)
{
    $ch = curl_init($url_base . $url_req);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    $data = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return json_decode($data, true);
}
//POST REQUESTS
function post_req($url_base, $url_req, $token, $data)
{
    $data_string = json_encode($data);
    $ch          = curl_init($url_base . $url_req);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ));
    $data = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return json_decode($data, true);
}
//SEND JSON
function send_json($id, $message)
{
    foreach ($message as $k => $v) {
        $message[$id . '_' . $k] = $v;
        unset($message[$k]);
    }
    foreach ($message as $i => $value) {
        if (empty($value))
            $message[$i] = 0;
    }
    //$message = change_booleans_to_numbers($message);
    $message = json_encode($message);
    echo $message;
}
//SEND UDP
function send_udp($id, $message, $ms_ip, $ms_port)
{
    foreach ($message as $k => $v) {
        $message[$id . '_' . $k] = $v;
        unset($message[$k]);
    }
    foreach ($message as $i => $value) {
        if (empty($value))
            $message[$i] = 0;
    }
    $message = implode(' ', array_map(function($v, $k)
    {
        return sprintf('%s=%s', $k, $v);
    }, $message, array_keys($message)));
    if ($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
        socket_sendto($socket, $message, strlen($message), 0, $ms_ip, $ms_port);
    } else {
        print("can't create socket\n");
    }
}
//SEND MQTT
function send_mqtt($id, $message)
{
require_once "loxberry_io.php";
require_once "phpMQTT.php";

foreach ($message as $i => $value) {
if (empty($value))
$message[$i] = 0;
}
//$message = change_booleans_to_numbers($message);

$creds = mqtt_connectiondetails();
$client_id = uniqid(gethostname()."_client");
$mqtt = new Bluerhinos\phpMQTT($creds['brokerhost'], $creds['brokerport'], $client_id);
if( $mqtt->connect(true, NULL, $creds['brokeruser'], $creds['brokerpass'] ) ) {
foreach($message as $x => $val) {
$mqtt->publish("easee/".$id."/".$x, $val, 0, 1);
}
$mqtt->close();
} else {
echo "MQTT connection failed";
}
} 

//BOOLEANS 2 NUMBERS
function change_booleans_to_numbers(Array $data)
{
    function converter(&$value, $key){
        if (is_bool($value)) {
            $value = ($value ? 1 : 0);
        }
    }
    array_walk_recursive($data, 'converter');
    return $data;
}

//CHECK Data
function check_data($data, $url, $file_log) {
		if (array_key_exists('status',$data)) {
		echo 'Something went wrong. Error: '.$data[status].' ('.$data[title].')' ;
		$time_e=date("Y-m-d  H:i:s");
		$message_s ='ERROR: '.$time_e.' - '.$url.' - '.json_encode($data);
		$myfile = file_put_contents($file_log, $message_s.PHP_EOL , FILE_APPEND | LOCK_EX);
		exit;
		}	
}

//LOG
function log_e ($text, $url, $file_log) {
		$time_e=date("Y-m-d  H:i:s");
		$message_s ='ERROR: '.$time_e.' - '.$url.' - '.json_encode($text);
		$myfile = file_put_contents($file_log, $message_s.PHP_EOL , FILE_APPEND | LOCK_EX);
}

function log_i ($text, $url, $file_log) {
		$time_e=date("Y-m-d  H:i:s");
		$message_s ='INFO: '.$time_e.' - '.$url.' - '.json_encode($text);
		$myfile = file_put_contents($file_log, $message_s.PHP_EOL , FILE_APPEND | LOCK_EX);
}
