<?php
/**
 * Allows display of a single image from Islandora
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 9/8/2015
 * Time: 8:43 PM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_Pdf extends Archive_Object{
	function launch() {
		global $interface;
		global $configArray;
		$objectUrl = $configArray['Islandora']['objectUrl'];
		$fedoraUtils = FedoraUtils::getInstance();

		$this->loadArchiveObjectData();
		//$this->loadExploreMoreContent();

		//Get the contents of the book
		$interface->assign('showExploreMore', true);

		if ($this->recordDriver->getArchiveObject()->getDataStream('OBJ') != null){
			$interface->assign('pdf', $objectUrl . '/' . $this->recordDriver->getUniqueID() . '/datastream/OBJ/view');
		}

		// Display PDF
		$this->display('pdf.tpl');
	}

}