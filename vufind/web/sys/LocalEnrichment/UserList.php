<?php
/**
 * Table Definition for user_list
 */
require_once 'DB/DataObject.php';

class UserList extends DB_DataObject
{
	###START_AUTOCODE
	/* the code below is auto generated do not remove the above tag */

	public $__table = 'user_list';												// table name
	public $id;															// int(11)	not_null primary_key auto_increment
	public $user_id;													// int(11)	not_null multiple_key
	public $title;														// string(200)	not_null
	public $description;											// string(500)
	public $created;													// datetime(19)	not_null binary
	public $public;													// int(11)	not_null
	public $deleted;
	public $dateUpdated;
	public $defaultSort; // string(20) null

	// Used by FavoriteHandler as well/**/
	protected $userListSortOptions = array(
		// URL_value => SQL code for Order BY clause
		'dateAdded' => 'dateAdded ASC',
		'custom' => 'weight ASC',  // this puts items with no set weight towards the end of the list
		//								'custom' => 'weight IS NULL, weight ASC',  // this puts items with no set weight towards the end of the list
	);


	/* Static get */
	function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('UserList',$k,$v); }

	/* the code above is auto generated do not remove the tag below */
	###END_AUTOCODE

	function getObjectStructure(){
		$structure = array(
			'id' => array(
				'property'=>'id',
				'type'=>'hidden',
				'label'=>'Id',
				'primaryKey'=>true,
				'description'=>'The unique id of the e-pub file.',
				'storeDb' => true,
				'storeSolr' => false,
			),
			'title' => array(
				'property' => 'title',
				'type' => 'text',
				'size' => 100,
				'maxLength'=>255,
				'label' => 'Title',
				'description' => 'The title of the item.',
				'required'=> true,
				'storeDb' => true,
				'storeSolr' => true,
			),
			'description' => array(
				'property' => 'description',
				'type' => 'textarea',
				'label' => 'Description',
				'rows'=>3,
				'cols'=>80,
				'description' => 'A brief description of the file for indexing and display if there is not an existing record within the catalog.',
				'required'=> false,
				'storeDb' => true,
				'storeSolr' => true,
			),
		);
		return $structure;
	}

	function numValidListItems() {
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry = new UserListEntry();
		$listEntry->listId = $this->id;

		// These conditions retrieve list items with a valid groupedworked or archive ID.
		// (This prevents list strangeness when our searches don't find the ID in the search indexes)
		$listEntry->whereAdd(
			'(
     (user_list_entry.groupedWorkPermanentId NOT LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT permanent_id FROM grouped_work) )
    OR
    (user_list_entry.groupedWorkPermanentId LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT pid FROM islandora_object_cache) )
)'
		);

		return $listEntry->count();
	}

