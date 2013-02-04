<?php

/**
 * NiftyGrid - DataGrid for Nette
 *
 * @author	Jakub Holub
 * @copyright	Copyright (c) 2012 Jakub Holub
 * @license     New BSD Licence
 * @link        http://addons.nette.org/cs/niftygrid
 */

namespace NiftyGrid\DataSource;

use NiftyGrid\FilterCondition;
use Nette\Database\Table\Selection;

class NDataSource implements IDataSource {

	private $table;

	public function __construct(Selection $table) {
		$this->table = $table;
	}

	public function getData() {
		return $this->table;
	}

	public function getPrimaryKey() {
		return $this->table->getPrimary();
	}

	public function getCount($column = "*") {
		return $this->table->count($column);
	}

	public function getSelectedRowsCount() {
		return $this->table->count();
	}

	public function orderData($by, $way) {
		$this->table->order($by . " " . $way);
	}

	public function limitData($limit, $offset) {
		$this->table->limit($limit, $offset);
	}

	public function filterData(array $filters) {
		foreach ($filters as $filter) {
			if ($filter["type"] == FilterCondition::WHERE) {
				$column = $filter["column"];
				$value = $filter["value"];
				if (!empty($filter["columnFunction"])) {
					$column = $filter["columnFunction"] . "(" . $filter["column"] . ")";
				}
				$column .= $filter["cond"];
				if (!empty($filter["valueFunction"])) {
					$column .= $filter["valueFunction"] . "(?)";
				}
				$this->table->where($column, $value);
			}
		}
	}

	public function delete($table, $primaryKeyValue) {
		throw new \Nette\NotImplementedException();
	}

	public function getColumns() {
		throw new \Nette\NotImplementedException();
	}

	public function insert($table, array $data) {
		throw new \Nette\NotImplementedException();
	}

	public function update($table, array $data, $primaryKeyValue) {
		throw new \Nette\NotImplementedException();
	}

}
