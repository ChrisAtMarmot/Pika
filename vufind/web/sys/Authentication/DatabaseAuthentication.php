<?php
require_once 'Authentication.php';

class DatabaseAuthentication implements Authentication {
	public function __construct($additionalInfo) {

	}

	public function authenticate() {
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