//	function numValidListItems() {
//		$archiveItems = $this->num_archive_items();
//		$catalogItems = $this->num_titles();
//		return $archiveItems + $catalogItems;
//	);
//	function num_archive_items() {
//		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
//		//Join with grouped work to make sure we only load valid entries
//		$listEntry = new UserListEntry();
//		$listEntry->listId = $this->id;
//
//		require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
//		$islandoraObject = new IslandoraObjectCache();
//		$listEntry->joinAdd($islandoraObject);
//		return $listEntry->count();
//	}
//
//	function num_titles(){
//		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
//		//Join with grouped work to make sure we only load valid entries
//		$listEntry = new UserListEntry();
//		$listEntry->listId = $this->id;
//
//		require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';
//		$groupedWork = new GroupedWork();
//		$listEntry->joinAdd($groupedWork);
//		return $listEntry->count();
//	}

	function insert(){
		$this->created = time();
		$this->dateUpdated = time();
		return parent::insert();
	}
	function update(){
		if ($this->created == 0){
			$this->created = time();
		}
		$this->dateUpdated = time();
		return parent::update();
	}
	function delete(){
		$this->deleted = 1;
		$this->dateUpdated = time();
		return parent::delete();
	}

	/**
	 * @var array An array of resources keyed by the list id since we can iterate over multiple lists while fetching from the DB
	 */
	private $listTitles = array();

	/**
	 * @param null $sort  optional SQL for the query's ORDER BY clause
	 * @return array      of list entries
	 */
	function getListEntries($sort = null){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry = new UserListEntry();
		$listEntry->listId = $this->id;
		if ($sort) $listEntry->orderBy($sort);

		// These conditions retrieve list items with a valid groupedworked or archive ID.
		// (This prevents list strangeness when our searches don't find the ID in the search indexes)
		$listEntry->whereAdd(
			'(
     (user_list_entry.groupedWorkPermanentId NOT LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT permanent_id FROM grouped_work) )
    OR
    (user_list_entry.groupedWorkPermanentId LIKE "%:%" AND user_list_entry.groupedWorkPermanentId IN (SELECT pid FROM islandora_object_cache) )
)'
		);

		$listEntries = $archiveIDs = $catalogIDs = array();
		$listEntry->find();
		while ($listEntry->fetch()){
			if (strpos($listEntry->groupedWorkPermanentId, ':') !== false) {
				$archiveIDs[] = $listEntry->groupedWorkPermanentId;
			} else {
				$catalogIDs[] = $listEntry->groupedWorkPermanentId;
			}
			$listEntries[] = $listEntry->groupedWorkPermanentId;
		}

		return array($listEntries, $catalogIDs, $archiveIDs);
	}

	/**
	 * @return UserListEntry[]|null
	 */
	function getListTitles()
	{
		if (isset($this->listTitles[$this->id])){
			return $this->listTitles[$this->id];
		}
		$listTitles = array();

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry = new UserListEntry();
		$listEntry->listId = $this->id;
		$listEntry->find();

		while ($listEntry->fetch()){
			$cleanedEntry = $this->cleanListEntry(clone($listEntry));
			if ($cleanedEntry != false){
				$listTitles[] = $cleanedEntry;
			}
		}

		$this->listTitles[$this->id] = $listTitles;
		return $this->listTitles[$this->id];
	}

	var $catalog;

	/**
	 * @param UserListEntry $listEntry - The resource to be cleaned
	 * @return UserListEntry|bool
	 */
	function cleanListEntry($listEntry){
//		global $configArray;
		global $user;

		// Connect to Database
		$this->catalog = CatalogFactory::getCatalogConnectionInstance();

		//Filter list information for bad words as needed.
		if ($user == false || $this->user_id != $user->id){
			//Load all bad words.
			global $library;
			require_once ROOT_DIR . '/Drivers/marmot_inc/BadWord.php';
			$badWords = new BadWord();
//			$badWordsList = $badWords->getBadWordExpressions();

			//Determine if we should censor bad words or hide the comment completely.
			$censorWords = true;
			if (isset($library)) $censorWords = $library->hideCommentsWithBadWords == 0 ? true : false;
			if ($censorWords){
				//Filter Title
				$titleText = $badWords->censorBadWords($this->title);
				$this->title = $titleText;

				//Filter description
				$descriptionText = $badWords->censorBadWords($this->description);
				$this->description = $descriptionText;

				//Filter notes
				// TODO: possible problem: $notesText overwrites the above description?
				$notesText = $badWords->censorBadWords($listEntry->notes);
//				$this->notes = $notesText;
				$listEntry->notes = $notesText;
			}else{
				//Check for bad words in the title or description
				$titleText = $this->title;
				if (isset($listEntry->description)){
					$titleText .= ' ' . $listEntry->description;
				}
				//Filter notes
				$titleText .= ' ' . $listEntry->notes;

				if ($badWords->hasBadWords($titleText)) return false;
			}
		}
		return $listEntry;
	}

	/**
	 * @param String $workToRemove
	 */
	function removeListEntry($workToRemove)
	{
		// Remove the Saved List Entry
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
		$listEntry = new UserListEntry();
		$listEntry->groupedWorkPermanentId = $workToRemove;
		$listEntry->listId = $this->id;
		$listEntry->delete();

		unset($this->listTitles[$this->id]);
	}

	/**
		* remove all resources within this list
		*/
	function removeAllListEntries(){
		$allListEntries = $this->getListTitles();
		foreach ($allListEntries as $listEntry){
			$this->removeListEntry($listEntry);
		}
	}

	/**
	 * @param $start     position of first list item to fetch
	 * @param $numItems  Number of items to fetch for this result
	 * @return array     Array of HTML to display to the user
	 */
	public function getBrowseRecords($start, $numItems) {
		global $interface;
		$browseRecords = array();
		$sort               = in_array($this->defaultSort, array_keys($this->userListSortOptions)) ? $this->userListSortOptions[$this->defaultSort] : null;
		list($listEntries)  = $this->getListEntries($sort);
		$listEntries        = array_slice($listEntries, $start, $numItems);
		foreach ($listEntries as $listItemId) {
			if (strpos($listItemId, ':') === false) {
				// Catalog Items
				require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';
				$groupedWork = new GroupedWorkDriver($listItemId);
				if ($groupedWork->isValid) {
					if (method_exists($groupedWork, 'getBrowseResult')) {
						$browseRecords[] = $interface->fetch($groupedWork->getBrowseResult());
					} else {
						$browseRecords[] = 'Browse Result not available';
					}
				}
			} // Archive Items
			else {
				require_once ROOT_DIR . './sys/Utils/FedoraUtils.php';
				$fedoraUtils = FedoraUtils::getInstance();
				$archiveObject = $fedoraUtils->getObject($listItemId);
				$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
				if (method_exists($recordDriver, 'getBrowseResult')) {
					$browseRecords[] = $interface->fetch($recordDriver->getBrowseResult());
				} else {
					$browseRecords[] = 'Browse Result not available';
				}
			}
		}
		return $browseRecords;
	}

	/**
	 * @return array
	 */
	public function getUserListSortOptions()
	{
		return $this->userListSortOptions;
	}
}
