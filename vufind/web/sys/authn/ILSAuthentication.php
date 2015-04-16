<?php
require_once 'Authentication.php';
require_once ROOT_DIR . '/CatalogConnection.php';

class ILSAuthentication implements Authentication {
	private $username;
	private $password;
	public function authenticate(){
		global $user;

		//Check to see if the username and password are provided
		if (!array_key_exists('username', $_REQUEST) && !array_key_exists('password', $_REQUEST)){
			//If not, check to see if we have a valid user already authenticated
			if ($user){
				return $user;
			}
		}
		$this->username = $_REQUEST['username'];
		$this->password = $_REQUEST['password'];

		if($this->username == '' || $this->password == ''){
			$user = new PEAR_Error('authentication_error_blank');
		} else {
			// Connect to Database
			$catalog = CatalogFactory::getCatalogConnectionInstance();;

			if ($catalog->status) {
				$patron = $catalog->patronLogin($this->username, $this->password);
				if ($patron && !PEAR_Singleton::isError($patron)) {
					$user = $this->processILSUser($patron);

					//Also call getPatronProfile to update extra fields
					$catalog = CatalogFactory::getCatalogConnectionInstance();;
					$catalog->getMyProfile($user);
				} else {
					$user = new PEAR_Error('authentication_error_invalid');
				}
			} else {
				$user = new PEAR_Error('authentication_error_technical');
			}
		}
		return $user;
	}

	public function validateAccount($username, $password) {
		return $this->authenticate();
	}

	private function processILSUser($info){
		require_once ROOT_DIR . "/services/MyResearch/lib/User.php";

		$user = new User();
		//Marmot make sure we are using the username which is the
		//unique patron ID in Millennium.
		$user->username = $info['username'];
		if ($user->find(true)) {
			$insert = false;
		} else {
			//Do one more check based on the patron barcode in case we are converting
			//Clear username temporarily
			$user->username = null;
			global $configArray;
			$barcodeProperty = $configArray['Catalog']['barcodeProperty'];
			$user->$barcodeProperty = $info[$barcodeProperty];
			if ($user->find(true)){
				$insert = false;
			}else{
				$insert = true;
			}
			//Restore username
			$user->username = $info['username'];
		}

		$user->password = $info['cat_password'];
		$user->firstname    = $info['firstname']    == null ? " " : $info['firstname'];
		$user->lastname     = $info['lastname']     == null ? " " : $info['lastname'];
		$user->cat_username = $info['cat_username'] == null ? " " : $info['cat_username'];
		$user->cat_password = $info['cat_password'] == null ? " " : $info['cat_password'];
		$user->email        = $info['email']        == null ? " " : $info['email'];
		$user->major        = $info['major']        == null ? " " : $info['major'];
		$user->college      = $info['college']      == null ? " " : $info['college'];
		$user->patronType   = $info['patronType']   == null ? " " : $info['patronType'];
		$user->web_note     = $info['web_note']     == null ? " " : $info['web_note'];

		if (empty($user->displayName)){
			if (strlen($user->firstname) >= 1){
				$user->displayName = substr($user->firstname, 0, 1) . '. ' . $user->lastname;
			}else{
				$user->displayName = $user->lastname;
			}
	}

		if ($insert) {
			$user->created = date('Y-m-d');
			$user->insert();
		} else {
			$user->update();
		}

		return $user;
	}
}
?>
