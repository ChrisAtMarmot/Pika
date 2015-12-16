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

/** CORE APPLICATION CONTROLLER **/
require_once 'bootstrap.php';

global $timer;

//Do additional tasks that are only needed when running the full website
loadModuleActionId();
$timer->logTime("Loaded Module and Action Id");
spl_autoload_register('vufind_autoloader');
initializeSession();
$timer->logTime("Initialized session");

if (isset($_REQUEST['test_role'])){
	if ($_REQUEST['test_role'] == ''){
		setcookie('test_role', $_REQUEST['test_role'], time() - 1000, '/');
	}else{
		setcookie('test_role', $_REQUEST['test_role'], 0, '/');
	}
}

// Start Interface
$interface = new UInterface();
$timer->logTime('Create interface');
if (isset($configArray['Site']['responsiveLogo'])){
	$interface->assign('responsiveLogo', $configArray['Site']['responsiveLogo']);
}
if (isset($configArray['Site']['smallLogo'])){
	$interface->assign('smallLogo', $configArray['Site']['smallLogo']);
}
if (isset($configArray['Site']['largeLogo'])){
	$interface->assign('largeLogo', $configArray['Site']['largeLogo']);
}
//Set focus to the search box by default.
//$interface->assign('focusElementId', 'lookfor'); // No references in templates. TODO delete

//Set footer information
/** @var Location $locationSingleton */
global $locationSingleton;
getGitBranch();

$interface->loadDisplayOptions();
$timer->logTime('Loaded display options within interface');

require_once ROOT_DIR . '/sys/Analytics.php';
//Define tracking to be done
global $analytics;
global $active_ip;
$analytics = new Analytics($active_ip, $startTime);
$timer->logTime('Setup Analytics');

$googleAnalyticsId = isset($configArray['Analytics']['googleAnalyticsId']) ? $configArray['Analytics']['googleAnalyticsId'] : false;
$interface->assign('googleAnalyticsId', $googleAnalyticsId);
if ($googleAnalyticsId) {
	$googleAnalyticsDomainName = isset($configArray['Analytics']['domainName']) ? $configArray['Analytics']['domainName'] : strstr($_SERVER['SERVER_NAME'], '.');
	// check for a config setting, use that if found, otherwise grab domain name  but remove the first subdomain
	$interface->assign('googleAnalyticsDomainName', $googleAnalyticsDomainName);
}
global $library;

//Set System Message
if ($configArray['System']['systemMessage']){
	$interface->assign('systemMessage', $configArray['System']['systemMessage']);
}else if ($configArray['Catalog']['offline']){
	$interface->assign('systemMessage', "The circulation system is currently offline.  Access to account information and availability is limited.");
}else{
	if ($library && strlen($library->systemMessage) > 0){
		$interface->assign('systemMessage', $library->systemMessage);
	}
}

//Get the name of the active instance
//$inLibrary, is used to pre-select autologoout on place hold forms;
// to hide the remember me option on login pages;
// and to show the Location in the page footer
if ($locationSingleton->getIPLocation() != null){
	$interface->assign('inLibrary', true);
	$physicalLocation = $locationSingleton->getIPLocation()->displayName;
}else{
	$interface->assign('inLibrary', false);
	$physicalLocation = 'Home';
}
$interface->assign('physicalLocation', $physicalLocation);

$productionServer = $configArray['Site']['isProduction'];
$interface->assign('productionServer', $productionServer);

$location = $locationSingleton->getActiveLocation();

// Determine Module and Action
$module = (isset($_GET['module'])) ? $_GET['module'] : null;
$module = preg_replace('/[^\w]/', '', $module);
$action = (isset($_GET['action'])) ? $_GET['action'] : null;
$action = preg_replace('/[^\w]/', '', $action);

//Redirect some common spam components so they go to a valid place, and redirect old actions to new
if ($action == 'trackback'){
	$action = null;
}
if ($action == 'SimilarTitles'){
	$action = 'Home';
}
//Set these initially in case user login fails, we will need the module to be set.
$interface->assign('module', $module);
$interface->assign('action', $action);

global $solrScope;
global $scopeType;
$interface->assign('solrScope', "$solrScope - $scopeType");

//Set that the interface is a single column by default
$interface->assign('page_body_style', 'one_column');

$interface->assign('showFines', $configArray['Catalog']['showFines']);

$interface->assign('activeIp', Location::getActiveIp());

// Check system availability
$mode = checkAvailabilityMode();
if ($mode['online'] === false) {
	// Why are we offline?
	switch ($mode['level']) {
		// Forced Downtime
		case "unavailable":
			$interface->display($mode['template']);
			break;

			// Should never execute. checkAvailabilityMode() would
			//    need to know we are offline, but not why.
		default:
			$interface->display($mode['template']);
			break;
	}
	exit();
}
$timer->logTime('Checked availability mode');

