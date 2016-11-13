<?php namespace axxapy\DB\Results;

use axxapy\DB\DBResultInterface;

class MySQLDBResult implements DBResultInterface {
	private $insert_id;

	/** @var \mysqli_result */
	private $result;

	public function __construct(\mysqli_result $result, $insert_id = -1) {
		$this->insert_id = $insert_id;
		$this->result    = $result;
		//$result->free_result();
	}

	public function getRowsCount() {
		return $this->result->num_rows;
	}

	public function getInsertId() {
		return $this->insert_id;
	}

	public function fetchAssoc() {
		return $this->result->fetch_assoc();
	}

	public function fetchAll($base_key = null) {
		$res = $this->result->fetch_all(MYSQLI_ASSOC);
		if (!$base_key) return $res;
		$res1 = [];
		foreach ($res as $value) {
			$res1[$value[$base_key]] = $value;
		}
		return $res1;
	}

	public function __destruct() {
		//$this->stmt->close();//fatal here
		if ($this->result) {
			$this->result->free();
		}
	}

	public function isSuccessful() {
		return !empty($this->result);
	}
}
