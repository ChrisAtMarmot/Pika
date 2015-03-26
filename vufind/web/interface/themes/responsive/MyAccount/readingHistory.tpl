<div class="col-xs-12">
{if $user->cat_username}
	{strip}

	{if $profile.web_note}
		<div class="row">
			<div id="web_note" class="alert alert-info text-center col-xs-12">{$profile.web_note}</div>
		</div>
	{/if}
	<h2>{translate text='My Reading History'} {if $historyActive == true}<small><a id='readingListWhatsThis' href="#" onclick="$('#readingListDisclaimer').toggle();return false;">(What's This?)</a></small>{/if}</h2>

	<div class="row">
	{if $userNoticeFile}
		{include file=$userNoticeFile}
	{/if}
		<div id='readingListDisclaimer' {if $historyActive == true}style='display: none'{/if} class="alert alert-info">
		{/strip}
			{translate text="ReadingHistoryNotice"}
		{strip}
		</div>
	</div>

	<form id='readingListForm' action ="{$fullPath}" class="form-inline">
		<div class="row">
			<input name='readingHistoryAction' id='readingHistoryAction' value='' type='hidden' />
			<div id="readingListActionsTop" class="col-xs-12">
				<div class="btn-group btn-group-sm">
					{if $historyActive == true}
						{if $transList}
							<a class="btn btn-sm btn-warning" onclick='return VuFind.Account.ReadingHistory.deletedMarkedAction()' href="#">Delete Marked</a>
							<a class="btn btn-sm btn-danger" onclick='return VuFind.Account.ReadingHistory.deleteAllAction()' href="#">Delete All</a>
						{/if}
						<a class="btn btn-sm btn-info" onclick="return VuFind.Account.ReadingHistory.exportListAction();">Export To Excel</a>
						<a class="btn btn-sm btn-danger" onclick="return VuFind.Account.ReadingHistory.optOutAction()" href="#">Stop Recording My Reading History</a>
					{else}
						<a class="btn btn-sm btn-primary" onclick='return VuFind.Account.ReadingHistory.optInAction()' href="#">Start Recording My Reading History</a>
					{/if}
				</div>
			</div>

			<hr/>

			{if $transList}
				<div id="pager" class="col-xs-12">
					<div class="row">
						<div class="col-sm-6 form-group" id="recordsPerPage">
							<label for="pagesize" class="control-label">Records Per Page&nbsp;</label>
							<select id="pagesize" class="pagesize form-control input-sm" onchange="VuFind.changePageSize()">
								<option value="10" {if $recordsPerPage == 10}selected="selected"{/if}>10</option>
								<option value="25" {if $recordsPerPage == 25}selected="selected"{/if}>25</option>
								<option value="50" {if $recordsPerPage == 50}selected="selected"{/if}>50</option>
								<option value="75" {if $recordsPerPage == 75}selected="selected"{/if}>75</option>
								<option value="100" {if $recordsPerPage == 100}selected="selected"{/if}>100</option>
							</select>
						</div>
						<div class="col-sm-6 col-lg-5 form-group" id="sortOptions">
							<label for="sortMethod" class="control-label">Sort By&nbsp;</label>
							<select class="sortMethod form-control" id="sortMethod" name="accountSort" onchange="VuFind.Account.changeAccountSort($(this).val())">
								{foreach from=$sortOptions item=sortOptionLabel key=sortOption}
									<option value="{$sortOption}" {if $sortOption == $defaultSortOption}selected="selected"{/if}>{$sortOptionLabel}</option>
								{/foreach}
							</select>
						</div>
						<div class="col-sm-12 col-lg-2 form-group" id="coverOptions">
							<label for="hideCovers">Hide Covers <input id="hideCovers" type="checkbox" onclick="$('.imageCell').toggle();" /></label>
						</div>
					</div>
				</div>

				<div class="row hidden-xs">
					<div class="col-sm-1">
						<input id='selectAll' type='checkbox' onclick="VuFind.toggleCheckboxes('.titleSelect', '#selectAll');" title="Select All/Deselect All"/>
					</div>
					<div class="col-sm-2">
						{translate text='Cover'}
					</div>
					<div class="col-sm-7">
						{translate text='Title'}
					</div>
					<div class="col-sm-2">
						{translate text='Checked Out'}
					</div>
				</div>

				<div class="striped">
					{foreach from=$transList item=record name="recordLoop" key=recordKey}
						<div class="row">
							<div class="col-sm-1">
								<input type="checkbox" name="selected[{$record.recordId|escape:"url"}]" class="titleSelect" value="rsh{$record.itemindex}" id="rsh{$record.itemindex}" />
							</div>
							<div class="col-sm-2">
								{if $record.coverUrl}
									<a href="{$record.linkUrl}" id="descriptionTrigger{$record.recordId|escape:"url"}">
										<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}"/>
									</a>
								{/if}
							</div>
							<div class="col-sm-7">
								<div class="row">
									<div class="col-xs-12">
										<strong>
											{if $record.recordId && $record.linkUrl}
												<a href="{$record.linkUrl}" class="title">{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation}{/if}</a>
											{else}
												{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation}{/if}
											{/if}
											{if $record.title2}
												<div class="searchResultSectionInfo">
													{$record.title2|removeTrailingPunctuation|truncate:180:"..."|highlight:$lookfor}
												</div>
											{/if}
										</strong>
									</div>
								</div>

								{if $record.author}
									<div class="row">
										<div class="result-label col-md-3">{translate text='Author'}</div>
										<div class="col-md-9 result-value">
											{if is_array($record.author)}
												{foreach from=$summAuthor item=author}
													<a href="{$path}/Author/Home?author={$author|escape:"url"}">{$author|highlight:$lookfor}</a>
												{/foreach}
											{else}
												<a href="{$path}/Author/Home?author={$record.author|escape:"url"}">{$record.author|highlight:$lookfor}</a>
											{/if}
										</div>
									</div>
								{/if}

								{if $record.publicationDate}
									<div class="row">
										<div class="result-label col-md-3">{translate text='Published'}</div>
										<div class="col-md-9 result-value">
											{$record.publicationDate|escape}
										</div>
									</div>
								{/if}

								<div class="row">
									<div class="result-label col-md-3">{translate text='Format'}</div>
									<div class="col-md-9 result-value">
										{implode subject=$record.format glue=", "}
									</div>
								</div>

								{if $showRatings == 1}
									{if $record.recordId != -1 && $record.ratingData}
										<div class="row">
											<div class="result-label col-md-3">Rating&nbsp;</div>
											<div class="col-md-9 result-value">
												{include file="GroupedWork/title-rating.tpl" ratingClass="" summId=$record.permanentId ratingData=$record.ratingData showNotInterested=false}
											</div>
										</div>
									{/if}
								{/if}
							</div>

							<div class="col-sm-2">
								{if is_numeric($record.checkout)}
									{$record.checkout|date_format}
								{else}
									{$record.checkout|escape}
								{/if}
								{if $record.lastCheckout} to {$record.lastCheckout|escape}{/if}
								{* Do not show checkin date since historical data from initial import is not correct.
								{if $record.checkin} to {$record.checkin|date_format}{/if}
								*}
							</div>
						</div>
					{/foreach}
				</div>

				<hr/>

				<div class="row">
					<div id="readingListActionsBottom" class="btn-group btn-group-sm">
						{if $historyActive == true}
							{if $transList}
								<a class="btn btn-sm btn-default" onclick="return VuFind.Account.ReadingHistory.deletedMarkedAction()" href="#">Delete Marked</a>
								<a class="btn btn-sm btn-default" onclick="return VuFind.Account.ReadingHistory.deleteAllAction()" href="#">Delete All</a>
							{/if}
							{* <button value="exportList" class="RLexportList" onclick='return exportListAction()'>Export Reading History</button> *}
							<a class="btn btn-sm btn-default" onclick='return VuFind.Account.ReadingHistory.optOutAction()' href="#">Stop Recording My Reading History</a>
						{else}
							<a class="btn btn-sm btn-default" onclick='return VuFind.Account.ReadingHistory.optInAction()' href="#">Start Recording My Reading History</a>
						{/if}
					</div>
				</div>

				{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
			{elseif $historyActive == true}
				{* No Items in the history, but the history is active *}
				You do not have any items in your reading list.	It may take up to 3 hours for your reading history to be updated after you start recording your history.
			{/if}
			</div>
		</form>
	{/strip}
{else}
	<div class="page">
		You must login to view this information. Click <a href="{$path}/MyAccount/Login">here</a> to login.
	</div>
{/if}
</div>
