{strip}
	<div id="scrollerTitle{$listName}{$key}" class="scrollerTitle">
		<a onclick="trackEvent('ListWidget', 'Title Click', '{$listName}')" href="{$titleURL}" id="descriptionTrigger{$shortId}">
		<img src="{$imageUrl}" class="scrollerTitleCover" alt="{$title} Cover"/>
		</a>
		{* show ratings check in the template *}
		{include file="GroupedWork/title-rating.tpl"}
	</div>
	<div id="descriptionPlaceholder{$id}" style="display:none" class="loaded">
		{include file="Record/ajax-description-popup.tpl"}
	</div>
{/strip}