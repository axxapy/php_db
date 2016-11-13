<?php namespace axxapy\DB\SQLBuilders;

use axxapy\DB\DBResultInterface;
use axxapy\DB\Drivers\DBDriver;

abstract class SQLBuilder {
	const OPERATION_SELECT  = 1;
	const OPERATION_INSERT  = 2;
	const OPERATION_UPDATE  = 3;
	const OPERATION_REPLACE = 4;
	const OPERATION_DELETE  = 5;

	const JOIN_CROSS = 1;
	const JOIN_LEFT  = 2;
	const JOIN_RIGHT = 3;
	const JOIN_INNER = 4;
	const JOIN_OUTER = 5;

	protected $operation;
	protected $operation_mode;
	protected $what;
	protected $tables;
	protected $where;
	protected $order_by      = [];
	protected $having;
	protected $limit;
	protected $offset;
	protected $join          = [];
	protected $union         = [];
	protected $values        = [];
	protected $values_where  = [];
	protected $values_having = [];
	protected $table;

	/* @var DBDriver */
	private $DB;

	public function __construct(DBDriver $db) {
		$this->DB = $db;
	}

	/**
	 * Creates select request. Example: select {$what} from ... {$mode}
	 * Mode depends on DB driver. For instance, with mysql it could be FOR UPDATE.
	 *
	 * @param string|array $what
	 * @param int          $mode
	 *
	 * @return $this
	 */
	public function Select($what, $mode = null) {
		$this->operation      = self::OPERATION_SELECT;
		$this->operation_mode = $mode;
		$this->what           = (array)$what;
		return $this;
	}

	/**
	 * Sets table/list of tables for select request
	 *
	 * @param string|array $tables
	 *
	 * @return $this
	 */
	public function From($tables) {
		$this->tables = (array)$tables;
		return $this;
	}

	/**
	 * Builds WHERE part of query
	 *
	 * @param string $cond   Where part of query: ... from ... where {$cond}. Example: id = :id AND order > :order
	 * @param array  $values ['id' => 1, 'order' => 2]
	 *
	 * @return $this
	 */
	public function Where($cond, array $values = []) {
		$this->where        = (string)$cond;
		$this->values_where = $values;
		return $this;
	}

	/**
	 * Sets order priority: ... ORDER BY {field} {$modifier}
	 * To add more fields to order list, call this method several times.
	 *
	 * @param string|array $field
	 * @param string       $modifier
	 *
	 * @return $this
	 */
	public function OrderBy($field, $modifier = null) {
		$this->order_by[$field] = $modifier;
		return $this;
	}

	/**
	 * Sets HAVING part of request: ...ORDER BY id DESC HAVING {$cond}
	 *
	 * @param string $cond   Example: date > :date
	 * @param array  $values Example: ['date' => '2012-04-15']
	 *
	 * @return $this
	 */
	public function Having($cond, array $values = []) {
		$this->having        = (string)$cond;
		$this->values_having = $values;
		return $this;
	}

	/**
	 * Sets LIMIT for sql query
	 *
	 * @param int $limit
	 *
	 * @return $this
	 */
	public function Limit($limit) {
		$this->limit = (int)$limit;
		return $this;
	}

	/**
	 * Sets OFFSET for sql query
	 *
	 * @param int $offset
	 *
	 * @return $this
	 */
	public function Offset($offset) {
		$this->offset = (int)$offset;
		return $this;
	}

	/**
	 * Joins {$table} with {$on} condition
	 *
	 * @param string $table
	 * @param string $on
	 *
	 * @return $this
	 */
	public function Join($table, $on) {
		$this->join[] = ['type' => self::JOIN_CROSS, 'table' => (array)$table, 'on' => (string)$on];
		return $this;
	}

	/**
	 * Performs Left join with {$table} by {$on} condition
	 *
	 * @param string $table
	 * @param string $on
	 *
	 * @return $this
	 */
	public function LeftJoin($table, $on) {
		$this->join[] = ['type' => self::JOIN_LEFT, 'table' => (array)$table, 'on' => (string)$on];
		return $this;
	}

	/**
	 * Performs Right join with {$table} by {$on} condition
	 *
	 * @param string $table
	 * @param string $on
	 *
	 * @return $this
	 */
	public function RightJoin($table, $on) {
		$this->join[] = ['type' => self::JOIN_RIGHT, 'table' => (array)$table, 'on' => (string)$on];
		return $this;
	}

	/**
	 * Performs Inner join with {$table} by {$on} condition
	 *
	 * @param string $table
	 * @param string $on
	 *
	 * @return $this
	 */
	public function InnerJoin($table, $on) {
		$this->join[] = ['type' => self::JOIN_INNER, 'table' => (array)$table, 'on' => (string)$on];
		return $this;
	}

	/**
	 * Performs Outer join with {$table} by {$on} condition
	 *
	 * @param string $table
	 * @param string $on
	 *
	 * @return $this
	 */
	public function OuterJoin($table, $on) {
		$this->join[] = ['type' => self::JOIN_OUTER, 'table' => (array)$table, 'on' => (string)$on];
		return $this;
	}

	/**
	 * Unions two tables
	 *
	 * @param string $sql
	 * @param bool   $all - true: all results, false: only unique
	 *
	 * @return $this
	 */
	public function Union($sql, $all = false) {
		$this->union = ['all' => (bool)$all, 'sql' => (string)$sql];
		return $this;
	}

	/**
	 * Builds insert query
	 *
	 * @param string $table_into insert into >>TABLE<< ...
	 * @param array  $values     ['name1' => 'val', ...]
	 *
	 * @return $this
	 */
	public function Insert($table_into, array $values) {
		$this->operation = self::OPERATION_INSERT;
		$this->values    = $values;
		$this->table     = (string)$table_into;
		return $this;
	}

	/**
	 * Builds REPLACE query
	 *
	 * @param string $table_where replace into >>TABLE<<
	 * @param array  $values      ['name1' => 'val1', ...]
	 *
	 * @return $this
	 */
	public function Replace($table_where, array $values) {
		$this->operation = self::OPERATION_REPLACE;
		$this->values    = $values;
		$this->table     = (string)$table_where;
		return $this;
	}

	/**
	 * Builds UPDATE query
	 *
	 * @param string $table  Table name
	 * @param array  $values ['key1' => 'val1', ...]
	 *
	 * @return $this
	 */
	public function Update($table, array $values) {
		$this->operation = self::OPERATION_UPDATE;
		$this->table     = (string)$table;
		$this->values    = $values;
		return $this;
	}

	/**
	 * Builds DELETE query
	 *
	 * @param string $from_table Table name
	 *
	 * @return $this
	 */
	public function Delete($from_table) {
		$this->operation = self::OPERATION_DELETE;
		$this->table     = (string)$from_table;
		return $this;
	}

	public function __toString() {
		return (string)$this->getQuery();
	}

	/**
	 * @return DBResultInterface
	 */
	public function Execute() {
		return $this->DB->query($this->getQuery(), $this->getValues());
	}

	public function getValues() {
		return array_merge($this->values, $this->values_where, $this->values_having);
	}

	abstract public function getQuery();
}


