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
		try{
			return $this->repository->getObject($pid);
		}catch (Exception $e){
			return null;
		}
	}

	/** AbstractObject */
	public function getObjectLabel($pid) {
		$object = $this->repository->getObject($pid);
		if ($object == null){
			return 'Invalid Object';
		}else{
			return $object->label;
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
		if ($size == 'small'){
			if ($archiveObject->getDatastream('SC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/SC/view';
			}else if ($archiveObject->getDatastream('TN') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				//return a placeholder
				return $this->getPlaceholderImage($defaultType);
			}

		}elseif ($size == 'medium'){
			if ($archiveObject->getDatastream('MC') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/MC/view';
			}else if ($archiveObject->getDatastream('TN') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/TN/view';
			}else{
				return $this->getPlaceholderImage($defaultType);
			}
		}if ($size == 'large'){
			if ($archiveObject->getDatastream('JPG') != null) {
				return $objectUrl . '/' . $archiveObject->id . '/datastream/JPG/view';
			}elseif ($archiveObject->getDatastream('LC') != null){
				return $objectUrl . '/' . $archiveObject->id . '/datastream/LC/view';
			}else{
				return $this->getPlaceholderImage($defaultType);
			}
		}
	}

	public function getPlaceholderImage($defaultType) {
		global $configArray;
		if ($defaultType == 'personCModel') {
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/people.png';
		}elseif ($defaultType == 'placeCModel'){
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/places.png';
		}elseif ($defaultType == 'eventCModel'){
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/events.png';
		}else{
			return $configArray['Site']['path'] . '/interface/themes/responsive/images/History.png';
		}
	}
}