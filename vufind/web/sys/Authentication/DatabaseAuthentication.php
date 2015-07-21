<?php
require_once 'Authentication.php';
require_once ROOT_DIR . '/services/MyResearch/lib/User.php';

class DatabaseAuthentication implements Authentication {

	public function authenticate($additionalInfo) {
		$username = $_POST['username'];
		$password = $_POST['password'];
		if (($username == '') || ($password == '')) {
			$user = new PEAR_Error('authentication_error_blank');
		} else {
			$user = new User();
			$user->username = $username;
			$user->password = $password;
			if (!$user->find(true)) {
				$user = new PEAR_Error('authentication_error_invalid');
			}
		}
		return $user;
	}
}