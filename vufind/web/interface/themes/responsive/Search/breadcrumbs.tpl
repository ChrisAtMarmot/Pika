{if $searchId}
	<li>{translate text="Search"}: {$lookfor|capitalize|escape:"html"} <span class="divider">&raquo;</span></li>
{elseif $pageTemplate=="newitem.tpl" || $pageTemplate=="newitem-list.tpl"}
	<li>{translate text="New Items"} <span class="divider">&raquo;</span></li>
{elseif $subTemplate}
	<li>{translate text=$subTemplate|replace:'.tpl':''|capitalize|translate} <span class="divider">&raquo;</span></li>
{elseif $pageTemplate!=""}
	<li>{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate} <span class="divider">&raquo;</span></li>
{/if}
