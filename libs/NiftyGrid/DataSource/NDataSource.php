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
		return $this->table->get($primaryKeyValue)->delete();
	}

	public function getColumns() {
		$result = array();
		foreach($this->table->getConnection()->getSupplementalDriver()->getColumns($this->table->getName()) as $col) {
			$result[] = new NColumnInfo($col);
		}
		return $result;
	}

	public function insert($table, array $data) {
		return $this->table->insert($data);
	}

	public function update($table, array $data, $primaryKeyValue) {
		return $this->table->get($primaryKeyValue)->update($data);
	}

}
