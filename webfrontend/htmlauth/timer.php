<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";
require_once "Config/Lite.php";

$L = LBWeb::readlanguage("language.ini");

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];
$helplink = "https://www.loxwiki.eu/display/LOXBERRY/Easee+Home+Wallbox";
$helptemplate = "pluginhelp.html";

$navbar[1]['Name'] = $L['NAVBAR.FIRST'];
$navbar[1]['URL'] = 'index.php';

$navbar[2]['Name'] = $L['NAVBAR.SECOND'];
$navbar[2]['URL'] = 'log.php';
$navbar[3]['Name'] = $L['NAVBAR.THIRD'];
$navbar[3]['URL'] = 'queries.php';


// NAVBAR
$navbar[1]['active'] = True;

LBWeb::lbheader($template_title, $helplink, $helptemplate);
$tockentime=15;
echo'
<script>
function countDown(secs,elem) {
var element = document.getElementById(elem);
element.innerHTML = "'.$L['TIMER.ACTIVATIONTIME'].': <b>"+secs+" </b>'.$L['TIMER.SECONDS'].'";
if(secs < 1) {
clearTimeout(timer);
element.innerHTML += \'<meta http-equiv="refresh" content="0; URL=index.php">\';
}

secs--;
var timer = setTimeout(\'countDown(\'+secs+\',"\'+elem+\'")\',1000);
}
</script>
<div id="status" style="text-align: center; font-size:25px;"></div>
<script>countDown('.$tockentime.',"status");</script> 
';
LBWeb::lbfooter();
?>
