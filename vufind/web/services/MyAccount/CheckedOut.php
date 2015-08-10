<?php
/**
 * Shows all titles that are checked out to a user (combines all sources)
 *
 * @category VuFind-Plus 
 * @author Mark Noble <mark@marmot.org>
 * Date: 10/10/13
 * Time: 1:10 PM
 */

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
class MyAccount_CheckedOut extends MyAccount{
	function launch(){
		global $configArray;
		global $interface;
		global $user;
		global $timer;

		$allCheckedOut = array();
		if ($configArray['Catalog']['offline']){
			$interface->assign('offline', true);
		}else{
			$interface->assign('offline', false);

			//Determine which columns to show
			$ils = $configArray['Catalog']['ils'];
			$showOut = ($ils == 'Horizon' );
			$showRenewed = ($ils == 'Horizon' || $ils == 'Millennium'  || $ils == 'Sierra' || $ils == 'Koha');
			$showWaitList = $ils == 'Horizon';

			$interface->assign('showOut', $showOut);
			$interface->assign('showRenewed', $showRenewed);
			$interface->assign('showWaitList', $showWaitList);

			// Define sorting options
			$sortOptions = array('title'   => 'Title',
				'author'  => 'Author',
				'dueDate' => 'Due Date',
				'format'  => 'Format',
			);

			if ($showWaitList){
				$sortOptions['holdQueueLength']  = 'Wait List';
			}
			if ($showRenewed){
				$sortOptions['renewed'] = 'Times Renewed';
			}

			$interface->assign('sortOptions', $sortOptions);
			$selectedSortOption = isset($_REQUEST['accountSort']) ? $_REQUEST['accountSort'] : 'dueDate';
			$interface->assign('defaultSortOption', $selectedSortOption);

			$libraryHoursMessage = Location::getLibraryHoursMessage($user->homeLocationId);
			$interface->assign('libraryHoursMessage', $libraryHoursMessage);

			if ($user){
				// Get My Transactions
				$allCheckedOut = $user->getMyCheckouts();

				$interface->assign('showNotInterested', false);
				foreach ($allCheckedOut as $i => $curTitle) {
					$sortTitle = isset($curTitle['title_sort']) ? $curTitle['title_sort'] : $curTitle['title'];
					$sortKey = $sortTitle;
					if ($selectedSortOption == 'title'){
						$sortKey = $sortTitle;
					}elseif ($selectedSortOption == 'author'){
						$sortKey = (isset($curTitle['author']) ? $curTitle['author'] : "Unknown") . '-' . $sortTitle;
					}elseif ($selectedSortOption == 'dueDate'){
						if (isset($curTitle['duedate'])){
							if (preg_match('/.*?(\\d{1,2})[-\/](\\d{1,2})[-\/](\\d{2,4}).*/', $curTitle['duedate'], $matches)) {
								$sortKey = $matches[3] . '-' . $matches[1] . '-' . $matches[2] . '-' . $sortTitle;
							} else {
								$sortKey = $curTitle['duedate'] . '-' . $sortTitle;
							}
						}
					}elseif ($selectedSortOption == 'format'){
						$sortKey = (isset($curTitle['format']) ? $curTitle['format'] : "Unknown") . '-' . $sortTitle;
					}elseif ($selectedSortOption == 'renewed'){
						$sortKey = str_pad((isset($curTitle['renewCount']) ? $curTitle['renewCount'] : 0), 3, '0', STR_PAD_LEFT) . '-' . $sortTitle;
					}elseif ($selectedSortOption == 'holdQueueLength'){
						$sortKey = str_pad((isset($curTitle['holdQueueLength']) ? $curTitle['holdQueueLength'] : 0), 3, '0', STR_PAD_LEFT) . '-' . $sortTitle;
					}
					$sortKey = utf8_encode($sortKey);

					$itemBarcode = isset($curTitle['barcode']) ? $curTitle['barcode'] : null;
					$itemId = isset($curTitle['itemid']) ? $curTitle['itemid'] : null;
					if ($itemBarcode != null && isset($_SESSION['renew_message'][$itemBarcode])){
						$renewMessage = $_SESSION['renew_message'][$itemBarcode]['message'];
						$renewResult = $_SESSION['renew_message'][$itemBarcode]['success'];
						$curTitle['renewMessage'] = $renewMessage;
						$curTitle['renewResult']  = $renewResult;
						$allCheckedOut[$sortKey] = $curTitle;
						unset($_SESSION['renew_message'][$itemBarcode]);
						//$logger->log("Found renewal message in session for $itemBarcode", PEAR_LOG_INFO);
					}else if ($itemId != null && isset($_SESSION['renew_message'][$itemId])){
						$renewMessage = $_SESSION['renew_message'][$itemId]['message'];
						$renewResult = $_SESSION['renew_message'][$itemId]['success'];
						$curTitle['renewMessage'] = $renewMessage;
						$curTitle['renewResult']  = $renewResult;
						$allCheckedOut[$sortKey] = $curTitle;
						unset($_SESSION['renew_message'][$itemId]);
						//$logger->log("Found renewal message in session for $itemBarcode", PEAR_LOG_INFO);
					}else{
						$allCheckedOut[$sortKey] = $curTitle;
						$renewMessage = null;
						$renewResult = null;
					}
					unset($allCheckedOut[$i]);
				}

				//Now that we have all the transactions we can sort them
				if ($selectedSortOption == 'renewed' || $selectedSortOption == 'holdQueueLength'){
					krsort($allCheckedOut);
				}else{
					ksort($allCheckedOut);
				}

				$interface->assign('transList', $allCheckedOut);
				unset($_SESSION['renew_message']);
			}

			if (isset($_GET['exportToExcel']) && isset($allCheckedOut)) {
				$this->exportToExcel($allCheckedOut, $showOut, $showRenewed, $showWaitList);
			}

		}

		$this->display('checkedout.tpl', 'Checked Out Items');
	}

