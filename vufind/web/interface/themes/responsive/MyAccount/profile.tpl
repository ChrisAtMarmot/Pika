{strip}
	<div id="main-content">
		{if $user->cat_username}
			{if $profile->web_note}
				<div class="row">
					<div id="web_note" class="alert alert-info text-center col-xs-12">{$profile->web_note}</div>
				</div>
			{/if}

			{* Alternate Mobile MyAccount Menu *}
			{include file="MyAccount/mobilePageHeader.tpl"}

			{include file="MyAccount/availableHoldsNotice.tpl"}

				<h2>{translate text='Account Settings'}</h2>

			{if $profileUpdateErrors}
				{foreach from=$profileUpdateErrors item=errorMsg}
					<div class='alert alert-danger'>{$errorMsg}</div>
				{/foreach}
			{/if}

			{include file="MyAccount/switch-linked-user-form.tpl" label="View Account Settings for" actionPath="/MyAccount/Profile"}

			<br>

			<div class="panel-group" id="account-settings-accordion">
				{* ILS Settings *}
				<div class="panel active">
					<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#contactPanel">
						<div class="panel-heading">
							<div class="panel-title">
								Contact Information
							</div>
						</div>
					</a>
					<div id="contactPanel" class="panel-collapse collapse in">
						<div class="panel-body">
							{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
							<form action="" method="post" class="form-horizontal" id="contactUpdateForm">
								<input type="hidden" name="updateScope" value="contact">
								<div class="form-group">
									<div class="col-xs-4"><strong>{translate text='Full Name'}:</strong></div><div class="col-xs-8">{$profile->fullname|escape}</div>
								</div>
								{if $showUsernameField}
									<div class="form-group">
										<div class="col-xs-4"><strong>Username:</strong></div>
										<div class="col-xs-8"><input type="text" name="username" id="username" value="{if !is_numeric(trim($profile->alt_username))}{$profile->alt_username|escape}{/if}" size="25" maxlength="25" class="form-control">
											<a href="#" onclick="$('#usernameHelp').toggle()">What is this?</a>
											<div id="usernameHelp" style="display:none">
												A username is an optional feature. If you set one, your username will be your alias on hold slips and can also be used to log into your account in place of your card number.  A username can be set, reset or removed from the “Account Settings” section of your online account. Usernames must be between 6 and 25 characters (letters and number only, no special characters).
											</div>
										</div>
									</div>
								{/if}
								{if !$offline}
									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Fines'}:</strong></div>
										<div class="col-xs-8">{$profile->fines|escape}</div>
									</div>
									{if $barcodePin}
									{* Only Display Barcode when the barcode is used as a username and not a password *}
									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Library Card Number'}:</strong></div>
										<div class="col-xs-8">{$profile->cat_username|escape}</div>
									</div>
									{/if}
									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Expiration Date'}:</strong></div>
										<div class="col-xs-8">{$profile->expires|escape}</div>
									</div>
								{/if}
								<div class="form-group">
									<div class="col-xs-4"><strong>{translate text='Home Library'}:</strong></div><div class="col-xs-8">{$profile->homeLocation|escape}</div>
								</div>
								{if !$offline}
									{* Don't show inputs for the Horizon ILS as updating those account settings has not been implemented in the Horizon Driver. *}
									<div class="form-group">
										<div class="col-xs-4">
											<label for="address1">{translate text='Address'}:</label>
										</div>
										<div class="col-xs-8">
											{if $edit && $canUpdateContactInfo && $canUpdateAddress && $ils != 'Horizon'}
												<input name="address1" id="address1" value='{$profile->address1|escape}' size="50" maxlength="75" class="form-control required">
											{elseif $edit && $millenniumNoAddress}
												<input name="address1" id="address1" value='{$profile->address1|escape}' type="hidden">
												{$profile->address1|escape}
											{else}
												{$profile->address1|escape}
											{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="city">{translate text='City'}:</label></div>
										<div class="col-xs-8">
											{if $edit && $canUpdateContactInfo && $canUpdateAddress && $ils != 'Horizon'}<input name="city" id="city" value="{$profile->city|escape}" size="50" maxlength="75" class="form-control required">
											{elseif $edit && $millenniumNoAddress}
												<input name="city" id="city" value="{$profile->city|escape}" type="hidden">
												{$profile->city|escape}
											{else}{$profile->city|escape}{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="state">{translate text='State'}:</label></div>
										<div class="col-xs-8">
											{if $edit && $canUpdateContactInfo && $canUpdateAddress && $ils != 'Horizon'}<input name='state' id="state" value="{$profile->state|escape}" size="50" maxlength="75" class="form-control required">
											{elseif $edit && $millenniumNoAddress}
												<input name="state" id="state" value="{$profile->state|escape}" type="hidden">
												{$profile->state|escape}
											{else}{$profile->state|escape}{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="zip">{translate text='Zip'}:</label></div>
										<div class="col-xs-8">
											{if $edit && $canUpdateContactInfo && $canUpdateAddress && $ils != 'Horizon'}
												<input name="zip" id="zip" value="{$profile->zip|escape}" size="50" maxlength="75" class="form-control required">
											{elseif $edit && $millenniumNoAddress}
												<input name="zip" id="zip" value="{$profile->zip|escape}" type="hidden">
												{$profile->zip|escape}
											{else}{$profile->zip|escape}{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="phone">{translate text='Primary Phone Number'}:</label></div>
										<div class="col-xs-8">
											{if $edit && $canUpdateContactInfo && ($ils != 'Horizon')}
												<input type="tel" name="phone" id="phone" value="{$profile->phone|replace:'TEXT ONLY':''|escape}" size="50" maxlength="75" class="form-control{*{if $primaryTheme =='arlington'} //Keep for debugging*}{if $libraryName =='Arlington Public Library'} digits{/if}">
											{else}
												{$profile->phone|escape}
											{/if}
										</div>
									</div>
									{if $showWorkPhoneInProfile}
										<div class="form-group">
											<div class="col-xs-4"><label for="workPhone">{translate text='Work Phone Number'}:</label></div>
											<div class="col-xs-8">{if $edit && $canUpdateContactInfo && $ils != 'Horizon'}<input name="workPhone" id="workPhone" value="{$profile->workPhone|escape}" size="50" maxlength="75" class="form-control">{else}{$profile->workPhone|escape}{/if}</div>
										</div>
									{/if}
								{/if}
								<div class="form-group">
									<div class="col-xs-4"><label for="email">{translate text='E-mail'}:</label></div>
									<div class="col-xs-8">
										{if $edit == true && $canUpdateContactInfo == true}<input type="text" name="email" id="email" value="{$profile->email|escape}" size="50" maxlength="75" class="form-control multiemail">{else}{$profile->email|escape}{/if}
										{* Multiemail class is for form validation; type has to be text for multiemail validation to work correctly *}
									</div>
								</div>
								{if $showPickupLocationInProfile}
									<div class="form-group">
										<div class="col-xs-4"><label for="pickupLocation" class="">{translate text='Pickup Location'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true && $canUpdateContactInfo == true}
												<select name="pickupLocation" id="pickupLocation" class="form-control">
													{if count($pickupLocations) > 0}
														{foreach from=$pickupLocations item=location}
															<option value="{$location->code}" {if $location->displayName|escape == $profile->homeLocation|escape}selected="selected"{/if}>{$location->displayName}</option>
														{/foreach}
													{else}
														<option>placeholder</option>
													{/if}
												</select>
											{else}
												{$profile->homeLocation|escape}
											{/if}
										</div>
									</div>
								{/if}

								{if $showNoticeTypeInProfile}
									<p class="alert alert-info">
										{translate text='account_profile_notification_notice'}
									</p>

									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Receive notices by'}:</strong></div>
										<div class="col-xs-8">
											{if $edit == true && $canUpdateContactInfo == true}
												<div class="btn-group btn-group-sm" data-toggle="buttons">
													{if $treatPrintNoticesAsPhoneNotices}
														{* Tell the User the notice is Phone even though in the ILS it will be print *}
														{* MDN 2/24/2016 - If the user changes their notice preference, make it phone to be more accurate, but show as selected if either print or mail is shown *}
														<label for="noticesMail" class="btn btn-sm btn-default {if $profile->notices == 'a' || $profile->notices == 'p'}active{/if}"><input type="radio" value="p" id="noticesMail" name="notices" {if $profile->notices == 'a' || $profile->notices == 'p'}checked="checked"{/if}> Telephone</label>
													{else}
														<label for="noticesMail" class="btn btn-sm btn-default {if $profile->notices == 'a'}active{/if}"><input type="radio" value="a" id="noticesMail" name="notices" {if $profile->notices == 'a'}checked="checked"{/if}> Postal Mail</label>
														<label for="noticesTel" class="btn btn-sm btn-default {if $profile->notices == 'p'}active{/if}"><input type="radio" value="p" id="noticesTel" name="notices" {if $profile->notices == 'p'}checked="checked"{/if}> Telephone</label>
													{/if}
													<label for="noticesEmail" class="btn btn-sm btn-default {if $profile->notices == 'z'}active{/if}"><input type="radio" value="z" id="noticesEmail" name="notices" {if $profile->notices == 'z'}checked="checked"{/if}> Email</label>
												</div>
											{else}
												{$profile->noticePreferenceLabel|escape}
											{/if}
										</div>
									</div>
								{/if}

								{if $showSMSNoticesInProfile}
									<div class="form-group">
										<div class="col-xs-4"><label for="smsNotices">{translate text='Receive SMS/Text Messages'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true && $canUpdateContactInfo == true}
												<input type="checkbox" name="smsNotices" id="smsNotices" {if $profile->mobileNumber}checked='checked'{/if} data-switch="">
												<p class="help-block alert alert-warning">
													SMS/Text Messages are sent <strong>in addition</strong> to postal mail/e-mail/phone alerts. <strong>Message and data rates may apply.</strong>
													<br><br>
													To sign up for SMS/Text messages, you must opt-in above and enter your Mobile (cell phone) number below.
													<br><br>
													<a href="{$path}/Help/Home?topic=smsTerms" data-title="SMS Notice Terms" class="modalDialogTrigger">View Terms and Conditions</a>
												</p>
											{else}

											{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="mobileNumber">{translate text='Mobile Number'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true && $canUpdateContactInfo == true}
												<input type="tel" name="mobileNumber" value="{$profile->mobileNumber}" class="form-control">
											{else}
												{$profile->mobileNumber}
											{/if}
										</div>
									</div>
								{/if}

								{if !$offline && $edit == true && $canUpdateContactInfo}
									<div class="form-group">
										<div class="col-xs-8 col-xs-offset-4">
											<input type="submit" value="Update Contact Information" name="updateContactInfo" class="btn btn-sm btn-primary">
										</div>
									</div>
								{/if}
								<script type="text/javascript">
									$("#contactUpdateForm").validate(
									{*{if $primaryTheme == 'arlington'}{literal} // Keep & use for debugging*}
									{if $libraryName == 'Arlington Public Library'}{literal}
													{
														rules: {
															phone: {
																minlength: 10
															}
														},
														messages: {
															phone: {
																digits: 'Please use numbers only.',
																minlength: 'Please provide a 10 digit phone number.'
															}
														}
													}
									{/literal}{/if}
									)
								</script>
							</form>
						</div>
					</div>
				</div>

				{if $allowPinReset}
					<div class="panel active">
						<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#pinPanel">
							<div class="panel-heading">
								<div class="panel-title">
									Personal Identification Number (PIN)
								</div>
							</div>
						</a>
						<div id="pinPanel" class="panel-collapse collapse in">
							<div class="panel-body">

								{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
								<form action="" method="post" class="form-horizontal" id="pinForm">
									<input type="hidden" name="updateScope" value="pin">
									<div class="form-group">
										<div class="col-xs-4"><label for="pin" class="control-label">{translate text='Old PIN'}:</label></div>
										<div class="col-xs-8">
											<input type="password" name="pin" id="pin" value="" size="4" maxlength="4" class="form-control required digits">
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="pin1" class="control-label">{translate text='New PIN'}:</label></div>
										<div class="col-xs-8">
											<input type="password" name="pin1" id="pin1" value="" size="4" maxlength="4" class="form-control required digits">
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
										<div class="col-xs-8">
												<input type="password" name="pin2" id="pin2" value="" size="4" maxlength="4" class="form-control required digits">
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-8 col-xs-offset-4">
											<input type="submit" value="Update" name="update" class="btn btn-primary">
										</div>
									</div>
									<script type="text/javascript">
										{* input classes  'required', 'digits' are validation rules for the validation plugin *}
										{literal}
										$("#pinForm").validate({
											rules: {
												pin2: {
													equalTo: "#pin1"
												}
											}
										});
										{/literal}
									</script>
								</form>
							</div>
						</div>
					</div>
				{/if}

				{*OverDrive Options*}
				<div class="panel active">
					<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#overdrivePanel">
						<div class="panel-heading">
							<div class="panel-title">
								OverDrive Options
							</div>
						</div>
					</a>
					<div id="overdrivePanel" class="panel-collapse collapse in">
						<div class="panel-body">
							{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
							<form action="" method="post" class="form-horizontal">
								<input type="hidden" name="updateScope" value="overdrive">
								<div class="form-group">
									<div class="col-xs-4"><label for="overdriveEmail" class="control-label">{translate text='OverDrive Hold e-mail'}:</label></div>
									<div class="col-xs-8">
										{if $edit == true}<input name="overdriveEmail" id="overdriveEmail" class="form-control" value='{$profile->overdriveEmail|escape}' size='50' maxlength='75'>{else}{$profile->overdriveEmail|escape}{/if}
									</div>
								</div>
								<div class="form-group">
									<div class="col-xs-4"><label for="promptForOverdriveEmail" class="control-label">{translate text='Prompt for OverDrive e-mail'}:</label></div>
									<div class="col-xs-8">
										{if $edit == true}
											<input type="checkbox" name="promptForOverdriveEmail" id="promptForOverdriveEmail" {if $profile->promptForOverdriveEmail==1}checked='checked'{/if} data-switch="">
										{else}
											{if $profile->promptForOverdriveEmail==0}No{else}Yes{/if}
										{/if}
									</div>
								</div>
								{if $overDriveLendingOptions}
									<strong>Lending Options</strong>
									<p class="help-block">Select how long you would like to checkout each type of material from OverDrive.</p>
									{foreach from=$overDriveLendingOptions item=lendingOption}
										<div class="form-group">
											<div class="col-xs-4"><label class="control-label">{$lendingOption.name}:</label></div>
											<div class="col-xs-8">
												<div class="btn-group btn-group-sm" data-toggle="buttons">
													{foreach from=$lendingOption.options item=option}
														{if $edit}
															<label for="{$lendingOption.id}_{$option.value}" class="btn btn-sm btn-default {if $option.selected}active{/if}"><input type="radio" name="{$lendingOption.id}" value="{$option.value}" id="{$lendingOption.id}_{$option.value}" {if $option.selected}checked="checked"{/if} class="form-control">&nbsp;{$option.name}</label>
															&nbsp; &nbsp;
														{elseif $option.selected}
															{$option.name}
														{/if}
													{/foreach}
													</div>
											</div>
										</div>
									{/foreach}
								{else}
									<p class="help-block alert alert-warning">
										{$overdrivePreferencesNotice}
									</p>
								{/if}
								{if !$offline && $edit == true}
									<div class="form-group">
										<div class="col-xs-8 col-xs-offset-4">
											<input type="submit" value="Update OverDrive Options" name="updateOverDrive" class="btn btn-sm btn-primary">
										</div>
									</div>
								{/if}
							</form>
						</div>
					</div>
				</div>

				{*User Preference Options*}
				{if $showAlternateLibraryOptions || $userIsStaff || ($showRatings && $showComments)}
				<div class="panel active">
					<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#userPreferencePanel">
						<div class="panel-heading">
							<div class="panel-title">
								My Preferences
							</div>
						</div>
					</a>
					<div id="userPreference" class="panel-collapse collapse in">
						<div class="panel-body">
							{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
							<form action="" method="post" class="form-horizontal">
								<input type="hidden" name="updateScope" value="userPreference">

								{if $showAlternateLibraryOptions}
									<div class="form-group">
										<div class="col-xs-4"><label for="myLocation1" class="control-label">{translate text='My First Alternate Library'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true}
												{html_options name="myLocation1" id="myLocation1" class="form-control" options=$locationList selected=$profile->myLocation1Id}
											{else}
												{$profile->myLocation1|escape}
											{/if}
										</div>
									</div>
									<div class="form-group">
										<div class="col-xs-4"><label for="myLocation2" class="control-label">{translate text='My Second Alternate Library'}:</label></div>
										<div class="col-xs-8">{if $edit == true}{html_options name="myLocation2" id="myLocation2" class="form-control" options=$locationList selected=$profile->myLocation2Id}{else}{$profile->myLocation2|escape}{/if}</div>
									</div>
								{/if}

								{if $showRatings && $showComments}
									<div class="form-group">
										<div class="col-xs-4"><label for="noPromptForUserReviews" class="control-label">{translate text='Do not prompt me for reviews after rating titles'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true}
												<input type="checkbox" name="noPromptForUserReviews" id="noPromptForUserReviews" {if $profile->noPromptForUserReviews==1}checked='checked'{/if} data-switch="">
											{else}
												{if $profile->noPromptForUserReviews==0}No{else}Yes{/if}
											{/if}
											<p class="help-block alert alert-warning">When you rate an item by clicking on the stars, you will be asked to review that item also. Setting this option to <strong>&quot;on&QUOT;</strong> lets us know you don't want to give reviews after you have rated an item by clicking its stars.</p>
										</div>
									</div>
								{/if}

								{if $userIsStaff}
									<div class="form-group">
										<div class="col-xs-4"><label for="bypassAutoLogout" class="control-label">{translate text='Bypass Automatic Logout'}:</label></div>
										<div class="col-xs-8">
											{if $edit == true}
												<input type="checkbox" name="bypassAutoLogout" id="bypassAutoLogout" {if $profile->bypassAutoLogout==1}checked='checked'{/if} data-switch="">
											{else}
												{if $profile->bypassAutoLogout==0}No{else}Yes{/if}
											{/if}
										</div>
									</div>
								{/if}

								{if !$offline && $edit == true}
									<div class="form-group">
										<div class="col-xs-8 col-xs-offset-4">
											<input type='submit' value='Update My Preferences' name='updateMyPreferences' class="btn btn-sm btn-primary">
										</div>
									</div>
								{/if}
							</form>
						</div>
					</div>
				</div>
				{/if}

				{if $allowAccountLinking}
					<div class="panel active">
						<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#linkedAccountPanel">
							<div class="panel-heading">
								<div class="panel-title">
									Linked Accounts
								</div>
							</div>
						</a>
						<div id="linkedAccountPanel" class="panel-collapse collapse in">
							<div class="panel-body">
								<p class="alert alert-info">
									Linked accounts allow you to easily maintain multiple accounts for the library so you can see all of your information in one place. Information from linked accounts will appear when you view your checkouts, holds, etc in the main account.
								</p>
									<div class="lead">Additional accounts to manage</div>
									<p>The following accounts can be managed from this account.</p>
									<ul>
										{foreach from=$profile->linkedUsers item=tmpUser} {* Show linking for the account currently chosen for display in account settings *}
											<li>{$tmpUser->getNameAndLibraryLabel()} <a href="#" onclick="VuFind.Account.removeLinkedUser({$tmpUser->id});">Remove</a> </li>
											{foreachelse}
											<li>None</li>
										{/foreach}
									</ul>
								{if $user->id == $profile->id}{* Only allow account adding for the actual account user is logged in with *}
									<button class="btn btn-default btn-xs" onclick="VuFind.Account.addAccountLink()">Add an Account</button>
								{else}
									<p>Log into this account to add other accounts to it.</p>
								{/if}
								<div class="lead">Other accounts that can view this account</div>
								<p>The following accounts can view checkout and hold information from this account.  If someone is viewing your account that you do not want to have access, please contact library staff.</p>
								<ul>
								{foreach from=$profile->getViewers() item=tmpUser}
									<li>{$tmpUser->getNameAndLibraryLabel()}</li>
								{foreachelse}
									<li>None</li>
								{/foreach}
								</ul>
							</div>
						</div>
					</div>
				{/if}

				{* Display user roles if the user has any roles*}
				{if count($profile->roles) > 0}
					<div class="panel active">
						<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#rolesPanel">
							<div class="panel-heading">
								<div class="panel-title">
									Roles
								</div>
							</div>
						</a>
						<div id="rolesPanel" class="panel-collapse collapse in">
							<div class="panel-body">
								{foreach from=$profile->roles item=role}
									<div class="row"><div class="col-xs-12">{$role}</div></div>
								{/foreach}
							</div>
						</div>
					</div>
				{/if}
			</div>

			<script type="text/javascript">
				{* Initiate any checkbox with a data attribute set to data-switch=""  as a bootstrap switch *}
				{literal}
				$(function(){ $('input[type="checkbox"][data-switch]').bootstrapSwitch()})
				{/literal}
			</script>

		{else}
			<div class="page">
				You must login to view this information. Click <a href="{$path}/MyResearch/Login">here</a> to login.
			</div>
		{/if}
	</div>
{/strip}
