<?php
/**
 * Table Definition for library
 */
require_once 'DB/DataObject.php';
require_once 'DB/DataObject/Cast.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/Holiday.php';
require_once ROOT_DIR . '/Drivers/marmot_inc/LibraryFacetSetting.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordOwned.php';
require_once ROOT_DIR . '/sys/Indexing/LibraryRecordToInclude.php';
require_once ROOT_DIR . '/sys/Browse/LibraryBrowseCategory.php';
require_once ROOT_DIR . '/sys/LibraryMoreDetails.php';
require_once ROOT_DIR . '/sys/LibraryLink.php';
require_once ROOT_DIR . '/sys/LibraryTopLinks.php';

class Library extends DB_DataObject
{
	public $__table = 'library';    // table name
	public $isDefault;
	public $libraryId; 				//int(11)
	public $subdomain; 				//varchar(15)
	public $displayName; 			//varchar(50)
	public $showDisplayNameInHeader;
	public $headerText;
	public $abbreviatedDisplayName;
	public $systemMessage;
	public $ilsCode;
	public $themeName; 				//varchar(15)
	public $restrictSearchByLibrary;
	public $includeOutOfSystemExternalLinks;
	public $allowProfileUpdates;   //tinyint(4)
	public $allowFreezeHolds;   //tinyint(4)
	public $scope; 					//smallint(6)
	public $useScope;		 		//tinyint(4)
	public $hideCommentsWithBadWords; //tinyint(4)
	public $showStandardReviews;
	public $showHoldButton;
	public $showHoldButtonInSearchResults;
	public $showHoldButtonForUnavailableOnly;
	public $showLoginButton;
	public $showTextThis;
	public $showEmailThis;
	public $showComments; // User Reviews switch
	public $showTagging;
	public $showRatings; // User Ratings
	public $showFavorites;
	public $showTableOfContentsTab;
	public $notesTabName;
	public $inSystemPickupsOnly;
	public $validPickupSystems;
	public $pTypes;
	public $defaultPType;
	public $facetLabel;
	public $showAvailableAtAnyLocation;
	public $showEcommerceLink;
	public $payFinesLink;
	public $payFinesLinkText;
	public $minimumFineAmount;
	public $showRefreshAccountButton;    // specifically to refresh account after paying fines online
	public $goldRushCode;
	public $repeatSearchOption;
	public $repeatInOnlineCollection;
	public $repeatInProspector;
	public $repeatInWorldCat;
	public $enableOverdriveCollection;
	public $includeOverDriveAdult;
	public $includeOverDriveTeen;
	public $includeOverDriveKids;
	public $repeatInOverdrive;
	public $overdriveAuthenticationILSName;
	public $overdriveRequirePin;
	public $overdriveAdvantageName;
	public $overdriveAdvantageProductsKey;
	public $systemsToRepeatIn;
	public $additionalLocationsToShowAvailabilityFor;
	public $homeLink;
	public $homeLinkText;
	public $useHomeLinkInBreadcrumbs;
	public $useHomeLinkForLogo;
	public $showAdvancedSearchbox;
	public $enablePospectorIntegration;
	public $showProspectorResultsAtEndOfSearch;
	public $prospectorCode;
	public $enableGenealogy;
	public $showHoldCancelDate;
	public $enableCourseReserves;
	public $enableSelfRegistration;
	public $promptForBirthDateInSelfReg;
	public $showItsHere;
	public $holdDisclaimer;
	public $enableMaterialsRequest;
	public $externalMaterialsRequestUrl;
	public $eContentLinkRules;
	public $includeNovelistEnrichment;
	public $applyNumberOfHoldingsBoost;
	public $allowAutomaticSearchReplacements;
	public $show856LinksAsTab;
	public $worldCatUrl;
	public $worldCatQt;
	public $preferSyndeticsSummary;
	public $showSimilarAuthors;
	public $showSimilarTitles;
	public $showGoDeeper;
	public $defaultNotNeededAfterDays;
	public $showCheckInGrid;
	public $boostByLibrary;
	public $additionalLocalBoostFactor;
	public $recordsToBlackList;
	public $publicListsToInclude;
	public $showWikipediaContent;
	public $eContentSupportAddress;
	public $restrictOwningBranchesAndSystems;
	public $allowPatronAddressUpdates;
	public $showWorkPhoneInProfile;
	public $showNoticeTypeInProfile;
	public $showPickupLocationInProfile;
	public $showAlternateLibraryOptionsInProfile;
	public $additionalCss;
	public $maxRequestsPerYear;
	public $maxOpenRequests;
	// Contact Links //
	public $twitterLink;
	public $facebookLink;
	public $youtubeLink;
	public $instagramLink;
	public $goodreadsLink;
	public $generalContactLink;

	public $allowPinReset;
	public $showLibraryHoursAndLocationsLink;
	public $showLibraryHoursNoticeOnAccountPages;
	public $showSearchTools;
	public $showShareOnExternalSites;
	public $showQRCode;
	public $showGoodReadsReviews;
	public $showStaffView;
	public $barcodePrefix;
	public $minBarcodeLength;
	public $maxBarcodeLength;
	public $econtentLocationsToInclude;
	public $showExpirationWarnings;
	public $availabilityToggleLabelSuperScope;
	public $availabilityToggleLabelLocal;
	public $availabilityToggleLabelAvailable;
	public $loginFormUsernameLabel;
	public $loginFormPasswordLabel;
	public $showDetailedHoldNoticeInformation;
	public $treatPrintNoticesAsPhoneNotices;
	public $includeDplaResults;
	public $showInMainDetails;
	public $selfRegistrationFormMessage;
	public $selfRegistrationSuccessMessage;
	public $selfRegistrationTemplate;
	public $addSMSIndicatorToPhone;
	public $defaultBrowseMode;
	public $browseCategoryRatingsMode;
	public $enableMaterialsBooking;
	public $allowLinkedAccounts;
	public $horizontalSearchBar;
	public $sideBarOnRight;
	public $enableArchive;
	public $showBisacSubjects;
	public $showStandardSubjects;
	public $showFastAddSubjects;
	public $maxFinesToAllowAccountUpdates;
	public $edsApiProfile;
	public $edsApiUsername;
	public $edsApiPassword;
	protected $patronNameDisplayStyle; //Needs to be protected so __get and __set are called
	private $patronNameDisplayStyleChanged = false; //Track changes so we can clear values for existing patrons

	// Use this to set which details will be shown in the the Main Details section of the record view.
	// You should be able to add options here without needing to change the database.
	// set the key to the desired SMARTY template variable name, set the value to the label to show in the library configuration page
	static $showInMainDetailsOptions = array(
		'showPublicationDetails' => 'Published',
		'showFormats' => 'Formats',
		'showEditions' => 'Editions',
		'showPhysicalDescriptions' => 'Physical Descriptions',
		'showLocations' => 'Locations',
		'showISBNs' => 'ISBNs',
	);

	/* Static get */
//	function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Library',$k,$v); }

	function keys() {
		return array('libraryId', 'subdomain');
	}

