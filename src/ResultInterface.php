<?php namespace axxapy\DB;

interface DBResultInterface {
	public function getRowsCount();
	public function getInsertId();
	public function fetchAssoc();
	public function fetchAll($base_key = null);
	public function isSuccessful();
}