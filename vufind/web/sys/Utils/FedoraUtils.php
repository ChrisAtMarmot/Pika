<?php

/**
 * Description goes here
 *
 * @category VuFind-Plus-2014
 * @author Mark Noble <mark@marmot.org>
 * Date: 1/31/2016
 * Time: 7:58 PM
 */
//Include code we need to use Tuque without Drupal
require_once(ROOT_DIR . '/sys/tuque/Cache.php');
require_once(ROOT_DIR . '/sys/tuque/FedoraApi.php');
require_once(ROOT_DIR . '/sys/tuque/FedoraApiSerializer.php');
require_once(ROOT_DIR . '/sys/tuque/Object.php');
require_once(ROOT_DIR . '/sys/tuque/HttpConnection.php');
require_once(ROOT_DIR . '/sys/tuque/Repository.php');
require_once(ROOT_DIR . '/sys/tuque/RepositoryConnection.php');
class FedoraUtils {
	/** @var FedoraRepository */
	private $repository;
	/** @var FedoraApi */
	private $api;
	/** @var  FedoraUtils */
	private static $singleton;

	/**
	 * @return FedoraUtils
	 */
	public static function getInstance(){
		if (FedoraUtils::$singleton == null){
			FedoraUtils::$singleton = new FedoraUtils();
			global $timer;
			$timer->logTime('Setup Fedora Utils');
		}
		return FedoraUtils::$singleton;
	}

	private function __construct(){
		global $configArray;
		try {
			$serializer = new FedoraApiSerializer();
			$cache = new SimpleCache();
			$fedoraUrl = $configArray['Islandora']['fedoraUrl'];
			$fedoraPassword = $configArray['Islandora']['fedoraPassword'];
			$fedoraUser = $configArray['Islandora']['fedoraUsername'];
			$connection = new RepositoryConnection($fedoraUrl, $fedoraUser, $fedoraPassword);
			$connection->verifyPeer = false;
			$this->api = new FedoraApi($connection, $serializer);
			$this->repository = new FedoraRepository($this->api, $cache);
		}catch (Exception $e){
			global $logger;
			$logger->log("Error connecting to repository $e", PEAR_LOG_ERR);
		}
	}

	/** AbstractObject */
	public function getObject($pid) {
		//Clean up the pid in case we get extra data
		$pid = str_replace('info:fedora/', '', $pid);
		$object = null;
		try{
			$object = $this->repository->getObject($pid);
		}catch (Exception $e){
			$object = null;
		}
		return $object;
	}

	/** AbstractObject */
	public function getObjectLabel($pid) {
		try{
			$object = $this->repository->getObject($pid);
		}catch (Exception $e){
			//global $logger;
			//$logger->log("Could not find object $pid due to exception $e", PEAR_LOG_WARNING);
			$object = null;
		}

		if ($object == null){
			return 'Invalid Object';
		}else{
			if (empty($object->label)){
				return $pid;
			}else{
				return $object->label;
			}
		}
	}

