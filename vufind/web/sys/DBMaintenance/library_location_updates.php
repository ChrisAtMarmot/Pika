<?php
/**
 * Updates related to library & location configuration for cleanliness
 *
 * @category VuFind-Plus-2014 
 * @author Mark Noble <mark@marmot.org>
 * Date: 7/29/14
 * Time: 2:23 PM
 */

function getLibraryLocationUpdates(){
	return array(
		'library_1' => array(
			'title' => 'Library 1',
			'description' => 'Update Library table to include showSeriesAsTab column',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN showSeriesAsTab TINYINT NOT NULL DEFAULT '0';",
				"UPDATE library SET showSeriesAsTab = '1' WHERE subdomain IN ('adams') ",
			),
		),

		'library_2' => array(
			'title' => 'Library 2',
			'description' => 'Update Library table to include showItsHere column',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN showItsHere TINYINT NOT NULL DEFAULT '1';",
				"UPDATE library SET showItsHere = '0' WHERE subdomain IN ('adams', 'msc') ",
			),
		),

		'library_3' => array(
			'title' => 'Library 3',
			'description' => 'Update Library table to include holdDisclaimer column',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN holdDisclaimer TEXT;",
				"UPDATE library SET holdDisclaimer = 'I understand that by requesting this item, information from my library patron record, including my contact information may be made available to the lending library.' WHERE subdomain IN ('msc') ",
			),
		),

		'library_4' => array(
			'title' => 'Library 4',
			'description' => 'Update Library table to include enableAlphaBrowse column',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN enableAlphaBrowse TINYINT DEFAULT '1';",
			),
		),

		'library_5' => array(
			'title' => 'Library 5',
			'description' => 'Set up a link to boopsie in mobile',
			'sql' => array(
				"ALTER TABLE `library` ADD `boopsieLink` VARCHAR(150) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;",
			),
		),

		'library_6' => array(
			'title' => 'Library 6',
			'description' => 'Add fields orginally defined for Marmot',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `showHoldCancelDate` TINYINT(4) NOT NULL DEFAULT '0';",
				"ALTER TABLE `library` ADD `enablePospectorIntegration` TINYINT(4) NOT NULL DEFAULT '0';",
				"ALTER TABLE `library` ADD `prospectorCode` VARCHAR(10) NOT NULL DEFAULT '';",
				"ALTER TABLE `library` ADD `showRatings` TINYINT(4) NOT NULL DEFAULT '1';",
				"ALTER TABLE `library` ADD `searchesFile` VARCHAR(15) NOT NULL DEFAULT 'default';",
				"ALTER TABLE `library` ADD `minimumFineAmount` FLOAT NOT NULL DEFAULT '0';",
				"UPDATE library SET minimumFineAmount = '5' WHERE showEcommerceLink = '1'",
				"ALTER TABLE `library` ADD `enableGenealogy` TINYINT(4) NOT NULL DEFAULT '0';",
				"ALTER TABLE `library` ADD `enableCourseReserves` TINYINT(1) NOT NULL DEFAULT '0';",
				"ALTER TABLE `library` ADD `exportOptions` VARCHAR(100) NOT NULL DEFAULT 'RefWorks|EndNote';",
				"ALTER TABLE `library` ADD `enableSelfRegistration` TINYINT NOT NULL DEFAULT '0';",
				"ALTER TABLE `library` ADD `useHomeLinkInBreadcrumbs` TINYINT(4) NOT NULL DEFAULT '0';",
			),
		),

		'library_7' => array(
			'title' => 'Library 7',
			'description' => 'Allow materials request to be enabled or disabled by library',
			'sql' => array(
				"ALTER TABLE `library` ADD `enableMaterialsRequest` TINYINT DEFAULT '1';",
			),
		),

		'library_8' => array(
			'title' => 'Library 8',
			'description' => 'Add eContenLinkRules to determine how to load library specific link urls',
			'sql' => array(
				"ALTER TABLE `library` ADD `eContentLinkRules` VARCHAR(512) DEFAULT '';",
			),
		),

		'library_9' => array(
			'title' => 'Library 9',
			'description' => 'Add showOtherEditionsPopup to determine whether or not the Other Editions and Languages Popup is shown',
			'sql' => array(
				"ALTER TABLE `library` ADD `showOtherEditionsPopup` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `showTableOfContentsTab` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `notesTabName` VARCHAR(50) DEFAULT 'Notes';",
			),
		),

		'library_10' => array(
			'title' => 'Library 10',
			'description' => 'Add fields for showing copies in holdings summary, and hold button in results list',
			'sql' => array(
				"ALTER TABLE `library` ADD `showHoldButtonInSearchResults` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `showCopiesLineInHoldingsSummary` TINYINT DEFAULT '1';",
			),
		),

		'library_11' => array(
			'title' => 'Library 11',
			'description' => 'Add fields for disabling some Novelist functionality and disabling boosting by number of holdings',
			'sql' => array(
				"ALTER TABLE `library` ADD `showSimilarAuthors` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `showSimilarTitles` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `showProspectorTitlesAsTab` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `show856LinksAsTab` TINYINT DEFAULT '0';",
				"ALTER TABLE `library` ADD `applyNumberOfHoldingsBoost` TINYINT DEFAULT '1';",
				"ALTER TABLE `library` ADD `worldCatUrl` VARCHAR(100) DEFAULT '';",
				"ALTER TABLE `library` ADD `worldCatQt` VARCHAR(20) DEFAULT '';",
				"ALTER TABLE `library` ADD `preferSyndeticsSummary` TINYINT DEFAULT '1';",
			),
		),

		'library_12' => array(
			'title' => 'Library 12',
			'description' => 'Add abbreviation for library name for use in some cases where the full name is not desired.',
			'sql' => array(
				"ALTER TABLE `library` ADD `abbreviatedDisplayName` VARCHAR(20) DEFAULT '';",
				"UPDATE `library` SET `abbreviatedDisplayName` = LEFT(`displayName`, 20);",
			),
		),

		'library_13' => array(
			'title' => 'Library 13',
			'description' => 'Updates to World Cat integration for local libraries',
			'sql' => array(
				"ALTER TABLE `library` CHANGE `worldCatQt` `worldCatQt` VARCHAR(40) DEFAULT '';",
			),
		),

		'library_14' => array(
			'title' => 'Library 14',
			'description' => 'Allow Go Deeper to be disabled by Library',
			'sql' => array(
				"ALTER TABLE `library` ADD `showGoDeeper` TINYINT DEFAULT '1';",
			),
		),

		'library_15' => array(
			'title' => 'Library 15',
			'description' => 'Add showProspectorResultsAtEndOfSearch to library so prospector titles can be removed from search results without completely diasabling prospector',
			'sql' => array(
				"ALTER TABLE `library` ADD `showProspectorResultsAtEndOfSearch` TINYINT DEFAULT '1';",
			),
		),

		'library_16' => array(
			'title' => 'Library 16',
			'description' => 'Add overdriveAdvantage Information to library so we can determine who advantage title should belong to. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `overdriveAdvantageName` VARCHAR(128) DEFAULT '';",
				"ALTER TABLE `library` ADD `overdriveAdvantageProductsKey` VARCHAR(20) DEFAULT '';",
			),
		),

		'library_17' => array(
			'title' => 'Library 17',
			'description' => 'Add defaultNotNeededAfterDays and homePageWidgetId. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `defaultNotNeededAfterDays` INT DEFAULT '0';",
				"ALTER TABLE `library` ADD `homePageWidgetId` INT(11) DEFAULT '0';",
			),
		),

		'library_18' => array(
			'title' => 'Library 18',
			'description' => 'Add showCheckInGrid to determine how periodicals display. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `showCheckInGrid` INT DEFAULT '1';",
			),
		),

		'library_19' => array(
			'title' => 'Library 19',
			'description' => 'Add the ability to specify a list of records to blacklist. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `recordsToBlackList` MEDIUMTEXT;",
			),
		),

		'library_20' => array(
			'title' => 'Library 20',
			'description' => 'Add the show or hide marmot search results in scoped searches. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `showMarmotResultsAtEndOfSearch` INT(11) DEFAULT 1;",
			),
		),

		'library_21' => array(
			'title' => 'Library 21',
			'description' => 'Add the home link text so the breadcrumbs can be customized. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `homeLinkText` VARCHAR(50) DEFAULT 'Home';",
			),
		),

		'library_23' => array(
			'title' => 'Library 23',
			'description' => 'Add the ability to disable wikipedia and the Other format icon by library. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `showOtherFormatCategory` TINYINT(1) DEFAULT '1';",
				"ALTER TABLE `library` ADD `showWikipediaContent` TINYINT(1) DEFAULT '1';",
			),
		),

		'library_24' => array(
			'title' => 'Library 24',
			'description' => 'Add the ability to customize the link to pay fines. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `payFinesLink` VARCHAR(512) DEFAULT 'default';",
			),
		),

		'library_25' => array(
			'title' => 'Library 25',
			'description' => 'Add the ability to customize the link text to pay fines. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `payFinesLinkText` VARCHAR(512) DEFAULT 'Click to Pay Fines Online';",
			),
		),

		'library_26' => array(
			'title' => 'Library 26',
			'description' => 'Add a support e-mail address for eContent problems.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `eContentSupportAddress` VARCHAR(256) DEFAULT 'askmarmot@marmot.org';",
			),
		),

		'library_28' => array(
			'title' => 'Library 28',
			'description' => 'Add ilsCode.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `ilsCode` VARCHAR(5) DEFAULT '';",
			),
		),

		'library_29' => array(
			'title' => 'Library 29',
			'description' => 'Add systemMessage.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `systemMessage` VARCHAR(512) DEFAULT '';",
			),
		),

		'library_30' => array(
			'title' => 'Library 30',
			'description' => 'Add bettter controls for restricting what is searched',
			'sql' => array(
				"ALTER TABLE library ADD restrictSearchByLibrary TINYINT(1) DEFAULT '0'",
				"ALTER TABLE library ADD includeDigitalCollection TINYINT(1) DEFAULT '1'",
				"UPDATE library SET restrictSearchByLibrary = 1 WHERE defaultLibraryFacet <> ''"
			),
		),

		'library_31' => array(
			'title' => 'Library 31',
			'description' => 'Add includeOutOfSystemExternalLinks option to allow econtent links to be shown in global library search',
			'sql' => array(
				"ALTER TABLE library ADD includeOutOfSystemExternalLinks TINYINT(1) DEFAULT '0'",
			),
		),

		'library_32' => array(
			'title' => 'Library 32',
			'description' => 'Add restrictOwningBranchesAndSystems option to allow libraries to only show "their" systems and branches',
			'sql' => array(
				"ALTER TABLE library ADD restrictOwningBranchesAndSystems TINYINT(1) DEFAULT '1'",
			),
		),

		'library_33' => array(
			'title' => 'Library 33',
			'description' => 'Add additional configuration for Available At facet',
			'sql' => array(
				"ALTER TABLE library ADD showAvailableAtAnyLocation TINYINT(1) DEFAULT '1'",
			),
		),

		'library_34' => array(
			'title' => 'Library 34',
			'description' => 'Remove Facet File',
			'sql' => array(
				"ALTER TABLE library DROP COLUMN facetFile",
				"ALTER TABLE library DROP COLUMN defaultLibraryFacet",
			),
		),

		'library_35_marmot' => array(
			'title' => 'Library 35 (Marmot)',
			'description' => 'Add Accounting Unit',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library ADD accountingUnit INT(11) DEFAULT 10",
				"ALTER TABLE library ADD makeOrderRecordsAvailableToOtherLibraries TINYINT(1) DEFAULT 0",
			),
		),

		'library_35_nashville' => array(
			'title' => 'Library 35 (Nashville Branch)',
			'description' => 'Additional control over profile updates',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library ADD allowPatronAddressUpdates TINYINT(1) DEFAULT '1'",
				"ALTER TABLE library ADD showWorkPhoneInProfile TINYINT(1) DEFAULT '0'",
				"ALTER TABLE library ADD showNoticeTypeInProfile TINYINT(1) DEFAULT '0'",
				"ALTER TABLE library ADD showPickupLocationInProfile TINYINT(1) DEFAULT '0'"
			),
		),

		'library_36_nashville' => array(
			'title' => 'Library 36 (Nashville branch)',
			'description' => 'Allow multiple widgets on the home page',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` CHANGE `homePageWidgetId` `homePageWidgetId` VARCHAR( 50 ) NULL DEFAULT ''",
			),
		),

		'library_css' => array(
			'title' => 'Library and Location CSS',
			'description' => 'Make changing the theme of common elements easier for libraries and locations',
			'sql' => array(
				"ALTER TABLE library ADD additionalCss MEDIUMTEXT",
				"ALTER TABLE location ADD additionalCss MEDIUMTEXT",
			),
		),

		'library_grouping' => array(
			'title' => 'Library Grouping Options',
			'description' => 'Whether or not records should shown as grouped in the user interface',
			'sql' => array(
				"ALTER TABLE library ADD searchGroupedRecords TINYINT DEFAULT 0",
			),
		),

		'library_materials_request_limits' => array(
			'title' => 'Library Materials Request Limits',
			'description' => 'Add configurable limits to the number of open requests and total requests per year that patrons can make. ',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `maxRequestsPerYear` INT(11) DEFAULT 60;",
				"ALTER TABLE `library` ADD `maxOpenRequests` INT(11) DEFAULT 5;",
			),
		),

		'library_contact_links' => array(
			'title' => 'Library Contact Links',
			'description' => 'Add contact links for Facebook, Twitter and general contact to library config.',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `twitterLink` VARCHAR(255) DEFAULT '';",
				"ALTER TABLE `library` ADD `facebookLink` VARCHAR(255) DEFAULT '';",
				"ALTER TABLE `library` ADD `generalContactLink` VARCHAR(255) DEFAULT '';",
			),
		),

		'library_links' => array(
			'title' => 'LibraryLinks',
			'description' => 'Add configurable links to display within the home page. ',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"CREATE TABLE IF NOT EXISTS library_links (" .
				"id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"libraryId INT NOT NULL, " .
				"category VARCHAR(100) NOT NULL, " .
				"linkText VARCHAR(100) NOT NULL, " .
				"url VARCHAR(255) NOT NULL, " .
				"weight INT NOT NULL DEFAULT '0' " .
				") ENGINE = MYISAM",
				"ALTER TABLE `library_links` ADD INDEX `libraryId` (`libraryId`)",
			),
		),

		'library_links_show_html' => array(
			'title' => 'LibraryLinks Update to Show HTML',
			'description' => 'Allow showing HTML within Sidebar. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library_links` ADD COLUMN `htmlContents` MEDIUMTEXT",
			),
		),

		'library_top_links' => array(
			'title' => 'Library Top Links',
			'description' => 'Add configurable links to display within the header. ',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"CREATE TABLE IF NOT EXISTS library_top_links (" .
				"id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"libraryId INT NOT NULL, " .
				"linkText VARCHAR(100) NOT NULL, " .
				"url VARCHAR(255) NOT NULL, " .
				"weight INT NOT NULL DEFAULT '0' " .
				") ENGINE = MYISAM",
				"ALTER TABLE `library_top_links` ADD INDEX `libraryId` (`libraryId`)",
			),
		),

		'library_pin_reset' => array(
			'title' => 'Library PIN Reset',
			'description' => 'Allow libraries to offer a link to reset a PIN (for libraries that use PINs.)',
			'sql' => array(
				"ALTER TABLE library ADD allowPinReset TINYINT(1)",
			),
		),

		'library_location_boosting' => array(
			'title' => 'Library Location Boosting',
			'description' => 'Allow additional boosting for library and location holdings in addition to the default in the index.',
			'sql' => array(
				"ALTER TABLE library ADD additionalLocalBoostFactor INT(11) DEFAULT 1",
				"ALTER TABLE location ADD additionalLocalBoostFactor INT(11) DEFAULT 1",
			),
		),

		'library_location_repeat_online' => array(
			'title' => 'Library Location Repeat Online',
			'description' => 'Allow additional boosting for library and location holdings in addition to the default in the index.',
			'sql' => array(
				"ALTER TABLE library ADD repeatInOnlineCollection INT(11) DEFAULT 1",
				"ALTER TABLE location ADD repeatInOnlineCollection INT(11) DEFAULT 1",
			),
		),

		'library_expiration_warning' => array(
			'title' => 'Library Expiration Warning',
			'description' => 'Determines whether or not the expiration warning should be shown to patrons who are set to expire soon.',
			'sql' => array(
				"ALTER TABLE library ADD showExpirationWarnings TINYINT(1) DEFAULT 1",
			),
		),

		'library_order_information' => array(
			'title' => 'Library Order Information',
			'description' => 'Add Properties to handle integration of order records within VuFind',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library ADD orderAccountingUnit INT(11) DEFAULT Null",
				"ALTER TABLE library ADD makeOrderRecordsAvailableToOtherLibraries TINYINT(1) DEFAULT 0",
			),
		),

		'library_ils_code_expansion' => array(
			'title' => 'Library Expand ILS Code',
			'description' => 'Expand ILS Code to allow regular expressions to be used',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library CHANGE ilsCode ilsCode VARCHAR(15) NOT NULL",
			),
		),

		'library_ils_code_expansion_2' => array(
			'title' => 'Library Expand ILS Code 2',
			'description' => 'Expand ILS Code to allow regular expressions to be used',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library CHANGE ilsCode ilsCode VARCHAR(50) NOT NULL",
			),
		),

		'econtent_locations_to_include' => array(
			'title' => 'eContent Locations To Include',
			'description' => 'Adds the ability to define which shared location codes should be included in the search scope',
			'sql' => array(
				"ALTER TABLE library ADD econtentLocationsToInclude VARCHAR(255)",
				"ALTER TABLE location ADD econtentLocationsToInclude VARCHAR(255)",
			),
		),

		'pTypesForLibrary' => array(
			'title' => 'pTypesForLibrary',
			'description' => 'A list of pTypes that are valid for the library',
			'sql' => array(
				"ALTER TABLE library ADD pTypes VARCHAR(255)",
			),
		),

		'library_bookings' => array(
			'title' => 'Enable Materials Booking',
			'description' => 'Add a library setting to enable Sierra\'s Materials Booking module.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `enableMaterialsBooking` TINYINT NOT NULL DEFAULT 0"
			),
		),

		'hours_and_locations_control' => array(
			'title' => 'Hours and Locations Control',
			'description' => 'Allow additional control over library hours and locations display.',
			'sql' => array(
				"ALTER TABLE library ADD showLibraryHoursAndLocationsLink INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showInLocationsAndHoursList INT(11) DEFAULT 1",
			),
		),

		'library_location_display_controls' => array(
			'title' => 'Library And Location display controls',
			'description' => 'Add additional controls for display of enhanced functionality for libraries and locations',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library ADD showShareOnExternalSites INT(11) DEFAULT 1",
				"ALTER TABLE library ADD showQRCode INT(11) DEFAULT 1",
				"ALTER TABLE library ADD showGoodReadsReviews INT(11) DEFAULT 1",
				"ALTER TABLE library ADD showStaffView INT(11) DEFAULT 1",
				"ALTER TABLE library ADD showSearchTools INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showShareOnExternalSites INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showTextThis INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showEmailThis INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showFavorites INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showComments INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showQRCode INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showGoodReadsReviews INT(11) DEFAULT 1",
				"ALTER TABLE location ADD showStaffView INT(11) DEFAULT 1",
			)
		),

		'library_barcodes' => array(
			'title' => 'Library Barcodes',
			'description' => 'Better handling of library barcodes to handle automatic prefixing.',
			'sql' => array(
				"ALTER TABLE library ADD barcodePrefix VARCHAR(15) DEFAULT ''",
				"ALTER TABLE library ADD minBarcodeLength INT(11) DEFAULT 0",
				"ALTER TABLE library ADD maxBarcodeLength INT(11) DEFAULT 0",
			),
		),

		'library_show_display_name' => array(
			'title' => 'Library Show Display Name In Header',
			'description' => 'Add option to allow display name to be shown in the header for the library',
			'sql' => array(
				"ALTER TABLE library ADD showDisplayNameInHeader TINYINT DEFAULT 0",
			),
		),

		'library_prompt_birth_date' => array(
			'title' => 'Library Prompt For Birth Date In Self Registration',
			'description' => 'Library Prompt For Birth Date In Self Registration',
			'sql' => array(
				"ALTER TABLE library ADD promptForBirthDateInSelfReg TINYINT DEFAULT 0",
			),
		),

		'library_facets' => array(
			'title' => 'Library Facets',
			'description' => 'Create Library Facets table to allow library admins to customize their own facets. ',
			'continueOnError' => true,
			'sql' => array(
				"CREATE TABLE IF NOT EXISTS library_facet_setting (" .
				"`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"`libraryId` INT NOT NULL, " .
				"`displayName` VARCHAR(50) NOT NULL, " .
				"`facetName` VARCHAR(50) NOT NULL, " .
				"weight INT NOT NULL DEFAULT '0', " .
				"numEntriesToShowByDefault INT NOT NULL DEFAULT '5', " .
				"showAsDropDown TINYINT NOT NULL DEFAULT '0', " .
				"sortMode ENUM ('alphabetically', 'num_results') NOT NULL DEFAULT 'num_results', " .
				"showAboveResults TINYINT NOT NULL DEFAULT '0', " .
				"showInResults TINYINT NOT NULL DEFAULT '1', " .
				"showInAuthorResults TINYINT NOT NULL DEFAULT '1', " .
				"showInAdvancedSearch TINYINT NOT NULL DEFAULT '1' " .
				") ENGINE = MYISAM COMMENT = 'A widget that can be displayed within VuFind or within other sites' ",
				"ALTER TABLE `library_facet_setting` ADD UNIQUE `libraryFacet` (`libraryId`, `facetName`)",
			),
		),

		'library_facets_1' => array(
			'title' => 'Library Facets Update 1',
			'description' => 'Add index to library facets. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library_facet_setting ADD INDEX (`libraryId`)",
			),
		),

		'library_facets_2' => array(
			'title' => 'Library Facets Update 2',
			'description' => 'Add collapsing of facets and more values popup. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE library_facet_setting ADD collapseByDefault TINYINT DEFAULT '0'",
				"ALTER TABLE library_facet_setting ADD useMoreFacetPopup TINYINT DEFAULT '1'",
			),
		),

		'location_facets' => array(
			'title' => 'Location Facets',
			'description' => 'Create Location Facets table to allow library admins to customize their own facets. ',
			'continueOnError' => true,
			'sql' => array(
				"CREATE TABLE IF NOT EXISTS location_facet_setting (" .
				"`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, " .
				"`locationId` INT NOT NULL, " .
				"`displayName` VARCHAR(50) NOT NULL, " .
				"`facetName` VARCHAR(50) NOT NULL, " .
				"weight INT NOT NULL DEFAULT '0', " .
				"numEntriesToShowByDefault INT NOT NULL DEFAULT '5', " .
				"showAsDropDown TINYINT NOT NULL DEFAULT '0', " .
				"sortMode ENUM ('alphabetically', 'num_results') NOT NULL DEFAULT 'num_results', " .
				"showAboveResults TINYINT NOT NULL DEFAULT '0', " .
				"showInResults TINYINT NOT NULL DEFAULT '1', " .
				"showInAuthorResults TINYINT NOT NULL DEFAULT '1', " .
				"showInAdvancedSearch TINYINT NOT NULL DEFAULT '1', " .
				"INDEX (locationId) " .
				") ENGINE = MYISAM COMMENT = 'A widget that can be displayed within VuFind or within other sites' ",
				"ALTER TABLE `location_facet_setting` ADD UNIQUE `locationFacet` (`locationID`, `facetName`)",
			),
		),

		'location_facets_1' => array(
			'title' => 'Location Facets Update 1',
			'description' => 'Add collapsing of facets and more values popup. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE location_facet_setting ADD collapseByDefault TINYINT DEFAULT '0'",
				"ALTER TABLE location_facet_setting ADD useMoreFacetPopup TINYINT DEFAULT '1'",
			),
		),

		'facet_grouping_updates' => array(
			'title' => 'Update facets to handle record grouping',
			'description' => 'Change names of facets based on record grouping changes. ',
			'continueOnError' => true,
			'sql' => array(
				"UPDATE location_facet_setting SET facetName = 'owning_location' WHERE  facetName = 'building'",
				"UPDATE library_facet_setting SET facetName = 'owning_location' WHERE  facetName = 'building'",
				"UPDATE location_facet_setting SET facetName = 'owning_library' WHERE  facetName = 'institution'",
				"UPDATE library_facet_setting SET facetName = 'owning_library' WHERE  facetName = 'institution'",
			),
		),

		'collapse_facets' => array(
			'title' => 'Collapse Facets',
			'description' => 'Collapse all facets by default. To match new Pika style.',
			'continueOnError' => true,
			'sql' => array(
				"UPDATE location_facet_setting SET collapseByDefault = '1'",
				"UPDATE library_facet_setting SET collapseByDefault = '1'",
			),
		),

		'location_1' => array(
			'title' => 'Location 1',
			'description' => 'Add fields orginally defined for Marmot',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `location` ADD `defaultPType` INT(11) NOT NULL DEFAULT '-1';",
				"ALTER TABLE `location` ADD `ptypesToAllowRenewals` VARCHAR(128) NOT NULL DEFAULT '*';"
			),
		),

		'location_2' => array(
			'title' => 'Location 2',
			'description' => 'Add the ability to customize footers per location',
			'sql' => array(
				"ALTER TABLE `location` ADD `footerTemplate` VARCHAR(40) NOT NULL DEFAULT 'default';",
			),
		),

		'location_3' => array(
			'title' => 'Location 3',
			'description' => 'Add the ability to set home page widget by location',
			'sql' => array(
				"ALTER TABLE `location` ADD `homePageWidgetId` INT(11) DEFAULT '0';",
			),
		),

		'location_4' => array(
			'title' => 'Location 4',
			'description' => 'Add the ability to specify a list of records to blacklist. ',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `location` ADD `recordsToBlackList` MEDIUMTEXT;",
			),
		),

		'location_5' => array(
			'title' => 'Location 5',
			'description' => 'Add ability to configure the automatic timeout length. ',
			'sql' => array(
				"ALTER TABLE `location` ADD `automaticTimeoutLength` INT(11) DEFAULT '90';",
			),
		),

		'location_6' => array(
			'title' => 'Location 6',
			'description' => 'Add ability to configure the automatic timeout length when logged out. ',
			'sql' => array(
				"ALTER TABLE `location` ADD `automaticTimeoutLengthLoggedOut` INT(11) DEFAULT '450';",
			),
		),

		'location_7' => array(
			'title' => 'Location 7',
			'description' => 'Add extraLocationCodesToInclude field for indexing of juvenile collections and other special collections, and add bettter controls for restricting what is searched',
			'sql' => array(
				"ALTER TABLE location ADD extraLocationCodesToInclude VARCHAR(255) DEFAULT ''",
				"ALTER TABLE location ADD restrictSearchByLocation TINYINT(1) DEFAULT '0'",
				"ALTER TABLE location ADD includeDigitalCollection TINYINT(1) DEFAULT '1'",
				"UPDATE location SET restrictSearchByLocation = 1 WHERE defaultLocationFacet <> ''"
			),
		),

		'location_8' => array(
			'title' => 'Location 8',
			'description' => 'Remove default location facet',
			'sql' => array(
				"ALTER TABLE location DROP defaultLocationFacet",
			),
		),

		'location_9' => array(
			'title' => 'Location 9',
			'description' => 'Allow suppressing all items from a location',
			'sql' => array(
				"ALTER TABLE location ADD suppressHoldings TINYINT(1) DEFAULT '0'",
			),
		),

		'location_10' => array(
			'title' => 'Location 10',
			'description' => 'Allow multiple widgets on the home page',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `location` CHANGE `homePageWidgetId` `homePageWidgetId` VARCHAR( 50 ) NULL DEFAULT ''",
			),
		),

		'location_address' => array(
			'title' => 'Location Address updates',
			'description' => 'Add fields related to address updates',
			'sql' => array(
				"ALTER TABLE location ADD address MEDIUMTEXT",
				"ALTER TABLE location ADD phone VARCHAR(15)  DEFAULT ''",
			),
		),

		'location_show_display_name' => array(
			'title' => 'Location Show Display Name In Header',
			'description' => 'Add option to allow display name to be shown in the header',
			'sql' => array(
				"ALTER TABLE location ADD showDisplayNameInHeader TINYINT DEFAULT 0",
			),
		),

		'location_increase_code_column_size' => array(
			'title' => 'Increase code column-size',
			'description' => 'Increase the maximum length an ILS code can be to 50 characters',
			'sql' => array(
				"ALTER TABLE `location` CHANGE `code` `code` varchar(50)",
			),
		),

		'location_sublocation' => array(
			'title' => 'Location Sub Location',
			'description' => 'Add more explicit handling of Sub Location to the location table ',
			'sql' => array(
				"ALTER TABLE `location` ADD subLocation varchar(50)",
			),
		),

		'location_sublocation_uniqueness' => array(
			'title' => 'SubLocations Uniqueness',
			'description' => 'Make combination of location and sublocation unique rather than just location',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE location DROP INDEX `code` , ADD UNIQUE `code` ( `code` , `subLocation` ) ",
			),
		),

		'search_sources' => array(
			'title' => 'Search Sources',
			'description' => 'Setup Library and Location Search Source Table',
			'sql' => array(
				"CREATE TABLE library_search_source (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					libraryId INT(11) NOT NULL DEFAULT -1,
					label VARCHAR(50) NOT NULL,
					weight INT NOT NULL DEFAULT 0,
					searchWhat ENUM('catalog', 'genealogy', 'overdrive', 'worldcat', 'prospector', 'goldrush', 'title_browse', 'author_browse', 'subject_browse', 'tags'),
					defaultFilter TEXT,
					defaultSort ENUM('relevance', 'popularity', 'newest_to_oldest', 'oldest_to_newest', 'author', 'title', 'user_rating'),
					INDEX (libraryId)
				)",
				"CREATE TABLE location_search_source (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					locationId INT(11) NOT NULL DEFAULT -1,
					label VARCHAR(50) NOT NULL,
					weight INT NOT NULL DEFAULT 0,
					searchWhat ENUM('catalog', 'genealogy', 'overdrive', 'worldcat', 'prospector', 'goldrush', 'title_browse', 'author_browse', 'subject_browse', 'tags'),
					defaultFilter TEXT,
					defaultSort ENUM('relevance', 'popularity', 'newest_to_oldest', 'oldest_to_newest', 'author', 'title', 'user_rating'),
					INDEX (locationId)
				)"
			),
		),

		'search_sources_1' => array(
			'title' => 'Search Sources Update 1',
			'description' => 'Add scoping information to search scope',
			'sql' => array(
				"ALTER TABLE library_search_source ADD COLUMN catalogScoping ENUM('unscoped', 'library', 'location') DEFAULT 'unscoped'",
				"ALTER TABLE location_search_source ADD COLUMN catalogScoping ENUM('unscoped', 'library', 'location') DEFAULT 'unscoped'"
			),
		),

		'more_details_customization' => array(
			'title' => 'More Details Customization',
			'description' => 'Setup tables to allow customization of more details in full record view',
			'sql' => array(
				"CREATE TABLE library_more_details (
						id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						libraryId INT(11) NOT NULL DEFAULT -1,
						weight INT NOT NULL DEFAULT 0,
						source VARCHAR(25) NOT NULL,
						collapseByDefault TINYINT(1),
						INDEX (libraryId)
					)",
				"CREATE TABLE location_more_details (
					id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
					locationId INT(11) NOT NULL DEFAULT -1,
					weight INT NOT NULL DEFAULT 0,
					source VARCHAR(25) NOT NULL,
					collapseByDefault TINYINT(1),
					INDEX (locationId)
				)"
			),
		),

		'availability_toggle_customization' => array(
			'title' => 'Availability Toggle Customization',
			'description' => 'Add the ability to customize the labels for the availability toggles',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN availabilityToggleLabelSuperScope VARCHAR(50) DEFAULT 'Entire Collection'",
				"ALTER TABLE library ADD COLUMN availabilityToggleLabelLocal VARCHAR(50) DEFAULT '{display name}'",
				"ALTER TABLE library ADD COLUMN availabilityToggleLabelAvailable VARCHAR(50) DEFAULT 'Available Now'",
				"ALTER TABLE location ADD COLUMN availabilityToggleLabelSuperScope VARCHAR(50) DEFAULT 'Entire Collection'",
				"ALTER TABLE location ADD COLUMN availabilityToggleLabelLocal VARCHAR(50) DEFAULT '{display name}'",
				"ALTER TABLE location ADD COLUMN availabilityToggleLabelAvailable VARCHAR(50) DEFAULT 'Available Now'",
			),
		),

		'login_form_labels' => array(
			'title' => 'Login Form Labels',
			'description' => 'Add the ability to customize the labels for the login form',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN loginFormUsernameLabel VARCHAR(50) DEFAULT 'Your Name'",
				"ALTER TABLE library ADD COLUMN loginFormPasswordLabel VARCHAR(50) DEFAULT 'Library Card Number'",
			),
		),

		'detailed_hold_notice_configuration' => array(
			'title' => 'Detailed Hold Notice Configuration',
			'description' => 'Additional configuration over how detailed hold notices are displayed to the user',
			'sql' => array(
				"ALTER TABLE library ADD COLUMN showDetailedHoldNoticeInformation TINYINT DEFAULT 1",
				"ALTER TABLE library ADD COLUMN treatPrintNoticesAsPhoneNotices TINYINT DEFAULT 0",
			),
		),

		'show_catalog_options_in_profile' => array(
			'title' => 'Show Catalog Options Tab in User Profile Configuration',
			'description' => 'Additional configuration to let libraries set whether or not the Catalog Options tab in the user profile is displayed to the user',
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `showAlternateLibraryOptionsInProfile` TINYINT(1) DEFAULT 1",
			),
		),

		'hoopla_library_options' => array(
			'title' => 'Updates to determine configuration for Hoopla inclusion/exclusion by library',
			'description' => 'Additional configuration over how Hoopla information is included by library',
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `includeHoopla` TINYINT(1) DEFAULT 0",
			),
		),

		'hoopla_library_options_remove' => array(
				'title' => 'Remove Hoopla setting since it can be done with inclusion rules now',
				'description' => 'Remove Hoopla setting since it can be done with inclusion rules now',
				'sql' => array(
						"ALTER TABLE `library` DROP COLUMN `includeHoopla`",
				),
		),

		'additional_library_contact_links' => array(
			'title' => 'Additional Library Contact Links',
			'description' => 'Add additional contact links for Youtube and Instagram to library config.',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `youtubeLink` VARCHAR(255) DEFAULT NULL AFTER twitterLink;",
				"ALTER TABLE `library` ADD `instagramLink` VARCHAR(255) DEFAULT NULL AFTER youtubeLink;",
			),
		),

		'goodreads_library_contact_link' => array(
			'title' => 'Goodreads Library Contact Link',
			'description' => 'Add additional contact link for Goodreads to library config.',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `goodreadsLink` VARCHAR(255) DEFAULT NULL AFTER instagramLink;",
			),
		),

		'additional_locations_for_availability' => array(
			'title' => 'Additional Locations to Show Availability',
			'description' => 'Add additional additional locations to show availability to library config.',
			'dependencies' => array(),
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD `additionalLocationsToShowAvailabilityFor` VARCHAR(255) DEFAULT '' NOT NULL;",
			),
		),


		'overdrive_integration' => array(
			'title' => 'Add Library Settings for Overdrive integration',
			'description' => 'Add log-in information (Authentication ILS-Name & require Pin) so that we can utilize Overdrive\'s APIs.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `overdriveAuthenticationILSName` VARCHAR(45) NULL AFTER `repeatInOverdrive`;",
				"ALTER TABLE `library` ADD COLUMN `overdriveRequirePin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `overdriveAuthenticationILSName`;",
			),
		),

		'overdrive_integration_2' => array(
			'title' => 'Rename includeDigitalCollection to enableOverdriveCollection for libraries.',
			'description' => 'Rename includeDigitalCollection column to enableOverdriveCollection for libraries.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` CHANGE COLUMN `includeDigitalCollection` `enableOverdriveCollection` TINYINT(1) NULL DEFAULT '1' ;"
			),
		),

		'overdrive_integration_3' => array(
			'title' => 'Rename includeDigitalCollection to enableOverdriveCollection for locations.',
			'description' => 'Rename includeDigitalCollection column to enableOverdriveCollection for locations.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `location` CHANGE COLUMN `includeDigitalCollection` `enableOverdriveCollection` TINYINT(1) NULL DEFAULT '1' ;"
			),
		),

		'full_record_view_configuration_options' => array(
			'title' => 'Add the "Show in Main Details" section configuration options',
			'description' => 'Allows a library to choose which details to display at the top of the record view.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `showInMainDetails` VARCHAR(255) NULL;"
			),
		),

		'dpla_integration' => array(
			'title' => 'DPLA Integration',
			'description' => 'Add a switch to determine whether or not we should include DPLA information within an interface',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `includeDplaResults` TINYINT(1) NULL DEFAULT '0' ;"
			),
		),

		'selfreg_customization' => array(
			'title' => 'Self Registration Customization',
			'description' => 'Add text fields so that libraries may customize messages accompanying self registration process.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `selfRegistrationFormMessage` TEXT;",
				"ALTER TABLE `library` ADD COLUMN `selfRegistrationSuccessMessage` TEXT;",
			),
		),

		'selfreg_template' => array(
			'title' => 'Self Registration Template',
			'description' => 'Add self registration template for Millennium and Sierra.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `selfRegistrationTemplate` VARCHAR(25) default 'default';",
			),
		),

		'browse_category_default_view_mode' => array(
			'title' => 'Viewing Mode for Browse Categories',
			'description' => 'Default Setting for the Viewing Mode of Browse Categories',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `defaultBrowseMode` VARCHAR(25);",
				"ALTER TABLE `location` ADD COLUMN `defaultBrowseMode` VARCHAR(25);",
			),
		),
		'browse_category_ratings_mode' => array(
			'title' => 'Ratings Mode for Browse Categories',
			'description' => 'Setting for the Ratings Mode of Browse Categories',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `browseCategoryRatingsMode` VARCHAR(25);",
				"ALTER TABLE `location` ADD COLUMN `browseCategoryRatingsMode` VARCHAR(25);",
			),
		),
		'logo_linking' => array(
			'title' => 'Logo Linking',
			'description' => 'Allow Linking of Logo to the library home page.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `useHomeLinkForLogo` TINYINT(1) NULL DEFAULT '0';",
			),
		),

		'add_sms_indicator_to_phone' => array(
			'title' => 'Add SMS Indicator to Phone flag',
			'description' => 'Allow libraries to determine if a flag should be added to the primary phone number when someone subscribes to SMS messaging.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `addSMSIndicatorToPhone` TINYINT(1) NULL DEFAULT '0';",
			),
		),

		'external_materials_request' => array(
			'title' => 'Allow linking to an external materials request system',
			'description' => 'Allow libraries to link to an external materials request system rather than using the built in system',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `externalMaterialsRequestUrl` VARCHAR(255);",
			),
		),

		'default_library' => array(
			'title' => 'Default Library',
			'description' => 'Setup a default library for use when we do not get a defined subdomain',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `isDefault` TINYINT(1);",
			),
		),

		'show_place_hold_on_unavailable' => array(
			'title' => 'Show place hold button for unavailable records only',
			'description' => 'Setup showing place hold button for unavailable records only',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `showHoldButtonForUnavailableOnly` TINYINT(1) DEFAULT '0';",
			),
		),

		'linked_accounts_switch' => array(
			'title' => 'Enable Linked Accounts',
			'description' => 'Library configuration switch to enable users to have linked library accounts.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `allowLinkedAccounts` TINYINT(1) DEFAULT 1;",
			),
		),

		'horizontal_search_bar' => array(
			'title' => 'Enable Horizontal Search Bar',
			'description' => 'Library configuration switch to display a horizontal search bar instead of the default sidebar search box.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `horizontalSearchBar` TINYINT(1) DEFAULT 0;",
			),
		),

		'right_hand_sidebar' => array(
			'title' => 'Enable Right Hand Sidebar',
			'description' => 'Library configuration switch to display sidebars on the right of the page instead of the default left side.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `sideBarOnRight` TINYINT(1) DEFAULT 0;",
			),
		),

		'theme_name_length' => array(
			'title' => 'Increase length of theme name',
			'description' => 'Increase the length of theme name to allow for more nesting of themes.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` CHANGE COLUMN `themeName` `themeName` VARCHAR(60);",
			),
		),

		'ils_code_records_owned_length' => array(
			'title' => 'Increase length of ils code and records owned fields',
			'description' => 'Increase the length of ils code and records owned fields for Koha.',
			'continueOnError' => true,
			'sql' => array(
				"ALTER TABLE `library` CHANGE COLUMN `ilsCode` `ilsCode` VARCHAR(75);",
				"ALTER TABLE `location` CHANGE COLUMN `code` `code` VARCHAR(75);",
			),
		),

		'header_text' => array(
			'title' => 'Library and Location Header Text',
			'description' => 'Text that can be displayed in the header between the logo and log-in buttons for libraries and locations',
			'sql' => array(
				"ALTER TABLE `library` ADD `headerText` MEDIUMTEXT AFTER `showDisplayNameInHeader`",
				"ALTER TABLE `location` ADD `headerText` MEDIUMTEXT AFTER `showDisplayNameInHeader`",
			),
		),

		'main_location_switch' => array(
			'title' => 'Location Main Branch Setting',
			'description' => 'Switch that is turned on for a library\'s main branch location.',
			'sql' => array(
				"ALTER TABLE `location` ADD COLUMN `isMainBranch` TINYINT(1) DEFAULT 0 AFTER `showHoldButton`",
			),
		),

		'disable_auto_correction_of_searches' => array(
			'title' => 'Disable Automatic Search Corrections',
			'description' => 'Whether or not Pika will try to automatically replace search terms (similar to Google) .',
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `allowAutomaticSearchReplacements` TINYINT(1) DEFAULT 1",
			),
		),

		'index_subsets_of_overdrive' => array(
			'title' => 'Index Subsets of OverDrive',
			'description' => 'Allow libraries and locations to select subsets of overdrive to index .',
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN `includeOverDriveAdult` TINYINT(1) DEFAULT 1",
				"ALTER TABLE `library` ADD COLUMN `includeOverDriveTeen` TINYINT(1) DEFAULT 1",
				"ALTER TABLE `library` ADD COLUMN `includeOverDriveKids` TINYINT(1) DEFAULT 1",
				"ALTER TABLE `location` ADD COLUMN `includeOverDriveAdult` TINYINT(1) DEFAULT 1",
				"ALTER TABLE `location` ADD COLUMN `includeOverDriveTeen` TINYINT(1) DEFAULT 1",
				"ALTER TABLE `location` ADD COLUMN `includeOverDriveKids` TINYINT(1) DEFAULT 1",
			),
		),

		'public_lists_to_include'  => array(
			'title' => 'Public Lists to Include',
			'description' => 'Allow administrators to control what public lists are included within the scope',
			'sql' => array(
				"ALTER TABLE `library` ADD COLUMN publicListsToInclude TINYINT(1)",
				"ALTER TABLE `location` ADD COLUMN publicListsToInclude TINYINT(1)",
				"UPDATE library set publicListsToInclude = 0 where showFavorites = 0",
				"UPDATE library set publicListsToInclude = 1 where showFavorites = 1",
				"UPDATE location set publicListsToInclude = 0 where showFavorites = 0",
				"UPDATE location set publicListsToInclude = 1 where showFavorites = 1",
			),
		),


			'enable_archive' => array(
					'title' => 'Enable Archive for libraries',
					'description' => 'Add option to enable archives for individual libraries',
					'sql' => array(
						'ALTER TABLE library ADD COLUMN enableArchive TINYINT(1) DEFAULT 0',
					),
			),

			'remove_order_options' => array(
					'title' => 'Remove Order Options',
					'description' => 'Remove Unused Order Record Options from libraries table',
					'sql' => array(
							'ALTER TABLE library DROP COLUMN orderAccountingUnit',
							'ALTER TABLE library DROP COLUMN makeOrderRecordsAvailableToOtherLibraries',
					)
			),

			'remove_consortial_results_in_search' => array(
					'title' => 'Remove Consortial Results in Search',
					'description' => 'Remove Unused Consortial Results in at the end of search results from libraries table',
					'sql' => array(
							'ALTER TABLE library DROP COLUMN showMarmotResultsAtEndOfSearch',
					)
			),

			'remove_unused_enrichment_and_full_record_options' => array(
					'title' => 'Remove Unused Options in Enrichment and Full Record sections',
					'description' => 'Remove Show other editions option libraries table',
					'sql' => array(
							'ALTER TABLE library DROP COLUMN showOtherEditionsPopup',
							'ALTER TABLE library DROP COLUMN showTableOfContentsTab',
							'ALTER TABLE library DROP COLUMN showProspectorTitlesAsTab',
							'ALTER TABLE library DROP COLUMN showCopiesLineInHoldingsSummary',
					)
			),

			'remove_unused_location_options_2015_14_0' => array(
					'title' => 'Remove Unused Location Options',
					'description' => 'Remove Show other editions option libraries table',
					'sql' => array(
							'ALTER TABLE location DROP COLUMN extraLocationCodesToInclude',
					)
			),

			'show_library_hours_notice_on_account_pages' => array(
				'title' => 'Show Library Hours Notice On Account Pages',
				'description' => 'Add option to enable showing the library Hours Notice on account pages for individual libraries',
				'sql' => array(
					'ALTER TABLE `library` ADD COLUMN `showLibraryHoursNoticeOnAccountPages` TINYINT(1) DEFAULT 1 AFTER `showLibraryHoursAndLocationsLink`',
				),
			),

			'library_subject_display' => array(
					'title' => 'Library Subject Display Options',
					'description' => 'Add options to control which subjects are shown in full record view',
					'sql' => array(
							'ALTER TABLE `library` ADD COLUMN `showStandardSubjects` TINYINT(1) DEFAULT 1',
							'ALTER TABLE `library` ADD COLUMN `showBisacSubjects` TINYINT(1) DEFAULT 1',
							'ALTER TABLE `library` ADD COLUMN `showFastAddSubjects` TINYINT(1) DEFAULT 1',
					),
			),

			'library_max_fines_for_account_update' => array(
					'title' => 'Library Maximum fines to allow account updates',
					'description' => 'Add option to prevent patrons with high fines from updating their account',
					'sql' => array(
							'ALTER TABLE `library` ADD COLUMN `maxFinesToAllowAccountUpdates` FLOAT DEFAULT 10',
					),
			),

			'library_eds_integration' => array(
					'title' => 'Library EDS Integration',
					'description' => 'Setup information for connection to EDS APIs',
					'sql' => array(
							'ALTER TABLE `library` ADD COLUMN `edsApiProfile` VARCHAR(50)',
							'ALTER TABLE `library` ADD COLUMN `edsApiUsername` VARCHAR(50)',
							'ALTER TABLE `library` ADD COLUMN `edsApiPassword` VARCHAR(50)',
					),
			),
	);
}