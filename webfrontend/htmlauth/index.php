<?php
require_once "loxberry_system.php";
require_once "loxberry_web.php";

$L = LBWeb::readlanguage("language.ini");
$file_config = $lbpconfigdir.'/easee_config.ini';
$defaultObsIds = [31, 103, 109, 120, 121, 122, 124, 250];
$observationOptions = [
	31 => 'isEnabled',
	102 => 'smartCharging',
	103 => 'cableLocked',
	109 => 'chargerOpMode',
	120 => 'totalPower',
	121 => 'sessionEnergy',
	122 => 'energyPerHour',
	124 => 'lifetimeEnergy',
	132 => 'wiFiRSSI',
	130 => 'cellRSSI',
	136 => 'localRSSI',
	110 => 'outputPhase',
	111 => 'dynamicCircuitCurrentP1',
	112 => 'dynamicCircuitCurrentP2',
	113 => 'dynamicCircuitCurrentP3',
	80 => 'chargerFirmware',
	131 => 'chargerRAT',
	30 => 'lockCablePermanently',
	182 => 'inCurrentT2',
	183 => 'inCurrentT3',
	184 => 'inCurrentT4',
	185 => 'inCurrentT5',
	114 => 'outputCurrent',
	190 => 'inVoltageT1T2',
	191 => 'inVoltageT1T3',
	192 => 'inVoltageT1T4',
	193 => 'inVoltageT1T5',
	194 => 'inVoltageT2T3',
	195 => 'inVoltageT2T4',
	196 => 'inVoltageT2T5',
	197 => 'inVoltageT3T4',
	198 => 'inVoltageT3T5',
	199 => 'inVoltageT4T5',
	46 => 'ledMode',
	104 => 'cableRating',
	48 => 'dynamicChargerCurrent',
	47 => 'maxChargerCurrent',
	70 => 'circuitTotalAllocatedPhaseConductorCurrentL1',
	71 => 'circuitTotalAllocatedPhaseConductorCurrentL2',
	72 => 'circuitTotalAllocatedPhaseConductorCurrentL3',
	73 => 'circuitTotalPhaseConductorCurrentL1',
	74 => 'circuitTotalPhaseConductorCurrentL2',
	75 => 'circuitTotalPhaseConductorCurrentL3',
	96 => 'reasonForNoCurrent',
	50 => 'offlineMaxCircuitCurrentP1',
	51 => 'offlineMaxCircuitCurrentP2',
	52 => 'offlineMaxCircuitCurrentP3',
	119 => 'errorCode',
	230 => 'eqAvailableCurrentP1',
	231 => 'eqAvailableCurrentP2',
	232 => 'eqAvailableCurrentP3',
	115 => 'deratedCurrent',
	116 => 'deratingActive',
	250 => 'connectedToCloud'
];

$config = json_decode(file_get_contents($file_config), true);
if (!is_array($config)) { $config = []; }
$cfgObsIds = isset($config['observation_ids']) ? trim($config['observation_ids']) : '';

if ($_POST) {
	$selectedIds = isset($_POST['observation_ids']) && is_array($_POST['observation_ids']) ? $_POST['observation_ids'] : [];
	$selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds), function ($id) use ($observationOptions) {
		return isset($observationOptions[$id]);
	})));
	if (empty($selectedIds)) {
		$config['observation_ids'] = 'none';
	} else {
		$orderedSelectedIds = [];
		foreach (array_keys($observationOptions) as $id) {
			if (in_array($id, $selectedIds)) { $orderedSelectedIds[] = $id; }
		}
		$config['observation_ids'] = implode(',', $orderedSelectedIds);
	}
	file_put_contents($file_config, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	$cfgObsIds = $config['observation_ids'];
}

if ($cfgObsIds === '') {
	$selectedObsIds = $defaultObsIds;
} elseif (strtolower($cfgObsIds) === 'all') {
	$selectedObsIds = array_keys($observationOptions);
} elseif (strtolower($cfgObsIds) === 'none') {
	$selectedObsIds = [];
} else {
	$parts = preg_split('/\s*,\s*/', $cfgObsIds, -1, PREG_SPLIT_NO_EMPTY);
	$selectedObsIds = array_values(array_unique(array_filter(array_map('intval', $parts), function ($id) use ($observationOptions) {
		return isset($observationOptions[$id]);
	})));
}

$template_title = "EaseeHome";
$helplink = $L['LINKS.WIKI'];

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
echo '<img src="logo.png" alt="Easee Home">';

echo '<p>'. $L['MAIN.INTRO1']. '</p>';
echo '<br>';
echo '<form action="index.php" method="post">';
echo '<p class="wide">'. $L['OBSERVATION.HEAD']. '</p>';
echo '<p><a style="color:#c05020;">'.$L['OBSERVATION.ALL_WARNING'].'</a></p>';
echo '<div style="margin-bottom:10px;">';
echo '<button type="button" data-mini="true" data-inline="true" onclick="setObsPreset(\'none\')">'.$L['OBSERVATION.PRESET_NONE'].'</button>';
echo '<button type="button" data-mini="true" data-inline="true" onclick="setObsPreset(\'standard\')">'.$L['OBSERVATION.PRESET_STANDARD'].'</button>';
echo '<button type="button" data-mini="true" data-inline="true" onclick="setObsPreset(\'all\')">'.$L['OBSERVATION.PRESET_ALL'].'</button>';
echo '</div>';
echo '<fieldset data-role="controlgroup">';
foreach ($observationOptions as $obsId => $obsName) {
	$checkboxId = 'obsid_' . $obsId;
	$isChecked = in_array($obsId, $selectedObsIds);
	echo '<input type="checkbox" id="'.$checkboxId.'" class="obs-id-box" name="observation_ids[]" value="'.$obsId.'"';
	if ($isChecked) { echo ' checked'; }
	echo '>';
	echo '<label for="'.$checkboxId.'">'.$obsId.' - '.$obsName.'</label>';
}
echo '</fieldset>';
echo '<p>'.$L['OBSERVATION.ID_LIST_LABEL'].': '.implode(', ', array_keys($observationOptions)).'</p>';
echo '<br><p><center><input data-role="button" data-inline="true" data-mini="true" type="submit" name="save_new" data-icon="check" value='.$L['MAIN.SAVE'].'> </center></p>';
echo '</form>';
echo '<script>
var standardObsIds = ['.implode(',', $defaultObsIds).'];
function setObsPreset(mode) {
	var boxes = document.querySelectorAll(".obs-id-box");
	boxes.forEach(function(box) { box.checked = false; });
	if (mode === "all") {
		boxes.forEach(function(box) { box.checked = true; });
		return;
	}
	if (mode === "standard") {
		boxes.forEach(function(box) {
			if (standardObsIds.indexOf(parseInt(box.value, 10)) !== -1) {
				box.checked = true;
			}
		});
	}
}
</script>';
LBWeb::lbfooter();
?>
