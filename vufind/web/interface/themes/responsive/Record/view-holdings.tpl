{strip}
{if $offline}
	<div class="warning">The circulation system is currently offline.  Holdings information is based on information from before the system went offline.</div>
{/if}
{assign var=lastSection value=''}
{if isset($holdings) && count($holdings) > 0}
	{foreach from=$holdings item=holding1}
		{foreach from=$holding1 item=holding}
			{if $lastSection != $holding.section}
				{if $lastSection != ""}
								</div>
							</div>
						</div>
					</div>
				{/if}
				{if strlen($holding.section) > 0}
					<div class="accordion-group">
						<div class="accordion-heading" id="holdings-header-{$holding.section|replace:' ':'_'}">
							<a class='accordion-toggle' data-toggle="collapse" data-target="#holdings-section-{$holding.section|replace:' ':'_'}">{$holding.section}</a>
						</div>
						<div id="holdings-section-{$holding.section|replace:' ':'_'}" class="accordion-body collapse {if $holding.sectionId <=5}in{/if}">
							<div class="accordion-inner">
								<div class="striped">
				{/if}
				{assign var=lastSection value=$holding.section}
			{/if}

			<div class="row">
				<div class="col-xs-5">
					<strong>
					{$holding.location|escape}
					{if $holding.locationLink} (<a href='{$holding.locationLink}' target='_blank'>Map</a>){/if}
					</strong>
				</div>
				<div class="holdingsCallNumber col-xs-4">
					{$holding.callnumber|escape}
					{if $holding.link}
						{foreach from=$holding.link item=link}
							<a href='{$link.link}' target='_blank'>{$link.linkText}</a><br />
						{/foreach}
					{/if}
				</div>

				<div class="col-xs-3">
					{if $holding.reserve == "Y"}
						{translate text="On Reserve - Ask at Circulation Desk"}
					{else}
						{if $holding.availability}
							<span class="available">{$holding.statusfull}{if $holding.holdable == 0 && $showHoldButton} <label class='notHoldable' title='{$holding.nonHoldableReason}'>(Not Holdable)</label>{/if}</span>
						{else}
							<span class="checkedout">{$holding.statusfull}{if $holding.holdable == 0 && $showHoldButton} <label class='notHoldable' title='{$holding.nonHoldableReason}'>(Not Holdable)</label>{/if}</span>
						{/if}
					{/if}
				</div>
			</div>
			{/foreach}
		{/foreach}
		</div>
	{elseif isset($issueSummaries) && count($issueSummaries) > 0}
		{* Display Issue Summaries *}
		{foreach from=$issueSummaries item=issueSummary name=summaryLoop}
			<tr class='issue-summary'>
				<td colspan='3' class='issue-summary-row'>
					{if $issueSummary.location}
						<div class='issue-summary-location'>{$issueSummary.location}</div>
					{/if}
					<div class='issue-summary-details'>
					{if $issueSummary.identity}
						<div class='issue-summary-line'><strong>Identity:</strong> {$issueSummary.identity}</div>
					{/if}
					{if $issueSummary.callNumber}
						<div class='issue-summary-line'><strong>Call Number:</strong> {$issueSummary.callNumber}</div>
					{/if}
					{if $issueSummary.latestReceived}
						<div class='issue-summary-line'><strong>Latest Issue Received:</strong> {$issueSummary.latestReceived}</div>
					{/if}
					{if $issueSummary.libHas}
						<div class='issue-summary-line'><strong>Library Has:</strong> {$issueSummary.libHas}</div>
					{/if}

					{if count($issueSummary.holdings) > 0}
					<span id='showHoldings-{$smarty.foreach.summaryLoop.iteration}' class='btn btn-sm btn-info'>Show Individual Issues</span>
					<script	type="text/javascript">
						$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').click(function(){literal} { {/literal}
						 if (!$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').hasClass('expanded')){literal} { {/literal}
								$('#issue-summary-holdings-{$smarty.foreach.summaryLoop.iteration}').slideDown();
								$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').html('Hide Individual Issues');
								$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').addClass('expanded');
							 {literal} }else{ {/literal}
								$('#issue-summary-holdings-{$smarty.foreach.summaryLoop.iteration}').slideUp();
								$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').removeClass('expanded');
								$('#showHoldings-{$smarty.foreach.summaryLoop.iteration}').html('Show Individual Issues');
							 {literal} } {/literal}
						 {literal} }); {/literal}
					</script>
					{/if}
					{if $showCheckInGrid && $issueSummary.checkInGridId}
					&nbsp;
					<span id='showCheckInGrid-{$smarty.foreach.summaryLoop.iteration}' class='btn btn-sm btn-info'>Show Check-in Grid</span>
					<script	type="text/javascript">
						$('#showCheckInGrid-{$smarty.foreach.summaryLoop.iteration}').click(function(){literal} { {/literal}
							VuFind.Account.ajaxLightbox('{$path}/Record/{$id}/CheckInGrid?lookfor={$issueSummary.checkInGridId}', false);
						{literal} }); {/literal}
					</script>
					{/if}
					</div>

					{if count($issueSummary.holdings) > 0}
					<table id='issue-summary-holdings-{$smarty.foreach.summaryLoop.iteration}' class='issue-summary-holdings' style='display:none;'>
						{* Display all holdings within this summary. *}
						{foreach from=$issueSummary.holdings item=holding}
						<tr class='holdingsLine'>
						<td style = "padding-bottom:5px;"><span><strong>
						 {$holding.location|escape}
						 {if $holding.locationLink} (<a href='{$holding.locationLink}' target='_blank'>Map</a>){/if}
						 </strong></span></td>
						 <td style = "padding-bottom:5px;">
						 {$holding.callnumber|escape}
						 {if $holding.link}
							{foreach from=$holding.link item=link}
								<a href='{$link.link}' target='_blank'>{$link.linkText}</a><br />
							{/foreach}
						 {/if}
						 </td>

						 <td style = "padding-bottom:5px;">
							{if $holding.reserve == "Y"}
								{translate text="On Reserve - Ask at Circulation Desk"}
							{else}
								{if $holding.availability}
									<span class="available">{$holding.statusfull}{if $holding.holdable == 0 && $showHoldButton} <label class='notHoldable' title='{$holding.nonHoldableReason}'>(Not Holdable)</label>{/if}</span>
								{else}
									<span class="checkedout">{$holding.statusfull}{if $holding.holdable == 0 && $showHoldButton} <label class='notHoldable' title='{$holding.nonHoldableReason}'>(Not Holdable)</label>{/if}</span>
								{/if}
							{/if}
						 </td>
						 </tr>
						{/foreach}
					</table>
					{/if}
				</td>
			</tr>
	{/foreach}
{else}
	No Copies Found
{/if}
{/strip}