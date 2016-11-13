<?php namespace axxapy\DB\Drivers;

use axxapy\DB\DBException;
use axxapy\DB\SQLBuilders\MySQLSQLBuilder;
use axxapy\DB\Results\MySQLDBResult;
use axxapy\DB\Results\RawDBResult;
use axxapy\DB\DBResultInterface;
use axxapy\Debug\Log;
use axxapy\Debug\Timer;
use mysqli_sql_exception;
use mysqli_stmt;

/**
 * Simple MySQL DB Driver
 *
 * Class DB_Driver_MySQL
 *
 * @package AF
 */
class MySQLDBDriver extends DBDriver {
	const TAG = 'MYSQL';

	/**
	 * Don't use it directly! use $this->getConnection()!
	 *
	 * @var \mysqli
	 */
	private $dbh;

	/**
	 * Cached statements.
	 * ['sql' => mysqli_stmt, 'sql2' => mysqli_stmt, ...]
	 *
	 * @var array
	 */
	private $cached_statements = [];

	private $stmt_cache_enabled = false;

	private $transaction_counter = 0;

	protected function getConnection() {
		if (!is_null($this->dbh)) {
			try {
				$this->dbh->ping();
				return $this->dbh;
			} catch (mysqli_sql_exception $ex) {
				if ($ex->getCode() != 2006) {//mysql server gone away
					throw $ex;
				}
				if ($this->transaction_counter !== 0) {
					throw new DBException('Transaction counter not zero whine connection was lost. Counter value: ' . var_export($this->transaction_counter, true));
				}
				Log::v(self::TAG, 'lost connection (2006), reconnecting...');
			}
		}

		$Timer = (new Timer('mysql:connect'))
			->addData(['config' => $this->config])
			->start();

		//Force mysqli to throw exceptions
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		try {
			/** @var \mysqli $dbh */
			$dbh = mysqli_init();
			$dbh->real_connect(
				$this->config['host'],
				$this->config['user'],
				$this->config['pass'],
				empty($this->config['db']) ? '' : $this->config['db'],
				empty($this->config['port']) ? ini_get("mysqli.default_port") : $this->config['port']
			);
			$dbh->set_charset('utf8');
		} catch (mysqli_sql_exception $e) {
			$Timer->stopWithFail();
			throw (new DBException($e->getMessage(), DBException::CODE_CONNECTION_FAILED, $e))
				->setDBCode($e->getCode());
			//throw Exception_DB::cannotConnect(mysqli_connect_error())->setDBCode(mysqli_connect_errno());
		}

		$Timer->stopWithSuccess();

		$this->dbh = $dbh;
		return $this->dbh;
	}

	private function createStatement($sql, $cache = false) {
		if (isset($this->cached_statements[$sql]) && $this->cached_statements[$sql] instanceof mysqli_stmt) {
			return $this->cached_statements[$sql];
		}

		$Timer = (new Timer('mysql:create_stmt'))->addData(['sql' => $sql, 'cache' => $cache])->start();

		//@ - is to disable uncatchable warnings. mysqli still will throw an exception
		$stmt = @$this->getConnection()->prepare($sql);

		if ($cache) {
			$this->cached_statements[$sql] = $stmt;
		}

		$Timer->stopWithSuccess();

		return $stmt;
	}

