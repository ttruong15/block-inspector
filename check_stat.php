<?php
require_once("src/telegram.inc");

define("STAT_FILE", "logs/stats.log");
$telegram = new Telegram();

$availableChains = getAvailableChains();
$currentStat = getCurrentStat();
$lastCheckStat = getLastStatCheck();

if(is_array($lastCheckStat) && count($lastCheckStat)) {
	foreach($availableChains as $chainName) {
		if($lastCheckStat[$chainName] == $currentStat[$chainName]) {
			$duration = number_format(((time() - $lastCheckStat[$chainName])/60), 2);
			$message = date("jS F H:i:s e") . "\n\n";
			$message .= "<b>".$chainName."</b>\n";
			$message .= "<b>Last processed:</b> " . $duration . " minutes ago\n";

			$data = array();
			$data['text'] = $message;
			$telegram->sendMessage($data);
		}
	}
}
file_put_contents(STAT_FILE, json_encode($currentStat));

function getCurrentStat() {
	$files = scandir("logs");
	$lastStat = array();
	foreach($files as $file) {
		if(preg_match("/(.+)_current_blockheight.log$/", $file, $match)) {
			$currentStats = json_decode(file_get_contents("logs/".$file), true);
			$chain = $match[1];
			foreach($currentStats as $blocknum=>$timer) {}
			$lastStat[$chain] = $timer;
		}
	}

	return $lastStat;
}

function getAvailableChains() {
	$files = scandir("logs");
	$chains = array();
	$match = array();
	foreach($files as $file) {
		if(preg_match("/(.+)_current_blockheight.log$/", $file, $match)) {
			$chains[] = $match[1];
		}
	}

	return $chains;
}

function getLastStatCheck() {
	if(file_exists(STAT_FILE)) {
		return json_decode(file_get_contents(STAT_FILE), true);
	}

	return array();
}