//Check to see if we have a collection applied.
// TODO: collection url parameter doesn't look to be used for anything
global $defaultCollection;
if (isset($_GET['collection'])){
	$defaultCollection = $_GET['collection'];
	//Set a cookie so we don't have to transfer the ip from page to page.
	if ($defaultCollection == '' || $defaultCollection == 'all'){
		setcookie('collection', '', 0, '/');
		$defaultCollection = null;
	}else{
		setcookie('collection', $defaultCollection, 0, '/');
	}
}elseif (isset($_COOKIE['collection'])){
	$defaultCollection = $_COOKIE['collection'];
}else{
	//No collection has been set.
}
$timer->logTime('Check default collection');

// Proxy server settings
if (isset($configArray['Proxy']['host'])) {
	if (isset($configArray['Proxy']['port'])) {
		$proxy_server = $configArray['Proxy']['host'].":".$configArray['Proxy']['port'];
	} else {
		$proxy_server = $configArray['Proxy']['host'];
	}
	$proxy = array('http' => array('proxy' => "tcp://$proxy_server", 'request_fulluri' => true));
	stream_context_get_default($proxy);
}
$timer->logTime('Proxy server checks');

// Setup Translator
global $language;
global $serverName;
if (isset($_REQUEST['mylang'])) {
	$language = strip_tags($_REQUEST['mylang']);
	setcookie('language', $language, null, '/');
} else {
	$language = strip_tags((isset($_COOKIE['language'])) ? $_COOKIE['language'] : $configArray['Site']['language']);
}
/** @var Memcache $memCache */
$translator = $memCache->get("translator_{$serverName}_{$language}");
if ($translator == false || isset($_REQUEST['reloadTranslator'])){
	// Make sure language code is valid, reset to default if bad:
	$validLanguages = array_keys($configArray['Languages']);
	if (!in_array($language, $validLanguages)) {
		$language = $configArray['Site']['language'];
	}
	$translator = new I18N_Translator('lang', $language, $configArray['System']['missingTranslations']);
	$memCache->set("translator_{$serverName}_{$language}", $translator, 0, $configArray['Caching']['translator']);
	$timer->logTime('Translator setup');
}
$interface->setLanguage($language);

$deviceName = get_device_name();
$interface->assign('deviceName', $deviceName);

//Look for spammy searches and kill them
if (isset($_REQUEST['lookfor'])) {
	// Advanced Search with only the default search group (multiple search groups are named lookfor0, lookfor1, ... )
	// TODO: Actually the lookfor is inconsistent; reloading from results in an array : lookfor[]
	if (is_array($_REQUEST['lookfor'])) {
		foreach ($_REQUEST['lookfor'] as $i => $searchTerm) {
			if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
				PEAR_Singleton::raiseError("Sorry it looks like you are searching for a website, please rephrase your query.");
				$_REQUEST['lookfor'][$i] = '';
				$_GET['lookfor'][$i]     = '';
			}
			if (strlen($searchTerm) >= 256) {
				PEAR_Singleton::raiseError("Sorry your query is too long, please rephrase your query.");
				$_REQUEST['lookfor'][$i] = '';
				$_GET['lookfor'][$i]     = '';
			}
		}

	}
	// Basic Search
	else {
		$searchTerm = $_REQUEST['lookfor'];
		if (preg_match('/http:|mailto:|https:/i', $searchTerm)) {
			PEAR_Singleton::raiseError("Sorry it looks like you are searching for a website, please rephrase your query.");
			$_REQUEST['lookfor'] = '';
			$_GET['lookfor']     = '';
		}
		if (strlen($searchTerm) >= 256) {
			PEAR_Singleton::raiseError("Sorry your query is too long, please rephrase your query.");
			$_REQUEST['lookfor'] = '';
			$_GET['lookfor']     = '';
		}
	}
}

//Check to see if the user is already logged in
/** @var User $user */
global $user;
$user = UserAccount::isLoggedIn();
$timer->logTime('Check if user is logged in');

