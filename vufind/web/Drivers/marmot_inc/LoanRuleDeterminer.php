<?php
/**
 * Table Definition for library
 */
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php';

class LoanRuleDeterminer extends DB_DataObject
{
	public $__table = 'loan_rule_determiners';   // table name
	public $id;
	public $rowNumber;
	public $location;
	public $patronType;
	public $itemType;
	public $ageRange;
	public $loanRuleId;
	public $active;

	/* Static get */
	function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('LoanRuleDeterminer',$k,$v); }

	function keys() {
		return array('id');
	}

	function getObjectStructure(){
		$structure = array(
			'id' => array('property'=>'id', 'type'=>'label', 'label'=>'Id', 'description'=>'The unique id of the p-type within the database', 'hideInLists' => true),
			'rowNumber' => array('property'=>'rowNumber', 'type'=>'integer', 'label'=>'Row Number', 'description'=>'The row number of the determiner', 'hideInLists' => false),
			'location' => array('property'=>'location', 'type'=>'text', 'label'=>'Location', 'description'=>'The locations this row applies to'),
			'patronType' => array('property'=>'patronType', 'type'=>'text', 'label'=>'Patron Type', 'description'=>'The pTypes this row applies to'),
			'itemType' => array('property'=>'itemType', 'type'=>'text', 'label'=>'Item Type', 'description'=>'The iTypes this row applies to'),
			'ageRange' => array('property'=>'ageRange', 'type'=>'text', 'label'=>'Age Range', 'description'=>'The age range this row applies to'),
			'loanRuleId' => array('property'=>'loanRuleId', 'type'=>'integer', 'label'=>'Loan Rule Id', 'description'=>'The loan rule that this determiner row triggers'),
			'active' => array('property'=>'active', 'type'=>'checkbox', 'label'=>'Active?', 'description'=>'Whether or not the determiner row is active.'),
		);
		foreach ($structure as $fieldName => $field){
			$field['propertyOld'] = $field['property'] . 'Old';
			$structure[$fieldName] = $field;
		}
		return $structure;
	}

	function insert(){
		parent::insert();
		global $memCache;
		$memCache->delete('loan_rule_determiners');
	}

	function update($dataObject = false){
		parent::update($dataObject);
		global $memCache;
		$memCache->delete('loan_rule_determiners');
	}

	private $iTypeArray = null;
	function iTypeArray(){
		if ($this->iTypeArray == null){
			$this->iTypeArray = split(',', $this->itemType);
			foreach($this->iTypeArray as $key => $iType){
				if (!is_numeric($iType)){
					$iTypeRange = explode("-", $iType);
					for ($i = $iTypeRange[0]; $i <= $iTypeRange[0]; $i++){
						$this->iTypeArray[] = $i;
					}
					unset($this->iTypeArray[$key]);
				}
			}
		}
		return $this->iTypeArray;
	}

	private $pTypeArray = null;
	function pTypeArray(){
		if ($this->pTypeArray == null){
			$this->pTypeArray = split(',', $this->patronType);
			foreach($this->pTypeArray as $key => $pType){
				if (!is_numeric($pType)){
					$pTypeRange = explode("-", $pType);
					for ($i = $pTypeRange[0]; $i <= $pTypeRange[0]; $i++){
						$this->pTypeArray[] = $i;
					}
					unset($this->pTypeArray[$key]);
				}
			}
		}
		return $this->pTypeArray;
	}

	private $trimmedLocation = null;
	function trimmedLocation(){
		if ($this->trimmedLocation == null){
			if (substr($this->location, -1) == "*"){
				$this->trimmedLocation = substr($this->location, 0, strlen($this->location) - 1);
			}else{
				$this->trimmedLocation = $this->location;
			}
		}
		return $this->trimmedLocation;
	}
	function matchesLocation($location){
		$this->location = trim($this->location);
		if ($this->location == '*' || $this->location == '?????'){
			return true;
		}else{
			//TODO: Make this hacky
			$location = preg_replace('/\*+/', '.*', $location);
			return preg_match("/^{$this->trimmedLocation()}/i", $location);
		}
	}
}