	static function getObjectStructure(){
		// get the structure for the library system's holidays
		$holidaysStructure = Holiday::getObjectStructure();

		// we don't want to make the libraryId property editable
		// because it is associated with this library system only
		unset($holidaysStructure['libraryId']);

		$facetSettingStructure = LibraryFacetSetting::getObjectStructure();
		unset($facetSettingStructure['weight']);
		unset($facetSettingStructure['libraryId']);
		unset($facetSettingStructure['numEntriesToShowByDefault']);
		unset($facetSettingStructure['showAsDropDown']);
		//unset($facetSettingStructure['sortMode']);

		$libraryMoreDetailsStructure = LibraryMoreDetails::getObjectStructure();
		unset($libraryMoreDetailsStructure['weight']);
		unset($libraryMoreDetailsStructure['libraryId']);

		$libraryLinksStructure = LibraryLink::getObjectStructure();
		unset($libraryLinksStructure['weight']);
		unset($libraryLinksStructure['libraryId']);

		$libraryTopLinksStructure = LibraryTopLinks::getObjectStructure();
		unset($libraryTopLinksStructure['weight']);
		unset($libraryTopLinksStructure['libraryId']);

		$libraryBrowseCategoryStructure = LibraryBrowseCategory::getObjectStructure();
		unset($libraryBrowseCategoryStructure['weight']);
		unset($libraryBrowseCategoryStructure['libraryId']);

		$libraryRecordOwnedStructure = LibraryRecordOwned::getObjectStructure();
		unset($libraryRecordOwnedStructure['libraryId']);

		$libraryRecordToIncludeStructure = LibraryRecordToInclude::getObjectStructure();
		unset($libraryRecordToIncludeStructure['libraryId']);
		unset($libraryRecordToIncludeStructure['weight']);

		global $user;
		require_once ROOT_DIR . '/sys/ListWidget.php';
		$widget = new ListWidget();
		if (($user->hasRole('libraryAdmin') || $user->hasRole('contentEditor')) && !$user->hasRole('opacAdmin') || $user->hasRole('libraryManager') || $user->hasRole('locationManager')){
			$patronLibrary = Library::getPatronHomeLibrary();
			if ($patronLibrary){
				$widget->libraryId = $patronLibrary->libraryId;
			}
		}
		$availableWidgets = array();
		$widget->orderBy('name');
		$widget->find();
		$availableWidgets[0] = 'No Widget';
		while ($widget->fetch()){
			$availableWidgets[$widget->id] = $widget->name;
		}

		//$Instructions = 'For more information on ???, see the <a href="">online documentation</a>.';

		$structure = array(
			'isDefault' => array('property' => 'isDefault', 'type'=>'checkbox', 'label' => 'Default Library (one per install!)', 'description' => 'The default library instance for loading scoping information etc', 'hideInLists' => true),
			'libraryId' => array('property'=>'libraryId', 'type'=>'label', 'label'=>'Library Id', 'description'=>'The unique id of the library within the database'),
			'subdomain' => array('property'=>'subdomain', 'type'=>'text', 'label'=>'Subdomain', 'description'=>'A unique id to identify the library within the system'),
			'displayName' => array('property'=>'displayName', 'type'=>'text', 'label'=>'Display Name', 'description'=>'A name to identify the library within the system', 'size'=>'40'),
			'showDisplayNameInHeader' => array('property'=>'showDisplayNameInHeader', 'type'=>'checkbox', 'label'=>'Show Display Name in Header', 'description'=>'Whether or not the display name should be shown in the header next to the logo', 'hideInLists' => true, 'default'=>false),
			'abbreviatedDisplayName' => array('property'=>'abbreviatedDisplayName', 'type'=>'text', 'label'=>'Abbreviated Display Name', 'description'=>'A short name to identify the library when space is low', 'size'=>'40'),
			'systemMessage' => array('property'=>'systemMessage', 'type'=>'html', 'label'=>'System Message', 'description'=>'A message to be displayed at the top of the screen', 'size'=>'80', 'maxLength' =>'512', 'allowableTags' => '<a><b><em><div><script><span><p><strong><sub><sup>', 'hideInLists' => true),

			// Basic Display //
			'displaySection' =>array('property'=>'displaySection', 'type' => 'section', 'label' =>'Basic Display', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/18XXYAn3m9IGbjKwDGluFhPoHDXdIFUhdgmoIEdgRVcM', 'properties' => array(
				'themeName' => array('property'=>'themeName', 'type'=>'text', 'label'=>'Theme Name', 'description'=>'The name of the theme which should be used for the library', 'hideInLists' => true, 'default' => 'default'),
				'homeLink' => array('property'=>'homeLink', 'type'=>'text', 'label'=>'Home Link', 'description'=>'The location to send the user when they click on the home button or logo.  Use default or blank to go back to the Pika home location.', 'size'=>'40', 'hideInLists' => true,),
				'additionalCss' => array('property'=>'additionalCss', 'type'=>'textarea', 'label'=>'Additional CSS', 'description'=>'Extra CSS to apply to the site.  Will apply to all pages.', 'hideInLists' => true),
				'headerText' => array('property'=>'headerText', 'type'=>'html', 'label'=>'Header Text', 'description'=>'Optional Text to display in the header, between the logo and the log in/out buttons.  Will apply to all pages.', 'allowableTags' => '<a><b><em><div><span><p><strong><sub><sup><h1><h2><h3><h4><h5><h6><img>', 'hideInLists' => true),
				'sideBarOnRight' => array('property'=>'sideBarOnRight', 'type'=>'checkbox', 'label'=>'Display Sidebar on the Right Side', 'description'=>'Sidebars will be displayed on the right side of the page rather than the default left side.', 'hideInLists' => true,),
				'useHomeLinkInBreadcrumbs' => array('property'=>'useHomeLinkInBreadcrumbs', 'type'=>'checkbox', 'label'=>'Use Home Link in Breadcrumbs', 'description'=>'Whether or not the home link should be used in the breadcumbs.', 'hideInLists' => true,),
				'useHomeLinkForLogo' => array('property'=>'useHomeLinkForLogo', 'type'=>'checkbox', 'label'=>'Use Home Link for Logo', 'description'=>'Whether or not the home link should be used as the link for the main logo.', 'hideInLists' => true,),
				'homeLinkText' => array('property'=>'homeLinkText', 'type'=>'text', 'label'=>'Home Link Text', 'description'=>'The text to show for the Home breadcrumb link', 'size'=>'40', 'hideInLists' => true, 'default' => 'Home'),
				'showLibraryHoursAndLocationsLink' => array('property'=>'showLibraryHoursAndLocationsLink', 'type'=>'checkbox', 'label'=>'Show Library Hours and Locations Link', 'description'=>'Whether or not the library hours and locations link is shown on the home page.', 'hideInLists' => true, 'default' => true),
				'eContentSupportAddress'  => array('property'=>'eContentSupportAddress', 'type'=>'multiemail', 'label'=>'E-Content Support Address', 'description'=>'An e-mail address to receive support requests for patrons with eContent problems.', 'size'=>'80', 'hideInLists' => true, 'default'=>'askmarmot@marmot.org'),

				'enableGenealogy' => array('property'=>'enableGenealogy', 'type'=>'checkbox', 'label'=>'Enable Genealogy Functionality', 'description'=>'Whether or not patrons can search genealogy.', 'hideInLists' => true, 'default' => 1),
				'enableCourseReserves' => array('property'=>'enableCourseReserves', 'type'=>'checkbox', 'label'=>'Enable Repeat Search in Course Reserves', 'description'=>'Whether or not patrons can repeat searches within course reserves.', 'hideInLists' => true,),
			)),

			// Contact Links //
			array('property'=>'contact', 'type' => 'section', 'label' =>'Contact Links', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1KTYDrTQAK38dxsMG0R4q5w0sNJ5mbyz5Y6YofqWWBxI','properties' => array(
				'facebookLink' => array('property'=>'facebookLink', 'type'=>'text', 'label'=>'Facebook Link Url', 'description'=>'The url to Facebook (leave blank if the library does not have a Facebook account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'twitterLink' => array('property'=>'twitterLink', 'type'=>'text', 'label'=>'Twitter Link Url', 'description'=>'The url to Twitter (leave blank if the library does not have a Twitter account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'youtubeLink' => array('property'=>'youtubeLink', 'type'=>'text', 'label'=>'Youtube Link Url', 'description'=>'The url to Youtube (leave blank if the library does not have a Youtube account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'instagramLink' => array('property'=>'instagramLink', 'type'=>'text', 'label'=>'Instagram Link Url', 'description'=>'The url to Instagram (leave blank if the library does not have a Instagram account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'goodreadsLink' => array('property'=>'goodreadsLink', 'type'=>'text', 'label'=>'GoodReads Link Url', 'description'=>'The url to GoodReads (leave blank if the library does not have a GoodReads account', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
				'generalContactLink' => array('property'=>'generalContactLink', 'type'=>'text', 'label'=>'General Contact Link Url', 'description'=>'The url to a General Contact Page, i.e webform or mailto link', 'size'=>'40', 'maxLength' => 255, 'hideInLists' => true/*, 'default' => 'Home'*/),
			)),
			// defaults should be blank so that icons don't appear on page when the link is not set. plb 1-21-2015

			// ILS/Account Integration //
			'ilsSection' => array('property'=>'ilsSection', 'type' => 'section', 'label' =>'ILS/Account Integration', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1SmCcWYIV8bnUEaGu4HYvyiF8iqOKt06ooBbJukkJdO8', 'properties' => array(
				'ilsCode' => array('property'=>'ilsCode', 'type'=>'text', 'label'=>'ILS Code', 'description'=>'The location code that all items for this location start with.', 'size'=>'4', 'hideInLists' => false,),
				'scope'  => array('property'=>'scope', 'type'=>'text', 'label'=>'Scope', 'description'=>'The scope for the system in Millennium to refine holdings for the user.', 'size'=>'4', 'hideInLists' => true,),
				'useScope' => array('property'=>'useScope', 'type'=>'checkbox', 'label'=>'Use Scope', 'description'=>'Whether or not the scope should be used when displaying holdings.', 'hideInLists' => true,),
				'showExpirationWarnings' => array('property'=>'showExpirationWarnings', 'type'=>'checkbox', 'label'=>'Show Expiration Warnings', 'description'=>'Whether or not the user should be shown expiration warnings if their card is nearly expired.', 'hideInLists' => true, 'default' => 1),
				'enableMaterialsBooking' => array('property'=>'enableMaterialsBooking', 'type'=>'checkbox', 'label'=>'Enable Materials Booking', 'description'=>'Check to enable integration of Sierra\'s Materials Booking module.', 'hideInLists' => true, 'default' => 0),
				'allowLinkedAccounts' => array('property'=>'allowLinkedAccounts', 'type'=>'checkbox', 'label'=>'Allow Linked Accounts', 'description' => 'Whether or not users can link multiple library cards under a single Pika account.', 'hideInLists' => true, 'default' => 1),
				'showLibraryHoursNoticeOnAccountPages' => array('property'=>'showLibraryHoursNoticeOnAccountPages', 'type'=>'checkbox', 'label'=>'Show Library Hours Notice on Account Pages', 'description'=>'Whether or not the Library Hours notice should be shown at the top of My Account\'s Checked Out, Holds and Bookings pages.', 'hideInLists' => true, 'default'=>true),
				'pTypesSection' => array('property' => 'pTypesSectionSection', 'type' => 'section', 'label' => 'P-Types', 'hideInLists' => true,
						'helpLink'=>'https://docs.google.com/document/d/1SmCcWYIV8bnUEaGu4HYvyiF8iqOKt06ooBbJukkJdO8','properties' => array(
					'pTypes'  => array('property'=>'pTypes', 'type'=>'text', 'label'=>'P-Types', 'description'=>'A list of pTypes that are valid for the library.  Separate multiple pTypes with commas.'),
					'defaultPType'  => array('property'=>'defaultPType', 'type'=>'text', 'label'=>'Default P-Type', 'description'=>'The P-Type to use when accessing a subdomain if the patron is not logged in.'),
				)),
				'barcodeSection' => array('property' => 'barcodeSection', 'type' => 'section', 'label' => 'Barcode', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/13vk5Cx_bWRwc_XtwwzKei92ZeTGS8LcMnZadE2CxaBU', 'properties' => array(
					'minBarcodeLength' => array('property'=>'minBarcodeLength', 'type'=>'integer', 'label'=>'Min Barcode Length', 'description'=>'A minimum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default'=>0),
					'maxBarcodeLength' => array('property'=>'maxBarcodeLength', 'type'=>'integer', 'label'=>'Max Barcode Length', 'description'=>'The maximum length the patron barcode is expected to be. Leave as 0 to extra processing of barcodes.', 'hideInLists' => true, 'default'=>0),
					'barcodePrefix' => array('property'=>'barcodePrefix', 'type'=>'text', 'label'=>'Barcode Prefix', 'description'=>'A barcode prefix to apply to the barcode if it does not start with the barcode prefix or if it is not within the expected min/max range.  Multiple prefixes can be specified by separating them with commas. Leave blank to avoid additional processing of barcodes.', 'hideInLists' => true,'default'=>''),
				)),
				'userProfileSection' => array('property' => 'userProfileSection', 'type' => 'section', 'label' => 'User Profile', 'hideInLists' => true,
						'helpLink'=>'https://docs.google.com/document/d/1S8s8KYPaw6x7IIcxUbzkXgCnnHXR6t8W_2CwXiQyjrE', 'properties' => array(
					'patronNameDisplayStyle'   => array('property'=>'patronNameDisplayStyle', 'type'=>'enum', 'values'=>array('firstinitial_lastname'=>'First Initial. Last Name', 'lastinitial_firstname'=>'Last Initial. First Name'), 'label'=>'Patron Display Name Style', 'description'=>'How to generate the patron display name'),
					'allowProfileUpdates' => array('property'=>'allowProfileUpdates', 'type'=>'checkbox', 'label'=>'Allow Profile Updates', 'description'=>'Whether or not the user can update their own profile.', 'hideInLists' => true, 'default' => 1),
					'allowPatronAddressUpdates' => array('property' => 'allowPatronAddressUpdates', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update Their Address', 'description'=>'Whether or not patrons should be able to update their own address in their profile.', 'hideInLists' => true, 'default' => 1),
					'showAlternateLibraryOptionsInProfile' => array('property' => 'showAlternateLibraryOptionsInProfile', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update their Alternate Libraries', 'description'=>'Allow Patrons to See and Change Alternate Library Settings in the Catalog Options Tab in their profile.', 'hideInLists' => true, 'default' => 1),
					'showWorkPhoneInProfile' => array('property' => 'showWorkPhoneInProfile', 'type'=>'checkbox', 'label'=>'Show Work Phone in Profile', 'description'=>'Whether or not patrons should be able to change a secondary/work phone number in their profile.', 'hideInLists' => true, 'default' => 0),
					'treatPrintNoticesAsPhoneNotices' => array('property' => 'treatPrintNoticesAsPhoneNotices', 'type' => 'checkbox', 'label' => 'Treat Print Notices As Phone Notices', 'description' => 'When showing detailed information about hold notices, treat print notices as if they are phone calls', 'hideInLists' => true, 'default' => 0),
					'showNoticeTypeInProfile' => array('property' => 'showNoticeTypeInProfile', 'type'=>'checkbox', 'label'=>'Show Notice Type in Profile', 'description'=>'Whether or not patrons should be able to change how they receive notices in their profile.', 'hideInLists' => true, 'default' => 0),
					'showPickupLocationInProfile' => array('property' => 'showPickupLocationInProfile', 'type'=>'checkbox', 'label'=>'Allow Patrons to Update Their Pickup Location', 'description'=>'Whether or not patrons should be able to update their preferred pickup location in their profile.', 'hideInLists' => true, 'default' => 0),
					'addSMSIndicatorToPhone' => array('property' => 'addSMSIndicatorToPhone', 'type'=>'checkbox', 'label'=>'Add SMS Indicator to Primary Phone', 'description'=>'Whether or not add TEXT ONLY to the user\'s primary phone number when they opt in to SMS notices.', 'hideInLists' => true, 'default' => 0),
					'maxFinesToAllowAccountUpdates' => array('property' => 'maxFinesToAllowAccountUpdates', 'type'=>'currency', 'displayFormat'=>'%0.2f', 'label'=>'Maximum Fine Amount to Allow Account Updates', 'description'=>'The maximum amount that a patron can owe and still update their account. Any value <= 0 will disable this functionality.', 'hideInLists' => true, 'default' => 10)
				)),
				'holdsSection' => array('property' => 'holdsSection', 'type' => 'section', 'label' => 'Holds', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1tFkmGhqBrTdluS2tOzQ_xtzl3HxfjGhmFgk4r3BTVY8', 'properties' => array(
					'showHoldButton'  => array('property'=>'showHoldButton', 'type'=>'checkbox', 'label'=>'Show Hold Button', 'description'=>'Whether or not the hold button is displayed so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldButtonInSearchResults'  => array('property'=>'showHoldButtonInSearchResults', 'type'=>'checkbox', 'label'=>'Show Hold Button within the search results', 'description'=>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldButtonForUnavailableOnly'  => array('property'=>'showHoldButtonForUnavailableOnly', 'type'=>'checkbox', 'label'=>'Show Hold Button for items that are checked out only', 'description'=>'Whether or not the hold button is displayed within the search results so patrons can place holds on items', 'hideInLists' => true, 'default' => 1),
					'showHoldCancelDate'   => array('property'=>'showHoldCancelDate', 'type'=>'checkbox', 'label'=>'Show Cancellation Date', 'description'=>'Whether or not the patron should be able to set a cancellation date (not needed after date) when placing holds.', 'hideInLists' => true, 'default' => 1),
					'allowFreezeHolds'  => array('property'=>'allowFreezeHolds', 'type'=>'checkbox', 'label'=>'Allow Freezing Holds', 'description'=>'Whether or not the user can freeze their holds.', 'hideInLists' => true, 'default' => 1),
					'defaultNotNeededAfterDays'=> array('property'=>'defaultNotNeededAfterDays', 'type'=>'integer', 'label'=>'Default Not Needed After Days', 'description'=>'Number of days to use for not needed after date by default. Use -1 for no default.', 'hideInLists' => true,),
					'showDetailedHoldNoticeInformation' => array('property' => 'showDetailedHoldNoticeInformation', 'type' => 'checkbox', 'label' => 'Show Detailed Hold Notice Information', 'description' => 'Whether or not the user should be presented with detailed hold notification information, i.e. you will receive an e-mail/phone call to xxx when the hold is available', 'hideInLists' => true, 'default' => 1),
					'inSystemPickupsOnly'  => array('property'=>'inSystemPickupsOnly', 'type'=>'checkbox', 'label'=>'In System Pickups Only', 'description'=>'Restrict pickup locations to only locations within this library system.', 'hideInLists' => true,),
					'validPickupSystems'  => array('property'=>'validPickupSystems', 'type'=>'text', 'label'=>'Valid Pickup Library Systems', 'description'=>'Additional Library Systems that can be used as pickup locations if the &quot;In System Pickups Only&quot; is on. List the libraries\' subdomains separated by pipes |', 'size'=>'20', 'hideInLists' => true,),
					'holdDisclaimer' => array('property'=>'holdDisclaimer', 'type'=>'textarea', 'label'=>'Hold Disclaimer', 'description'=>'A disclaimer to display to patrons when they are placing a hold on items letting them know that their information may be available to other libraries.  Leave blank to not show a disclaimer.', 'hideInLists' => true,),
				)),
				'loginSection' => array('property' => 'loginSection', 'type' => 'section', 'label' => 'Login', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/13vk5Cx_bWRwc_XtwwzKei92ZeTGS8LcMnZadE2CxaBU', 'properties' => array(
					'showLoginButton'  => array('property'=>'showLoginButton', 'type'=>'checkbox', 'label'=>'Show Login Button', 'description'=>'Whether or not the login button is displayed so patrons can login to the site', 'hideInLists' => true, 'default' => 1),
					'allowPinReset' => array('property'=>'allowPinReset', 'type'=>'checkbox', 'label'=>'Allow PIN Reset', 'description'=>'Whether or not the user can reset their PIN if they forget it.', 'hideInLists' => true, 'default' => 0),
					'loginFormUsernameLabel'  => array('property'=>'loginFormUsernameLabel', 'type'=>'text', 'label'=>'Login Form Username Label', 'description'=>'The label to show for the username when logging in', 'size'=>'50', 'hideInLists' => true, 'default'=>'Your Name'),
					'loginFormPasswordLabel'  => array('property'=>'loginFormPasswordLabel', 'type'=>'text', 'label'=>'Login Form Password Label', 'description'=>'The label to show for the password when logging in', 'size'=>'50', 'hideInLists' => true, 'default'=>'Library Card Number'),
				)),
				'selfRegistrationSection' => array('property' => 'selfRegistrationSection', 'type' => 'section', 'label' => 'Self Registration', 'hideInLists' => true,
						'helpLink' => 'https://docs.google.com/document/d/1MZAOlg3F2IEa0WKsJmDQiCFUrw-pVo_fnSNexAV4MbQ', 'properties' => array(
					'enableSelfRegistration' => array('property'=>'enableSelfRegistration', 'type'=>'checkbox', 'label'=>'Enable Self Registration', 'description'=>'Whether or not patrons can self register on the site', 'hideInLists' => true),
					'promptForBirthDateInSelfReg' => array('property' => 'promptForBirthDateInSelfReg', 'type' => 'checkbox', 'label' => 'Prompt For Birth Date', 'description'=>'Whether or not to prompt for birth date when self registering'),
					'selfRegistrationFormMessage' => array('property'=>'selfRegistrationFormMessage', 'type'=>'html', 'label'=>'Self Registration Form Message', 'description'=>'Message shown to users with the form to submit the self registration.  Leave blank to give users the default message.', 'hideInLists' => true),
					'selfRegistrationSuccessMessage' => array('property'=>'selfRegistrationSuccessMessage', 'type'=>'html', 'label'=>'Self Registration Success Message', 'description'=>'Message shown to users when the self registration has been completed successfully.  Leave blank to give users the default message.', 'hideInLists' => true),
					'selfRegistrationTemplate' => array('property'=>'selfRegistrationTemplate', 'type'=>'text', 'label'=>'Self Registration Template', 'description'=>'The ILS template to use during self registration (Sierra and Millennium).', 'hideInLists' => true, 'default' => 'default'),
				)),
			)),

			'ecommerceSection' => array('property'=>'ecommerceSection', 'type' => 'section', 'label' =>'Fines/e-commerce', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1PNoYpn01Yn0Bnqnk9R1CkAMM3RiqBLrk-U4azb2xrZg', 'properties' => array(
				'showEcommerceLink'        => array('property'=>'showEcommerceLink', 'type'=>'checkbox', 'label'=>'Show E-Commerce Link', 'description'=>'Whether or not users should be given a link to classic opac to pay fines', 'hideInLists' => true,),
				'payFinesLink'             => array('property'=>'payFinesLink', 'type'=>'text', 'label'=>'Pay Fines Link', 'description'=>'The link to pay fines.  Leave as default to link to classic (should have eCommerce link enabled)', 'hideInLists' => true, 'default' => 'default', 'size' => 80),
				'payFinesLinkText'         => array('property'=>'payFinesLinkText', 'type'=>'text', 'label'=>'Pay Fines Link Text', 'description'=>'The text when linking to pay fines.', 'hideInLists' => true, 'default' => 'Click to Pay Fines Online ', 'size' => 80),
				'minimumFineAmount'        => array('property'=>'minimumFineAmount', 'type'=>'currency', 'displayFormat'=>'%0.2f', 'label'=>'Minimum Fine Amount', 'description'=>'The minimum fine amount to display the e-commerce link', 'hideInLists' => true,),
				'showRefreshAccountButton' => array('property'=>'showRefreshAccountButton', 'type'=>'checkbox', 'label'=>'Show Refresh Account Button', 'description'=>'Whether or not a Show Refresh Account button is displayed in a pop-up when a user clicks the E-Commerce Link', 'hideInLists' => true, 'default' => true),
			)),

			// Searching //
			'searchingSection' => array('property'=>'searchingSection', 'type' => 'section', 'label' =>'Searching', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1QQ7bNfGx75ImTguxEOmf7eCtdrVN9vi8FpWtWY_O3OU', 'properties' => array(
				'horizontalSearchBar' => array('property' => 'horizontalSearchBar', 'type'=>'checkbox', 'label' => 'Use Horizontal Search Bar', 'description' => 'Instead of the default sidebar search box, a horizontal search bar is shown below the header that spans the screen.', 'hideInLists' => true, 'default' => false),
				'facetLabel' => array('property'=>'facetLabel', 'type'=>'text', 'label'=>'Facet Label', 'description'=>'The label for the library system in the Library System Facet.', 'size'=>'40', 'hideInLists' => true,),
				'restrictSearchByLibrary' => array('property'=>'restrictSearchByLibrary', 'type'=>'checkbox', 'label'=>'Restrict Search By Library', 'description'=>'Whether or not search results should only include titles from this library', 'hideInLists' => true),
				'econtentLocationsToInclude' => array('property'=>'econtentLocationsToInclude', 'type'=>'text', 'label'=>'eContent Locations To Include', 'description'=>'A list of eContent Locations to include within the scope.', 'size'=>'40', 'hideInLists' => true,),
				'includeOutOfSystemExternalLinks' => array('property' => 'includeOutOfSystemExternalLinks', 'type'=>'checkbox', 'label'=>'Include Out Of System External Links', 'description'=>'Whether or not to include external links from other library systems.  Should only be enabled for global scope.', 'hideInLists' => true, 'default'=>0),
				'publicListsToInclude' => array('property' => 'publicListsToInclude', 'type'=>'enum', 'values' => array(0 => 'No Lists', '1' => 'Lists from this library', '3'=>'Lists from library list publishers Only', '4'=>'Lists from all list publishers', '2' => 'All Lists'), 'label'=>'Public Lists To Include', 'description'=>'Which lists should be included in this scope'),
				'boostByLibrary' => array('property'=>'boostByLibrary', 'type'=>'checkbox', 'label'=>'Boost By Library', 'description'=>'Whether or not boosting of titles owned by this library should be applied', 'hideInLists' => true),
				'additionalLocalBoostFactor' => array('property'=>'additionalLocalBoostFactor', 'type'=>'integer', 'label'=>'Additional Local Boost Factor', 'description'=>'An additional numeric boost to apply to any locally owned and locally available titles', 'hideInLists' => true),
				'restrictOwningBranchesAndSystems' => array('property'=>'restrictOwningBranchesAndSystems', 'type'=>'checkbox', 'label'=>'Restrict Owning Branch and System Facets to this library', 'description'=>'Whether or not the Owning Branch and Owning System Facets will only display values relevant to this library.', 'hideInLists' => true),
				'showAvailableAtAnyLocation' => array('property'=>'showAvailableAtAnyLocation', 'type'=>'checkbox', 'label'=>'Show Available At Any Location?', 'description'=>'Whether or not to show any Marmot Location within the Available At facet', 'hideInLists' => true),
				'repeatSearchOption' => array('property'=>'repeatSearchOption', 'type'=>'enum', 'values'=>array('none'=>'None', 'librarySystem'=>'Library System','marmot'=>'Consortium'), 'label'=>'Repeat Search Options', 'description'=>'Where to allow repeating search. Valid options are: none, librarySystem, marmot, all'),
				'systemsToRepeatIn' => array('property'=>'systemsToRepeatIn', 'type'=>'text', 'label'=>'Systems To Repeat In', 'description'=>'A list of library codes that you would like to repeat search in separated by pipes |.', 'size'=>'20', 'hideInLists' => true,),
				'additionalLocationsToShowAvailabilityFor' => array('property'=>'additionalLocationsToShowAvailabilityFor', 'type'=>'text', 'label'=>'Additional Locations to Include in Available At Facet', 'description'=>'A list of library codes that you would like included in the available at facet separated by pipes |.', 'size'=>'20', 'hideInLists' => true,),
				'availabilityToggleLabelSuperScope' => array('property' => 'availabilityToggleLabelSuperScope', 'type' => 'text', 'label' => 'SuperScope Toggle Label', 'description' => 'The label to show when viewing super scope i.e. Consortium Name / Entire Collection / Everything.  Does not show if superscope is not enabled.', 'default' => 'Entire Collection'),
				'availabilityToggleLabelLocal' => array('property' => 'availabilityToggleLabelLocal', 'type' => 'text', 'label' => 'Local Collection Toggle Label', 'description' => 'The label to show when viewing the local collection i.e. Library Name / Local Collection.  Leave blank to hide the button.', 'default' => ''),
				'availabilityToggleLabelAvailable' => array('property' => 'availabilityToggleLabelAvailable', 'type' => 'text', 'label' => 'Available Toggle Label', 'description' => 'The label to show when viewing available items i.e. Available Now / Available Locally / Available Here.', 'default' => 'Available Now'),
				'repeatInOnlineCollection' => array('property'=>'repeatInOnlineCollection', 'type'=>'checkbox', 'label'=>'Repeat In Online Collection', 'description'=>'Turn on to allow repeat search in the Online Collection.', 'hideInLists' => true, 'default'=>false),
				'allowAutomaticSearchReplacements' => array('property'=>'allowAutomaticSearchReplacements', 'type'=>'checkbox', 'label'=>'Allow Automatic Search Corrections', 'description'=>'Turn on to allow Pika to replace search terms that have no results if the current search term looks like a misspelling.', 'hideInLists' => true, 'default'=>true),
				'showAdvancedSearchbox'  => array('property'=>'showAdvancedSearchbox', 'type'=>'checkbox', 'label'=>'Show Advanced Search Link', 'description'=>'Whether or not users should see the advanced search link below the search box.', 'hideInLists' => true, 'default' => 1),
				'applyNumberOfHoldingsBoost' => array('property'=>'applyNumberOfHoldingsBoost', 'type'=>'checkbox', 'label'=>'Apply Number Of Holdings Boost', 'description'=>'Whether or not the relevance will use boosting by number of holdings in the catalog.', 'hideInLists' => true, 'default' => 1),
				'showSearchTools'  => array('property'=>'showSearchTools', 'type'=>'checkbox', 'label'=>'Show Search Tools', 'description'=>'Turn on to activate search tools (save search, export to excel, rss feed, etc).', 'hideInLists' => true),
			)),

			// Catalog Enrichment //
			array('property'=>'enrichmentSection', 'type' => 'section', 'label' =>'Catalog Enrichment', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/1fJ2Sc62fTieJlPvaFz4XUoSr8blou_3MfxDGh1luI84', 'properties' => array(
				'showStandardReviews'  => array('property'=>'showStandardReviews', 'type'=>'checkbox', 'label'=>'Show Standard Reviews', 'description'=>'Whether or not reviews from Content Cafe/Syndetics are displayed on the full record page.', 'hideInLists' => true, 'default' => 1),
				'showGoodReadsReviews' => array('property'=>'showGoodReadsReviews', 'type'=>'checkbox', 'label'=>'Show GoodReads Reviews', 'description'=>'Whether or not reviews from GoodReads are displayed on the full record page.', 'hideInLists' => true, 'default'=>true),
				'preferSyndeticsSummary' => array('property'=>'preferSyndeticsSummary', 'type'=>'checkbox', 'label'=>'Prefer Syndetics/Content Cafe Description', 'description'=>'Whether or not the Description loaded from an enrichment service should be preferred over the Description in the Marc Record.', 'hideInLists' => true, 'default' => 1),
				'showSimilarAuthors' => array('property'=>'showSimilarAuthors', 'type'=>'checkbox', 'label'=>'Show Similar Authors', 'description'=>'Whether or not Similar Authors from Novelist is shown.', 'default' => 1, 'hideInLists' => true,),
				'showSimilarTitles' => array('property'=>'showSimilarTitles', 'type'=>'checkbox', 'label'=>'Show Similar Titles', 'description'=>'Whether or not Similar Titles from Novelist is shown.', 'default' => 1, 'hideInLists' => true,),
				'showGoDeeper' => array('property'=>'showGoDeeper', 'type'=>'checkbox', 'label'=>'Show Content Enrichment (TOC, Excerpts, etc)', 'description'=>'Whether or not additional content enrichment like Table of Contents, Exceprts, etc are shown to the user', 'default' => 1, 'hideInLists' => true,),
				'showRatings'  => array('property'=>'showRatings', 'type'=>'checkbox', 'label'=>'Enable User Ratings', 'description'=>'Whether or not ratings are shown', 'hideInLists' => true, 'default' => 1),
				'showComments'  => array('property'=>'showComments', 'type'=>'checkbox', 'label'=>'Enable User Reviews', 'description'=>'Whether or not user reviews are shown (also disables adding user reviews)', 'hideInLists' => true, 'default' => 1),
				// showComments & hideCommentsWithBadWords moved from full record display to this section. plb 6-30-2015
				'hideCommentsWithBadWords'  => array('property'=>'hideCommentsWithBadWords', 'type'=>'checkbox', 'label'=>'Hide Comments with Bad Words', 'description'=>'If checked, any User Lists or User Reviews with bad words are completely removed from the user interface for everyone except the original poster.', 'hideInLists' => true,),
				'showFavorites'  => array('property'=>'showFavorites', 'type'=>'checkbox', 'label'=>'Enable User Lists', 'description'=>'Whether or not users can maintain favorites lists', 'hideInLists' => true, 'default' => 1),
				//TODO database column rename?
				'showWikipediaContent' => array('property'=>'showWikipediaContent', 'type'=>'checkbox', 'label'=>'Show Wikipedia Content', 'description'=>'Whether or not Wikipedia content should be shown on author page', 'default'=>'1', 'hideInLists' => true,),
			)),

			// Full Record Display //
			array('property'=>'fullRecordSection', 'type' => 'section', 'label' =>'Full Record Display', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1ZZsoKW2NOfGMad36BkWeF5ROqH5Wyg5up3eIhki5Lec', 'properties' => array(
				'showTextThis'  => array('property'=>'showTextThis', 'type'=>'checkbox', 'label'=>'Show Text This', 'description'=>'Whether or not the Text This link is shown', 'hideInLists' => true, 'default' => 1),
				'showEmailThis'  => array('property'=>'showEmailThis', 'type'=>'checkbox', 'label'=>'Show Email This', 'description'=>'Whether or not the Email This link is shown', 'hideInLists' => true, 'default' => 1),
				'showShareOnExternalSites'  => array('property'=>'showShareOnExternalSites', 'type'=>'checkbox', 'label'=>'Show Sharing To External Sites', 'description'=>'Whether or not sharing on external sites (Twitter, Facebook, Pinterest, etc. is shown)', 'hideInLists' => true, 'default' => 1),
				'showQRCode'  => array('property'=>'showQRCode', 'type'=>'checkbox', 'label'=>'Show QR Code', 'description'=>'Whether or not the catalog should show a QR Code in full record view', 'hideInLists' => true, 'default' => 1),
				'showTagging'  => array('property'=>'showTagging', 'type'=>'checkbox', 'label'=>'Show Tagging', 'description'=>'Whether or not tags are shown (also disables adding tags)', 'hideInLists' => true, 'default' => 1),
				'notesTabName' => array('property'=>'notesTabName', 'type'=>'text', 'label'=>'Notes Tab Name', 'description'=>'Text to display for the the notes tab.', 'size'=>'40', 'maxLength' => '50', 'hideInLists' => true, 'default' => 'Notes'),
				'exportOptions' => array('property'=>'exportOptions', 'type'=>'text', 'label'=>'Export Options', 'description'=>'A list of export options that should be enabled separated by pipes.  Valid values are currently RefWorks and EndNote.', 'size'=>'40', 'hideInLists' => true,),
				'show856LinksAsTab'  => array('property'=>'show856LinksAsTab', 'type'=>'checkbox', 'label'=>'Show 856 Links as Tab', 'description'=>'Whether or not 856 links will be shown in their own tab or on the same tab as holdings.', 'hideInLists' => true, 'default' => 1),
				'showCheckInGrid' => array('property'=>'showCheckInGrid', 'type'=>'checkbox', 'label'=>'Show Check-in Grid', 'description'=>'Whether or not the check-in grid is shown for periodicals.', 'default' => 1, 'hideInLists' => true,),
				'showStaffView' => array('property'=>'showStaffView', 'type'=>'checkbox', 'label'=>'Show Staff View', 'description'=>'Whether or not the staff view is displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showStandardSubjects' => array('property'=>'showStandardSubjects', 'type'=>'checkbox', 'label'=>'Show Standard Subjects', 'description'=>'Whether or not standard (LC) subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showBisacSubjects' => array('property'=>'showBisacSubjects', 'type'=>'checkbox', 'label'=>'Show Bisac Subjects', 'description'=>'Whether or not Bisac subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),
				'showFastAddSubjects' => array('property'=>'showFastAddSubjects', 'type'=>'checkbox', 'label'=>'Show OCLC Fast Subjects', 'description'=>'Whether or not OCLC Fast Add subjects are displayed in full record view.', 'hideInLists' => true, 'default'=>true),

				'showInMainDetails' => array('property' => 'showInMainDetails', 'type' => 'multiSelect', 'label'=>'Which details to show in the main/top details section : ', 'description'=> 'Selected details will be shown in the top/main section of the full record view. Details not selected are moved to the More Details accordion.',
					'listStyle'=> 'checkboxSimple',
				  'values' => self::$showInMainDetailsOptions,
				),
				'moreDetailsOptions' => array(
						'property'=>'moreDetailsOptions',
						'type'=>'oneToMany',
						'label'=>'Full Record Options',
						'description'=>'Record Options for the display of full record',
						'keyThis' => 'libraryId',
						'keyOther' => 'libraryId',
						'subObjectType' => 'LibraryMoreDetails',
						'structure' => $libraryMoreDetailsStructure,
						'sortable' => true,
						'storeDb' => true,
						'allowEdit' => true,
						'canEdit' => true,
				),
			)),

			// Browse Category Section //
			array('property' => 'browseCategorySection', 'type' => 'section', 'label' => 'Browse Categories', 'hideInLists' => true, 'helpLink'=> 'https://docs.google.com/document/d/11biGMw6UDKx9UBiDCCj_GBmatx93UlJBLMESNf_RtDU', 'properties' => array(
				'defaultBrowseMode' => array('property' => 'defaultBrowseMode', 'type' => 'enum', 'label'=>'Default Viewing Mode for Browse Categories', 'description' => 'Sets how browse categories will be displayed when users haven\'t chosen themselves.', 'hideInLists' => true,
				                             'values'=> array('covers' => 'Show Covers Only', 'grid' => 'Show as Grid'), 'default' => 'covers'),
				'browseCategoryRatingsMode' => array('property' => 'browseCategoryRatingsMode', 'type' => 'enum', 'label' => 'Ratings Mode for Browse Categories ("covers" browse mode only)', 'description' => 'Sets how ratings will be displayed and how user ratings will be enabled when a user is viewing a browse category in the &#34;covers&#34; browse mode. These settings only apply when User Ratings have been enabled. (These settings will also apply to search results viewed in covers mode.)',
				                                     'values' => array('popup' => 'Show rating stars and enable user rating via pop-up form.',
				                                                       'stars' => 'Show rating stars and enable user ratings by clicking the stars.',
				                                                       'none' => 'Do not show rating stars.'
					                                     ), 'default' => 'popup'
				),

				// The specific categories displayed in the carousel
			'browseCategories' => array(
			'property'=>'browseCategories',
			'type'=>'oneToMany',
			'label'=>'Browse Categories',
			'description'=>'Browse Categories To Show on the Home Screen',
			'keyThis' => 'libraryId',
			'keyOther' => 'libraryId',
			'subObjectType' => 'LibraryBrowseCategory',
			'structure' => $libraryBrowseCategoryStructure,
			'sortable' => true,
			'storeDb' => true,
			'allowEdit' => false,
			'canEdit' => false,
		),
			)),

			array('property'=>'holdingsSummarySection', 'type' => 'section', 'label' =>'Holdings Summary', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/1PjlFlhPVNRVcg_uzzHLQLkRicyPEB1KeVNok4Wkye1I', 'properties' => array(
				'showItsHere' => array('property'=>'showItsHere', 'type'=>'checkbox', 'label'=>'Show It\'s Here', 'description'=>'Whether or not the holdings summray should show It\'s here based on IP and the currently logged in patron\'s location.', 'hideInLists' => true, 'default' => 1),
			)),
			'materialsRequestSection'=> array('property'=>'materialsRequestSection', 'type' => 'section', 'label' =>'Materials Request', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/18Sah0T8sWUextphL5ykg8QEM_YozniSXqOo1nfi6gnc',
					'properties' => array(
				'enableMaterialsRequest' => array('property'=>'enableMaterialsRequest', 'type'=>'checkbox', 'label'=>'Enable Pika Materials Request System', 'description'=>'Enable Materials Request functionality so patrons can request items not in the catalog.', 'hideInLists' => true,),
				'externalMaterialsRequestUrl' => array('property'=>'externalMaterialsRequestUrl', 'type'=>'text', 'label'=>'External Materials Request URL', 'description'=>'A link to an external Materials Request System to be used instead of the built in Pika system', 'hideInList' => true),
				'maxRequestsPerYear' => array('property'=>'maxRequestsPerYear', 'type'=>'integer', 'label'=>'Max Requests Per Year', 'description'=>'The maximum number of requests that a user can make within a year', 'hideInLists' => true, 'default' => 60),
				'maxOpenRequests' => array('property'=>'maxOpenRequests', 'type'=>'integer', 'label'=>'Max Open Requests', 'description'=>'The maximum number of requests that a user can have open at one time', 'hideInLists' => true, 'default' => 5),
			)),
			'goldrushSection'=>array('property'=>'goldrushSection', 'type' => 'section', 'label' =>'Gold Rush', 'hideInLists' => true,
					'helpLink' => 'https://docs.google.com/document/d/1OfVcwdalgi8YNEqTAXXv7Oye15eQwxGGKX5IIaeuT7U', 'properties' => array(
				'goldRushCode'  => array('property'=>'goldRushCode', 'type'=>'text', 'label'=>'Gold Rush Inst Code', 'description'=>'The INST Code to use with Gold Rush.  Leave blank to not link to Gold Rush.', 'hideInLists' => true,),
			)),

			'prospectorSection'=>array('property'=>'prospectorSection', 'type' => 'section', 'label' =>'Prospector', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/18SVEhciSjO99hcFLLdFR6OpC4_OtjOafTkuWPGXOhu4', 'properties' => array(
				'repeatInProspector'  => array('property'=>'repeatInProspector', 'type'=>'checkbox', 'label'=>'Repeat In Prospector', 'description'=>'Turn on to allow repeat search in Prospector functionality.', 'hideInLists' => true, 'default' => 1),
				'prospectorCode' => array('property'=>'prospectorCode', 'type'=>'text', 'label'=>'Prospector Code', 'description'=>'The code used to identify this location within Prospector. Leave blank if items for this location are not in Prospector.', 'hideInLists' => true,),
				'enablePospectorIntegration'=> array('property'=>'enablePospectorIntegration', 'type'=>'checkbox', 'label'=>'Enable Prospector Integration', 'description'=>'Whether or not Prospector Integrations should be displayed for this library.', 'hideInLists' => true, 'default' => 1),
				'showProspectorResultsAtEndOfSearch' => array('property'=>'showProspectorResultsAtEndOfSearch', 'type'=>'checkbox', 'label'=>'Show Prospector Results At End Of Search', 'description'=>'Whether or not Prospector Search Results should be shown at the end of search results.', 'hideInLists' => true, 'default' => 1),
			)),
			'worldCatSection' => array('property'=>'worldCatSection', 'type' => 'section', 'label' =>'WorldCat', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1z6krQ9bf8qSEcYnWWbHA_EZsJyp9gXf9QYiZYf964w8', 'properties' => array(
				'repeatInWorldCat'  => array('property'=>'repeatInWorldCat', 'type'=>'checkbox', 'label'=>'Repeat In WorldCat', 'description'=>'Turn on to allow repeat search in WorldCat functionality.', 'hideInLists' => true,),
				'worldCatUrl' => array('property'=>'worldCatUrl', 'type'=>'text', 'label'=>'WorldCat URL', 'description'=>'A custom World Cat URL to use while searching.', 'hideInLists' => true, 'size'=>'80'),
				'worldCatQt' => array('property'=>'worldCatQt', 'type'=>'text', 'label'=>'WorldCat QT', 'description'=>'A custom World Cat QT term to use while searching.', 'hideInLists' => true, 'size'=>'40'),
			)),

			'overdriveSection' => array('property'=>'overdriveSection', 'type' => 'section', 'label' =>'OverDrive', 'hideInLists' => true,
					'helpLink'=>'https://docs.google.com/document/d/1HG7duKI4-gbOlgDvMlQrib52LV0BBUhzGD7Q69QLziM', 'properties' => array(
				'enableOverdriveCollection' => array('property'=>'enableOverdriveCollection', 'type'=>'checkbox', 'label'=>'Enable Overdrive Collection', 'description'=>'Whether or not titles from the Overdrive collection should be included in searches', 'hideInLists' => true),
				'includeOverDriveAdult' => array('property'=>'includeOverDriveAdult', 'type'=>'checkbox', 'label'=>'Include Adult Titles', 'description'=>'Whether or not adult titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),
				'includeOverDriveTeen' => array('property'=>'includeOverDriveTeen', 'type'=>'checkbox', 'label'=>'Include Teen Titles', 'description'=>'Whether or not teen titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),
				'includeOverDriveKids' => array('property'=>'includeOverDriveKids', 'type'=>'checkbox', 'label'=>'Include Kids Titles', 'description'=>'Whether or not kids titles from the Overdrive collection should be included in searches', 'hideInLists' => true, 'default' => true),

				'repeatInOverdrive' => array('property'=>'repeatInOverdrive', 'type'=>'checkbox', 'label'=>'Repeat In Overdrive', 'description'=>'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => 0),
				'overdriveAuthenticationILSName' => array('property'=>'overdriveAuthenticationILSName', 'type'=>'text', 'label'=>'The ILS Name Overdrive uses for user Authentication', 'description'=>'The name of the ILS that OverDrive uses to authenticate users logging into the Overdrive website.', 'size'=>'20', 'hideInLists' => true),
				'overdriveRequirePin' => array('property'=>'overdriveRequirePin', 'type'=>'checkbox', 'label'=>'Is a Pin Required to log into Overdrive website?', 'description'=>'Turn on to allow repeat search in Overdrive functionality.', 'hideInLists' => true, 'default' => 0),
				'overdriveAdvantageName' => array('property'=>'overdriveAdvantageName', 'type'=>'text', 'label'=>'Overdrive Advantage Name', 'description'=>'The name of the OverDrive Advantage account if any.', 'size'=>'80', 'hideInLists' => true,),
				'overdriveAdvantageProductsKey' => array('property'=>'overdriveAdvantageProductsKey', 'type'=>'text', 'label'=>'Overdrive Advantage Products Key', 'description'=>'The products key for use when building urls to the API from the advantageAccounts call.', 'size'=>'80', 'hideInLists' => false,),
			)),
			'archiveSection'=>array('property'=>'archiveSection', 'type' => 'section', 'label' =>'Local Content Archive', 'hideInLists' => true, 'properties' => array(
				'enableArchive' => array('property'=>'enableArchive', 'type'=>'checkbox', 'label'=>'Allow Searching the Archive', 'description'=>'Whether or not information from the archive is shown in Pika.', 'hideInLists' => true, 'default' => 0),
			)),

			'edsSection'=>array('property'=>'edsSection', 'type' => 'section', 'label' =>'EBSCO EDS', 'hideInLists' => true, 'properties' => array(
					'edsApiProfile' => array('property'=>'edsApiProfile', 'type'=>'text', 'label'=>'EDS API Profile', 'description'=>'The profile to use when connecting to the EBSCO API', 'hideInLists' => true),
					'edsApiUsername' => array('property'=>'edsApiUsername', 'type'=>'text', 'label'=>'EDS API Username', 'description'=>'The username to use when connecting to the EBSCO API', 'hideInLists' => true),
					'edsApiPassword' => array('property'=>'edsApiPassword', 'type'=>'text', 'label'=>'EDS API Password', 'description'=>'The password to use when connecting to the EBSCO API', 'hideInLists' => true),
			)),

			array('property'=>'dplaSection', 'type' => 'section', 'label' =>'DPLA', 'hideInLists' => true, 'helpLink'=> 'https://docs.google.com/document/d/1I6RuNhKNwDJOMpM63a4V5Lm0URgWp23465HegEIkP_w', 'properties' => array(
				'includeDplaResults' => array('property'=>'includeDplaResults', 'type'=>'checkbox', 'label'=>'Include DPLA content in search results', 'description'=>'Whether or not DPLA data should be included for this library.', 'hideInLists' => true, 'default' => 0),
			)),

			'holidays' => array(
				'property' => 'holidays',
				'type'=> 'oneToMany',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'Holiday',
				'structure' => $holidaysStructure,
				'label' => 'Holidays',
				'description' => 'Holidays',
				'sortable' => false,
				'storeDb' => true
			),

			'facets' => array(
				'property'=>'facets',
				'type'=>'oneToMany',
				'label'=>'Facets',
				'description'=>'A list of facets to display in search results',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryFacetSetting',
				'structure' => $facetSettingStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => true,
				'canEdit' => true,
			),

			'libraryLinks' => array(
				'property'=>'libraryLinks',
				'type'=>'oneToMany',
				'label'=>'Sidebar Links',
				'description'=>'Links To Show in the sidebar',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryLink',
				'structure' => $libraryLinksStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => true,
				'canEdit' => true,
			),

			'libraryTopLinks' => array(
				'property'=>'libraryTopLinks',
				'type'=>'oneToMany',
				'label'=>'Header Links',
				'description'=>'Links To Show in the header',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryTopLinks',
				'structure' => $libraryTopLinksStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),

			'recordsOwned' => array(
				'property'=>'recordsOwned',
				'type'=>'oneToMany',
				'label'=>'Records Owned',
				'description'=>'Information about what records are owned by the library',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryRecordOwned',
				'structure' => $libraryRecordOwnedStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),

			'recordsToInclude' => array(
				'property'=>'recordsToInclude',
				'type'=>'oneToMany',
				'label'=>'Records To Include',
				'description'=>'Information about what records to include in this scope',
				'keyThis' => 'libraryId',
				'keyOther' => 'libraryId',
				'subObjectType' => 'LibraryRecordToInclude',
				'structure' => $libraryRecordToIncludeStructure,
				'sortable' => true,
				'storeDb' => true,
				'allowEdit' => false,
				'canEdit' => false,
			),
		);

		if ($user->hasRole('libraryManager')){
			$structure['subdomain']['type'] = 'label';
			$structure['displayName']['type'] = 'label';
			unset($structure['displaySection']['properties']['themeName']);
			unset($structure['displaySection']['properties']['sideBarOnRight']);
			unset($structure['displaySection']['properties']['enableGenealogy']);
			unset($structure['displaySection']['properties']['enableCourseReserves']);
			unset($structure['ilsSection']);
			unset($structure['ecommerceSection']);
			unset($structure['searchingSection']);
			unset($structure['materialsRequestSection']);
			unset($structure['goldrushSection']);
			unset($structure['prospectorSection']);
			unset($structure['worldCatSection']);
			unset($structure['overdriveSection']);
			unset($structure['archiveSection']);
			unset($structure['edsSection']);
			unset($structure['recordsOwned']);
			unset($structure['recordsToInclude']);
		}
		return $structure;
	}

	static $searchLibrary  = array();
	static function getSearchLibrary($searchSource = null){
		if ($searchSource == null){
			global $searchSource;
		}
		if (!array_key_exists($searchSource, Library::$searchLibrary)){
			$scopingSetting = $searchSource;
			if ($scopingSetting == null){
				return null;
			} else if ($scopingSetting == 'local' || $scopingSetting == 'econtent' || $scopingSetting == 'library' || $scopingSetting == 'location'){
				Library::$searchLibrary[$searchSource] = Library::getActiveLibrary();
			}else if ($scopingSetting == 'marmot' || $scopingSetting == 'unscoped'){
				//Get the default library
				$library = new Library();
				$library->isDefault = true;
				$library->find();
				if ($library->N > 0){
					$library->fetch();
					Library::$searchLibrary[$searchSource] = clone($library);
				}else{
					Library::$searchLibrary[$searchSource] = null;
				}
			}else{
				$location = Location::getSearchLocation();
				if (is_null($location)){
					//Check to see if we have a library for the subdomain
					$library = new Library();
					$library->subdomain = $scopingSetting;
					$library->find();
					if ($library->N > 0){
						$library->fetch();
						Library::$searchLibrary[$searchSource] = clone($library);
						return clone($library);
					}else{
						Library::$searchLibrary[$searchSource] = null;
					}
				}else{
					Library::$searchLibrary[$searchSource] = self::getLibraryForLocation($location->locationId);
				}
			}
		}
		return Library::$searchLibrary[$searchSource];
	}

	static function getActiveLibrary(){
		global $library;
		//First check to see if we have a library loaded based on subdomain (loaded in index)
		if (isset($library)) {
			return $library;
		}
		//If there is only one library, that library is active by default.
		$activeLibrary = new Library();
		$activeLibrary->find();
		if ($activeLibrary->N == 1){
			$activeLibrary->fetch();
			return $activeLibrary;
		}
		//Next check to see if we are in a library.
		/** @var Location $locationSingleton */
		global $locationSingleton;
		$physicalLocation = $locationSingleton->getActiveLocation();
		if (!is_null($physicalLocation)){
			//Load the library based on the home branch for the user
			return self::getLibraryForLocation($physicalLocation->libraryId);
		}
		return null;
	}

	static function getPatronHomeLibrary($tmpUser = null){
		if ($tmpUser == null){
			global $user;
			$tmpUser = $user;
		}
		//Finally check to see if the user has logged in and if so, use that library
		if (isset($tmpUser) && $tmpUser != false){
			//Load the library based on the home branch for the user
			return self::getLibraryForLocation($tmpUser->homeLocationId);
		}else{
			return null;
		}

	}

	static function getLibraryForLocation($locationId){
		if (isset($locationId)){
			$libLookup = new Library();
			require_once(ROOT_DIR . '/Drivers/marmot_inc/Location.php');
			$libLookup->whereAdd('libraryId = (SELECT libraryId FROM location WHERE locationId = ' . $libLookup->escape($locationId) . ')');
			$libLookup->find();
			if ($libLookup->N > 0){
				$libLookup->fetch();
				return clone $libLookup;
			}
		}
		return null;
	}

	private $data = array();
	public function __get($name){
		if ($name == "holidays") {
			if (!isset($this->holidays) && $this->libraryId){
				$this->holidays = array();
				$holiday = new Holiday();
				$holiday->libraryId = $this->libraryId;
				$holiday->orderBy('date');
				$holiday->find();
				while($holiday->fetch()){
					$this->holidays[$holiday->id] = clone($holiday);
				}
			}
			return $this->holidays;
		}elseif ($name == "moreDetailsOptions") {
			if (!isset($this->moreDetailsOptions) && $this->libraryId){
				$this->moreDetailsOptions = array();
				$moreDetailsOptions = new LibraryMoreDetails();
				$moreDetailsOptions->libraryId = $this->libraryId;
				$moreDetailsOptions->orderBy('weight');
				$moreDetailsOptions->find();
				while($moreDetailsOptions->fetch()){
					$this->moreDetailsOptions[$moreDetailsOptions->id] = clone($moreDetailsOptions);
				}
			}
			return $this->moreDetailsOptions;
		}elseif ($name == "facets") {
			if (!isset($this->facets) && $this->libraryId){
				$this->facets = array();
				$facet = new LibraryFacetSetting();
				$facet->libraryId = $this->libraryId;
				$facet->orderBy('weight');
				$facet->find();
				while($facet->fetch()){
					$this->facets[$facet->id] = clone($facet);
				}
			}
			return $this->facets;
		}elseif ($name == 'libraryLinks'){
			if (!isset($this->libraryLinks) && $this->libraryId){
				$this->libraryLinks = array();
				$libraryLink = new LibraryLink();
				$libraryLink->libraryId = $this->libraryId;
				$libraryLink->orderBy('weight');
				$libraryLink->find();
				while ($libraryLink->fetch()){
					$this->libraryLinks[$libraryLink->id] = clone($libraryLink);
				}
			}
			return $this->libraryLinks;
		}elseif ($name == 'libraryTopLinks'){
			if (!isset($this->libraryTopLinks) && $this->libraryId){
				$this->libraryTopLinks = array();
				$libraryLink = new LibraryTopLinks();
				$libraryLink->libraryId = $this->libraryId;
				$libraryLink->orderBy('weight');
				$libraryLink->find();
				while($libraryLink->fetch()){
					$this->libraryTopLinks[$libraryLink->id] = clone($libraryLink);
				}
			}
			return $this->libraryTopLinks;
		}elseif ($name == 'recordsOwned'){
			if (!isset($this->recordsOwned) && $this->libraryId){
				$this->recordsOwned = array();
				$object = new LibraryRecordOwned();
				$object->libraryId = $this->libraryId;
				$object->find();
				while($object->fetch()){
					$this->recordsOwned[$object->id] = clone($object);
				}
			}
			return $this->recordsOwned;
		}elseif ($name == 'recordsToInclude'){
			if (!isset($this->recordsToInclude) && $this->libraryId){
				$this->recordsToInclude = array();
				$object = new LibraryRecordToInclude();
				$object->libraryId = $this->libraryId;
				$object->orderBy('weight');
				$object->find();
				while($object->fetch()){
					$this->recordsToInclude[$object->id] = clone($object);
				}
			}
			return $this->recordsToInclude;
		}elseif  ($name == 'browseCategories') {
			if (!isset($this->browseCategories) && $this->libraryId) {
				$this->browseCategories = array();
				$browseCategory = new LibraryBrowseCategory();
				$browseCategory->libraryId = $this->libraryId;
				$browseCategory->orderBy('weight');
				$browseCategory->find();
				while ($browseCategory->fetch()) {
					$this->browseCategories[$browseCategory->id] = clone($browseCategory);
				}
			}
			return $this->browseCategories;
		}elseif ($name == 'patronNameDisplayStyle'){
			return $this->patronNameDisplayStyle;

		}else{
			return $this->data[$name];
		}
	}

	public function __set($name, $value){
		if ($name == "holidays") {
			$this->holidays = $value;
		}elseif ($name == "moreDetailsOptions") {
			$this->moreDetailsOptions = $value;
		}elseif ($name == "facets") {
			$this->facets = $value;
		}elseif ($name == 'libraryLinks'){
			$this->libraryLinks = $value;
		}elseif ($name == 'recordsOwned'){
			$this->recordsOwned = $value;
		}elseif ($name == 'recordsToInclude'){
			$this->recordsToInclude = $value;
		}elseif ($name == 'libraryTopLinks'){
			$this->libraryTopLinks = $value;
		}elseif ($name == 'browseCategories') {
			$this->browseCategories = $value;
		}elseif ($name == 'patronNameDisplayStyle'){
			if ($this->patronNameDisplayStyle != $value){
				$this->patronNameDisplayStyle = $value;
				$this->patronNameDisplayStyleChanged = true;
			}
		}else{
			$this->data[$name] = $value;
		}
	}
	/**
	 * Override the fetch functionality to fetch related objects
	 *
	 * @see DB/DB_DataObject::fetch()
	 */
	public function fetch(){
		$return = parent::fetch();
		if ($return) {
			if (isset($this->showInMainDetails) && is_string($this->showInMainDetails) && !empty($this->showInMainDetails)) {
				// convert to array retrieving from database
				$this->showInMainDetails = unserialize($this->showInMainDetails);
				if (!$this->showInMainDetails) $this->showInMainDetails = array();
			}
			elseif (empty($this->showInMainDetails)) {
				// when a value is not set, assume set to show all options, eg null = all
				$default = self::$showInMainDetailsOptions;
				// remove options below that aren't to be part of the default
				unset($default['showISBNs']);
				$default = array_keys($default);
				$this->showInMainDetails = $default;
			}
		}
		return $return;
	}
	/**
	 * Override the update functionality to save related objects
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update(){
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)) {
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		$ret = parent::update();
		if ($ret !== FALSE ){
			$this->saveHolidays();
			$this->saveFacets();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveLibraryLinks();
			$this->saveLibraryTopLinks();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
		}
		if ($this->patronNameDisplayStyleChanged){
			$libraryLocations = new Location();
			$libraryLocations->libraryId = $this->libraryId;
			$libraryLocations->find();
			while ($libraryLocations->fetch()){
				$user = new User();
				$numChanges = $user->query("update user set displayName = '' where homeLocationId = {$libraryLocations->locationId}");

			}
		}
		return $ret;
	}

	/**
	 * Override the insert functionality to save the related objects
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert(){
		if (isset($this->showInMainDetails) && is_array($this->showInMainDetails)) {
			// convert array to string before storing in database
			$this->showInMainDetails = serialize($this->showInMainDetails);
		}
		$ret = parent::insert();
		if ($ret !== FALSE ){
			$this->saveHolidays();
			$this->saveFacets();
			$this->saveRecordsOwned();
			$this->saveRecordsToInclude();
			$this->saveLibraryLinks();
			$this->saveLibraryTopLinks();
			$this->saveBrowseCategories();
			$this->saveMoreDetailsOptions();
		}
		return $ret;
	}

	public function saveBrowseCategories(){
		if (isset ($this->browseCategories) && is_array($this->browseCategories)){
			/** @var LibraryBrowseCategory[] $browseCategories */
			foreach ($this->browseCategories as $libraryBrowseCategory){
				if (isset($libraryBrowseCategory->deleteOnSave) && $libraryBrowseCategory->deleteOnSave == true){
					$libraryBrowseCategory->delete();
				}else{
					if (isset($libraryBrowseCategory->id) && is_numeric($libraryBrowseCategory->id)){
						$libraryBrowseCategory->update();
					}else{
						$libraryBrowseCategory->libraryId = $this->libraryId;
						$libraryBrowseCategory->insert();
					}
				}
			}
			unset($this->browseCategories);
		}
	}

