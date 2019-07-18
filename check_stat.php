<?php
require_once("src/telegram.inc");

define("STAT_FILE", "logs/stats.log");
$telegram = new Telegram();

$availableChains = getAvailableChains();
$currentStat = getCurrentStat();
$lastCheckStat = getLastStatCheck();

logMessage("Start checking..");
if(is_array($lastCheckStat) && count($lastCheckStat)) {
	foreach($availableChains as $chainName) {
		if(array_key_exists($chainName, $lastCheckStat) && array_key_exists($chainName, $currentStat) && $lastCheckStat[$chainName] == $currentStat[$chainName]) {
			$message = date("jS F H:i:s e") . "\n\n";
			$message .= "<b>".$chainName."</b>\n";
			$message .= "<b>Last processed:</b> " . calculateTime($lastCheckStat[$chainName]) . " ago\n";

			$data = array();
			$data['text'] = $message;
			$telegram->sendMessage($data);
			logMessage("Send - " . $message);
		}
	}
}
file_put_contents(STAT_FILE, json_encode($currentStat));
logMessage("Completed");

function getCurrentStat() {
	$logDir = getLogDir();
	$directories = scandir($logDir);
	$lastStat = array();
	foreach($directories as $directory) {
		if($directory == "." || $directory == "..") {
			continue;
		}
		if(is_dir($logDir.$directory)) {
			$filename = $logDir.$directory."/current_blockheight.log";
			if(file_exists($filename)) {
				$currentStats = json_decode(file_get_contents($filename), true);
				if(is_array($currentStats)) {
					foreach($currentStats as $timer) {}
					$lastStat[$directory] = $timer;
				}
			}
		}
	}

	return $lastStat;
}

function getAvailableChains() {
	$chains = array();
	$logDir = getLogDir();
	$directories = scandir($logDir);
	foreach($directories as $directory) {
		if(is_dir($logDir.$directory)) {
			$filename = $logDir.$directory."/current_blockheight.log";
			if(file_exists($filename)) {
				$chains[] = $directory;
			}
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

function getLogDir() {
	$logDir = __DIR__."/logs/";

	return $logDir;
}

function calculateTime($lastCheckTimer) {
	$duration = time() - $lastCheckTimer;

	$hours = floor($duration / 3600);
	$minutes = floor(($duration / 60) % 60);
	$seconds = $duration % 60;

	return $hours.":".$minutes.":".$seconds;
}

function logMessage($message) {
	echo date("Y-m-d H:i:s") . " - " . $message . "\n";
}