<?php
/**
 * A superclass for Digital Archive Objects
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 9/9/2015
 * Time: 4:13 PM
 */

require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
abstract class Archive_Object extends Action {
	protected $pid;
	/** @var  FedoraObject $archiveObject */
	protected $archiveObject;
	/** @var IslandoraDriver $recordDriver */
	protected $recordDriver;
	//protected $dcData;
	protected $modsData;
	//Data with a namespace of mods
	protected $modsModsData;
	protected $relsExtData;

	protected $formattedSubjects;
	protected $links;

	/**
	 * @param string $mainContentTemplate Name of the SMARTY template file for the main content of the Full Record View Pages
	 * @param string $pageTitle What to display is the html title tag
	 */
	function display($mainContentTemplate, $pageTitle = null) {
		global $interface;
		global $user;
		global $logger;

		$pageTitle = $pageTitle == null ? $this->archiveObject->label : $pageTitle;
		$interface->assign('breadcrumbText', $pageTitle);

		// Set Search Navigation
		// Retrieve User Search History
		//Get Next/Previous Links

		$isExhibitContext = !empty($_SESSION['ExhibitContext']) and $this->recordDriver->getUniqueID() != $_SESSION['ExhibitContext'];
		if ($isExhibitContext && empty($_COOKIE['exhibitNavigation'])) {
			$isExhibitContext = false;
			$this->endExhibitContext();
		}
		if ($isExhibitContext) {
			$logger->log("In exhibit context, setting exhibit navigation", PEAR_LOG_DEBUG);
			$this->setExhibitNavigation();
		} elseif (isset($_SESSION['lastSearchURL'])) {
			$logger->log("In search context, setting search navigation", PEAR_LOG_DEBUG);
			$this->setArchiveSearchNavigation();
		} else {
			$logger->log("Not in any context, not setting navigation", PEAR_LOG_DEBUG);
		}

		//Check to see if usage is restricted or not.
		$viewingRestrictions = $this->recordDriver->getViewingRestrictions();
		if (count($viewingRestrictions) > 0){
			$canView = false;
			$validHomeLibraries = array();

			if ($user && $user->getHomeLibrary()){
				$validHomeLibraries[] = $user->getHomeLibrary()->subdomain;
				$linkedAccounts = $user->getLinkedUsers();
				foreach ($linkedAccounts as $linkedAccount){
					$validHomeLibraries[] = $linkedAccount->getHomeLibrary()->subdomain;
				}
			}

			foreach ($viewingRestrictions as $restriction){
				$libraryDomain = trim($restriction);
				if (array_search($libraryDomain, $validHomeLibraries) !== false){
					//User is valid based on their login
					$canView = true;
					break;
				}
			}

			if (!$canView){
				global $locationSingleton;
				$physicalLocation = $locationSingleton->getPhysicalLocation();
				if ($physicalLocation){
					$physicalLibrary = new Library();
					$physicalLibrary->libraryId = $physicalLocation->libraryId;
					if ($physicalLibrary->find(true)){
						$physicalLibrarySubdomain = $physicalLibrary->subdomain;
						foreach ($viewingRestrictions as $restriction){
							$libraryDomain = trim($restriction);
							if ($libraryDomain == $physicalLibrarySubdomain){
								//User is valid based on their login
								$canView = true;
								break;
							}
						}
					}
				}
			}
		}else{
			$canView = true;
		}

		$interface->assign('canView', $canView);

		$showClaimAuthorship = $this->recordDriver->getShowClaimAuthorship();
		$interface->assign('showClaimAuthorship', $showClaimAuthorship);

		parent::display($mainContentTemplate, $pageTitle);
	}

