{strip}
	<div id="record{$record.source}_{$record.id|escape}" class="result row">
		<div class="col-xs-12 col-sm-3">
			<div class="row">
				<div class="selectTitle col-xs-2">
					{if !isset($record.renewable) || $record.renewable == true}
					<input type="checkbox" name="selected[{$record.renewIndicator}]" class="titleSelect" id="selected{$record.itemid}"/>
					{/if}
				</div>
				<div class="col-xs-10 text-center coverColumn">
					{if $user->disableCoverArt != 1}
						{if $record.id && $record.coverUrl}
							<a href="{$record.link}">
								<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}"/>
							</a>
						{/if}
					{/if}
				</div>
			</div>
		</div>
		<div class="col-xs-12 col-sm-9">
			<div class="row">
				<div class="col-xs-12">
					<span class="result-index">{$resultIndex})</span>&nbsp;
					{if $record.id}
						<a href="{$record.link}" class="result-title notranslate">
					{/if}
					{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation|truncate:180:"..."|highlight:$lookfor}{/if}
					{if $record.id}
						</a>
					{/if}
					{if $record.title2}
						<div class="searchResultSectionInfo">
							{$record.title2|removeTrailingPunctuation|truncate:180:"..."|highlight:$lookfor}
						</div>
					{/if}
				</div>
			</div>
			<div class="row">
				<div class="resultDetails col-xs-12 col-md-9">
					{if $record.author}
						<div class="row">
							<div class="result-label col-md-3">{translate text='Author'}</div>
							<div class="col-md-9 result-value">
								{if is_array($record.author)}
									{foreach from=$record.author item=author}
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
							<div class="col-md-9 result-value">{$record.publicationDate|escape}</div>
						</div>
					{/if}

					{if $showOut}
						<div class="row">
							<div class="result-label col-md-3">{translate text='Checked Out'}</div>
							<div class="col-md-9 result-value">{$record.checkoutdate|date_format}</div>
						</div>
					{/if}

					<div class="row">
						<div class="result-label col-md-3">{translate text='Format'}</div>
						<div class="col-md-9 result-value">{$record.format}</div>
					</div>

					{if $showRatings && $record.groupedWorkId && $record.ratingData}
							<div class="row">
								<div class="result-label col-md-3">Rating&nbsp;</div>
								<div class="col-md-9 result-value">
									{include file="GroupedWork/title-rating.tpl" ratingClass="" id=$record.groupedWorkId ratingData=$record.ratingData showNotInterested=false}
								</div>
							</div>
					{/if}

					<div class="row">
						<div class="result-label col-md-3">{translate text='Due'}</div>
						<div class="col-md-9 result-value">
							{$record.duedate|date_format}
							{if $record.overdue}
								<span class='overdueLabel'> OVERDUE</span>
							{elseif $record.daysUntilDue == 0}
								<span class='dueSoonLabel'> (Due today)</span>
							{elseif $record.daysUntilDue == 1}
								<span class='dueSoonLabel'> (Due tomorrow)</span>
							{elseif $record.daysUntilDue <= 7}
								<span class='dueSoonLabel'> (Due in {$record.daysUntilDue} days)</span>
							{/if}
							{if $record.fine}
								<span class='overdueLabel'> FINE {$record.fine}</span>
							{/if}
						</div>
					</div>

					{if $showRenewed && $record.renewCount}
						<div class="row">
							<div class="result-label col-md-3">{translate text='Renewed'}</div>
							<div class="col-md-9 result-value">
								{$record.renewCount} times
								{if $record.renewMessage}
									<div class='alert {if $record.renewResult == true}alert-success{else}alert-error{/if}'>
										{$record.renewMessage|escape}
									</div>
								{/if}
							</div>
						</div>
					{/if}

					{if $showWaitList}
						<div class="row">
							<div class="result-label col-md-3">{translate text='Wait List'}</div>
							<div class="col-md-9 result-value">
								{* Wait List goes here *}
								{$record.holdQueueLength}
							</div>
						</div>
					{/if}
				</div>

				<div class="col-xs-12 col-md-3">
					<div class="btn-group btn-group-vertical btn-block">
						{if !isset($record.renewable) || $record.renewable == true}
							{*<a href="#" onclick="$('#selected{$record.itemid}').attr('checked', 'checked');return VuFind.Account.renewSelectedTitles();" class="btn btn-sm btn-primary">Renew</a>*}
							<a href="#" onclick="return VuFind.Account.renewTitle('{$record.renewIndicator}');" class="btn btn-sm btn-primary">{translate text='Renew'}</a>
						{else}
							Sorry, this title cannot be renewed
						{/if}
					</div>

					{* Include standard tools *}
					{* include file='Record/result-tools.tpl' id=$record.id shortId=$record.shortId ratingData=$record.ratingData *}
				</div>
			</div>
		</div>
	</div>
{/strip}