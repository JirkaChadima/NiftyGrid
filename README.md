NiftyGrid/Automatic
===================

This fork brings an AutomaticGrid class to the NiftyGrid package.

Usage
-----

```php
$fluent = $dibi	
			->select('report.id, report.date, report.status, report.note')
			->from('report');
$source = new \NiftyGrid\DataSource\DibiFluentDataSource($fluent, 'id');
$grid = new \NiftyGrid\AutomaticGrid(
	$source,
	array(
			'id' => array(
				NiftyGrid\AutomaticGrid::KEY => true,
				NiftyGrid\AutomaticGrid::ORDER => true,
				NiftyGrid\AutomaticGrid::ORDER_DESC => true,
			),
			'date' => array(
				NiftyGrid\AutomaticGrid::ALIAS => 'Datum reportu',
				NiftyGrid\AutomaticGrid::FILTERABLE => true,
				NiftyGrid\AutomaticGrid::TYPE => NiftyGrid\AutomaticGrid::TYPE_DATE,
				NiftyGrid\AutomaticGrid::EDITABLE => true,
			),
			'note' => array(
				NiftyGrid\AutomaticGrid::EDITABLE => true,
				NiftyGrid\AutomaticGrid::FILTERABLE => true,
				NiftyGrid\AutomaticGrid::ALIAS => 'Note to this report',
				NiftyGrid\AutomaticGrid::TYPE => NiftyGrid\AutomaticGrid::TYPE_LONGTEXT,
				NiftyGrid\AutomaticGrid::AUTOCOMPLETE => true,
				NiftyGrid\AutomaticGrid::AUTOCOMPLETE_LENGTH => 15,
				NiftyGrid\AutomaticGrid::RENDERER => function($row, $component) {
					return \Nette\Utils\Html::el('strong')->setText($row['note']);
				},
			),
			'status' => array(
				NiftyGrid\AutomaticGrid::ENUM => array('OK', 'NOT OK'),
				NiftyGrid\AutomaticGrid::TYPE => NiftyGrid\AutomaticGrid::TYPE_ENUM,
				NiftyGrid\AutomaticGrid::EDITABLE => true,
				NiftyGrid\AutomaticGrid::FILTERABLE => true,
			),
		),
);
```