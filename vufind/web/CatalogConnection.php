<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class CatalogConnection
{
	/**
	 * A boolean value that defines whether a connection has been successfully
	 * made.
	 *
	 * @access public
	 * @var    bool
	 */
	public $status = false;

	/**
	 * The object of the appropriate driver.
	 *
	 * @access private
	 * @var    MillenniumDriver|DriverInterface
	 */
	public $driver;

	/**
	 * Constructor
	 *
	 * This is responsible for instantiating the driver that has been specified.
	 *
	 * @param string $driver The name of the driver to load.
	 * @throws PDOException error if we cannot connect to the driver.
	 *
	 * @access public
	 */
	public function __construct($driver)
	{
		global $configArray;
		$path = ROOT_DIR . "/Drivers/{$driver}.php";
		if (is_readable($path)) {
			require_once $path;

			try {
				$this->driver = new $driver;
			} catch (PDOException $e) {
				throw $e;
			}

			$this->status = true;
		}
	}

	/**
	 * Check Function
	 *
	 * This is responsible for checking the driver configuration to determine
	 * if the system supports a particular function.
	 *
	 * @param string $function The name of the function to check.
	 *
	 * @return mixed On success, an associative array with specific function keys
	 * and values; on failure, false.
	 * @access public
	 */
	public function checkFunction($function)
	{
		// Extract the configuration from the driver if available:
		$functionConfig = method_exists($this->driver, 'getConfig') ? $this->driver->getConfig($function) : false;

		// See if we have a corresponding check method to analyze the response:
		$checkMethod = "_checkMethod".$function;
		if (!method_exists($this, $checkMethod)) {
			//Just see if the method exists on the driver
			return method_exists($this->driver, $function);
		}

		// Send back the settings:
		return $this->$checkMethod($functionConfig);
	}

	/**
	 * Get Status
	 *
	 * This is responsible for retrieving the status information of a certain
	 * record.
	 *
	 * @param string $recordId The record id to retrieve the holdings for
	 *
	 * @return mixed     On success, an associative array with the following keys:
	 * id, availability (boolean), status, location, reserve, callnumber; on
	 * failure, a PEAR_Error.
	 * @access public
	 */
	public function getStatus($recordId, $forSearch = false)
	{
		return $this->driver->getStatus($recordId, $forSearch);
	}

	/**
	 * Get Statuses
	 *
	 * This is responsible for retrieving the status information for a
	 * collection of records.
	 *
	 * @param array $recordIds The array of record ids to retrieve the status for
	 * @param boolean $forSearch whether or not the summary will be shown in search results
	 *
	 * @return mixed           An array of getStatus() return values on success,
	 * a PEAR_Error object otherwise.
	 * @access public
	 * @author Chris Delis <cedelis@uillinois.edu>
	 */
	public function getStatuses($recordIds, $forSearch = false)
	{
		return $this->driver->getStatuses($recordIds, $forSearch);
	}

	/**
	 * Returns a summary of the holdings information for a single id. Used to display
	 * within the search results and at the top of a full record display to ensure
	 * the holding information makes sense to all users.
	 *
	 * @param string $id the id of the bid to load holdings for
	 * @param boolean $forSearch whether or not the summary will be shown in search results
	 * @return array an associative array with a summary of the holdings.
	 */
	public function getStatusSummary($id, $forSearch = false){
		return $this->driver->getStatusSummary($id, $forSearch);
	}

	/**
	 * Returns summary information for an array of ids.  This allows the search results
	 * to query all holdings at one time.
	 *
	 * @param array $ids an array ids to load summary information for.
	 * @param boolean $forSearch whether or not the summary will be shown in search results
	 * @return array an associative array containing a second array with summary information.
	 */
	public function getStatusSummaries($ids, $forSearch = false){
		return $this->driver->getStatusSummaries($ids, $forSearch);
	}

	/**
	 * Get Holding
	 *
	 * This is responsible for retrieving the holding information of a certain
	 * record.
	 *
	 * @param string $recordId The record id to retrieve the holdings for
	 * @param array  $patron   Optional Patron details to determine if a user can
	 * place a hold or recall on an item
	 *
	 * @return mixed     On success, an associative array with the following keys:
	 * id, availability (boolean), status, location, reserve, callnumber, duedate,
	 * number, barcode; on failure, a PEAR_Error.
	 * @access public
	 */
	public function getHolding($recordId, $patron = false)
	{
		$holding = $this->driver->getHolding($recordId, $patron);

		// Validate return from driver's getHolding method -- should be an array or
		// an error.  Anything else is unexpected and should become an error.
		if (!is_array($holding) && !PEAR_Singleton::isError($holding)) {
			return new PEAR_Error('Unexpected return from getHolding: ' . $holding);
		}

		return $holding;
	}

	/**
	 * Get Purchase History
	 *
	 * This is responsible for retrieving the acquisitions history data for the
	 * specific record (usually recently received issues of a serial).
	 *
	 * @param string $recordId The record id to retrieve the info for
	 *
	 * @return mixed           An array with the acquisitions data on success,
	 * PEAR_Error on failure
	 * @access public
	 */
	public function getPurchaseHistory($recordId)
	{
		return $this->driver->getPurchaseHistory($recordId);
	}

	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 *
	 * @param string $username The patron username
	 * @param string $password The patron password
	 *
	 * @return mixed           Associative array of patron info on successful
	 * login, null on unsuccessful login, PEAR_Error on error.
	 * @access public
	 */
	public function patronLogin($username, $password)
	{
		return $this->driver->patronLogin($username, $password);
	}

	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 *
	 * @param integer $page current     page to retrieve data for
	 * @param integer $recordsPerPage   current page to retrieve data for
	 * @param string  $sortOption       how the dates should sort.
	 *
	 * @return mixed        Array of the patron's transactions on success,
	 * PEAR_Error otherwise.
	 * @access public
	 */
	public function getMyTransactions($page = 1, $recordsPerPage = -1, $sortOption = 'dueDate')
	{
		return $this->driver->getMyTransactions($page, $recordsPerPage, $sortOption);
	}

	/**
	 * Get Patron Fines
	 *
	 * This is responsible for retrieving all fines by a specific patron.
	 *
	 * @param array $patron The patron array from patronLogin
	 *
	 * @return mixed        Array of the patron's fines on success, PEAR_Error
	 * otherwise.
	 * @access public
	 */
	public function getMyFines($patron, $includeMessages = false)
	{
		return $this->driver->getMyFines($patron, $includeMessages);
	}

	/**
	 * Get Reading History
	 *
	 * This is responsible for retrieving a history of checked out items for the patron.
	 *
	 * @param   array   $patron     The patron array
	 * @param   int     $page
	 * @param   int     $recordsPerPage
	 * @param   string  $sortOption
	 *
	 * @return  array               Array of the patron's reading list
	 *                              If an error occurs, return a PEAR_Error
	 * @access  public
	 */
	function getReadingHistory($patron, $page = 1, $recordsPerPage = -1, $sortOption = "checkedOut"){
		//Get reading history from the database unless we specifically want to load from the driver.
		global $user;
		if (($user->trackReadingHistory && $user->initialReadingHistoryLoaded) || !$this->driver->hasNativeReadingHistory()){
			require_once ROOT_DIR . '/sys/ReadingHistoryEntry.php';
			$readingHistoryDB = new ReadingHistoryEntry();
			$readingHistoryDB->userId = $user->id;
			if ($sortOption == "checkedOut"){
				$readingHistoryDB->orderBy('checkOutDate DESC, title ASC');
			}else if ($sortOption == "returned"){
				$readingHistoryDB->orderBy('checkInDate DESC, title ASC');
			}else if ($sortOption == "title"){
				$readingHistoryDB->orderBy('title ASC');
			}else if ($sortOption == "author"){
				$readingHistoryDB->orderBy('author ASC, title ASC');
			}else if ($sortOption == "format"){
				$readingHistoryDB->orderBy('format ASC, title ASC');
			}
			if ($recordsPerPage != -1){
				$readingHistoryDB->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
			}
			$readingHistoryDB->find();
			$readingHistoryTitles = array();
			$activeHistoryTitles = array();
			while ($readingHistoryDB->fetch()){
				$historyEntry = $this->getHistoryEntryForDatabaseEntry($readingHistoryDB);

				if ($historyEntry['checkin'] == null){
					$activeHistoryTitles[$historyEntry['source'] . ':' . $historyEntry['id']] = $historyEntry;
				}

				$readingHistoryTitles[] = $historyEntry;
			}

			//Update reading history based on current checkouts.  That way it never looks out of date
			require_once ROOT_DIR . '/services/API/UserAPI.php';
			$userAPI = new UserAPI();
			$checkouts = $userAPI->getPatronCheckedOutItems();
			foreach ($checkouts['checkedOutItems'] as $checkout){
				$sourceId = '?';
				$source = $checkout['checkoutSource'];
				if ($source == 'OverDrive'){
					$sourceId = $checkout['overDriveId'];
				}elseif ($source == 'ILS'){
					$sourceId = $checkout['id'];
				}elseif ($source == 'eContent'){
					$source = $checkout['recordType'];
					$sourceId = $checkout['id'];
				}
				$key = $source . ':' . $sourceId;
				if (array_key_exists($key, $activeHistoryTitles)){
					unset($activeHistoryTitles[$key]);
				}else{
					$historyEntryDB = new ReadingHistoryEntry();
					$historyEntryDB->userId = $user->id;
					$historyEntryDB->groupedWorkPermanentId = $checkout['groupedWorkId'] == null ? '' : $checkout['groupedWorkId'];
					$historyEntryDB->source = $source;
					$historyEntryDB->sourceId = $sourceId;
					$historyEntryDB->title = substr($checkout['title'], 0, 150);
					$historyEntryDB->author = substr($checkout['author'], 0, 75);
					$historyEntryDB->format = substr($checkout['format'], 0, 50);
					$historyEntryDB->checkOutDate = time();
					$historyEntryDB->insert();

					$historyEntry = $this->getHistoryEntryForDatabaseEntry($historyEntryDB);
					$readingHistoryTitles[] = $historyEntry;
				}
			}

			//Anything that was still active is now checked in
			foreach ($activeHistoryTitles as $historyEntry){
				$historyEntryDB = new ReadingHistoryEntry();
				$historyEntryDB->source = $historyEntry['source'];
				$readingHistoryDB->sourceId = $historyEntry['id'];
				$readingHistoryDB->checkInDate = time();
				$readingHistoryDB->update();
			}

			$readingHistoryDB = new ReadingHistoryEntry();
			$readingHistoryDB->userId = $user->id;
			$numTitles = $readingHistoryDB->count();

			return array('historyActive'=>$user->trackReadingHistory, 'titles'=>$readingHistoryTitles, 'numTitles'=> $numTitles);
		}else{
			//Don't know enough to load internally, check the ILS.
			return $this->driver->getReadingHistory($patron, $page, $recordsPerPage, $sortOption);
		}
	}

	/**
	 * Do an update or edit of reading history information.  Current actions are:
	 * deleteMarked
	 * deleteAll
	 * exportList
	 * optOut
	 *
	 * @param   string  $action         The action to perform
	 * @param   array   $selectedTitles The titles to do the action on if applicable
	 */
	function doReadingHistoryAction($action, $selectedTitles){
		global $user;
		if (($user->trackReadingHistory && $user->initialReadingHistoryLoaded) || ! $this->driver->hasNativeReadingHistory()){
			if ($action == 'deleteMarked'){
				//Remove titles from database (do not remove from ILS)
				foreach ($selectedTitles as $titleId){
					list($source, $sourceId) = split('_', $titleId);
					$readingHistoryDB = new ReadingHistoryEntry();
					$readingHistoryDB->userId = $user->id;
					$readingHistoryDB->id = str_replace('rsh', '', $titleId);
					$readingHistoryDB->delete();
				}
			}elseif ($action == 'deleteAll'){
				//Remove all titles from database (do not remove from ILS)
				$readingHistoryDB = new ReadingHistoryEntry();
				$readingHistoryDB->userId = $user->id;
				$readingHistoryDB->delete();
			}elseif ($action == 'exportList'){
				//Leave this unimplemented for now.
			}elseif ($action == 'optOut'){
				$driverHasReadingHistory = $this->driver->hasNativeReadingHistory();
				if ($driverHasReadingHistory){
					$result = $this->driver->doReadingHistoryAction($action, $selectedTitles);
				}
				if (!$driverHasReadingHistory || $result['historyActive']){
					//Opt out within Pika since the ILS does not seem to implement this functionality
					$user->trackReadingHistory = false;
					$user->update();
					$_SESSION['userinfo'] = serialize($user);
				}
			}elseif ($action == 'optIn'){
				$driverHasReadingHistory = $this->driver->hasNativeReadingHistory();
				if ($driverHasReadingHistory){
					$result = $this->driver->doReadingHistoryAction($action, $selectedTitles);
				}
				if (!$driverHasReadingHistory || !$result['historyActive']){
					//Opt in within Pika since the ILS does not seem to implement this functionality
					$user->trackReadingHistory = true;
					$user->update();
					$_SESSION['userinfo'] = serialize($user);
				}
			}
		}else{
			return $this->driver->doReadingHistoryAction($action, $selectedTitles);
		}
	}


	/**
	 * Get Patron Holds
	 *
	 * This is responsible for retrieving all holds by a specific patron.
	 *
	 * @param array $patron The patron array from patronLogin
	 *
	 * @return mixed        Array of the patron's holds on success, PEAR_Error
	 * otherwise.
	 * @access public
	 */
	public function getMyHolds($patron, $page = 1, $recordsPerPage = -1, $sortOption = 'title')
	{
		return $this->driver->getMyHolds($patron, $page, $recordsPerPage, $sortOption);
	}

	/**
	 * Get Patron Profile
	 *
	 * This is responsible for retrieving the profile for a specific patron.
	 *
	 * @param array|User $patron The patron array
	 *
	 * @return mixed        Array of the patron's profile data on success,
	 * PEAR_Error otherwise.
	 * @access public
	 */
	public function getMyProfile($patron)
	{
		$profile = $this->driver->getMyProfile($patron);
		$profile['readingHistorySize'] = '';
		global $user;
		if ($user && $user->trackReadingHistory && $user->initialReadingHistoryLoaded){
			require_once ROOT_DIR . '/sys/ReadingHistoryEntry.php';
			$readingHistoryDB = new ReadingHistoryEntry();
			$readingHistoryDB->userId = $user->id;
			$profile['readingHistorySize'] = $readingHistoryDB->count();
		}
		return $profile;
	}

	/**
	 * Place Hold
	 *
	 * This is responsible for both placing holds as well as placing recalls.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $patronId   The id of the patron
	 * @param   string  $comment    Any comment regarding the hold or recall
	 * @param   string  $type       Whether to place a hold or recall
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occures, return a PEAR_Error
	 * @access  public
	 */
	function placeHold($recordId, $patronId, $comment, $type)
	{
		return $this->driver->placeHold($recordId, $patronId, $comment, $type);
	}

	/**
	 * Place Item Hold
	 *
	 * This is responsible for both placing item level holds.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @param   string  $itemId     The id of the item to hold
	 * @param   string  $patronId   The id of the patron
	 * @param   string  $comment    Any comment regarding the hold or recall
	 * @param   string  $type       Whether to place a hold or recall
	 * @return  mixed               True if successful, false if unsuccessful
	 *                              If an error occures, return a PEAR_Error
	 * @access  public
	 */
	function placeItemHold($recordId, $itemId, $patronId, $comment, $type)
	{
		return $this->driver->placeItemHold($recordId, $itemId, $patronId, $comment, $type);
	}

	/**
	 * Get Hold Link
	 *
	 * The goal for this method is to return a URL to a "place hold" web page on
	 * the ILS OPAC. This is used for ILSs that do not support an API or method
	 * to place Holds.
	 *
	 * @param   string  $recordId   The id of the bib record
	 * @return  mixed               True if successful, otherwise return a PEAR_Error
	 * @access  public
	 */
	function getHoldLink($recordId)
	{
		return $this->driver->getHoldLink($recordId);
	}

	function updatePatronInfo($canUpdateContactInfo)
	{
		return $this->driver->updatePatronInfo($canUpdateContactInfo);
	}

	function selfRegister(){
		return $this->driver->selfRegister();
	}

	/**
	 * Get New Items
	 *
	 * Retrieve the IDs of items recently added to the catalog.
	 *
	 * @param int $page    Page number of results to retrieve (counting starts at 1)
	 * @param int $limit   The size of each page of results to retrieve
	 * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
	 * @param int $fundId  optional fund ID to use for limiting results (use a value
	 * returned by getFunds, or exclude for no limit); note that "fund" may be a
	 * misnomer - if funds are not an appropriate way to limit your new item
	 * results, you can return a different set of values from getFunds. The
	 * important thing is that this parameter supports an ID returned by getFunds,
	 * whatever that may mean.
	 *
	 * @return array       Associative array with 'count' and 'results' keys
	 * @access public
	 */
	public function getNewItems($page = 1, $limit = 20, $daysOld = 30,
	$fundId = null
	) {
		return $this->driver->getNewItems($page, $limit, $daysOld, $fundId);
	}

	/**
	 * Get Funds
	 *
	 * Return a list of funds which may be used to limit the getNewItems list.
	 *
	 * @return array An associative array with key = fund ID, value = fund name.
	 * @access public
	 */
	public function getFunds()
	{
		// Graceful degradation -- return empty fund list if no method supported.
		return method_exists($this->driver, 'getFunds') ?
		$this->driver->getFunds() : array();
	}

	/**
	 * Get Departments
	 *
	 * Obtain a list of departments for use in limiting the reserves list.
	 *
	 * @return array An associative array with key = dept. ID, value = dept. name.
	 * @access public
	 */
	public function getDepartments()
	{
		// Graceful degradation -- return empty list if no method supported.
		return method_exists($this->driver, 'getDepartments') ?
		$this->driver->getDepartments() : array();
	}

	/**
	 * Get Instructors
	 *
	 * Obtain a list of instructors for use in limiting the reserves list.
	 *
	 * @return array An associative array with key = ID, value = name.
	 * @access public
	 */
	public function getInstructors()
	{
		// Graceful degradation -- return empty list if no method supported.
		return method_exists($this->driver, 'getInstructors') ?
		$this->driver->getInstructors() : array();
	}

	/**
	 * Get Courses
	 *
	 * Obtain a list of courses for use in limiting the reserves list.
	 *
	 * @return array An associative array with key = ID, value = name.
	 * @access public
	 */
	public function getCourses()
	{
		// Graceful degradation -- return empty list if no method supported.
		return method_exists($this->driver, 'getCourses') ?
		$this->driver->getCourses() : array();
	}

	/**
	 * Find Reserves
	 *
	 * Obtain information on course reserves.
	 *
	 * @param string $course ID from getCourses (empty string to match all)
	 * @param string $inst   ID from getInstructors (empty string to match all)
	 * @param string $dept   ID from getDepartments (empty string to match all)
	 *
	 * @return mixed An array of associative arrays representing reserve items (or a
	 * PEAR_Error object if there is a problem)
	 * @access public
	 */
	public function findReserves($course, $inst, $dept)
	{
		return $this->driver->findReserves($course, $inst, $dept);
	}

	/**
	 * Process inventory for a particular item in the catalog
	 *
	 * @param string $login     Login for the user doing the inventory
	 * @param string $password1 Password for the user doing the inventory
	 * @param string $initials
	 * @param string $password2
	 * @param string[] $barcodes
	 * @param boolean $updateIncorrectStatuses
	 *
	 * @return array
	 */
	function doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses){
		return $this->driver->doInventory($login, $password1, $initials, $password2, $barcodes, $updateIncorrectStatuses);
	}

	/**
	 * Get suppressed records.
	 *
	 * @return array ID numbers of suppressed records in the system.
	 * @access public
	 */
	public function getSuppressedRecords()
	{
		return $this->driver->getSuppressedRecords();
	}

	/**
	 * Default method -- pass along calls to the driver if available; return
	 * false otherwise.  This allows custom functions to be implemented in
	 * the driver without constant modification to the connection class.
	 *
	 * @param string $methodName The name of the called method.
	 * @param array  $params     Array of passed parameters.
	 *
	 * @return mixed             Varies by method (false if undefined method)
	 * @access public
	 */
	public function __call($methodName, $params)
	{
		$method = array($this->driver, $methodName);
		if (is_callable($method)) {
			return call_user_func_array($method, $params);
		}
		return false;
	}

	public function getSelfRegistrationFields() {
		return $this->driver->getSelfRegistrationFields();
	}

	/**
	 * @param ReadingHistoryEntry $readingHistoryDB
	 * @return mixed
	 */
	public function getHistoryEntryForDatabaseEntry($readingHistoryDB) {
		$historyEntry = array();

		$historyEntry['itemindex'] = $readingHistoryDB->id;
		$historyEntry['deletable'] = true;
		$historyEntry['source'] = $readingHistoryDB->source;
		$historyEntry['id'] = $readingHistoryDB->sourceId;
		$historyEntry['recordId'] = $readingHistoryDB->sourceId;
		$historyEntry['shortId'] = $readingHistoryDB->sourceId;
		$historyEntry['title'] = $readingHistoryDB->title;
		$historyEntry['author'] = $readingHistoryDB->author;
		$historyEntry['format'] = array($readingHistoryDB->format);
		$historyEntry['checkout'] = $readingHistoryDB->checkOutDate;
		$historyEntry['checkin'] = $readingHistoryDB->checkInDate;
		$historyEntry['ratingData'] = null;
		$historyEntry['permanentId'] = null;
		$historyEntry['linkUrl'] = null;
		$historyEntry['coverUrl'] = null;
		$recordDriver = null;
		if ($readingHistoryDB->source == 'ILS') {
			require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
			$recordDriver = new MarcRecord($historyEntry['id']);
		} elseif ($readingHistoryDB->source == 'OverDrive') {
			require_once ROOT_DIR . '/RecordDrivers/OverDriveRecordDriver.php';
			$recordDriver = new OverDriveRecordDriver($historyEntry['id']);
		} elseif ($readingHistoryDB->source == 'PublicEContent') {
			require_once ROOT_DIR . '/RecordDrivers/PublicEContentDriver.php';
			$recordDriver = new PublicEContentDriver($historyEntry['id']);
		} elseif ($readingHistoryDB->source == 'RestrictedEContent') {
			require_once ROOT_DIR . '/RecordDrivers/RestrictedEContentDriver.php';
			$recordDriver = new RestrictedEContentDriver($historyEntry['id']);
		}
		if ($recordDriver != null && $recordDriver->isValid()) {
			$historyEntry['ratingData'] = $recordDriver->getRatingData();
			$historyEntry['permanentId'] = $recordDriver->getPermanentId();
			$historyEntry['linkUrl'] = $recordDriver->getLinkUrl();
			$historyEntry['coverUrl'] = $recordDriver->getBookcoverUrl('medium');
			$historyEntry['format'] = $recordDriver->getFormats();
		}
		$recordDriver = null;
		return $historyEntry;
	}
}

?>
