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
	const TYPE_YEAR = 'y';
	const TYPE_BINARY = 'bin';
	const TYPE_ENUM = 'e';

	private $types;
	// native mysql types to 
	private $typeCache = array(
		'TINYINT' => self::TYPE_BOOLEAN,
		'SMALLINT' => self::TYPE_NUMERIC,
		'MEDIUMINT' => self::TYPE_NUMERIC,
		'INT' => self::TYPE_NUMERIC,
		'BIGINT' => self::TYPE_NUMERIC,
		'BIT' => self::TYPE_BOOLEAN,
		'FLOAT' => self::TYPE_NUMERIC,
		'DOUBLE' => self::TYPE_NUMERIC,
		'DECIMAL' => self::TYPE_NUMERIC,
		'CHAR' => self::TYPE_TEXT,
		'VARCHAR' => self::TYPE_TEXT,
		'STRING' => self::TYPE_TEXT,
		'TINYTEXT' => self::TYPE_TEXT,
		'TEXT' => self::TYPE_LONGTEXT,
		'MEDIUMTEXT' => self::TYPE_LONGTEXT,
		'LONGTEXT' => self::TYPE_LONGTEXT,
		'BINARY' => self::TYPE_BINARY,
		'VARBINARY' => self::TYPE_BINARY,
		'TINYBLOB' => self::TYPE_BINARY,
		'BLOB' => self::TYPE_BINARY,
		'MEDIUMBLOB' => self::TYPE_BINARY,
		'LONGBLOB' => self::TYPE_BINARY,
		'DATE' => self::TYPE_DATE,
		'TIME' => self::TYPE_TIME,
		'YEAR' => self::TYPE_YEAR,
		'DATETIME' => self::TYPE_DATETIME,
		'TIMESTAMP' => self::TYPE_DATETIME,
		'ENUM' => self::TYPE_TEXT,
	);

	/** @var \DibiFluent */
	protected $fluentSource;
	protected $cacheResult;
	// grid options
	/**
	 * Is allowed editing
	 * @var bool
	 */
	protected $editable = false;

	/**
	 * Is allowed filtering
	 * @var bool
	 */
	protected $filterable = false;

	/**
	 * Is allowed removing of rows
	 * @var bool
	 */
	protected $removable = false;

	/**
	 * Is allowed adding of rows
	 * @var bool
	 */
	protected $creatable = false;

	/**
	 * If no column options are set, grid is trying to do all by itself.
	 * Influences editable and filterable columns.
	 * @var bool
	 */
	protected $autoMode = false;

	/**
	 * If default insert row callback is active.
	 * @var bool
	 */
	protected $defaultInsertRowCallbackEnabled = true;

	/**
	 * If default update row callback is active.
	 * @var bool
	 */
	protected $defaultUpdateRowCallbackEnabled = true;

	/**
	 * If default delete row callback is active.
	 * @var bool
	 */
	protected $defaultDeleteRowCallbackEnabled = true;

	/**
	 * Update row callbacks
	 * @var array
	 */
	public $onUpdateRow = array();

	/**
	 * Insert row callbacks
	 * @var array
	 */
	public $onInsertRow = array();

	/**
	 * Delete row callbacks
	 * @var array
	 */
	public $onDeleteRow = array();

	/**
	 * Specific options for grid columns
	 * @var array
	 */
	protected $columnOptions = array();

	/**
	 * Primary key column name, without it the grid cannot be shown. If none is
	 * specified in columnOptions, id is automatically chosen.
	 * @var string
	 */
	protected $keyColumn;

	/**
	 * Default order by clause. If none is specified in columnOptions,
	 * id is automatically chosen.
	 * @var string
	 */
	protected $defaultOrderBy;

	/** @var array */
	protected $rowButtonOptions;
	
	// ajax, css class, confirmationdialog, label, link, target, text, name (=key)
	
	
	public function __construct(\DibiFluent $fluent, array $columnOptions = array(), $rowButtonOptions = array(), $globalButtonOptions = array()) {
		parent::__construct();
		$this->fluentSource = $fluent;
		$this->types = array(self::TYPE_NUMERIC, self::TYPE_TEXT, self::TYPE_LONGTEXT, self::TYPE_BOOLEAN, self::TYPE_DATE, self::TYPE_DATETIME, self::TYPE_TIME, self::TYPE_BINARY, self::TYPE_ENUM);
		$this->columnOptions = $columnOptions;
		$this->rowButtonOptions = $rowButtonOptions;

		// set key and orderBy
		if (empty($columnOptions)) {
			$this->keyColumn = 'id';
			$this->defaultOrderBy = 'id asc';
			$this->autoMode = true;
		} else {
			foreach ($columnOptions as $col => $attrs) {
				if (!empty($attrs[self::KEY])) {
					$this->keyColumn = $col;
				}
				if (!empty($attrs[self::ORDER])) {
					$this->defaultOrderBy = $col;
					$this->defaultOrderBy .= ($attrs[self::ORDER_DESC] ? ' desc' : ' asc');
				}
			}
		}
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
			} else {
				if ($this->getColumnType($column) === self::TYPE_TIME) {
					$col->setRenderer(function ($row) use($colName) {
								if ($row[$colName] instanceof \DateTime) {
									return $row[$colName]->format('H:i:s');
								}
								return $row[$colName];
							});
				} elseif ($this->getColumnType($column) === self::TYPE_DATE) {
					$col->setRenderer(function ($row) use($colName) {
								if ($row[$colName] instanceof \DateTime) {
									return $row[$colName]->format('Y-m-d');
								}
								return $row[$colName];
							});
				} elseif ($this->getColumnType($column) === self::TYPE_YEAR) {
					$col->setRenderer(function ($row) use($colName) {
								if ($row[$colName] instanceof \DateTime) {
									return $row[$colName]->format('Y');
								}
								return $row[$colName];
							});
				}
			}
		}
		$this->makeEditableColumns($columns, $this['columns']->components);
		$this->makeFilterableColumns($columns, $this['columns']->components);

		if ($this->removable) {
			$self = $this;
			$this->addButton('remove')
					->setLabel(_('Remove row'))
					->setClass('inline-remove')
					->setLink(function($row) use ($self) {
								return $self->link("removeRow!", array('primaryKey' => $row[$self->getKeyColumn()]));
							})
					->setConfirmationDialog(function($row) {
								return _("Are you sure? This row will be deleted forever!");
							});
		}

		if ($this->creatable) {
			$this->addGlobalButton(Grid::ADD_ROW, _('Add row'))
					->setClass('inline-add btn-success');
			if ($this->rowFormCallback === null) {
				$this->setRowFormCallback(callback($this, 'handleUpdateRow'));
			}
		}

		if (count($this->rowButtonOptions)) {
			foreach($this->rowButtonOptions as $name => $options) {
				
			}
		}

