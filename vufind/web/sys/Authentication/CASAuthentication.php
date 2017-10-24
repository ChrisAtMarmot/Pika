<?php
require_once 'Authentication.php';
require_once ROOT_DIR . '/CatalogConnection.php';

class CASAuthentication implements Authentication {
	static $clientInitialized = false;


	public function __construct($additionalInfo) {

	}

	public function authenticate($validatedViaSSO){
		$this->initializeCASClient();

		try{
			global $logger;
			$logger->log("Forcing CAS authentication", PEAR_LOG_DEBUG);
			$isValidated = phpCAS::forceAuthentication();
			if ($isValidated){
				$userAttributes = phpCAS::getAttributes();
				//TODO: If we use other CAS systems we will need a configuration option to store which
				//attribute the id is in
				$userId = $userAttributes['flcid'];
				return $userId;
			}else{
				return false;
			}
		}catch (CAS_AuthenticationException $e){
			global $logger;
			$logger->log("Error authenticating in CAS $e", PEAR_LOG_ERR);
			$isValidated = false;
		}

		return $isValidated;
	}

	/**
	 * @param $username       string Should be null for CAS
	 * @param $password       string Should be null for CAS
	 * @param $parentAccount  User|null
	 * @param $validatedViaSSO boolean
	 * @return bool|PEAR_Error|string return false if the user cannot authenticate, the barcode if they can, and an error if configuration is incorrect
	 */
	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO) {
		if($username == '' || $password == ''){
			$this->initializeCASClient();

			try{
				global $logger;
				$logger->log("Checking CAS Authentication", PEAR_LOG_DEBUG);
				$isValidated = phpCAS::checkAuthentication();
			}catch (CAS_AuthenticationException $e){
				global $logger;
				$logger->log("Error validating account in CAS $e", PEAR_LOG_ERR);
				$isValidated = false;
			}

			if ($isValidated){
				//We have a valid user within CAS.  Return the user id
				$userAttributes = phpCAS::getAttributes();
				//TODO: If we use other CAS systems we will need a configuration option to store which
				//attribute the id is in
				$userId = $userAttributes['flcid'];
				return $userId;
			}else{
				return false;
			}
		} else {
			return new PEAR_Error('Should not pass username and password to account validation for CAS');
		}
	}

	public function logout() {
		//global $logger;
		$this->initializeCASClient();
		//$logger->log('Logging the user out from CAS', PEAR_LOG_INFO);
		phpCAS::logout();
	}

	protected function initializeCASClient() {
		if (!CASAuthentication::$clientInitialized) {
			require_once ROOT_DIR . '/CAS-1.3.4/CAS.php';

			global $library;
			global $configArray;
			if ($configArray['System']['debug']) {
				phpCAS::setDebug();
				phpCAS::setVerbose(true);
			}

			global $logger;
			$logger->log("Initializing CAS Client", PEAR_LOG_DEBUG);

			phpCAS::client(CAS_VERSION_3_0, $library->casHost, (int)$library->casPort, $library->casContext);

			phpCAS::setNoCasServerValidation();

			CASAuthentication::$clientInitialized = true;
		}
	}
}