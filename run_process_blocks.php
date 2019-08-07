<?php
require_once("src/eosio_chain.inc");
require_once("src/telegram.inc");

if(count($argv) < 2) {
    echo "Usage: php {$argv[0]} <chain_name(eos|telos|worbli)> [enableTelegram]\n";
    echo "\n";
    echo "Example:\n";
    echo "\n";
    echo "\t Running EOS chain\n";
    echo "\t php {$argv[0]} eos\n";
    echo "\n";
    echo "\t Running TELOS chain\n";
    echo "\t php {$argv[0]} telos\n";
    echo "\n";
    echo "\t Running WORBLI chain\n";
    echo "\t php {$argv[0]} worbli\n";
    echo "\n";
    echo "\n";
    echo "\t Running WORBLI chain and Enable Telegram Notification\n";
    echo "\t php {$argv[0]} worbli 1\n";
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
	private $chainName = null;
	private $processingBlock = array();
	private $enableTelegramNotification = false;
	const PHP_CMD = "/usr/bin/php";
	const KILL_CMD = "/bin/kill";

	public function __construct($chainName, $enableTelegram=false) {

		if(!file_exists(self::PHP_CMD)) {
			throw new Exception(self::PHP_CMD . " is missing");
		}
		if(!file_exists(self::KILL_CMD)) {
			throw new Exception(self::KILL_CMD . " is missing");
		}

		$this->chainName = $chainName;
		if(!file_exists("logs/".$this->chainName)) {
			mkdir("logs/".$this->chainName, 0777);
		}
		$this->logFilename = "logs/".$this->chainName."/process_block.log";
		$this->currentBlockFile = "logs/".$this->chainName."/current_blockheight.log";
		$this->enableTelegramNotification = $enableTelegram;
	}

	public function run() {
		try {
			$eosio = new EosioChain($this->chainName);
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
						$currentBlockHeights = json_decode(file_get_contents($this->currentBlockFile), true);
						if(is_array($currentBlockHeights) && count($currentBlockHeights)) {
							foreach($currentBlockHeights as $currentBlockHeight=>$lastRun) {}
						}
						if(!$currentBlockHeight) {
							$currentBlockHeight = $currentHeadBlockHeight;
						}
					} else if(!$currentBlockHeight) {
						// if no current blockheight, then start at the latest head block
						$currentBlockHeight = $currentHeadBlockHeight;
					}

					if(count($this->numSpawn) <= self::MAX_SPAWN) {
						if($currentBlockHeight < $currentHeadBlockHeight) {
							// log block start processing
							$command = self::PHP_CMD . " process_block.php {$this->chainName} $currentBlockHeight {$this->enableTelegramNotification} >> {$this->logFilename} 2>&1 & echo $!";
							$childPid = exec($command);
							$this->numSpawn[$childPid] = array('start_timer'=>microtime(true), 'blocknum'=>$currentBlockHeight);
							$currentBlockHeight += 1;
							$this->processingBlock[$currentBlockHeight] = time();
							file_put_contents($this->currentBlockFile, json_encode($this->processingBlock));
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
				unset($this->processingBlock[$details['blocknum']]);
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
					$command = self::KILL_CMD . " ".$runningPid;
					unset($this->numSpawn[$runningPid]);
					exec($command);

					$command = self::PHP_CMD . " process_block.php {$this->chainName} $currentBlockHeight {$this->enableTelegramNotification} >> {$this->logFilename} 2>&1 & echo $!";
					$childPid = exec($command);
					$this->numSpawn[$childPid] = array('start_timer'=>microtime(true), 'blocknum'=>$job['blocknum']);
					$this->processingBlock[$job['blocknum']] = time();
				}
			}
			echo "\n";
		} else {
			echo "*";
		}
	}
} // End RunProcessBlocks class
