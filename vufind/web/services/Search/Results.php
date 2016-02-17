<?php
/**
 *
 * Copyright (C) Andrew Nagy 2009
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
require_once ROOT_DIR . '/services/MyResearch/lib/Search.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/Prospector.php';

require_once ROOT_DIR . '/sys/Pager.php';

class Search_Results extends Action {

	protected $viewOptions = array('list', 'covers');
	// define the valid view modes checked in Base.php

	function launch() {
		global $interface;
		global $configArray;
		global $timer;
		global $analytics;
		global $library;

		/** @var string $searchSource */
		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';

		if (isset($_REQUEST['replacementTerm'])){
			$replacementTerm = $_REQUEST['replacementTerm'];
			$interface->assign('replacementTerm', $replacementTerm);
			$oldTerm = $_REQUEST['lookfor'];
			$interface->assign('oldTerm', $oldTerm);
			$_REQUEST['lookfor'] = $replacementTerm;
			$_GET['lookfor'] = $replacementTerm;
			$oldSearchUrl = $_SERVER['REQUEST_URI'];
			$oldSearchUrl = str_replace('replacementTerm=' . urlencode($replacementTerm), 'disallowReplacements', $oldSearchUrl);
			$interface->assign('oldSearchUrl', $oldSearchUrl);
		}

		$interface->assign('showDplaLink', false);
		if ($configArray['DPLA']['enabled']){
			if ($library->includeDplaResults){
				$interface->assign('showDplaLink', true);
			}
		}


		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Solr.php';
		$timer->logTime('Include search engine');

		//Check to see if the year has been set and if so, convert to a filter and resend.
		$dateFilters = array('publishDate');
		foreach ($dateFilters as $dateFilter){
			if ((isset($_REQUEST[$dateFilter . 'yearfrom']) && !empty($_REQUEST[$dateFilter . 'yearfrom'])) || (isset($_REQUEST[$dateFilter . 'yearto']) && !empty($_REQUEST[$dateFilter . 'yearto']))){
				$queryParams = $_GET;
				$yearFrom = preg_match('/^\d{2,4}$/', $_REQUEST[$dateFilter . 'yearfrom']) ? $_REQUEST[$dateFilter . 'yearfrom'] : '*';
				$yearTo = preg_match('/^\d{2,4}$/', $_REQUEST[$dateFilter . 'yearto']) ? $_REQUEST[$dateFilter . 'yearto'] : '*';
				if (strlen($yearFrom) == 2){
					$yearFrom = '19' . $yearFrom;
				}else if (strlen($yearFrom) == 3){
					$yearFrom = '0' . $yearFrom;
				}
				if (strlen($yearTo) == 2){
					$yearTo = '19' . $yearTo;
				}else if (strlen($yearFrom) == 3){
					$yearTo = '0' . $yearTo;
				}
				if ($yearTo != '*' && $yearFrom != '*' && $yearTo < $yearFrom){
					$tmpYear = $yearTo;
					$yearTo = $yearFrom;
					$yearFrom = $tmpYear;
				}
				unset($queryParams['module']);
				unset($queryParams['action']);
				unset($queryParams[$dateFilter . 'yearfrom']);
				unset($queryParams[$dateFilter . 'yearto']);
				if (!isset($queryParams['sort'])){
					$queryParams['sort'] = 'year';
				}
				$queryParamStrings = array();
				foreach($queryParams as $paramName => $queryValue){
					if (is_array($queryValue)){
						foreach ($queryValue as $arrayValue){
							if (strlen($arrayValue) > 0){
								$queryParamStrings[] = $paramName . '[]=' . $arrayValue;
							}
						}
					}else{
						if (strlen($queryValue)){
							$queryParamStrings[] = $paramName . '=' . $queryValue;
						}
					}
				}
				if ($yearFrom != '*' || $yearTo != '*'){
					$queryParamStrings[] = "&filter[]=$dateFilter:[$yearFrom+TO+$yearTo]";
				}
				$queryParamString = join('&', $queryParamStrings);
				header("Location: {$configArray['Site']['path']}/Search/Results?$queryParamString");
				exit;
			}
		}

		$rangeFilters = array('lexile_score', 'accelerated_reader_reading_level', 'accelerated_reader_point_value');
		foreach ($rangeFilters as $filter){
			if ((isset($_REQUEST[$filter . 'from']) && strlen($_REQUEST[$filter . 'from']) > 0) || (isset($_REQUEST[$filter . 'to']) && strlen($_REQUEST[$filter . 'to']) > 0)){
				$queryParams = $_GET;
				$from = (isset($_REQUEST[$filter . 'from']) && preg_match('/^\d*(\.\d*)?$/', $_REQUEST[$filter . 'from'])) ? $_REQUEST[$filter . 'from'] : '*';
				$to = (isset($_REQUEST[$filter . 'to']) && preg_match('/^\d*(\.\d*)?$/', $_REQUEST[$filter . 'to'])) ? $_REQUEST[$filter . 'to'] : '*';

				if ($to != '*' && $from != '*' && $to < $from){
					$tmpFilter = $to;
					$to = $from;
					$from = $tmpFilter;
				}
				unset($queryParams['module']);
				unset($queryParams['action']);
				unset($queryParams[$filter . 'from']);
				unset($queryParams[$filter . 'to']);
				$queryParamStrings = array();
				foreach($queryParams as $paramName => $queryValue){
					if (is_array($queryValue)){
						foreach ($queryValue as $arrayValue){
							if (strlen($arrayValue) > 0){
								$queryParamStrings[] = $paramName . '[]=' . $arrayValue;
							}
						}
					}else{
						if (strlen($queryValue)){
							$queryParamStrings[] = $paramName . '=' . $queryValue;
						}
					}
				}
				if ($from != '*' || $to != '*'){
					$queryParamStrings[] = "&filter[]=$filter:[$from+TO+$to]";
				}
				$queryParamString = join('&', $queryParamStrings);
				header("Location: {$configArray['Site']['path']}/Search/Results?$queryParamString");
				exit;
			}
		}

		// Cannot use the current search globals since we may change the search term above
		// Display of query is not right when reusing the global search object
		/** @var SearchObject_Solr $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$timer->logTime("Init Search Object");
//		$searchObject->viewOptions = $this->viewOptions; // set valid view options for the search object

		// Build RSS Feed for Results (if requested)
		if ($searchObject->getView() == 'rss') {
			// Throw the XML to screen
			echo $searchObject->buildRSS();
			// And we're done
			exit;
		}else if ($searchObject->getView() == 'excel'){
			// Throw the Excel spreadsheet to screen for download
			echo $searchObject->buildExcel();
			// And we're done
			exit;
		}
		$displayMode = $searchObject->getView();
		if ($displayMode == 'covers') {
			$searchObject->setLimit(24); // a set of 24 covers looks better in display
		}


		// Set Interface Variables
		//   Those we can construct BEFORE the search is executed

		// Hide Covers when the user has set that setting on the Search Results Page
		$this->setShowCovers();

		$displayQuery = $searchObject->displayQuery();
		$pageTitle = $displayQuery;
		if (strlen($pageTitle) > 20){
			$pageTitle = substr($pageTitle, 0, 20) . '...';
		}
		$pageTitle .= ' | Search Results';
		$interface->assign('sortList',   $searchObject->getSortList());
		$interface->assign('rssLink',    $searchObject->getRSSUrl());
		$interface->assign('excelLink',  $searchObject->getExcelUrl());

		$timer->logTime('Setup Search');

		// Process Search
		$result = $searchObject->processSearch(true, true);
		if (PEAR_Singleton::isError($result)) {
			PEAR_Singleton::raiseError($result->getMessage());
		}
		$timer->logTime('Process Search');

		// Some more variables
		//   Those we can construct AFTER the search is executed, but we need
		//   no matter whether there were any results
		$interface->assign('qtime',               round($searchObject->getQuerySpeed(), 2));
		$interface->assign('debugTiming',         $searchObject->getDebugTiming());
		$interface->assign('lookfor',             $displayQuery);
		$interface->assign('searchType',          $searchObject->getSearchType());
		// Will assign null for an advanced search
		$interface->assign('searchIndex',         $searchObject->getSearchIndex());

		// We'll need recommendations no matter how many results we found:
		$interface->assign('topRecommendations',
		$searchObject->getRecommendationsTemplates('top'));
		$interface->assign('sideRecommendations',
		$searchObject->getRecommendationsTemplates('side'));

		// 'Finish' the search... complete timers and log search history.
		$searchObject->close();
		$interface->assign('time', round($searchObject->getTotalSpeed(), 2));
		// Show the save/unsave code on screen
		// The ID won't exist until after the search has been put in the search history
		//    so this needs to occur after the close() on the searchObject
		$interface->assign('showSaved',   true);
		$interface->assign('savedSearch', $searchObject->isSavedSearch());
		$interface->assign('searchId',    $searchObject->getSearchId());
		$currentPage = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$interface->assign('page', $currentPage);

		//Enable and disable functionality based on library settings
		//This must be done before we process each result
		$interface->assign('showNotInterested', false);
		$interface->assign('page_body_style', 'sidebar_left');
		$interface->assign('overDriveVersion', isset($configArray['OverDrive']['interfaceVersion']) ? $configArray['OverDrive']['interfaceVersion'] : 1);

		$showRatings = 1;
		$enableProspectorIntegration = isset($configArray['Content']['Prospector']) ? $configArray['Content']['Prospector'] : false;
		if (isset($library)){
			$enableProspectorIntegration = ($library->enablePospectorIntegration == 1);
			$showRatings = $library->showRatings;
		}
		if ($enableProspectorIntegration){
			$interface->assign('showProspectorLink', true);
			$interface->assign('prospectorSavedSearchId', $searchObject->getSearchId());
		}else{
			$interface->assign('showProspectorLink', false);
		}
		$interface->assign('showRatings', $showRatings);

		$numUnscopedTitlesToLoad = 0;

		// Save the ID of this search to the session so we can return to it easily:
		$_SESSION['lastSearchId'] = $searchObject->getSearchId();

		// Save the URL of this search to the session so we can return to it easily:
		$_SESSION['lastSearchURL'] = $searchObject->renderSearchUrl();

		$allSearchSources = SearchSources::getSearchSources();
		if (!isset($allSearchSources[$searchSource]) && $searchSource == 'marmot'){
			$searchSource = 'local';
		}
		$translatedSearch = $allSearchSources[$searchSource]['name'];

		// Save the search for statistics
		$analytics->addSearch($translatedSearch, $searchObject->displayQuery(), $searchObject->isAdvanced(), $searchObject->getFullSearchType(), $searchObject->hasAppliedFacets(), $searchObject->getResultTotal());


		// No Results Actions //
		if ($searchObject->getResultTotal() < 1) {
			require_once ROOT_DIR . '/services/Search/lib/SearchSuggestions.php';
			$searchSuggestions = new SearchSuggestions();

			$commonSearches = $searchSuggestions->getSpellingSearches($searchObject->displayQuery(), $searchObject->getSearchIndex());
			$suggestions = array();
			foreach ($commonSearches as $commonSearch){
				$suggestions[$commonSearch['phrase']] = '/Search/Results?lookfor=' . urlencode($commonSearch['phrase']);
			}
			$interface->assign('spellingSuggestions', $suggestions);

			//We didn't find anything.  Look for search Suggestions
			//Don't try to find suggestions if facets were applied
			$autoSwitchSearch = false;
			$disallowReplacements = isset($_REQUEST['disallowReplacements']) || isset($_REQUEST['replacementTerm']);
			if (!$disallowReplacements && (!isset($facetSet) || count($facetSet) == 0)){
				//We can try to find a suggestion, but only if we are not doing a phrase search.
				if (strpos($searchObject->displayQuery(), '"') === false){
					require_once ROOT_DIR . '/services/Search/lib/SearchSuggestions.php';
					$searchSuggestions = new SearchSuggestions();
					$commonSearches = $searchSuggestions->getCommonSearchesMySql($searchObject->displayQuery(), $searchObject->getSearchIndex());

					//assign here before we start popping stuff off
					$interface->assign('searchSuggestions', $commonSearches);

					//If the first search in the list is used 10 times more than the next, just show results for that
					$allSuggestions = $searchSuggestions->getAllSuggestions($searchObject->displayQuery(), $searchObject->getSearchIndex());
					$numSuggestions = count($allSuggestions);
					if ($numSuggestions == 1){
						$firstSearch = array_pop($allSuggestions);
						$autoSwitchSearch = true;
					}elseif ($numSuggestions >= 2){
						$firstSearch = array_shift($allSuggestions);
						$secondSearch = array_shift($allSuggestions);
						$firstTimesSearched = $firstSearch['numSearches'];
						$secondTimesSearched = $secondSearch['numSearches'];
						if ($secondTimesSearched > 0 && $firstTimesSearched / $secondTimesSearched > 10){ // avoids division by zero
							$autoSwitchSearch = true;
						}
					}

					//Check to see if the library does not want automatic search replacements
					if (!$library->allowAutomaticSearchReplacements){
						$autoSwitchSearch = false;
					}

					// Switch to search with a better search term //
					if ($autoSwitchSearch){
						//Get search results for the new search
						// The above assignments probably do nothing when there is a redirect below
						$thisUrl = $_SERVER['REQUEST_URI'] . "&replacementTerm=" . urlencode($firstSearch['phrase']);
						header("Location: " . $thisUrl);
						exit();
					}
				}
			}

			// No record found
			$interface->assign('recordCount', 0);

			// Was the empty result set due to an error?
			$error = $searchObject->getIndexError();
			if ($error !== false) {
				// If it's a parse error or the user specified an invalid field, we
				// should display an appropriate message:
				if (stristr($error['msg'], 'org.apache.lucene.queryParser.ParseException') || preg_match('/^undefined field/', $error['msg'])) {
					$interface->assign('parseError', $error['msg']);

					if (preg_match('/^undefined field/', $error['msg'])) {
						// Setup to try as a possible subtitle search
						$fieldName = trim(str_replace('undefined field', '', $error['msg'], $replaced)); // strip out the phrase 'undefined field' to get just the fieldname
						$original = urlencode("$fieldName:");
						if ($replaced === 1 && !empty($fieldName) && strpos($_SERVER['REQUEST_URI'], $original)) {
						// ensure only 1 replacement was done, that the fieldname isn't an empty string, and the label is in fact in the Search URL
							$new = urlencode("$fieldName :"); // include space in between the field name & colon to avoid the parse error
							$thisUrl = str_replace($original, $new, $_SERVER['REQUEST_URI'], $replaced);
							if ($replaced === 1) { // ensure only one modification was made
								header("Location: " . $thisUrl);
								exit();
							}
						}
					}

					// Unexpected error -- let's treat this as a fatal condition.
				} else {
					PEAR_Singleton::raiseError(new PEAR_Error('Unable to process query<br>' .
                        'Solr Returned: ' . print_r($error, true)));
				}
			}

			// Set up to try an Unscoped Search //
			$numUnscopedTitlesToLoad = 10;
			$timer->logTime('no hits processing');

		}
		// Exactly One Result //
		elseif ($searchObject->getResultTotal() == 1 && (strpos($searchObject->displayQuery(), 'id') === 0 || $searchObject->getSearchType() == 'id')){
			//Redirect to the home page for the record
			$recordSet = $searchObject->getResultRecordSet();
			$record = reset($recordSet);
			$_SESSION['searchId'] = $searchObject->getSearchId();
			if ($record['recordtype'] == 'list'){
				$listId = substr($record['id'], 4);
				header("Location: " . $configArray['Site']['path'] . "/MyResearch/MyList/{$listId}");
				exit();
			}else{
				header("Location: " . $configArray['Site']['path'] . "/Record/{$record['id']}/Home");
				exit();
			}

		}
		else {
			$timer->logTime('save search');

			// Assign interface variables
			$summary = $searchObject->getResultSummary();
			$interface->assign('recordCount', $summary['resultTotal']);
			$interface->assign('recordStart', $summary['startRecord']);
			$interface->assign('recordEnd',   $summary['endRecord']);

			$facetSet = $searchObject->getFacetList();
			$interface->assign('facetSet', $facetSet);

			//Check to see if a format category is already set
			$categorySelected = false;
			if (isset($facetSet['top'])){
				foreach ($facetSet['top'] as $cluster){
					if ($cluster['label'] == 'Category'){
						foreach ($cluster['list'] as $thisFacet){
							if ($thisFacet['isApplied']){
								$categorySelected = true;
								break;
							}
						}
					}
					if ($categorySelected) break;
				}
			}
			$interface->assign('categorySelected', $categorySelected);
			$timer->logTime('load selected category');
		}

		// What Mode will search results be Displayed In //
		if ($displayMode == 'covers'){
			$displayTemplate = 'Search/covers-list.tpl'; // structure for bookcover tiles
		} else { // default
			$displayTemplate = 'Search/list-list.tpl'; // structure for regular results
			$displayMode = 'list'; // In case the view is not explicitly set, do so now for display & clients-side functions

			// Process Paging (only in list mode)
			if ($searchObject->getResultTotal() > 1) {
				$link    = $searchObject->renderLinkPageTemplate();
				$options = array('totalItems' => $summary['resultTotal'],
				                 'fileName' => $link,
				                 'perPage' => $summary['perPage']);
				$pager   = new VuFindPager($options);
				$interface->assign('pageLinks', $pager->getLinks());
				if ($pager->isLastPage()) {
					$numUnscopedTitlesToLoad = 5;
				}
			}
		}
		$timer->logTime('finish hits processing');

		$interface->assign('subpage', $displayTemplate);
		$interface->assign('displayMode', $displayMode); // For user toggle switches

		// Big one - our results //
		$recordSet = $searchObject->getResultRecordHTML($displayMode);
		$interface->assign('recordSet', $recordSet);
		$timer->logTime('load result records');

		//Load explore more data
		$this->loadExploreMoreBar();

		if ($configArray['Statistics']['enabled'] && isset( $_GET['lookfor']) && !is_array($_GET['lookfor'])) {
			require_once(ROOT_DIR . '/Drivers/marmot_inc/SearchStatNew.php');
			$searchStat = new SearchStatNew();
			$searchStat->saveSearch( strip_tags($_GET['lookfor']),  strip_tags(isset($_GET['type']) ? $_GET['type'] : (isset($_GET['basicType']) ? $_GET['basicType'] : 'Keyword')), $searchObject->getResultTotal());
		}

		// Done, display the page
		$this->display($searchObject->getResultTotal() ? 'list.tpl' : 'list-none.tpl', $pageTitle, 'Search/results-sidebar.tpl');
	} // End launch()

	function loadExploreMoreBar(){
		if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1){
			return;
		}
		//Get data from the repository
		global $interface;
		global $configArray;
		global $library;
		$exploreMoreOptions = array();

		//Check the archive to see if we match an entity
		if ($library->enableArchive) {
			if (isset($configArray['Islandora']) && isset($configArray['Islandora']['solrUrl']) && !empty($_GET['lookfor']) && !is_array($_GET['lookfor'])) {
				/** @var SearchObject_Islandora $searchObject */
				$searchObject = SearchObjectFactory::initSearchObject('Islandora');
				$searchObject->init();
				$searchObject->setDebugging(false, false);

				//First look specifically for (We cou
				$searchObject->setSearchTerms(array(
						'lookfor' => $_REQUEST['lookfor'],
						'index' => 'IslandoraTitle'
				));
				$searchObject->clearHiddenFilters();
				$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
				//First search for people, places, and things
				$searchObject->addHiddenFilter('RELS_EXT_hasModel_uri_s', "(*placeCModel OR *personCModel OR *eventCModel)");
				$response = $searchObject->processSearch(true, false);
				if ($response && $response['response']['numFound'] > 0) {
					//Check the docs to see if we have a match for a person, place, or event
					foreach ($response['response']['docs'] as $doc){
						$entityDriver = RecordDriverFactory::initRecordDriver($doc);
						$exploreMoreOptions[] = array(
								'title' => $entityDriver->getTitle(),
								'description' => $entityDriver->getTitle(),
								'thumbnail' => $entityDriver->getBookcoverUrl('medium'),
								'link' => $entityDriver->getRecordUrl(),
						);
					}
				}
			}
		}

		if ($library->edsApiProfile){
			//Load EDS options
			require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
			$edsApi = EDS_API::getInstance();
			if ($edsApi->authenticate()){
				//Find related titles
				$query = $_REQUEST['lookfor'];
				$edsResults = $edsApi->getSearchResults($_REQUEST['lookfor']);
				if ($edsResults){
					$numMatches = $edsResults->Statistics->TotalHits;
					if ($numMatches > 0){
						$exploreMoreOptions[] = array(
								'title' => "Articles ({$numMatches})",
								'description' => "Articles related to {$query}",
								'thumbnail' => $configArray['Site']['path'] . '/interface/themes/responsive/images/ebsco_eds.png',
								'link' => '/EBSCO/Results?lookfor=' . urlencode($query)
						);
					}
				}

			}
		}

		if ($library->enableArchive){
			if (isset($configArray['Islandora']) && isset($configArray['Islandora']['solrUrl']) && !empty($_GET['lookfor']) && !is_array($_GET['lookfor'])) {
				require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
				$fedoraUtils = FedoraUtils::getInstance();

				/** @var SearchObject_Islandora $searchObject */
				$searchObject = SearchObjectFactory::initSearchObject('Islandora');
				$searchObject->init();
				$searchObject->setDebugging(false, false);

				//Get a list of objects in the archive related to this search
				$searchObject->setSearchTerms(array(
						'lookfor' => $_REQUEST['lookfor'],
						'index' => 'IslandoraKeyword'
				));
				$searchObject->clearHiddenFilters();
				$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
				$searchObject->clearFilters();
				$searchObject->addFacet('RELS_EXT_hasModel_uri_s', 'Format');
				$searchObject->addFacet('RELS_EXT_isMemberOfCollection_uri_ms', 'Collection');
				$searchObject->addFacet('mods_extension_marmotLocal_relatedEntity_person_entityPid_ms', 'People');
				$searchObject->addFacet('mods_extension_marmotLocal_relatedEntity_place_entityPid_ms', 'Places');
				$searchObject->addFacet('mods_extension_marmotLocal_relatedEntity_event_entityPid_ms', 'Events');

				$response = $searchObject->processSearch(true, false);
				if ($response && $response['response']['numFound'] > 0) {
					//Using the facets, look for related entities
					foreach ($response['facet_counts']['facet_fields']['RELS_EXT_isMemberOfCollection_uri_ms'] as $collectionInfo) {
						$archiveObject = $fedoraUtils->getObject($collectionInfo[0]);
						if ($archiveObject != null) {
							//Check the mods data to see if it should be suppressed in Pika
							$okToAdd = true;
							$mods = FedoraUtils::getInstance()->getModsData($archiveObject);
							if ($mods != null){
								if (count($mods->extension) > 0){
									/** @var SimpleXMLElement $marmotExtension */
									$marmotExtension = $mods->extension->children('http://marmot.org/local_mods_extension');
									if (count($marmotExtension) > 0) {
										$marmotLocal = $marmotExtension->marmotLocal;
										if ($marmotLocal->count() > 0) {
											$pikaOptions = $marmotLocal->pikaOptions;
											if ($pikaOptions->count() > 0) {
												$okToAdd = $pikaOptions->includeInPika != 'no';
											}
										}
									}
								}
							}else{
								//If we don't get mods, exclude from the display
								$okToAdd = false;
							}

							if ($okToAdd){
								$exploreMoreOptions[] = array(
										'title' => $archiveObject->label,
										'description' => $archiveObject->label,
										'thumbnail' => $fedoraUtils->getObjectImageUrl($archiveObject, 'small'),
										'link' => $configArray['Site']['path'] . "/Archive/{$archiveObject->id}/Exhibit",
										'usageCount' => $collectionInfo[1]
								);
							}
						}
					}

					foreach ($response['facet_counts']['facet_fields']['RELS_EXT_hasModel_uri_s'] as $relatedContentType) {
						if ($relatedContentType[0] != 'info:fedora/islandora:collectionCModel' &&
								$relatedContentType[0] != 'info:fedora/islandora:personCModel' &&
								$relatedContentType[0] != 'info:fedora/islandora:placeCModel' &&
								$relatedContentType[0] != 'info:fedora/islandora:eventCModel'
						) {

							/** @var SearchObject_Islandora $searchObject2 */
							$searchObject2 = SearchObjectFactory::initSearchObject('Islandora');
							$searchObject2->init();
							$searchObject2->setDebugging(false, false);
							$searchObject2->setSearchTerms(array(
									'lookfor' => $_REQUEST['lookfor'],
									'index' => 'IslandoraKeyword'
							));
							$searchObject2->clearHiddenFilters();
							$searchObject2->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
							$searchObject2->clearFilters();
							$searchObject2->addFilter("RELS_EXT_hasModel_uri_s:{$relatedContentType[0]}");
							$response2 = $searchObject2->processSearch(true, false);
							if ($response2 && $response2['response']['numFound'] > 0) {
								$firstObject = reset($response2['response']['docs']);
								/** @var IslandoraDriver $firstObjectDriver */
								$firstObjectDriver = RecordDriverFactory::initRecordDriver($firstObject);
								$numMatches = $response2['response']['numFound'];
								$contentType = translate($relatedContentType[0]);
								if ($numMatches == 1) {
									$exploreMoreOptions[] = array(
											'title' => "{$contentType}s ({$numMatches})",
											'description' => "{$contentType}s related to {$searchObject2->getQuery()}",
											'thumbnail' => $firstObjectDriver->getBookcoverUrl('medium'),
											'link' => $firstObjectDriver->getRecordUrl(),
									);
								} else {
									$exploreMoreOptions[] = array(
											'title' => "{$contentType}s ({$numMatches})",
											'description' => "{$contentType}s related to {$searchObject2->getQuery()}",
											'thumbnail' => $firstObjectDriver->getBookcoverUrl('medium'),
											'link' => $searchObject2->renderSearchUrl(),
									);
								}
							}
						}
					}

					if (isset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms'])) {
						$personInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms']);
						$numPeople = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_person_entityPid_ms']);
						if ($numPeople == 100) {
							$numPeople = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($personInfo[0]);
						$searchObject->clearFilters();
						$searchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:personCModel');
						if ($archiveObject != null) {
							$exploreMoreOptions[] = array(
									'title' => "People (" . $numPeople . ")",
									'description' => "People related to {$searchObject->getQuery()}",
									'thumbnail' => $fedoraUtils->getObjectImageUrl($archiveObject, 'small', 'personCModel'),
									'link' => $searchObject->renderSearchUrl(),
									'usageCount' => $numPeople
							);
						}
					}
					if (isset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms'])) {
						$placeInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms']);
						$numPlaces = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_place_entityPid_ms']);
						if ($numPlaces == 100) {
							$numPlaces = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($placeInfo[0]);
						$searchObject->clearFilters();
						$searchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:placeCModel');
						if ($archiveObject != null) {
							$exploreMoreOptions[] = array(
									'title' => "Places (" . $numPeople . ")",
									'description' => "Places related to {$searchObject->getQuery()}",
									'thumbnail' => $fedoraUtils->getObjectImageUrl($archiveObject, 'small', 'placeCModel'),
									'link' => $searchObject->renderSearchUrl(),
									'usageCount' => $numPeople
							);
						}
					}
					if (isset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms'])) {
						$eventInfo = reset($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms']);
						$numEvents = count($response['facet_counts']['facet_fields']['mods_extension_marmotLocal_relatedEntity_event_entityPid_ms']);
						if ($numEvents == 100) {
							$numEvents = '100+';
						}
						$archiveObject = $fedoraUtils->getObject($eventInfo[0]);
						$searchObject->clearFilters();
						$searchObject->addFilter('RELS_EXT_hasModel_uri_s:info:fedora/islandora:eventCModel');
						if ($archiveObject != null) {
							$exploreMoreOptions[] = array(
									'title' => "Events (" . $numEvents . ")",
									'description' => "Places related to {$searchObject->getQuery()}",
									'thumbnail' => $fedoraUtils->getObjectImageUrl($archiveObject, 'small', 'eventCModel'),
									'link' => $searchObject->renderSearchUrl(),
									'usageCount' => $numPeople
							);
						}
					}
				}
			}
			$interface->assign('exploreMoreOptions', $exploreMoreOptions);
		} else {
			global $logger;
			$logger->log('Islandora Search Failed.', PEAR_LOG_WARNING);
		}
	}


}