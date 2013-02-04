<?php

/**
 *
 * @author JT8100803
 */
interface IColumnInfo {
	
	/**
	 * Returns type of the column.
	 * 
	 * examples: string, number, time
	 */
	public function getType();
	
	/**
	 * Returns native type of the column.
	 * 
	 * examples: VARCHAR, TEXT, FLOAT
	 */
	public function getNativeType();
	
	/**
	 * Returns column name.
	 */
	public function getName();
	
	/**
	 * Returns table name of the column.
	 */
	public function getTableName();
}
