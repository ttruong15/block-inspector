<?php
class Mysql extends \mysqli {
    public function __construct($host, $username, $password, $database, $port) {
        parent::__construct($host, $username, $password, $database, $port);
        if ($this->connect_error) {
            throw new \Exception('Connect failed (' . $this->connect_errno . ') ' . $this->connect_error);
        }
    }

    public function escape($data) {
        return $this->real_escape_string($data);
    }

    public function getError() {
        return $this->error;
    }

    public function getErrorCode() {
        return $this->errno;
    }
}
