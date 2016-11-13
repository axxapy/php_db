<?php namespace axxapy\DB\Results;

use axxapy\DB\DBResultInterface;

class RawDBResult implements DBResultInterface {
	/** @var bool */
	private $raw_result;

	private $insert_id;
	private $affected_rows;

	/**
	 * @param boolean $raw_result
	 * @param int     $insert_id
	 * @param int     $affected_rows
	 */
	function __construct($raw_result, $insert_id = -1, $affected_rows = 0) {
		$this->raw_result = (bool)$raw_result;
		$this->insert_id = (int)$insert_id;
		$this->affected_rows = $affected_rows;
	}

	public function getRowsCount() {
		return $this->affected_rows;
	}

	public function getInsertId() {
		return $this->insert_id;
	}

	public function fetchAssoc() {
		return [];
	}

	public function fetchAll($base_key = null) {
		return [];
	}

	public function isSuccessful() {
		return $this->raw_result || $this->insert_id >= 0;
	}
}