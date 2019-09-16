#!/usr/bin/php
<?php
require_once("src/database.inc");
require_once("src/eosio_chain.inc");
require_once("src/telegram.inc");

$availableChains = array('eos'=>1, 'telos'=>2, 'worbli'=>3);

$logFile = "logs/new_member_last_record.log";
$lastLog = array();
if(file_exists($logFile)) {
	$lastLog = json_decode(file_get_contents($logFile), true);
}

if(is_array($lastLog) && count($lastLog)) {
	$itemId = key($lastLog);
	$query = "
		SELECT i.item_key, i.name, im.*
		FROM wp_c7dnz82ntw_frm_items i JOIN wp_c7dnz82ntw_frm_item_metas im ON i.id=im.item_id
		WHERE form_id=53 and i.created_at >= '". addslashes($lastLog[$itemId]). "'
		AND im.item_id > ".intval($itemId)."
		ORDER BY im.created_at
	";
} else {
	$query = "
		SELECT i.item_key, i.name, im.*
		FROM wp_c7dnz82ntw_frm_items i JOIN wp_c7dnz82ntw_frm_item_metas im ON i.id=im.item_id
		WHERE form_id=53
		ORDER BY im.created_at
	";
}

$db = new Database();
$stmt = $db->query($query);
$results = $stmt->fetch_all(MYSQLI_ASSOC);

if(empty($results)) {
	echo date("Y-m-d H:i:s") . " - no new member found\n";
	exit;
}

$datas = array();
foreach($results as $result) {
	$lastRecord = array();
	$itemId = $result['item_id'];
	$fieldId = $result['field_id'];
	$value = $result['meta_value'];
	$datas[$itemId][$fieldId] = $value;
	$lastRecord[$itemId] = $result['created_at'];
}

echo date("Y-m-d H:i:s") . " - found " . count($datas) . " new member" . (count($datas) > 1 ? "s" : "") ."\n";
$field = get_available_fields();

$telegram = new Telegram();
foreach($datas as $data) {
	$userId = $data[$field['user_id']];
	$membershipId = $data[$field['membership_id']];
	$homeChain = strtoupper($data[$field['home_chain']]);
	$accountName = $data[$field['account_name']];
	$stakeEdna = $data[$field['stake_edna']];
	$referredBy = array_key_exists($field['referred_by_id'], $data) ? $data[$field['referred_by_id']] : 0;
	$agreeConstitution = array_key_exists($field['agree_edna_constitution'], $data) && $data[$field['agree_edna_constitution']] ? 1 : 0;
	$agreeOneUser = array_key_exists($field['agree_one_user'], $data) && $data[$field['agree_one_user']] ? 1 : 0;

	$genericResearcher = @unserialize($data[$field['genetic_researcher']]);
	if($genericResearcher === false) {
		$genericResearcher = array_key_exists($field['genetic_researcher'], $data) && $data[$field['genetic_researcher']] ? $data[$field['genetic_researcher']] : "";
	}

	if($homeChain == "EOS") {
		$homeChainId = $availableChains['eos'];
	} else if($homeChain == "TELOS") {
		$homeChainId = $availableChains['telos'];
	} else if($homeChain == "WORBLI") {
		$homeChainId = $availableChains['worbli'];
	} else {
		throw new Exception("Chain not yet supported: " . $homeChain);
	}

	$isResearcher = 0;
	$isCouncilor = 0;
	if(is_array($genericResearcher)) {
		foreach($genericResearcher as $researcher) {
			if(preg_match("/genetic researcher/", $researcher)) {
				$isResearcher = 1;
			} else if(preg_match("/genetic councilor/", $researcher)) {
				$isCouncilor = 1;
			}
		}
	} else {
		if(preg_match("/genetic researcher/", $genericResearcher)) {
			$isResearcher = 1;
		} else if(preg_match("/genetic councilor/", $genericResearcher)) {
			$isCouncilor = 1;
		}
	}

	foreach($availableChains as $chainName=>$chainId) {
		$eosioChain = new EosioChain($chainName);
		$command = $eosioChain->buildCommandWithWallet('push action ednazztokens addnewmember \'{"member_id":"'.$userId.'","home_chain":"'.$homeChainId.'","account":"'.$accountName.'","referred_by_id":"'. $referredBy . '","is_researcher":"'.$isResearcher.'", "is_councilor":"'.$isCouncilor.'"}\' -p ednazzscotty');

		if($eosioChain->checkAndUnlockWallet()) {
			$retryCount = 0;
			while(true) {
				$result = $eosioChain->runCommand($command, false, true, true);
				$issueTransId = EosioChain::parseTransactionId($result);
				if(!$issueTransId) {
					$error = "Issue action failed: ";
					if(is_array($result)) {
						$error .= json_encode($result) . "\n";
					} else {
						$error .= $result . "\n";
					}
					$error .= $command . "\n";

					if(preg_match("/3080006: Transaction took too long/", $error)) {
						if($retryCount <= 3) {
							continue;
						}
						$retryCount++;
					}
					echo $error . "\n";

					$eosioChain->log($error);
					$message = date("jS F H:i:s e") . "\n\n";
					$message .= $error;
					$telegram->sendMessage(array('text'=>$message));
				} else {
					$eosioChain->log("Issue successfully with transaction id: " . $issueTransId);
					if(is_object($telegram) && $telegram instanceof Telegram) {
						$message = date("jS F H:i:s e") . "\n\n";
						$message .= "Issue successfully with transaction id: $issueTransId\n\n";
						$message .= "$command\n";

						echo $message . "\n";
						$telegram->sendMessage(array('text'=>$message));
					}
				}
				break; // transaction completed, exit loop
			}
		} else {
			$eosioChain->log("Error: unable to unlock wallet");
			$message = date("jS F H:i:s e") . "\n\n";
			$message .= "Failed to unlock wallet\n\n";
			$message .= json_encode($data) . "\n";
			$telegram->sendMessage(array('text'=>$message));
		}
	}
}

if(isset($lastRecord) && is_array($lastRecord) && count($lastRecord)) {
	file_put_contents($logFile, json_encode($lastRecord));
}


function get_available_fields() {
	return array(
		"user_id"=>987,
		"membership_id"=>988,
		"home_chain"=>990,
		"account_name"=>991,
		"stake_edna"=>992,
		"agree_edna_constitution"=>993,
		"agree_one_user"=>1101,
		"referred_by_id"=>1070,
		"genetic_researcher"=>1102
	);
}
