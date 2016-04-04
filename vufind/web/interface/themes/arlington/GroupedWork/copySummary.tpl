{strip}
	{if $format == 'Journal' || $format == 'Newspaper' || $format == 'Print Periodical' || $format == 'Magazine'}
		{if $recordUrl}
			<div class="itemSummary">
				&nbsp;&nbsp;<a href="{$recordUrl}#copiesPanelBody">
					View all copies
				</a>
			</div>
		{/if}
	{else}
		{if count($summary) > 0}
			{*<div class="itemSummary row">
				<div class="col-xs-2 nobreak">
					<strong>Available</strong>
				</div>
				<div class="col-xs-6">
					<strong>Location</strong>
				</div>
				<div class="col-xs-4">
					<strong>Call Number</strong>
				</div>
			</div>*}
			{assign var=numDefaultItems value="0"}
			{assign var=numRowsShown value="0"}
			{foreach from=$summary item="item"}
				{if $item.displayByDefault && $numRowsShown<3}
					<div class="itemSummary row">
						<div class="col-xs-7">
							<span class="notranslate"><strong>{$item.shelfLocation}</strong>
								{if $item.availableCopies > 1}
								&nbsp;has&nbsp;{$item.availableCopies}
								{/if}
							</span>
						</div>
						<div class="col-xs-4">
							{if $item.isEContent == false}
								<span class="notranslate"><strong>{$item.callNumber}</strong></span>
							{/if}
						</div>
					</div>
					{assign var=numDefaultItems value=$numDefaultItems+$item.totalCopies}
					{assign var=numRowsShown value=$numRowsShown+1}
				{/if}
			{/foreach}
			{assign var=numRemainingCopies value=$totalCopies-$numDefaultItems}
			{if $numRemainingCopies > 0}
				<div class="itemSummary">
					&nbsp;&nbsp;<a href="#" onclick="return VuFind.showElementInPopup('Copy Summary', '#itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}');">
						View all copies
					</a>
				</div>
				<div id="itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}" class="itemSummaryPopup" style="display: none">
					<table class="table table-striped table-condensed itemSummaryTable">
						<thead>
						<tr>
							<th>Avail. Copies</th>
							<th>Location</th>
							<th>Call #</th>
						</tr>
						</thead>
						<tbody>
						{assign var=numRowsShown value=0}
						{foreach from=$summary item="item"}
							<tr {if $item.availableCopies}class="available" {/if}>
								{if $item.onOrderCopies > 0}
									<td>{$item.onOrderCopies} on order</td>
								{else}
									<td>{$item.availableCopies} of {$item.totalCopies}</td>
								{/if}
								<td class="notranslate">{$item.shelfLocation}</td>
								<td class="notranslate">
									{if !$item.isEContent}
										{$item.callNumber}
									{/if}
								</td>
							</tr>
						{/foreach}
						</tbody>
					</table>
				</div>
			{/if}
		{/if}
	{/if}
{/strip}