	public function exportToExcel($checkedOutItems, $showOut, $showRenewed, $showWaitList) {
		global $interface;
		//PHPEXCEL
		// Create new PHPExcel object
		$objPHPExcel = new PHPExcel();

		// Set properties
		$gitBranch = $interface->getVariable('gitBranch');
		$objPHPExcel->getProperties()->setCreator("Pika " . $gitBranch)
		->setLastModifiedBy("Pika " . $gitBranch)
		->setTitle("Office 2007 XLSX Document")
		->setSubject("Office 2007 XLSX Document")
		->setDescription("Office 2007 XLSX, generated using PHP.")
		->setKeywords("office 2007 openxml php")
		->setCategory("Checked Out Items");

		$activeSheet = $objPHPExcel->setActiveSheetIndex(0);
		$curRow = 1;
		$curCol = 0;
		$activeSheet->setCellValueByColumnAndRow($curCol, $curRow, 'Checked Out Items');
		$curRow = 3;
		$curCol = 0;
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Title');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Author');
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Format');
		if ($showOut){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Out');
		}
		$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Due');
		if ($showRenewed){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Renewed');
		}
		if ($showWaitList){
			$activeSheet->setCellValueByColumnAndRow($curCol++, $curRow, 'Wait List');
		}


		$a=4;
		//Loop Through The Report Data
		foreach ($checkedOutItems as $row) {
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
				$formatString ='';
			}
			$activeSheet = $objPHPExcel->setActiveSheetIndex(0);
			$curCol = 0;
			$activeSheet->setCellValueByColumnAndRow($curCol++, $a, $titleCell);
			$activeSheet->setCellValueByColumnAndRow($curCol++, $a, $authorCell);
			$activeSheet->setCellValueByColumnAndRow($curCol++, $a, $formatString);
			if ($showOut){
				$activeSheet->setCellValueByColumnAndRow($curCol++, $a, date('M d, Y', $row['checkoutdate']));
			}
			if (isset($row['duedate'])){
				$activeSheet->setCellValueByColumnAndRow($curCol++, $a, date('M d, Y', $row['duedate']));
			}else{
				$activeSheet->setCellValueByColumnAndRow($curCol++, $a, '');
			}

			if ($showRenewed){
				if (isset($row['duedate'])) {
					$activeSheet->setCellValueByColumnAndRow($curCol++, $a, $row['renewCount']);
				}else{
					$activeSheet->setCellValueByColumnAndRow($curCol++, $a, '');
				}
			}
			if ($showWaitList){
				$activeSheet->setCellValueByColumnAndRow($curCol++, $a, $row['holdQueueLength']);
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

		// Rename sheet
		$objPHPExcel->getActiveSheet()->setTitle('Checked Out');

		// Redirect output to a client's web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="CheckedOutItems.xls"');
		header('Cache-Control: max-age=0');

		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objWriter->save('php://output');
		exit;

	}
}