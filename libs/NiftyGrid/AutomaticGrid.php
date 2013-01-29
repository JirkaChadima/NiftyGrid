<?php

namespace NiftyGrid;

class AutomaticGrid extends \NiftyGrid\Grid {

	const AUTOCOMPLETE_LIST_LENGTH = 10;
	
	/** @var \DibiFluent */
	protected $fluentSource;
	protected $keyColumn;
	protected $editable = false;
	protected $filterable = false;
	protected $defaultUpdateRowCallback = true;
	protected $cacheResult;
	
	public $onUpdateRow = array();
	
	// refactor - have one column options array
	protected $editableColumns = array();
	protected $filterableColumns = array();
	protected $aliases = array();

	public function __construct(\DibiFluent $fluent, $keyColumn) {
		parent::__construct();
		$this->fluentSource = $fluent;
		$this->keyColumn = $keyColumn;
	}

	public function enableEditing() {
		$this->editable = true;
		return $this;
	}
	
	public function setAliases(array $aliases) {
		$this->aliases = $aliases;
		return $this;
	}

	public function setEditableColumns(array $columns) {
		$this->editableColumns = $columns;
		return $this;
	}

	public function setFilterableColumns(array $columns) {
		$this->filterableColumns = $columns;
		return $this;
	}

	public function enableFiltering() {
		$this->filterable = true;
		return $this;
	}

	public function disableDefaultOnUpdateRowCallback() {
		$this->defaultUpdateRowCallback = false;
		return $this;
	}

	public function disableSorting() {
		$this->enableSorting = false;
		return $this;
	}

	protected function configure($presenter) {
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
			$name = (!empty($this->aliases[$column->getName()]) ? $this->aliases[$column->getName()] : \Nette\Utils\Strings::firstUpper($column->getName()));
			$this->addColumn($column->getName(), $name);
			
			/*->setTableName('u.username')
				->setAutoComplete(10)
				->setRenderer(function($row) use ($presenter) {
							return \Nette\Utils\Html::el('a')
									->setText($row['username'])->setHref($presenter->link(':Front:Administrator:Users:manage', $row['id']));
			});*/
			
			// ++ booleans
			
			// ++ default actions
			
			// ++ default order
			
			// ++ custom actions
		}
		$this->makeEditableColumns($columns, $this['columns']->components);
		$this->makeFilterableColumns($columns, $this['columns']->components);
	}

	protected function makeEditableColumns($columns, $components) {
		if (!$this->editable) {
			return;
		}
		$this->addButton(\NiftyGrid\Grid::ROW_FORM, "Rychlá editace")
				->setClass("inline-edit");
		$this->setRowFormCallback(callback($this, 'handleUpdateRow'));

		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (count($this->editableColumns) && !in_array($column->getName(), $this->editableColumns))) {
				continue;
			}
			$col = $components[$column->getName()];
			switch ($column->getType()) {
				case \Dibi::BOOL: #boolean
					$col->setBooleanEditable();
					break;
				case \Dibi::TEXT: #text
					// TODO re-visit with show create table
					//$col->setTextEditable(($column->getNativeType() != 'VARCHAR' || $column->getNativeType() != 'ENUM'));
					$col->setTextEditable(true);
					break;
				case \Dibi::INTEGER: #numbers
				case \Dibi::FLOAT: #numbers
					$col->setTextEditable();
					break;
				case \Dibi::DATE: #date
					$col->setDateEditable();
					break;
				case \Dibi::DATETIME: #nothing
				case \Dibi::TIME: #nothing
				case \Dibi::BINARY: #nothing
			}
		}
	}

	protected function makeFilterableColumns($columns, $components) {
		if (!$this->filterable) {
			return;
		}
		foreach ($columns as $column) {
			if ($column->getName() === $this->keyColumn || (count($this->filterableColumns) && !in_array($column->getName(), $this->filterableColumns))) {
				continue;
			}
			$col = $components[$column->getName()];
			switch ($column->getType()) {
				case \Dibi::BOOL: #boolean
					$col->setBooleanFilter();
					break;
				case \Dibi::TEXT: #text
					$col->setTextFilter()
						->setAutoComplete(self::AUTOCOMPLETE_LIST_LENGTH);
					break;
				case \Dibi::INTEGER: #numbers
				case \Dibi::FLOAT: #numbers
					$col->setNumericFilter();
					break;
				case \Dibi::DATE: #date
				case \Dibi::DATETIME: #date
					$col->setDateFilter();
					break;
				case \Dibi::TIME: #nothing
				case \Dibi::BINARY: #nothing
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

}

