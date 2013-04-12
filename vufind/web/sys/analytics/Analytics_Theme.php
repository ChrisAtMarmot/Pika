<?php
/**
 * Information about the theme being used for the session.
 *
 * @category VuFind-Plus
 * @author Mark Noble <mark@marmot.org>
 * Date: 4/12/13
 * Time: 11:47 AM
 */

require_once 'DB/DataObject.php';

class Analytics_Theme extends DB_DataObject
{
	public $__table = 'analytics_theme';                        // table name
	public $id;
	public $value;
}