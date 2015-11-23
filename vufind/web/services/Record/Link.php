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

require_once ROOT_DIR . '/Action.php';

class Record_Link extends Action {

	function launch() {

		global $configArray;

		//Grab the tracking data
		$recordId = $_REQUEST['id'];
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		$field856Index = isset($_REQUEST['index']) ? $_REQUEST['index'] : null;
				
		// Setup Search Engine Connection
		$class = $configArray['Index']['engine'];
		$url = $configArray['Index']['url'];
		$this->db = new $class($url);

		// Process MARC Data
		require_once ROOT_DIR . '/sys/MarcLoader.php';
		$marcRecord = MarcLoader::loadMarcRecordByILSId($recordId);
		if ($marcRecord) {
			$this->marcRecord = $marcRecord;
		} else {
			PEAR_Singleton::raiseError(new PEAR_Error("Failed to load the MAC record for this title."));
		}

		/** @var File_MARC_Data_Field[] $linkFields */
		$linkFields = $marcRecord->getFields('856') ;
		if ($linkFields){
			$cur856Index = 0;
			foreach ($linkFields as $marcField){
				$cur856Index++;
				if ($cur856Index == $field856Index){
					//Get the link
					if ($marcField->getSubfield('u')){
						$link = $marcField->getSubfield('u')->getData();
						$externalLink = $link;
					}
				}
			}
		}
		
		$linkParts = parse_url($externalLink);

		//Insert into the purchaseLinkTracking table
		require_once(ROOT_DIR . '/sys/BotChecker.php');
		if (!BotChecker::isRequestFromBot()){
			require_once(ROOT_DIR . '/sys/ExternalLinkTracking.php');
			$externalLinkTracking = new ExternalLinkTracking();
			$externalLinkTracking->ipAddress = $ipAddress;
			$externalLinkTracking->recordId = $recordId;
			$externalLinkTracking->linkUrl = $externalLink;
			$externalLinkTracking->linkHost = $linkParts['host'];
			$result = $externalLinkTracking->insert();
		}

		//redirects them to the link they clicked
		if ($externalLink != ""){
			header( "Location:" .$externalLink);
		} else {
			PEAR_Singleton::raiseError(new PEAR_Error("Failed to load link for this record."));
		}
	}

}