// Process Authentication, must be done here so we can redirect based on user information
// immediately after logging in.
$interface->assign('loggedIn', $user == false ? 'false' : 'true');
if ($user) {
	//The user is logged in
	$interface->assign('user', $user);
	//Create a cookie for the user's home branch so we can sort holdings even if they logout.
	//Cookie expires in 1 week.
	setcookie('home_location', $user->homeLocationId, time()+60*60*24*7, '/');
} else if (isset($_POST['username']) && isset($_POST['password']) && ($action != 'Account' && $module != 'AJAX')) {
	//The user is trying to log in
	$user = UserAccount::login();
	$timer->logTime('Login the user');
	if (PEAR_Singleton::isError($user)) {
		require_once ROOT_DIR . '/services/MyAccount/Login.php';
		$launchAction = new MyAccount_Login();
		$launchAction->launch($user->getMessage());
		exit();
	}elseif(!$user){
		require_once ROOT_DIR . '/services/MyAccount/Login.php';
		$launchAction = new MyAccount_Login();
		$launchAction->launch("Unknown error logging in");
		exit();
	}
	$interface->assign('user', $user);
	$interface->assign('loggedIn', $user == false ? 'false' : 'true');
	//Check to see if there is a followup module and if so, use that module and action for the next page load
	if (isset($_REQUEST['returnUrl'])) {
		$followupUrl =  $_REQUEST['returnUrl'];
		header("Location: " . $followupUrl);
		exit();
	}
	if ($user){
		if (isset($_REQUEST['followupModule']) && isset($_REQUEST['followupAction'])) {
			echo("Redirecting to followup location");
			$followupUrl =  $configArray['Site']['path'] . "/".  strip_tags($_REQUEST['followupModule']);
			if (!empty($_REQUEST['recordId'])) {
				$followupUrl .= "/" .  strip_tags($_REQUEST['recordId']);
			}
			$followupUrl .= "/" .  strip_tags($_REQUEST['followupAction']);
			if(isset($_REQUEST['comment'])) $followupUrl .= "?comment=" . urlencode($_REQUEST['comment']);
			header("Location: " . $followupUrl);
			exit();
		}
	}
	if (isset($_REQUEST['followup']) || isset($_REQUEST['followupModule'])){
		$module = isset($_REQUEST['followupModule']) ? $_REQUEST['followupModule'] : $configArray['Site']['defaultModule'];
		$action = isset($_REQUEST['followup']) ? $_REQUEST['followup'] : (isset($_REQUEST['followupAction']) ? $_REQUEST['followupAction'] : 'Home');
		if (isset($_REQUEST['id'])){
			$id = $_REQUEST['id'];
		}elseif (isset($_REQUEST['recordId'])){
			$id = $_REQUEST['recordId'];
		}
		if (isset($id)){
			$_REQUEST['id'] = $id;
		}
		$_REQUEST['module'] = $module;
		$_REQUEST['action'] = $action;
	}
}
$timer->logTime('User authentication');

//Load user data for the user as long as we aren't in the act of logging out.
if ($user && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'Logout')){
	loadUserData();
	$timer->logTime('Load user data');

	$interface->assign('pType', $user->patronType);
	$homeLibrary = Library::getLibraryForLocation($user->homeLocationId);
	if (isset($homeLibrary)){
		$interface->assign('homeLibrary', $homeLibrary->displayName);
	}
	$timer->logTime('Load patron pType');
}else{
	$interface->assign('pType', 'logged out');
	$interface->assign('homeLibrary', 'n/a');
}

//Setup analytics
if (!$analytics->isTrackingDisabled()){
	$analytics->setModule($module);
	$analytics->setAction($action);
	$analytics->setObjectId(isset($_REQUEST['id']) ? $_REQUEST['id'] : null);
	$analytics->setMethod(isset($_REQUEST['method']) ? $_REQUEST['method'] : null);
	$analytics->setLanguage($interface->getLanguage());
	$analytics->setTheme($interface->getPrimaryTheme());
	$analytics->setMobile($interface->isMobile() ? 1 : 0);
	$analytics->setDevice(get_device_name());
	$analytics->setPhysicalLocation($physicalLocation);
	if ($user){
		$analytics->setPatronType($user->patronType);
		$analytics->setHomeLocationId($user->homeLocationId);
	}else{
		$analytics->setPatronType('logged out');
		$analytics->setHomeLocationId(-1);
	}
}
$timer->logTime('Setup Analytics');

//Find a reasonable default location to go to
if ($module == null && $action == null){
	//We have no information about where to go, go to the default location from config
	$module = $configArray['Site']['defaultModule'];
	$action = 'Home';
}elseif ($action == null){
	$action = 'Home';
}
//Override MyAccount Home as needed
if ($module == 'MyAccount' && $action == 'Home' && $user){
	if ($user->getNumCheckedOutTotal() > 0){
		$action ='CheckedOut';
		header('Location:/MyAccount/CheckedOut');
		exit();
	}elseif ($user->getNumHoldsTotal() > 0){
		header('Location:/MyAccount/Holds');
		exit();
	}
}

