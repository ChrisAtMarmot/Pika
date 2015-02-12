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

require_once ROOT_DIR . '/RecordDrivers/PublicEContentDriver.php';

class PublicEContent_Home extends Action{
	/** @var  SearchObject_Solr $db */
	protected $db;
	private $id;
	private $isbn;
	private $issn;
	/** @var PublicEContentDriver */
	private $recordDriver;

	function launch(){
		global $interface;

		if (isset($_REQUEST['searchId'])){
			$_SESSION['searchId'] = $_REQUEST['searchId'];
			$interface->assign('searchId', $_SESSION['searchId']);
		}else if (isset($_SESSION['searchId'])){
			$interface->assign('searchId', $_SESSION['searchId']);
		}

		$this->id = strip_tags($_REQUEST['id']);
		$interface->assign('id', $this->id);
		$recordDriver = new PublicEContentDriver($this->id);

		if (!$recordDriver->isValid()){
			$interface->setTemplate('../Record/invalidRecord.tpl');
			$interface->display('layout.tpl');
			die();
		}else{
			$interface->assign('recordDriver', $recordDriver);

			$items = $recordDriver->getItemsFast();
			$interface->assign('items', $items);

			$summaryActions = array();
			foreach ($items as $item){
				foreach ($item['actions'] as $key => $action){
					if ($action['showInSummary']){
						$summaryActions[$key] = $action;
					}
				}
			}
			$interface->assign('summaryActions', $summaryActions);

			//Load the citations
			$this->loadCitations($recordDriver);

			// Retrieve User Search History
			$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false);

			//Get Next/Previous Links
			$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init($searchSource);
			$searchObject->getNextPrevLinks();

			// Set Show in Main Details Section options for templates
			// (needs to be set before moreDetailsOptions)
			global $library;
			foreach ($library->showInMainDetails as $detailoption) {
				$interface->assign($detailoption, true);
			}

			$interface->setPageTitle($recordDriver->getTitle());
			$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());

			// Display Page
			$interface->assign('sidebar', 'PublicEContent/full-record-sidebar.tpl');
			$interface->assign('moreDetailsTemplate', 'GroupedWork/moredetails-accordion.tpl');
			$interface->setTemplate('view.tpl');

			$interface->display('layout.tpl');
		}
	}

	/**
	 * @param PublicEContentDriver $recordDriver
	 */
	function loadCitations($recordDriver)
	{
		global $interface;

		$citationCount = 0;
		$formats = $recordDriver->getCitationFormats();
		foreach($formats as $current) {
			$interface->assign(strtolower($current), $recordDriver->getCitation($current));
			$citationCount++;
		}
		$interface->assign('citationCount', $citationCount);
	}

}