	public function clearBrowseCategories(){
		$browseCategories = new LibraryBrowseCategory();
		$browseCategories->libraryId = $this->libraryId;
		$browseCategories->delete();
		$this->browseCategories = array();
	}

	public function saveLibraryLinks(){
		if (isset ($this->libraryLinks) && is_array($this->libraryLinks)){
			/** @var LibraryLink[] $libraryLinks */
			foreach ($this->libraryLinks as $libraryLink){
				if (isset($libraryLink->deleteOnSave) && $libraryLink->deleteOnSave == true){
					$libraryLink->delete();
				}else{
					if (isset($libraryLink->id) && is_numeric($libraryLink->id)){
						$libraryLink->update();
					}else{
						$libraryLink->libraryId = $this->libraryId;
						$libraryLink->insert();
					}
				}
			}
			unset($this->libraryLinks);
		}
	}

	public function clearLibraryLinks(){
		$libraryLinks = new LibraryLink();
		$libraryLinks->libraryId = $this->libraryId;
		$libraryLinks->delete();
		$this->libraryLinks = array();
	}

	public function saveLibraryTopLinks(){
		if (isset ($this->libraryTopLinks) && is_array($this->libraryTopLinks)){
			/** @var LibraryTopLinks[] $libraryTopLinks */
			foreach ($this->libraryTopLinks as $libraryLink){
				if (isset($libraryLink->deleteOnSave) && $libraryLink->deleteOnSave == true){
					$libraryLink->delete();
				}else{
					if (isset($libraryLink->id) && is_numeric($libraryLink->id)){
						$libraryLink->update();
					}else{
						$libraryLink->libraryId = $this->libraryId;
						$libraryLink->insert();
					}
				}
			}
			unset($this->libraryTopLinks);
		}
	}