$interface->assign('module', $module);
$interface->assign('action', $action);
$timer->logTime('Load module and action');

if (isset($_REQUEST['basicType'])){
	$interface->assign('basicSearchIndex', $_REQUEST['basicType']);
}else{
	$interface->assign('basicSearchIndex', 'Keyword');
}
$interface->assign('curFormatCategory', 'Everything');
if (isset($_REQUEST['filter'])){
	foreach ($_REQUEST['filter'] as $curFilter){
		$filterInfo = explode(":", $curFilter);
		if ($filterInfo[0] == 'format_category'){
			$curFormatCategory = str_replace('"', '', $filterInfo[1]);
			$interface->assign('curFormatCategory', $curFormatCategory);
			break;
		}
	}
}
if (isset($_REQUEST['genealogyType'])){
	$interface->assign('genealogySearchIndex', $_REQUEST['genealogyType']);
}else{
	$interface->assign('genealogySearchIndex', 'GenealogyKeyword');
}
if ($searchSource == 'genealogy') {
	$_REQUEST['type'] = isset($_REQUEST['genealogyType']) ? $_REQUEST['genealogyType'] : 'GenealogyKeyword';
}elseif ($searchSource == 'islandora'){
		$_REQUEST['type'] = isset($_REQUEST['islandoraType']) ? $_REQUEST['islandoraType']:  'IslandoraKeyword';
}else{
	$_REQUEST['type'] = isset($_REQUEST['basicType']) ? $_REQUEST['basicType'] : 'Keyword';
}
$interface->assign('searchSource', $searchSource);

//Determine if the top search box and breadcrumbs should be shown.  Not showing these
//Does have a slight performance advantage.
if ($action == "AJAX" || $action == "JSON"){
	$interface->assign('showTopSearchBox', 0);
	$interface->assign('showBreadcrumbs', 0);
}else{
	if (isset($configArray['FooterLists'])){
		$interface->assign('footerLists', $configArray['FooterLists']);
	}

	//Load basic search types for use in the interface.
	/** @var SearchObject_Solr|SearchObject_Base $searchObject */
	$searchObject = SearchObjectFactory::initSearchObject();
	$searchObject->init();
	$timer->logTime('Create Search Object');
	$basicSearchTypes = is_object($searchObject) ?    $searchObject->getBasicTypes() : array();
	$interface->assign('basicSearchTypes', $basicSearchTypes);

	// Set search results display mode in search-box //
	if ($searchObject->getView()) $interface->assign('displayMode', $searchObject->getView());

	//Load repeat search options
	require_once(ROOT_DIR . '/Drivers/marmot_inc/SearchSources.php');
	$searchSources = new SearchSources();
	$interface->assign('searchSources', $searchSources->getSearchSources());

	if (isset($configArray['Genealogy']) && $library->enableGenealogy){
		$genealogySearchObject = SearchObjectFactory::initSearchObject('Genealogy');
		$interface->assign('genealogySearchTypes', is_object($genealogySearchObject) ? $genealogySearchObject->getBasicTypes() : array());
	}

	if ($library->enableArchive){
		$islandoraSearchObject = SearchObjectFactory::initSearchObject('Islandora');
		$interface->assign('islandoraSearchTypes', is_object($islandoraSearchObject) ? $islandoraSearchObject->getBasicTypes() : array());
	}



	if (!($module == 'Search' && $action == 'Home')){
		/** @var SearchObject_Base $savedSearch */
		$savedSearch = $searchObject->loadLastSearch();
		//Load information about the search so we can display it in the search box
		if (!is_null($savedSearch)){
			$interface->assign('lookfor',             $savedSearch->displayQuery());
			$interface->assign('searchType',          $savedSearch->getSearchType());
			$searchIndex = $savedSearch->getSearchIndex();
			$interface->assign('searchIndex',         $searchIndex);
			$interface->assign('filterList', $savedSearch->getFilterList());
			$interface->assign('savedSearch', $savedSearch->isSavedSearch());
		}
		$timer->logTime('Load last search for redisplay');
	}

	if (($action =="Home" && ($module=="Search" || $module=="Summon" || $module=="WorldCat")) ||
	$action == "AJAX" || $action == "JSON"){
		$interface->assign('showTopSearchBox', 0);
		$interface->assign('showBreadcrumbs', 0);
	}else{
		$interface->assign('showTopSearchBox', 1);
		$interface->assign('showBreadcrumbs', 1);
		if (isset($library) && $library != false && $library->useHomeLinkInBreadcrumbs){
			$interface->assign('homeBreadcrumbLink', $library->homeLink);
		}else{
			$interface->assign('homeBreadcrumbLink', '/');
		}
		if (isset($library) && $library != false){
			$interface->assign('homeLinkText', $library->homeLinkText);
		}else{
			$interface->assign('homeLinkText', 'Home');
		}
	}
	//Load user list for book bag
	if ($user){
		$lists = $user->getLists();
		$timer->logTime('Get user lists for book cart');

		$userLists = array();
		foreach($lists as $current) {
			$userLists[] = array('id' => $current->id,
                    'title' => $current->title);
		}
		$interface->assign('userLists', $userLists);
	}
}

