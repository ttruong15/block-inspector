<?php
require_once("mysql.inc");

class Database {
    private $db = null;

    public function getDBConnection() {
		return $this->db;
	}

    private function connect() {
        $config = json_decode(file_get_contents(__DIR__."/../conf/db.json"), true);
		$this->db = new Mysql($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
    }

    public function close() {
		if(is_object($this->db) && method_exists($this->db, "close")) {
			$this->db->close();
		}
    }

    public function query($query) {
		try {
			if(!$this->db) {
				$this->connect();
			}
			$result = $this->db->query($query);
			if (!$result) {
				$this->throwError();
			}

			return $result;
		} catch(\Exception $e) {
			$this->throwError();
		}
    }

    public function prepare($queryStmt) {
		if(!$this->db) {
			$this->connect();
		}
        $query = $this->db->prepare($queryStmt);
        if(!$query) {
			$this->throwError();
        }

		return $query;
    }

	public function throwError() {
		$error = $this->error();
		$errno = $this->errno();
		throw new \Exception($error, $errno);
	}

	public function escape($string) {
		if(!$this->db) {
			$this->connect();
		}
        return $this->db->escape($string);
    }

    public function getInsertId() {
		if($this->db) {
			return $this->db->insert_id;
		}
    }

    public function error() {
		if($this->db) {
			return $this->db->error;
		}
    }

    public function errno() {
		if($this->db) {
			return $this->db->errno;
		}
    }

    protected function buildBodyQuery($data, array $availableFields, $type="update") {
        $queryBody = "";
		foreach ($data as $fieldname => $value) {
			if (array_key_exists($fieldname, $availableFields)) {
				if ($type == "search" && empty($value)) {
					continue;
				}

				if ($queryBody && $type == "update") {
					$queryBody .= ", ";
				} else if ($queryBody && $type == "search") {
					$queryBody .= " AND ";
				}
				switch ($availableFields[$fieldname]) {
					case 'i':
						$queryBody .= $fieldname . "=" . intval($value);
						break;
					case 'd':
					case 'f':
						$queryBody .= $fieldname . "=" . floatval($value);
						break;
					default:
						$queryBody .= $fieldname . "='" . $this->escape($value) . "'";
						break;
				}
			}
		}

		return $queryBody;
	}

	protected function getTableFields($tableName, $skipFields=array()) {
		$query = "SELECT column_name, data_type  FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name='".$this->escape($tableName)."'";
		$stmt = $this->query($query);

		$tableFields = array();
		while($row = $stmt->fetch_assoc()) {
			$columnName = $row['column_name'];
			if(in_array($columnName, $skipFields)) {
				continue;
			}

			$dataType = $row['data_type'];
			if($this->isDecimalNumber($dataType)) {
				$dataType = "int";
			}

			switch($dataType) {
				case 'int':
					$tableFields[$columnName] = "i";
					break;
				case 'decimal':
				case 'float':
					$tableFields[$columnName] = "d";
					break;
				default:
					$tableFields[$columnName] = "s";
					break;
			}
		}
		$stmt->close();

		return $tableFields;
    }

	private function isDecimalNumber($data) {
		if(preg_match("/int/", $data)) {
			return true;
		}

		return false;
	}
} // End Database class