	/**
	 * Executes sql query and returns result.
	 *
	 * @param string $q
	 * @param array  $params
	 *
	 * @return \axxapy\DB\Results\MySQLDBResult
	 * @throws \axxapy\DB\DBException
	 */
	public function query($q, $params = []) {
		$Timer = (new Timer('mysql:query'))
			->addData(['sql' => $q, 'type' => 'stmt', 'params' => $params])
			->start();

		Log::v(self::TAG, 'query_stmt: [' . ((string)$q) . '] values [' . json_encode($params) . ']');

		//support pdo-like :name expressions in sql
		if (preg_match_all('#:(([a-z]+)[\d_a-z]*)#i', $q, $matches)) {
			//sort params array to match replaced sql
			$p = [];
			foreach ($matches[1] as $key) {
				if (!array_key_exists($key, $params)) {
					$Timer->stopWithFail();
					throw new DBException('value for ":' . $key . '" not found');
				}
				if (is_array($params[$key])) {//to make statements LIKE (:name) work we need to replace :name to number of ? equal to number of values
					$p = array_merge($p, $params[$key]);
					$q = preg_replace("#:{$key}([^a-z\d])#", implode(', ', array_fill(0, count($params[$key]), '?')) . '\1', $q);
				} else {
					$p[] = $params[$key];
				}
			}
			$params = $p;

			//replace :name to ? in sql
			$q = preg_replace('#(:([a-z]+)[\d_a-z]*)#i', '?', $q);
		}

		$params_types = '';
		array_walk($params, function ($val) use (&$params_types) {
			if (is_string($val)) {
				$params_types .= 's';
			} elseif (is_int($val)) {
				$params_types .= 'i';
			} elseif (is_double($val) || is_float($val)) {
				$params_types .= 'd';
			} else {
				//@todo: throw exception, invalid param. What abt boolean?
				$params_types .= 'b'; //blob
			}
		});

		try {
			$stmt = $this->createStatement((string)$q);
			if (!empty($params)) {
				$refValues = function ($arr) { //Reference is required for PHP 5.3+
					$refs = [];
					foreach ($arr as $key => $value)
						$refs[$key] = &$arr[$key];
					return $refs;
				};
				call_user_func_array([$stmt, 'bind_param'], $refValues(array_merge([$params_types], $params)));
				if ($stmt->errno) {
					throw new mysqli_sql_exception($stmt->error, $stmt->errno);
				}
			}
			$stmt->execute();
			//$stmt->store_result(); //буферизирием все кортежи

			$result = $stmt->get_result();
			if ($result instanceof \mysqli_result) {
				// SELECT QUERIES
				$result = new MySQLDBResult($result, $stmt->insert_id);
				Log::v(self::TAG, 'result: success. rows: ' . $result->getRowsCount());
			} else {
				// INSERT, UPDATE, or DELETE QUERIES
				$result = new RawDBResult($result, $stmt->insert_id, $stmt->affected_rows);
				Log::v(self::TAG, 'result: success. rows: ' . $stmt->affected_rows) ;
			}

			$Timer->stopWithSuccess();
			return $result;
		} catch (mysqli_sql_exception $e) {
			Log::v(self::TAG, 'result: error ' . $e->getMessage() .', code: '.$e->getCode());

			$Timer->stopWithFail();
			throw (new DBException($e->getMessage(), DBException::CODE_QUERY_ERROR, $e))
				->setDBCode($e->getCode())->setSql($q)->setSqlParams($params);
		}
	}

	/**
	 * Executes raw SQL query without statements or params
	 *
	 * @param string $q
	 *
	 * @throws \axxapy\DB\DBException
	 *
	 * @return DBResultInterface | RawDBResult
	 */
	public function queryRaw($q) {
		$Timer = (new Timer('mysql:query'))
			->addData(['sql' => $q, 'type' => 'raw'])
			->start();

		Log::v(self::TAG, 'query_raw: ' . $q);

		try {
			$result = $this->getConnection()->query((string)$q);
			$result = $result instanceof \mysqli_result ? new MySQLDBResult($result) : new RawDBResult($result);

			$Timer->stopWithSuccess();

			return $result;
		} catch (mysqli_sql_exception $e) {
			$Timer->stopWithFail();
			throw (new DBException($e->getMessage(), DBException::CODE_QUERY_ERROR, $e))
				->setDBCode($e->getCode())->setSql($q);
		}
	}

	/**
	 * Starts MySQL transaction
	 *
	 * @return bool
	 */
	public function beginTransaction() {
		if($this->transaction_counter != 0){
			$this->transaction_counter++;
			return true;
		}
		if ($this->queryRaw('START TRANSACTION')->isSuccessful()) {
			$this->transaction_counter++;
			return true;
		}
		return false;
	}

	/**
	 * Commits MySQL transaction
	 *
	 * @return bool
	 */
	public function commitTransaction() {
		if($this->transaction_counter > 1){
			$this->transaction_counter--;
			return true;
		}

		if ($this->queryRaw('COMMIT')->isSuccessful()) {
			$this->transaction_counter = 0;
			return true;
		}
		return false;
	}

	/**
	 * Rolls back MySQL transaction
	 *
	 * @return bool
	 */
	public function rollbackTransaction() {
		if($this->transaction_counter > 1){
			$this->transaction_counter--;
			return true;
		}

		if ($this->queryRaw('ROLLBACK')->isSuccessful()) {
			$this->transaction_counter = 0;
			return true;
		}
		return false;
	}

	/**
	 * Returns whether mysql statements caching enabled or not
	 *
	 * @return boolean
	 */
	public function isStmtCacheEnabled() {
		return $this->stmt_cache_enabled;
	}

	/**
	 * Enables or disables MySQL statements caching
	 *
	 * @param boolean $stmt_cache_enabled
	 *
	 * @return $this
	 */
	public function setStmtCacheEnabled($stmt_cache_enabled) {
		$this->stmt_cache_enabled = (bool)$stmt_cache_enabled;
		return $this;
	}

	/**
	 * Creates SQLBuilder instance
	 *
	 * @return \axxapy\DB\SQLBuilders\MySQLSQLBuilder
	 */
	public function buildQuery() {
		return new MySQLSQLBuilder($this);
	}

	public function closeConnection() {
		if ($this->transaction_counter > 0) {
			trigger_error('WARNING! ' . $this->transaction_counter . ' UNCLOSED TRANSACTIONS DETECTED!', E_USER_WARNING);
		}
		$this->transaction_counter = 0;
		if (!$this->dbh) return;
		$this->dbh->close();
		$this->dbh = null;
	}
}
