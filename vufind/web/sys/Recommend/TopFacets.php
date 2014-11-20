<?php
/**
 *
 * Copyright (C) Villanova University 2009.
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

require_once ROOT_DIR . '/sys/Recommend/Interface.php';

/**
 * TopFacets Recommendations Module
 *
 * This class provides recommendations displaying facets above search results
 */
class TopFacets implements RecommendationInterface
{
	/** @var SearchObject_Solr|SearchObject_Base searchObject */
	private $searchObject;
	private $facetSettings = array();
	private $facets = array();
	private $baseSettings;

	/* Constructor
	 *
	 * Establishes base settings for making recommendations.
	 *
	 * @access  public
	 * @param   object  $searchObject   The SearchObject requesting recommendations.
	 * @param   string  $params         Additional settings from the searches.ini.
	 */
	public function __construct($searchObject, $params)
	{
		global $configArray;
		// Save the basic parameters:
		/** @var SearchObject_Solr|SearchObject_Base searchObject */
		$this->searchObject = $searchObject;

		// Parse the additional parameters:
		$params = explode(':', $params);
		$iniFile = isset($params[1]) ? $params[1] : 'facets';

		// Load the desired facet information:
		$config = getExtraConfigArray($iniFile);
		if ($this->searchObject->getSearchType() == 'genealogy'){
			$this->mainFacets = array();
		}else{
			$searchLibrary = Library::getActiveLibrary();
			global $locationSingleton;
			$searchLocation = $locationSingleton->getActiveLocation();
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
			global $solrScope;
			foreach ($facets as $facet){
				if ($facet->showAboveResults == 1){
					$facetName = $facet->facetName;
					if ($solrScope){
						if ($facet->facetName == 'availability_toggle' && $configArray['Index']['enableDetailedAvailability']){
							$facetName = 'availability_toggle_' . $solrScope;
						}else if ($facet->facetName == 'format_category' && $configArray['Index']['enableDetailedFormats']){
							$facetName = 'format_category_' . $solrScope;
						}
					}
					$this->facets[$facetName] = $facet->displayName;
					$this->facetSettings[$facetName] = $facet;
					if (!$solrScope && $facet->facetName == 'availability_toggle'){
						//Don't do availability toggles in the global scope.
						//TODO: Base this off the default scope (i.e. mercury/responsive).
						unset($this->facets[$facetName]);
						unset($this->facetSettings[$facetName]);
					}
				}
			}
		}

		// Load other relevant settings:
		$this->baseSettings = array(
            'rows' => $config['Results_Settings']['top_rows'],
            'cols' => $config['Results_Settings']['top_cols']
		);
	}

	/* init
	 *
	 * Called before the SearchObject performs its main search.  This may be used
	 * to set SearchObject parameters in order to generate recommendations as part
	 * of the search.
	 *
	 * @access  public
	 */
	public function init()
	{
		// Turn on top facets in the search results:
		foreach($this->facets as $name => $desc) {
			$this->searchObject->addFacet($name, $desc);
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
	public function process()
	{
		global $interface;

		// Grab the facet set -- note that we need to take advantage of the third
		// parameter to getFacetList in order to pass down row and column
		// information for inclusion in the final list.
		$facetList = $this->searchObject->getFacetList($this->facets, false);
		foreach ($facetList as $facetSetkey => $facetSet){
			if ($facetSet['label'] == 'Category' || $facetSet['label'] == 'Format Category'){
				$validCategories = array(
						'Books',
						'eBook',
						'Audio Books',
						'eAudio',
						'Music',
						'Movies',
				);

				//add an image name for display in the template
				foreach ($facetSet['list'] as $facetKey => $facet){
					if (in_array($facetKey,$validCategories)){
						$facet['imageName'] = strtolower(str_replace(' ', '', $facet['value'])) . ".png";
						$facet['imageNameSelected'] = strtolower(str_replace(' ', '', $facet['value'])) . "_selected.png";
						$facetSet['list'][$facetKey] = $facet;
					}else{
						unset($facetSet['list'][$facetKey]);
					}
				}

				uksort($facetSet['list'], "format_category_comparator");

				$facetList[$facetSetkey] = $facetSet;
			}elseif (preg_match('/available/i', $facetSet['label'])){
				$numSelected = 0;
				foreach ($facetSet['list'] as $facetKey => $facet){
					if ($facet['isApplied']){
						$numSelected++;
					}
				}

				//If nothing is selected, select entire collection by default
				$sortedFacetList = array();
				foreach ($facetSet['list'] as $facetKey => $facet){
					if ($facet['value'] == 'Entire Collection'){
						$facet['value'] = 'Local Collection';
						$sortedFacetList[1] = $facet;
					}elseif ($facet['value'] == ''){
						$facet['isApplied'] = $facet['isApplied'] || ($numSelected == 0);
						$facet['value'] = 'Everything';
						$facet['count'] = 0;
						$sortedFacetList[0] = $facet;
						break;
					}else{
						$sortedFacetList[2] = $facet;
					}
				}
				ksort($sortedFacetList);
				$facetSet['list'] = $sortedFacetList;
				$facetList[$facetSetkey] = $facetSet;
			}
		}
		$interface->assign('topFacetSet', $facetList);
		$interface->assign('topFacetSettings', $this->baseSettings);
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
	public function getTemplate()
	{
		return 'Search/Recommend/TopFacets.tpl';
	}
}

function format_category_comparator($a, $b){
	$formatCategorySortOrder = array(
		'Books' => 1,
		'eBook' => 2,
		'Audio Books' => 3,
		'eAudio' => 4,
		'Music' => 5,
		'Movies' => 6,
	);

	$a = $formatCategorySortOrder[$a];
	$b = $formatCategorySortOrder[$b];
	if ($a==$b){return 0;}else{return ($a > $b ? 1 : -1);}
};
?>