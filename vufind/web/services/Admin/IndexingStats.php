<?php
/**
 * Displays indexing statistics for the system
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 3/16/15
 * Time: 8:41 PM
 */

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';

class IndexingStats extends Admin_Admin{
	function launch(){
		global $interface;
		global $user;
		global $configArray;

		$interface->setPageTitle('Indexing Statistics');

		//Load the latest indexing stats
		$baseDir = dirname($configArray['Reindex']['marcPath']);

		$indexingStatFiles = array();
		$allFilesInDir = scandir($baseDir);
		foreach ($allFilesInDir as $curFile){
			if (preg_match('/reindex_stats_([\\d-]+)\\.csv/', $curFile, $matches)){
				$indexingStatFiles[$matches[1]] = $baseDir . '/' . $curFile;
			}
		}
		krsort($indexingStatFiles);

		if (count($indexingStatFiles) != 0){
			//Get the specified file, the file for today, or the most recent file
			$dateToRetrieve = date('Y-m-d');
			if (isset($_REQUEST['day'])){
				$dateToRetrieve = $_REQUEST['day'];
			}
			$fileToLoad = null;
			if (isset($indexingStatFiles[$dateToRetrieve])){
				$fileToLoad = $indexingStatFiles[$dateToRetrieve];
			}else{
				$fileToLoad = reset($indexingStatFiles);
			}

			$indexingStatFhnd = fopen($fileToLoad, 'r');
			$indexingStatHeader = fgetcsv($indexingStatFhnd);
			$indexingStats = array();
			while ($curRow = fgetcsv($indexingStatFhnd)){
				$indexingStats[] = $curRow;
			}
			fclose($indexingStatFhnd);

			$interface->assign('indexingStatHeader', $indexingStatHeader);
			$interface->assign('indexingStats', $indexingStats);
			$interface->assign('indexingStatsDate', $dateToRetrieve);
		}else{
			$interface->assign('noStatsFound', true);
		}

		$interface->assign('sidebar', 'MyAccount/account-sidebar.tpl');
		$interface->setTemplate('reindexStats.tpl');
		$interface->display('layout.tpl');
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'cataloging');
	}
} 