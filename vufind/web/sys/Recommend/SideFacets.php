<?php
/**
 *
 * Copyright (C) Villanova University 2010.
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

require_once 'sys/Recommend/Interface.php';

/**
 * SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 */
class SideFacets implements RecommendationInterface
{
	private $searchObject;
	private $facetSettings;
	private $mainFacets;
	private $checkboxFacets;

	/* Constructor
	 *
	 * Establishes base settings for making recommendations.
	 *
	 * @access  public
	 * @param   object  $searchObject   The SearchObject requesting recommendations.
	 * @param   string  $params         Additional settings from the searches.ini.
	 */
	public function __construct($searchObject, $params) {
		// Save the passed-in SearchObject:
		$this->searchObject = $searchObject;

		// Parse the additional settings:
		$params = explode(':', $params);
		$mainSection = empty($params[0]) ? 'Results' : $params[0];
		$checkboxSection = isset($params[1]) ? $params[1] : false;
		$iniName = isset($params[2]) ? $params[2] : 'facets';

		if ($searchObject->getSearchType() == 'genealogy'){
			$config = getExtraConfigArray($iniName);
			$this->mainFacets = isset($config[$mainSection]) ? $config[$mainSection] : array();
		}else{
			$searchLibrary = Library::getActiveLibrary();
			$searchLocation = Location::getActiveLocation();
			$userLocation = Location::getUserHomeLocation();
			$hasSearchLibraryFacets = ($searchLibrary != null && (count($searchLibrary->facets) > 0));
			$hasSearchLocationFacets = ($searchLocation != null && (count($searchLocation->facets) > 0));
			if ($hasSearchLocationFacets){
				$facets = $searchLocation->facets;
			}elseif ($hasSearchLibraryFacets){
				$facets = $searchLibrary->facets;
			}else{
				$facets = Library::getDefaultFacets();
			}
			$this->facetSettings = array();
			$this->mainFacets = array();
			foreach ($facets as $facet){
				$facetName = $facet->facetName;
				//Adjust facet name for local scoping
				if (isset($searchLibrary)){
					if ($facet->facetName == 'time_since_added'){
						$facetName = 'local_time_since_added_' . $searchLibrary->subdomain;
					}elseif ($facet->facetName == 'itype'){
						$facetName = 'itype_' . $searchLibrary->subdomain;
					}elseif ($facet->facetName == 'detailed_location'){
						$facetName = 'detailed_location_' . $searchLibrary->subdomain;
					}elseif ($facet->facetName == 'available_at'){
						$facetName = 'available_' . $searchLibrary->subdomain;
					}
				}
				if (isset($userLocation)){
					if ($facet->facetName == 'available_at'){
						$facetName = 'available_' . $userLocation->code;
					}
				}
				if (isset($searchLocation)){
					if ($facet->facetName == 'time_since_added'){
						$facetName = 'local_time_since_added_' . $searchLocation->code;
					}elseif ($facet->facetName == 'available_at'){
						$facetName = 'available_' . $searchLocation->code;
					}
				}

				//Figure out if the facet should be included
				if ($mainSection == 'Results'){
					if ($facet->showInResults == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet;
						$this->mainFacets[$facetName] = $facet->displayName;
					}
				}elseif ($mainSection == 'Author'){
					if ($facet->showInAuthorResults == 1 && $facet->showAboveResults == 0){
						$this->facetSettings[$facetName] = $facet;
						$this->mainFacets[$facetName] = $facet->displayName;
					}
				}

			}
		}

		$this->checkboxFacets = ($checkboxSection && isset($config[$checkboxSection])) ? $config[$checkboxSection] : array();
	}

	/* init
	 *
	 * Called before the SearchObject performs its main search.  This may be used
	 * to set SearchObject parameters in order to generate recommendations as part
	 * of the search.
	 *
	 * @access  public
	 */
	public function init() {
		// Turn on side facets in the search results:
		foreach($this->mainFacets as $name => $desc) {
			$this->searchObject->addFacet($name, $desc);
		}
		foreach($this->checkboxFacets as $name => $desc) {
			$this->searchObject->addCheckboxFacet($name, $desc);
		}
	}

