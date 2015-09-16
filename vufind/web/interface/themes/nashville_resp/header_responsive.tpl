{strip}

	<div class="col-xs-12 col-sm-6 col-md-5 col-lg-4">
<div id="header-logo">
		<a href="{$logoLink}/">

			<img src="{if $responsiveLogo}{$responsiveLogo}{else}{img filename="logo_responsive.png"}{/if}" alt="{$librarySystemName}" title="Return to Catalog Home" id="header-logo" {if $showDisplayNameInHeader && $librarySystemName}class="pull-left"{/if}/>
			{if $showDisplayNameInHeader && $librarySystemName}
				<span id="library-name-header" class="hidden-xs visible-sm">{$librarySystemName}</span>
			{/if}
		</a>
</div>
	</div>

	<div class="logoutOptions" {if !$user} style="display: none;"{/if}>
		<div class="hidden-xs col-sm-2 col-sm-offset-2 col-md-2 col-md-offset-3 col-lg-2 col-lg-offset-4">
			<div class="header-button header-primary">
				<a id="myAccountNameLink" href="{$path}/MyAccount/Home">
					{$user->firstname|capitalize} {$user->lastname|capitalize|substr:0:1}'s Account
				</a>
			</div>
		</div>
		<div class="hidden-xs col-xs-3 col-sm-2 col-md-2 col-lg-2">
			<div class="header-button header-logout" >
				<a href="{$path}/MyAccount/Logout" id="logoutLink" >{translate text="Log Out"}</a>
			</div>
		</div>
	</div>

	<div class="loginOptions col-xs-3 col-xs-offset-4 col-sm-2 col-sm-offset-4 col-md-2 col-md-offset-5 col-lg-2 col-lg-offset-6"{if $user} style="display: none;"{/if}>
		{if $showLoginButton == 1}
			<div class="hidden-xs header-button header-primary">
				<a id="headerLoginLink" href="{$path}/MyAccount/Home" class='loginLink' data-login="true" title='Login'>{translate text="LOGIN"}</a>
			</div>
		{/if}
	</div>

	{if $topLinks}
		<div class="col-xs-12" id="header-links">
			{foreach from=$topLinks item=link}
				<div class="header-link-wrapper">
					<a href="{$link->url}" class="library-header-link">{$link->linkText}</a>
				</div>
			{/foreach}
		</div>
	{/if}
{/strip}
