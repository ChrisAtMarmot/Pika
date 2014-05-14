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

class Admin_AJAX extends Action {


	function launch() {
		global $timer;
		$method = $_GET['method'];
		$timer->logTime("Starting method $method");
		if (in_array($method, array('getReindexNotes', 'getReindexProcessNotes', 'getCronNotes', 'getCronProcessNotes', 'getAddToWidgetForm'))){
			//JSON Responses
			header('Content-type: application/json');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo $this->$method();
		}else if (in_array($method, array('getOverDriveExtractNotes'))){
			//HTML responses
			header('Content-type: text/html');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			echo $this->$method();
		}else{
			//XML responses
			header ('Content-type: text/xml');
			header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			$xml = '<?xml version="1.0" encoding="UTF-8"?' . ">\n" .
	               "<AJAXResponse>\n";
			if (is_callable(array($this, $_GET['method']))) {
				$xml .= $this->$_GET['method']();
			} else {
				$xml .= '<Error>Invalid Method</Error>';
			}
			$xml .= '</AJAXResponse>';

			echo $xml;
		}
	}

	function getReindexNotes(){
		$id = $_REQUEST['id'];
		$reindexProcess = new ReindexLogEntry();
		$reindexProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($reindexProcess->find(true)){
			$results['title'] = "Reindex Notes";
			if (strlen(trim($reindexProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered yet";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$reindexProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a reindex entry with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getReindexProcessNotes(){
		$id = $_REQUEST['id'];
		$reindexProcess = new ReindexProcessLogEntry();
		$reindexProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($reindexProcess->find(true)){
			$results['title'] = "{$reindexProcess->processName} Notes";
			if (strlen(trim($reindexProcess->notes)) == 0){
				$results['modalBody'] = "No notes have been entered for this process";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$reindexProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a process with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getCronProcessNotes(){
		$id = $_REQUEST['id'];
		$cronProcess = new CronProcessLogEntry();
		$cronProcess->id = $id;
		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($cronProcess->find(true)){
			$results['title'] = "{$cronProcess->processName} Notes";
			if (strlen($cronProcess->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this process";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$cronProcess->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a process with that id.  No notes available.";
		}
		return json_encode($results);
	}

	function getCronNotes()	{
		$id = $_REQUEST['id'];
		$cronLog = new CronLogEntry();
		$cronLog->id = $id;

		$results = array(
				'title' => '',
				'modalBody' => '',
				'modalButtons' => ""
		);
		if ($cronLog->find(true)){
			$results['title'] = "Cron Process {$cronLog->id} Notes";
			if (strlen($cronLog->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this cron run";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$cronLog->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a cron entry with that id.  No notes available.";
		}
		return json_encode($results);
	}
    
  function getOverDriveExtractNotes()	{
		global $interface;
		$id = $_REQUEST['id'];
		$overdriveExtractLog = new OverDriveExtractLogEntry();
		$overdriveExtractLog->id = $id;
	  $results = array(
			  'title' => '',
			  'modalBody' => '',
			  'modalButtons' => ""
	  );
		if ($overdriveExtractLog->find(true)){
			$results['title'] = "OverDrive Extract {$overdriveExtractLog->id} Notes";
			if (strlen($overdriveExtractLog->notes) == 0){
				$results['modalBody'] = "No notes have been entered for this OverDrive Extract run";
			}else{
				$results['modalBody'] = "<div class='helpText'>{$overdriveExtractLog->notes}</div>";
			}
		}else{
			$results['title'] = "Error";
			$results['modalBody'] = "We could not find a OverDrive Extract entry with that id.  No notes available.";
		}
	  return json_encode($results);
	}

	function getAddToWidgetForm(){
		global $interface;
		global $user;
		// Display Page
		$interface->assign('id', strip_tags($_REQUEST['id']));
		$interface->assign('source', strip_tags($_REQUEST['source']));
		$existingWidgets = array();
		$listWidget = new ListWidget();
		if ($user->hasRole('libraryAdmin') || $user->hasRole('contentEditor')){
			//Get all widgets for the library
			$userLibrary = Library::getPatronHomeLibrary();
			$listWidget->libraryId = $userLibrary->libraryId;
		}
		$listWidget->find();
		while ($listWidget->fetch()){
			$existingWidgets[$listWidget->id] = $listWidget->name;
		}
		$interface->assign('existingWidgets', $existingWidgets);
		$results = array(
				'title' => 'Create a Widget',
				'modalBody' => $interface->fetch('Admin/addToWidgetForm.tpl'),
				'modalButtons' => "<span class='tool btn btn-primary' onclick='$(\"#bulkAddToList\").submit();'>Create Widget</span>"
		);
		return json_encode($results);
	}
}
