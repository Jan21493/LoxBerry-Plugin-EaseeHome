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
$navbar[2]['active'] = True;

LBWeb::lbheader($template_title, $helplink, $helptemplate);

//LOGFILES
echo '<p class="wide">'. $L['LOGFILES.HEAD']. '</p>';

if ($handle = opendir($lbplogdir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
          if (is_dir($lbplogdir . '/' . $entry)) {
            continue;
          }
          $lowerEntry = strtolower($entry);
          if (strpos($lowerEntry, 'token.ini') !== false || strpos($lowerEntry, 'state.log') !== false || strpos($lowerEntry, 'lock-date') !== false) {
            continue;
          }
          echo '<div class="ui-corner-all ui-shadow">';
          echo '<a id="btnlogs" data-role="button" href="/admin/system/tools/logfile.cgi?logfile=plugins/easee_home/'. $entry. '&header=html&format=template" target="_blank" data-inline="true" data-mini="true">'.$entry. '</a>';
          echo '</div>';
        }
    }
    closedir($handle);
}

LBWeb::lbfooter();
?>