// ++ custom global buttons
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
		$this->addButton(Grid::ROW_FORM, _("Inline edit"))
				->setClass("inline-edit");
		if ($this->rowFormCallback === null) {
			$this->setRowFormCallback(callback($this, 'handleUpdateRow'));
		}

		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (!$this->autoMode && empty($this->columnOptions[$column->getName()])) || (!$this->autoMode && empty($this->columnOptions[$column->getName()][self::EDITABLE])) || empty($components[$column->getName()])) {
				continue;
			}
			$col = $components[$column->getName()];
			switch ($this->getColumnType($column)) {
				case self::TYPE_BOOLEAN: # boolean
					$col->setBooleanEditable();
					break;
				case self::TYPE_LONGTEXT: # longtext
					$col->setTextEditable(true);
					break;
				case self::TYPE_TEXT: # text
				case self::TYPE_NUMERIC: # text
					$col->setTextEditable();
					break;
				case self::TYPE_ENUM: # enum
					$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array() );
					if (!empty($colOptions[self::ENUM])) {
						$col->setSelectEditable($colOptions[self::ENUM]);
					}
					break;
				case self::TYPE_DATETIME: # datetime
					$col->setDatetimeEditable(Components\Column::DATE_TIME);
					break;
				case self::TYPE_DATE: # date
					$col->setDatetimeEditable(Components\Column::DATE_ONLY);
					break;
				case self::TYPE_TIME: # time
					$col->setDatetimeEditable(Components\Column::TIME_ONLY);
					break;
				case self::TYPE_YEAR: # time
					$col->setDatetimeEditable(Components\Column::YEAR_ONLY);
					break;
				case self::TYPE_BINARY: # nothing
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
					$col->setBooleanFilter();
					break;
				case self::TYPE_TEXT: # text
				case self::TYPE_LONGTEXT: # nothing
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
					$col->setNumericFilter();
					break;
				case self::TYPE_ENUM: # enum
					$colOptions = (!empty($this->columnOptions[$column->getName()]) ? $this->columnOptions[$column->getName()] : array() );
					if (!empty($colOptions[self::ENUM])) {
						$col->setSelectFilter($colOptions[self::ENUM], '--');
					}
					break;
				case self::TYPE_DATETIME: # datetime
					$col->setDatetimeFilter(Components\Column::DATE_TIME);
					break;
				case self::TYPE_DATE: # date
					$col->setDatetimeFilter(Components\Column::DATE_ONLY);
					break;
				case self::TYPE_TIME: # time
					$col->setDatetimeFilter(Components\Column::TIME_ONLY);
					break;
				case self::TYPE_YEAR: # time
					$col->setDatetimeFilter(Components\Column::YEAR_ONLY);
					break;
				case self::TYPE_BINARY: # nothing
			}
		}
	}

	public function handleUpdateRow($values) {
		if (!$this->keyColumn) {
			throw new \NiftyGrid\UnknownColumnException('Key column is not set!');
		}

		if (!empty($values[$this->keyColumn])) { // if there is a key, it is probably an update
			if (!$this->editable) {
				return;
			}
			$this->defaultUpdateRowCallback($values);
			foreach ($this->onUpdateRow as $callback) {
				call_user_func($callback, $values);
			}
		} else { // if there is no key, it is probably an insert
			if (!$this->creatable) {
				return;
			}
			$this->defaultInsertRowCallback($values);
			foreach ($this->onInsertRow as $callback) {
				call_user_func($callback, $values);
			}
		}
	}

	/**
	 * If the default udpate row callback is allowed, it tries to set the values
	 * in the row with passed $primeryKey field
	 * 
	 * @param array $values
	 * @throws \NiftyGrid\UnknownColumnException
	 */
	private function defaultUpdateRowCallback($values) {
		if ($this->defaultUpdateRowCallbackEnabled) {
			$columns = $this->cacheResult->getColumns();
			$table = $columns[0]->getTableName();

			if (!$values[$this->keyColumn]) {
				throw new \NiftyGrid\UnknownColumnException('Key column not found!');
			}

			$id = $values[$this->keyColumn];
			unset($values[$this->keyColumn]);

			foreach ($values as $colname => $val) {
				$tableName = $this['columns']->components[$colname]->tableName;
				if (!empty($this['columns']->components[$colname]) && !empty($tableName)) {
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
	}

	/**
	 * If the default insert row callback is allowed, it tries to insert
	 * values into the database table.
	 * 
	 * @param array $values
	 */
	private function defaultInsertRowCallback($values) {
		if ($this->defaultInsertRowCallbackEnabled) {
			$columns = $this->cacheResult->getColumns();
			$table = $columns[0]->getTableName();

			foreach ($values as $colname => $val) {
				$tableName = $this['columns']->components[$colname]->tableName;
				if (!empty($this['columns']->components[$colname]) && !empty($tableName)) {
					$values[$tableName] = $val;
					unset($values[$colname]);
				}
			}
			try {
				$this->fluentSource
						->getConnection()
						->insert($table, $values)
						->execute();
				$this->flashMessage(_('Row was inserted.'), 'alert alert-success');
			} catch (\DibiDriverException $e) {
				$this->flashMessage(_('There was an error during the communication with the database: ' . $e->getMessage()), 'alert alert-error');
			}
		}
	}

	/**
	 * Handles row removing if allowed.
	 * 
	 * If the default deleteRowCallback is enabled, it is processed. Then all
	 * other onDeleteRow callback are called. 
	 * 
	 * @param mixed $primaryKey
	 * @throws \NiftyGrid\UnknownColumnException if key column is not found
	 */
	public function handleRemoveRow($primaryKey) {
		if (!$this->removable) {
			return;
		}
		if ($this->defaultDeleteRowCallbackEnabled) {
			$columns = $this->cacheResult->getColumns();
			$table = $columns[0]->getTableName();

			if (!$this->keyColumn) {
				throw new \NiftyGrid\UnknownColumnException('Key column not set!');
			}

			try {
				$this->fluentSource
						->getConnection()
						->delete($table)
						->where('%n = %i', $this->keyColumn, $primaryKey)
						->execute();
				$this->flashMessage(_('Row was removed.'), 'alert alert-success');
			} catch (\DibiDriverException $e) {
				$this->flashMessage(_('There was an error during the communication with the database: ' . $e->getMessage()), 'alert alert-error');
			}
		}

		foreach ($this->onDeleteRow as $callback) {
			call_user_func($callback, $primaryKey);
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
			return $this->typeCache[strtoupper($column->getNativeType())];
		}
	}

	/**
	 * Getter for $keyColumn attribute
	 * @return string
	 */
	public function getKeyColumn() {
		return $this->keyColumn;
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
	 * Globally enables removing.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function enableRemoving() {
		$this->removable = true;
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
	 * Globally enables adding.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function enableAdding() {
		$this->creatable = true;
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
		$this->defaultUpdateRowCallbackEnabled = false;
		return $this;
	}

	/**
	 * Disables default insert row callback that updates row in the database.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function disableDefaultOnInsertRowCallback() {
		$this->defaultInsertRowCallbackEnabled = false;
		return $this;
	}

	/**
	 * Disables default delete row callback that deletes row from the database.
	 * 
	 * @return \NiftyGrid\AutomaticGrid
	 */
	public function disableDefaultOnDeleteRowCallback() {
		$this->defaultDeleteRowCallbackEnabled = false;
		return $this;
	}

}

