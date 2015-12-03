{if !empty($addThis)}
<script type="text/javascript" src="https://s7.addthis.com/js/250/addthis_widget.js?pub={$addThis|escape:"url"}"></script>
{/if}
<script type="text/javascript">
{literal}$(document).ready(function(){{/literal}
	//VuFind.Record.loadHoldingsInfo('{$activeRecordProfileModule}', '{$id|escape:"url"}', '{$shortId}', 'VuFind');
	VuFind.GroupedWork.loadEnrichmentInfo('{$recordDriver->getPermanentId()|escape:"url"}');
	VuFind.GroupedWork.loadReviewInfo('{$recordDriver->getPermanentId()|escape:"url"}');
	{if $enablePospectorIntegration == 1}
		VuFind.Prospector.loadRelatedProspectorTitles('{$recordDriver->getPermanentId()|escape:"url"}');
	{/if}
{literal}});{/literal}
</script>
{strip}
	<div class="col-xs-12">
		{* Display Title *}
		<h2>
			{$recordDriver->getTitle()|escape}
			{if $recordDriver->getTitleSection()} {$recordDriver->getTitleSection()|escape}{/if}
			{if $recordDriver->getFormats()}
				<br/><small>({implode subject=$recordDriver->getFormats() glue=", "})</small>
			{/if}
		</h2>

		{if $error}<p class="error">{$error}</p>{/if}

		<div class="row">

			<div id="main-content" class="col-xs-12">
				<div class="row">

					<div id="record-details-column" class="col-xs-12 col-sm-9">
						{include file="Record/view-title-details.tpl"}
					</div>

					<br>

					<div id="recordTools" class="col-xs-12 col-sm-3">
						{include file="Record/result-tools.tpl" showMoreInfo=false summShortId=$shortId module=$activeRecordProfileModule summId=$id summTitle=$title recordUrl=$recordUrl}
					</div>
				</div>

				<br/>

				{include file=$moreDetailsTemplate}

			</div>
		</div>
	</div>
	<span class="Z3988" title="{$recordDriver->getOpenURL()|escape}" style="display:none">&nbsp;</span>
{/strip}