	/**
	 * @param AbstractObject $archiveObject
	 * @param string $size
	 * @param string $defaultType
	 * @return string
	 */
	function getObjectImageUrl($archiveObject, $size = 'small', $defaultType = null){
		global $configArray;
		$objectUrl = $configArray['Islandora']['objectUrl'];

		if ($size == 'thumbnail'){
			if ($archiveObject && $archiveObject->getDatastream('TN') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else if ($archiveObject && $archiveObject->getDatastream('SC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/SC/view';
			}else {
				//return a placeholder
				return $this->getPlaceholderImage($defaultType);
			}
		}elseif ($size == 'small'){
			if ($archiveObject && $archiveObject->getDatastream('SC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/SC/view';
			}else if ($archiveObject && $archiveObject->getDatastream('TN') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				//return a placeholder
				return $this->getPlaceholderImage($defaultType);
			}
		}elseif ($size == 'medium'){
			if ($archiveObject && $archiveObject->getDatastream('MC') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/MC/view';
			}else if ($archiveObject && $archiveObject->getDatastream('MEDIUM_SIZE') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/MEDIUM_SIZE/view';
			}else if ($archiveObject && $archiveObject->getDatastream('TN') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				return $this->getObjectImageUrl($archiveObject, 'small', $defaultType);
			}
		}if ($size == 'large'){
			if ($archiveObject && $archiveObject->getDatastream('JPG') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/JPG/view';
			}elseif ($archiveObject && $archiveObject->getDatastream('LC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/LC/view';
			}else{
				return $this->getObjectImageUrl($archiveObject, 'medium', $defaultType);
			}
		}
	}

	public function getPlaceholderImage($defaultType) {
		global $configArray;
		if ($defaultType == 'personCModel' || $defaultType == 'person') {
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/people.png';
		}elseif ($defaultType == 'placeCModel' || $defaultType == 'place'){
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/places.png';
		}elseif ($defaultType == 'eventCModel' || $defaultType == 'event'){
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/events.png';
		}else{
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/History.png';
		}
	}

	/**
	 * Retrieves MODS data for the specified object
	 *
	 * @param FedoraObject $archiveObject
	 *
	 * @return SimpleXMLElement
	 */
	public function getModsData($archiveObject){
		global $timer;
		if (array_key_exists($archiveObject->id, $this->modsCache)) {
			$modsData = $this->modsCache[$archiveObject->id];
		}else{
			$modsStream = $archiveObject->getDatastream('MODS');
			if ($modsStream){
				$timer->logTime('Retrieved mods stream from fedora ' . $archiveObject->id);
				$modsData = $modsStream->content;
				/*if (strlen($modsStream->content) > 0){
					$modsData = simplexml_load_file($modsStream->content, 'SimpleXmlElement', 0, 'http://www.loc.gov/mods/v3', false);
					$timer->logTime('Parsed as xml with simple xml');
				}*/
				$timer->logTime('Retrieved mods stream content from fedora ' . $archiveObject->id);
				$this->modsCache[$archiveObject->id] = $modsData;
			}else{
				return null;
			}
		}
		return $modsData;
	}

	private $modsCache = array();

	public function doSparqlQuery($query){
		$results = $this->repository->ri->sparqlQuery($query);
		return $results;
	}

	/**
	 * @param FedoraObject $archiveObject
	 * @return bool
	 */
	public function isObjectValidForPika($archiveObject){
		/** @var Memcache $memCache */
		global $memCache;
		global $timer;
		$isValid = $memCache->get('islandora_object_valid_in_pika_' . $archiveObject->id);
		if ($isValid !== FALSE){
			return $isValid == 1;
		}else{
			$mods = FedoraUtils::getInstance()->getModsData($archiveObject);
			if (strlen($mods) > 0) {
				$includeInPika = $this->getModsValue('includeInPika', 'marmot', $mods);
				$okToAdd = $includeInPika != 'no';
			} else {
				//If we don't get mods, exclude from the display
				$okToAdd = false;
			}
			$timer->logTime("Checked if {$archiveObject->id} is valid to include");
			global $configArray;
			$memCache->set('islandora_object_valid_in_pika_' . $archiveObject->id, $okToAdd ? 1 : 0, 0, $configArray['Caching']['islandora_object_valid']);
			return $okToAdd;
		}
	}

	/**
	 * @param string $pid
	 * @return bool
	 */
	public function isPidValidForPika($pid){
		/** @var Memcache $memCache */
		global $memCache;
		$isValid = $memCache->get('islandora_object_valid_in_pika_' . $pid);
		if ($isValid !== FALSE){
			return $isValid == 1;
		}else{
			$archiveObject = $this->getObject($pid);
			if ($archiveObject != null) {
				return $this->isObjectValidForPika($archiveObject);
			}else{
				global $configArray;
				$memCache->set('islandora_object_valid_in_pika_' . $pid, 0, $configArray['Caching']['islandora_object_valid']);
				return false;
			}

		}
	}

	public function getModsAttribute($attribute, $snippet){
		if (preg_match("/$attribute\\s*=\\s*\"(.*?)\"/s", $snippet, $matches)){
			return $matches[1];
		}
	}

	/**
	 * Gets a single valued field from the MODS data using regular expressions
	 *
	 * @param $tag
	 * @param $namespace
	 * @param $snippet - The snippet of XML to load from
	 *
	 * @return string
	 */
	public function getModsValue($tag, $namespace = null, $snippet = null){
		if ($namespace == null){
			if (preg_match("/<$tag.*?>(.*?)<\\/$tag>/s", $snippet, $matches)){
				return $matches[1];
			}
		}else{
			if (preg_match("/<(?:$namespace:)?$tag.*?>(.*?)<\\/(?:$namespace:)?$tag>/s", $snippet, $matches)){
				return $matches[1];
			}
		}
		return '';
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
		if ($namespace == null){
			if (preg_match_all("/<$tag.*?>(.*?)<\\/$tag>/s", $snippet, $matches, PREG_PATTERN_ORDER)){
				return $includeTag ? $matches[0] : $matches[1];
			}
		}else{
			if (preg_match_all("/<(?:$namespace:)?$tag.*?>(.*?)<\\/(?:$namespace:)?{$tag}>/s", $snippet, $matches, PREG_PATTERN_ORDER)){
				return $includeTag ? $matches[0] : $matches[1];
			}
		}
		return array();
	}
}