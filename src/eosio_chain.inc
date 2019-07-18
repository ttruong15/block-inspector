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

class EosioChain {
	private $currentChain = null;

	private $config = null;
	private $configTimestamp = null;

	private $availableNodes = array();
	private $nodeConfigTimestamp = null;

    public function __construct($currentChain) {
		if(empty($currentChain)) {
			throw new Exception("Chain name cannot be empty");
		}
        $this->currentChain = $currentChain;
		$this->getConfigOptions();
    }

    public static function parseTransactionId($data) {
        $issueTransId = "";
		$match = array();
        if (preg_match("/.+transaction: ([^\s]+) .+/", $data, $match)) {
            $issueTransId = $match[1];
        }

        return $issueTransId;
    }

	/**
	 * get chain information
	 *
	 * @param integer $blockNum
	 * @return array
	 */
    public function getInfo() {
		while(true) {
			try {
				$command = $this->buildCommand("get info");
				return $this->runCommand($command);
			} catch (Exception $e) {
				$this->checkErrors($e);
				$this->log($e->getMessage());
			}
		}
    }

    public function getLastIrreversibleBlockNum() {
        try {
            $chainInfo = $this->getInfo();
            if (is_array($chainInfo)) {
                return $chainInfo['last_irreversible_block_num'];
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

	/**
	 * grab a block from the node
	 *
	 * @param integer $blockNum
	 * @return array
	 */
    public function getBlock($blockNum) {
		while(true) {
			try {
				$command = $this->buildCommand("get block $blockNum");
				return $this->runCommand($command);
			} catch (Exception $e) {
				$this->checkErrors($e);
				$this->log($e->getMessage());
			}
		}
    }

	/**
	 * Check for fatal errors and re-throw the error.  This kind of errors should stop the script from running.
	 *
	 * @param Exception $e
	 * @throws Exception
	 */
	public function checkErrors($e) {
		if(preg_match("/^Missing conf/", $e->getMessage())) {
			$this->log($e->getMessage() . " trace: " . json_encode($e->getTrace()));
			throw $e;
		}
	}

	/**
	 * get transactions for a blocknum
	 *
	 * @param integer $blockNum
	 * @return array
	 */
    public function getTransactions($blockNum) {
		$block = $this->getBlock($blockNum);
		return $block['transactions'];
    }

	/**
	 * get all actions for a given blocknum
	 *
	 * @param integer $blockNum
	 * @return array
	 */
    public function getActions($blockNum) {
		$block = $this->getBlock($blockNum);
		if(array_key_exists('transactions', $block) && is_array($block['transactions'])) {
			$actions = array();
			foreach($block['transactions'] as $transaction) {
				if ($transaction['status'] === "executed") {
					if (is_array($transaction['trx']) && array_key_exists('transaction', $transaction['trx'])) {
						foreach ($transaction['trx']['transaction']['actions'] as $action) {
							$actions[] = $action;
						}
					}
				}
			}

			return $actions;
		} else {
			return array();
		}
    }

	/**
	 * build a cleos command string to use to send to a node.
	 *
	 * @param string $commandArg
	 * @return string
	 */
	public function buildCommand($commandArg) {
		$command = $this->getConfigOption('cleos') . ' -u ' . $this->getNodeURL() . ' ' . $commandArg;

		return $command;
	}

	/**
	 * Same as buildCommand except the wallet url is specify.  This is for those actions that required
	 * permission to run.
	 *
	 * @param string $commandArg
	 * @return string
	 */
	public function buildCommandWithWallet($commandArg) {
		$command = $this->getConfigOption('cleos') . ' -u ' . $this->getNodeURL() . ' --wallet-url ' . $this->getConfigOption('wallet-url') . ' ' . $commandArg;

		return $command;
	}

	/**
	 * check to see if the wallet is open and unlock.
	 * if wallet is not open, we need to open and then unlock.
	 * Expected result string from wallet list command:
	 * // Wallets:
	 * // [
	 * //   "default"
	 * // ]
	 * @return boolean
	 */
    public function checkAndUnlockWallet() {

		$command = $this->buildCommandWithWallet("wallet list");
        $result = $this->runCommand($command, false);

		$result = str_replace("Wallets:", "", $result);
		$walletList = json_decode($result, true);

		$walletName = $this->getConfigOption('wallet-name');

		// no wallet open, so we need to open and then unlock it.
		if(empty($walletList)) {
			$openWalletCommand = $this->buildCommandWithWallet("wallet open -n " . $walletName);
			$this->runCommand($openWalletCommand, false);
			return $this->unlockWallet();
		} else {
			foreach($walletList as $walletName) {
				if(preg_match("/$walletName \*/", $walletName)) {
					// look like wallet is open and unlock
					return true;
				}
			}

			return $this->unlockWallet();
		}
    }

	/**
	 * unlock a wallet ready to be use, if unlock successfully it will return true.
	 *
	 * @return boolean
	 * @throws Exception
	 */
    public function unlockWallet() {
		$command = $this->buildCommandWithWallet("wallet unlock --password " . $this->getConfigOption('wallet-password'));
        $result = $this->runCommand($command, false);

		if(preg_match("/(Already unlocked|Unlocked)/", $result)) {
			return true;
		} else {
            throw new Exception("Unable to unlock wallet: " . $result);
        }
    }

	/**
	 * randomly select a single node url to use in cleos
	 *
	 * @return string
	 */
	public function getNodeURL() {
		$nodes = $this->getAvailableNodes();
        $numNode = count($nodes) - 1;

		if($numNode > 0) {
			list($usec, $sec) = explode(' ', microtime());
			srand($sec + $usec * 1000000);

			$randIdx = rand(0, $numNode);
		} else {
			$randIdx = 0;
		}

		return $nodes[$randIdx];
	}

	/**
	 * get a single config option
	 *
	 * @param string $optionName
	 * @return mixed
	 */
	public function getConfigOption($optionName) {
		if(empty($this->config)) {
			$this->getConfigOptions();
		}

		if(array_key_exists($optionName, $this->config)) {
			return $this->config[$optionName];
		} else {
			return false;
		}
	}

	/**
	 * Send a cleos command to the node
	 *
	 * @param string $command
	 * @param boolean $expectedJsonReturn
	 * @param boolean $redirectErrorMessage
	 * @return array
	 * @throws Exception
	 */
    public function runCommand($command, $expectedJsonReturn = true, $redirectErrorMessage = true, $log=false) {
		if($redirectErrorMessage) {
			$command .= " 2>&1";
		}

		if($log) {
			$this->log($command);
		}

        ob_start();
        @system($command, $retval);
        $resultString = ob_get_contents();
        ob_end_clean();

		if($expectedJsonReturn) {
			$resultArray = json_decode(mb_convert_encoding($resultString, 'UTF-8', 'UTF-8'), true);

			if (json_last_error()) {
				throw new Exception($resultString);
			}

			return $resultArray;
		} else {
			return $resultString;
		}
    }

	/**
	 * reading the config from a json file.
	 *
	 * @return array
	 * @throws Exception
	 * @throws exception
	 */
    public function getConfigOptions() {
		$configFile = "conf/".$this->currentChain.".json";
		if(!file_exists($configFile)) {
			throw new Exception("Missing " . $configFile . " file.");
		}

		if($this->configTimestamp == null || $this->configTimestamp != filemtime($configFile)) {
			$this->config = json_decode(file_get_contents($configFile), true);
			if(json_last_error()) {
				throw new Exception("ERROR: reading " . $configFile . " : " . json_last_error_msg());
			}
			$this->configTimestamp = filemtime($configFile);
		}

		if(!array_key_exists('cleos', $this->config) || !file_exists($this->config['cleos'])) {
			throw new Exception("cleos '".$this->config['cleos']."' command is not available in your system.");
		}

		if(!array_key_exists('wallet-url', $this->config) && empty($this->config['wallet-url'])) {
			throw new Exception("Wallet url is required.");
		}

		if(!array_key_exists('wallet-password', $this->config) && empty($this->config['wallet-password'])) {
			throw new Exception("Wallet password is required.");
		} else if($this->config['wallet-password'] == "XXXX") {
			throw new Exception("Wallet password is required");
		}

		return $this->config;
    }

	/**
	 * Reading the chain config from a json file.  The config file should contain
	 * the name of all nodes currently enabled.  So to disable a chain we just need
	 * to remove a record from this config file.
	 *
	 * currenly supported chain are telos, eos and worbli
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getEnableChains() {
		return $this->getConfigOption('enable-chain');
	}

	/**
	 * Reading the json config file to get all the available node url to use in the cleos command.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getAvailableNodes() {
		$avalableNodeConfigFile = "conf/".$this->currentChain."_node_available.json";
		if(!file_exists($avalableNodeConfigFile)) {
			throw new Exception("Missing " . $avalableNodeConfigFile . " file.");
		}

		if($this->nodeConfigTimestamp == null || $this->nodeConfigTimestamp != filemtime($avalableNodeConfigFile)) {
			$this->availableNodes = json_decode(file_get_contents($avalableNodeConfigFile), true);
			if(json_last_error()) {
				throw new Exception("ERROR: reading " . $avalableNodeConfigFile . " : " . json_last_error_msg());
			}
			$this->nodeConfigTimestamp = filemtime($avalableNodeConfigFile);
		}

		return $this->availableNodes;
	}

	/**
	 * log message to a file, once the file reach file-log-size-limit value (in Megabyte) it will compress
	 * and archive it.
	 *
	 * @param string $message
	 * @param string $logDir
	 */
    public function log($message, $logDir=null) {
        $logMessage = date("Y-m-d H:i:s", time()) . " - " . $message . "\n";

		if($logDir === null) {
			$dir = dirname(__DIR__) . "/logs/".$this->currentChain;
			if(!file_exists($dir)) {
				mkdir($dir, 0777, true);
			}

			$logFile = $dir."/log_running.log";
		} else {
			if(substr($logDir, -1) == "/") {
				$logFile = $logDir . "log_running.log";
			} else {
				$logFile = $logDir . "/log_running.log";
			}
		}

        if (file_exists($logFile)) {
            $fileSize = filesize($logFile) / 1024 / 1024;
            // Size in Megabyte, if bigger then 100M compress and rotate
            if ($fileSize > $this->getConfigOption('file-log-size-limit')) {
                $compressContent = gzcompress(file_get_contents($logFile));
                file_put_contents($logFile . date("YmdHis").".gz", $compressContent);
                file_put_contents($logFile, "");
            }
        }

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
} // End EosioChain class