	/* process
	 *
	 * Called after the SearchObject has performed its main search.  This may be
	 * used to extract necessary information from the SearchObject or to perform
	 * completely unrelated processing.
	 *
	 * @access  public
	 */
	public function process() {
		global $interface;
		global $configArray;

		//Get Facet settings for processing display
		$searchLibrary = Library::getActiveLibrary();
		$searchLocation = Location::getActiveLocation();
		$hasSearchLibraryFacets = ($searchLibrary != null && (count($searchLibrary->facets) > 0));
		$hasSearchLocationFacets = ($searchLocation != null && (count($searchLocation->facets) > 0));
		if ($hasSearchLocationFacets){
			$facets = $searchLocation->facets;
		}elseif ($hasSearchLibraryFacets){
			$facets = $searchLibrary->facets;
		}else{
			$facets = Library::getDefaultFacets();
		}

		$interface->assign('checkboxFilters', $this->searchObject->getCheckboxFacets());
		$interface->assign('filterList', $this->searchObject->getFilterList(true));
		//Process the side facet set to handle the Added In Last facet which we only want to be
		//visible if there is not a value selected for the facet (makes it single select
		$sideFacets = $this->searchObject->getFacetList($this->mainFacets);
		global $librarySingleton;
		$searchLibrary = $librarySingleton->getSearchLibrary();
		$searchLocation = Location::getSearchLocation();

		//Do additional processing of facets for non-genealogy searches
		if ($this->searchObject->getSearchType() != 'genealogy'){
			foreach ($sideFacets as $facetKey => $facet){

				$facetSetting = $this->facetSettings[$facetKey];

				//Do special processing of facets
				if (preg_match('/time_since_added/i', $facetKey)){
					$timeSinceAddedFacet = $this->updateTimeSinceAddedFacet($facet);
					$sideFacets[$facetKey] = $timeSinceAddedFacet;
				}elseif ($facetKey == 'rating_facet'){
					$userRatingFacet = $this->updateUserRatingsFacet($facet);
					$sideFacets[$facetKey] = $userRatingFacet;
				}else{
					//Do other handling of the display
					if ($facetSetting->sortMode == 'alphabetically'){
						asort($sideFacets[$facetKey]['list']);
					}
					if ($facetSetting->numEntriesToShowByDefault > 0){
						$sideFacets[$facetKey]['valuesToShow'] = $facetSetting->numEntriesToShowByDefault;
					}
					if ($facetSetting->showAsDropDown){
						$sideFacets[$facetKey]['showAsDropDown'] = $facetSetting->showAsDropDown;
					}
					if ($facetSetting->useMoreFacetPopup && count($sideFacets[$facetKey]['list']) > 12){
						$sideFacets[$facetKey]['showMoreFacetPopup'] = true;
						$facetsList = $sideFacets[$facetKey]['list'];
						$sideFacets[$facetKey]['list'] = array_slice($facetsList, 0, 5);
						$sortedList = array();
						foreach ($facetsList as $key => $value){
							$sortedList[strtolower($key)] = $value;
						}
						ksort($sortedList);
						$sideFacets[$facetKey]['sortedList'] = $sortedList;
					}else{
						$sideFacets[$facetKey]['showMoreFacetPopup'] = false;
					}
				}
				$sideFacets[$facetKey]['collapseByDefault'] = $facetSetting->collapseByDefault;
			}
		}

		$interface->assign('sideFacetSet', $sideFacets);
	}

	private function updateTimeSinceAddedFacet($timeSinceAddedFacet){
		//See if there is a value selected
		$valueSelected = false;
		foreach ($timeSinceAddedFacet['list'] as $facetKey => $facetValue){
			if (isset($facetValue['isApplied']) && $facetValue['isApplied'] == true){
				$valueSelected = true;
			}
		}
		if ($valueSelected){
			//Get rid of all values except the selected value which will allow the value to be removed
			//We remove the other values because it is confusing to have results both longer and shorter than the current value.
			foreach ($timeSinceAddedFacet['list'] as $facetKey => $facetValue){
				if (!isset($facetValue['isApplied']) || $facetValue['isApplied'] == false){
					unset($timeSinceAddedFacet['list'][$facetKey]);
				}
			}
		}else{
			//Make sure to show all values
			$timeSinceAddedFacet['valuesToShow'] = count($timeSinceAddedFacet['list']);
			//Reverse the display of the list so Day is first and year is last
			$timeSinceAddedFacet['list'] = array_reverse($timeSinceAddedFacet['list']);
		}
		return $timeSinceAddedFacet;
	}

	private function updateUserRatingsFacet($userRatingFacet){
		global $interface;
		$ratingApplied = false;
		foreach ($userRatingFacet['list'] as $facetValue ){
			if ($facetValue['isApplied']){
				$ratingApplied = true;
				$ratingLabels = array($facetValue['value']);
			}
		}
		if (!$ratingApplied){
			$ratingLabels =array('fiveStar','fourStar','threeStar','twoStar','oneStar', 'Unrated');
		}
		$interface->assign('ratingLabels', $ratingLabels);
		return $userRatingFacet;
	}

	/* getTemplate
	 *
	 * This method provides a template name so that recommendations can be displayed
	 * to the end user.  It is the responsibility of the process() method to
	 * populate all necessary template variables.
	 *
	 * @access  public
	 * @return  string      The template to use to display the recommendations.
	 */
	public function getTemplate() {
		return 'Search/Recommend/SideFacets.tpl';
	}
}