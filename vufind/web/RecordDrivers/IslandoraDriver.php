<?php

/**
 * Record Driver for display of LargeImages from Islandora
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/9/2015
 * Time: 1:47 PM
 */
require_once ROOT_DIR . '/RecordDrivers/Interface.php';
require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
abstract class IslandoraDriver extends RecordInterface {
	protected $pid = null;
	protected $title = null;

	/** @var AbstractFedoraObject|null */
	protected $archiveObject = null;

	protected $modsData = null;
	/**
	 * Constructor.  We build the object using all the data retrieved
	 * from the (Solr) index.  Since we have to
	 * make a search call to find out which record driver to construct,
	 * we will already have this data available, so we might as well
	 * just pass it into the constructor.
	 *
	 * @param   array|File_MARC_Record||string   $recordData     Data to construct the driver from
	 * @access  public
	 */
	public function __construct($recordData) {

		if ($recordData instanceof AbstractFedoraObject){
			$this->archiveObject = $recordData;
			$this->pid = $this->archiveObject->id;
			$this->title = $this->archiveObject->label;
		}elseif (is_array($recordData)){
			$this->pid = $recordData['PID'];
			$this->title = isset($recordData['fgs_label_s']) ? $recordData['fgs_label_s'] : (isset($recordData['dc.title']) ? $recordData['dc.title'] : "");
		}else{
			$this->pid = $recordData;
		}

		global $configArray;
		// Load highlighting/snippet preferences:
		$searchSettings = getExtraConfigArray('searches');
		$this->highlight = $configArray['Index']['enableHighlighting'];
		$this->snippet = $configArray['Index']['enableSnippets'];
		$this->snippetCaptions = isset($searchSettings['Snippet_Captions']) && is_array($searchSettings['Snippet_Captions']) ? $searchSettings['Snippet_Captions'] : array();
	}

	function getArchiveObject(){
		$fedoraUtils = FedoraUtils::getInstance();
		if ($this->archiveObject == null && $this->pid != null){
			$this->archiveObject = $fedoraUtils->getObject($this->pid);
		}
		return $this->archiveObject;
	}

	private $islandoraObjectCache = null;

	/**
	 * @return IslandoraObjectCache
	 */
	private function getCachedData(){
		if ($this->islandoraObjectCache == null) {
			require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
			$this->islandoraObjectCache = new IslandoraObjectCache();
			$this->islandoraObjectCache->pid = $this->pid;
			if (!$this->islandoraObjectCache->find(true)){
				$this->islandoraObjectCache = new IslandoraObjectCache();
				$this->islandoraObjectCache->pid = $this->pid;
				$this->islandoraObjectCache->insert();
			}
		}
		return $this->islandoraObjectCache;
	}

	function getBookcoverUrl($size = 'small'){
		global $configArray;

		$cachedData = $this->getCachedData();
		if ($cachedData && !isset($_REQUEST['reload'])){
			if ($size == 'small' && $cachedData->smallCoverUrl != ''){
				return $cachedData->smallCoverUrl;
			}elseif ($size == 'medium' && $cachedData->mediumCoverUrl != ''){
				return $cachedData->mediumCoverUrl;
			}elseif ($size == 'large' && $cachedData->largeCoverUrl != ''){
				return $cachedData->largeCoverUrl;
			}
		}

		$archiveObject = $this->getArchiveObject();
		if ($archiveObject == null){
			return $this->getPlaceholderImage();
		}

		$objectUrl = $configArray['Islandora']['objectUrl'];
		if ($size == 'small'){
			if ($archiveObject->getDatastream('SC') != null){
				$cachedData->smallCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/SC/view';
			}elseif ($archiveObject->getDatastream('TN') != null){
				$cachedData->smallCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/TN/view';
			}else{
				//return a placeholder
				$cachedData->smallCoverUrl = $this->getPlaceholderImage();
			}
			$cachedData->update();
			return $cachedData->smallCoverUrl;

		}elseif ($size == 'medium'){
			if ($archiveObject->getDatastream('MC') != null){
				$cachedData->mediumCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/MC/view';
			}elseif ($archiveObject->getDatastream('PREVIEW') != null) {
				$cachedData->mediumCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/PREVIEW/view';
			}elseif ($archiveObject->getDatastream('TN') != null){
				$cachedData->mediumCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/TN/view';
			}else{
				$cachedData->mediumCoverUrl = $this->getPlaceholderImage();
			}
			$cachedData->update();
			return $cachedData->mediumCoverUrl;
		}elseif ($size == 'large'){
			if ($archiveObject->getDatastream('JPG') != null){
				$cachedData->largeCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/JPG/view';
			}elseif ($archiveObject->getDatastream('LC') != null) {
				$cachedData->largeCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/LC/view';
			}elseif ($archiveObject->getDatastream('PREVIEW') != null) {
				$cachedData->largeCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/PREVIEW/view';
			}elseif ($archiveObject->getDatastream('OBJ') != null && ($this->archiveObject->getDatastream('OBJ')->mimetype == 'image/jpg' || $this->archiveObject->getDatastream('OBJ')->mimetype == 'image/jpeg')) {
				$cachedData->largeCoverUrl = $objectUrl . '/' . $this->getUniqueID() . '/datastream/OBJ/view';
			}else{
				$cachedData->largeCoverUrl = $this->getPlaceholderImage();
			}
			$cachedData->update();
			return $cachedData->largeCoverUrl;
		}elseif ($size == 'original'){
			if ($archiveObject->getDatastream('OBJ') != null) {
				return $objectUrl . '/' . $this->getUniqueID() . '/datastream/OBJ/view';
			}
		}else{
			return $this->getPlaceholderImage();
		}
	}

	/**
	 * Get text that can be displayed to represent this record in
	 * breadcrumbs.
	 *
	 * @access  public
	 * @return  string              Breadcrumb text to represent this record.
	 */
	public function getBreadcrumb() {
		return $this->getTitle();
	}

	public function getBrowseResult(){
		global $interface;
		$id = $this->getUniqueID();
		$interface->assign('summId', $id);

		$url = $this->getLinkUrl();

		$interface->assign('summUrl', $url);
		$interface->assign('summTitle', $this->getTitle());
//		$interface->assign('summAuthor', null); // Commented out in the template for now. plb 8-25-2016

//		$interface->assign('summFormat', $this->getFormat()); // Not used in the template below. plb 8-25-2016

		//Get Book Covers
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		return 'RecordDrivers/Islandora/browse_result.tpl';
	}
	public function getListWidgetTitle(){
		$widgetTitleInfo = array(
			'id' =>          $this->getUniqueID(),
			'shortId' =>     $this->getUniqueID(),
			'recordtype' => 'archive', //TODO: meh, islandora?
			'image' =>       $this->getBookcoverUrl('medium'),
			'small_image' => $this->getBookcoverUrl('small'),
			'title' =>       $this->getTitle(),
		  'titleURL' =>    $this->getLinkUrl(true), // Include site URL
//			'author' =>      $this->getPrimaryAuthor(),
			'author' =>      $this->getFormat(), // Display the Format of Archive Object where the author would be otherwise displayed in the ListWidget
			'description' => $this->getDescription(),
			'length' =>      '', // TODO: do list widgets use this
			'publisher' =>   '', // TODO: do list widgets use this
			'ratingData' =>  null,
//			'ratingData' =>  $this->getRatingData(),
		);
		return $widgetTitleInfo;
	}

	/**
	 * Assign necessary Smarty variables and return a template name
	 * to load in order to display the requested citation format.
	 * For legal values, see getCitationFormats().  Returns null if
	 * format is not supported.
	 *
	 * @param   string $format Citation format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getCitation($format) {
		// TODO: Implement getCitation() method.
	}

	/**
	 * Get an array of strings representing citation formats supported
	 * by this record's data (empty if none).  Legal values: "APA", "MLA".
	 *
	 * @access  public
	 * @return  array               Strings representing citation formats.
	 */
	public function getCitationFormats() {
		// TODO: Implement getCitationFormats() method.
	}

	/**
	 * Get the text to represent this record in the body of an email.
	 *
	 * @access  public
	 * @return  string              Text for inclusion in email.
	 */
	public function getEmail() {
		// TODO: Implement getEmail() method.
	}

