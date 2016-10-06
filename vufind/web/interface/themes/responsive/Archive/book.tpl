{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="GroupedWork/search-results-navigation.tpl"}
		<h2>
			{$title|escape}
		</h2>
		<div class="row">
			<div id="main-content" class="col-xs-12 text-center">
				<div id="view-toggle" class="btn-group" role="group" data-toggle="buttons">
					<label class="btn btn-group-small btn-default">
						<input type="radio" name="pageView" id="view-toggle-pdf" autocomplete="off" onchange="return VuFind.Archive.handleBookClick('{$pid}', VuFind.Archive.activeBookPage, 'pdf');">
						{*TODO: set bookPID*}

						View As PDF
					</label>
					<label class="btn btn-group-small btn-default">
						<input type="radio" name="pageView" id="view-toggle-image" autocomplete="off" onchange="return VuFind.Archive.handleBookClick('{$pid}', VuFind.Archive.activeBookPage, 'image');">

						View As Image
					</label>
					<label class="btn btn-group-small btn-default">
						<input type="radio" name="pageView" id="view-toggle-transcription" autocomplete="off" onchange="return VuFind.Archive.handleBookClick('{$pid}', VuFind.Archive.activeBookPage, 'transcription');">

						View Transcription
					</label>
				</div>

				<br>

				<div id="view-pdf" width="100%" height="600px" style="display: none">
					No PDF loaded
				</div>

				<div id="view-image" style="display: none">
					<div class="large-image-wrapper">
						<div class="large-image-content">
							<div id="pika-openseadragon" class="openseadragon"></div>
						</div>
					</div>
				</div>

				<div id="view-transcription" style="display: none" width="100%" height="600px;">
					No transcription loaded
				</div>
			</div>
		</div>

		<div id="download-options">
			{*
			<a class="btn btn-default" href="/Archive/{$pid}/DownloadPDF">Download Book As PDF</a>
			<a class="btn btn-default" href="/Archive/{$activePage}/DownloadPDF" id="downloadPageAsPDF">Download Page As PDF</a>
			*}
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="{$path}/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
		</div>

		<div class="row">
			<div class="col-xs-12 text-center">
				<div class="jcarousel-wrapper" id="book-sections">
					<a href="#" class="jcarousel-control-prev"{* data-target="-=1"*}><i class="glyphicon glyphicon-chevron-left"></i></a>
					<a href="#" class="jcarousel-control-next"{* data-target="+=1"*}><i class="glyphicon glyphicon-chevron-right"></i></a>

					<div class="relatedTitlesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
						<ul>
							{assign var=pageCounter value=1}
							{foreach from=$bookContents item=section}
								{if count($section.pages) == 0}
									<li class="relatedTitle">
										<a href="{$section.link}">
											<figure class="thumbnail">
												<img src="{$section.cover}" alt="{$section.title|removeTrailingPunctuation|truncate:80:"..."}">
												<figcaption>{$section.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
											</figure>
										</a>
									</li>
									{assign var=pageCounter value=$pageCounter+1}
								{else}
									{foreach from=$section.pages item=page}
										<li class="relatedTitle">
											<a href="{$page.link}?pagePid={$page.pid}" onclick="return VuFind.Archive.handleBookClick('{$pid}', '{$page.pid}', VuFind.Archive.activeBookViewer);">
												<figure class="thumbnail">
													<img src="{$page.cover}" alt="Page {$pageCounter}">
													<figcaption>{$pageCounter}</figcaption>
												</figure>
											</a>
										</li>
										{assign var=pageCounter value=$pageCounter+1}
									{/foreach}
								{/if}
							{/foreach}
						</ul>
					</div>
				</div>
			</div>
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script src="{$path}/js/openseadragon/openseadragon.js" ></script>
<script src="{$path}/js/openseadragon/djtilesource.js" ></script>

<script type="text/javascript">
	{assign var=pageCounter value=1}
	{foreach from=$bookContents item=section}
		VuFind.Archive.pageDetails['{$section.pid}'] = {ldelim}
			pid: '{$section.pid}',
			title: "{$section.title|escape:javascript}",
			pdf: '{$section.pdf}'
		{rdelim};

		{foreach from=$section.pages item=page}
			VuFind.Archive.pageDetails['{$page.pid}'] = {ldelim}
				pid: '{$page.pid}',
				title: 'Page {$pageCounter}',
				pdf: '{$page.pdf}',
				jp2: '{$page.jp2}',
				transcript: '{$page.transcript}',
				index: '{$pageCounter}'
			{rdelim};
			{if $page.pid == $activePage}
				{assign var=scrollToPage value=$pageCounter}
				VuFind.Archive.curPage = {$pageCounter};
			{/if}
			{assign var=pageCounter value=$pageCounter+1}
		{/foreach}
	{/foreach}

	{* Greater than 2 because we increment page counter after an object is displayed *}
	{if $pageCounter > 2}
		VuFind.Archive.multiPage = true;
	{/if}

	$(function(){ldelim}
		{* Below click events trigger indirectly the handleBookClick function, and properly sets the appropriate button. *}
		VuFind.Archive.handleBookClick('{$pid}', '{$activePage}', '{$activeViewer}');

		VuFind.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>
