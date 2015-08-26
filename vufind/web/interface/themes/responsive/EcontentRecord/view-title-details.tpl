{strip}
	<div>
		<dl class="dl-horizontal">
			{if count($additionalAuthorsList) > 0}
				<dt>{translate text='Additional Authors'}:</dt>
				{foreach from=$additionalAuthorsList item=additionalAuthorsListItem name=loop}
					<dd><a href="{$path}/Author/Home?author={$additionalAuthorsListItem|escape:"url"}">{$additionalAuthorsListItem|escape}</a></dd>
				{/foreach}
			{/if}

			{if $eContentRecord->publishDate || $eContentRecord->publisher || $eContentRecord->publishLocation}
				<dt>{translate text='Published'}:</dt>
				<dd>{$eContentRecord->publisher|escape} {$eContentRecord->publishLocation|escape} {$eContentRecord->publishDate|escape}</dd>
			{/if}

			<dt>{translate text='Format'}:</dt>
			{if is_array($recordDriver->getFormat())}
				{foreach from=$recordDriver->getFormat() item=displayFormat name=loop}
					<dd><span class="icon {$displayFormat|lower|regex_replace:"/[^a-z0-9]/":""}">&nbsp;</span><span class="iconlabel">{translate text=$displayFormat}</span></dd>
				{/foreach}
			{else}
				<dd><span class="icon {$eContentRecord->format()|lower|regex_replace:"/[^a-z0-9]/":""}">&nbsp;</span><span class="iconlabel">{translate text=$eContentRecord->format}</span></dd>
			{/if}

			{if $eContentRecord->physicalDescription}
				<dt>{translate text='Physical Desc'}:</dt>
				{foreach from=$eContentRecord->physicalDescription item=physicalDescription name=loop}
					<dd>{$physicalDescription|escape}</dd>
				{/foreach}
			{/if}

			<dt>{translate text='Language'}:</dt>
			<dd>{$eContentRecord->language|escape}</dd>

			{if $eContentRecord->edition}
				<dt>{translate text='Edition'}:</dt>
				<dd>{$eContentRecord->edition|escape}</dd>
			{/if}

			{if count($lccnList) > 0}
				<dt>{translate text='LCCN'}:</dt>
				{foreach from=$lccnList item=lccnListItem name=loop}
					<dd>{$lccnListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($isbnList) > 0}
				<dt>{translate text='ISBN'}:</dt>
				{foreach from=$isbnList item=isbnListItem name=loop}
					<dd>{$isbnListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($issnList) > 0}
				<dt>{translate text='ISSN'}:</dt>
				{foreach from=$issnList item=issnListItem name=loop}
					<dd>{$issnListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($upcList) > 0}
				<dt>{translate text='UPC'}:</dt>
				{foreach from=$upcList item=upcListItem name=loop}
					<dd>{$upcListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($seriesList) > 0}
				<dt>{translate text='Series'}:</dt>
				{foreach from=$seriesList item=seriesListItem name=loop}
					<dd><a href="{$path}/Search/Results?lookfor=Series%3A%22{$seriesListItem|escape:"url"}%22">{$seriesListItem|escape}</a></dd>
				{/foreach}
			{/if}

			{if count($topicList) > 0}
				<dt>{translate text='Topic'}:</dt>
				{foreach from=$topicList item=topicListItem name=loop}
					<dd><a href="{$path}/Search/Results?lookfor=%22{$topicListItem|escape:"url"}%22&amp;basicType=Subject">{$topicListItem|escape}</a></dd>
				{/foreach}
			{/if}

			{if count($genreList) > 0}
				<dt>{translate text='Genre'}:</dt>
				{foreach from=$genreList item=genreListItem name=loop}
					<dd>{$genreListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($regionList) > 0}
				<dt>{translate text='Region'}:</dt>
				{foreach from=$regionList item=regionListItem name=loop}
					<dd>{$regionListItem|escape}</dd>
				{/foreach}
			{/if}

			{if count($eraList) > 0}
				<dt>{translate text='Era'}:</dt>
				{foreach from=$eraList item=eraListItem name=loop}
					<dd>{$eraListItem|escape}</dd>
				{/foreach}
			{/if}

			{if $standardSubjects}
				<dt>{translate text='Subjects'}</dt>
				{foreach from=$standardSubjects item=subject name=loop}
					<dd>
						{foreach from=$subject item=subjectPart name=subloop}
							{if !$smarty.foreach.subloop.first} -- {/if}
							<a href="{$path}/Search/Results?lookfor=%22{$subjectPart.search|escape:"url"}%22&amp;basicType=Subject">{$subjectPart.title|escape}</a>
						{/foreach}
					</dd>
				{/foreach}
			{/if}

			{if $bisacSubjects}
				<dt>{translate text='Bisac Subjects'}</dt>
				{foreach from=$bisacSubjects item=subject name=loop}
					<dd>
						{foreach from=$subject item=subjectPart name=subloop}
							{if !$smarty.foreach.subloop.first} -- {/if}
							<a href="{$path}/Search/Results?lookfor=%22{$subjectPart.search|escape:"url"}%22&amp;basicType=Subject">{$subjectPart.title|escape}</a>
						{/foreach}
					</dd>
				{/foreach}
			{/if}

			{if $oclcFastSubjects}
				<dt>{translate text='OCLC Fast Subjects'}</dt>
				{foreach from=$oclcFastSubjects item=subject name=loop}
					<dd>
						{foreach from=$subject item=subjectPart name=subloop}
							{if !$smarty.foreach.subloop.first} -- {/if}
							<a href="{$path}/Search/Results?lookfor=%22{$subjectPart.search|escape:"url"}%22&amp;basicType=Subject">{$subjectPart.title|escape}</a>
						{/foreach}
					</dd>
				{/foreach}
			{/if}

		</dl>
	</div>
{/strip}