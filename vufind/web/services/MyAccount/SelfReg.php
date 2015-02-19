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

require_once ROOT_DIR . "/Action.php";
require_once ROOT_DIR . '/recaptcha/recaptchalib.php';

class SelfReg extends Action {
	protected $catalog;

	function __construct() {
		global $configArray;
		// Connect to Catalog
		$this->catalog = new CatalogConnection($configArray['Catalog']['driver']);
	}

	function launch($msg = null) {
		global $interface;
		global $configArray;

		if (isset($_REQUEST['submit'])) {

			$privatekey = $configArray['ReCaptcha']['privateKey'];
			$resp = recaptcha_check_answer ($privatekey,
				$_SERVER["REMOTE_ADDR"],
				$_POST["recaptcha_challenge_field"],
				$_POST["recaptcha_response_field"]);

			if (!$resp->is_valid) {
				$interface->assign('captchaMessage', 'The CAPTCHA response was incorrect, please try again.');
			} else {

				//Submit the form to ILS
				$result = $this->catalog->selfRegister();
				$interface->assign('selfRegResult', $result);
			}
		}

		/** @var  CatalogConnection $catalog */
		$catalog = new CatalogConnection($configArray['Catalog']['driver']);
		$selfRegFields = $catalog->getSelfRegistrationFields();
		$interface->assign('submitUrl', $configArray['Site']['path'] . '/MyAccount/SelfReg');
		$interface->assign('structure', $selfRegFields);
		$interface->assign('saveButtonText', 'Register');

		// Set up captcha to limit spam self registrations
		if (isset($configArray['ReCaptcha']['publicKey'])) {
			$recaptchaPublicKey = $configArray['ReCaptcha']['publicKey']; // you got this from the signup page
			$captchaCode        = recaptcha_get_html($recaptchaPublicKey);
			$interface->assign('captcha', $captchaCode);
		}

		$fieldsForm = $interface->fetch('DataObjectUtil/objectEditForm.tpl');
		$interface->assign('selfRegForm', $fieldsForm);

		$interface->setTemplate('selfReg.tpl');
		$interface->assign('sidebar', 'MyAccount/account-sidebar.tpl');
		$interface->display('layout.tpl');

	}
}
