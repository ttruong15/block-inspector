#!/usr/bin/php
<?php
require_once("src/action.inc");
require_once("src/transaction.inc");
require_once("src/telegram.inc");

if(count($argv) < 3) {
	echo "Usage: php " . $argv[0] . " <chain> <blocknum> [enable_telegram_notification]\n";
	echo "Example: \n";
	echo "\tProcessing eos chain for block 1000000\n";
	echo "\tphp " . $argv[0] . " eos 1000000\n";
	echo "\n";
	echo "Enable telegram notification\n";
	echo "\tProcessing eos chain for block 1000000\n";
	echo "\tphp " . $argv[0] . " eos 1000000 1\n";
	exit;
}

$chain = $argv[1];
$blockNum = $argv[2];
$enableTelegramNotification = $argv[3] > 0 ? true : false;
$eosioChain = new EosioChain($chain);
$telegram = new Telegram();

try {
	$actions = $eosioChain->getActions($blockNum);
	$actionObj = new Action($eosioChain, $telegram);
	if($enableTelegramNotification) {
		$actionObj->setTelegram($telegram);
	}

	foreach($actions as $action) {
		if(array_key_exists('account', $action)) {
			if(preg_match("/^edna/", $action['account'])) {
				switch($action['name']) {
					case 'teleport':
						$actionObj->processTeleport($action);
						break;
				}
				file_put_contents("action_".$chain.".log", $blockNum . ":" . json_encode($action)."\n", FILE_APPEND);
			}
		}
	}
} catch(Exception $e) {
	$message = date("jS F H:i:s e") . "\n\n";
	$message .= $e->getMessage();
	$telegram->sendMessage(array('text'=>$message));
}
