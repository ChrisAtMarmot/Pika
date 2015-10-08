<?php
require_once ROOT_DIR . '/RecordDrivers/IndexRecord.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

/**
 * List Record Driver
 *
 * This class is designed to handle List records.  Much of its functionality
 * is inherited from the default index-based driver.
 */
class PersonRecord extends IndexRecord
{
	public function __construct($record)
	{
		// Call the parent's constructor...
		parent::__construct($record);
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult()
	{
		global $interface;

		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		$shortId = substr($id, 6);
		$interface->assign('summShortId', $shortId); //Trim the list prefix for the short id

		$person = new Person();
		$person->personId = $shortId;
		$person->find();
		if ($person->N > 0){
			$person->fetch();
			$interface->assign('summPicture', $person->picture);
		}

		$name = $this->getName();
		$interface->assign('summTitle', trim($name));
		$interface->assign('birthDate', $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear));
		$interface->assign('deathDate', $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear));
		$interface->assign('lastUpdate', $person->lastModified);
		$interface->assign('dateAdded', $person->dateAdded);
		$interface->assign('numObits', count($person->obituaries));

		return 'RecordDrivers/Person/result.tpl';
	}

	function getBreadcrumb(){
		return $this->getName();
	}

	function getName(){
		$name = '';
		if (isset($this->fields['firstName'])){
			$name = $this->fields['firstName'];
		}
		if (isset($this->fields['middleName'])){
			$name .= ' ' . $this->fields['middleName'];
		}
		if (isset($this->fields['nickName']) && strlen($this->fields['nickName']) > 0){
			$name .= ' "' . $this->fields['nickName'] . '"';
		}
		if (isset($this->fields['maidenName']) && strlen($this->fields['maidenName']) > 0){
			$name .= ' (' . $this->fields['maidenName'] . ')';
		}
		if (isset($this->fields['lastName']) && strlen($this->fields['lastName']) > 0) {
			$name .= ' ' . $this->fields['lastName'];
		}
		return $name;
	}
}