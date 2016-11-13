<?php namespace axxapy\DB\Drivers;

use axxapy\DB\DBException;
use axxapy\DB\SQLBuilders\SQLBuilder;
use axxapy\DB\Results\RawDBResult;
use axxapy\DB\DBResultInterface;

/**
 * Abstract MySQL DB driver class/interface
 *
 * Class DB_Driver
 *
 * @package AF
 */
abstract class DBDriver {
	/**
	 * Driver configuration
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * Requires config from section "db" in standard config array.
	 *
	 * @param array               $config # ['host':'', 'port':0, 'user':'', 'password':'']
	 */
	public function __construct(array $config) {
		$config += [
			'host' => null,
			'port' => null,
			'user' => null,
			'pass' => null,
			'db'   => null,
		];
		$this->config = $config;
		//$this->query("SET NAMES {$this->encode}");
		//$this->query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
	}

	public function __destruct() {
		$this->closeConnection();
	}

	abstract public function closeConnection();

	/**
	 * Executes SQL statement and returns result.
	 *
	 * @param string $q      SQL statement body
	 * @param array  $params SQL statement parameters
	 *
	 * @throws DBException    cannot execute SQL
	 *
	 * @return DBResultInterface
	 */
	abstract public function query($q, $params = []);

	/**
	 * Executes raw SQL query without statements or params
	 *
	 * @param string $q
	 *
	 * @throws DBException
	 *
	 * @return DBResultInterface | RawDBResult
	 */
	abstract public function queryRaw($q);

	/**
	 * Starts transaction
	 *
	 * @return bool
	 */
	abstract public function beginTransaction();

	/**
	 * Commits transaction
	 *
	 * @return bool
	 */
	abstract public function commitTransaction();

	/**
	 * Rolls back transaction
	 *
	 * @return bool
	 */
	abstract public function rollbackTransaction();

	/**
	 * Creates SQLBuilder instance
	 *
	 * @return SQLBuilder
	 */
	abstract public function buildQuery();
}
