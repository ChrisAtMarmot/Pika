{strip}
	<table class="table table-striped table-condensed">
		<thead>
		<tr>
			{display_if_inconsistent array=$relatedRecords key="publicationDate"}
				<th>Pub. Date</th>
			{/display_if_inconsistent}
			{if $relatedManifestation.format == 'eBook' || $relatedManifestation.format == 'eAudiobook' || $relatedManifestation.format == 'eMagazine'}
				<th>Source</th>
			{/if}
			{assign var="displayEdition" value=false}
			{foreach from=$relatedRecords item=relatedRecord}
				{if !empty($relatedRecord.edition)}
					{assign var="displayEdition" value=true}
				{/if}
			{/foreach}
			{if $displayEdition}
				<th>Edition</th>
			{/if}
			{display_if_inconsistent array=$relatedRecords key="publisher"}
				<th>Publisher</th>
			{/display_if_inconsistent}
			{display_if_inconsistent array=$relatedRecords key="physical"}
				<th>Phys Desc.</th>
			{/display_if_inconsistent}
			{display_if_inconsistent array=$relatedRecords key="language"}
				<th>Language</th>
			{/display_if_inconsistent}
			<th>Availability</th>
			<th></th>
		</tr>
		</thead>
		{foreach from=$relatedRecords item=relatedRecord}
			<tr>
				{* <td>
				{$relatedRecord.holdRatio}
				</td> *}
				{display_if_inconsistent array=$relatedRecords key="publicationDate"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.publicationDate}</a></td>
				{/display_if_inconsistent}
				{if $relatedManifestation.format == 'eBook' || $relatedManifestation.format == 'eAudiobook' || $relatedManifestation.format == 'eMagazine'}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.eContentSource}</a></td>
				{/if}
				{if $displayEdition}
					<td>{$relatedRecord.edition}</td>
				{/if}
				{display_if_inconsistent array=$relatedRecords key="publisher"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.publisher}</a></td>
				{/display_if_inconsistent}
				{display_if_inconsistent array=$relatedRecords key="physical"}
					<td><a href="{$relatedRecord.url}">{$relatedRecord.physical}</a></td>
				{/display_if_inconsistent}
				{display_if_inconsistent array=$relatedRecords key="language"}
					<td><a href="{$relatedRecord.url}">{implode subject=$relatedRecord.language glue=","}</a></td>
				{/display_if_inconsistent}
				<td>
					{include file='GroupedWork/statusIndicator.tpl' statusInformation=$relatedRecord}

					{include file='GroupedWork/copySummary.tpl' summary=$relatedRecord.itemSummary totalCopies=$relatedRecord.copies itemSummaryId=$relatedRecord.id format=$relatedManifestation.format recordViewUrl=$relatedRecord.url}

					{if $relatedRecord.usageRestrictions}
						<br>{$relatedRecord.usageRestrictions}
					{/if}
				</td>
				<td>
					<div class="btn-group btn-group-vertical btn-group-sm">
						<a href="{$relatedRecord.url}" class="btn btn-sm btn-info">More Info</a>
						{foreach from=$relatedRecord.actions item=curAction}
							<a href="{$curAction.url}" {if $curAction.onclick}onclick="{$curAction.onclick}"{/if} class="btn btn-sm btn-default" {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</a>
						{/foreach}
					</div>
				</td>
			</tr>
		{/foreach}
	</table>
{/strip}