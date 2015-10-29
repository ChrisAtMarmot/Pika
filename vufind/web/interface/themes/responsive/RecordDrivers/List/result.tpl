{strip}
<div id="record{$summId|escape}" class="resultsList row">
	<div class="col-sm-3 col-md-3 col-lg-2 text-center">
		{if $user->disableCoverArt != 1}
			<a href="{$path}/MyResearch/MyList/{$summShortId}" class="alignleft listResultImage">
				<img src="{img filename="lists.png"}" alt="{translate text='No Cover Image'}"/><br />
			</a>
		{/if}
	</div>

	<div class="col-md-9">
		<div class="row">
			<div class="col-xs-12">
				<span class="result-index">{$resultIndex})</span>&nbsp;
				<a href="{$path}/MyAccount/MyList/{$summShortId}" class="result-title notranslate">{if !$summTitle}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}</a>
				{if $summTitleStatement}
					<div class="searchResultSectionInfo">
						&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|truncate:180:"..."|highlight}
					</div>
				{/if}
				{if isset($summScore)}
					(<a href="#" onclick="return VuFind.showElementInPopup('Score Explanation', '#scoreExplanationValue{$summId|escape}');">{$summScore}</a>)
				{/if}
			</div>
		</div>

		{if $summAuthor}
			<div class="row">
				<div class="result-label col-md-3">Created By: </div>
				<div class="col-md-9 result-value  notranslate">
					{if is_array($summAuthor)}
						{foreach from=$summAuthor item=author}
							{$author|highlight}
						{/foreach}
					{else}
						{$summAuthor|highlight}
					{/if}
				</div>
			</div>
		{/if}

		{if $summNumTitles}
			<div class="row">
				<div class="result-label col-md-3">Number of Titles: </div>
				<div class="col-md-9 result-value  notranslate">
					{$summNumTitles} titles are in this list.
				</div>
			</div>
		{/if}

		{if $summSnippets}
			{foreach from=$summSnippets item=snippet}
				<div class="row">
					<div class="result-label col-xs-3">{translate text=$snippet.caption}: </div>
					<div class="col-xs-9 result-value">
						{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br />{/if}
					</div>
				</div>
			{/foreach}
		{/if}

		{if $summDescription}
			<div class="row well-small">
				<div class="col-md-12 result-value">{$summDescription|highlight|truncate_html:450:"..."}</div>
			</div>
		{/if}

		<div class="resultActions row">
			{include file='List/result-tools.tpl' id=$summId shortId=$shortId module=$summModule summTitle=$summTitle ratingData=$summRating recordUrl=$summUrl}
		</div>
	</div>
</div>
{/strip}