	//TODO: This should eventually move onto a Record Driver
	function loadArchiveObjectData() {
		global $interface;
		global $configArray;
		$fedoraUtils = FedoraUtils::getInstance();

		// Replace 'object:pid' with the PID of the object to be loaded.
		$this->pid = urldecode($_REQUEST['id']);
		$interface->assign('pid', $this->pid);

		list($namespace) = explode(':', $this->pid);
		//Find the owning library
		$owningLibrary = new Library();
		$owningLibrary->archiveNamespace = $namespace;
		if ($owningLibrary->find(true) && $owningLibrary->N == 1) {
			$interface->assign('allowRequestsForArchiveMaterials', $owningLibrary->allowRequestsForArchiveMaterials);
		} else {
			$interface->assign('allowRequestsForArchiveMaterials', false);
		}

		$this->archiveObject = $fedoraUtils->getObject($this->pid);
		$this->recordDriver = RecordDriverFactory::initRecordDriver($this->archiveObject);
		$interface->assign('recordDriver', $this->recordDriver);

		//Load the MODS data stream
		$this->modsData = $this->recordDriver->getModsData();
		$interface->assign('mods', $this->modsData);

		$location = $this->recordDriver->getModsValue('location', 'mods');
		if (strlen($location) > 0) {
			$interface->assign('primaryUrl', $this->recordDriver->getModsValue('url', 'mods', $location));
		}

		$alternateNames = $this->recordDriver->getModsValues('alternateName', 'marmot');
		$interface->assign('alternateNames', FedoraUtils::cleanValues($alternateNames));

		$this->recordDriver->loadRelatedEntities();

		$addressInfo = array();
		$latitude = $this->recordDriver->getModsValue('latitude', 'marmot');
		$longitude = $this->recordDriver->getModsValue('longitude', 'marmot');
		$addressStreetNumber = $this->recordDriver->getModsValue('addressStreetNumber', 'marmot');
		$addressStreet = $this->recordDriver->getModsValue('addressStreet', 'marmot');
		$address2 = $this->recordDriver->getModsValue('address2', 'marmot');
		$addressCity = $this->recordDriver->getModsValue('addressCity', 'marmot');
		$addressCounty = $this->recordDriver->getModsValue('addressCounty', 'marmot');
		$addressState = $this->recordDriver->getModsValue('addressState', 'marmot');
		$addressZipCode = $this->recordDriver->getModsValue('addressZipCode', 'marmot');
		$addressCountry = $this->recordDriver->getModsValue('addressCountry', 'marmot');
		$addressOtherRegion = $this->recordDriver->getModsValue('addressOtherRegion', 'marmot');
		if (strlen($latitude) ||
				strlen($longitude) ||
				strlen($addressStreetNumber) ||
				strlen($addressStreet) ||
				strlen($address2) ||
				strlen($addressCity) ||
				strlen($addressCounty) ||
				strlen($addressState) ||
				strlen($addressZipCode) ||
				strlen($addressOtherRegion)
		) {

			if (strlen($latitude) > 0) {
				$addressInfo['latitude'] = $latitude;
			}
			if (strlen($longitude) > 0) {
				$addressInfo['longitude'] = $longitude;
			}

			if (strlen($addressStreetNumber) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressStreetNumber'] = $addressStreetNumber;
			}
			if (strlen($addressStreet) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressStreet'] = $addressStreet;
			}
			if (strlen($address2) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['address2'] = $address2;
			}
			if (strlen($addressCity) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressCity'] = $addressCity;
			}
			if (strlen($addressState) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressState'] = $addressState;
			}
			if (strlen($addressCounty) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressCounty'] = $addressCounty;
			}
			if (strlen($addressZipCode) > 0) {
				$addressInfo['hasDetailedAddress'] = true;
				$addressInfo['addressZipCode'] = $addressZipCode;
			}
			if (strlen($addressCountry) > 0) {
				$addressInfo['addressCountry'] = $addressCountry;
			}
			if (strlen($addressOtherRegion) > 0) {
				$addressInfo['addressOtherRegion'] = $addressOtherRegion;
			}
			$interface->assign('addressInfo', $addressInfo);
		}//End verifying checking for address information

		//Load information about dates
		$startDate = $this->recordDriver->getModsValue('placeDateStart', 'marmot');
		if ($startDate) {
			$interface->assign('startDate', $startDate);
		} else {
			$startDate = $this->recordDriver->getModsValue('eventStartDate', 'marmot');
			if ($startDate) {
				$interface->assign('startDate', $startDate);
			} else {
				$startDate = $this->recordDriver->getModsValue('dateEstablished', 'marmot');
				if ($startDate) {
					$interface->assign('startDate', $startDate);
				}
			}
		}
		$endDate = $this->recordDriver->getModsValue('placeDateEnd', 'marmot');
		if ($endDate) {
			$interface->assign('endDate', $endDate);
		} else {
			$endDate = $this->recordDriver->getModsValue('eventEndDate', 'marmot');
			if ($endDate) {
				$interface->assign('endDate', $endDate);
			} else {
				$endDate = $this->recordDriver->getModsValue('dateDisbanded', 'marmot');
				if ($endDate) {
					$interface->assign('endDate', $endDate);
				}
			}
		}

		$title = $this->archiveObject->label;
		$interface->assign('title', $title);
		$interface->setPageTitle($title);


		$interface->assign('original_image', $this->recordDriver->getBookcoverUrl('original'));
		$interface->assign('large_image', $this->recordDriver->getBookcoverUrl('large'));
		$interface->assign('medium_image', $this->recordDriver->getBookcoverUrl('medium'));

		$repositoryLink = $configArray['Islandora']['repositoryUrl'] . '/islandora/object/' . $this->pid;
		$interface->assign('repositoryLink', $repositoryLink);

		//Check for display restrictions
		if ($this->recordDriver instanceof BasicImageDriver || $this->recordDriver instanceof LargeImageDriver) {
			/** @var CollectionDriver $collection */
			$anonymousMasterDownload = true;
			$verifiedMasterDownload = true;
			$anonymousLcDownload = true;
			$verifiedLcDownload = true;
			foreach ($this->recordDriver->getRelatedCollections() as $collection) {
				$collectionDriver = RecordDriverFactory::initRecordDriver($collection['object']);
				if (!$collectionDriver->canAnonymousDownloadMaster()) {
					$anonymousMasterDownload = false;
				}
				if (!$collectionDriver->canVerifiedDownloadMaster()) {
					$verifiedMasterDownload = false;
				}
				if (!$collectionDriver->canAnonymousDownloadLC()) {
					$anonymousLcDownload = false;
				}
				if (!$collectionDriver->canVerifiedDownloadLC()) {
					$verifiedLcDownload = false;
				}
			}
			$interface->assign('anonymousMasterDownload', $anonymousMasterDownload);
			$interface->assign('verifiedMasterDownload', $verifiedMasterDownload);
			$interface->assign('anonymousLcDownload', $anonymousLcDownload);
			$interface->assign('verifiedLcDownload', $verifiedLcDownload);
		}
	}