//Determine if we should include autoLogout Code
$ipLocation = $locationSingleton->getPhysicalLocation();
if (!empty($ipLocation) && !empty($library) && $ipLocation->libraryId != $library->libraryId){
	// This is to cover the case of being within one library but the user is browsing another library catalog
	// This will turn off the auto-log out and Internal IP functionality
	// (unless the user includes the opac parameter)
	$ipLocation = null;
}
$isOpac = $locationSingleton->getOpacStatus();

$onInternalIP = false;
$includeAutoLogoutCode = false;
$automaticTimeoutLength = 0;
$automaticTimeoutLengthLoggedOut = 0;
if (($isOpac || (!empty($ipLocation) && $ipLocation->getOpacStatus()) ) && !$configArray['Catalog']['offline']){
	// Make sure we don't have timeouts if we are offline (because it's super annoying when doing offline checkouts and holds)

	//$isOpac is set by URL parameter or cookie; pLocation->getOpacStatus() returns $opacStatus private variable which comes from the ip tables

	// Turn on the auto log out
	$onInternalIP = true;
	$includeAutoLogoutCode = true;
	$automaticTimeoutLength          = $locationSingleton::DEFAULT_AUTOLOGOUT_TIME;
	$automaticTimeoutLengthLoggedOut = $locationSingleton::DEFAULT_AUTOLOGOUT_TIME_LOGGED_OUT;

	// Only include auto logout code if we are not on the home page
	if ($module == 'Search' && $action == 'Home'){
		$includeAutoLogoutCode = false;
	}

	if ($user){
		// User has bypass AutoLog out setting turned on
		if ($user->bypassAutoLogout == 1){
		// The account setting profile template only presents this option to users that are staff
			$includeAutoLogoutCode = false;
		}
	}

	// Determine Time Out Lengths
	// If we know the branch, use the timeout settings from that branch
	if ($isOpac && $location) {
		$automaticTimeoutLength          = $location->automaticTimeoutLength;
		$automaticTimeoutLengthLoggedOut = $location->automaticTimeoutLengthLoggedOut;
	}
	// If we know the branch by iplocation, use the settings based on that location
	elseif ($ipLocation) {
		//TODO: ensure we are checking that URL is consistent with location, if not turn off
		// eg: browsing at fort lewis library from garfield county library
		$automaticTimeoutLength          = $ipLocation->automaticTimeoutLength;
		$automaticTimeoutLengthLoggedOut = $ipLocation->automaticTimeoutLengthLoggedOut;
	}
	// Otherwise, use the main branch's settings or the first location's settings
	elseif ($library) {
		$firstLocation = new Location();
		$firstLocation->libraryId = $library->libraryId;
		$firstLocation->orderBy('isMainBranch DESC');
		if ($firstLocation->find(true)) {
			// This finds either the main branch, or if there isn't one a location
			$automaticTimeoutLength          = $firstLocation->automaticTimeoutLength;
			$automaticTimeoutLengthLoggedOut = $firstLocation->automaticTimeoutLengthLoggedOut;
		}
	}
}
$interface->assign('automaticTimeoutLength', $automaticTimeoutLength);
$interface->assign('automaticTimeoutLengthLoggedOut', $automaticTimeoutLengthLoggedOut);
$interface->assign('onInternalIP', $onInternalIP);
$interface->assign('includeAutoLogoutCode', $includeAutoLogoutCode);

$timer->logTime('Check whether or not to include auto logout code');

// Process Login Followup
if (isset($_REQUEST['followup'])) {
	processFollowup();
	$timer->logTime('Process followup');
}

//If there is a hold_message, make sure it gets displayed.
/* //TODO deprecated, but there are still references in scripts that likely need removed
if (isset($_SESSION['hold_message'])) {
	$interface->assign('hold_message', formatHoldMessage($_SESSION['hold_message']));
	unset($_SESSION['hold_message']);
}elseif (isset($_SESSION['renew_message'])){ // this routine should be deprecated now. plb 2-2-2015
	$interface->assign('renew_message', formatRenewMessage($_SESSION['renew_message']));
}elseif (isset($_SESSION['checkout_message'])){
	global $logger;
	$logger->log("Found checkout message", PEAR_LOG_DEBUG);
	$checkoutMessage = $_SESSION['checkout_message'];
	unset($_SESSION['checkout_message']);
	$interface->assign('checkout_message', formatCheckoutMessage($checkoutMessage));
}*/

