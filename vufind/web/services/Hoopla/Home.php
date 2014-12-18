<?php
/**
 *
 * Copyright (C) Marmot Library Network 2014.
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

require_once ROOT_DIR . '/RecordDrivers/HooplaDriver.php';

class Hoopla_Home extends Action{
	/** @var  SearchObject_Solr $db */
	protected $db;
	private $id;
	/** @var HooplaRecordDriver */
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
		$recordDriver = new HooplaRecordDriver($this->id);

		if (!$recordDriver->isValid()){
			$interface->setTemplate('../Record/invalidRecord.tpl');
			$interface->display('layout.tpl');
			die();
		}else{
			$interface->assign('recordDriver', $recordDriver);

			//Load the citations
			$this->loadCitations($recordDriver);

			// Retrieve User Search History
			$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false);

			//Get Next/Previous Links
			$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
			$searchObject = SearchObjectFactory::initSearchObject();
			$searchObject->init($searchSource);
			$searchObject->getNextPrevLinks();

			$interface->setPageTitle($recordDriver->getTitle());
			$interface->assign('moreDetailsOptions', $recordDriver->getMoreDetailsOptions());

			// Display Page
			$interface->assign('sidebar', 'Hoopla/full-record-sidebar.tpl');
			$interface->assign('moreDetailsTemplate', 'GroupedWork/moredetails-accordion.tpl');
			$interface->setTemplate('view.tpl');

			$interface->display('layout.tpl');
		}
	}

	/**
	 * @param RestrictedEContentDriver $recordDriver
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