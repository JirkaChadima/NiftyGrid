<?php

namespace NiftyGrid;

class AutomaticGrid extends \NiftyGrid\Grid {
	// default options
	const DEFAULT_AUTOCOMPLETE_LIST_LENGTH = 10;

	// column options keys
	const KEY = 'key';
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
	protected $columnOptions = array();
	protected $keyColumn;
	protected $orderBy;

	/**
	 * Default settings: show all columns, none are filterable, none are editable
	 * 
	 * @param \DibiFluent $fluent
	 * @param array $columnOptions
	 */
	public function __construct(\DibiFluent $fluent, array $columnOptions = array(), $actionOptions = array()) {
		parent::__construct();
		$this->fluentSource = $fluent;
		$this->types = array(self::TYPE_NUMERIC, self::TYPE_TEXT, self::TYPE_LONGTEXT, self::TYPE_BOOLEAN, self::TYPE_DATE, self::TYPE_DATETIME, self::TYPE_TIME, self::TYPE_BINARY, self::TYPE_ENUM);
		$this->columnOptions = $columnOptions;

		// set key and orderBy
		if (empty($columnOptions)) {
			$this->keyColumn = 'id';
			$this->orderBy = 'id asc';
			$this->autoMode = true;
		} else {
			foreach ($columnOptions as $col => $attrs) {
				if (!empty($attrs[self::KEY])) {
					$this->keyColumn = $col;
				}
				if (!empty($attrs[self::ORDER])) {
					$this->orderBy = $col;
					$this->orderBy .= ($attrs[self::ORDER_DESC] ? ' desc' : ' asc');
				}
			}
		}
	}

	/**
	 * Globally enables editing.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function enableEditing() {
		$this->editable = true;
		return $this;
	}

	/**
	 * Globally enables filtering.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function enableFiltering() {
		$this->filterable = true;
		return $this;
	}

	/**
	 * Globally disables sorting.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function disableSorting() {
		$this->enableSorting = false;
		return $this;
	}

	/**
	 * Disables default update row callback that updates row in the database.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function disableDefaultOnUpdateRowCallback() {
		$this->defaultUpdateRowCallback = false;
		return $this;
	}

	/**
	 * Tries to detect all columns in the datasource and adds them to grid.
	 * To every column a tablename, alias and a renderer might be added.
	 * 
	 * Then all columns are made editable and filterable accordingly to the
	 * column options or by automatic mode.
	 * 
	 * Finally, all actions are added.
	 * 
	 * @param \Nette\Application\UI\Presenter $presenter
	 * @throws GridException
	 */
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
		
		$columns = $cacheResult->getInfo()->getColumns();
		foreach ($columns as $column) {
			$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array());
			if (!empty($colOptions[self::ALIAS])) {
				$name = $colOptions[self::ALIAS];
			} else {
				$name = \Nette\Utils\Strings::firstUpper($column->getName());
			}
			
			$colName = $column->getName();
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
		}
		$this->makeEditableColumns($columns, $this['columns']->components);
		$this->makeFilterableColumns($columns, $this['columns']->components);
		
		// ++ default actions -- create, edit, delete
		// ++ custom actions -- ??callbacks, class??
	}

	/**
	 * If the grid is globally editable and no column options are set, 
	 * all columns except the primary key column are set to be editable.
	 * The editing type is decided by the column type that is either specified
	 * manually or detected by Dibi.
	 * 
	 * Supported edit modes: boolean, long text, text, date and select/enum.
	 * 
	 * @param array of DibiColumnInfo $columns
	 * @param array of \Nette\Application\UI\PresenterComponent $components
	 */
	private function makeEditableColumns($columns, $components) {
		if (!$this->editable) {
			return;
		}
		$this->addButton(\NiftyGrid\Grid::ROW_FORM, _("Inline edit"))
				->setClass("inline-edit");
		$this->setRowFormCallback(callback($this, 'handleUpdateRow'));

		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (!$this->autoMode && empty($this->columnOptions[$column->getName()])) || (!$this->autoMode && empty($this->columnOptions[$column->getName()][self::EDITABLE])) || empty($components[$column->getName()])) {
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
					$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array() );
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

	/**
	 * If the grid is globally filterable and no column options are set,
	 * all columns are made filterable. The filter type is set either manually
	 * in columns config or detected automatically by Dibi.
	 * 
	 * Supported filter types are boolean, text, numericx, date and select/enum.
	 * 
	 * @param array of DibiColumnInfo $columns
	 * @param array of \Nette\Application\UI\PresenterComponent $components
	 */
	private function makeFilterableColumns($columns, $components) {
		if (!$this->filterable) {
			return;
		}
		foreach ($columns as $column) {
			if (!$this->autoMode && empty($this->columnOptions[$column->getName()]) || (!$this->autoMode && empty($this->columnOptions[$column->getName()][self::FILTERABLE])) || empty($components[$column->getName()])) {
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
					$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array() );
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
					$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array() );
					if (!empty($colOptions[self::ENUM])) {
						$col->setSelectFilter($colOptions[self::ENUM], '--');
					}
					break;
				case self::TYPE_DATETIME: # datetime
				case \Dibi::DATETIME: # datetime
				case \Dibi::TIME: # nothing
				case self::TYPE_TIME:
				case \Dibi::BINARY: # nothing
				case self::TYPE_BINARY: # nothing
			}
		}
	}

	/**
	 * Handles inline edit action, there's the deafult action that tries
	 * to save the row in the database. You may turn this action off
	 * by calling AutomaticGrid::disableDefaultOnUpdateRowCallback method
	 * and add more onUpdateRow methods to the appropriate array.
	 * 
	 * @param array $values
	 * @throws \NiftyGrid\UnknownColumnException When key column is not found.
	 */
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
			
			try {
				$this->fluentSource
						->getConnection()
						->update($table, $values)
						->where('%n = %i', $this->keyColumn, $id)
						->execute();
				$this->flashMessage(_('Row was updated.'), 'alert alert-success');
			} catch (\DibiDriverException $e) {
				$this->flashMessage(_('There was an error during the communication with the database: ' . $e->getMessage()), 'alert alert-error');
			}
		}

		foreach ($this->onUpdateRow as $callback) {
			call_user_func($callback, $values);
		}
	}

	/**
	 * Detects column type either from the options or from the DibiColumnInfo
	 * 
	 * @param \DibiColumnInfo $column
	 * @return string column type
	 */
	private function getColumnType($column) {
		if (!empty($this->columnOptions[$column->getName()]) &&
				!empty($this->columnOptions[$column->getName()][self::TYPE]) &&
				in_array($this->columnOptions[$column->getName()][self::TYPE], $this->types)) {
			return $this->columnOptions[$column->getName()][self::TYPE];
		} else {
			return $column->getType();
		}
	}

}

