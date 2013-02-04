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

interface IDataSource
{
	/**
	 * Returns data
	 */
	public function getData();

	/**
	 * Returns name of Primary key
	 */
	public function getPrimaryKey();

	/**
	 * Returns count of rows on SQL side
	 * @param string $column
	 */
	public function getCount($column = "*");

	/**
	 * Returns count of rows on PHP side (because HAVING conditions doesn't affect on SQL COUNT)
	 */
	public function getSelectedRowsCount();
	
	/**
	 * Sort data by given column
	 * @param string $by
	 * @param string $way
	 */
	public function orderData($by, $way);

	/**
	 * Limit data to select
	 * @param int $limit
	 * @param int $offset
	 */
	public function limitData($limit, $offset);
	
	/**
	 * Array of IColumnInfo that holds information about all columns.
	 */
	public function getColumns();
	
	public function update($table, array $data, $primaryKeyValue);
	public function insert($table, array $data);
	public function delete($table, $primaryKeyValue);

	/**
	 * Filter data by $filters
	 * $filters = array(
	 * 	filter => array(
	 * 		column => $name
	 * 			- name of the column
	 *
	 * 		type => WHERE
	 * 			- type of SQL condition (based on class FilterCondition - condition types)
	 *
	 * 		datatype => TEXT|NUMERIC|DATE
	 * 			- data type of the column (based on class FilterCondition - filter types)
	 * 			- SELECT and BOOLEAN filters are translated as TEXT filter with EQUAL( = ) condition
	 *
	 * 		cond => $condition
	 * 			- SQL operator ( = , > , < , LIKE ? , ...)
	 *
	 * 		value => value for condition
	 * 			- the filter value (text, %text, 50, ...)
	 *
	 * 		columnFunction => $function
	 * 			- SQL function for use on column (DATE, ...)
	 * 			- optional
	 *
	 * 		valueFunction => $function
	 * 			- SQL function for use on value (DATE, ...)
	 *			- optional
	 * 	)
	 * )
	 *
	 * @param array $filters
	 */
	public function filterData(array $filters);
}

class DataSourceException extends \Exception {}