<?php
interface Authentication {
	public function __construct($additionalInfo);

	/**
	 * Authenticate the user in the system
	 *
	 * @return mixed
	 */
	public function authenticate();

	/**
	 * @param $username       string
	 * @param $password       string
	 * @param $parentAccount  User|null
	 * @param $validatedViaSSO boolean
	 * @return bool|PEAR_Error|string
	 */
	public function validateAccount($username, $password, $parentAccount, $validatedViaSSO);
}