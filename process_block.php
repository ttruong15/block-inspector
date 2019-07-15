#!/usr/bin/php
<?php
require_once("src/transaction.inc");

if(count($argv) != 3) {
	echo "Usage: php " . $argv[0] . " <chain> <blocknum>\n";
	echo "Example: \n";
	echo "\tProcessing eos chain for block 1000000\n";
	echo "\tphp " . $argv[0] . " eos 1000000\n";
	exit;
}

$chain = $argv[1];
$blockNum = $argv[2];

try {
	$eosioChain = new EosioChain($chain);
//	$transactionObj = new Transaction($eosioChain);
//	$transactions = $transactionObj->processTransaction($blockNum);
//sleep(20);
	$actions = $eosioChain->getActions($blockNum);
	foreach($actions as $action) {
		if(array_key_exists('account', $action)) {
			if(preg_match("/^edna/", $action['account'])) {
				file_put_contents("action_".$action['account'].".log", $blockNum . ":" . json_encode($action), FILE_APPEND);
			}
		}
	}

exit;
	foreach($transactions as $transaction) {
		if ($transaction['status'] === "executed") {
			if (is_array($transaction['trx']) && array_key_exists('transaction', $transaction['trx'])) {
				foreach ($transaction['trx']['transaction']['actions'] as $action) {
					print_r($action);
//					processAction($action, $chain);
				}
			}
		}
	}
} catch(Exception $e) {
	echo "Error: " . $e->getMessage() . "\n";
}

function processAction($action, $currentChain) {
	if ($action['account'] == "ednazztokens") {
		$actionTypePrefix = substr($action['name'], 0, 4);
print_r($action);

		while(true) {
			try {
				if($actionTypePrefix == "send") {
					$actionTypePostfix = substr($action['name'], 4);
					$receiveAction = "recv".$actionTypePostfix;

					if($actionTypePostfix == "teleport") {
						list($quantity,) = explode(" ", $action['data']['quantity']);

						$targetChain = $action['data']['target_chain'];
						$targetChainName = getChainName($targetChain);

						if($targetChainName) {
							$fromAccountName = $action['data']['from'];
							$memo = $action['data']['memo'];
							$toAccountName = $action['data']['address_to'];
							$eosio = new EosioChain($targetChainName);
							$command = $eosio->buildCommandWithWallet('push action ednazztokens '.$receiveAction.' {"name":"'.$fromAccountName.'","quantity":"'.$quantity.'","memo":"'.$memo.'","target_chain":"'. $targetChain . '","address_to":"'.$toAccountName.'"}');
							
							if($eosio->checkAndUnlockWallet()) {
								$result = $eosio->runCommand($command, false);
								$issueTransId = EosioChain::parseTransactionId($result);
								echo "Issue successfully with transaction id: " . $issueTransId . "\n";
							} else {
								// Log error
							}
						}
					} else {
						$eosio = new EosioChain($currentChain);
						$enableChains = $eosio->getEnableChains();

						foreach($enableChains as $chainName) {
							if($chainName != $currentChain) {
								$eosioChain = new EosioChain($chainName);

								$data = array();
								foreach($action['data'] as $fieldName=>$value) {
									$data[$fieldName] = $value;
								}

								$command = $eosio->buildCommandWithWallet('push action ednazztokens '.$receiveAction.' ' . json_encode($data));
								$result = $eosio->runCommand($command, false);
								$issueTransId = EosioChain::parseTransactionId($result);

								echo "Issue successfully with transaction id: " . $issueTransId . "\n";
							}
						}
					}
				}

				break;
			} catch(Exception $e) {
				$eosio = new EosioChain($currentChain);
				$eosio->log($e->getMessage());
			}
		} // End while
	}
}
