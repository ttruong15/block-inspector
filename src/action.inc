<?php
//////////////////////////////////////////////////////////////////////////////
// Copyright (C) 2019 - Tinh Truong                                         //
//									                                        //
// Date:		24/06/2019													//
// Created by:	Tinh Truong @edna.life                                      //
//                                                                          //
// This source code is subject to commerical confidentiality and may not be //
// disclosed to unauthorised thrid parties without written permission from  //
// the owner.																//
//////////////////////////////////////////////////////////////////////////////
require_once("eosio_chain.inc");
define("EOS", 1);
define("TELOS", 2);
define("WORBLI", 3);

class Action  {
	private $currentChain = null;
	private $telegram = null;

	public function __construct(EosioChain $currentChain) {
		$this->currentChain = $currentChain;
	}

	public function setTelegram(Telegram $telegram) {
		$this->telegram = $telegram;
	}

	public function processTeleport($action) {
		$targetChain = $action['data']['target_chain'];
		$targetChainName = $this->getChainName($targetChain);
		$targetChainObj = new EosioChain($targetChainName);
		$fromAccountName = $action['data']['from'];
		$quantity = $action['data']['quantity'];
		$memo = $action['data']['memo'];
		$toAccountName = $action['data']['address_to'];

		if($targetChainName == "worbli") {
			list($qty, $token) = explode(" ", $quantity);
			$quantity = $qty."0 ". $token;
		}
		$command = $targetChainObj->buildCommandWithWallet('push action ednazztokens recvteleport \'{"from":"'.$fromAccountName.'","quantity":"'.$quantity.'","memo":"'.$memo.'","target_chain":"'. $targetChain . '","address_to":"'.$toAccountName.'"}\' -p ednazzscotty');
		if($targetChainObj->checkAndUnlockWallet()) {
			$result = $targetChainObj->runCommand($command, false, true, true);
			$issueTransId = EosioChain::parseTransactionId($result);
			if(!$issueTransId) {
				$error = "Issue action failed: ";
				if(is_array($result)) {
					$error .= json_encode($result) . "\n";
				} else {
					$error .= $result . "\n";
				}
				$error .= $command . "\n";
				$targetChainObj->log($error);
				throw new Exception($error);
			} else {
				$targetChainObj->log("Issue successfully with transaction id: " . $issueTransId);
				if(is_object($this->telegram) && $this->telegram instanceof Telegram) {
					$message = date("jS F H:i:s e") . "\n\n";
					$message .= "Issue successfully with transaction id: $issueTransId\n\n";
					$message .= "$command\n";
					$this->telegram->sendMessage(array('text'=>$message));
				}
			}
		} else {
			$this->currentChain->log("Error: unable to unlock '$targetChainName' wallet");
			throw new Exception("Error: unable to unlock '$targetChainName' wallet");
		}
	}

	private function getChainName($targetChain) {
		switch($targetChain) {
			case 1:
				return "eos";
			case 2:
				return "telos";
			case 3:
				return "worbli";
			default:
				$this->currentChain->log("chain '$targetChain' not supported");
				throw new Exception("chain '$targetChain' not supported");
		}
	}
}
