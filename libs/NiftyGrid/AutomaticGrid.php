<?php

namespace NiftyGrid;

class AutomaticGrid extends \NiftyGrid\Grid {
	// default options

	const DEFAULT_AUTOCOMPLETE_LIST_LENGTH = 10;

	// options keys
	const KEY = 'key';
	const KEY_ALIAS = 'key_alias';
	const ORDER = 'order';
	const ORDER_DESC = 'order_desc';
	const ORDER_ASC = 'order_asc';
	const EDITABLE = 'editable';
	const FILTERABLE = 'filterable';
	const AUTOCOMPLETE = 'autocomplete';
	const AUTOCOMPLETE_LENGTH = 'autocomplete_length';
	const RENDERER = 'renderer';
	const TABLENAME = 'tablename';
	const ALIAS = 'alias';
	const TYPE = 'type';
	const ENUM = 'enum';

	// types
	const TYPE_NUMERIC = 'i';
	const TYPE_TEXT = 's';
	const TYPE_LONGTEXT = 'ls';
	const TYPE_BOOLEAN = 'b';
	const TYPE_DATE = 'd';
	const TYPE_DATETIME = 'dt';
	const TYPE_TIME = 'tt';
	const TYPE_BINARY = 'bin';
	const TYPE_ENUM = 'e';
	private $types;

	/** @var \DibiFluent */
	protected $fluentSource;
	protected $cacheResult;
	
	// grid options
	protected $editable = false;
	protected $filterable = false;
	protected $autoMode = false;
	protected $defaultUpdateRowCallback = true;
	public $onUpdateRow = array();
	protected $options = array();
	protected $keyColumn;
	protected $keyColumnAlias;
	protected $orderBy;

	/**
	 * Default settings: show all columns, none are filterable, none are editable
	 * 
	 * @param \DibiFluent $fluent
	 * @param array $options
	 */
	public function __construct(\DibiFluent $fluent, array $options = array()) {
		parent::__construct();
		$this->fluentSource = $fluent;
		$this->types = array(self::TYPE_NUMERIC, self::TYPE_TEXT, self::TYPE_LONGTEXT, self::TYPE_BOOLEAN, self::TYPE_DATE, self::TYPE_DATETIME, self::TYPE_TIME, self::TYPE_BINARY, self::TYPE_ENUM);
		$this->options = $options;

		// set key and orderBy
		if (empty($options)) {
			$this->keyColumn = 'id';
			$this->orderBy = 'id asc';
			$this->autoMode = true;
		} else {
			foreach ($options as $col => $attrs) {
				if (!empty($attrs[self::KEY])) {
					$this->keyColumn = $col;
				}
				if (!empty($attrs[self::KEY_ALIAS])) {
					$this->keyColumnAlias = $attrs[self::KEY_ALIAS];
				}
				if (!empty($attrs[self::ORDER])) {
					$this->orderBy = $col;
					$this->orderBy .= ($attrs[self::ORDER_DESC] ? ' desc' : ' asc');
				}
			}
		}
		
	}

	public function enableEditing() {
		$this->editable = true;
		return $this;
	}

	public function enableFiltering() {
		$this->filterable = true;
		return $this;
	}

	public function disableSorting() {
		$this->enableSorting = false;
		return $this;
	}

	public function disableDefaultOnUpdateRowCallback() {
		$this->defaultUpdateRowCallback = false;
		return $this;
	}

	protected function configure($presenter) {
		if (empty($this->keyColumn)) {
			throw new GridException('Missing key column!');
		}
		$this->template->setTranslator($presenter->getTranslator());
		$this->setTranslator($presenter->getTranslator());
		$this->setMessageNoRecords(_('No records'));
		$this->setDataSource(new \NiftyGrid\DataSource\DibiFluentDataSource($this->fluentSource, $this->keyColumn));
		$this->cacheResult = $this->fluentSource->limit(1)->execute();
		$cacheResult = $this->cacheResult;
		
		$columns = $cacheResult->getInfo()->getColumns(); // or use show create table to detect types more precisely
		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn) {
				continue;
			}
			$colOptions = (!empty($this->options[$column->getName()]) ? $this->options[$column->getName()] : array());
			if (!empty($colOptions[self::ALIAS])) {
				$name = $colOptions[self::ALIAS];
			} else {
				$name = \Nette\Utils\Strings::firstUpper($column->getName());
			}
			
			if ($column->getName() === $this->keyColumnAlias) {
				$colName = $this->keyColumnAlias;
			} else {
				$colName = $column->getName();
			}
			$col = $this->addColumn($colName, $name);
			
			if (!empty($colOptions[self::TABLENAME])) {
				$col->setTableName($colOptions[self::TABLENAME] . '.' . $colName);
			}
			
			if (!empty($colOptions[self::RENDERER])) {
				$rndr = $colOptions[self::RENDERER];
				$self = $this;
				$col->setRenderer(function ($row) use ($self, $rndr) {
					return call_user_func($rndr, $row, $self);
				});
			}

