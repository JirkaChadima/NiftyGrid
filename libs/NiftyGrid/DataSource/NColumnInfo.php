<?php

namespace NiftyGrid\DataSource;

class NColumnInfo implements IColumnInfo {

	private $info;

	public function __construct(array $info) {
		$this->info = $info;
	}
	
	public function getType() {
		return $this->info['type'];
	}
	
	public function getNativeType() {
		return $this->info['nativetype'];
	}
	
	public function getName() {
		return $this->info['name'];
	}
	
	public function getTableName() {
		return $this->info['table'];
	}
}