	public function clearLibraryTopLinks(){
		$libraryTopLinks = new LibraryTopLinks();
		$libraryTopLinks->libraryId = $this->libraryId;
		$libraryTopLinks->delete();
		$this->libraryTopLinks = array();
	}

	public function saveRecordsOwned(){
		if (isset ($this->recordsOwned) && is_array($this->recordsOwned)){
			/** @var LibraryRecordOwned $object */
			foreach ($this->recordsOwned as $object){
				if (isset($object->deleteOnSave) && $object->deleteOnSave == true){
					$object->delete();
				}else{
					if (isset($object->id) && is_numeric($object->id)){
						$object->update();
					}else{
						$object->libraryId = $this->libraryId;
						$object->insert();
					}
				}
			}
			unset($this->recordsOwned);
		}
	}

	public function clearRecordsOwned(){
		$object = new LibraryRecordOwned();
		$object->libraryId = $this->libraryId;
		$object->delete();
		$this->recordsOwned = array();
	}

	public function saveRecordsToInclude(){
		if (isset ($this->recordsToInclude) && is_array($this->recordsToInclude)){
			/** @var LibraryRecordOwned $object */
			foreach ($this->recordsToInclude as $object){
				if (isset($object->deleteOnSave) && $object->deleteOnSave == true){
					$object->delete();
				}else{
					if (isset($object->id) && is_numeric($object->id)){
						$object->update();
					}else{
						$object->libraryId = $this->libraryId;
						$object->insert();
					}
				}
			}
			unset($this->recordsToInclude);
		}
	}

