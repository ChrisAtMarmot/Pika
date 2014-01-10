<!DOCTYPE html>
<html lang="{$userLang}">
	<head>
		<title>{$pageTitle|truncate:64:"..."}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
		{if $addHeader}{$addHeader}{/if}
		<link type="image/x-icon" href="{img filename=favicon.png}" rel="shortcut icon" />
		<link rel="search" type="application/opensearchdescription+xml" title="Library Catalog Search" href="{$path}/Search/OpenSearch?method=describe" />

		{* Include css as appropriate *}
		{css filename="main.css"}
		{* <link href="{$path}/interface/themes/responsive/css/marmot.css" rel="stylesheet" media="screen"> *}

		{* Include correct javascript *}
		<script src="{$path}/js/jquery-1.9.1.min.js"></script>
		<script src="{$path}/interface/themes/responsive/js/scripts.js"></script>
		<script type="text/javascript">
			{literal}
			var Globals = VuFind.Globals || {};
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
		</script>

		{if $includeAutoLogoutCode == true}
			<script type="text/javascript" src="{$path}/js/autoLogout.js"></script>
		{/if}
		{if $additionalCss}
			<style type="text/css">
				{$additionalCss}
			</style>
		{/if}
	</head>
	<body class="module_{$module} action_{$action}">
		<div id="header-container">
			{include file='header_responsive.tpl'}
		</div>

		{if $showTopSearchBox}
			<div id='searchbar'>
				{include file="Search/searchbox.tpl" showAsBar=true}
			</div>
		{/if}

		{if $showBreadcrumbs}
			<ul class="breadcrumb">
				<li><a href="{$homeBreadcrumbLink}" id="home-breadcrumb"><i class="icon-home"></i> {translate text=$homeLinkText}</a> <span class="divider">&raquo;</span></li>
				{include file="$module/breadcrumbs.tpl"}
			</ul>
		{/if}

		<div class="container" id="content-container">
			{include file="$module/$pageTemplate"}
		</div>

		<div id="footer-container">
			{include file="footer_responsive.tpl"}
		</div>

		<div id="modalDialog" class="modal hide fade" tabindex="-1" role="dialog">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">×</button>
				<h3 id="modal-title"></h3>
			</div>
			<div class="modal-body">
				<div id="modal-loading" class="text-center">Loading...<br/><img src="{img filename='loading_bar.gif'}" title="Loading"/></div>
			</div>
			<div class="modal-footer">
				<button class="btn" data-dismiss="modal" id="modalClose">Close</button>
			</div>
		</div>

		{include file="tracking.tpl"}

		{* Extra javascript at end so the pages load faster. *}
		<script src="{$path}/interface/themes/responsive/js/rater.js"></script>
		<script src="{$path}/interface/themes/responsive/js/bootstrap.min.js"></script>
		<script src="{$path}/interface/themes/responsive/js/bootstrap-switch.js"></script>
		<script src="{$path}/interface/themes/responsive/js/bootstrap-datepicker.js"></script>
		<script src="{$path}/js/tablesorter/jquery.tablesorter.min.js"></script>
		<script src="{$path}/ckeditor/ckeditor.js"></script>
		<script type="text/javascript" src="https://www.google.com/recaptcha/api/js/recaptcha_ajax.js"></script>
	</body>
</html>