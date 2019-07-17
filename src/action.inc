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
	public function __construct(EosioChain $currentChain) {
		$this->currentChain = $currentChain;
	}

	public function processTeleport($action) {
		$targetChain = $action['data']['target_chain'];
		$targetChainName = $this->getChainName($targetChain);
		$targetChainObj = new EosioChain($targetChainName);
		$fromAccountName = $action['data']['from'];
		$quantity = $action['data']['quantity'];
		$memo = $action['data']['memo'];
		$toAccountName = $action['data']['address_to'];
		$command = $targetChainObj->buildCommandWithWallet('push action ednazztokens recvteleport \'{"from":"'.$fromAccountName.'","quantity":"'.$quantity.'","memo":"'.$memo.'","target_chain":"'. $targetChain . '","address_to":"'.$toAccountName.'"}\' -p ednazzscotty');
		if($targetChainObj->checkAndUnlockWallet()) {
			$result = $targetChainObj->runCommand($command, false);
			$issueTransId = EosioChain::parseTransactionId($result);
			if(!$issueTransId) {
				$error = "Issue action failed: ";
				if(is_array($result)) {
					$error .= json_encode($result) . "\n";
				} else {
					$error .= $result . "\n";
				}
				$error .= json_encode($action['data']);
				throw new Exception($error);
			} else {
				$targetChainObj->log("Issue successfully with transaction id: " . $issueTransId);
			}
		} else {
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
				throw new Exception("chain '$targetChain' not supported");
		}
	}
}
