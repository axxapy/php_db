<?php namespace axxapy\DB\SQLBuilders;

use axxapy\DB\SQLBuilderException;

class MySQLSQLBuilder extends SQLBuilder {
	const MODE_FOR_UPDATE         = 1;
	const MODE_LOCK_IN_SHARE_MODE = 2;

	static private $select_modes = [
		self::MODE_FOR_UPDATE         => ' FOR UPDATE',
		self::MODE_LOCK_IN_SHARE_MODE => ' LOCK IN SHARE MODE',
	];

	static private $join_types = [
		self::JOIN_CROSS => 'CROSS',
		self::JOIN_OUTER => 'OUTER',
		self::JOIN_INNER => 'INNER',
		self::JOIN_RIGHT => 'RIGHT',
		self::JOIN_LEFT  => 'LEFT',
	];

	/**
	 * Formats names aliases
	 * Ex: select COUNT(*) AS cnt from table AS t where t.batch_id = 10 group by t.type having cnt > 20
	 * 'cnt' and 't' are aliases in this example
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	private function format_as(array $array) {
		$res = [];
		foreach ($array as $key => $val) {
			if (!is_numeric($key) && preg_match('#^[a-z_\d\.\(\)\s]+$#i', $key)) {
				$res[] = $this->format_complex_name($key) . ' ' . $val;
			} else {
				$res[] = $this->format_complex_name($val);
			}
		}
		return implode(', ', $res);
	}

	/**
	 * Names like dbname.table_name must be formatted as `dbname`.`table_name` instead of `dbname.table_name`
	 *
	 * @param $name
	 *
	 * @return string
	 */
	private function format_complex_name($name) {
		$name = trim($name);
		if ($name == '*') return $name; //skip *
		if (strpos($name, '!') === 0) return substr($name, 1);//use ! in start of statement to avoid escaping of functions. ex: !SUM(var)
		if (strpos($name, '(') !== false) {//FUNCTION(field) => FUNCTION(`field`)
			return preg_replace_callback('#([a-z]+\()([^\)]+)(\))#i', function($m) {
				return $m[1].$this->format_complex_name($m[2]).$m[3];
			}, $name);
		}
		if (strpos(strtoupper($name), 'DISTINCT ') === 0) {//DISTINCT table.field => DISTINCT `table`.`field`
			$name = explode(' ', trim($name), 2);
			return $name[0].' '.$this->format_complex_name($name[1]);
		}
		$names = array_map('trim', explode('.', $name, 2));
		if (count($names) == 2 && $names[1] == '*') {
			return "`{$names[0]}`.*";
		}
		return '`'.implode('`.`', $names).'`';
	}

	private function getTableFormatted() {
		return $this->format_complex_name($this->table);
	}

	public function getQuery() {
		switch ($this->operation) {
			case self::OPERATION_SELECT:
				$q = 'SELECT ';
				$q .= $this->format_as($this->what);
				$q .= ' FROM ';
				$q .= $this->format_as($this->tables);
				foreach ($this->join as $join) {
					$q .= ' ' . self::$join_types[$join['type']] . ' JOIN ' . $this->format_as((array)$join['table']) . ' ON ' . $join['on'];
				}
				if ($this->where) {
					$q .= ' WHERE ';
					$q .= $this->where;
				}
				if ($this->having) {
					$q .= ' HAVING ';
					$q .= $this->having;
				}
				if ($this->order_by) {
					$q .= ' ORDER BY ';
					$order = [];
					foreach ($this->order_by as $field => $mod) {
						$order[] = $mod ? $field . ' ' . $mod : $field;
					}
					$q .= implode(', ', $order);
				}
				if ($this->limit) {
					$q .= ' LIMIT ';
					$q .= $this->offset ? $this->offset . ', ' . $this->limit : $this->limit;
				}
				if (isset(self::$select_modes[$this->operation_mode])) {
					$q .= self::$select_modes[$this->operation_mode];
				}
				foreach ($this->union as $union) {//@todo: statements
					$q .= $union['all'] ? ' UNION ALL ' : ' UNION ' . (string)$union['sql'];
				}
				return $q;

			case self::OPERATION_INSERT:
				$q = 'INSERT ';
				$q .= 'INTO ' . $this->getTableFormatted();
				if ($this->values) {
					$q .= ' (`' . implode('`, `', array_keys($this->values)) . '`)';
					$q .= ' VALUES (:' . implode(', :', array_keys($this->values)) . ')';
				} else {
					$q .= ' () VALUES ()';
				}
				return $q;

			case self::OPERATION_REPLACE:
				$q = 'REPLACE ';
				$q .= 'INTO ' . $this->getTableFormatted();
				if ($this->values) {
					$q .= ' (`' . implode('`, `', array_keys($this->values)) . '`)';
					$q .= ' VALUES (:' . implode(', :', array_keys($this->values)) . ')';
				} else {
					$q .= ' () VALUES ()';
				}
				return $q;

			case self::OPERATION_UPDATE:
				$q      = 'UPDATE ' . $this->getTableFormatted() . ' SET ';
				$update = [];
				foreach ($this->values as $key => $val) {
					$update[] = "`{$key}` = :{$key}";
				}
				$q .= implode(', ', $update);
				if (empty($this->where)) {
					throw new SQLBuilderException('operation UPDATE without WHERE is forbidden');
				}
				$q .= ' WHERE ' . $this->where;
				return $q;

			case self::OPERATION_DELETE:
				$q = 'DELETE ';
				$q .= 'FROM ' . $this->getTableFormatted();
				if (empty($this->where)) {
					throw new SQLBuilderException('operation DELETE without WHERE is forbidden');
				}
				$q .= ' WHERE ' . $this->where;
				return $q;
		}

		return null;
	}
}