// Process Solr shard settings
processShards();
$timer->logTime('Process Shards');

// Call Action
// Note: ObjectEditor classes typically have the class name of DB_Object with an 's' added to the end.
//       This distinction prevents the DB_Object from being mistakenly called as the Action class.
if (is_readable("services/$module/$action.php")) {
	$actionFile = ROOT_DIR . "/services/$module/$action.php";
	require_once $actionFile;
	$moduleActionClass = "{$module}_{$action}";
	if (class_exists($action, false)) {
		/** @var Action $service */
		$service = new $action();
		$timer->logTime('Start launch of action');
		$service->launch();
		$timer->logTime('Finish launch of action');
	}elseif (class_exists($moduleActionClass, false)) {
		/** @var Action $service */
		$service = new $moduleActionClass();
		$timer->logTime('Start launch of action');
		$service->launch();
		$timer->logTime('Finish launch of action');
	} else {
		PEAR_Singleton::raiseError(new PEAR_Error('Unknown Action'));
	}
} else {
	$requestURI = $_SERVER['REQUEST_URI'];
	PEAR_Singleton::RaiseError(new PEAR_Error("Cannot Load Action '$action' for Module '$module' request '$requestURI'"));
}
$timer->logTime('Finished Index');
$timer->writeTimings();
//$analytics->finish();

function processFollowup(){
	global $configArray;

	switch($_REQUEST['followup']) {
		case 'SaveSearch':
			header("Location: {$configArray['Site']['path']}/".$_REQUEST['followupModule']."/".$_REQUEST['followupAction']."?".$_REQUEST['recordId']);
			die();
			break;
	}
}

/**
 * Process Solr-shard-related parameters and settings.
 *
 * @return void
 */
function processShards()
{
	global $configArray;
	global $interface;

	// If shards are not configured, give up now:
	if (!isset($configArray['IndexShards']) || empty($configArray['IndexShards'])) {
		return;
	}

	// If a shard selection list is found as an incoming parameter, we should save
	// it in the session for future reference:
	$useDefaultShards = false;
	if (array_key_exists('shard', $_REQUEST)) {
		if ($_REQUEST['shard'] == ''){
			$useDefaultShards = true;
		}else{
			$_SESSION['shards'] = $_REQUEST['shard'];
		}

	} else if (!array_key_exists('shards', $_SESSION)) {
		$useDefaultShards = true;
	}
	if ($useDefaultShards){
		// If no selection list was passed in, use the default...

		// If we have a default from the configuration, use that...
		if (isset($configArray['ShardPreferences']['defaultChecked'])
				&& !empty($configArray['ShardPreferences']['defaultChecked'])
				) {
			$checkedShards = $configArray['ShardPreferences']['defaultChecked'];
			$_SESSION['shards'] = is_array($checkedShards) ?
			$checkedShards : array($checkedShards);
		} else {
			// If no default is configured, use all shards...
			$_SESSION['shards'] = array_keys($configArray['IndexShards']);
		}
	}

	// If we are configured to display shard checkboxes, send a list of shards
	// to the interface, with keys being shard names and values being a boolean
	// value indicating whether or not the shard is currently selected.
	if (isset($configArray['ShardPreferences']['showCheckboxes'])
	&& $configArray['ShardPreferences']['showCheckboxes'] == true
	) {
		$shards = array();
		foreach ($configArray['IndexShards'] as $shardName => $shardAddress) {
			$shards[$shardName] = in_array($shardName, $_SESSION['shards']);
		}
		$interface->assign('shards', $shards);
	}
}

// Check for the various stages of functionality
function checkAvailabilityMode() {
	global $configArray;
	$mode = array();

	// If the config file 'available' flag is
	//    set we are forcing downtime.
	if (!$configArray['System']['available']) {
		//Unless the user is accessing from a maintainence IP address

		$isMaintenance = false;
		if (isset($configArray['System']['maintainenceIps'])){
			$activeIp = $_SERVER['REMOTE_ADDR'];
			$maintenanceIp =  $configArray['System']['maintainenceIps'];

			$maintenanceIps = explode(",", $maintenanceIp);
			foreach ($maintenanceIps as $curIp){
				if ($curIp == $activeIp){
					$isMaintenance = true;
					break;
				}
			}

		}

		if ($isMaintenance){
			global $interface;
			$interface->assign('systemMessage', 'You are currently accessing the site in maintenance mode. Remember to turn off maintenance when you are done.');
		}else{
			$mode['online']   = false;
			$mode['level']    = 'unavailable';
			$mode['template'] = 'unavailable.tpl';
			return $mode;
		}
	}

	// No problems? We are online then
	$mode['online'] = true;
	return $mode;
}

