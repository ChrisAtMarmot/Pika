<?php
/**
 * Shows all titles that are on hold for a user (combines all sources)
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 10/10/13
 * Time: 1:11 PM
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
class MyAccount_Holds extends MyAccount{
	function launch()
	{
		global $configArray;
		global $interface;
		global $user;

		//these actions are being moved to MyAccount/AJAX.php
		if (isset($_REQUEST['multiAction'])){
			$multiAction = $_REQUEST['multiAction'];
			$locationId = isset($_REQUEST['location']) ? $_REQUEST['location'] : null;
			$cancelId = array();
			$type = 'update';
			$freeze = '';
			if ($multiAction == 'cancelSelected'){
				$type = 'cancel';
//				$freeze = ''; // same as default setting.
			}elseif ($multiAction == 'freezeSelected'){
//				$type = 'update'; // same as default setting.
				$freeze = 'on';
			}elseif ($multiAction == 'thawSelected'){
//				$type = 'update'; // same as default setting.
				$freeze = 'off';
			}
//			elseif ($multiAction == 'updateSelected'){ // same as default settings.

//				$type = 'update';
//				$freeze = '';
//			}
			$result = $this->catalog->driver->updateHoldDetailed($user->password, $type, '', null, $cancelId, $locationId, $freeze);
//			$interface->assign('holdResult', $result);


			//Redirect back here without the extra parameters.
			$redirectUrl = $configArray['Site']['path'] . '/MyAccount/Holds?accountSort=' . ($selectedSortOption = isset($_REQUEST['accountSort']) ? $_REQUEST['accountSort'] : 'title');
			header("Location: " . $redirectUrl);


			die();
		}

		$interface->assign('allowFreezeHolds', true);

		$ils = $configArray['Catalog']['ils'];
		$showPosition = ($ils == 'Horizon');
		$showExpireTime = ($ils == 'Horizon');
		$suspendRequiresReactivationDate = ($ils == 'Horizon');
		$interface->assign('suspendRequiresReactivationDate', $suspendRequiresReactivationDate);
		$canChangePickupLocation = ($ils != 'Koha');
		$interface->assign('canChangePickupLocation', $canChangePickupLocation);
		// Define sorting options
		$sortOptions = array(
			'title' => 'Title',
			'author' => 'Author',
			'format' => 'Format',
			'placed' => 'Date Placed',
			'location' => 'Pickup Location',
			'status' => 'Status',
		);

		if ($showPosition){
			$sortOptions['position'] = 'Position';
		}
		$interface->assign('sortOptions', $sortOptions);
		$selectedSortOption = isset($_REQUEST['accountSort']) ? $_REQUEST['accountSort'] : 'title';
		$interface->assign('defaultSortOption', $selectedSortOption);

		$profile = $this->catalog->getMyProfile($user);

		$libraryHoursMessage = Location::getLibraryHoursMessage($profile['homeLocationId']);
		$interface->assign('libraryHoursMessage', $libraryHoursMessage);

		$allowChangeLocation = ($ils == 'Millennium' || $ils == 'Sierra');
		$interface->assign('allowChangeLocation', $allowChangeLocation);
		//$showPlacedColumn = ($ils == 'Horizon');
		//Horizon Web Services does not include data placed anymore
		$showPlacedColumn = false;
		$interface->assign('showPlacedColumn', $showPlacedColumn);
		$showDateWhenSuspending = ($ils == 'Horizon');
		$interface->assign('showDateWhenSuspending', $showDateWhenSuspending);

		$interface->assign('showPosition', $showPosition);
		$interface->assign('showNotInterested', false);

		// Get My Transactions
		if ($configArray['Catalog']['offline']){
			$interface->assign('offline', true);
		}else{
			$patron = null;
			if ($this->catalog->status) {
				if ($user->cat_username) {
					$patron = $this->catalog->patronLogin($user->cat_username, $user->cat_password);
					$patronResult = $this->catalog->getMyProfile($patron);
					if (!PEAR_Singleton::isError($patronResult)) {
						$interface->assign('profile', $patronResult);
					}

					$interface->assign('sortOptions', $sortOptions);
					$selectedSortOption = isset($_REQUEST['accountSort']) ? $_REQUEST['accountSort'] : 'dueDate';
					$interface->assign('defaultSortOption', $selectedSortOption);
					$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;

					$recordsPerPage = isset($_REQUEST['pagesize']) && (is_numeric($_REQUEST['pagesize'])) ? $_REQUEST['pagesize'] : 25;
					$interface->assign('recordsPerPage', $recordsPerPage);
					if (isset($_GET['exportToExcel'])) {
						$recordsPerPage = -1;
						$page = 1;
					}

					//Get Holds from the ILS
					$ilsHolds = $this->catalog->getMyHolds($patron, 1, -1, $selectedSortOption);
					if (PEAR_Singleton::isError($ilsHolds)) {
						$ilsHolds = array();
					}

					//Get holds from OverDrive
					require_once ROOT_DIR . '/Drivers/OverDriveDriverFactory.php';
					$overDriveDriver = OverDriveDriverFactory::getDriver();
					$overDriveHolds = $overDriveDriver->getOverDriveHolds($user);

					//Get a list of eContent that has been checked out
					require_once ROOT_DIR . '/Drivers/EContentDriver.php';
					$driver = new EContentDriver();
					$eContentHolds = $driver->getMyHolds($user);


					$allHolds = array_merge_recursive($ilsHolds, $overDriveHolds, $eContentHolds);


					/* pickUpLocations doesn't seem to be used by the Holds summary page. plb 1-26-2015
					$location = new Location();
					$pickupBranches = $location->getPickupBranches($patronResult, null);
					$locationList = array();
					foreach ($pickupBranches as $curLocation) {
						$locationList[$curLocation->locationId] = $curLocation->displayName;
					}
					$interface->assign('pickupLocations', $locationList); */

					//Make sure available holds come before unavailable
					$interface->assign('recordList', $allHolds['holds']);

					//make call to export function
					if ((isset($_GET['exportToExcelAvailable'])) || (isset($_GET['exportToExcelUnavailable']))){
						if (isset($_GET['exportToExcelAvailable'])) {
							$exportType = "available";
						}
						else {
							$exportType = "unavailable";
						}
						$this->exportToExcel($allHolds, $exportType, $showDateWhenSuspending, $showPosition, $showExpireTime);
					}
				}
			}
			$interface->assign('patron',$patron);
		}

		//Load holds that have been entered offline
		if ($user){
			require_once ROOT_DIR . '/sys/OfflineHold.php';
			$twoDaysAgo = time() - 48 * 60 * 60;
			$twoWeeksAgo = time() - 14 * 24 * 60 * 60;
			$offlineHoldsObj = new OfflineHold();
			$offlineHoldsObj->patronId = $user->id;
			$offlineHoldsObj->whereAdd("status = 'Not Processed' OR (status = 'Hold Placed' AND timeEntered >= $twoDaysAgo) OR (status = 'Hold Failed' AND timeEntered >= $twoWeeksAgo)");
			// mysql has these functions as well: "status = 'Not Processed' OR (status = 'Hold Placed' AND timeEntered >= DATE_SUB(NOW(), INTERVAL 2 DAYS)) OR (status = 'Hold Failed' AND timeEntered >= DATE_SUB(NOW(), INTERVAL 2 WEEKS))");
			$offlineHolds = array();
			if ($offlineHoldsObj->find()){
				while ($offlineHoldsObj->fetch()){
					//Load the title
					$offlineHold = array();
					require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';
					$recordDriver = new MarcRecord($offlineHoldsObj->bibId);
					if ($recordDriver->isValid()){
						$offlineHold['title'] = $recordDriver->getTitle();
					}
					$offlineHold['bibId'] = $offlineHoldsObj->bibId;
					$offlineHold['timeEntered'] = $offlineHoldsObj->timeEntered;
					$offlineHold['status'] = $offlineHoldsObj->status;
					$offlineHold['notes'] = $offlineHoldsObj->notes;
					$offlineHolds[] = $offlineHold;
				}
			}
			$interface->assign('offlineHolds', $offlineHolds);
		}

		$interface->setPageTitle('My Holds');
		$interface->assign('sidebar', 'MyAccount/account-sidebar.tpl');
		global $library;
		if (!$library->showDetailedHoldNoticeInformation){
			$notification_method = '';
		}else{
			$notification_method = ($profile['noticePreferenceLabel'] != 'Unknown') ? $profile['noticePreferenceLabel'] : '';
			if ($notification_method == 'Mail' && $library->treatPrintNoticesAsPhoneNotices){
				$notification_method = 'Telephone';
			}
		}
		$interface->assign('notification_method', strtolower($notification_method));
		$interface->setTemplate('holds.tpl');

		//print_r($patron);
		$interface->display('layout.tpl');
	}

	public function exportToExcel($result, $exportType, $showDateWhenSuspending, $showPosition, $showExpireTime) {
		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		$objPHPExcel->getProperties()->setCreator("DCL")
		->setLastModifiedBy("DCL")
		->setTitle("Office 2007 XLSX Document")
		->setSubject("Office 2007 XLSX Document")
		->setDescription("Office 2007 XLSX, generated using PHP.")
		->setKeywords("office 2007 openxml php")
		->setCategory("Holds");

		if ($exportType == "available") {
			// Add some data
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', 'Holds - '.ucfirst($exportType))
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Format')
			->setCellValue('D3', 'Placed')
			->setCellValue('E3', 'Pickup')
			->setCellValue('F3', 'Available')
			->setCellValue('G3', 'Expires');
		} else {
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A1', 'Holds - '.ucfirst($exportType))
			->setCellValue('A3', 'Title')
			->setCellValue('B3', 'Author')
			->setCellValue('C3', 'Format')
			->setCellValue('D3', 'Placed')
			->setCellValue('E3', 'Pickup');

			if ($showPosition){
				$objPHPExcel->getActiveSheet()->setCellValue('F3', 'Position')
				->setCellValue('G3', 'Status');
				if ($showExpireTime){
					$objPHPExcel->getActiveSheet()->setCellValue('H3', 'Expires');
				}
			}else{
				$objPHPExcel->getActiveSheet()
				->setCellValue('F3', 'Status');
				if ($showExpireTime){
					$objPHPExcel->getActiveSheet()->setCellValue('G3', 'Expires');
				}
			}
		}


		$a=4;
		//Loop Through The Report Data
		foreach ($result[$exportType] as $row) {

			$titleCell = preg_replace("/(\/|:)$/", "", $row['title']);
			if (isset ($row['title2'])){
				$titleCell .= preg_replace("/(\/|:)$/", "", $row['title2']);
			}

			if (isset ($row['author'])){
				if (is_array($row['author'])){
					$authorCell = implode(', ', $row['author']);
				}else{
					$authorCell = $row['author'];
				}
				$authorCell = str_replace('&nbsp;', ' ', $authorCell);
			}else{
				$authorCell = '';
			}
			if (isset($row['format'])){
				if (is_array($row['format'])){
					$formatString = implode(', ', $row['format']);
				}else{
					$formatString = $row['format'];
				}
			}else{
				$formatString = '';
			}

			if ($exportType == "available") {
				$objPHPExcel->getActiveSheet()
				->setCellValue('A'.$a, $titleCell)
				->setCellValue('B'.$a, $authorCell)
				->setCellValue('C'.$a, $formatString)
				->setCellValue('D'.$a, isset($row['createTime']) ? date('M d, Y', $row['createTime']) : '')
				->setCellValue('E'.$a, $row['location'])
				->setCellValue('F'.$a, isset($row['availableTime']) ? date('M d, Y', strtotime($row['availableTime'])) : 'Now')
				->setCellValue('G'.$a, date('M d, Y', $row['expire']));
			} else {
				$statusCell = $row['status'];
				if ($row['frozen'] && $showDateWhenSuspending){
					$statusCell .= " until " . date('M d, Y', strtotime($row['reactivateTime']));
				}
				$objPHPExcel->getActiveSheet()
				->setCellValue('A'.$a, $titleCell)
				->setCellValue('B'.$a, $authorCell)
				->setCellValue('C'.$a, $formatString)
				->setCellValue('D'.$a, isset($row['createTime']) ? date('M d, Y', $row['createTime']) : '')
				->setCellValue('E'.$a, $row['location']);
				if ($showPosition){
					$objPHPExcel->getActiveSheet()
					->setCellValue('F'.$a, $row['position'])
					->setCellValue('G'.$a, $statusCell);
					if ($showExpireTime){
						$objPHPExcel->getActiveSheet()->setCellValue('H'.$a, date('M d, Y', $row['expireTime']));
					}
				}else{
					$objPHPExcel->getActiveSheet()->setCellValue('F'.$a, $statusCell);
					if ($showExpireTime){
						$objPHPExcel->getActiveSheet()->setCellValue('G'.$a, date('M d, Y', $row['expireTime']));
					}
				}
			}
			$a++;
		}
		$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setAutoSize(true);
		$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setAutoSize(true);


		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle('Holds');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="Holds.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;

	}
}