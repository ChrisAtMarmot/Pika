<?php
/**
 *
 * Copyright (C) Douglas County Libraries 2011.
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
require_once ROOT_DIR . '/sys/Pager.php';

class SearchAPI extends Action {

	function launch()
	{
		//header('Content-type: application/json');
		header('Content-type: text/html');
		header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

		if (!empty($_GET['method']) && is_callable(array($this, $_GET['method']))) {
			if (in_array($_GET['method'] , array('getSearchBar', 'getListWidget'))){
				$output = $this->$_GET['method']();
			}else{
				$output = json_encode(array('result'=>$this->$_GET['method']()));
			}
		} else {
			$output = json_encode(array('error'=>'invalid_method'));
		}

		echo $output;
	}

	// The time intervals in seconds beyond which we consider the status as not current
	const
		FULL_INDEX_INTERVAL_WARN            = 86400,  // 24 Hours (in seconds)
		FULL_INDEX_INTERVAL_CRITICAL        = 129600, // 36 Hours (in seconds)
		PARTIAL_INDEX_INTERVAL_WARN         = 1500,   // 25 Minutes (in seconds)
		PARTIAL_INDEX_INTERVAL_CRITICAL     = 3600,   // 1 Hour (in seconds)
		OVERDRIVE_EXTRACT_INTERVAL_WARN     = 14400,  // 4 Hours (in seconds)
		OVERDRIVE_EXTRACT_INTERVAL_CRITICAL = 18000,  // 5 Hours (in seconds)
		SOLR_RESTART_INTERVAL_WARN          = 86400,  // 24 Hours (in seconds)
		SOLR_RESTART_INTERVAL_CRITICAL      = 129600, // 36 Hours (in seconds)

		STATUS_OK       = 'okay',
		STATUS_WARN     = 'warning',
		STATUS_CRITICAL = 'critical';


	function getIndexStatus(){
		$notes = array();
		$status = array();

		$currentTime = time();

		// Last Export Valid //
		$lastExportValidVariable = new Variable();
		$lastExportValidVariable->name= 'last_export_valid';
		if ($lastExportValidVariable->find(true)){
			//Check to see if the last export was valid
			if ($lastExportValidVariable->value == false){
				$status[] = self::STATUS_CRITICAL;
				$notes[]  = 'The Last Export was not valid';
			}
		}else{
			$status[] = self::STATUS_WARN;
			$notes[]  = 'Have not checked the export yet to see if it is valid.';
		}

		// Full Index //
		$lastFullIndexVariable = new Variable();
		$lastFullIndexVariable->name= 'lastFullReindexFinish';
		if ($lastFullIndexVariable->find(true)){
			//Check to see if the last full index finished more than FULL_INDEX_INTERVAL seconds ago
			if ($lastFullIndexVariable->value < ($currentTime - self::FULL_INDEX_INTERVAL_WARN)){
				$status[] = ($lastFullIndexVariable->value < ($currentTime - self::FULL_INDEX_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
				$notes[]  = 'Full Index last finished ' . date('m-d-Y H:i:s', $lastFullIndexVariable->value) . ' - ' . round(($currentTime - $lastFullIndexVariable->value) / 3600, 2) . ' hours ago';
			}
		}else{
			$status[] = self::STATUS_WARN;
			$notes[]  = 'Full index has never been run';
			$lastFullIndexVariable = null;
		}

		$fullIndexRunningVariable =  new Variable();
		$fullIndexRunningVariable->name = 'full_reindex_running';
		$fullIndexRunning = false;
		if ($fullIndexRunningVariable->find(true)){
			$fullIndexRunning = $fullIndexRunningVariable->value == 'true';
		}

		//Check to see if a regrouping is running since that will also delay partial indexing
		$recordGroupingRunningVariable =  new Variable();
		$recordGroupingRunningVariable->name = 'record_grouping_running';
		$recordGroupingRunning = false;
		if ($recordGroupingRunningVariable->find(true)){
			$recordGroupingRunning = $recordGroupingRunningVariable->value == 'true';
		}

		//Do not check partial index or overdrive extract if there is a full index running since they pause during that period
		//Also do not check these from 9pm to 7am since between these hours, we're running full indexing and these issues wind up being ok.
		$curHour = date('H');
		if (!$fullIndexRunning && !$recordGroupingRunning && ($curHour >= 7 && $curHour <= 21)) {
			// Partial Index //
			$lastPartialIndexVariable = new Variable();
			$lastPartialIndexVariable->name = 'lastPartialReindexFinish';
			if ($lastPartialIndexVariable->find(true)) {
				//Get the last time either a full or partial index finished
				$lastIndexFinishedWasFull = false;
				$lastIndexTime = $lastPartialIndexVariable->value;
				if ($lastFullIndexVariable && $lastFullIndexVariable->value > $lastIndexTime){
					$lastIndexTime = $lastFullIndexVariable->value;
					$lastIndexFinishedWasFull = true;
				}

				//Check to see if the last partial index finished more than PARTIAL_INDEX_INTERVAL_WARN seconds ago
				if ($lastIndexTime < ($currentTime - self::PARTIAL_INDEX_INTERVAL_WARN)) {
					$status[] = ($lastIndexTime < ($currentTime - self::PARTIAL_INDEX_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;

					if ($lastIndexFinishedWasFull ){
						$notes[] = 'Full Index last finished ' . date('m-d-Y H:i:s', $lastPartialIndexVariable->value) . ' - ' . round(($currentTime - $lastPartialIndexVariable->value) / 60, 2) . ' minutes ago, and a new partial index hasn\'t completed since.';
					}else{
						$notes[] = 'Partial Index last finished ' . date('m-d-Y H:i:s', $lastPartialIndexVariable->value) . ' - ' . round(($currentTime - $lastPartialIndexVariable->value) / 60, 2) . ' minutes ago';
					}
				}
			} else {
				$status[] = self::STATUS_WARN;
				$notes[]  = 'Partial index has never been run';
			}

			// OverDrive Extract //
			$lastOverDriveExtractVariable = new Variable();
			$lastOverDriveExtractVariable->name = 'last_overdrive_extract_time';
			if ($lastOverDriveExtractVariable->find(true)) {
				//Check to see if the last partial index finished more than OVERDRIVE_EXTRACT_INTERVAL_WARN seconds ago
				$lastOverDriveExtractTime = $lastOverDriveExtractVariable->value / 1000;
				if ($lastOverDriveExtractTime < ($currentTime - self::OVERDRIVE_EXTRACT_INTERVAL_WARN)) {
					$status[] = ($lastOverDriveExtractTime < ($currentTime - self::OVERDRIVE_EXTRACT_INTERVAL_CRITICAL)) ? self::STATUS_CRITICAL : self::STATUS_WARN;
					$notes[]  = 'OverDrive Extract last finished ' . date('m-d-Y H:i:s', $lastOverDriveExtractTime) . ' - ' . round(($currentTime - ($lastOverDriveExtractTime)) / 3600, 2) . ' hours ago';
				}
			} else {
				$status[] = self::STATUS_WARN;
				$notes[]  = 'OverDrive Extract has never been run';
			}
		}

		// Solr Restart //
		global $configArray;
		if ($configArray['Index']['engine'] == 'Solr') {
			$xml = @file_get_contents($configArray['Index']['url'] . '/admin/cores');
			if ($xml) {
				$options = array('parseAttributes' => 'true',
				                 'keyAttribute' => 'name');
				$unxml = new XML_Unserializer($options);
				$unxml->unserialize($xml);
				$data = $unxml->getUnserializedData();

				$uptime = $data['status']['grouped']['uptime']['_content']/1000;  // Grouped Index, puts uptime into seconds.
				$solrStartTime = strtotime($data['status']['grouped']['startTime']['_content']);
				if ($uptime >= self::SOLR_RESTART_INTERVAL_WARN){ // Grouped Index
					$status[] = ($uptime >= self::SOLR_RESTART_INTERVAL_CRITICAL) ? self::STATUS_CRITICAL : self::STATUS_WARN;
					$notes[]  = 'Solr Index (Grouped) last restarted ' . date('m-d-Y H:i:s', $solrStartTime) . ' - '. round($uptime / 3600, 2) . ' hours ago';
				}

				$numRecords = $data['status']['grouped']['index']['numDocs']['_content'];

				$minNumRecordVariable = new Variable();
				$minNumRecordVariable->name = 'solr_grouped_minimum_number_records';
				if ($minNumRecordVariable->find(true)) {
					$minNumRecords = $minNumRecordVariable->value;
					if (!empty($minNumRecords)) {
						if ($numRecords < $minNumRecords) {
						$status[] = self::STATUS_CRITICAL;
						$notes[]  = "Solr Index (Grouped) Record Count ($numRecords) in below the minimum ($minNumRecords)";
					} elseif ($numRecords > $minNumRecords + 10000) {
							$status[] = self::STATUS_WARN;
							$notes[]  = "Solr Index (Grouped) Record Count ($numRecords) is more than 10,000 above the minimum ($minNumRecords)";

						}
					}

				} else {
					$status[] = self::STATUS_WARN;
					$notes[]  = 'The minimum number of records for Solr Index (Grouped) has not been set.';
				}

			} else {
				$status[] = self::STATUS_CRITICAL;
				$notes[]  = 'Could not get status from Solr';
			}
		}

		// Unprocessed Offline Circs //
		$offlineCirculationEntry = new OfflineCirculationEntry();
		$offlineCirculationEntry->status = 'Not Processed';
		$offlineCircs = $offlineCirculationEntry->count('id');
		if (!empty($offlineCircs)) {
			$status[] = self::STATUS_CRITICAL;
			$notes[]  = "There are $offlineCircs un-processed offline circulation transactions";
		}

		// Unprocessed Offline Holds //
		$offlineHoldEntry = new OfflineHold();
		$offlineHoldEntry->status = 'Not Processed';
		$offlineHolds = $offlineHoldEntry->count('id');
		if (!empty($offlineHolds)) {
			$status[] = self::STATUS_CRITICAL;
			$notes[]  = "There are $offlineHolds un-processed offline holds";
		}

		if (count($notes) > 0){
			$result = array(
				'status'  => in_array(self::STATUS_CRITICAL, $status) ? self::STATUS_CRITICAL : self::STATUS_WARN, // Criticals trump Warnings;
				'message' => implode('; ',$notes)
			);
		}else{
			$result = array(
				'status'  => self::STATUS_OK,
				'message' => "Everything is current"
			);
		}

		return $result;
	}

	/**
	 * Do a basic search and return results as a JSON array
	 */
	function search()
	{
		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = array();

		// Initialise from the current search globals
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();

		if ($searchObject->getResultTotal() < 1) {
			// No record found
			$interface->setTemplate('list-none.tpl');
			$jsonResults['recordCount'] = 0;

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				// If it's a parse error or the user specified an invalid field, we
				// should display an appropriate message:
				if (stristr($error, 'org.apache.lucene.queryParser.ParseException') ||
				preg_match('/^undefined field/', $error)) {
					$jsonResults['parseError'] = true;

					// Unexpected error -- let's treat this as a fatal condition.
				} else {
					PEAR_Singleton::raiseError(new PEAR_Error('Unable to process query<br />' .
                        'Solr Returned: ' . $error));
				}
			}

			$timer->logTime('no hits processing');

		} else {
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$jsonResults['recordCount'] = $summary['resultTotal'];
			$jsonResults['recordStart'] = $summary['startRecord'];
			$jsonResults['recordEnd'] =   $summary['endRecord'];

			// Big one - our results
			$recordSet = $searchObject->getResultRecordSet();
			//Remove fields as needed to improve the display.
			foreach($recordSet as $recordKey => $record){
				unset($record['auth_author']);
				unset($record['auth_authorStr']);
				unset($record['callnumber-first-code']);
				unset($record['spelling']);
				unset($record['callnumber-first']);
				unset($record['title_auth']);
				unset($record['callnumber-subject']);
				unset($record['author-letter']);
				unset($record['marc_error']);
				unset($record['title_fullStr']);
				unset($record['shortId']);
				$recordSet[$recordKey] = $record;
			}
			$jsonResults['recordSet'] = $recordSet;
			$timer->logTime('load result records');

			$facetSet = $searchObject->getFacetList();
			$jsonResults['facetSet'] =       $facetSet;

			//Check to see if a format category is already set
			$categorySelected = false;
			if (isset($facetSet['top'])){
				foreach ($facetSet['top'] as $title=>$cluster){
					if ($cluster['label'] == 'Category'){
						foreach ($cluster['list'] as $thisFacet){
							if ($thisFacet['isApplied']){
								$categorySelected = true;
							}
						}
					}
				}
			}
			$jsonResults['categorySelected'] = $categorySelected;
			$timer->logTime('finish checking to see if a format category has been loaded already');

			// Process Paging
			$link = $searchObject->renderLinkPageTemplate();
			$options = array('totalItems' => $summary['resultTotal'],
                             'fileName'   => $link,
                             'perPage'    => $summary['perPage']);
			$pager = new VuFindPager($options);
			$jsonResults['paging'] = array(
            	'currentPage' => $pager->pager->_currentPage,
            	'totalPages' => $pager->pager->_totalPages,
            	'totalItems' => $pager->pager->_totalItems,
            	'itemsPerPage' => $pager->pager->_perPage,
			);
			$interface->assign('pageLinks', $pager->getLinks());
			$timer->logTime('finish hits processing');
		}

		// Report additional information after the results
		$jsonResults['query_time'] = 		  round($searchObject->getQuerySpeed(), 2);
		$jsonResults['spellingSuggestions'] = $searchObject->getSpellingSuggestions();
		$jsonResults['lookfor'] =             $searchObject->displayQuery();
		$jsonResults['searchType'] =          $searchObject->getSearchType();
		// Will assign null for an advanced search
		$jsonResults['searchIndex'] =         $searchObject->getSearchIndex();
		$jsonResults['time'] = round($searchObject->getTotalSpeed(), 2);
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$jsonResults['showSaved'] =   true;
		$jsonResults['savedSearch'] = $searchObject->isSavedSearch();
		$jsonResults['searchId'] =    $searchObject->getSearchId();
		$currentPage = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$jsonResults['page'] = $currentPage;


		if ($configArray['Statistics']['enabled'] && isset( $_GET['lookfor'])) {
			require_once(ROOT_DIR . '/Drivers/marmot_inc/SearchStatNew.php');
			$searchStat = new SearchStatNew();
			$type = isset($_GET['type']) ? strip_tags($_GET['type']) : 'Keyword';
			$searchStat->saveSearch( strip_tags($_GET['lookfor']), $type, $searchObject->getResultTotal());
		}

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		// Return the results for display to the user.
		return $jsonResults;
	}

	function getSearchBar(){
		global $interface;
		return $interface->fetch('API/searchbar.tpl');
	}

	function getListWidget(){
		global $interface;
		global $user;
		if (isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$username = $_REQUEST['username'];
			$password = $_REQUEST['password'];
			$user = UserAccount::validateAccount($username, $password);
			$interface->assign('user', $user);
		}
		//Load the widget configuration
		require_once ROOT_DIR . '/sys/ListWidget.php';
		require_once ROOT_DIR . '/sys/ListWidgetList.php';
		require_once ROOT_DIR . '/sys/ListWidgetListsLinks.php';
		$widget = new ListWidget();
		$id = $_REQUEST['id'];

		$widget->id = $id;
		if ($widget->find(true)){
			$interface->assign('widget', $widget);

			if (!empty($_REQUEST['resizeIframe']) || !empty($_REQUEST['resizeiframe'])) {
				$interface->assign('resizeIframe', true);
			}
			//return the widget
			return $interface->fetch('ListWidget/listWidget.tpl');
		}
	}

	/**
	 * Retrieve the top 20 search terms by popularity from the search_stats table
	 * Enter description here ...
	 */
	function getTopSearches(){
		require_once(ROOT_DIR . '/Drivers/marmot_inc/SearchStatNew.php');
		$numSearchesToReturn = isset($_REQUEST['numResults']) ? $_REQUEST['numResults'] : 20;
		$searchStats = new SearchStatNew();
		$searchStats->query("SELECT phrase, numSearches as numTotalSearches FROM `search_stats_new` where phrase != '' order by numTotalSearches DESC LIMIT " . $numSearchesToReturn);
		$searches = array();
		while ($searchStats->fetch()){
			$searches[] = $searchStats->phrase;
		}
		return $searches;
	}

	function getRecordIdForTitle(){
		$title = strip_tags($_REQUEST['title']);
		$_REQUEST['lookfor'] = $title;
		$_REQUEST['type'] = 'Keyword';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = array();

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() < 1){
			return "";
		}else{
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach($recordSet as $recordKey => $record){
				return $record['id'];
			}
		}
	}

	function getRecordIdForItemBarcode(){
		$barcode = strip_tags($_REQUEST['barcode']);
		$_REQUEST['lookfor'] = $barcode;
		$_REQUEST['type'] = 'barcode';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = array();

		// Initialise from the current search globals
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() < 1){
			return "";
		}else{
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach($recordSet as $recordKey => $record){
				return $record['id'];
			}
		}
	}
	
	function getTitleInfoForISBN(){
		$isbn = str_replace('-', '', strip_tags($_REQUEST['isbn']));
		$_REQUEST['lookfor'] = $isbn;
		$_REQUEST['type'] = 'ISN';

		global $interface;
		global $configArray;
		global $timer;

		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/' . $configArray['Index']['engine'] . '.php';
		$timer->logTime('Include search engine');

		//setup the results array.
		$jsonResults = array();

		// Initialise from the current search globals
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init();

		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed
		$interface->setPageTitle('Search Results');
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}

		if ($searchObject->getResultTotal() >= 1){
			//Return the first result
			$recordSet = $searchObject->getResultRecordSet();
			foreach($recordSet as $recordKey => $record){
				$jsonResults[] = array(
					'id' => $record['id'], 
					'title'=> $record['title'], 
					'author' => isset($record['author']) ? $record['author'] : (isset($record['author2']) ? $record['author2'] : ''),
					'format' => isset($record['format']) ? $record['format'] : '',
					'format_category' => isset($record['format_category']) ? $record['format_category'] : '',
				);
			}
		}
		return $jsonResults;
	}
}