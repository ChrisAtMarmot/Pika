{strip}
	{* New Search Box *}
	{if !$horizontalSearchBar}
		{include file="Search/searchbox-home.tpl"}
	{/if}

	{* Navigate within the results *}
	<div class="search-results-navigation text-center">
		{if $lastsearch}
			<div id="returnToSearch">
				<a href="{$lastsearch|escape}#record{$id|escape:"url"}">&laquo; {translate text="Return to Search Results"|strtoupper}</a>
			</div>
		{/if}
		<div class="btn-group">
			{if isset($previousId)}
				<div id="previousRecordLink" class="btn"><a href="{$path}/{$previousType}/{$previousId|escape:"url"}?searchId={$searchId}&amp;recordIndex={$previousIndex}&amp;page={if isset($previousPage)}{$previousPage}{else}{$page}{/if}" title="{if !$previousTitle}{translate text='Previous'}{else}{$previousTitle|truncate:180:"..."|replace:"&":"&amp;"}{/if}"><img src="{$path}/interface/themes/default/images/prev.png" alt="Previous Record"/></a></div>
			{/if}
			{if isset($nextId)}
				<div id="nextRecordLink" class="btn"><a href="{$path}/{$nextType}/{$nextId|escape:"url"}?searchId={$searchId}&amp;recordIndex={$nextIndex}&amp;page={if isset($nextPage)}{$nextPage}{else}{$page}{/if}" title="{if !$nextTitle}{translate text='Next'}{else}{$nextTitle|truncate:180:"..."|replace:"&":"&amp;"}{/if}"><img src="{$path}/interface/themes/default/images/next.png" alt="Next Record"/></a></div>
			{/if}
		</div>
	</div>

	{* Display Book Cover *}
	{if $user->disableCoverArt != 1}
		<div id="recordcover" class="text-center">
			<img alt="{translate text='Book Cover'}" class="img-thumbnail" src="{$recordDriver->getBookcoverUrl('large')}">
		</div>
	{/if}

	<div id="full-record-ratings" class="text-center">
		{* Let the user rate this title *}
		{include file="GroupedWork/title-rating-full.tpl" ratingClass="" showFavorites=0 ratingData=$recordDriver->getRatingData() showNotInterested=false}
	</div>

	<div id="recordTools" class="full-record-tools">
		{include file="GroupedWork/result-tools.tpl" showMoreInfo=false summId=$recordDriver->getPermanentId()}
	</div>

	{*<div id="xs-main-content-insertion-point" class="row"></div>*}

	{* QR Code *}
	{if $showQRCode}
	<div id="record-qr-code" class="text-center row hidden-xs visible-md"><img src="{$recordDriver->getQRCodeUrl()}" alt="QR Code for Record"></div>
	{/if}

	{if $user}
		{* Account Menu *}
		{include file="MyAccount/menu.tpl"}
	{/if}

	{include file="library-sidebar.tpl"}
{/strip}