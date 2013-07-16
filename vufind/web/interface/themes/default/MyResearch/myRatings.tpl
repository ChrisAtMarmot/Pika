{if (isset($title)) }
	<script type="text/javascript">
		alert("{$title}");
	</script>
{/if}
<script type="text/javascript" src="{$path}/js/tablesorter/jquery.tablesorter.min.js"></script>
<div id="page-content" class="content">
<div id="sidebar">
	{include file="MyResearch/menu.tpl"}
	{include file="Admin/menu.tpl"}
</div>

<div id="main-content">
	{if $user->cat_username}
		<div class="resulthead">
			<div class="myAccountTitle">{translate text='My Ratings'}</div>
			{if $userNoticeFile}
				{include file=$userNoticeFile}
			{/if}

			<div class="page">
				{if $ratings}
					<table class="myAccountTable" id="myRatingsTable">
						<thead>
							<tr>
								<th>{translate text='Date'}</th>
								<th>{translate text='Title'}</th>
								<th>{translate text='Author'}</th>
								<th>{translate text='Format'}</th>
								<th>{translate text='Rating'}</th>
								<th>&nbsp;</th>
							</tr>
						</thead>
						<tbody>

							{foreach from=$ratings item=record name="recordLoop" key=recordKey item=rating}

								<tr id="record{$record.recordId|escape}" class="result {if ($smarty.foreach.recordLoop.iteration % 2) == 0}alt{/if} record{$smarty.foreach.recordLoop.iteration}">
									<td>
										{if isset($rating.dateRated)}
											{$rating.dateRated|date_format}
										{/if}
									</td>
									<td class="myAccountCell">
										<a href='{$rating.link}'>{$rating.title}</a>
									</td>
									<td class="myAccountCell">
										{$rating.author}
									</td>
									<td class="myAccountCell">
										{$rating.format}
									</td>
									<td class="myAccountCell">
										{if $rating.source == 'VuFind'}
											{include file='Record/title-rating.tpl' shortId=$rating.shortId recordId=$rating.fullId ratingData=$rating.ratingData}
										{else}
											{include file='EcontentRecord/title-rating.tpl' shortId=$rating.shortId recordId=$rating.shortId ratingData=$rating.ratingData}
										{/if}
									</td>
									<td class="myAccountCell">
										<span class="button" onclick="return clearUserRating('{$rating.source}', '{$rating.fullId}', '{$rating.shortId}');">{translate text="Clear"}</span>
									</td>
								</tr>
							{/foreach}
							</tbody>
						</table>
						<script type="text/javascript">
							$(document).ready(function () {literal} {
								$("#myRatingsTable")
								 .tablesorter({
										cssAsc: 'sortAscHeader',
										cssDesc: 'sortDescHeader',
										cssHeader: 'unsortedHeader',
										headers: { 0: { sorter: 'date' }, 5: { sorter: false } },
										sortList: [[0, 1]]
									})
								});
							{/literal}
						</script>
					{else}
						You have not rated any titles yet.
					{/if}

				{if $notInterested}
					<div class="myAccountTitle">{translate text='Not Interested'}</div>
					<table class="myAccountTable" id="notInterestedTable">
						<thead>
							<tr>
								<th>Date</th>
								<th>Title</th>
								<th>Author</th>
								<th>&nbsp;</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$notInterested item=notInterestedTitle}
								<tr id="notInterested{$notInterestedTitle.id}">
									<td>{$notInterestedTitle.dateMarked|date_format}</td>
									<td><a href="{$notInterestedTitle.link}">{$notInterestedTitle.title}</a></td>
									<td>{$notInterestedTitle.author}</td>
									<td><span class="button" onclick="return clearNotInterested('{$notInterestedTitle.id}');">Clear</span></td>
								</tr>
							{/foreach}
						</tbody>
					</table>
					<script type="text/javascript">
						$(document).ready(function () {literal} {
							$("#notInterestedTable")
											.tablesorter({
												cssAsc: 'sortAscHeader',
												cssDesc: 'sortDescHeader',
												cssHeader: 'unsortedHeader',
												headers: { 0: { sorter: 'date' }, 3: { sorter: false } },
												sortList: [[0, 1]]
											})
						});
						{/literal}
					</script>
				{/if}
				</div>
		</div>
	{else}
		<div class="page">
			You must login to view this information. Click <a href="{$path}/MyResearch/Login">here</a> to login.
		</div>
	{/if}
</div>
</div>