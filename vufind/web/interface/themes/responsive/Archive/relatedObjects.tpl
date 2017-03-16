{strip}
	{if ($displayType == 'map' || $displayType == 'timeline' || $displayType == 'scroller' || $displayType == 'basic') && $page == 1 && $reloadHeader == 1}
		<div id="exhibit-results-loading" class="row" style="display: none">
			<div class="alert alert-info">
				Updating results, please wait.
			</div>
		</div>

		<div class="row">
			<div class="col-sm-6">
				{if ($displayType == 'map' || $displayType == 'timeline')}
					<form action="/Archive/Results">
						<div class="input-group">
							<input type="text" name="lookfor" size="30" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="Search this collection">
							<div class="input-group-btn" id="search-actions">
								<button class="btn btn-default" type="submit">GO</button>
							</div>
							<input type="hidden" name="islandoraType" value="IslandoraKeyword"/>
							<input type="hidden" name="filter[]" value='RELS_EXT_isMemberOfCollection_uri_ms:"info:fedora/{$exhibitPid}"'/>
						</div>
					</form>
				{elseif $displayType == 'basic'}
					<form action="/Archive/Results">
						<div class="input-group">
							<input type="text" name="lookfor" size="30" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="Search this collection">
							<div class="input-group-btn" id="search-actions">
								<button class="btn btn-default" type="submit">GO</button>
							</div>
							<input type="hidden" name="islandoraType" value="IslandoraKeyword">
						</div>
					</form>
				{/if}
			</div>
			<div class="col-sm-4 col-sm-offset-2">
				{* Display information to sort the results (by date or by title *}
				<select id="results-sort" name="sort" onchange="VuFind.Archive.sort = this.options[this.selectedIndex].value;
								{if $displayType == 'map'}
									VuFind.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 1);
								{elseif $displayType == 'timeline'}
									VuFind.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 1);
								{elseif $displayType == 'scroller'}
									VuFind.Archive.reloadScrollerResults('{$exhibitPid|urlencode}', 1);
								{elseif $displayType == 'basic'}
												VuFind.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}', 1);
								{/if}
								" class="form-control">
					<option value="title" {if $sort=='title'}selected="selected"{/if}>{translate text='Sort by ' }Title</option>
					<option value="newest" {if $sort=='newest'}selected="selected"{/if}>{translate text='Sort by ' }Newest First</option>
					<option value="oldest" {if $sort=='oldest'}selected="selected"{/if}>{translate text='Sort by ' }Oldest First</option>
					<option value="dateAdded" {if $sort=='dateAdded'}selected="selected"{/if}>{translate text='Sort by ' }Date Added</option>
					<option value="dateModified" {if $sort=='dateModified'}selected="selected"{/if}>{translate text='Sort by ' }Date Modified</option>
				</select>
			</div>
		</div>
		<h2>{$label}</h2>
		<div class="row">
			<div class="col-sm-4">
				{if $recordCount}
					{$recordCount} total objects.
				{/if}
			</div>
		</div>

		{if $displayType != 'basic' && ($recordEnd < $recordCount || $updateTimeLine)}
			{* Display selection of date ranges *}
			<div class="row">
				<div class="col-xs-12">
					<div class="btn-group btn-group-sm" role="group" aria-label="Select Dates" data-toggle="buttons">
						<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array('unknown', $smarty.request.dateFilter)} active{/if}">
							{if $displayType == 'map'}
								<input name="dateFilter" onchange="VuFind.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" value="all"><strong>All</strong><br/>({$recordCount})
							{elseif $displayType == 'timeline'}
								<input name="dateFilter" onchange="VuFind.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" value="all"><strong>All</strong><br/>({$recordCount})
							{/if}
						</label>
						{foreach from=$dateFacetInfo item=facet}
							<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array($facet.value, $smarty.request.dateFilter)} active{/if}">
								{if $displayType == 'map'}
									<input name="dateFilter" onchange="VuFind.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" autocomplete="off" value="{$facet.value}"><strong>{$facet.label}</strong><br/>({$facet.count})
								{elseif $displayType == 'timeline'}
									<input name="dateFilter" onchange="VuFind.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" autocomplete="off" value="{$facet.value}"{if !empty($smarty.request.dateFilter) && in_array($facet.value, $smarty.request.dateFilter)} checked="checked"{/if}><strong>{$facet.label}</strong><br/>({$facet.count})
								{/if}
							</label>
						{/foreach}
						{if $numObjectsWithUnknownDate > 0}
							<div class="btn-group btn-group-sm" role="group" aria-label="Unknown Date" data-toggle="buttons">
								<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array('unknown', $smarty.request.dateFilter)} active{/if}">
									{if $displayType == 'map'}
										<input name="dateFilter" onchange="VuFind.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" autocomplete="off" value="unknown"><strong>Unknown</strong><br/>({$numObjectsWithUnknownDate})
									{elseif $displayType == 'timeline'}
										<input name="dateFilter" onchange="VuFind.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" autocomplete="off" value="unknown"><strong>Unknown</strong><br/>({$numObjectsWithUnknownDate})
									{/if}
								</label>
							</div>
						{/if}
					</div>
				</div>
			</div>
		{/if}

		<div class="clearer"></div>
		<div id="results">
	{/if}

	{if $solrError}
		<div class="alert alert-danger">{$solrError}</div>
		<a href="{$solrLink}">Link to solr query</a>
	{/if}
	<div class="{if $showThumbnailsSorted}row{else}results-covers home-page-browse-thumbnails{/if}">
		{foreach from=$relatedObjects item=image}
			{if $showThumbnailsSorted}<div class="col-xs-6 col-sm-4 col-md-3">{/if}
				<figure class="{if $showThumbnailsSorted}browse-thumbnail-sorted{else}browse-thumbnail{/if}">
					<a href="{$image.link}" {if $image.title}data-title="{$image.title}"{/if} onclick="return VuFind.Archive.showObjectInPopup('{$image.pid|urlencode}'{if $image.recordIndex},{$image.recordIndex}{if $page},{$page}{/if}{/if})">
						<img src="{$image.image}" {if $image.title}alt="{$image.title}"{/if}>
						<figcaption class="explore-more-category-title">
							<strong>{$image.title|truncate:50} ({$image.dateCreated})</strong>
						</figcaption>
					</a>
				</figure>
			{if $showThumbnailsSorted}</div>{/if}
		{/foreach}
	</div>

	<div id="nextInsertPoint">
	{if $displayType == 'map'}
		{* {$recordCount-$recordEnd} more records to load *}
		{if $recordEnd < $recordCount}
			<a onclick="return VuFind.Archive.getMoreMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}')">
				<div class="row" id="more-browse-results">
					<img src="{img filename="browse_more_arrow.png"}" alt="Load More Search Results" title="Load More Search Results">
				</div>
			</a>
		{/if}
	{elseif $displayType == 'timeline'}
		{* {$recordCount-$recordEnd} more records to load *}
		{if $recordEnd < $recordCount}
			<a onclick="return VuFind.Archive.getMoreTimelineResults('{$exhibitPid|urlencode}')">
				<div class="row" id="more-browse-results">
					<img src="{img filename="browse_more_arrow.png"}" alt="Load More Search Results" title="Load More Search Results">
				</div>
			</a>
		{/if}
	{elseif $displayType == 'scroller'}
		{* {$recordCount-$recordEnd} more records to load *}
		{if $recordEnd < $recordCount}
			<a onclick="return VuFind.Archive.getMoreScrollerResults('{$exhibitPid|urlencode}')">
				<div class="row" id="more-browse-results">
					<img src="{img filename="browse_more_arrow.png"}" alt="Load More Search Results" title="Load More Search Results">
				</div>
			</a>
		{/if}
	{else}
		{if $recordEnd < $recordCount}
			{* {$recordCount-$recordEnd} more records to load *}
			<a onclick="return VuFind.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}')">
				<div class="row" id="more-browse-results">
					<img src="{img filename="browse_more_arrow.png"}" alt="Load More Search Results" title="Load More Search Results">
				</div>
			</a>
		{/if}
	{/if}
	</div>
{/strip}