	public function clearRecordsToInclude(){
		$object = new LibraryRecordToInclude();
		$object->libraryId = $this->libraryId;
		$object->delete();
		$this->recordsToInclude = array();
	}

	public function saveMoreDetailsOptions(){
		if (isset ($this->moreDetailsOptions) && is_array($this->moreDetailsOptions)){
			/** @var LibraryMoreDetails $options */
			foreach ($this->moreDetailsOptions as $options){
				if (isset($options->deleteOnSave) && $options->deleteOnSave == true){
					$options->delete();
				}else{
					if (isset($options->id) && is_numeric($options->id)){
						$options->update();
					}else{
						$options->libraryId = $this->libraryId;
						$options->insert();
					}
				}
			}
			unset($this->moreDetailsOptions);
		}
	}

	public function clearMoreDetailsOptions(){
		$options = new LibraryMoreDetails();
		$options->libraryId = $this->libraryId;
		$options->delete();
		$this->moreDetailsOptions = array();
	}

	public function saveFacets(){
		if (isset ($this->facets) && is_array($this->facets)){
			/** @var LibraryFacetSetting $facet */
			foreach ($this->facets as $facet){
				if (isset($facet->deleteOnSave) && $facet->deleteOnSave == true){
					$facet->delete();
				}else{
					if (isset($facet->id) && is_numeric($facet->id)){
						$facet->update();
					}else{
						$facet->libraryId = $this->libraryId;
						$facet->insert();
					}
				}
			}
			unset($this->facets);
		}
	}

