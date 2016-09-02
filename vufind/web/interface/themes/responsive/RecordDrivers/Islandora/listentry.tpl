{strip}
	<div id="archive{$jquerySafeId|escape}" class="resultsList" data-order="{$resultIndex}">
		{* Because colons give css & jquery trouble the Ids from Islandora have : replaced with _ *}
		<a name="record{$summId|escape:"url"}"></a>
		<div class="row">
			{if $showCovers}
			<div class="col-xs-12 col-sm-3 col-md-3 col-lg-2 text-center">
				{if $user->disableCoverArt != 1}
					<a href="{$summUrl}">
						<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}">
					</a>
				{/if}
			</div>
			{/if}

			<div class="{if !$showCovers}col-xs-10 col-sm-10 col-md-10 col-lg-11{else}col-xs-7 col-sm-7 col-md-7 col-lg-9{/if}">
				<div class="row">
					<div class="col-xs-12">
						<span class="result-index">{$resultIndex})</span>&nbsp;
						<a href="{$summUrl}" class="result-title notranslate">{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}</a>
						{if $summTitleStatement}
							&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|highlight|truncate:180:"..."}
						{/if}
					</div>
				</div>

				{if $summAuthor}
					<div class="row">
						<div class="result-label col-xs-3">Author: </div>
						<div class="col-xs-9 result-value  notranslate">
							{if is_array($summAuthor)}
								{foreach from=$summAuthor item=author}
									<a href='{$path}/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
								{/foreach}
							{else}
								<a href='{$path}/Author/Home?author="{$summAuthor|escape:"url"}"'>{$summAuthor|highlight}</a>
							{/if}
						</div>
					</div>
				{/if}

				{if $listEntryNotes}
					<div class="row">
						<div class="result-label col-md-3">Notes: </div>
						<div class="result-value col-md-9">
							{$listEntryNotes}
						</div>
					</div>
				{/if}

				{if $summPublisher}
					<div class="row">
						<div class="result-label col-xs-3">Publisher: </div>
						<div class="col-xs-9 result-value">
							{$summPublisher}
						</div>
					</div>
				{/if}

				{if $summFormat}
					<div class="row">
						<div class="result-label col-xs-3">Format: </div>
						<div class="col-xs-9 result-value">
							{$summFormat}
						</div>
					</div>
				{/if}

				{if $summPubDate}
					<div class="row">
						<div class="result-label col-xs-3">Pub. Date: </div>
						<div class="col-xs-9 result-value">
							{$summPubDate|escape}
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

				<div class="row well-small">
					<div class="col-xs-12 result-value" id="descriptionValue{$summId|escape}">{$summDescription|highlight|truncate_html:450:"..."}</div>
				</div>

				<div class="row">
					<div class="col-xs-12">
						{include file='Archive/result-tools-horizontal.tpl'}
					</div>
				</div>

			</div>

			<div class="col-xs-2 col-sm-2 col-md-2 col-lg-1">
				{if $listEditAllowed}
					<div class="btn-group-vertical" role="group">
						<a href="{$path}/MyAccount/Edit?id={$summId|escape:"url"}{if !is_null($listSelected)}&amp;list_id={$listSelected|escape:"url"}{/if}" class="btn btn-default">{translate text='Edit'}</a>
						{* Use a different delete URL if we're removing from a specific list or the overall favorites: *}
						<a href="{$path}/MyAccount/MyList/{$listSelected|escape:"url"}?delete={$summId|escape:"url"}" onclick="return confirm('Are you sure you want to delete this?');" class="btn btn-default">{translate text='Delete'}</a>

						{* manual ordering of user lists. plb 5-27-2015
										{if $userSort}
											<div class="btn-group" role="group">
												<button class="btn btn-default dropdown-toggle" type="button" id="sortOrder{$resultIndex}" data-toggle="dropdown" aria-expanded="true">
													Order &nbsp;
													<span class="caret"></span>
												</button>
												<ul class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="sortOrder{$resultIndex}">
													<li>
														<a>
														<input class="form-control" type="number" size="4" name="weight[{$summId|escape:"url"}]" id="weight_{$summId|escape:"url"}"{if 1} value="{$resultIndex}"{/if}>
														</a>
													</li>
												</ul>
											</div>
										{/if} *}

					</div>
				{/if}
			</div>
		</div>
	</div>
{/strip}