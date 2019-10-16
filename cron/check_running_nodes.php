<?php
/**
 * The script to run as cron to monitor a running node to make sure that its running as expected.
 * When a node is running smoothly we expecting the head_block_num to increase.  If we detect this
 * number doesn't change we send a telegram message.
 */
require_once("../src/eosio_chain.inc");
require_once("../src/telegram.inc");

if(count($argv) < 2) {
    echo "Usage: php {$argv[0]} <chain_name(eos|telos|worbli)>\n";
    echo "\n";
    echo "Example:\n";
    echo "\n";
    echo "\t Checking EOS chain\n";
    echo "\t php {$argv[0]} eos\n";
    echo "\n";
    echo "\t Checking TELOS chain\n";
    echo "\t php {$argv[0]} telos\n";
    echo "\n";
    echo "\t Checking WORBLI chain\n";
    echo "\t php {$argv[0]} worbli\n";
    echo "\n";
    echo "\n";
    exit;
}

$chainName = $argv[1];
$telegram = new telegram();
$eosio = new EosioChain($chainName);
$logFile = dirname(__DIR__) ."/logs/check_running_nodes_".$chainName.".json";

$currentNodeStat = $eosio->getInfo();

if(!file_exists($logFile)) {
	file_put_contents($logFile, json_encode($currentNodeStat));
} else {
	$prevNodeStat = json_decode(file_get_contents($logFile), true);

	if($prevNodeStat['head_block_num'] == $currentNodeStat['head_block_num']) {
		$message = date("jS F H:i:s e") . "\n\n";
		$message .= "<b>$chainName</b> node stop sync at " . $currentNodeStat['head_block_num']."\n\n";
		$telegram->sendMessage(array('text'=>$message));
		echo date("Y-m-d H:i:s") . " - Failed\n";
	} else {
		file_put_contents($logFile, json_encode($currentNodeStat));
		echo date("Y-m-d H:i:s") . " - OK\n";
	}
}