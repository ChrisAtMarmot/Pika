<?php
/**
 * Description goes here
 *
 * @category VuFind-Plus 
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/6/13
 * Time: 9:50 AM
 */

class GroupedWork extends DB_DataObject {
	public $__table = 'grouped_work';    // table name
	public $id;
	public $permanent_id;
	public $title;
	public $author;
	public $subtitle;
	public $grouping_category;
} 