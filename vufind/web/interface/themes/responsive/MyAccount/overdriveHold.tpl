{strip}
	<div class="result row" id="overDriveHold_{$record.overDriveId}">
		<div class="col-xs-12 col-sm-3">
			<div class="row">
				{*
				<div class="selectTitle col-xs-2">
					{if $section == 'available'}
						<input type="checkbox" name="availableholdselected[]" value="{$record.userId}~{$record.overDriveId}~{$record.overDriveId}" id="selected{$record.cancelId|escape:"url"}" class="titleSelect{$sectionKey} titleSelect"/>&nbsp;
					{else}
						<input type="checkbox" name="waitingholdselected[]" value="{$record.userId}~{$record.overDriveId}~{$record.overDriveId}" id="selected{$record.cancelId|escape:"url"}" class="titleSelect{$sectionKey} titleSelect"/>&nbsp;
					{/if}
				</div>
				*}
				<div class="col-xs-12 text-center">
					{if $record.recordId}
					<a href="{$record.linkUrl}">
						{/if}
						<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}"/>
						{if $record.recordId}
					</a>
					{/if}
				</div>
			</div>
		</div>

		<div class="col-xs-12 col-sm-9">
			<div class="row">
				<div class="col-xs-12">
					<span class="result-index">{$resultIndex})</span>&nbsp;
					{if $record.recordId != -1}
					<a href="{$record.linkUrl}" class="result-title notranslate">
						{/if}
						{if !$record.title}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation}{/if}
						{if $record.recordId != -1}
					</a>
					{/if}
					{if $record.subTitle}
						<div class="searchResultSectionInfo">
							{$record.subTitle|removeTrailingPunctuation}
						</div>
					{/if}
				</div>
			</div>

			<div class="row">
				<div class="resultDetails col-xs-12 col-sm-9">
					{if $record.author}
						<div class="row">
							<div class="result-label col-xs-3">{translate text='Author'}</div>
							<div class="col-xs-9 result-value">
								{if is_array($record.author)}
									{foreach from=$record.author item=author}
										<a href="{$path}/Author/Home?author={$author|escape:"url"}">{$author|highlight}</a>
									{/foreach}
								{else}
									<a href="{$path}/Author/Home?author={$record.author|escape:"url"}">{$record.author|highlight}</a>
								{/if}
							</div>
						</div>
					{/if}

					{if $record.format}
						<div class="row">
							<div class="result-label col-xs-3">{translate text='Format'}</div>
							<div class="col-xs-9 result-value">
								{implode subject=$record.format glue=", "}
							</div>
						</div>
					{/if}

					<div class="row">
						<div class="result-label col-xs-3">{translate text='On Hold For'}</div>
						<div class="col-xs-9 result-value">
							{$record.user}
						</div>
					</div>

					{if $section == 'available'}
					{* Available Hold *}

						<div class="row">
							<div class="result-label col-xs-3">{translate text='Expires'}</div>
							<div class="col-xs-9 result-value">
								{$record.expirationDate|date_format:"%b %d, %Y at %l:%M %p"}
							</div>
						</div>

					{else}
						{* Unavailable hold *}
						<div class="row">
							<div class="result-label col-xs-3">{translate text='Position'}</div>
							<div class="col-xs-9 result-value">
								{$record.holdQueuePosition} out of {$record.holdQueueLength}
							</div>
						</div>
					{/if}
				</div>
				<div class="col-xs-12 col-md-3">
					<div class="btn-group btn-group-vertical btn-block">
						{if $section == 'available'}
							<a href="#" onclick="return VuFind.OverDrive.doOverDriveCheckout('{$record.userId}', '{$record.overDriveId}');" class="btn btn-sm btn-primary">Checkout</a>
						{/if}
						<a href="#" onclick="return VuFind.OverDrive.cancelOverDriveHold('{$record.userId}', '{$record.overDriveId}');" class="btn btn-sm btn-warning">Cancel Hold</a>
					</div>

				</div>
			</div>
		</div>
	</div>
{/strip}