function getGitBranch(){
	global $interface;
	global $configArray;

	$gitName = $configArray['System']['gitVersionFile'];
	$branchName = 'Unknown';
	if ($gitName == 'HEAD'){
		$stringFromFile = file('../../.git/HEAD', FILE_USE_INCLUDE_PATH);
		$stringFromFile = $stringFromFile[0]; //get the string from the array
		$explodedString = explode("/", $stringFromFile); //seperate out by the "/" in the string
		$branchName = $explodedString[2]; //get the one that is always the branch name
	}else{
		$stringFromFile = file('../../.git/FETCH_HEAD', FILE_USE_INCLUDE_PATH);
		$stringFromFile = $stringFromFile[0]; //get the string from the array
		if (preg_match('/(.*?)\s+branch\s+\'(.*?)\'.*/', $stringFromFile, $matches)){
			$branchName = $matches[2] . ' (' . $matches[1] . ')'; //get the branch name
		}
	}
	$interface->assign('gitBranch', $branchName);
}
// Set up autoloader (needed for YAML)
function vufind_autoloader($class) {
	if (strpos($class, '.php') > 0){
		$class = substr($class, 0, strpos($class, '.php'));
	}
	$nameSpaceClass = str_replace('_', '/', $class) . '.php';
	try{
		if (file_exists('sys/' . $class . '.php')){
			$className = ROOT_DIR . '/sys/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('Drivers/' . $class . '.php')){
			$className = ROOT_DIR . '/Drivers/' . $class . '.php';
			require_once $className;
		}elseif (file_exists('services/MyAccount/lib/' . $class . '.php')){
			$className = ROOT_DIR . '/services/MyAccount/lib/' . $class . '.php';
			require_once $className;
		}else{
			require_once $nameSpaceClass;
		}
	}catch (Exception $e){
		PEAR_Singleton::raiseError("Error loading class $class");
	}
}

function loadModuleActionId(){
	//Cleanup method information so module, action, and id are set properly.
	//This ensures that we don't have to change the http-vufind.conf file when new types are added.
	//$dataObjects = array('Record', 'EContent', 'EditorialReview', 'Person');
	//$dataObjectsStr = implode('|', $dataObjects);
	//Deal with old path based urls by removing the leading path.
	$requestURI = $_SERVER['REQUEST_URI'];
	$requestURI = preg_replace("/^\/?vufind\//", "", $requestURI);
	if (preg_match("/(MyAccount)\/([^\/?]+)\/([^\/?]+)(\?.+)?/", $requestURI, $matches)){
		$_GET['module'] = $matches[1];
		$_GET['id'] = $matches[3];
		$_GET['action'] = $matches[2];
		$_REQUEST['module'] = $matches[1];
		$_REQUEST['id'] = $matches[3];
		$_REQUEST['action'] = $matches[2];
	}elseif (preg_match("/(MyAccount)\/([^\/?]+)(\?.+)?/", $requestURI, $matches)){
		$_GET['module'] = $matches[1];
		$_GET['action'] = $matches[2];
		$_REQUEST['id'] = '';
		$_REQUEST['module'] = $matches[1];
		$_REQUEST['action'] = $matches[2];
		$_REQUEST['id'] = '';
	}elseif (preg_match('/\/Archive\/((?:[\\w\\d:]|%3A)+)\/([^\/?]+)/', $requestURI, $matches)){
		$_GET['module'] = 'Archive';
		$_GET['id'] = $matches[1];
		$_GET['action'] = $matches[2];
		$_REQUEST['module'] = 'Archive';
		$_REQUEST['id'] = $matches[1];
		$_REQUEST['action'] = $matches[2];

	//Redirect things /Record/.b3246786/Home to the proper action
	//Also things like /OverDrive/84876507-043b-b3ce-2930-91af93d2a4f0/Home
	}elseif (preg_match("/([^\/?]+)\/((?:\.b|MWT|rbdZ)?[\da-fA-F-]+x?)\/([^\/?]+)/", $requestURI, $matches)){
		$_GET['module'] = $matches[1];
		$_GET['id'] = $matches[2];
		$_GET['action'] = $matches[3];
		$_REQUEST['module'] = $matches[1];
		$_REQUEST['id'] = $matches[2];
		$_REQUEST['action'] = $matches[3];

	//Redirect things /Record/.b3246786 to the proper action
	}elseif (preg_match("/([^\/?]+)\/((?:\.b|MWT|rbdZ)?[\da-fA-F-]+x?)(?:\?|\/?$)/", $requestURI, $matches)){
		$_GET['module'] = $matches[1];
		$_GET['id'] = $matches[2];
		$_GET['action'] = 'Home';
		$_REQUEST['module'] = $matches[1];
		$_REQUEST['id'] = $matches[2];
		$_REQUEST['action'] = 'Home';
	}elseif (preg_match("/([^\/?]+)\/([^\/?]+)/", $requestURI, $matches)){
		$_GET['module'] = $matches[1];
		$_GET['action'] = $matches[2];
		$_REQUEST['module'] = $matches[1];
		$_REQUEST['action'] = $matches[2];
	}

	//Correct some old actions
	if (isset($_GET['action'])) {
		if ($_GET['action'] == 'OverdriveHolds') {
			$_GET['action'] = 'Holds';
			$_REQUEST['action'] = 'Holds';
		} else {
			if ($_GET['action'] == 'OverdriveCheckedOut') {
				$_GET['action'] = 'CheckedOut';
				$_REQUEST['action'] = 'CheckedOut';
			}
		}
	}

	global $activeRecordProfile;
	//Check to see if the module is a profile
	if (isset($_REQUEST['module'])){
		/** @var IndexingProfile[] */
		/** @var IndexingProfile $profile */
		global $indexingProfiles;
		foreach ($indexingProfiles as $profile) {
			if ($profile->recordUrlComponent == $_REQUEST['module']) {
				$newId = $profile->name . ':' . $_REQUEST['id'];;
				$_GET['id'] = $newId;
				$_REQUEST['id'] = $newId;
				if (!file_exists(ROOT_DIR . '/services/' . $_REQUEST['module'])){
					$_GET['module'] = 'Record';
					$_REQUEST['module'] = 'Record';
				}
				$activeRecordProfile = $profile;
				break;
			}
		}
	}
}

