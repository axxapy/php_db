<?php namespace axxapy\DB;

use Exception;

class DBException extends Exception {
	const CODE_CONNECTION_FAILED = 1;
	const CODE_QUERY_ERROR       = 2;

	const ER_NO_SUCH_TABLE = 1146;

	private $db_code    = 0;
	private $sql        = '';
	private $sql_params = [];

	/**
	 * @return int
	 */
	public function getDBCode() {
		return $this->db_code;
	}

	/**
	 * @param int $db_code
	 *
	 * @return $this
	 */
	public function setDBCode($db_code) {
		$this->db_code = $db_code;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSql() {
		return $this->sql;
	}

	/**
	 * @param string $sql
	 *
	 * @return $this
	 */
	public function setSql($sql) {
		$this->sql = $sql;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getSqlParams() {
		return $this->sql_params;
	}

	/**
	 * @param array $sql_params
	 *
	 * @return $this
	 */
	public function setSqlParams($sql_params) {
		$this->sql_params = $sql_params;
		return $this;
	}

	/**
	 * Allows to include some debug info in output when executed in devel environment
	 *
	 * @return string
	 */
	public function getDebugData() {
		$debug = [];
		if ($this->sql) {
			$debug['SQL'] = $this->sql;
		}

		if ($this->sql_params) {
			$debug['PARAMS'] = $this->sql_params;
		}

		return var_export($debug, true);
	}
}
