<script type="text/javascript" src="{$path}/js/tablesorter/jquery.tablesorter.min.js"></script>
<div id="page-content" class="row">
	<div id="sidebar" class="col-md-3">
		{include file="MyResearch/menu.tpl"}
		{include file="Admin/menu.tpl"}
	</div>

	<div id="main-content" class="col-md-9">
		<h3>eContent Collection Details</h3>
		{if $error}
			<div class="error">{$error}</div>
		{else}
			<div id="collectionDetailsFilters" class="reportFilters">
				Filters:
				<form id="filters" action="{$path}/EContent/EContentCollection" method="get">
					<div>
						<div>
							<label for="source">Source to Show:</label> 
							<select name="source" id="source">
								{foreach from=$sourceFilter item="sourceItem"}
									<option value="{$sourceItem}" {if $sourceItem == $source}selected="selected"{/if}>{$sourceItem}</option>
								{/foreach}
							</select>
						</div>
						<div>
							Date: 
							<label for="startDate">From</label> <input type="text" id="startDate" name="startDate" value="{$startDate}" size="8"/>
							<label for="endDate">To</label> <input type="text" id="endDate" name="endDate" value="{$endDate}" size="8"/>
						</div>
						<div>
						<input type="submit" name="submit" value="Update Filters" class="btn" />
						<input type="submit" id="exportToExcel" name="exportToExcel" value="Export to Excel" class="btn" />
						</div>
					</div>
					
					<div id="reportSorting">
						{if $pageLinks.all}
							{translate text="Showing"}
							<b>{$recordStart}</b> - <b>{$recordEnd}</b>
							{translate text='of'} <b>{$recordCount}</b>
							{if $searchType == 'basic'}{translate text='for search'}: <b>'{$lookfor|escape:"html"}'</b>,{/if}
						{/if}
					            
						<b>Results Per Page: </b>
						<select name="itemsPerPage" id="itemsPerPage"\>
						{foreach from=$itemsPerPageList item=itemsPerPageItem key=keyName}
							<option value="{$itemsPerPageItem.amount}" {if $itemsPerPageItem.selected} selected="selected"{/if} >{$itemsPerPageItem.amount}</option>
						{/foreach}
					  </select>
					  <input name="updateRecordsPerPage" value="Go" type="submit"/>
					</div>
				</form>
			</div>
			
			{if count($collectionDetails) > 0}
				<table id="collectionDetails" class="tablesorter table table-bordered table-striped">
					<thead>
						<tr>
							<th>Id</th>
							<th>Title</th>
							<th>Author</th>
							<th>ISBN</th>
							<th>Publisher</th>
							<th>Source</th>
							<th>Date Added</th>
							{if $showNumItems}
								<th>Num Items</th>
							{/if}
						</tr>
					</thead>
					<tbody>
						{foreach from=$collectionDetails item=record}
							<tr>
								<td>{$record->id}</td>
								<td>{$record->title}{if $record->subTitle}: {$record->subTitle}{/if}</td>
								<td>{$record->author}</td>
								<td>{$record->getISBN()}</td>
								<td>{$record->publisher}</td>
								<td>{$record->source}</td>
								<td>{$record->date_added|date_format}</td>
								{if $showNumItems}
									<td>{$record->getNumItems()}</td>
								{/if}
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if $pageLinks.all}<div class="pagination" id="pagination-bottom">Page: {$pageLinks.all}</div>{/if}
			{else}
				<div>There are no record that meet your criteria.</div>
			{/if}
		{/if}
	</div>
</div>
<script type="text/javascript">
{literal}
	$("#startDate").datepicker();
	$("#endDate").datepicker();
	$("#collectionDetails").tablesorter({cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader', headers: { 0: { sorter: false}, 6: {sorter : 'date'} } });
{/literal}
</script>