function initializeSession(){
	global $configArray;
	global $timer;
	// Initiate Session State
	$session_type = $configArray['Session']['type'];
	$session_lifetime = $configArray['Session']['lifetime'];
	$session_rememberMeLifetime = $configArray['Session']['rememberMeLifetime'];
	register_shutdown_function('session_write_close');
	$sessionClass = ROOT_DIR . '/sys/' . $session_type . '.php';
	require_once $sessionClass;
	if (class_exists($session_type)) {
		/** @var SessionInterface $session */
		$session = new $session_type();
		$session->init($session_lifetime, $session_rememberMeLifetime);
	}
	$timer->logTime('Session initialization ' . $session_type);
}

function loadUserData(){
	global $user;
	global $interface;
	global $timer;

	//Assign User information to the interface
	if (!PEAR_Singleton::isError($user)) {
		$interface->assign('profile', $user); // TODO Phase out in favor of $user below; $profile should be for Account Pages for specified linked Accounts
		$interface->assign('user', $user); //TODO $user is also assigned to User on line 237
	}

	//Load a list of lists
	$lists = array();
	require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
	$tmpList = new UserList();
	$tmpList->user_id = $user->id;
	$tmpList->deleted = 0;
	$tmpList->orderBy("title ASC");
	$tmpList->find();
	if ($tmpList->N > 0){
		while ($tmpList->fetch()){
			$lists[$tmpList->id] = array(
					'name' => $tmpList->title,
					'url' => '/MyAccount/MyList/' .$tmpList->id ,
					'id' => $tmpList->id,
					'numTitles' => $tmpList->num_titles()
			);
		}
	}
	$interface->assign('lists', $lists);
	$timer->logTime("Load Lists");

	// Get My Tags
	$tagList = $user->getTags();
	$interface->assign('tagList', $tagList);
	$timer->logTime("Load Tags");

	if ($user->hasRole('opacAdmin') || $user->hasRole('libraryAdmin') || $user->hasRole('cataloging')){
		$variable = new Variable();
		$variable->name= 'lastFullReindexFinish';
		if ($variable->find(true)){
			$interface->assign('lastFullReindexFinish', date('m-d-Y H:i:s', $variable->value));
		}else{
			$interface->assign('lastFullReindexFinish', 'Unknown');
		}
		$variable = new Variable();
		$variable->name= 'lastPartialReindexFinish';
		if ($variable->find(true)){
			$interface->assign('lastPartialReindexFinish', date('m-d-Y H:i:s', $variable->value));
		}else{
			$interface->assign('lastPartialReindexFinish', 'Unknown');
		}
		$timer->logTime("Load Information about Index status");
	}
}