	protected function endExhibitContext()
	{
		global $logger;
		$logger->log("Ending exhibit context", PEAR_LOG_DEBUG);
		$_SESSION['ExhibitContext']  = null;
		$_SESSION['exhibitSearchId'] = null;
		$_SESSION['placePid']        = null;
		$_SESSION['placeLabel']      = null;
		$_SESSION['dateFilter']      = null;
		$_COOKIE['exhibitInAExhibitParentPid'] = null;
	}

	/**
	 *
	 */
	protected function setExhibitNavigation()
	{
		global $interface;
		global $logger;

		$interface->assign('isFromExhibit', true);

		// Return to Exhibit URLs
		$exhibitObject = RecordDriverFactory::initRecordDriver(array('PID' => $_SESSION['ExhibitContext']));
		$exhibitUrl    = $exhibitObject->getLinkUrl();
		$exhibitName   = $exhibitObject->getTitle();
		$isMapExhibit  = !empty($_SESSION['placePid']);
		if ($isMapExhibit) {
			$exhibitUrl .= '?style=map&placePid=' . urlencode($_SESSION['placePid']);
			if (!empty($_SESSION['placeLabel'])) {
				$exhibitName .= ' - ' . $_SESSION['placeLabel'];
			}
			$logger->log("Navigating from a map exhibit", PEAR_LOG_DEBUG);
		}else{
			$logger->log("Navigating from a NON map exhibit", PEAR_LOG_DEBUG);
		}

		//TODO: rename to template vars exhibitName and exhibitUrl;  does it affect other navigation contexts

		$interface->assign('lastCollection', $exhibitUrl);
		$interface->assign('collectionName', $exhibitName);
		$isExhibit = get_class($this) == 'Archive_Exhibit';
		if (!empty($_COOKIE['exhibitInAExhibitParentPid']) && $_COOKIE['exhibitInAExhibitParentPid'] == $_SESSION['ExhibitContext']) {
			$_COOKIE['exhibitInAExhibitParentPid'] = null;
		}

		if (!empty($_COOKIE['exhibitInAExhibitParentPid'])) {
			/** @var CollectionDriver $parentExhibitObject */
			$parentExhibitObject = RecordDriverFactory::initRecordDriver(array('PID' => $_COOKIE['exhibitInAExhibitParentPid']));
			$parentExhibitUrl    = $parentExhibitObject->getLinkUrl();
			$parentExhibitName   = $parentExhibitObject->getTitle();
			$interface->assign('parentExhibitUrl', $parentExhibitUrl);
			$interface->assign('parentExhibitName', $parentExhibitName);

			if ($isExhibit) { // If this is a child exhibit page
				//
				$interface->assign('lastCollection', $parentExhibitUrl);
				$interface->assign('collectionName', $parentExhibitName);
				$parentExhibitObject->getNextPrevLinks($this->pid);
			}
		}
		if (!empty($_COOKIE['collectionPid'])) {
			$fedoraUtils = FedoraUtils::getInstance();
			$collectionToLoadFromObject = $fedoraUtils->getObject($_COOKIE['collectionPid']);
			/** @var CollectionDriver $collectionDriver */
			$collectionDriver = RecordDriverFactory::initRecordDriver($collectionToLoadFromObject);
			$collectionDriver->getNextPrevLinks($this->pid);

		} elseif (!empty($_SESSION['exhibitSearchId']) && !$isExhibit) {
			$recordIndex = isset($_COOKIE['recordIndex']) ? $_COOKIE['recordIndex'] : null;
			$page        = isset($_COOKIE['page']) ? $_COOKIE['page'] : null;
			// Restore Islandora Search
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init('islandora');
			$searchObject->getNextPrevLinks($_SESSION['exhibitSearchId'], $recordIndex, $page, $isMapExhibit);
			// pass page and record index info
			$logger->log("Setting exhibit navigation for exhibit {$_SESSION['ExhibitContext']} from search id {$_SESSION['exhibitSearchId']}", PEAR_LOG_DEBUG);
		}else{
			$logger->log("Exhibit search id was not provided", PEAR_LOG_DEBUG);
		}
	}

	private function setArchiveSearchNavigation()
	{
		global $interface;
		global $logger;
		$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false);
		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'islandora';
		//TODO: What if it ain't islandora? (direct navigation to archive object page)
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init($searchSource);
		$searchObject->getNextPrevLinks();
		$logger->log("Setting search navigation for archive search", PEAR_LOG_DEBUG);
	}

}