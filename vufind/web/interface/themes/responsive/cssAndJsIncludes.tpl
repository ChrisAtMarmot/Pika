{strip}
	{* Include css as appropriate *}
	{css filename="main.css"}
	{* <link href="{$path}/interface/themes/responsive/css/marmot.css" rel="stylesheet" media="screen"> *}

	{* Include correct all javascript *}
	{* TODO: Somehow minify all of this into one little file *}
	<script src="{$path}/js/jquery-1.9.1.min.js"></script>
	{* Load Libraries*}
	<script src="{$path}/interface/themes/responsive/js/lib/rater.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/bootstrap.min.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/jcarousel.min.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/jcarousel.responsive.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/bootstrap-datepicker.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/jquery-ui-1.10.4.custom.min.js"></script>
	<script src="{$path}/interface/themes/responsive/js/lib/bootstrap-switch.min.js"></script>
	<script src="{$path}/js/tablesorter/jquery.tablesorter.min.js"></script>
	<script src="{$path}/ckeditor/ckeditor.js"></script>
	<script type="text/javascript" src="https://www.google.com/recaptcha/api/js/recaptcha_ajax.js"></script>
	{* Load application specific Javascript *}
	<script src="{$path}/interface/themes/responsive/js/vufind/globals.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/base.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/account.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/browse.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/econtent-record.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/grouped-work.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/lists.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/lists-widgets.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/overdrive.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/prospector.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/ratings.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/reading-history.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/record.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/responsive.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/results-list.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/searches.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/title-scroller.js"></script>
	<script src="{$path}/interface/themes/responsive/js/vufind/wikipedia.js"></script>


	<script type="text/javascript">
		{* Override variables as needed *}
		{literal}
		$(document).ready(function(){
			{/literal}
			Globals.path = '{$path}';
			Globals.url = '{$url}';
			Globals.loggedIn = {$loggedIn};
			{if $automaticTimeoutLength}
			Globals.automaticTimeoutLength = {$automaticTimeoutLength};
			{/if}
			{if $automaticTimeoutLengthLoggedOut}
			Globals.automaticTimeoutLengthLoggedOut = {$automaticTimeoutLengthLoggedOut};
			{/if}
			{literal}
		});
		{/literal}
	</script>

	{if $includeAutoLogoutCode == true}
		<script type="text/javascript" src="{$path}/js/autoLogout.js"></script>
	{/if}
	{if $additionalCss}
		<style type="text/css">
			{$additionalCss}
		</style>
	{/if}
{/strip}