	/**
	 * Get any excerpts associated with this record.  For details of
	 * the return format, see sys/Excerpts.php.
	 *
	 * @access  public
	 * @return  array               Excerpt information.
	 */
	public function getExcerpts() {
		// TODO: Implement getExcerpts() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to export the record in the requested format.  For
	 * legal values, see getExportFormats().  Returns null if format is
	 * not supported.
	 *
	 * @param   string $format Export format to display.
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getExport($format) {
		// TODO: Implement getExport() method.
	}

	/**
	 * Get an array of strings representing formats in which this record's
	 * data may be exported (empty if none).  Legal values: "RefWorks",
	 * "EndNote", "MARC", "RDF".
	 *
	 * @access  public
	 * @return  array               Strings representing export formats.
	 */
	public function getExportFormats() {
		// TODO: Implement getExportFormats() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display extended metadata (more details beyond
	 * what is found in getCoreMetadata() -- used as the contents of the
	 * Description tab of the record view).
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getExtendedMetadata() {
		// TODO: Implement getExtendedMetadata() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param   object $user User object owning tag/note metadata.
	 * @param   int $listId ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param   bool $allowEdit Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
//	public function getListEntry($user, $listId = null, $allowEdit = true) {
//		// TODO: Implement getListEntry() method.
//	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * user's favorites list.
	 *
	 * @access  public
	 * @param   object $user User object owning tag/note metadata.
	 * @param   int $listId ID of list containing desired tags/notes (or
	 *                              null to show tags/notes from all user's lists).
	 * @param   bool $allowEdit Should we display edit controls?
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getListEntry($user, $listId = null, $allowEdit = true) {
		global $interface;

		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		$interface->assign('jquerySafeId', str_replace(':', '_', $id)); // make id safe for jquery & css calls
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('module', $this->getModule());
		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());

		// The below template variables are in the listentry.tpl but the driver doesn't currently
		// supply this information, so we are making sure they are set to a null value.
		$interface->assign('summShortId', null);
		$interface->assign('summTitleStatement', null);
		$interface->assign('summAuthor', null);
		$interface->assign('summPublisher', null);
		$interface->assign('summPubDate', null);
		$interface->assign('$summSnippets', null);


		//Determine the cover to use
//		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));


		//Get information from list entry
		if ($listId) {
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$listEntry                         = new UserListEntry();
			$listEntry->groupedWorkPermanentId = $this->getUniqueID();
			$listEntry->listId                 = $listId;
			if ($listEntry->find(true)) {
				$interface->assign('listEntryNotes', $listEntry->notes);
			}
			$interface->assign('listEditAllowed', $allowEdit);
		}
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		// By default, do not display AJAX status; we won't assume that all
		// records exist in the ILS.  Child classes can override this setting
		// to turn on AJAX as needed:
		$interface->assign('summAjaxStatus', false);

		$interface->assign('recordDriver', $this);

		return 'RecordDrivers/Islandora/listentry.tpl';
	}


	public function getModule() {
		return 'Archive';
	}

	/**
	 * Get an XML RDF representation of the data in this record.
	 *
	 * @access  public
	 * @return  mixed               XML RDF data (false if unsupported or error).
	 */
	public function getRDFXML() {
		// TODO: Implement getRDFXML() method.
	}

	/**
	 * Get any reviews associated with this record.  For details of
	 * the return format, see sys/Reviews.php.
	 *
	 * @access  public
	 * @return  array               Review information.
	 */
	public function getReviews() {
		// TODO: Implement getReviews() method.
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display a summary of the item suitable for use in
	 * search results.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getSearchResult($view = 'list') {
		if ($view == 'covers') { // Displaying Results as bookcover tiles
			return $this->getBrowseResult();
		}

		global $interface;
		$id = $this->getUniqueID();
		$interface->assign('summId', $id);
		$interface->assign('summTitle', $this->getTitle());
		$interface->assign('module', $this->getModule());

		$linkUrl = $this->getLinkUrl();
		$linkUrl .= '?searchId=' . $interface->get_template_vars('searchId') . '&amp;recordIndex=' . $interface->get_template_vars('recordIndex') . '&amp;page='  . $interface->get_template_vars('page');

		$interface->assign('summUrl', $linkUrl);
//		$interface->assign('summUrl', $this->getLinkUrl());
		$interface->assign('summDescription', $this->getDescription());
		$interface->assign('summFormat', $this->getFormat());

		//Determine the cover to use
		$interface->assign('bookCoverUrl', $this->getBookcoverUrl('small'));
		$interface->assign('bookCoverUrlMedium', $this->getBookcoverUrl('medium'));

		return 'RecordDrivers/Islandora/result.tpl';
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display the full record information on the Staff
	 * View tab of the record view page.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getStaffView() {
		// TODO: Implement getStaffView() method.
	}

	public function getTitle() {
		if (empty($this->title)){
			$archiveObject = $this->getArchiveObject();
			if ($archiveObject){
				$this->title = $this->getArchiveObject()->label;
			}else{
				$this->title = 'Invalid Object';
			}
		}

		return $this->title;
	}

	private $fullTitle = null;
	public function getFullTitle() {
		if (empty($this->fullTitle)){
			$titleInfo = $this->getModsValue('titleInfo','mods');
			$title = $this->getModsValue('title','mods', $titleInfo);
			$subTitle = $this->getModsValue('subTitle','mods', $titleInfo);
			$this->fullTitle = $title;
			if ($subTitle){
				$this->fullTitle .= ": " . $subTitle;
			}
		}

		return $this->fullTitle;
	}

	/**
	 * Assign necessary Smarty variables and return a template name to
	 * load in order to display the Table of Contents extracted from the
	 * record.  Returns null if no Table of Contents is available.
	 *
	 * @access  public
	 * @return  string              Name of Smarty template file to display.
	 */
	public function getTOC() {
		// TODO: Implement getTOC() method.
	}

	/**
	 * Return the unique identifier of this record within the Solr index;
	 * useful for retrieving additional information (like tags and user
	 * comments) from the external MySQL database.
	 *
	 * @access  public
	 * @return  string              Unique identifier.
	 */
	public function getUniqueID() {
		return $this->pid;
	}

	public function getType(){
		$id = $this->getUniqueID();
		list($type) = explode(':', $id, 2);
		return $type;
	}

	/**
	 * Does this record have audio content available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasAudio() {
		return false;
	}

	/**
	 * Does this record have an excerpt available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasExcerpt() {
		return false;
	}

	/**
	 * Does this record have searchable full text in the index?
	 *
	 * Note: As of this writing, searchable full text is not a VuFind feature,
	 *       but this method will be useful if/when it is eventually added.
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasFullText() {
		return false;
	}

	/**
	 * Does this record have image content available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasImages() {
		return true;
	}

	/**
	 * Does this record support an RDF representation?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasRDF() {
		// TODO: Implement hasRDF() method.
	}

	/**
	 * Does this record have reviews available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasReviews() {
		return false;
	}

	/**
	 * Does this record have a Table of Contents available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasTOC() {
		return false;
	}

	/**
	 * Does this record have video content available?
	 *
	 * @access  public
	 * @return  bool
	 */
	public function hasVideo() {
		return false;
	}

	public function getDescription() {
		if (isset($this->fields['mods_abstract_s'])){
			return $this->fields['mods_abstract_s'];
		} else{
			$modsData = $this->getModsData();
			return $this->getModsValue('abstract', 'mods');
		}
	}

	public function getBaseMoreDetailsOptions($isbn_ignored = null){
		global $interface;
		global $configArray;
		$moreDetailsOptions = array();

		$description = html_entity_decode($this->getDescription());
		$description = str_replace("\r\n", '<br>', $description);
		$description = str_replace("&#xD;", '<br>', $description);
		if (strlen($description)){
			$interface->assign('description', $description);
			$moreDetailsOptions['description'] = array(
				'label' => 'Description',
				'body' => $description,
				'hideByDefault' => false,
			);
		}

		//This call loads transcriptions and also assigns to the interface
		$transcriptions = $this->loadTranscription();
		if (count($transcriptions)) {
			$moreDetailsOptions['transcription'] = array(
				'label' => 'Transcription',
				'body' => $interface->fetch('Archive/transcriptionSection.tpl'),
				'hideByDefault' => false,
			);
		}

		if ($this->loadCorrespondenceInfo()) {
			$moreDetailsOptions['correspondence'] = array(
				'label' => 'Correspondence Information',
				'body' => $interface->fetch('Archive/correspondenceInfoSection.tpl'),
				'hideByDefault' => false
			);
		}

		if ($this->loadAcademicResearchData()) {
			$moreDetailsOptions['academicResearch'] = array(
				'label' => 'Academic Research Information',
				'body' => $interface->fetch('Archive/academicResearchSection.tpl'),
				'hideByDefault' => false
			);
		}

		if ($this->loadArtInformation()) {
			$moreDetailsOptions['artworkDetails'] = array(
					'label' => 'Art Information',
					'body' => $interface->fetch('Archive/artworkDetailsSection.tpl'),
					'hideByDefault' => false
			);
		}

		$directlyRelatedObjects = $this->getDirectlyRelatedArchiveObjects();
		$existingValue = $interface->getVariable('directlyRelatedObjects');
		if ($existingValue != null){
			$directlyRelatedObjects['numFound'] += $existingValue['numFound'];
			$directlyRelatedObjects['objects'] = array_merge($existingValue['objects'], $directlyRelatedObjects['objects']);
		}
		if ($directlyRelatedObjects['numFound'] > 0) {
			$interface->assign('directlyRelatedObjects', $directlyRelatedObjects);
			$moreDetailsOptions['relatedObjects'] = array(
				'label' => 'Related Objects',
				'body' => $interface->fetch('Archive/relatedObjectsSection.tpl'),
				'hideByDefault' => false
			);
		}

		$this->loadLinkedData();
		if (count($interface->getVariable('obituaries'))) {
			$moreDetailsOptions['obituaries'] = array(
				'label' => 'Obituaries',
				'body' => $interface->fetch('Archive/obituariesSection.tpl'),
				'hideByDefault' => false
			);
		}
		if ($this->showBurialData) { //TODO: May need a more extensive check
			$moreDetailsOptions['burialDetails'] = array(
				'label' => 'Burial Details',
				'body' => $interface->fetch('Archive/burialDetailsSection.tpl'),
				'hideByDefault' => false
			);
		}
		//See if we need another section for wikipedia content.
		if (count($interface->getVariable('wikipediaData'))){
			if (strlen($description) > 0) {
				$moreDetailsOptions['wikipedia'] = array(
						'label' => 'From Wikipedia',
						'body' => $interface->fetch('Archive/wikipediaSection.tpl'),
						'hideByDefault' => false
				);
			}else{
				$moreDetailsOptions['description'] = array(
						'label' => 'Description',
						'body' => $interface->fetch('Archive/wikipediaSection.tpl'),
						'hideByDefault' => false,
				);
			}
		}


		$relatedEvents = $this->getRelatedEvents();
		$relatedPeople = $this->getRelatedPeople();
		$productionTeam = $this->getProductionTeam();
		$relatedOrganizations = $this->getRelatedOrganizations();
		$relatedPlaces = $this->getRelatedPlaces();
		$creators = $this->getCreators();

		//Sort all the related information
		usort($relatedEvents, 'ExploreMore::sortRelatedEntities');
		usort($relatedPeople, 'ExploreMore::sortRelatedEntities');
		usort($productionTeam, 'ExploreMore::sortRelatedEntities');
		usort($relatedOrganizations, 'ExploreMore::sortRelatedEntities');
		usort($relatedPlaces, 'ExploreMore::sortRelatedEntities');
		usort($creators, 'ExploreMore::sortRelatedEntities');

		//Do final assignment
		$temp                 = $this->mergeEntities($interface->getVariable('relatedPeople'), $relatedPeople);
		$interface->assign('relatedPeople', $temp);
		// Marriage data comes from getLinkedData()
		if ($temp){
			$moreDetailsOptions['relatedPeople'] = array(
				'label' => 'Related People',
				'body' => $interface->fetch('Archive/relatedPeopleSection.tpl'),
				'hideByDefault' => false
			);
		}

		if ($relatedOrganizations) {
			$interface->assign('relatedItems', $this->mergeEntities($interface->getVariable('relatedOrganizations'), $relatedOrganizations));
			$moreDetailsOptions['relatedOrganizations'] = array(
				'label' => 'Related Organizations',
				'body' => $interface->fetch('Archive/accordion-items.tpl'),
				'hideByDefault' => false
			);
		}

		$relatedPlaces = $this->mergeEntities($interface->getVariable('relatedPlaces'), $relatedPlaces);
		if ($relatedPlaces && $this->getType() != 'event') {
			$interface->assign('relatedPlaces', $relatedPlaces);
			$moreDetailsOptions['relatedPlaces'] = array(
				'label' => 'Related Places',
				'body' => $interface->fetch('Archive/relatedPlacesSection.tpl'),
				'hideByDefault' => false
			);
		}

		$relatedEvents = $this->mergeEntities($interface->getVariable('relatedEvents'), $relatedEvents);
		if ($relatedEvents /*&& $recordDriver->getType() != 'event'*/) {
			$interface->assign('relatedItems', $relatedEvents);
			$moreDetailsOptions['relatedEvents'] = array(
				'label' => 'Related Events',
				'body' => $interface->fetch('Archive/accordion-items.tpl'),
				'hideByDefault' => false
			);
		}

		if ($this->loadMilitaryServiceData()) {
			$moreDetailsOptions['militaryService'] = array(
				'label' => 'Military Service',
				'body' => $interface->fetch('Archive/militaryServiceSection.tpl'),
				'hideByDefault' => false
			);
		}

		$this->loadNotes();
		if (count($interface->getVariable('notes'))) {
			$moreDetailsOptions['notes'] = array(
				'label' => 'Notes',
				'body' => $interface->fetch('Archive/notesSection.tpl'),
				'hideByDefault' => false
			);
		}

		//		$this->formattedSubjects = $this->getAllSubjectsWithLinks();
//		$interface->assignAppendToExisting('subjects', $this->formattedSubjects);

		$interface->assignAppendToExisting('subjects', $this->getAllSubjectsWithLinks());
		if (count($interface->getVariable('subjects'))) {
			$moreDetailsOptions['subject'] = array(
				'label' => 'Subjects',
				'body' => $interface->fetch('Archive/subjectsSection.tpl'),
				'hideByDefault' => false
			);
		}

		$productionTeam  = $this->mergeEntities($interface->getVariable('productionTeam'), $productionTeam);
		if ($productionTeam) {
			$interface->assign('productionTeam', $productionTeam);
			$moreDetailsOptions['acknowledgements'] = array(
				'label' => 'Acknowledgements',
				'body' => $interface->fetch('Archive/acknowledgementsSection.tpl'),
				'hideByDefault' => false
			);
		}

		$visibleLinks = $this->getVisibleLinks();
		$interface->assignAppendToExisting('externalLinks', $visibleLinks);
		if (count($interface->getVariable('externalLinks'))) {
			$moreDetailsOptions['externalLinks'] = array(
				'label' => 'Links',
				'body' => $interface->fetch('Archive/externalLinksSection.tpl'),
				'hideByDefault' => false
			);
		}

		if ($this->loadRecordInfo()) {
			$temp                 = $this->mergeEntities($interface->getVariable('creators'), $creators);
			$interface->assign('creators', $temp);

			$interface->assign('unlinkedEntities', $this->unlinkedEntities);
			if ((count($interface->getVariable('creators')) > 0)
					|| $this->hasDetails
					|| (count($interface->getVariable('marriages')) > 0)
					|| (count($this->unlinkedEntities) > 0)){
				$moreDetailsOptions['details'] = array(
						'label' => 'Details',
						'body' => $interface->fetch('Archive/detailsSection.tpl'),
						'hideByDefault' => false
				);
			}

			$moreDetailsOptions['moreDetails'] = array(
					'label' => 'More Details',
					'body' => $interface->fetch('Archive/moreDetailsSection.tpl'),
					'hideByDefault' => false
			);
		}

		$this->loadRightsStatements();
		if (count($interface->getVariable('rightsStatements'))) {
			$moreDetailsOptions['rightsStatements'] = array(
				'label' => 'Rights Statements',
				'body' => $interface->fetch('Archive/rightsStatementsSection.tpl'),
				'hideByDefault' => false
			);
		}

		$repositoryLink = $configArray['Islandora']['repositoryUrl'] . '/islandora/object/' . $this->getUniqueID();
		$interface->assign('repositoryLink', $repositoryLink);
		global $user;
		if($user && ($user->hasRole('archives') || $user->hasRole('opacAdmin') || $user->hasRole('libraryAdmin'))) {
			$moreDetailsOptions['staffView'] = array(
				'label' => 'Staff View',
				'body' => $interface->fetch('Archive/staffViewSection.tpl'),
				'hideByDefault' => false
			);
		}

		//Do the filtering and sorting here so subclasses can use this directly
		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	public function getMoreDetailsOptions() {
		//Load more details options
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions();

		return $moreDetailsOptions;
		// Doesn't need filtered twice.
//		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	public function filterAndSortMoreDetailsOptions($allOptions) {
		global $library;

		$useDefault = true;
		$moreDetailsFilters = array();
		if ($library && count($library->archiveMoreDetailsOptions) > 0){
			$useDefault = false;
			/** @var LibraryArchiveMoreDetails $option */
			foreach ($library->archiveMoreDetailsOptions as $option){
				$moreDetailsFilters[$option->section] = $option->collapseByDefault ? 'closed' : 'open';
			}
		}

		if ($useDefault){
			/** @var LibraryArchiveMoreDetails[] $defaultDetailsFilters */
			$defaultDetailsFilters = LibraryArchiveMoreDetails::getDefaultOptions($library->libraryId);
//			$moreDetailsFilters = RecordInterface::getDefaultMoreDetailsOptions();
			foreach ($defaultDetailsFilters as $filter) {
				$moreDetailsFilters[$filter->section] = $filter->collapseByDefault ? 'closed' : 'open';
			}

		}

		$filteredMoreDetailsOptions = array();
		foreach ($moreDetailsFilters as $option => $initialState){
			if (array_key_exists($option, $allOptions)){
				$detailOptions = $allOptions[$option];
				$detailOptions['openByDefault'] = $initialState == 'open';
				$filteredMoreDetailsOptions[$option] = $detailOptions;
			}
		}
		return $filteredMoreDetailsOptions;
	}

	public function getItemActions($itemInfo) {
		return array();
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $relatedUrls = null) {
		return array();
	}

//	public function getLinkUrl($unscoped = false) {
	public function getLinkUrl($absolutePath = false) {  // Signature is modeled after Grouped Work Driver to implement URLs for List Widgets
		$linkUrl = $this->getRecordUrl($absolutePath);
		return $linkUrl;
	}
	function getRecordUrl($absolutePath = false){
		global $configArray;
		$recordId = $this->getUniqueID();
		if ($absolutePath){
			return $configArray['Site']['url'] . '/Archive/' . urlencode($recordId) . '/' . $this->getViewAction();
		}else{
			return $configArray['Site']['path'] . '/Archive/' . urlencode($recordId) . '/' . $this->getViewAction();
		}
	}

	public abstract function getViewAction();

	protected function getPlaceholderImage() {
		global $configArray;
		return $configArray['Site']['path'] . '/interface/themes/responsive/images/History.png';
	}

	private $subjectHeadings = null;
	public function getAllSubjectHeadings() {
		if ($this->subjectHeadings == null) {
			require_once ROOT_DIR . '/sys/ArchiveSubject.php';
			$archiveSubjects = new ArchiveSubject();
			$subjectsToIgnore = array();
			$subjectsToRestrict = array();
			if ($archiveSubjects->find(true)){
				$subjectsToIgnore = array_flip(explode("\r\n", strtolower($archiveSubjects->subjectsToIgnore)));
				$subjectsToRestrict = array_flip(explode("\r\n", strtolower($archiveSubjects->subjectsToRestrict)));
			}

			$subjectsWithLinks = $this->getAllSubjectsWithLinks();
			$relatedSubjects = array();
			$title = $this->getTitle();
			if (strlen($title) > 0) {
				$relatedSubjects[$title] = '"' . $title . '"';
			}
			for ($i = 0; $i < 2; $i++){
				foreach ($subjectsWithLinks as $subject) {
					$searchSubject = preg_replace('/\(.*?\)/',"", $subject['label']);
					$searchSubject = trim(preg_replace('/[\/|:.,"]/',"", $searchSubject));
					$lowerSubject = strtolower($searchSubject);
					if (!array_key_exists($lowerSubject, $subjectsToIgnore)) {
						if ($i == 0){
							//First pass, just add primary subjects
							if (!array_key_exists($lowerSubject, $subjectsToRestrict)) {
								$relatedSubjects[$lowerSubject] = '"' . $searchSubject . '"';
							}
						}else{
							//Second pass, add restricted subjects, but only if we don't have 5 subjects already
							if (array_key_exists($lowerSubject, $subjectsToRestrict) && count($relatedSubjects) <= 5) {
								$relatedSubjects[$lowerSubject] = '"' . $searchSubject . '"';
							}
						}
					}
				}
			}
			$relatedSubjects = array_slice($relatedSubjects, 0, 5);

			//Extract Subjects
			$this->subjectHeadings = $relatedSubjects;
		}
		return $this->subjectHeadings;
	}

	private $subjectsWithLinks = null;
	public function getAllSubjectsWithLinks() {
		global $configArray;
		if ($this->subjectsWithLinks == null) {
			//Extract Subjects
			$this->subjectsWithLinks = array();
			$matches = $this->getModsValues('topic', 'mods');
			foreach ($matches as $subjectPart) {
				$subjectLink = $configArray['Site']['path'] . '/Archive/Results?lookfor=';
				if (strlen($subjectPart) > 0) {
					$subjectLink .= '&filter[]=mods_subject_topic_ms%3A' . urlencode('"' .(string)$subjectPart . '"');
					$this->subjectsWithLinks[] = array(
							'link' => $subjectLink,
							'label' => (string)$subjectPart
					);
				}
			}
		}
		return $this->subjectsWithLinks;
	}

	public function getModsAttribute($attribute, $snippet){
		return FedoraUtils::getInstance()->getModsAttribute($attribute, $snippet);
	}

	/**
	 * Gets a single valued field from the MODS data using regular expressions
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $snippet - The snippet of XML to load from
	 * @param $includeTag - whether or not the surrounding tag should be included
	 *
	 * @return string
	 */
	public function getModsValue($tag, $namespace = null, $snippet = null, $includeTag = false){
		if ($snippet == null){
			$modsData = $this->getModsData();
		}else{
			$modsData = $snippet;
		}
		return FedoraUtils::getInstance()->getModsValue($tag, $namespace, $modsData, $includeTag);
	}

	/**
	 * Gets a multi valued field from the MODS data using regular expressions
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $snippet - The snippet of XML to load from
	 * @param $includeTag - whether or not the surrounding tag should be included
	 *
	 * @return string[]
	 */
	public function getModsValues($tag, $namespace = null, $snippet = null, $includeTag = false){
		if ($snippet == null){
			$modsData = $this->getModsData();
		}else{
			$modsData = $snippet;
		}
		return FedoraUtils::getInstance()->getModsValues($tag, $namespace, $modsData, $includeTag);
	}

	public function getModsData(){
		global $timer;
		if ($this->modsData == null){
			$fedoraUtils = FedoraUtils::getInstance();
			$this->modsData = $fedoraUtils->getModsData($this->getArchiveObject());
			$timer->logTime('Loaded MODS data for ' . $this->getUniqueID());
		}
		return $this->modsData;
	}

	protected $subCollections = null;
	public function getSubCollections(){
		if ($this->subCollections == null){
			$this->subCollections = array();
			// Include Search Engine Class
			require_once ROOT_DIR . '/sys/Solr.php';

			// Initialise from the current search globals
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setLimit(100);
			$searchObject->setSearchTerms(array(
				'lookfor' => 'RELS_EXT_isMemberOfCollection_uri_mt:"info:fedora/' . $this->getUniqueID() . '" AND RELS_EXT_hasModel_uri_mt:"info:fedora/islandora:collectionCModel"',
				'index' => 'IslandoraKeyword'
			));

			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			//$searchObject->setDebugging(true, true);
			//$searchObject->setPrimarySearch(true);
			$searchObject->setApplyStandardFilters(false);
			$response = $searchObject->processSearch(true, false, true);
			if ($response && $response['response']['numFound'] > 0) {
				foreach ($response['response']['docs'] as $doc) {
					$subCollectionPid = $doc['PID'];
					$this->subCollections[] = $subCollectionPid;
				}
			}
		}
		return $this->subCollections;
	}

	protected $relatedCollections = null;
	public function getRelatedCollections() {
		if ($this->relatedCollections == null){
			global $timer;
			$this->relatedCollections = array();
			if ($this->isEntity()){
				//Get collections related to objects related to this entity
				$directlyLinkedObjects = $this->getDirectlyRelatedArchiveObjects();
				foreach ($directlyLinkedObjects['objects'] as $tmpObject){
					$linkedCollections = $tmpObject['driver']->getRelatedCollections();
					$this->relatedCollections = array_merge($this->relatedCollections, $linkedCollections);
				}
			}
			//Get collections directly related to the object
			$collectionsRaw = $this->getArchiveObject()->relationships->get(FEDORA_RELS_EXT_URI, 'isMemberOfCollection');
			$fedoraUtils = FedoraUtils::getInstance();
			foreach ($collectionsRaw as $collectionInfo) {
				if ($fedoraUtils->isPidValidForPika($collectionInfo['object']['value'])){
					$collectionObject = $fedoraUtils->getObject($collectionInfo['object']['value']);
					$driver = RecordDriverFactory::initRecordDriver($collectionObject);
					$this->relatedCollections[$collectionInfo['object']['value']] = array(
							'pid' => $collectionInfo['object']['value'],
							'label' => $collectionObject->label,
							'link' => '/Archive/' . $collectionInfo['object']['value'] . '/Exhibit',
							'image' => $fedoraUtils->getObjectImageUrl($collectionObject, 'small'),
							'object' => $collectionObject,
							'driver' => $driver,
					);
				}
			}

			if (count($this->relatedCollections) == 0){
				foreach ($collectionsRaw as $collectionInfo) {
					if (!$fedoraUtils->isPidValidForPika($collectionInfo['object']['value'])){
						$parentObject = $fedoraUtils->getObject($collectionInfo['object']['value']);
						/** @var IslandoraDriver $parentDriver */
						$parentDriver = RecordDriverFactory::initRecordDriver($parentObject);
						if ($parentDriver){
							$this->relatedCollections = $parentDriver->getRelatedCollections();
							if (count($this->relatedCollections) != 0){
								break;
							}
						}
					}
				}
			}

			$timer->logTime('Loaded related collections for ' . $this->getUniqueID());
		}

		return $this->relatedCollections;
	}

	protected $creators = array();
	protected $relatedPeople = array();
	protected $productionTeam = array();
	protected $relatedPlaces = array();
	protected $unlinkedEntities = array();
	protected $relatedEvents = array();
	protected $relatedOrganizations = array();
	protected $brandingEntities = array();
	private $loadedRelatedEntities = false;
	private $loadedBrandingFromCollection = false;
	private static $nonProductionTeamRoles = array('attendee', 'artist', 'child', 'correspondence recipient', 'employee', 'interviewee', 'member', 'parade marshal', 'parent', 'participant', 'president', 'rodeo royalty', 'described', 'author', 'sibling', 'spouse', 'pictured', 'student' );
	private static $brandingRoles = array('donor', 'owner', 'funder', 'acknowledgement');
	public function loadRelatedEntities(){
		if ($this->loadedRelatedEntities == false){
			$this->loadedRelatedEntities = true;
			$fedoraUtils = FedoraUtils::getInstance();
			$marmotExtension = $this->getMarmotExtension();
			if ($marmotExtension != null){
				$entities = $this->getModsValues('relatedEntity', 'marmot', null, true);
				foreach ($entities as $entity){
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0){
						continue;
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$entityType = $this->getModsAttribute('type', $entity);
					$relationshipNote = $this->getModsValue('entityRelationshipNote', 'marmot', $entity);
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, $entityType, $relationshipNote, '');

				}

				$transcriber = $this->getModsValue('transcriber', 'marmot');
				if ($transcriber){
					$transcriberPid = $this->getModsValue('entityPid', 'marmot', $transcriber);
					$transcriberTitle = $this->getModsValue('entityTitle', 'marmot', $transcriber);
					$this->addRelatedEntityToArrays($transcriberPid, $transcriberTitle, '', '', 'Transcriber');
				}

				$militaryConflict = $this->getModsValue('militaryConflict', 'marmot');
				if ($militaryConflict){
					$militaryConflictTitle = FedoraUtils::getInstance()->getObjectLabel($militaryConflict);
					if ($militaryConflictTitle != 'Invalid Object'){
						$this->addRelatedEntityToArrays($militaryConflict, $militaryConflictTitle, '', '', '');
					}
				}

				$creators = $this->getModsValues('hasCreator', 'marmot', null, true);
				foreach ($creators as $entity) {
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						continue;
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$relationshipNote = $this->getModsValue('relationshipNote', 'marmot', $entity);
					$entityType = $this->getModsAttribute('type', $entity);
					$entityRole = $this->getModsAttribute('role', $entity);
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, $entityType, $relationshipNote, $entityRole, true);
				}

				$entities = $this->getModsValues('describedEntity', 'marmot', null, true);
				foreach ($entities as $entity) {
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						continue;
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$relationshipNote = $this->getModsValue('relationshipNote', 'marmot', $entity);
					$entityType = $this->getModsAttribute('type', $entity);
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, $entityType, $relationshipNote, 'Described');
				}

				$entities = $this->getModsValues('picturedEntity', 'marmot', null, true);
				foreach ($entities as $entity) {
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						continue;
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$relationshipNote = $this->getModsValue('relationshipNote', 'marmot', $entity);
					$entityType = $this->getModsAttribute('type', $entity);
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, $entityType, $relationshipNote, 'Pictured');
				}

				$entities = $this->getModsValues('relatedPersonOrg', 'marmot', null, true);
				foreach ($entities as $entity) {
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						continue;
					}
					$entityType = $this->getModsAttribute('type', $entity);
					if ($entityType == '' && strlen($entityPid)) {
						//Get the type based on the pid
						list($entityType) = explode(':', $entityPid);
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$relationshipNote = $this->getModsValue('relationshipNote', 'marmot', $entity);
					$entityRole = $this->getModsAttribute('role', $entity);
					if (strlen($entityRole) == 0) {
						$entityRole = $this->getModsValue('role', 'marmot', $entity);
					}
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, $entityType, $relationshipNote, $entityRole);
				}

				$entities = $this->getModsValues('relatedEvent', 'marmot', null, true);
				foreach ($entities as $entity){
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						continue;
					}
					$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					$relationshipNote = $this->getModsValue('entityRelationshipNote', 'marmot', $entity);
					$entityRole = $this->getModsAttribute('role', $entity);
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, 'event', $relationshipNote, $entityRole);
				}

				$entities = $this->getModsValues('relatedPlace', 'marmot', null, true);
				foreach ($entities as $entity){
					$entityPid = $this->getModsValue('entityPid', 'marmot', $entity);
					if (strlen($entityPid) == 0) {
						$entityTitle = '';

						$hasAddressInfo = false;
						$addressStreetNumber = FedoraUtils::cleanValue($this->getModsValue('addressStreetNumber', 'marmot', $entity));
						if ($addressStreetNumber){
							$entityTitle .= $addressStreetNumber;
							$hasAddressInfo = true;
						}
						$addressStreet = FedoraUtils::cleanValue($this->getModsValue('addressStreet', 'marmot', $entity));
						if ($addressStreet){
							$entityTitle .= ' ' . $addressStreet;
							$hasAddressInfo = true;
						}
						$address2 = FedoraUtils::cleanValue($this->getModsValue('address2', 'marmot', $entity));
						if ($address2){
							if (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $address2;
							$hasAddressInfo = true;
						}
						$addressCity = FedoraUtils::cleanValue($this->getModsValue('addressCity', 'marmot', $entity));
						if ($addressCity){
							if (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $addressCity;
							$hasAddressInfo = true;
						}
						$addressState = FedoraUtils::cleanValue($this->getModsValue('addressState', 'marmot', $entity));
						if ($addressCity){
							if ($addressState){
								$entityTitle .= ', ';
							}elseif (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $addressState;
							$hasAddressInfo = true;
						}
						$addressZipCode = FedoraUtils::cleanValue($this->getModsValue('addressZipCode', 'marmot', $entity));
						if ($addressZipCode){
							$entityTitle .= ' ' . $addressZipCode;
							$hasAddressInfo = true;
						}
						$addressCounty = FedoraUtils::cleanValue($this->getModsValue('addressCounty', 'marmot', $entity));
						if ($addressCounty){
							if (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $addressCounty;
							$hasAddressInfo = true;
						}
						//Country defaults to USA, don't set $hasAddressInfo = true;
						$addressCountry = FedoraUtils::cleanValue($this->getModsValue('addressCountry', 'marmot', $entity));
						if ($addressCountry){
							if (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $addressCountry;
						}
						$addressOtherRegion = FedoraUtils::cleanValue($this->getModsValue('addressOtherRegion', 'marmot', $entity));
						if ($addressOtherRegion){
							if (strlen($entityTitle) > 0){
								$entityTitle .= '<br/>';
							}
							$entityTitle .= $addressOtherRegion;
							$hasAddressInfo = true;
						}

						if (!$hasAddressInfo){
							$entityTitle = '';
						}

						$latitude = $this->getModsValue('latitude', 'marmot', $entity);
						$longitude = $this->getModsValue('longitude', 'marmot', $entity);
						if ($latitude || $longitude){
							$entityTitle .= " ($latitude, $longitude)";
						}
					}else{
						$entityTitle = $this->getModsValue('entityTitle', 'marmot', $entity);
					}

					$entityInfo = array(
							'pid' => $entityPid,
							'label' => $entityTitle
					);
					$significance = $this->getModsValue('significance', 'marmot', $entity);
					if ($significance){
						$entityInfo['role'] = ucfirst($significance);
					}
					$this->addRelatedEntityToArrays($entityPid, $entityTitle, 'place', '', $significance);
				}
			}

		}
	}

	public function getRelatedEvents(){
		if ($this->relatedEvents == null){
			$this->loadRelatedEntities();
		}
		return $this->relatedEvents;
	}

	public function getRelatedPeople(){
		if ($this->relatedPeople == null){
			$this->loadRelatedEntities();
		}
		return $this->relatedPeople;
	}

	public function getProductionTeam(){
		if ($this->productionTeam == null){
			$this->loadRelatedEntities();
		}
		return $this->productionTeam;
	}

	public function getCreators(){
		if ($this->creators == null){
			$this->loadRelatedEntities();
		}
		return $this->creators;
	}

	public function getRelatedPlaces(){
		if ($this->relatedPlaces == null){
			$this->loadRelatedEntities();
		}
		return $this->relatedPlaces;
	}

	public function getRelatedOrganizations(){
		if ($this->relatedOrganizations == null){
			$this->loadRelatedEntities();
		}
		return $this->relatedOrganizations;
	}

	public function isEntity(){
		return false;
	}

	/**
	 * @return string
	 */
	protected function getMarmotExtension(){
		return $this->getModsValue('extension', 'mods');
	}

	public function getVisibleLinks(){
		$allLinks = $this->getLinks();
		$visibleLinks = array();
		foreach ($allLinks as $link){
			if (!$link['hidden']){
				$visibleLinks[] = $link;
			}
		}
		return $visibleLinks;
	}
	protected $links = null;
	public function getLinks(){
		if ($this->links == null){
			global $timer;
			$this->links = array();
			$marmotExtension = $this->getMarmotExtension();
			if (strlen($marmotExtension) > 0){
				$linkData = $this->getModsValues('externalLink', 'marmot', $marmotExtension, true);
				foreach ($linkData as $linkInfo) {
					$linkType = $this->getModsAttribute('type', $linkInfo);
					$link = $this->getModsValue('link', 'marmot', $linkInfo);
					$linkText = $this->getModsValue('linkText', 'marmot', $linkInfo);
					if (strlen($linkText) == 0) {
						if (strlen($linkType) == 0) {
							$linkText = $link;
						} else {
							switch ($linkType) {
								case 'relatedPika':
									$linkText = 'Related title from the catalog';
									break;
								case 'marmotGenealogy':
									$linkText = 'Genealogy Record';
									break;
								case 'findAGrave':
									$linkText = 'Grave site information on Find a Grave';
									break;
								case 'fortLewisGeoPlaces':
									//Skip this one
									continue;
								case 'geoNames':
									$linkText = 'Geographic information from GeoNames.org';
									continue;
								case 'samePika':
									$linkText = 'This record within the catalog';
									continue;
								case 'whosOnFirst':
									$linkText = 'Geographic information from Who\'s on First';
									continue;
								case 'wikipedia':
									$linkText = 'Information from Wikipedia';
									continue;
								default:
									$linkText = $linkType;
							}
						}
					}
					if (strlen($link) > 0) {
						$isHidden = false;
						if ($linkType == 'wikipedia' || $linkType == 'geoNames' || $linkType == 'whosOnFirst' || $linkType == 'relatedPika') {
							$isHidden = true;
						}
						$this->links[] = array(
								'type' => $linkType,
								'link' => $link,
								'text' => $linkText,
								'hidden' => $isHidden
						);
					}
				}
			}
			$timer->logTime("Loaded links");
		}
		return $this->links;
	}

	protected $relatedPikaRecords;
	public function getRelatedPikaContent(){
		if ($this->relatedPikaRecords == null){
			require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

			$this->relatedPikaRecords = array();

			//Look for things linked directly to this object
			$links = $this->getLinks();
			$relatedWorkIds = array();
			foreach ($links as $id => $link){
				if ($link['type'] == 'relatedPika'){
					if (preg_match('/^.*\/GroupedWork\/([a-f0-9-]{36})/', $link['link'], $matches)) {
						$workId = $matches[1];
						$relatedWorkIds[] = $workId;
					}else{
						//Didn't get a valid grouped work id
					}
				}
			}

			/** @var SearchObject_Solr $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init();
			$linkedWorkData = $searchObject->getRecords($relatedWorkIds);
			foreach ($linkedWorkData as $workData) {
				$workDriver = new GroupedWorkDriver($workData);
				if ($workDriver->isValid) {
					$this->relatedPikaRecords[] = array(
							'link' => $workDriver->getLinkUrl(),
							'label' => $workDriver->getTitle(),
							'image' => $workDriver->getBookcoverUrl('medium'),
							'id' => $workId
					);
					//$this->links[$id]['hidden'] = true;
				}
			}
			$searchObject = null;
			unset ($searchObject);

			//Look for links related to the collection(s) this object is linked to
			$collections = $this->getRelatedCollections();
			foreach ($collections as $collection){
				/** @var IslandoraDriver $collectionDriver */
				$collectionDriver = RecordDriverFactory::initRecordDriver($collection['object']);
				$relatedFromCollection = $collectionDriver->getRelatedPikaContent();
				if (count($relatedFromCollection)){
					$this->relatedPikaRecords = array_merge($this->relatedPikaRecords, $relatedFromCollection);
				}
			}
		}
		return $this->relatedPikaRecords;
	}

	protected $directlyRelatedObjects = null;

	/**
	 * Load objects that are related directly to this object
	 * Either based on a link from this object to another object
	 * Or based on a link from another object to this object
	 *
	 * @return array|null
	 */
	public function getDirectlyRelatedArchiveObjects(){
		if ($this->directlyRelatedObjects == null){
			global $timer;
			$fedoraUtils = FedoraUtils::getInstance();

			$timer->logTime("Starting getDirectlyLinkedArchiveObjects");
			$this->directlyRelatedObjects = array(
					'numFound' => 0,
					'objects' => array(),
			);

			$relatedObjects = $this->getModsValues('relatedObject', 'marmot');
			if (count($relatedObjects) > 0){
				$numObjects = 0;
				$relatedObjectPIDs = array();
				$objectNotes = array();
				$objectLabels = array();
				foreach ($relatedObjects as $relatedObjectSnippets){
					$objectPid = trim($this->getModsValue('objectPid', 'marmot', $relatedObjectSnippets));
					$objectLabel = trim($this->getModsValue('objectTitle', 'marmot', $relatedObjectSnippets));
					$relationshipNote = trim($this->getModsValue('objectRelationshipNote', 'marmot', $relatedObjectSnippets));
					if (strlen($objectPid) > 0){
						$numObjects++;
						$relatedObjectPIDs[] = $objectPid;
						$objectNotes[$objectPid] = $relationshipNote;
						$objectLabels[$objectPid] = $objectLabel;
					}
				}

				if (count($relatedObjectPIDs) > 0) {
					/** @var SearchObject_Islandora $searchObject */
					$searchObject = SearchObjectFactory::initSearchObject('Islandora');
					$searchObject->init();
					$searchObject->setSort('fgs_label_s');
					$searchObject->setLimit($numObjects);
					$searchObject->setQueryIDs($relatedObjectPIDs);
					$response = $searchObject->processSearch(true, false, true);
					if ($response && $response['response']['numFound'] > 0) {
						foreach ($response['response']['docs'] as $doc) {
							$entityDriver = RecordDriverFactory::initRecordDriver($doc);
							$objectInfo = array(
									'pid' => $entityDriver->getUniqueID(),
									'label' => $objectLabels[$entityDriver->getUniqueID()] ? $objectLabels[$entityDriver->getUniqueID()] : $entityDriver->getTitle(),
									'description' => $entityDriver->getTitle(),
									'image' => $entityDriver->getBookcoverUrl('medium'),
									'link' => $entityDriver->getRecordUrl(),
									'driver' => $entityDriver,
									'note' => $objectNotes[$entityDriver->getUniqueID()]
							);
							$this->directlyRelatedObjects['objects'][$objectInfo['pid']] = $objectInfo;
							$this->directlyRelatedObjects['numFound']++;
						}
					}
				}
				$searchObject = null;
				unset($searchObject);
			}
			// Include Search Engine Class
			require_once ROOT_DIR . '/sys/Solr.php';

			// Initialise from the current search globals
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setSort('fgs_label_s');
			$searchObject->setLimit(100);
			$searchObject->setSearchTerms(array(
					'lookfor' => '"' . $this->getUniqueID() . '"',
					'index' => 'IslandoraRelationshipsById'
			));

			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->addFieldsToReturn(array(
					'mods_extension_marmotLocal_relatedPersonOrg_entityPid_ms',
					'mods_extension_marmotLocal_relatedPersonOrg_role_ms',
					'mods_extension_marmotLocal_relatedPersonOrg_entityTitle_ms'
			));
			//$searchObject->setDebugging(true, true);
			//$searchObject->setPrimarySearch(true);
			$response = $searchObject->processSearch(true, false);
			if ($response && $response['response']['numFound'] > 0) {
				foreach ($response['response']['docs'] as $doc) {
					$entityDriver = RecordDriverFactory::initRecordDriver($doc);

					//Try to find the relationship to the person
					$role = '';
					if (isset($doc['mods_extension_marmotLocal_relatedPersonOrg_entityPid_ms']) && isset($doc['mods_extension_marmotLocal_relatedPersonOrg_role_ms'])){
						//Check to see if we have the same number of entities and roles.  If not we will need to load the full related object to determine role.
						if (count($doc['mods_extension_marmotLocal_relatedPersonOrg_entityPid_ms']) != count($doc['mods_extension_marmotLocal_relatedPersonOrg_role_ms'])){
							/** @var IslandoraDriver $relatedEntityDriver */
							$relatedEntityDriver = RecordDriverFactory::initRecordDriver($doc);
							$relatedPeople = $relatedEntityDriver->getRelatedPeople();
							foreach ($relatedPeople as $person){
								if ($person['pid'] == $this->getUniqueID()){
									$role = $person['role'];
									break;
								}
							}

						}else{
							foreach ($doc['mods_extension_marmotLocal_relatedPersonOrg_entityPid_ms'] as $index => $value) {
								if ($value == $this->getUniqueID()) {
									if (isset($doc['mods_extension_marmotLocal_relatedPersonOrg_role_ms'][$index])){
										$role = $doc['mods_extension_marmotLocal_relatedPersonOrg_role_ms'][$index];
										//Reverse roles as appropriate
										if ($role == 'child'){
											$role = 'parent';
										}
									}
								}
							}
						}
					}

					if ($entityDriver instanceof EventDriver) {
						$this->addRelatedEntityToArrays($entityDriver->getUniqueID(), $entityDriver->getTitle(), 'event', '', $role);
					}elseif ($entityDriver instanceof PersonDriver){
						$this->addRelatedEntityToArrays($entityDriver->getUniqueID(), $entityDriver->getTitle(), 'person', '', $role);
					}elseif ($entityDriver instanceof OrganizationDriver){
						$this->addRelatedEntityToArrays($entityDriver->getUniqueID(), $entityDriver->getTitle(), 'organization', '', $role);
					}elseif ($entityDriver instanceof PlaceDriver){
						$this->addRelatedEntityToArrays($entityDriver->getUniqueID(), $entityDriver->getTitle(), 'place', '', $role);
					}else{
						$objectInfo = array(
								'pid' => $entityDriver->getUniqueID(),
								'label' => $entityDriver->getTitle(),
								'description' => $entityDriver->getTitle(),
								'image' => $entityDriver->getBookcoverUrl('medium'),
								'link' => $entityDriver->getRecordUrl(),
								'role' => $role,
								'driver' => $entityDriver
						);
						$this->directlyRelatedObjects['objects'][$objectInfo['pid']] = $objectInfo;
						$this->directlyRelatedObjects['numFound']++;
					}
				}
			}
			$timer->logTime("Finished getDirectlyLinkedArchiveObjects");
		}

		return $this->directlyRelatedObjects;
	}

	private function addRelatedEntityToArrays($pid, $entityName, $entityType, $note, $role, $isCreator = false) {
		$fedoraUtils = FedoraUtils::getInstance();
		if (strlen($pid) == 0 || strpos($pid, ':') === false){
			if (strlen($entityName) > 0) {
				//This is an object with just a title
				$this->unlinkedEntities[] = array(
						'role' => $role,
						'label' => $entityName
				);
			}
		}else{
			if ($entityType == '' && strlen($pid)){
				//Get the type based on the pid
				list($entityType, $id) = explode(':', $pid);
			}

			require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
			$islandoraCache = new IslandoraObjectCache();
			$islandoraCache->pid = $pid;
			if ($islandoraCache->find(true) && !empty($islandoraCache->mediumCoverUrl)){
				$imageUrl = $islandoraCache->mediumCoverUrl;
			}else{
				$imageUrl = $fedoraUtils->getObjectImageUrl($fedoraUtils->getObject($pid), 'medium', $entityType);
			}

			$entityInfo = array(
					'pid' => $pid,
					'label' => $entityName,
					'note' => $note,
					'role' => $role,
					'image' => $imageUrl,
			);

			if (!in_array($entityType, array('person', 'organization', 'event', 'place'))){
				//Need to check the actual content model
				$fedoraObject = $fedoraUtils->getObject($entityInfo['pid']);
				$recordDriver = RecordDriverFactory::initRecordDriver($fedoraObject);
				if ($recordDriver instanceof PersonDriver) {
					$entityType = 'person';
				}elseif ($recordDriver instanceof PlaceDriver){
					$entityType = 'place';
				}elseif ($recordDriver instanceof EventDriver){
					$entityType = 'event';
				}elseif ($recordDriver instanceof OrganizationDriver){
					$entityType = 'organization';
				}
			}
			if ($entityType == 'person'){
				$entityInfo['link']= '/Archive/' . $pid . '/Person';
				if ($isCreator) {
					if (array_key_exists($pid, $this->creators)){
						$this->creators[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->creators[$pid] = $entityInfo;
					}
				}
				if (strlen($role) > 0 && !in_array(strtolower($role), IslandoraDriver::$nonProductionTeamRoles)) {
					if (array_key_exists($pid, $this->productionTeam)) {
						$this->productionTeam[$pid]['role'] .= ', ' . $entityInfo['role'];
					} else {
						$this->productionTeam[$pid] = $entityInfo;
					}
				}elseif (strlen($role) > 0 && in_array(strtolower($role), IslandoraDriver::$brandingRoles)){
					if ($role == 'owner'){
						$entityInfo['sortIndex'] = 1;
						$entityInfo['label'] = 'Owned by ' . $entityInfo['label'];
					}elseif ($role == 'donor'){
						$entityInfo['sortIndex'] = 2;
						$entityInfo['label'] = 'Donated by ' . $entityInfo['label'];
					}elseif ($role == 'funder'){
						$entityInfo['label'] = 'Funded by ' . $entityInfo['label'];
						$entityInfo['sortIndex'] = 3;
					}elseif ($role == 'acknowledgement'){
						$entityInfo['label'] = '';
						$entityInfo['sortIndex'] = 4;
					}
					if (array_key_exists($pid, $this->brandingEntities)){
						$this->brandingEntities[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->brandingEntities[$pid] = $entityInfo;
					}
				}else{
					if (array_key_exists($pid, $this->relatedPeople)){
						$this->relatedPeople[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->relatedPeople[$pid] = $entityInfo;
					}
				}

			}elseif ($entityType == 'place'){
				$entityInfo['link']= '/Archive/' . $pid . '/Place';
				if (array_key_exists($pid, $this->relatedPlaces)){
					$this->relatedPlaces[$pid]['role'] .= ', ' . $entityInfo['role'];
				}else{
					$this->relatedPlaces[$pid] = $entityInfo;
				}
			}elseif ($entityType == 'event'){
				$entityInfo['link']= '/Archive/' . $pid . '/Event';
				if (array_key_exists($pid, $this->relatedEvents)){
					$this->relatedEvents[$pid]['role'] .= ', ' . $entityInfo['role'];
				}else{
					$this->relatedEvents[$pid] = $entityInfo;
				}
			}elseif ($entityType == 'organization'){
				$entityInfo['link']= '/Archive/' . $pid . '/Organization';
				if ($isCreator) {
					if (array_key_exists($pid, $this->creators)){
						$this->creators[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->creators[$pid] = $entityInfo;
					}
				}
				if (strlen($role) > 0 && in_array(strtolower($role), IslandoraDriver::$brandingRoles)){
					if ($role == 'owner'){
						$entityInfo['sortIndex'] = 1;
						$entityInfo['label'] = 'Owned by ' . $entityInfo['label'];
					}elseif ($role == 'donor'){
						$entityInfo['sortIndex'] = 2;
						$entityInfo['label'] = 'Donated by ' . $entityInfo['label'];
					}elseif ($role == 'funder'){
						$entityInfo['label'] = 'Funded by ' . $entityInfo['label'];
						$entityInfo['sortIndex'] = 3;
					}elseif ($role == 'acknowledgement'){
						$entityInfo['label'] = '';
						$entityInfo['sortIndex'] = 4;
					}
					if (array_key_exists($pid, $this->brandingEntities)){
						$this->brandingEntities[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->brandingEntities[$pid] = $entityInfo;
					}
				}else{
					if (array_key_exists($pid, $this->relatedOrganizations)){
						$this->relatedOrganizations[$pid]['role'] .= ', ' . $entityInfo['role'];
					}else{
						$this->relatedOrganizations[$pid] = $entityInfo;
					}
				}
			}
		}
	}

	public function getExtension($mimeType)
	{
		if(empty($mimeType)) return false;
		switch($mimeType)
		{
			case 'image/bmp': return '.bmp';
			case 'image/cis-cod': return '.cod';
			case 'image/gif': return '.gif';
			case 'image/ief': return '.ief';
			case 'image/jpeg': return '.jpg';
			case 'image/jpg': return '.jpg';
			case 'image/pipeg': return '.jfif';
			case 'image/tiff': return '.tif';
			case 'image/x-cmu-raster': return '.ras';
			case 'image/x-cmx': return '.cmx';
			case 'image/x-icon': return '.ico';
			case 'image/x-portable-anymap': return '.pnm';
			case 'image/x-portable-bitmap': return '.pbm';
			case 'image/x-portable-graymap': return '.pgm';
			case 'image/x-portable-pixmap': return '.ppm';
			case 'image/x-rgb': return '.rgb';
			case 'image/x-xbitmap': return '.xbm';
			case 'image/x-xpixmap': return '.xpm';
			case 'image/x-xwindowdump': return '.xwd';
			case 'image/png': return '.png';
			case 'image/x-jps': return '.jps';
			case 'image/x-freehand': return '.fh';
			case 'application/pdf': return '.pdf';
			default: return false;
		}
	}

	public function getDateCreated() {
		$dateCreated = $this->getModsValue('dateCreated', 'mods');
		if ($dateCreated == ''){
			$dateCreated = $this->getModsValue('dateIssued', 'mods');
			if ($dateCreated == ''){
				return 'Unknown';
			}
		}
		$formattedDate = DateTime::createFromFormat('Y-m-d', $dateCreated);
		if ($formattedDate != false) {
			$dateCreated = $formattedDate->format('m/d/Y');
		}
		return $dateCreated;
	}

	public function getFormat(){
		$genre = $this->getModsValue('genre', 'mods');
		if ($genre != null){
			return ucwords($genre);
		}
		return null;
	}

	/**
	 * @return null|FedoraObject
	 */
	public function getParentObject(){
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$parentIdArray = $this->getArchiveObject()->relationships->get(FEDORA_RELS_EXT_URI, 'isMemberOf');
		if ($parentIdArray != null){
			$parentIdInfo = reset($parentIdArray);
			$parentId = $parentIdInfo['object']['value'];
			return $fedoraUtils->getObject($parentId);
		}else{
			$parentIdArray = $this->getArchiveObject()->relationships->get(FEDORA_RELS_EXT_URI, 'isConstituentOf');
			if ($parentIdArray != null){
				$parentIdInfo = reset($parentIdArray);
				$parentId = $parentIdInfo['object']['value'];
				return $fedoraUtils->getObject($parentId);
			}
		}
		return null;
	}

	private function mergeEntities($array1, $array2){
		if ($array1 == null){
			return $array2;
		}elseif ($array2 == null){
			return $array1;
		}else{
			foreach ($array2 as $entityInfo){
				$pid = $entityInfo['pid'];
				if (array_key_exists($pid, $array1)){
					$array1[$pid]['role'] .= ', ' . $entityInfo['role'];
				}else{
					$array1[$pid] = $entityInfo;
				}
			}
		}
	}

	private $transcriptions = null;
	private function loadTranscription() {
		global $interface;
		if ($this->transcriptions == null){

		}
		$this->transcriptions = $this->getModsValues('hasTranscription', 'marmot');
		if ($this->transcriptions){
			$transcriptionInfo = array();
			foreach ($this->transcriptions as $transcription){
				$transcriptionText = $this->getModsValue('transcriptionText', 'marmot', $transcription);
				$transcriptionText = str_replace("\r\n", '<br/>', $transcriptionText);
				$transcriptionText = str_replace("&#xD;", '<br/>', $transcriptionText);

				//Add links to timestamps
				$transcriptionTextWithLinks = $transcriptionText;
				if (preg_match_all('/\\(\\d{1,2}:\d{1,2}\\)/', $transcriptionText, $allMatches)){
					foreach ($allMatches[0] as $match){
						$offset = str_replace('(', '', $match);
						$offset = str_replace(')', '', $offset);
						list($minutes, $seconds) = explode(':', $offset);
						$offset = $minutes * 60 + $seconds;
						$replacement = '<a onclick="document.getElementById(\'player\').currentTime=\'' . $offset . '\';" style="cursor:pointer">' . $match . '</a>';
						$transcriptionTextWithLinks = str_replace($match, $replacement, $transcriptionTextWithLinks);
					}
				}elseif (preg_match_all('/\\[\\d{1,2}:\d{1,2}:\d{1,2}\\]/', $transcriptionText, $allMatches)){
					foreach ($allMatches[0] as $match){
						$offset = str_replace('(', '', $match);
						$offset = str_replace(')', '', $offset);
						list($hours, $minutes, $seconds) = explode(':', $offset);
						$offset = $hours * 3600 + $minutes * 60 + $seconds;
						$replacement = '<a onclick="document.getElementById(\'player\').currentTime=\'' . $offset . '\';" style="cursor:pointer">' . $match . '</a>';
						$transcriptionTextWithLinks = str_replace($match, $replacement, $transcriptionTextWithLinks);
					}
				}
				if (strlen($transcriptionTextWithLinks) > 0){
					$transcript = array(
							'language' => $this->getModsValue('transcriptionLanguage', 'marmot', $transcription),
							'text' => $transcriptionTextWithLinks,
							'location' => $this->getModsValue('transcriptionLocation', 'marmot', $transcription)
					);
					$transcriptionInfo[] = $transcript;
				}
			}

			if (count($transcriptionInfo) > 0){
				$interface->assign('transcription',$transcriptionInfo);
			}
			$this->transcriptions = $transcriptionInfo;
		}
		return $this->transcriptions;
	}

	private function loadCorrespondenceInfo() {
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$correspondence = $this->getModsValue('correspondence', 'marmot');
		$hasCorrespondenceInfo = false;
		if ($correspondence){
			$includesStamp = $this->getModsValue('includesStamp', 'marmot', $correspondence);
			if ($includesStamp == 'yes'){
				$interface->assign('includesStamp', true);
				$hasCorrespondenceInfo = true;
			}

			//Load postmark information
			$postmarkDetails = $this->getModsValues('relatedPlace', 'marmot', $correspondence);
			$postmarks = array();
			foreach ($postmarkDetails as $postmarkDetail){
				$datePostmarked = $this->getModsValue('datePostmarked', 'marmot', $postmarkDetail);
				$postmarkDate = DateTime::createFromFormat('Y-m-d', $datePostmarked);
				if ($postmarkDate != false) {
					$datePostmarked = $postmarkDate->format('m/d/Y');
				}
				$placePid = $this->getModsValue('entityPid', 'marmot', $postmarkDetail);
				$validEntity = false;
				$postmarkLocation = null;
				if ($placePid){
					$postMarkLocationObject = $fedoraUtils->getObject($placePid);
					if ($postMarkLocationObject){
						$postMarkLocationDriver = RecordDriverFactory::initRecordDriver($postMarkLocationObject);
						$postmarkLocation = array(
								'link' => $postMarkLocationDriver->getRecordUrl(),
								'label' => $postMarkLocationDriver->getTitle(),
								'role' => 'Postmark Location'
						);
						$validEntity = true;
					}
				}
				if (!$validEntity){
					$placeTitle = $this->getModsValue('entityTitle', 'marmot', $postmarkDetail);
					if ($placeTitle){
						$postmarkLocation = array(
								'label' => $placeTitle,
								'role' => 'Postmark Location'
						);

					}
				}
				if ($postmarkDate || $postmarkLocation){
					$postmarks[] = array(
							'datePostmarked' => $datePostmarked,
							'postmarkLocation' => $postmarkLocation
					);
				}
			}
			if (count($postmarks) > 0) {
				$interface->assign('postmarks', $postmarks);
				$hasCorrespondenceInfo = true;
			}

			$relatedPerson = $this->getModsValue('relatedPersonOrg', 'marmot', $correspondence);
			if ($relatedPerson){
				$personPid = $this->getModsValue('entityPid', 'marmot', $relatedPerson);
				$validPerson = false;
				if ($personPid){
					$correspondenceRecipientObject = $fedoraUtils->getObject($personPid);
					if ($correspondenceRecipientObject){
						$correspondenceRecipientDriver = RecordDriverFactory::initRecordDriver($correspondenceRecipientObject);
						$interface->assign('correspondenceRecipient', array(
								'link' => $correspondenceRecipientDriver->getRecordUrl(),
								'label' => $correspondenceRecipientDriver->getTitle(),
								'role' => 'Correspondence Recipient'
						));
						$validPerson = true;
						$hasCorrespondenceInfo = true;
					}
				}
				if (!$validPerson){
					$personTitle = $this->getModsValue('entityTitle', 'marmot', $relatedPerson);
					if ($personTitle){
						$interface->assign('correspondenceRecipient', array(
								'label' => $personTitle,
								'role' => 'Correspondence Recipient'
						));
						$hasCorrespondenceInfo = true;
					}
				}
			}

			$postcardPublisherNumber = $this->getModsValue('postcardPublisherNumber', 'marmot');
			if ($postcardPublisherNumber){
				$interface->assign('postcardPublisherNumber', $postcardPublisherNumber);
				$hasCorrespondenceInfo = true;
			}
		}
		$interface->assign('hasCorrespondenceInfo', $hasCorrespondenceInfo);
		return $hasCorrespondenceInfo;
	}

	private function loadAcademicResearchData() {
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$academicResearchSection = $this->getModsValue('academicResearch', 'marmot');
		$hasAcademicResearchData = false;
		if (!empty($academicResearchSection)){
			$researchType = FedoraUtils::cleanValues($this->getModsValues('academicResearchType', 'marmot', $academicResearchSection));
			if (count($researchType)){
				$hasAcademicResearchData = true;
				$interface->assign('researchType', $researchType);
			}

			$researchLevel = FedoraUtils::cleanValue($this->getModsValue('academicResearchLevel', 'marmot', $academicResearchSection));
			if (strlen($researchLevel)) {
				$hasAcademicResearchData = true;
				$interface->assign('researchLevel', ucwords(translate($researchLevel)));
			}

			$peerReview = FedoraUtils::cleanValue($this->getModsValue('peerReview', 'marmot', $academicResearchSection));
			$interface->assign('peerReview', ucwords($peerReview));

			$relatedAcademicPeople = $this->getModsValues('relatedPersonOrg', 'marmot', $academicResearchSection);
			if ($relatedAcademicPeople){
				$academicPeople = array();
				foreach ($relatedAcademicPeople as $relatedPerson){
					$personPid = $this->getModsValue('entityPid', 'marmot', $relatedPerson);
					$role = ucwords($this->getModsValue('role', 'marmot', $relatedPerson));
					$isValidPerson = false;
					if ($personPid){
						$academicPersonObject = $fedoraUtils->getObject($personPid);
						if ($academicPersonObject){
							$academicPersonDriver = RecordDriverFactory::initRecordDriver($academicPersonObject);
							$academicPeople[] = array(
									'link' => $academicPersonDriver->getRecordUrl(),
									'label' => $academicPersonDriver->getTitle(),
									'role' => $role
							);
							$isValidPerson = true;
						}
					}
					if (!$isValidPerson){
						$personTitle = $this->getModsValue('entityTitle', 'marmot', $relatedPerson);
						if ($personTitle){
							$academicPeople[] = array(
									'label' => $personTitle,
									'role' => $role
							);
						}
					}
				}
				if (count($academicPeople) > 0){
					$interface->assign('supportingDepartments', $academicPeople);
					$hasAcademicResearchData = true;
				}
			}

			//Degree Name only shows for people
			$degreeName = FedoraUtils::cleanValue($this->getModsValue('degreeName', 'marmot', $academicResearchSection));
			if (strlen($degreeName)) {
				$hasAcademicResearchData = true;
				$interface->assign('degreeName', $degreeName);
			}

			//Degree Discipline only shows for people
			$degreeDiscipline = FedoraUtils::cleanValue($this->getModsValue('degreeDiscipline', 'marmot', $academicResearchSection));
			if (strlen($degreeDiscipline)){
				$hasAcademicResearchData = true;
				$interface->assign('degreeDiscipline', $degreeDiscipline);
			}

			$defenceDate = FedoraUtils::cleanValue($this->getModsValue('defenceDate', 'marmot', $academicResearchSection));
			if (strlen($defenceDate)) {
				$hasAcademicResearchData = true;
				$formattedDate = DateTime::createFromFormat('Y-m-d', $defenceDate);
				if ($formattedDate != false) {
					$defenceDate = $formattedDate->format('m/d/Y');
				}
				$pubInfo['defenceDate'] = $defenceDate;
			}

			$acceptedDate = FedoraUtils::cleanValue($this->getModsValue('acceptedDate', 'marmot', $academicResearchSection));
			if (strlen($acceptedDate)) {
				$hasAcademicResearchData = true;
				$formattedDate = DateTime::createFromFormat('Y-m-d', $acceptedDate);
				if ($formattedDate != false) {
					$acceptedDate = $formattedDate->format('m/d/Y');
				}
				$pubInfo['acceptedDate'] = $acceptedDate;
			}

			$publicationPresentations = array();
			$publicationPresentationInfo = $this->getModsValues('publicationPresentationInfo', 'marmot', $academicResearchSection);
			foreach ($publicationPresentationInfo as $publicationPresentationData) {
				$pubInfo = array();

				$journalTitle = FedoraUtils::cleanValue($this->getModsValue('journalTitle', 'marmot', $publicationPresentationData));
				if (strlen($journalTitle)) {
					$hasAcademicResearchData = true;
					$pubInfo['journalTitle'] = $journalTitle;
				}
				$journalVolumeNumber = FedoraUtils::cleanValue($this->getModsValue('journalVolumeNumber', 'marmot', $publicationPresentationData));
				if (strlen($journalVolumeNumber)) {
					$hasAcademicResearchData = true;
					$pubInfo['journalVolumeNumber'] = $journalVolumeNumber;
				}
				$journalIssueNumber = FedoraUtils::cleanValue($this->getModsValue('journalIssueNumber', 'marmot', $publicationPresentationData));
				if (strlen($journalIssueNumber)) {
					$hasAcademicResearchData = true;
					$pubInfo['journalIssueNumber'] = $journalIssueNumber;
				}
				$journalArticleNumber = FedoraUtils::cleanValue($this->getModsValue('journalArticleNumber', 'marmot', $publicationPresentationData));
				if (strlen($journalArticleNumber)) {
					$hasAcademicResearchData = true;
					$pubInfo['journalArticleNumber'] = $journalArticleNumber;
				}
				$articleFirstPage = FedoraUtils::cleanValue($this->getModsValue('articleFirstPage', 'marmot', $publicationPresentationData));
				if (strlen($articleFirstPage)) {
					$hasAcademicResearchData = true;
					$pubInfo['articleFirstPage'] = $articleFirstPage;
				}
				$articleLastPage = FedoraUtils::cleanValue($this->getModsValue('articleLastPage', 'marmot', $publicationPresentationData));
				if (strlen($articleLastPage)) {
					$hasAcademicResearchData = true;
					$pubInfo['articleLastPage'] = $articleLastPage;
				}
				$conferenceName = FedoraUtils::cleanValue($this->getModsValue('conferenceName', 'marmot', $publicationPresentationData));
				if (strlen($conferenceName)) {
					$hasAcademicResearchData = true;
					$pubInfo['conferenceName'] = $conferenceName;
				}
				$conferencePresentationDate = FedoraUtils::cleanValue($this->getModsValue('conferencePresentationDate', 'marmot', $publicationPresentationData));
				if (strlen($articleLastPage)) {
					$hasAcademicResearchData = true;
					$formattedDate = DateTime::createFromFormat('Y-m-d', $conferencePresentationDate);
					if ($formattedDate != false) {
						$conferencePresentationDate = $formattedDate->format('m/d/Y');
					}
					$pubInfo['conferencePresentationDate'] = $conferencePresentationDate;
				}


				if (count($pubInfo) > 0){
					$publicationPresentations[] = $pubInfo;
				}
			}
			$interface->assign('publicationPresentations', $publicationPresentations);
		}
		$interface->assign('hasAcademicResearchData', $hasAcademicResearchData);
		return $hasAcademicResearchData;
	}

	private $showBurialData;
	public function loadLinkedData(){
		global $interface;
		foreach ($this->getLinks() as $link){
			if ($link['type'] == 'wikipedia'){
				global $library;
				if ($library->showWikipediaContent){
					require_once ROOT_DIR . '/sys/WikipediaParser.php';
					$wikipediaParser = new WikipediaParser('en');

					//Transform from a regular wikipedia link to an api link
					$searchTerm = str_replace('https://en.wikipedia.org/wiki/', '', $link['link']);
					$url = "http://en.wikipedia.org/w/api.php" .
							'?action=query&prop=revisions&rvprop=content&format=json' .
							'&titles=' . urlencode(urldecode($searchTerm));
					$wikipediaData = $wikipediaParser->getWikipediaPage($url);
					$interface->assign('wikipediaData', $wikipediaData);
				}

			}elseif($link['type'] == 'marmotGenealogy'){
				$matches = array();
				if (preg_match('/.*Person\/(\d+)/', $link['link'], $matches)){
					$personId = $matches[1];
					require_once ROOT_DIR . '/sys/Genealogy/Person.php';
					$person = new Person();
					$person->personId = $personId;
					if ($person->find(true)){
						$interface->assign('genealogyData', $person);
						if ($person->cemeteryName
								|| $person->cemeteryLocation
								|| $person->cemeteryAvenue
								|| $person->addition
								|| $person->lot
								|| $person->block
								|| $person->grave
								|| $person->tombstoneInscription
								|| $person->mortuaryName){
							$this->showBurialData;
						}

						$formattedBirthdate = $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear);
						$interface->assign('birthDate', $formattedBirthdate);

						$formattedDeathdate = $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear);
						$interface->assign('deathDate', $formattedDeathdate);

						$marriages = array();
						$personMarriages = $person->marriages;
						if (isset($personMarriages)){
							foreach ($personMarriages as $marriage){
								$marriageArray = (array)$marriage;
								$marriageArray['formattedMarriageDate'] = $person->formatPartialDate($marriage->marriageDateDay, $marriage->marriageDateMonth, $marriage->marriageDateYear);
								$marriages[] = $marriageArray;
							}
						}
						$interface->assign('marriages', $marriages);
						$obituaries = array();
						$personObituaries =$person->obituaries;
						if (isset($personObituaries)){
							foreach ($personObituaries as $obit){
								$obitArray = (array)$obit;
								$obitArray['formattedObitDate'] = $person->formatPartialDate($obit->dateDay, $obit->dateMonth, $obit->dateYear);
								$obituaries[] = $obitArray;
							}
						}
						$interface->assign('obituaries', $obituaries);
					}
				}
			}
		}
	}

	private function loadEducationInfo() {
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$interface->assign('hasEducationInfo', false);
		$academicRecord = $this->getModsValue('education', 'marmot');
		if (strlen($academicRecord) > 0){
			$degreeName = FedoraUtils::cleanValue($this->getModsValue('degreeName', 'marmot', $academicRecord));
			if ($degreeName){
				$interface->assign('degreeName', $degreeName);
				$hasEducationInfo = true;
			}

			$graduationDate = FedoraUtils::cleanValue($this->getModsValue('graduationDate', 'marmot', $academicRecord));
			if ($graduationDate){
				$interface->assign('graduationDate', $graduationDate);
				$hasEducationInfo = true;
			}

			$relatedEducationPeople = $this->getModsValues('relatedPersonOrg', 'marmot', $academicRecord);
			if ($relatedEducationPeople){
				$educationPeople = array();
				foreach ($relatedEducationPeople as $relatedPerson){
					$personPid = $this->getModsValue('entityPid', 'marmot', $relatedPerson);
					$role = ucwords($this->getModsValue('role', 'marmot', $relatedPerson));
					if ($personPid){
						$educationPersonObject = $fedoraUtils->getObject($personPid);
						if ($educationPersonObject){
							$educationPersonDriver = RecordDriverFactory::initRecordDriver($educationPersonObject);
							$educationPeople[] = array(
									'link' => $educationPersonDriver->getRecordUrl(),
									'label' => $educationPersonDriver->getTitle(),
									'role' => $role
							);
						}
					}else{
						$personTitle = $this->getModsValue('entityTitle', 'marmot', $relatedPerson);
						if ($personTitle){
							$educationPeople[] = array(
									'label' => $personTitle,
									'role' => $role
							);
						}
					}
					$hasEducationInfo = true;
				}
				if (count($educationPeople) > 0){
					$interface->assign('educationPeople', $educationPeople);
				}
			}

			$interface->assign('hasEducationInfo', $hasEducationInfo);
		}
	}

	private function loadMilitaryServiceData() {
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$hasMilitaryService = false;
		$interface->assign('hasMilitaryService', $hasMilitaryService);
		$militaryService = $this->getModsValue('militaryService', 'marmot');
		if (strlen($militaryService) > 0){
			/** @var SimpleXMLElement $record */
			$militaryRecord = $this->getModsValue('militaryRecord', 'marmot', $militaryService);
			$militaryBranch = $this->getModsValue('militaryBranch', 'marmot', $militaryRecord);
			$militaryConflict = $this->getModsValue('militaryConflict', 'marmot', $militaryRecord);
			if ($militaryBranch != 'none' || $militaryConflict != 'none'){
				$militaryRecord = array(
						'branch' => $fedoraUtils->getObjectLabel($militaryBranch),
						'branchLink' => '/Archive/' . $militaryBranch . '/Organization',
						'conflict' => $fedoraUtils->getObjectLabel($militaryConflict),
						'conflictLink' => '/Archive/' . $militaryConflict . '/Event',
				);
				$interface->assign('militaryRecord', $militaryRecord);
				$hasMilitaryService = true;
			}
		}
		$interface->assign('hasMilitaryService', $hasMilitaryService);
		return $hasMilitaryService;
	}

	private function loadNotes() {
		global $interface;
		$notes = array();
		$generalNotes = $this->getModsValues('note', 'mods', null, true);
		foreach ($generalNotes as $tmpNote) {
			if (preg_match('~xmlns:mods="http://www.loc.gov/mods/v3"~', $tmpNote)) {
				$noteValue = $this->getModsValue('note', 'mods', $tmpNote);
				if (strlen($noteValue) > 0) {
					$notes[] = array(
							'label' => 'General Notes',
							'body' => $noteValue
					);
				}
			}
		}

		$personNotes = $this->getModsValue('personNotes', 'marmot');
		if (strlen($personNotes) > 0){
			$notes[] = array(
					'label' => 'Notes',
					'body' => $personNotes
			);
		}
		$citationNotes = $this->getModsValue('citationNotes', 'marmot');
		if (strlen($citationNotes) > 0){
			$notes[] = array(
					'label' => 'Citation Notes',
					'body' => $citationNotes
			);
		}

		$interface->assignAppendToExisting('notes', $notes);
	}

	private $hasDetails;
	private function loadRecordInfo() {
		global $interface;
		$this->hasDetails = false;
		$recordInfo = $this->getModsValue('identifier', 'recordInfo');
		if (strlen($recordInfo)){
			$recordOrigin = $this->getModsValue('recordOrigin', 'mods', $recordInfo);
			$interface->assign('recordOrigin', $recordOrigin);

			$recordCreationDate = $this->getModsValue('recordCreationDate', 'mods', $recordInfo);
			$interface->assign('recordCreationDate', $recordCreationDate);

			$recordChangeDate = $this->getModsValue('recordChangeDate', 'mods', $recordInfo);
			$interface->assign('recordChangeDate', $recordChangeDate);
		}

		$identifier = $this->getModsValues('identifier', 'mods');
		$interface->assignAppendToExisting('identifier', FedoraUtils::cleanValues($identifier));

		$originInfo = $this->getModsValue('originInfo', 'mods');
		if (strlen($originInfo)){
			$datesCreated = $this->getModsValues('dateCreated', 'mods', $originInfo, true);
			$dateCreated = '';
			foreach ($datesCreated as $dateCreatedTag){
				$dateCreatedValue = $this->getModsValue('dateCreated', 'mods', $dateCreatedTag);
				$formattedDate = DateTime::createFromFormat('Y-m-d', $dateCreatedValue);
				if ($formattedDate != false) {
					$dateCreatedValue = $formattedDate->format('m/d/Y');
				}
				if ($dateCreatedValue){
					$point = $this->getModsAttribute('point', $dateCreatedTag);
					$qualifier = $this->getModsAttribute('qualifier', $dateCreatedTag);

					if ($point == null || $point == 'start'){
						$dateCreated = $dateCreatedValue;
						if ($qualifier){
							$dateCreated .= " ({$qualifier})";
						}
					}else{
						$dateCreated .= " - " . $dateCreatedValue;
						if ($qualifier){
							$dateCreated .= " ({$qualifier})";
						}
					}
				}
			}

			if ($dateCreated){
				$this->hasDetails = true;
			}
			$interface->assign('dateCreated', $dateCreated);

			$dateIssuedTag = $this->getModsValue('dateIssued', 'mods', $originInfo, true);
			$dateIssued = '';
			if ($dateIssuedTag){
				$dateIssuedValue = $this->getModsValue('dateIssued', 'mods', $dateIssuedTag);
				$formattedDate = DateTime::createFromFormat('Y-m-d', $dateIssuedValue);
				if ($formattedDate != false) {
					$dateIssuedValue = $formattedDate->format('m/d/Y');
				}
				$qualifier = $this->getModsAttribute('qualifier', $dateIssuedTag);
				$dateIssued = $dateIssuedValue;
				if ($qualifier){
					$dateIssued .= " ({$qualifier})";
				}
			}

			if ($dateIssued){
				$this->hasDetails = true;
			}
			$interface->assign('dateIssued', $dateIssued);
		}

		$language = FedoraUtils::cleanValue($this->getModsValue('languageTerm', 'mods'));
		if ($language){
			$this->hasDetails = true;
		}
		$interface->assign('language', $language);

		$physicalDescriptions = $this->getModsValues('physicalDescription', 'mods');
		$physicalExtents = array();
		foreach ($physicalDescriptions as $physicalDescription){
			$extent = $this->getModsValue('extent', 'mods', $physicalDescription);
			$form = $this->getModsValue('form', 'mods', $physicalDescription);
			$note = $this->getModsValue('note', 'mods', $physicalDescription);
			if (empty($extent)){
				$extent = $form;
			}elseif (!empty($form) && !empty($note)){
				$extent .= " ($form, $note)";
			}elseif (!empty($form)){
				$extent .= " ($form)";
			}elseif (!empty($note)){
				$extent .= " ($note)";
			}
			$physicalExtents[] = $extent;

		}
		$interface->assign('physicalExtents', $physicalExtents);

		$physicalLocation = $this->getModsValues('physicalLocation', 'mods');
		$interface->assign('physicalLocation', $physicalLocation);

		$shelfLocator = $this->getModsValues('shelfLocator', 'mods');
		$interface->assign('shelfLocator', FedoraUtils::cleanValues($shelfLocator));

		$collections = $this->getRelatedCollections();
		$interface->assign('collectionInfo', $collections);

		//Load migration information
		$migratedFileName = $this->getModsValue('migratedFileName', 'marmot');
		$interface->assign('migratedFileName', $migratedFileName);

		$migratedIdentifier = $this->getModsValue('migratedIdentifier', 'marmot');
		$interface->assign('migratedIdentifier', FedoraUtils::cleanValue($migratedIdentifier));

		$migrationContextNotes = $this->getModsValue('contextNotes', 'marmot');
		$interface->assign('contextNotes', FedoraUtils::cleanValue($migrationContextNotes));

		$relationshipNotes = $this->getModsValue('relationshipNotes', 'marmot');
		$interface->assign('relationshipNotes', FedoraUtils::cleanValue($relationshipNotes));

		return true;
	}

	private function loadRightsStatements() {
		global $interface;
		require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
		$fedoraUtils = FedoraUtils::getInstance();

		$rightsStatements = $this->getModsValues('rightsStatement', 'marmot');
		foreach ($rightsStatements as $id => $rightsStatement){
			$rightsStatement = str_replace("\r\n", '<br/>', $rightsStatement);
			$rightsStatement = str_replace("&#xD;", '<br/>', $rightsStatement);
			$rightsStatements[$id] = $rightsStatement;
		}

		$interface->assignAppendUniqueToExisting('rightsStatements', $rightsStatements);

		$rightsEffectiveDate = $this->getModsValue('rightsEffectiveDate', 'marmot');
		$formattedDate = DateTime::createFromFormat('Y-m-d', $rightsEffectiveDate);
		if ($formattedDate != false) {
			$rightsEffectiveDate = $formattedDate->format('m/d/Y');
		}
		$interface->assign('rightsEffectiveDate', $rightsEffectiveDate);

		$rightsExpirationDate = $this->getModsValue('rightsExpirationDate', 'marmot');
		$formattedDate = DateTime::createFromFormat('Y-m-d', $rightsExpirationDate);
		if ($formattedDate != false) {
			$rightsExpirationDate = $formattedDate->format('m/d/Y');
		}
		$interface->assign('rightsExpirationDate', $rightsExpirationDate);

		$rightsHolders = $this->getModsValues('rightsHolder', 'marmot');
		$rightsHolderData = array();
		foreach ($rightsHolders as $rightsHolder) {
			$rightsHolderPid = $this->getModsValue('entityPid', 'marmot', $rightsHolder);
			$rightsHolderTitle = $this->getModsValue('entityTitle', 'marmot', $rightsHolder);
			$validRightsHolder = false;
			if ($rightsHolderPid) {
				$rightsHolderObj = $fedoraUtils->getObject($rightsHolderPid);
				if ($rightsHolderObj){
					$rightsHolderDriver = RecordDriverFactory::initRecordDriver($rightsHolderObj);
					$rightsHolderData[] = array(
							'label' => $rightsHolderTitle,
							'link' => $rightsHolderDriver->getRecordUrl()
					);
					$validRightsHolder = true;
				}
			}
			if (!$validRightsHolder){
				$rightsHolderData[] = array(
						'label' => $rightsHolderTitle,
				);
			}
		}
		$interface->assign('rightsHolders', $rightsHolderData);

		$rightsCreator = $this->getModsValue('rightsCreator', 'marmot');
		if (!empty($rightsCreator)) {
			$rightsCreatorPid = $this->getModsValue('entityPid', 'marmot', $rightsCreator);
			$rightsCreatorTitle = $this->getModsValue('entityTitle', 'marmot', $rightsCreator);
			if ($rightsCreatorPid) {
				$interface->assign('rightsCreatorTitle', $rightsCreatorTitle);
				$rightsCreatorObj = RecordDriverFactory::initRecordDriver($fedoraUtils->getObject($rightsCreatorPid));
				$interface->assign('rightsCreatorLink', $rightsCreatorObj->getRecordUrl());
			}
		}

		$limitationsNotes = $this->getModsValue('limitationsNotes', 'marmot');
		$interface->assign('limitationsNotes', $limitationsNotes);
	}

	protected $pidsOfChildContainers = null;
	public function getPIDsOfChildContainers(){
		if ($this->pidsOfChildContainers == null){
			$this->pidsOfChildContainers = array();

			// Include Search Engine Class
			require_once ROOT_DIR . '/sys/Solr.php';

			// Initialise from the current search globals
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setLimit(100);
			$searchObject->setSort('fgs_label_s');
			$searchObject->setBasicQuery('RELS_EXT_isMemberOfCollection_uri_mt:"info:fedora/' . $this->getUniqueID() .'" OR RELS_EXT_isMemberOf_uri_mt:"info:fedora/' . $this->getUniqueID() .'"');
			$searchObject->addFieldsToReturn(array('RELS_EXT_isMemberOfCollection_uri_mt'));

			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->addHiddenFilter('RELS_EXT_hasModel_uri_s', "(info\:fedora/islandora\:collectionCModel)");
			$searchObject->clearFilters();
			$searchObject->setApplyStandardFilters(false);
			$response = $searchObject->processSearch(true, false, true);
			if ($response && $response['response']['numFound'] > 0) {
				foreach ($response['response']['docs'] as $doc) {
					$subCollectionPid = $doc['PID'];
					$this->pidsOfChildContainers[$subCollectionPid] = $subCollectionPid;
				}
			}

			$grandKids = array();
			foreach ($this->pidsOfChildContainers as $childPid){
				require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
				$fedoraUtils = FedoraUtils::getInstance();
				$exhibitObject = $fedoraUtils->getObject($childPid);
				/** @var IslandoraDriver $exhibitDriver */
				$exhibitDriver = RecordDriverFactory::initRecordDriver($exhibitObject);
				$kidsOfThisChild = $exhibitDriver->getPIDsOfChildContainers();
				$grandKids = array_merge($grandKids, $kidsOfThisChild);
			}
			$this->pidsOfChildContainers = array_merge($this->pidsOfChildContainers, $grandKids);
		}
		return $this->pidsOfChildContainers;
	}

	protected $childObjects = null;
	public function getChildren() {
		if ($this->childObjects == null){
			$this->childObjects = array();
			// Include Search Engine Class
			require_once ROOT_DIR . '/sys/Solr.php';

			// Initialise from the current search globals
			/** @var SearchObject_Islandora $searchObject */
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setLimit(100);
			$searchObject->setSort('fgs_label_s');
			$searchObject->setSearchTerms(array(
					'lookfor' => '"info:fedora/' . $this->getUniqueID() .'"',
					'index' => 'RELS_EXT_isMemberOfCollection_uri_mt'
			));
			$searchObject->addFieldsToReturn(array('RELS_EXT_isMemberOfCollection_uri_mt'));

			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->setApplyStandardFilters(false);
			$response = $searchObject->processSearch(true, false, true);
			if ($response && $response['response']['numFound'] > 0) {
				foreach ($response['response']['docs'] as $doc) {
					$subCollectionPid = $doc['PID'];
					$this->childObjects[] = $subCollectionPid;
				}
			}

			//Also check isMemberOf for pages within a book
			$searchObject = SearchObjectFactory::initSearchObject('Islandora');
			$searchObject->init();
			$searchObject->setLimit(100);
			$searchObject->setSort('fgs_label_s');
			$searchObject->setSearchTerms(array(
					'lookfor' => '"info:fedora/' . $this->getUniqueID() .'"',
					'index' => 'RELS_EXT_isMemberOf_uri_mt'
			));
			$searchObject->addFieldsToReturn(array('RELS_EXT_isMemberOf_uri_mt'));

			$searchObject->clearHiddenFilters();
			$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
			$searchObject->clearFilters();
			$searchObject->setApplyStandardFilters(false);
			$response = $searchObject->processSearch(true, false, true);
			if ($response && $response['response']['numFound'] > 0) {
				foreach ($response['response']['docs'] as $doc) {
					$subCollectionPid = $doc['PID'];
					$this->childObjects[] = $subCollectionPid;
				}
			}
		}
		return $this->childObjects;
	}

	public function getRandomObject() {
		// Include Search Engine Class
		require_once ROOT_DIR . '/sys/Solr.php';

		// Initialise from the current search globals
		/** @var SearchObject_Islandora $searchObject */
		$searchObject = SearchObjectFactory::initSearchObject('Islandora');
		$searchObject->init();
		$searchObject->setLimit(1);
		$now = time();
		$searchObject->setSort("random_$now asc");
		$searchObject->setSearchTerms(array(
				'lookfor' => '"info:fedora/' . $this->getUniqueID() .'"',
				'index' => 'RELS_EXT_isMemberOfCollection_uri_mt'
		));

		$searchObject->clearHiddenFilters();
		$searchObject->addHiddenFilter('!RELS_EXT_isViewableByRole_literal_ms', "administrator");
		$searchObject->clearFilters();
		$searchObject->setApplyStandardFilters(false);
		$response = $searchObject->processSearch(true, false, true);
		if ($response && $response['response']['numFound'] > 0) {
			foreach ($response['response']['docs'] as $doc) {
				return $doc['PID'];
			}
		}
		return null;
	}

	public function getBrandingInformation() {
		if (!$this->loadedBrandingFromCollection){
			$this->loadRelatedEntities();
			$this->loadedBrandingFromCollection = true;

			//Get the contributing institution
			list($namespace) = explode(':', $this->getUniqueID());
			$contributingLibrary = new Library();
			$contributingLibrary->archiveNamespace = $namespace;
			if (!$contributingLibrary->find(true)){
				$contributingLibrary = null;
			}else{
				if ($contributingLibrary->archivePid == ''){
					$contributingLibrary = null;
				}
			}

			if ($contributingLibrary){
				require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
				$fedoraUtils = FedoraUtils::getInstance();

				$contributingLibraryPid = $contributingLibrary->archivePid;
				require_once ROOT_DIR . '/sys/Islandora/IslandoraObjectCache.php';
				$islandoraCache = new IslandoraObjectCache();
				$islandoraCache->pid = $contributingLibraryPid;
				if ($islandoraCache->find(true) && !empty($islandoraCache->mediumCoverUrl)){
					$imageUrl = $islandoraCache->mediumCoverUrl;
				}else{
					$imageUrl = $fedoraUtils->getObjectImageUrl($fedoraUtils->getObject($contributingLibraryPid), 'medium');
				}
				$this->brandingEntities[$contributingLibraryPid] = array(
						'label' => 'Contributed by ' . $islandoraCache->title,
						'image' => $imageUrl,
						'link' => "/Archive/$contributingLibraryPid/Organization",
						'sortIndex' => 9,
						'pid' => $contributingLibraryPid
				);
			}

			$collections = $this->getRelatedCollections();
			foreach ($collections as $collection){
				/** @var CollectionDriver $collectionDriver */
				$collectionDriver = $collection['driver'];
				$collectionBranding = $collectionDriver->getBrandingInformation();
				foreach ($collectionBranding as $key => $entity){
					if ($entity['sortIndex'] <= 3){
						$entity['sortIndex'] += 4;
					}
					$collectionBranding[$key] = $entity;
				}
				$this->brandingEntities = array_merge($this->brandingEntities, $collectionBranding);
			}
		}
		return $this->brandingEntities;
	}

	private $viewingRestrictions = null;

	/**
	 * @return array
	 */
	public function getViewingRestrictions() {
		if ($this->viewingRestrictions == null) {
			$this->viewingRestrictions = array();
			$accessLimits = $this->getModsValue('pikaAccessLimits', 'marmot');
			if ($accessLimits == 'all') {
				//No restrictions needed, don't check the parent collections
			}else if ($accessLimits == 'default' || $accessLimits == null) {
				$parentCollections = $this->getRelatedCollections();
				foreach ($parentCollections as $collection) {
					$collectionDriver = $collection['driver'];
					$accessLimits = $collectionDriver->getViewingRestrictions();
					if (count($accessLimits) > 0){
						$this->viewingRestrictions = array_merge($this->viewingRestrictions, $accessLimits);
					}
				}
			}else{
				$accessLimits = preg_split('/[\r\n,]/', $accessLimits);
				$this->viewingRestrictions = array_merge($this->viewingRestrictions, $accessLimits);
			}
		}
		return $this->viewingRestrictions;
	}

	private $showClaimAuthorship = null;

	/**
	 * @return boolean
	 */
	public function getShowClaimAuthorship() {
		if ($this->showClaimAuthorship == null){
			$showClaimAuthorship = $this->getModsValue('showClaimAuthorship', 'marmot');
			if ($showClaimAuthorship == null || strcasecmp($showClaimAuthorship, 'no') === 0){
				$this->showClaimAuthorship = false;
			}else{
				$this->showClaimAuthorship = true;
			}
		}
		return $this->showClaimAuthorship;
	}

	private function loadArtInformation() {
		global $interface;
		$hasArtInformation = false;
		$artSection = $this->getModsValue('art', 'marmot');
		if ($artSection){
			require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';
			$fedoraUtils = FedoraUtils::getInstance();

			$materialDescription = $this->getModsValue('materialDescription', 'marmot', $artSection);
			if ($materialDescription){
				$interface->assign('materialDescription', $materialDescription);
				$hasArtInformation = true;
			}

			$materialsSections = $this->getModsValues('material', 'marmot', $artSection);
			$materials = array();
			foreach ($materialsSections as $materialsSection){
				$materialTerm = $this->getModsValue('materialTerm', 'marmot', $materialsSection);
				$aatID = $this->getModsValue('aatID', 'marmot', $materialsSection);
				if ($aatID){
					$link = "/Archive/Results?lookfor=$aatID";
				}else{
					$link = null;
				}
				if ($materialTerm){
					$materials[] = array(
							'label' => $materialTerm,
							'aatID' => $aatID,
							'link' => $link
					);
				}
			}
			if (count($materials) > 0){
				$interface->assign('materials', $materials);
				$hasArtInformation = true;
			}

			$styleAndPeriodSections = $this->getModsValues('stylePeriodSet', 'marmot', $artSection);
			$stylesAndPeriods = array();
			foreach ($styleAndPeriodSections as $styleAndPeriodSection){
				$term = $this->getModsValue('stylePeriodTerm', 'marmot', $styleAndPeriodSection);
				$aatID = $this->getModsValue('aatID', 'marmot', $styleAndPeriodSection);
				if ($aatID){
					$link = "/Archive/Results?lookfor=$aatID";
				}else{
					$link = null;
				}
				if ($term){
					$stylesAndPeriods[] = array(
							'label' => $term,
							'aatID' => $aatID,
							'link' => $link
					);
				}
			}
			if (count($stylesAndPeriods) > 0){
				$interface->assign('stylesAndPeriods', $stylesAndPeriods);
				$hasArtInformation = true;
			}

			$techniquesSections = $this->getModsValues('techniqueSet', 'marmot', $artSection);
			$techniques = array();
			foreach ($techniquesSections as $techniquesSection){
				$term = $this->getModsValue('techniqueTerm', 'marmot', $techniquesSection);
				$aatID = $this->getModsValue('aatID', 'marmot', $techniquesSection);
				if ($aatID){
					$link = "/Archive/Results?lookfor=$aatID";
				}else{
					$link = null;
				}
				if ($term){
					$techniques[] = array(
							'label' => $term,
							'aatID' => $aatID,
							'link' => $link
					);
				}
			}
			if (count($techniques) > 0){
				$interface->assign('techniques', $techniques);
				$hasArtInformation = true;
			}

			$measurementsSections = $this->getModsValues('measurementSet', 'marmot', $artSection);
			$measurements = array();
			foreach ($measurementsSections as $measurementsSection){
				$type = $this->getModsValue('measurementType', 'marmot', $measurementsSection);
				$unit = $this->getModsValue('measurementUnit', 'marmot', $measurementsSection);
				$number = $this->getModsValue('measurementNumber', 'marmot', $measurementsSection);
				if ($type || $unit || $number){
					$label = "$type: $number $unit";
					if ($label){
						$measurements[] = $label;
					}
				}
			}
			if (count($measurements) > 0){
				$interface->assign('measurements', $measurements);
				$hasArtInformation = true;
			}

			$installationsSections = $this->getModsValues('artInstallation', 'marmot', $artSection);
			$installations = array();
			foreach ($installationsSections as $installationsSection){
				$installationDate = $this->getModsValue('installationDate', 'marmot', $installationsSection);
				$removalDate = $this->getModsValue('removalDate', 'marmot', $installationsSection);

				$label = $installationDate;
				if ($removalDate){
					$label .= " to $removalDate";
				}

				$entityPlace = $this->getModsValue('entityPlace', 'marmot', $installationsSection);
				$placePid = $this->getModsValue('entityPid', 'marmot', $entityPlace);
				$validPlace = false;
				if ($placePid){
					$placeObject = $fedoraUtils->getObject($placePid);
					if ($placeObject){
						$placeDriver = RecordDriverFactory::initRecordDriver($placeObject);
						$label .= " (<a href='{$placeDriver->getRecordUrl()}'>{$placeDriver->getTitle()}</a>)";
						$validPlace = true;
					}
				}
				if (!$validPlace){
					$placeTitle = $this->getModsValue('entityTitle', 'marmot', $entityPlace);
					if ($placeTitle){
						$label .= " ($placeTitle)";
					}else{
						$latitude = $this->getModsValue('latitude', 'marmot', $installationsSection);
						$longitude = $this->getModsValue('longitude', 'marmot', $installationsSection);
						if ($latitude || $longitude){
							$label .= " ($latitude, $longitude)";
						}
					}
				}

				if ($label){
					$installations[] = $label;
				}
			}
			if (count($installations) > 0){
				$interface->assign('installations', $installations);
				$hasArtInformation = true;
			}
		}//End art section

		return $hasArtInformation;
	}
}