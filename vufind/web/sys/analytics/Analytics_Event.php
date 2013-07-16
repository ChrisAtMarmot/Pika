<?php
require_once 'DB/DataObject.php';

class Analytics_Event extends DB_DataObject{
	public $__table = 'analytics_event';                        // table name
	public $sessionId;
	public $category;
	public $action;
	public $data;
	public $data2;
	public $data3;
	public $eventTime;

	//Dynamically created based on queries
	public $numEvents;

	public function addDateFilters(){
		if (isset($_REQUEST['startDate'])){
			$startDate = DateTime::createFromFormat('m-d-Y', $_REQUEST['startDate']);
			$startDate->setTime(0, 0, 0);
			$this->whereAdd("eventTime  >= " . $startDate->getTimestamp());
		}
		if (isset($_REQUEST['endDate'])){
			$endDate = DateTime::createFromFormat('m-d-Y', $_REQUEST['endDate']);
			$startDate->setTime(24, 0, 0);
			$this->whereAdd("eventTime < " . $endDate->getTimestamp());
		}
	}

	public function getDateFilterSQL(){
		$sql = '';
		if (isset($_REQUEST['startDate'])){
			$startDate = DateTime::createFromFormat('m-d-Y', $_REQUEST['startDate']);
			$startDate->setTime(0, 0, 0);
			$sql .= " AND eventTime  >= " . $startDate->getTimestamp();
		}
		if (isset($_REQUEST['endDate'])){
			$endDate = DateTime::createFromFormat('m-d-Y', $_REQUEST['endDate']);
			$startDate->setTime(24, 0, 0);
			$sql .= " AND eventTime < " . $endDate->getTimestamp();
		}
		return $sql;
	}
}