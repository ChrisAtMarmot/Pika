{strip}
	<div id="page-content" class="col-xs-12">
	{if $importResults}
		<h2>
			Congratulations, we imported {$importResults.totalTitles} title{if $importResults.totalTitles !=1}s{/if} from {$importResults.totalLists} list{if $importResults.totalLists != 1}s{/if}.
		</h2>
	{else}
		<h2>
			Sorry your lists could not be imported
		</h2>
	{/if}
	{if $importResults.errors}
		<div class="errors">We were not able to import the following titles. You can search the catalog for these titles to re-add them to your lists.<br />
			<ul>
				{foreach from=$importResults.errors item=error}
					<li>{$error}</li>
				{/foreach}
			</ul>
		</div>
	{/if}
	</div>
{/strip}