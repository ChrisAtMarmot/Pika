{strip}
	{if $raceEthnicity}
		<div class="row">
			<div class="result-label col-sm-4">Race and Ethnicity: </div>
			<div class="result-value col-sm-8">
				{$raceEthnicity}
			</div>
		</div>
	{/if}
	{if $gender}
		<div class="row">
			<div class="result-label col-sm-4">Gender Expression/Identity: </div>
			<div class="result-value col-sm-8">
				{$gender}
			</div>
		</div>
	{/if}
{/strip}