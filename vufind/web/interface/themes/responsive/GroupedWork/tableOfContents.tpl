{strip}
	<div id="tableOfContentsPlaceholder" style="display:none"></div>

	{if $tableOfContents}
		{foreach from=$tableOfContents item=note}
			<div class="row">
				<div class="col-xs-12">{$note}</div>
			</div>
		{/foreach}
		<script type="text/javascript">
			VuFind.GroupedWork.hasTableOfContentsInRecord = true;
		</script>
	{/if}
{/strip}