			// ++ default actions -- create, edit, delete
			// ++ custom actions -- ??callbacks, class??
		}
		$this->makeEditableColumns($columns, $this['columns']->components);
		$this->makeFilterableColumns($columns, $this['columns']->components);
	}

	protected function makeEditableColumns($columns, $components) {
		if (!$this->editable) {
			return;
		}
		$this->addButton(\NiftyGrid\Grid::ROW_FORM, _("Inline edit"))
				->setClass("inline-edit");
		$this->setRowFormCallback(callback($this, 'handleUpdateRow'));

		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (!$this->autoMode && empty($this->options[$column->getName()])) || (!$this->autoMode && empty($this->options[$column->getName()][self::EDITABLE]))) {
				continue;
			}
			$col = $components[$column->getName()];
			switch ($this->getColumnType($column)) {
				case self::TYPE_BOOLEAN: # boolean
				case \Dibi::BOOL: # boolean
					$col->setBooleanEditable();
					break;
				case self::TYPE_LONGTEXT: # longtext
					$col->setTextEditable(true);
					break;
				case self::TYPE_TEXT: # text
				case self::TYPE_NUMERIC: # text
				case \Dibi::TEXT: # text
				case \Dibi::INTEGER: # numbers
				case \Dibi::FLOAT: # numbers
					$col->setTextEditable();
					break;
				case self::TYPE_DATE: # date
				case \Dibi::DATE: # date
					$col->setDateEditable();
					break;
				case self::TYPE_ENUM: # enum
					$colOptions = (!empty($this->options[$column->getName()]) ? $this->options[$column->getName()] : array() );
					if (!empty($colOptions[self::ENUM])) {
						$col->setSelectEditable($colOptions[self::ENUM]);
					}
					break;
				case self::TYPE_DATETIME: # nothing
				case \Dibi::DATETIME: # nothing
				case self::TYPE_TIME:
				case \Dibi::TIME: # nothing
				case self::TYPE_BINARY: # nothing
				case \Dibi::BINARY: # nothing
			}
		}
	}

	protected function makeFilterableColumns($columns, $components) {
		if (!$this->filterable) {
			return;
		}
		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (!$this->autoMode && empty($this->options[$column->getName()])) || (!$this->autoMode && empty($this->options[$column->getName()][self::FILTERABLE]))) {
				continue;
			}
			$col = $components[$column->getName()];
			switch ($this->getColumnType($column)) {
				case self::TYPE_BOOLEAN: # boolean
				case \Dibi::BOOL: # boolean
					$col->setBooleanFilter();
					break;
				case self::TYPE_TEXT: # text
				case self::TYPE_LONGTEXT: # nothing
				case \Dibi::TEXT: # text
					$col->setTextFilter();
					$colOptions = (!empty($this->options[$column->getName()]) ? $this->options[$column->getName()] : array() );
					if (!empty($colOptions[self::AUTOCOMPLETE])) {
						if (!empty($colOptions[self::AUTOCOMPLETE_LENGTH])) {
							$col->setAutoComplete($colOptions[self::AUTOCOMPLETE_LENGTH]);
						} else {
							$col->setAutoComplete(self::DEFAULT_AUTOCOMPLETE_LIST_LENGTH);
						}
					}
					break;
				case self::TYPE_NUMERIC: # numbers
				case \Dibi::INTEGER: #numbers
				case \Dibi::FLOAT: # numbers
					$col->setNumericFilter();
					break;
				case self::TYPE_DATE: # date
				case \Dibi::DATE: # date
					$col->setDateFilter();
					break;
				case self::TYPE_ENUM: # enum
					$colOptions = (!empty($this->options[$column->getName()]) ? $this->options[$column->getName()] : array() );
					if (!empty($colOptions[self::ENUM])) {
						$col->setSelectFilter($colOptions[self::ENUM], '--');
					}
					break;
				case self::TYPE_DATETIME: # nothing
				case \Dibi::DATETIME: # datetime
				case \Dibi::TIME: # nothing
				case self::TYPE_TIME:
				case \Dibi::BINARY: # nothing
				case self::TYPE_BINARY: # nothing
			}
		}
	}

	public function handleUpdateRow($values) {
		if ($this->defaultUpdateRowCallback) {
			$columns = $this->cacheResult->getColumns();
			$table = $columns[0]->getTableName();
			
			if (!$this->keyColumn) {
				throw new \NiftyGrid\UnknownColumnException('Key column not set!');
			}

			if (!$values[$this->keyColumn]) {
				throw new \NiftyGrid\UnknownColumnException('Key column not found!');
			}

			$id = $values[$this->keyColumn];
			unset($values[$this->keyColumn]);

			foreach($values as $colname => $val) {
				$tableName = $this['columns']->components[$colname]->tableName;
				if(!empty($this['columns']->components[$colname]) && !empty($tableName)) {
					$values[$tableName] = $val;
					unset($values[$colname]);
				}
			}
			
			$this->fluentSource
					->getConnection()
					->update($table, $values)
					->where('%n = %i', $this->keyColumn, $id)
					->execute();
			$this->flashMessage(_('Row was updated.'), 'alert alert-success');
		}

		foreach ($this->onUpdateRow as $callback) {
			call_user_func($callback, $values);
		}
	}

	private function getColumnType($column) {
		if (!empty($this->options[$column->getName()]) &&
				!empty($this->options[$column->getName()][self::TYPE]) &&
				in_array($this->options[$column->getName()][self::TYPE], $this->types)) {
			return $this->options[$column->getName()][self::TYPE];
		} else {
			return $column->getType();
		}
	}

}

