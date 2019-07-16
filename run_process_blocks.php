<?php
require_once("src/eosio_chain.inc");
require_once("src/telegram.inc");

if(count($argv) < 2) {
    echo "Usage: php {$argv[0]} <chain_name(eos|telos)> <turn_on_telegram_bot(0/1)\n";
    echo "\n";
    echo "Example:\n";
    echo "\n";
    echo "\t Running EOS chain without telegram bot\n";
    echo "\t php {$argv[0]} eos 0\n";
    echo "\n";
    echo "\t Running EOS chain and turn on telegram bot\n";
    echo "\t php {$argv[0]} eos 1\n";
    echo "\n";
    exit;
}

$chainName = $argv[1];
$enableTelegramBot = isset($argv[2]) ? $argv[2] : 0;
$runProcess = new RunProcessBlocks($chainName, $enableTelegramBot);
$runProcess->run();

class RunProcessBlocks {

	const MAX_SPAWN = 16;  // Max number of process allow to spawn
	const MAX_BLOCK_GAP = 100;
	const MAX_TIME_ALLOW = 90;  // kill any process that's take long then this value in seconds
	const NOTIFY_TELEGRAM_INTERVAL = 1;  // notify telegram every number of hour

	private $numSpawn = array();
	private $logFilename = null;
	private $lastNotify = null;
	private $enableTelegramBot = false;
	private $chainName = null;
	private $telegram = null;

	public function __construct($chainName, $enableTelegramBot) {
		$this->chainName = $chainName;
		$this->logFilename = "logs/".$this->chainName."_process_block.log";
		$this->currentBlockFile = "logs/".$this->chainName."_current_blockheight.log";
		$this->enableTelegramBot = $enableTelegramBot;
		if($this->enableTelegramBot) {
			$this->telegram = new Telegram();
		}
	}

	public function run() {
		try {
			$eosio = new EosioChain($this->chainName);
			$lastBlocknumNotifyTelegram = "logs/last_block_update_telegram_".$this->chainName;
			$currentHeadBlockHeight = $eosio->getLastIrreversibleBlockNum();
			$currentBlockHeight = 0;
			while(true) {
				if(file_exists("exit_".$this->chainName)) {
					unlink("exit_".$this->chainName);
					break;
				}

				// allow max number process to spawn
				try {
					if(!$currentBlockHeight && file_exists($this->currentBlockFile)) {
						$currentBlockHeight = trim(file_get_contents($this->currentBlockFile));
					} else if(!$currentBlockHeight) {
						// if no current blockheight, then start at the latest head block
						$currentBlockHeight = $currentHeadBlockHeight;
					}

					if(count($this->numSpawn) <= self::MAX_SPAWN) {
						$this->notifyTelegramBot($lastBlocknumNotifyTelegram, $currentBlockHeight, $currentHeadBlockHeight);

						if($currentBlockHeight < $currentHeadBlockHeight) {
							// log block start processing
							$command = "/usr/bin/nohup ./process_block.php {$this->chainName} $currentBlockHeight >> {$this->logFilename} 2>&1 & echo $!";
							$childPid = exec($command);
							$this->numSpawn[$childPid] = array('start_timer'=>microtime(true), 'blocknum'=>$currentBlockHeight);
							file_put_contents($this->currentBlockFile, ++$currentBlockHeight);
						} else {
							$currentHeadBlockHeight = $eosio->getLastIrreversibleBlockNum();
							echo "H";
							sleep(2);
						}
					} else {
						echo "S";
						sleep(1);
					}

					if(is_array($this->numSpawn) && count($this->numSpawn)) {
						$this->checkRunningProcess();
					}
				} catch(Exception $e) {
					echo "Error: " . $e->getMessage() . "\n";
				}
			} // End while loop

			while(count($this->numSpawn)) {
				echo "Waiting for all child to exit\n";
				sleep(1);
				$this->checkRunningProcess();
			}
		} catch(Exception $e) {
			echo date("Y-m-d H:i:s") . " - Error: " . $e->getMessage() . "\n\n";
		}
	}

	private function checkRunningProcess() {
		foreach($this->numSpawn as $pid=>$details) {
			$dirExist = is_dir("/proc/$pid/fd") && file_exists("/proc/$pid/fd");
			if(!$dirExist) {
				echo date("Y-m-d H:i:s") . " - " . $details['blocknum'] . " processed\n";
				unset($this->numSpawn[$pid]);
			}
		}

		if(is_array($this->numSpawn) && count($this->numSpawn)) {
			echo "Queue blocknum: ";
			foreach($this->numSpawn as $runningPid=>$job) {
				echo $job['blocknum'] . " ";
				$duration = microtime(true) - $job['start_timer'];
				if($duration > self::MAX_TIME_ALLOW) {
					echo "Failed block: " . $job['blocknum'] . "\n";
					$command = "/bin/kill -9 ".$runningPid;
					exec($command);

					$command = "/usr/bin/nohup ./process_block.php.php {$this->chainName} ".$job['blocknum']." >> {$this->logFilename} 2>&1 & echo $!";
					$childPid = exec($command);
					$this->numSpawn[$childPid] = array('start_timer'=>microtime(true), 'blocknum'=>$job['blocknum']);
					unset($this->numSpawn[$runningPid]);
				}
			}
			echo "\n";
		} else {
			echo "*";
		}
	}

	private function notifyTelegramBot($lastBlocknumNotifyTelegram, $currentBlockHeight, $currentHeadBlockHeight) {
		$currentHour = date("H");
		if($this->enableTelegramBot && ($this->lastNotify === null || ($currentHour % self::NOTIFY_TELEGRAM_INTERVAL == 0 && $currentHour != $this->lastNotify))) {
			if(file_exists($lastBlocknumNotifyTelegram)) {
				$lastBlockProcess = file_get_contents($lastBlocknumNotifyTelegram);
			} else {
				$lastBlockProcess = $currentBlockHeight;
			}
			$data = array(
				'Current Block:'=>number_format($currentBlockHeight),
				'Last Irreversible Block:'=>number_format($currentHeadBlockHeight),
				'Total Block Behind:'=>number_format($currentHeadBlockHeight - $currentBlockHeight),
				'Processed Block:'=>$currentBlockHeight - $lastBlockProcess,
				'chain'=>$this->chainName
			);
			file_put_contents($lastBlocknumNotifyTelegram, $currentBlockHeight);
			$this->telegram->notifyStatus($data);
			$this->lastNotify = $currentHour;
		}
	}
} // End RunProcessBlocks class
