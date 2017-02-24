<?php
/**
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
 */

require_once ROOT_DIR . '/Action.php';

class Archive_AJAX extends Action {


	function launch() {
		global $timer;
		$method = $_GET['method'];
		$timer->logTime("Starting method $method");
		//JSON Responses
		header('Content-type: application/json');
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
		echo json_encode($this->$method());
	}

	function getRelatedObjectsForExhibit(){
		if (isset($_REQUEST['collectionId'])){
			global $interface;
			global $timer;
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid = urldecode($_REQUEST['collectionId']);
			$interface->assign('exhibitPid', $pid);

			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $page);

			$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'title';
			$interface->assign('sort', $sort);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '0');
			}


			$displayType = 'basic';
			$interface->assign('displayType', $displayType);

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			$searchObject->clearFacets();

			$searchObject->setLimit(24);

//			$searchObject->setSort('fgs_label_s');
			$this->setupTimelineSorts($sort, $searchObject);
			//TODO: Do these sorts work for a basic exhibit?

			$interface->assign('showThumbnailsSorted', true);

			$relatedObjects = array();
			$response = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0) {
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd',   $summary['endRecord']);

				$recordIndex = $summary['startRecord'];
				foreach ($response['response']['docs'] as $objectInCollection){
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedObjects[] = array(
							'title' => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image' => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link' => $firstObjectDriver->getRecordUrl(),
							'pid' => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++
					);
					$timer->logTime('Loaded related object');
				}

			}

			$interface->assign('relatedObjects', $relatedObjects);
			return array(
					'success' => true,
					'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl')
			);
		}else{
			return array(
					'success' => false,
					'message' => 'You must supply the collection and place to load data for'
			);
		}
	}

	function getRelatedObjectsForScroller(){
		if (isset($_REQUEST['pid'])){
			global $interface;
			global $timer;
			global $logger;
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid = urldecode($_REQUEST['pid']);
			$interface->assign('exhibitPid', $pid);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $page);

			$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'title';
			$interface->assign('sort', $sort);

			$displayType = 'scroller';
			$interface->assign('displayType', $displayType);

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->clearFacets();

			$searchObject->setSearchTerms(array(
					'lookfor' => '"' . $pid . '"',
					'index' => 'IslandoraRelationshipsById'
			));

			$searchObject->setLimit(24);

			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$relatedObjects = array();
			$response = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0) {
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd',   $summary['endRecord']);
				$recordIndex = $summary['startRecord'];
				$page = $summary['page'];
				$interface->assign('page', $page);

				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$logger->log("Setting exhibit search id to $lastExhibitObjectsSearch", PEAR_LOG_DEBUG);

				foreach ($response['response']['docs'] as $objectInCollection){
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedObject = array(
							'title' => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image' => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link' => $firstObjectDriver->getRecordUrl(),
							'pid' => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++
					);
					if ($sort == 'dateAdded'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
					}elseif ($sort == 'dateModified'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
					}
					$relatedObjects[] = $relatedObject;
					$timer->logTime('Loaded related object');
				}

				$this->processTimelineData($response, $interface);
			}

			$interface->assign('relatedObjects', $relatedObjects);
			return array(
					'success' => true,
					'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl')
			);
		}else{
			return array(
					'success' => false,
					'message' => 'You must supply the collection and place to load data for'
			);
		}
	}

	function getRelatedObjectsForTimelineExhibit(){
		if (isset($_REQUEST['collectionId'])){
			global $interface;
			global $timer;
			global $logger;
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid = urldecode($_REQUEST['collectionId']);
			$interface->assign('exhibitPid', $pid);

			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $page);

			$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'title';
			$interface->assign('sort', $sort);

			$displayType = 'timeline';
			$interface->assign('displayType', $displayType);

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			$searchObject->clearFacets();
			//Add filtering based on date filters
			$timeLineSetUp = false;
			if (!empty($_SESSION['ExhibitContext']) && $_SESSION['ExhibitContext'] == $pid) {

				if (!empty($_REQUEST['dateFilter']) && $_REQUEST['dateFilter'] != 'all') {
					$_SESSION['dateFilter'] = $_REQUEST['dateFilter']; // store applied date filters
				} else {
					// Clear time data
					unset($_SESSION['dateFilter']);
				}
			}
			$this->setupTimelineFacetsAndFilters($searchObject);

			$searchObject->setLimit(24);

			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$relatedObjects = array();
			$response = $searchObject->processSearch(true, false);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0) {
				$summary = $searchObject->getResultSummary();
				if (!$timeLineSetUp) {
					$interface->assign('recordCount', $summary['resultTotal']);
				}
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd',   $summary['endRecord']);
				$recordIndex = $summary['startRecord'];
				$page = $summary['page'];
				$interface->assign('page', $page);

				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$logger->log("Setting exhibit search id to $lastExhibitObjectsSearch", PEAR_LOG_DEBUG);

				foreach ($response['response']['docs'] as $objectInCollection){
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedObject = array(
							'title' => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image' => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link' => $firstObjectDriver->getRecordUrl(),
							'pid' => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++
					);
					if ($sort == 'dateAdded'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
					}elseif ($sort == 'dateModified'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
					}
					$relatedObjects[] = $relatedObject;
					$timer->logTime('Loaded related object');
				}

				if (!$timeLineSetUp){
					$this->processTimelineData($response, $interface);
				}
			}

			$interface->assign('relatedObjects', $relatedObjects);
			return array(
					'success' => true,
					'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl')
			);
		}else{
			return array(
					'success' => false,
					'message' => 'You must supply the collection and place to load data for'
			);
		}
	}

	function getRelatedObjectsForMappedCollection(){
		if (isset($_REQUEST['collectionId']) && isset($_REQUEST['placeId'])){
			global $interface;
			global $timer;
			global $logger;
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pid = urldecode($_REQUEST['collectionId']);
			$interface->assign('exhibitPid', $pid);
			if (isset($_REQUEST['reloadHeader'])){
				$interface->assign('reloadHeader', $_REQUEST['reloadHeader']);
			}else{
				$interface->assign('reloadHeader', '1');
			}

			$placeId = urldecode($_REQUEST['placeId']);
			$logger->log("Setting place information for context $placeId", PEAR_LOG_DEBUG);
			@session_start();
			$_SESSION['placePid'] =  $placeId;
			$interface->assign('placePid', $placeId);

			/** @var FedoraObject $placeObject */
			$placeObject = $fedoraUtils->getObject($placeId);
			$_SESSION['placeLabel'] = $placeObject->label;
			$logger->log("Setting place label for context $placeObject->label", PEAR_LOG_DEBUG);

			$interface->assign('displayType', 'map');

			$interface->assign('label', $placeObject->label);

			$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
			$interface->assign('page', $page);

			$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'title';
			$interface->assign('sort', $sort);

			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setDebugging(false, false);
			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->addFilter("RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$pid}\"");
			$searchObject->setBasicQuery("mods_extension_marmotLocal_relatedEntity_place_entityPid_ms:\"{$placeId}\" OR " .
					"mods_extension_marmotLocal_relatedPlace_entityPlace_entityPid_ms:\"{$placeId}\" OR " .
					"mods_extension_marmotLocal_militaryService_militaryRecord_relatedPlace_entityPlace_entityPid_ms:\"{$placeId}\" OR " .
					"mods_extension_marmotLocal_describedEntity_entityPid_ms:\"{$placeId}\" OR " .
					"mods_extension_marmotLocal_picturedEntity_entityPid_ms:\"{$placeId}\""
			);

			$searchObject->clearFacets();
			$this->setupTimelineFacetsAndFilters($searchObject);
			$this->setupTimelineSorts($sort, $searchObject);
			$interface->assign('showThumbnailsSorted', true);

			$searchObject->setLimit(24);

			$relatedObjects = array();
			$response = $searchObject->processSearch(true, false, true);
			if ($response && isset($response['error'])){
				$interface->assign('solrError', $response['error']['msg']);
				$interface->assign('solrLink', $searchObject->getFullSearchUrl());
			}
			if ($response && isset($response['response']) && $response['response']['numFound'] > 0) {
				$summary = $searchObject->getResultSummary();
				$interface->assign('recordCount', $summary['resultTotal']);
				$interface->assign('recordStart', $summary['startRecord']);
				$interface->assign('recordEnd',   $summary['endRecord']);
				$recordIndex = $summary['startRecord'];

				// Save the search with Map query and filters
				$searchObject->close(); // Trigger save search
				$lastExhibitObjectsSearch = $searchObject->getSearchId(); // Have to save the search first.
				$_SESSION['exhibitSearchId'] = $lastExhibitObjectsSearch;
				$logger->log("Setting exhibit search id to $lastExhibitObjectsSearch", PEAR_LOG_DEBUG);

				foreach ($response['response']['docs'] as $objectInCollection){
					/** @var IslandoraDriver $firstObjectDriver */
					$firstObjectDriver = RecordDriverFactory::initRecordDriver($objectInCollection);
					$relatedObject = array(
							'title' => $firstObjectDriver->getTitle(),
							'description' => $firstObjectDriver->getDescription(),
							'image' => $firstObjectDriver->getBookcoverUrl('medium'),
							'dateCreated' => $firstObjectDriver->getDateCreated(),
							'link' => $firstObjectDriver->getRecordUrl(),
							'pid' => $firstObjectDriver->getUniqueID(),
							'recordIndex' => $recordIndex++
					);
					if ($sort == 'dateAdded'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_createdDate_dt']));
					}elseif ($sort == 'dateModified'){
						$relatedObject['dateCreated'] = date('M j, Y', strtotime($objectInCollection['fgs_lastModifiedDate_dt']));
					}
					$relatedObjects[] = $relatedObject;
					$timer->logTime('Loaded related object');
				}
				$this->processTimelineData($response, $interface);
			}

			$interface->assign('relatedObjects', $relatedObjects);
			return array(
					'success' => true,
					'relatedObjects' => $interface->fetch('Archive/relatedObjects.tpl')
			);
		}else{
			return array(
					'success' => false,
					'message' => 'You must supply the collection and place to load data for'
			);
		}
	}

	function getFacetValuesForExhibit(){
		if (!isset($_REQUEST['id'])){
			return array(
					'success' => false,
					'message' => 'You must supply the id to load facet data for'
			);
		}
		if (!isset($_REQUEST['facetName'])){
			return array(
					'success' => false,
					'message' => 'You must supply the facetName to load facet data for'
			);
		}

		$pid = urldecode($_REQUEST['id']);

		//get a list of all collections and books within the main exhibit so we can find all related data.
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();
		$exhibitObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $exhibitDriver */
		$exhibitDriver = RecordDriverFactory::initRecordDriver($exhibitObject);
		$childContainers = $exhibitDriver->getPIDsOfChildContainers();

		global $interface;
		$facetName = urldecode($_REQUEST['facetName']);
		$interface->assign('exhibitPid', $pid);
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setDebugging(false, false);
		$searchObject->clearHiddenFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->clearFilters();

		$collectionFilter = "";
		foreach ($childContainers as $childPID){
			if (strlen($collectionFilter > 0)){
				$collectionFilter .= " OR ";
			}
			$collectionFilter .= "RELS_EXT_isMemberOfCollection_uri_ms:\"info:fedora/{$childPID}\" OR RELS_EXT_isMemberOf_uri_mt:\"info:fedora /{$childPID}\"";
		}
		$searchObject->addHiddenFilter("",$collectionFilter);
		$searchObject->clearFacets();
		$searchObject->addFacet($facetName);
		$searchObject->addFacetOptions(array(
				'facet.sort' => 'index'
		));

		$searchObject->setLimit(1);

		$facetValues = array();
		$response = $searchObject->processSearch(true, false);
		if ($response && isset($response['error'])){
			$interface->assign('solrError', $response['error']['msg']);
			$interface->assign('solrLink', $searchObject->getFullSearchUrl());
		}
		if ($response['facet_counts'] && isset($response['facet_counts']['facet_fields'][$facetName])){
			$facetFieldData = $response['facet_counts']['facet_fields'][$facetName];
			foreach ($facetFieldData as $field){
				$searchLink = $searchObject->renderLinkWithFilter("$facetName:$field[0]");
				$facetValues[] = array(
						'display' => $field[0],
						'url' => $searchLink,
						'count' => $field[1]
				);
			}
		}

		$interface->assign('facetValues', $facetValues);
		$results = array(
				'modalBody' => $interface->fetch("Archive/browseFacetPopup.tpl"),
		);
		return $results;
	}

	function getExploreMoreContent(){
		if (!isset($_REQUEST['id'])){
			return array(
					'success' => false,
					'message' => 'You must supply the id to load explore more content for'
			);
		}
		global $interface;
		global $timer;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();
		$pid = urldecode($_REQUEST['id']);
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);
		$timer->logTime("Loaded record driver for main object");

		require_once ROOT_DIR . '/sys/ExploreMore.php';
		$exploreMore = new ExploreMore();
		$exploreMore->loadExploreMoreSidebar('archive', $recordDriver);
		$timer->logTime("Called loadExploreMoreSidebar");

		$relatedSubjects = $recordDriver->getAllSubjectHeadings();

		$ebscoMatches = $exploreMore->loadEbscoOptions('archive', array(), implode($relatedSubjects, " or "));
		if (count($ebscoMatches) > 0){
			$interface->assign('relatedArticles', $ebscoMatches);
		}
		$timer->logTime("Loaded Ebsco options");

		global $library;
		$exploreMoreSettings = $library->exploreMoreBar;
		if (empty($exploreMoreSettings)) {
			$exploreMoreSettings = ArchiveExploreMoreBar::getDefaultArchiveExploreMoreOptions();
		}
		$interface->assign('exploreMoreSettings', $exploreMoreSettings);
		$interface->assign('archiveSections', ArchiveExploreMoreBar::$archiveSections);
		$timer->logTime("Loaded Settings");

		return array(
				'success' => true,
				'exploreMore' => $interface->fetch('explore-more-sidebar.tpl')
		);
	}

	public function getObjectInfo(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pid = urldecode($_REQUEST['id']);
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);

		$url = $recordDriver->getLinkUrl();
		$interface->assign('url', $url);
		$interface->assign('description', $recordDriver->getDescription());
		$interface->assign('image', $recordDriver->getBookcoverUrl('medium'));

		$urlStr = "<a href=\"$url\" onclick='VuFind.Archive.setForExhibitNavigation({$_COOKIE['recordIndex']},{$_COOKIE['page']})'>";
		return array(
			'title' => "{$urlStr}{$recordDriver->getTitle()}</a>",
			'modalBody' => $interface->fetch('Archive/archivePopup.tpl'),
			'modalButtons' => "{$urlStr}<button class='modal-buttons btn btn-primary'>More Info</button></a>"
		);
	}

	public function getMetadata(){
		global $interface;
		$id = urldecode($_REQUEST['id']);
		$interface->assign('pid', $id);

		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$archiveObject = $fedoraUtils->getObject($id);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);

		$this->setMoreDetailsDisplayMode();
		//TODO: Not sure what this code blocks ending effect is to be
		if (array_key_exists('secondaryId', $_REQUEST)){
			$secondaryId = urldecode($_REQUEST['secondaryId']);

			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();

			$secondaryObject = $fedoraUtils->getObject($secondaryId);
			/** @var IslandoraDriver $secondaryDriver */
			$secondaryDriver = RecordDriverFactory::initRecordDriver($secondaryObject);

			$secondaryDriver->getMoreDetailsOptions();
		}
		$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());


		$metadata = $interface->fetch('Archive/moredetails-accordion.tpl');
		return array(
				'success' => true,
				'metadata' => $metadata,
		);
	}

	private function setMoreDetailsDisplayMode(){
		// Set Display Mode for More Details Accordion Related Objects and Entities sections
		global $library, $interface;
		$displayMode = empty($library->archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode) ? 'tiled' : $library->archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode;
		$interface->assign('archiveMoreDetailsRelatedObjectsOrEntitiesDisplayMode', $displayMode);

	}

	public function getNextRandomObject(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pid = $_REQUEST['id'];

		$archiveObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);

		$randomImagePid = $recordDriver->getRandomObject();
		if ($randomImagePid != null){
			$randomObject = RecordDriverFactory::initRecordDriver($fedoraUtils->getObject($randomImagePid));
			$randomObjectInfo = array(
					'label' => $randomObject->getTitle(),
					'link' => $randomObject->getRecordUrl(),
					'image' => $randomObject->getBookcoverUrl('medium')
			);
			$interface->assign('randomObject', $randomObjectInfo);
			return array(
					'success' => true,
					'image' => $interface->fetch('Archive/randomImage.tpl')
			);
		}else{
			return array(
					'success' => false,
					'message' => 'No ID provided'
			);
		}
	}

	public function getTranscript(){
		global $configArray;
		$objectUrl = $configArray['Islandora']['objectUrl'];
		$transcriptIdentifier = urldecode($_REQUEST['transcriptId']);
		if (strlen($transcriptIdentifier) == 0){
			//Check to see if we can get it based on the
			return array(
					'success' => true,
					'transcript' => "There is no transcription available for this page.",
			);
		}elseif (strpos($transcriptIdentifier, 'mods:') === 0){
			$objectPid = str_replace('mods:', '', $transcriptIdentifier);
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();
			$pageObject = $fedoraUtils->getObject($objectPid);
			$mods = $fedoraUtils->getModsData($pageObject);
			$transcript = $fedoraUtils->getModsValue('transcriptionText', 'marmot', $mods);
			if (strlen($transcript) > 0){
				return array(
						'success' => true,
						'transcript' => $transcript,
				);
			}
		}else{
			$transcriptUrl = $objectUrl . '/' . $transcriptIdentifier;
			$transcript = file_get_contents($transcriptUrl);

			if ($transcript) {
				return array(
						'success' => true,
						'transcript' => $transcript,
				);
			}
		}
		return array(
			'success' => false,
		);
	}

	public function getAdditionalRelatedObjects(){
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$pid = $_REQUEST['id'];
		$interface->assign('pid', $pid);
		$archiveObject = $fedoraUtils->getObject($pid);
		/** @var IslandoraDriver $recordDriver */
		$recordDriver = RecordDriverFactory::initRecordDriver($archiveObject);
		$interface->assign('recordDriver', $recordDriver);
		$directlyRelatedObjects = $recordDriver->getDirectlyRelatedArchiveObjects();

		$interface->assign('directlyRelatedObjects', $directlyRelatedObjects);

		$this->setMoreDetailsDisplayMode();

		return array(
				'success' => true,
				'additionalObjects' => $interface->fetch('Archive/additionalRelatedObjects.tpl')
		);
	}

	function getSaveToListForm(){
		global $interface;
		global $user;

		$id = $_REQUEST['id'];
		$interface->assign('id', $id);

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		//Get a list of all lists for the user
		$containingLists = array();
		$nonContainingLists = array();

		$userLists = new UserList();
		$userLists->user_id = $user->id;
		$userLists->deleted = 0;
		$userLists->orderBy('title');
		$userLists->find();
		while ($userLists->fetch()){
			//Check to see if the user has already added the title to the list.
			$userListEntry = new UserListEntry();
			$userListEntry->listId = $userLists->id;
			$userListEntry->groupedWorkPermanentId = $id;
			if ($userListEntry->find(true)){
				$containingLists[] = array(
					'id' => $userLists->id,
					'title' => $userLists->title
				);
			}else{
				$nonContainingLists[] = array(
					'id' => $userLists->id,
					'title' => $userLists->title
				);
			}
		}

		$interface->assign('containingLists', $containingLists);
		$interface->assign('nonContainingLists', $nonContainingLists);

		$results = array(
			'title' => 'Add To List',
			'modalBody' => $interface->fetch("GroupedWork/save.tpl"),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='VuFind.Archive.saveToList(\"{$id}\"); return false;'>Save To List</button>"
		);
		return $results;
	}

	function saveToList(){
		$result = array();

		global $user;
		if ($user === false) {
			$result['success'] = false;
			$result['message'] = 'Please login before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$result['success'] = true;
			$id = urldecode($_REQUEST['id']);
			$listId = $_REQUEST['listId'];
			$notes = $_REQUEST['notes'];

			//Check to see if we need to create a list
			$userList = new UserList();
			$listOk = true;
			if (empty($listId)){
				$userList->title = "My Favorites";
				$userList->user_id = $user->id;
				$userList->public = 0;
				$userList->description = '';
				$userList->insert();
			}else{
				$userList->id = $listId;
				if (!$userList->find(true)){
					$result['success'] = false;
					$result['message'] = 'Sorry, we could not find that list in the system.';
					$listOk = false;
				}
			}

			if ($listOk){
				$userListEntry = new UserListEntry();
				$userListEntry->listId = $userList->id;
				$userListEntry->groupedWorkPermanentId = $id;

				$existingEntry = false;
				if ($userListEntry->find(true)){
					$existingEntry = true;
				}
				$userListEntry->notes = $notes;
				$userListEntry->dateAdded = time();
				if ($existingEntry){
					$userListEntry->update();
				}else{
					$userListEntry->insert();
				}
			}

			$result['success'] = true;
			$result['message'] = 'This title was saved to your list successfully.';
		}

		return $result;
	}

	/**
	 * @param SearchObject_Islandora $searchObject
	 */
	public function setupTimelineFacetsAndFilters($searchObject)
	{
		if (isset($_REQUEST['dateFilter']) && $_REQUEST['dateFilter'] != 'all') {

			$filter = '';
			$date = $_REQUEST['dateFilter'];
			if ($date == 'before1880') {
				$filter .= "(mods_originInfo_dateCreated_dt:[* TO 1879-12-31T23:59:59Z] OR mods_originInfo_point_start_qualifier__dateCreated_dt:[* TO 1879-12-31T23:59:59Z] OR mods_originInfo_point_start_dateCreated_dt:[* TO 1879-12-31T23:59:59Z] OR mods_originInfo_qualifier_approximate_dateCreated_dt:[* TO 1879-12-31T23:59:59Z] OR mods_originInfo_qualifier_questionable_dateCreated_dt:[* TO 1879-12-31T23:59:59Z])";
			} elseif ($date == 'unknown') {
				$filter .= '(-mods_originInfo_dateCreated_dt:[* TO *] AND -mods_originInfo_point_start_qualifier__dateCreated_dt:[* TO *] AND -mods_originInfo_point_start_dateCreated_dt:[* TO *] AND -mods_originInfo_qualifier_approximate_dateCreated_dt:[* TO *] AND -mods_originInfo_qualifier_questionable_dateCreated_dt:[* TO *])';
			} else {
				$startYear = substr($date, 0, 4);
				$endYear = (int)$startYear + 9;
				$filter .= "(mods_originInfo_dateCreated_dt:[$date TO $endYear-12-31T23:59:59Z] OR mods_originInfo_point_start_qualifier__dateCreated_dt:[$date TO $endYear-12-31T23:59:59Z] OR mods_originInfo_point_start_dateCreated_dt:[$date TO $endYear-12-31T23:59:59Z] OR mods_originInfo_qualifier_approximate_dateCreated_dt:[$date TO $endYear-12-31T23:59:59Z] OR mods_originInfo_qualifier_questionable_dateCreated_dt:[$date TO $endYear-12-31T23:59:59Z])";
			}

			if (strlen($filter)){
				$searchObject->addFilter($filter);
			}

		}
		$searchObject->addFacet('mods_originInfo_point_start_qualifier__dateCreated_dt', 'Date Created');
		$searchObject->addFacet('mods_originInfo_point_start_dateCreated_dt', 'Date Created 2');
		$searchObject->addFacet('mods_originInfo_qualifier_approximate_dateCreated_dt', 'Date Created 3');
		$searchObject->addFacet('mods_originInfo_dateCreated_dt', 'Date Created 4');
		$searchObject->addFacet('mods_originInfo_qualifier_questionable_dateCreated_dt', 'Date Created 5');

		$searchObject->addFacetOptions(array(
				'facet.range' => array('mods_originInfo_point_start_qualifier__dateCreated_dt', 'mods_originInfo_dateCreated_dt', 'mods_originInfo_point_start_dateCreated_dt', 'mods_originInfo_qualifier_approximate_dateCreated_dt', 'mods_originInfo_qualifier_questionable_dateCreated_dt'),
				'facet.range.1' => 'mods_originInfo_point_start_dateCreated_dt',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.missing' => 'true',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.range.start' => '1880-01-01T00:00:00Z',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.range.end' => 'NOW/YEAR',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.range.hardend' => 'true',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.range.gap' => '+10YEAR',
				'f.mods_originInfo_point_start_qualifier__dateCreated_dt.facet.range.other' => 'all',
				'f.mods_originInfo_dateCreated_dt.facet.missing' => 'true',
				'f.mods_originInfo_dateCreated_dt.facet.range.start' => '1880-01-01T00:00:00Z',
				'f.mods_originInfo_dateCreated_dt.facet.range.end' => 'NOW/YEAR',
				'f.mods_originInfo_dateCreated_dt.facet.range.hardend' => 'true',
				'f.mods_originInfo_dateCreated_dt.facet.range.gap' => '+10YEAR',
				'f.mods_originInfo_dateCreated_dt.facet.range.other' => 'all',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.missing' => 'true',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.range.start' => '1880-01-01T00:00:00Z',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.range.end' => 'NOW/YEAR',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.range.hardend' => 'true',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.range.gap' => '+10YEAR',
				'f.mods_originInfo_qualifier_questionable_dateCreated_dt.facet.range.other' => 'all',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.missing' => 'true',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.range.start' => '1880-01-01T00:00:00Z',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.range.end' => 'NOW/YEAR',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.range.hardend' => 'true',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.range.gap' => '+10YEAR',
				'f.mods_originInfo_point_start_dateCreated_dt.facet.range.other' => 'all',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.missing' => 'true',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.range.start' => '1880-01-01T00:00:00Z',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.range.end' => 'NOW/YEAR',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.range.hardend' => 'true',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.range.gap' => '+10YEAR',
				'f.mods_originInfo_qualifier_approximate_dateCreated_dt.facet.range.other' => 'all',
		));
	}

	/**
	 * @param $sort
	 * @param SearchObject_Islandora $searchObject
	 */
	public function setupTimelineSorts($sort, $searchObject)
	{
		if ($sort == 'title') {
			$searchObject->setSort('fgs_label_s');
		} elseif ($sort == 'newest') {
			$searchObject->setSort('mods_originInfo_qualifier__dateIssued_dt desc,mods_originInfo_point_start_qualifier__dateCreated_dt desc,mods_originInfo_dateCreated_dt desc,mods_originInfo_point_start_dateCreated_dt desc,mods_originInfo_qualifier_approximate_dateCreated_dt desc, mods_originInfo_qualifier_questionable_dateCreated_dt desc,fgs_label_s asc');
		} elseif ($sort == 'oldest') {
			$searchObject->setSort('mods_originInfo_qualifier__dateIssued_dt asc,mods_originInfo_point_start_qualifier__dateCreated_dt asc,mods_originInfo_dateCreated_dt asc,mods_originInfo_point_start_dateCreated_dt asc,mods_originInfo_qualifier_approximate_dateCreated_dt asc, mods_originInfo_qualifier_questionable_dateCreated_dt asc,fgs_label_s asc');
		} elseif ($sort == 'dateAdded') {
			$searchObject->setSort('fgs_createdDate_dt desc,fgs_label_s asc');
		} elseif ($sort == 'dateModified') {
			$searchObject->setSort('fgs_lastModifiedDate_dt desc,fgs_label_s asc');
		}
	}

	/**
	 * @param $response
	 * @param $interface
	 */
	public function processTimelineData($response, $interface)
	{
		if (isset($response['facet_counts']) && count($response['facet_counts']['facet_ranges']) > 0) {
			$dateFacetInfo = array();
			if (isset($response['facet_counts']['facet_ranges']['mods_originInfo_point_start_qualifier__dateCreated_dt'])) {
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['mods_originInfo_point_start_qualifier__dateCreated_dt'];
				if ($dateCreatedInfo['before'] > 0) {
					$dateFacetInfo['1870'] = array(
							'label' => 'Before 1880',
							'count' => $dateCreatedInfo['before'],
							'value' => 'before1880'
					);
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo) {
					$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = array(
							'label' => substr($facetInfo[0], 0, 4) . '\'s',
							'count' => $facetInfo[1],
							'value' => $facetInfo[0]
					);
				}
			}

			if (isset($response['facet_counts']['facet_ranges']['mods_originInfo_dateCreated_dt'])) {
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['mods_originInfo_dateCreated_dt'];
				if ($dateCreatedInfo['before'] > 0) {
					if (isset($dateFacetInfo['1870'])) {
						$dateFacetInfo['1870']['count'] += $dateCreatedInfo['before'];
					} else {
						$dateFacetInfo['1870'] = array(
								'label' => 'Before 1880',
								'count' => $dateCreatedInfo['before'],
								'value' => 'before1880'
						);
					}
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo) {
					if (isset($dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'])) {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s']['count'] += $facetInfo[1];
					} else {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = array(
								'label' => substr($facetInfo[0], 0, 4) . '\'s',
								'count' => $facetInfo[1],
								'value' => $facetInfo[0]
						);
					}
				}
			}

			if (isset($response['facet_counts']['facet_ranges']['mods_originInfo_point_start_dateCreated_dt'])) {
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['mods_originInfo_point_start_dateCreated_dt'];
				if ($dateCreatedInfo['before'] > 0) {
					if (isset($dateFacetInfo['1870'])) {
						$dateFacetInfo['1870']['count'] += $dateCreatedInfo['before'];
					} else {
						$dateFacetInfo['1870'] = array(
								'label' => 'Before 1880',
								'count' => $dateCreatedInfo['before'],
								'value' => 'before1880'
						);
					}
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo) {
					if (isset($dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'])) {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s']['count'] += $facetInfo[1];
					} else {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = array(
								'label' => substr($facetInfo[0], 0, 4) . '\'s',
								'count' => $facetInfo[1],
								'value' => $facetInfo[0]
						);
					}
				}
			}

			if (isset($response['facet_counts']['facet_ranges']['mods_originInfo_qualifier_approximate_dateCreated_dt'])) {
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['mods_originInfo_qualifier_approximate_dateCreated_dt'];
				if ($dateCreatedInfo['before'] > 0) {
					if (isset($dateFacetInfo['1870'])) {
						$dateFacetInfo['1870']['count'] += $dateCreatedInfo['before'];
					} else {
						$dateFacetInfo['1870'] = array(
								'label' => 'Before 1880',
								'count' => $dateCreatedInfo['before'],
								'value' => 'before1880'
						);
					}
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo) {
					if (isset($dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'])) {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s']['count'] += $facetInfo[1];
					} else {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = array(
								'label' => substr($facetInfo[0], 0, 4) . '\'s',
								'count' => $facetInfo[1],
								'value' => $facetInfo[0]
						);
					}
				}
			}

			if (isset($response['facet_counts']['facet_ranges']['mods_originInfo_qualifier_questionable_dateCreated_dt'])) {
				$dateCreatedInfo = $response['facet_counts']['facet_ranges']['mods_originInfo_qualifier_questionable_dateCreated_dt'];
				if ($dateCreatedInfo['before'] > 0) {
					if (isset($dateFacetInfo['1870'])) {
						$dateFacetInfo['1870']['count'] += $dateCreatedInfo['before'];
					} else {
						$dateFacetInfo['1870'] = array(
								'label' => 'Before 1880',
								'count' => $dateCreatedInfo['before'],
								'value' => 'before1880'
						);
					}
				}
				foreach ($dateCreatedInfo['counts'] as $facetInfo) {
					if (isset($dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'])) {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s']['count'] += $facetInfo[1];
					} else {
						$dateFacetInfo[substr($facetInfo[0], 0, 4) . '\'s'] = array(
								'label' => substr($facetInfo[0], 0, 4) . '\'s',
								'count' => $facetInfo[1],
								'value' => $facetInfo[0]
						);
					}
				}
			}

			//Figure out how many unknown dates there are
			$totalFound = 0;
			foreach($dateFacetInfo as $dateFacet){
				$totalFound += $dateFacet['count'];
			}
			$numUnknown = $response['response']['numFound'] - $totalFound;
			/*if ($numUnknown > 0){
				$dateFacetInfo['Unknown'] = array(
						'label' => 'Unknown',
						'count' => $numUnknown,
						'value' => 'unknown'
				);
			}*/
			$interface->assign('numObjectsWithUnknownDate', $numUnknown);

			ksort($dateFacetInfo);

			$interface->assign('dateFacetInfo', $dateFacetInfo);
		}
	}
}
