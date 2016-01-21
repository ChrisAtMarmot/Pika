{strip}
	{if $recordDriver->getContributors()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Contributors'}:</div>
			<div class="col-md-9 result-value">
				{foreach from=$recordDriver->getContributors() item=contributor name=loop}
					<a href="{$path}/Author/Home?author={$contributor|trim|escape:"url"}">{$contributor|escape}</a><br/>
				{/foreach}
			</div>
		</div>
	{/if}

	{if $recordDriver->getMpaaRating()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Rating'}:</div>
			<div class="col-md-9 result-value">{$recordDriver->getMpaaRating()|escape}</div>
		</div>
	{/if}

	{if $recordDriver->getISBNs()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='ISBN'}:</div>
			<div class="col-md-9 result-value">
				{foreach from=$recordDriver->getISBNs() item=tmpIsbn name=loop}
					{$tmpIsbn|escape}<br/>
				{/foreach}
			</div>
		</div>
	{/if}

	{if $recordDriver->getISSNs()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='ISSN'}:</div>
			<div class="col-md-9 result-value">{$recordDriver->getISSNs()}</div>
		</div>
	{/if}

	{if $recordDriver->getUPCs()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='UPC'}:</div>
			<div class="col-md-9 result-value">
				{foreach from=$recordDriver->getUPCs() item=tmpUpc name=loop}
					{$tmpUpc|escape}<br/>
				{/foreach}
			</div>
		</div>
	{/if}

	{if $recordDriver->getAcceleratedReaderData() != null}
		{assign var="arData" value=$recordDriver->getAcceleratedReaderData()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Accelerated Reader'}:</div>
			<div class="col-md-9 result-value">
				{if $arData.interestLevel}
					{$arData.interestLevel|escape}<br/>
				{/if}
				Level {$arData.readingLevel|escape}, {$arData.pointValue|escape} Points
			</div>
		</div>
	{/if}

	{if $recordDriver->getLexileCode()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Lexile Code'}:</div>
			<div class="col-md-9 result-value">
				{$recordDriver->getLexileCode()|escape}
			</div>
		</div>
	{/if}

	{if $recordDriver->getLexileScore()}
		<div class="row">
			<div class="result-label col-md-3">{translate text='Lexile Score'}:</div>
			<div class="col-md-9 result-value">
				{$recordDriver->getLexileScore()|escape}
			</div>
		</div>
	{/if}
{/strip}