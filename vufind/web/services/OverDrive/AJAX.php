<?php
require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/sys/Proxy_Request.php';

global $configArray;

class OverDrive_AJAX extends Action {

	function launch() {
		global $analytics;
		$analytics->disableTracking();
		$method = $_GET['method'];
		if (in_array($method, array('CheckoutOverDriveItem', 'PlaceOverDriveHold', 'CancelOverDriveHold', 'GetOverDriveHoldPrompts', 'ReturnOverDriveItem', 'SelectOverDriveDownloadFormat', 'GetDownloadLink', 'GetOverDriveCheckoutPrompts'))){
			header('Content-type: text/plain');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo $this->$method();
		}else{
			header ('Content-type: text/xml');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

			$xmlResponse = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
			$xmlResponse .= "<AJAXResponse>\n";
			if (is_callable(array($this, $_GET['method']))) {
				$xmlResponse .= $this->$_GET['method']();
			} else {
				$xmlResponse .= '<Error>Invalid Method</Error>';
			}
			$xmlResponse .= '</AJAXResponse>';

			echo $xmlResponse;
		}
	}

	function PlaceOverDriveHold(){
		global $user;

		$overDriveId = $_REQUEST['overDriveId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron){
				if (isset($_REQUEST['overdriveEmail'])){
					if ($_REQUEST['overdriveEmail'] != $patron->overdriveEmail){
						$patron->overdriveEmail = $_REQUEST['overdriveEmail'];
						$patron->update();
					}
				}
				if (isset($_REQUEST['promptForOverdriveEmail'])){
					$patron->promptForOverdriveEmail = $_REQUEST['promptForOverdriveEmail'];
					$patron->update();
				}

				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$holdMessage = $driver->placeOverDriveHold($overDriveId, $patron);
				return json_encode($holdMessage);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to place holds for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to place a hold.'));
		}
	}

	function CheckoutOverDriveItem(){
		global $user;
		$overDriveId = $_REQUEST['overDriveId'];
		//global $logger;
		//$logger->log("Lending period = $lendingPeriod", PEAR_LOG_INFO);
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->checkoutOverDriveItem($overDriveId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return json_encode($result);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to checkout titles for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to checkout an item.'));
		}
	}

	function ReturnOverDriveItem(){
		global $user;
		$overDriveId = $_REQUEST['overDriveId'];
		$transactionId = $_REQUEST['transactionId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->returnOverDriveItem($overDriveId, $transactionId, $user);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return json_encode($result);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to return titles for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to return an item.'));
		}
	}

	function SelectOverDriveDownloadFormat(){
		global $user;
		$overDriveId = $_REQUEST['overDriveId'];
		$formatId = $_REQUEST['formatId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->selectOverDriveDownloadFormat($overDriveId, $formatId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return json_encode($result);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to download titles for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to download a title.'));
		}
	}

	function GetDownloadLink(){
		global $user;
		$overDriveId = $_REQUEST['overDriveId'];
		$formatId = $_REQUEST['formatId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->getDownloadLink($overDriveId, $formatId, $patron);
				//$logger->log("Checkout result = $result", PEAR_LOG_INFO);
				return json_encode($result);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to download titles for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to download a title.'));
		}
	}

	function GetOverDriveHoldPrompts(){
		global $user;
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('overDriveId', $id);
		if ($user->overdriveEmail == 'undefined'){
			$user->overdriveEmail = '';
		}
		$promptForEmail = false;
		if (strlen($user->overdriveEmail) == 0 || $user->promptForOverdriveEmail == 1){
			$promptForEmail = true;
		}

		$overDriveUsers = $user->getRelatedOverDriveUsers();
		$interface->assign('overDriveUsers', $overDriveUsers);
		if (count($overDriveUsers) == 1){
			$interface->assign('patronId', reset($overDriveUsers)->id);
		}

		$interface->assign('overdriveEmail', $user->overdriveEmail);
		$interface->assign('promptForEmail', $promptForEmail);
		if ($promptForEmail || count($overDriveUsers) > 1){
			$promptTitle = 'OverDrive Hold Options';
			return json_encode(
				array(
					'promptNeeded' => true,
					'promptTitle' => $promptTitle,
					'prompts' => $interface->fetch('OverDrive/ajax-overdrive-hold-prompt.tpl'),
					'buttons' => '<input class="btn btn-primary" type="submit" name="submit" value="Place Hold" onclick="return VuFind.OverDrive.processOverDriveHoldPrompts();"/>'
				)
			);
		}else{
			return json_encode(
				array(
					'patronId' => reset($overDriveUsers)->id,
					'promptNeeded' => false,
					'overdriveEmail' => $user->overdriveEmail,
					'promptForOverdriveEmail' => $promptForEmail,
				)
			);
		}
	}

	function GetOverDriveCheckoutPrompts(){
		global $user;
		global $interface;
		$id = $_REQUEST['id'];
		$interface->assign('overDriveId', $id);

		$overDriveUsers = $user->getRelatedOverDriveUsers();
		$interface->assign('overDriveUsers', $overDriveUsers);

		if (count($overDriveUsers) > 1){
			$promptTitle = 'OverDrive Checkout Options';
			return json_encode(
				array(
					'promptNeeded' => true,
					'promptTitle' => $promptTitle,
					'prompts' => $interface->fetch('OverDrive/ajax-overdrive-checkout-prompt.tpl'),
					'buttons' => '<input class="btn btn-primary" type="submit" name="submit" value="Checkout Title" onclick="return VuFind.OverDrive.processOverDriveCheckoutPrompts();"/>'
				)
			);
		}else{
			return json_encode(
				array(
					'patronId' => reset($overDriveUsers)->id,
					'promptNeeded' => false,
				)
			);
		}

	}

	function CancelOverDriveHold(){
		global $user;
		$overDriveId = $_REQUEST['overDriveId'];
		if ($user){
			$patronId = $_REQUEST['patronId'];
			$patron = $user->getUserReferredTo($patronId);
			if ($patron) {
				require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
				$driver = OverDriveDriverFactory::getDriver();
				$result = $driver->cancelOverDriveHold($overDriveId, $user);
				return json_encode($result);
			}else{
				return json_encode(array('result'=>false, 'message'=>'Sorry, it looks like you don\'t have permissions to download cancel holds for that user.'));
			}
		}else{
			return json_encode(array('result'=>false, 'message'=>'You must be logged in to cancel holds.'));
		}
	}
}