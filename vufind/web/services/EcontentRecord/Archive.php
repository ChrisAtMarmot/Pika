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
require_once ROOT_DIR . '/sys/DataObjectUtil.php';
require_once ROOT_DIR . '/sys/eContent/EContentRecord.php';

class Archive extends Action {

	function launch()
	{
		global $interface;
		global $configArray;

		$eContentRecord = EContentRecord::staticGet('id', $_REQUEST['id']);
		$eContentRecord->status = 'archived';
		$eContentRecord->date_updated = time();
		$ret = $eContentRecord->update();

		//Redirect back to the PMDA home page
		header('Location:' . $configArray['Site']['path'] . "/");
		exit();
	}

}
