<strip>
<button onclick="return VuFind.GroupedWork.reloadCover('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Cover</button>
<button onclick="return VuFind.GroupedWork.reloadEnrichment('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Enrichment</button>
{if $loggedIn && (array_key_exists('opacAdmin', $userRoles) || array_key_exists('catalogging', $userRoles))}
	<button onclick="return VuFind.GroupedWork.forceReindex('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Reindex</button>
	<button onclick="return VuFind.GroupedWork.forceRegrouping('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Regrouping</button>
{/if}
{if $loggedIn && $enableArchive && (array_key_exists('opacAdmin', $userRoles) || array_key_exists('archives', $userRoles))}
	<button onclick="return VuFind.GroupedWork.reloadIslandora('{$recordDriver->getUniqueID()}')" class="btn btn-sm btn-default">Clear Islandora Cache</button>
{/if}


	{* QR Code *}
{if $showQRCode}
	<div id="record-qr-code" class="text-center hidden-xs visible-md"><img src="{$recordDriver->getQRCodeUrl()}" alt="QR Code for Record"></div>
{/if}

<h4>Grouping Information</h4>
<table class="table-striped table table-condensed notranslate">
	<tr>
		<th>Grouped Work ID</th>
		<td>{$recordDriver->getPermanentId()}</td>
	</tr>
	{foreach from=$groupedWorkDetails key='field' item='value'}
	<tr>
		<th>{$field|escape}</th>
		<td>
			{$value}
		</td>
	</tr>
	{/foreach}
</table>

<h4>Solr Details</h4>
<table class="table-striped table table-condensed notranslate">
	{foreach from=$details key='field' item='values'}
		<tr>
			{if strpos($field, "scoping_details") !== false}
				<td colspan="2">
					<strong>{$field|escape}</strong>
				<table id="scoping_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-x: scroll">{*TODO: style rule should go in css *}
					<tr>
						<th>Bib Id</th><th>Item Id</th><th>Grouped Status</th><th>Status</th><th>Locally Owned</th><th>Available</th><th>Holdable</th><th>Bookable</th><th>In Library Use Only</th><th>Library Owned</th><th>Holdable PTypes</th><th>Bookable PTypes</th><th>Local Url</th>
					</tr>
					{foreach from=$values item="item"}
					<tr>
						{*{assign var="item" value=$item|rtrim:"|"}*}
						{assign var="details" value="|"|explode:$item}
						{foreach from=$details item='detail'}
						{*{foreach from=explode($values, "|") item='detail'}*}
						<td>{$detail|replace:',':', '}</td>
					{/foreach}
					</tr>
					{/foreach}
				</table>
				</td>
			{else}
			<th>{$field|escape}</th>
			<td>
				{implode subject=$values glue=', ' sort=true}
			</td>
			{/if}
		</tr>
	{/foreach}
</table>
</strip>