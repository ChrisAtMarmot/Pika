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
require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/ListWidget.php';
require_once ROOT_DIR . '/sys/ListWidgetList.php';
require_once ROOT_DIR . '/sys/DataObjectUtil.php';

/**
 * Provides a method of running SQL updates to the database.
 * Shows a list of updates that are available with a description of the
 *
 * @author Mark Noble
 *
 */
class ListWidgets extends ObjectEditor {
	function getObjectType(){
		return 'ListWidget';
	}
	function getToolName(){
		return 'ListWidgets';
	}
	function getPageTitle(){
		return 'List Widgets';
	}
	function getAllObjects(){
		$list = array();

		global $user;
		$widget = new ListWidget();
		if ($user->hasRole('libraryAdmin') || $user->hasRole('contentEditor')){
			$patronLibrary = Library::getPatronHomeLibrary();
			$widget->libraryId = $patronLibrary->libraryId();
		}
		$widget->orderBy('name');
		$widget->find();
		while ($widget->fetch()){
			$list[$widget->id] = clone $widget;
		}

		return $list;
	}
	function getObjectStructure(){
		return ListWidget::getObjectStructure();
	}
	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor');
	}
	function getPrimaryKeyColumn(){
		return 'id';
	}
	function getIdKeyColumn(){
		return 'id';
	}
	function showExportAndCompare(){
		global $user;
		return $user->hasRole('opacAdmin');
	}
	function canAddNew(){
		global $user;
		return $user->hasRole('opacAdmin') || $user->hasRole('libraryAdmin') || $user->hasRole('contentEditor');
	}
	function canDelete(){
		global $user;
		return $user->hasRole('opacAdmin');
	}
	function launch() 	{
		global $configArray;
		global $interface;
		global $user;

		$interface->assign('canAddNew', $this->canAddNew());
		$interface->assign('canDelete', $this->canDelete());
		$interface->assign('showReturnToList', $this->showReturnToList());
		$interface->assign('showExportAndCompare', $this->showExportAndCompare());

		//Figure out what mode we are in
		if (isset($_REQUEST['objectAction'])){
			$objectAction = $_REQUEST['objectAction'];
		}else{
			$objectAction = 'list';
		}

		if ($objectAction == 'delete' && isset($_REQUEST['id'])){
			parent::launch();
			exit();
		}

		//Get all available widgets
		$availableWidgets = array();
		$listWidget = new ListWidget();
		if ($user->hasRole('libraryAdmin') || $user->hasRole('contentEditor')){
			$homeLibrary = Library::getPatronHomeLibrary();
			$listWidget->libraryId = $homeLibrary->libraryId;
		}
		$listWidget->orderBy('name ASC');
		$listWidget->find();
		while ($listWidget->fetch()){
			$availableWidgets[$listWidget->id] = clone($listWidget);
		}
		$interface->assign('availableWidgets', $availableWidgets);

		//Get the selected widget
		if (isset($_REQUEST['id'])  && is_numeric($_REQUEST['id'])){
			$widget = $availableWidgets[$_REQUEST['id']];
			$interface->assign('object', $widget);
		}

		//Do actions that require preprocessing
		if ($objectAction == 'save'){
			if (!isset($widget)){
				$widget = new ListWidget();
			}
			DataObjectUtil::updateFromUI($widget, $listWidget->getObjectStructure());
			$validationResults = DataObjectUtil::saveObject($listWidget->getObjectStructure(), "ListWidget");
			if (!$validationResults['validatedOk']){
				$interface->assign('object', $widget);
				$interface->assign('errors', $validationResults['errors']);
				$objectAction = 'edit';
			}else{
				$interface->assign('object', $validationResults['object']);
				$objectAction = 'view';
			}

		}

		if ($objectAction == 'list'){
			$interface->setTemplate('listWidgets.tpl');
		}else{
			if ($objectAction == 'edit' || $objectAction == 'add'){
				if (isset($_REQUEST['id'])){
					$interface->assign('widgetid',$_REQUEST['id']);
					$interface->assign('id',$_REQUEST['id']);
				}
				$editForm = DataObjectUtil::getEditForm($listWidget->getObjectStructure());
				$interface->assign('editForm', $editForm);
				$interface->setTemplate('listWidgetEdit.tpl');
			}else{
				$interface->setTemplate('listWidget.tpl');
			}
		}

		$interface->setPageTitle('List Widgets');
		$interface->display('layout.tpl');

	}
}