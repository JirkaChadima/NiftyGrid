<?php

namespace NiftyGrid\DataSource;

use Nette,
	DibiFluent;
use NiftyGrid\FilterCondition;

/**
 * DibiFluent datasource for Nifty's grid.
 *
 * <code>
 * $db = new DibiConnection($dbConfig);
 * $fluent = $db->select('id, name, surname')->from('employee')->where('is_active = %b', TRUE);
 * $dataSource = new DibiFluentDataSource($fluent, 'id');
 * </code>
 *
 * <code>
 * $dataSource = new DibiFluentDataSource(dibi::select('*')->from('employee'), 'id');
 * </code>
 *
 * @author  Miloslav Hůla
 * @version 1.2
 * @licence LGPL
 * @see     https://github.com/Niftyx/NiftyGrid
 */
class DibiFluentDataSource extends Nette\Object implements IDataSource {

	/** @var DibiFluent */
	private $fluent;

	/** @var string  Primary key column name */
	private $pKeyColumn;

	/** @var int  LIMIT clause value */
	private $limit;

	/** @var int  OFFSET clause value */
	private $offset;
	private $cacheResult;

	/**
	 * @param DibiFluent
	 * @param string  Primary key column name
	 */
	public function __construct(DibiFluent $fluent, $pKeyColumn) {
		$this->fluent = clone $fluent;
		$this->pKeyColumn = $pKeyColumn;
	}

	/* --- NiftyGrid\IDataSource implementation ----------------------------- */

	public function getData() {
		return $this->fluent->getConnection()->query('%SQL %lmt %ofs', (string) $this->fluent, $this->limit, $this->offset)->fetchAssoc($this->pKeyColumn);
	}

	public function getPrimaryKey() {
		return $this->pKeyColumn;
	}

	public function getCount($column = '*') {
		// @see http://forum.dibiphp.com/cs/792-velky-problem-s-dibidatasource-a-mysql

		$fluent = clone $this->fluent;
		$fluent->removeClause('SELECT')->removeClause('ORDER BY');

		$modifiers = \DibiFluent::$modifiers;
		\DibiFluent::$modifiers['SELECT'] = '%sql';
		$fluent->select(array('COUNT(%n) AS [count]', $column));
		\DibiFluent::$modifiers = $modifiers;

		if (strpos((string) $fluent, 'GROUP BY') === FALSE) {
			return $fluent->fetchSingle();
		}

		try {
			return $fluent->execute()->count();
		} catch (\DibiNotSupportedException $e) {
			
		}

		$count = 0;
		foreach ($fluent as $row) {
			$count += 1;
		}

		return $count;
	}

	public function getSelectedRowsCount() {
		return $this->getCount();
	}

	public function orderData($by, $way) {
		$this->fluent->orderBy(array($by => $way));
	}

	public function limitData($limit, $offset) {
		$this->limit = $limit;
		$this->offset = $offset;
	}

	public function filterData(array $filters) {
		static $typeToModifier = array(
			FilterCondition::NUMERIC => '%f',
			FilterCondition::DATE => '%d',
		);

		$where = array();
		foreach ($filters as $filter) {
			$cond = array();

			// Column
			if (isset($filter['columnFunction'])) {
				$cond[] = $filter['columnFunction'] . '(';
			}

			$cond[] = '%n';
			$cond[] = $filter['column'];

			if (isset($filter['columnFunction'])) {
				$cond[] = ')';
			}


			// Operator
			$cond[] = trim(strtoupper(str_replace('?', '', $filter['cond'])));


			// Value
			if (isset($filter['valueFunction'])) {
				$cond[] = $filter['valueFunction'] . '(';
			}

			$cond[] = isset($typeToModifier[$filter['datatype']]) ? $typeToModifier[$filter['datatype']] : '%s';
			$cond[] = $filter['value'];

			if (isset($filter['valueFunction'])) {
				$cond[] = ')';
			}

			if ($filter['type'] === FilterCondition::WHERE) {
				$where[] = $cond;
			} else {
				trigger_error("Unknown filter type '$filter[type]'.", E_USER_NOTICE);
			}
		}

		if (count($where)) {
			$this->fluent->where($where);
		}
	}

	public function getColumns() {
		if (!$this->cacheResult) {
			$this->cacheResult = $this->fluent->execute();
		}
		return $this->cacheResult->getInfo()->getColumns();
	}

	public function update($table, array $data, $primaryKeyValue) {
		if (empty($primaryKeyValue)) {
			throw new DataSourceException("Missing primary key value!");
		}
		try {
			$this->fluent->getConnection()
					->update($table, $data)
					->where('%n = %s', $this->getPrimaryKey(), $primaryKeyValue)
					->execute();
		} catch (\DibiDriverException $e) {
			throw new DataSourceException($e);
		}
	}

	public function insert($table, array $data) {
		try {
			$this->fluent->getConnection()
					->insert($table, $data)
					->execute();
		} catch (\DibiDriverException $e) {
			throw new DataSourceException($e);
		}
	}

	public function delete($table, $primaryKeyValue) {
		try {
			$this->fluent->getConnection()
					->delete($table)
					->where('%n = %s', $this->getPrimaryKey(), $primaryKeyValue)
					->execute();
		} catch (\DibiDriverException $e) {
			throw new DataSourceException($e);
		}
	}

}