	public function clearFacets(){
		$facets = new LibraryFacetSetting();
		$facets->libraryId = $this->libraryId;
		$facets->delete();
		$this->facets = array();
	}

	public function saveHolidays(){
		if (isset ($this->holidays) && is_array($this->holidays)){
			/** @var Holiday $holiday */
			foreach ($this->holidays as $holiday){
				if (isset($holiday->deleteOnSave) && $holiday->deleteOnSave == true){
					$holiday->delete();
				}else{
					if (isset($holiday->id) && is_numeric($holiday->id)){
						$holiday->update();
					}else{
						$holiday->libraryId = $this->libraryId;
						$holiday->insert();
					}
				}
			}
			unset($this->holidays);
		}
	}

	static function getDefaultFacets($libraryId = -1){
		global $configArray;
		$defaultFacets = array();

		$facet = new LibraryFacetSetting();
		$facet->setupTopFacet('format_category', 'Format Category');
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;


		if ($configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupTopFacet('availability_toggle', 'Available?', true);
			$facet->libraryId = $libraryId;
			$facet->weight = count($defaultFacets) + 1;
			$defaultFacets[] = $facet;
		}


		if (!$configArray['Index']['enableDetailedAvailability']){
			$facet = new LibraryFacetSetting();
			$facet->setupSideFacet('available_at', 'Available Now At', true);
			$facet->libraryId = $libraryId;
			$facet->weight = count($defaultFacets) + 1;
			$defaultFacets[] = $facet;
		}

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('format', 'Format', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('literary_form_full', 'Literary Form', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('target_audience_full', 'Reading Level', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$facet->numEntriesToShowByDefault = 8;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('topic_facet', 'Subject', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('time_since_added', 'Added in the Last', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('authorStr', 'Author', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('awards_facet', 'Awards', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('econtent_device', 'Compatible Device', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('econtent_source', 'eContent Source', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('econtent_protection_type', 'eContent Protection', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('era', 'Era', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('genre_facet', 'Genre', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('itype', 'Item Type', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('language', 'Language', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_code', 'Lexile Code', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('lexile_score', 'Lexile Score', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('mpaa_rating', 'Movie Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_library', 'Owning System', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('owning_location', 'Owning Branch', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('publishDate', 'Publication Date', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupAdvancedFacet('geographic_facet', 'Region', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		$facet = new LibraryFacetSetting();
		$facet->setupSideFacet('rating_facet', 'User Rating', true);
		$facet->libraryId = $libraryId;
		$facet->weight = count($defaultFacets) + 1;
		$defaultFacets[] = $facet;

		return $defaultFacets;
	}

	public function getNumLocationsForLibrary(){
		$location = new Location;
		$location->libraryId = $this->libraryId;
		return $location->count();
	}
}
