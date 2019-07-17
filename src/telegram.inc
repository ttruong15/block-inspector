<?php

class Telegram {

    const CURL_SSL_VERSION = 6;

    private $contentType = "application/json";
    private $requestType = "POST";
    private $headers = array();
    private $curlVerbose = 0;
    private $curlHeader = 0;
    private $curlTransfer = 1;  // Enable this to have the response result return
    private $curlVerifyPeer = 0;
    private $curlVerifyHost = 0;
    private $curlInfo = array();
    private $curlTimeout = null;
    private $URL = "https://api.telegram.org/bot";
    private $botKey = null;
    private $botChatId = null;
    private $channelName = null;

    public function __construct() {
        try {
            $this->readConfig();
            $this->setURL($this->URL . $this->botKey);
        } catch(Exception $e) {
            // Let the caller handle the error.
            throw $e;
        }
    }

    public function notifyStatus(array $data) {
		try {
			$message = date("jS F H:i:s e") . "\n\n";
			$message .= "<b>Status for '" . strtoupper($data['chain']) . "'</b>\n";

			foreach($data as $label=>$value) {
				$message .= "<b>" . $label . "</b> " . $value . "\n";
			}

			$this->sendMessage(array('text' => $message));
		} catch (Exception $e) {
			print_r($e);
			// ignore
		}
    }

	public function getChannelName() {
		return $this->channelName;
	}

    public function sendMe(array $data) {
        $data['chat_id'] = $this->botChatId;
        if (!array_key_exists('parse_mode', $data)) {
            $data['parse_mode'] = 'html';
        }

        return $this->sendMessage($data);
    }

    public function getMe() {
        $URL = $this->getURL() . '/getMe';
        return $this->SendRequest($URL);
    }

    public function getUpdates(array $data = array()) {
        $URL = $this->getURL() . '/getUpdates';
        return $this->SendRequest($URL, $data);
    }

    public function sendMessage(array $data) {
		if(!array_key_exists('text', $data)) {
			throw new Exception("message is empty.");
		}

		if(!array_key_exists('chat_id', $data)) {
			$data['chat_id'] = $this->channelName;
		}

        if (!array_key_exists('parse_mode', $data)) {
            $data['parse_mode'] = 'html';
        }

        $URL = $this->getURL() . '/sendMessage';
        return $this->SendRequest($URL, $data);
    }

    public function setURL($URL) {
        $this->URL = $URL;
    }

    public function getURL() {
        return $this->URL;
    }

    public function turnOnVerbose() {
        $this->CurlVerbose = 1;
    }

    public function SendRequest($URL, array $Data = array()) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $URL);
        curl_setopt($curl, CURLOPT_HEADER, $this->curlHeader);
        curl_setopt($curl, CURLOPT_VERBOSE, $this->curlVerbose);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->curlTimeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, $this->curlTransfer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->curlVerifyPeer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->curlVerifyHost);
        curl_setopt($curl, CURLOPT_SSLVERSION, self::CURL_SSL_VERSION);

        if (is_array($Data) && count($Data)) {
            curl_setopt($curl, CURLOPT_POST, 0);
            if ($this->requestType == "POST") {
                curl_setopt($curl, CURLOPT_POST, 1);
            } else {
                curl_setopt($curl, CURLOPT_POST, 0);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->requestType);
            }

            if (strstr($this->contentType, "json")) {
                $this->SetRequestHeaders($curl, json_encode($Data));
            } else {
                $this->SetRequestHeaders($curl, http_build_query($Data));
            }
        }

        $response = curl_exec($curl);
        $this->curlInfo = curl_getinfo($curl);

        if (curl_errno($curl)) {
            throw new Exception('Error: server is not responding to your request - ' . curl_errno($curl) . "-" . curl_error($curl));
        }

        curl_close($curl);

        $result = json_decode($response, true);
        if (json_last_error()) {
            throw new Exception("Return data is not json: " . json_last_error() . " - " . json_last_error_msg());
        }

        if ($result['ok'] == 1) {
            return $result['result'];
        } else {
            throw new Exception("return error: " . $response);
        }
    }

    private function SetRequestHeaders($curl, $stringData) {
        $headers = array("Content-Type: " . $this->contentType, "Content-Length: " . strlen($stringData));
        if (is_array($this->headers) && count($this->headers)) {
            foreach ($this->headers as $header) {
                $headers[] = $header;
            }
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $stringData);
    }

    private function readConfig() {
        if(!file_exists("conf/telegram.json")) {
            throw new Exception("conf/telegram.json is not available");
        }

        $config = json_decode(file_get_contents("conf/telegram.json"), true);
        if(json_last_error()) {
            throw new Exception("ERROR: reading telegram.json - " . json_last_error_msg());
        }

        $this->botKey = $config['botkey'];
        $this->botChatId = $config['botchatid'];
        $this->channelName = $config['channel_name'];
        $this->curlTimeout = $config['request-timeout'];

        if(empty($this->botKey) || (empty($this->botChatId) && empty($this->channelName))) {
            throw new Exception("telegram bot is enabled, you must provide botkey and botchatid.");
        }
    }
}