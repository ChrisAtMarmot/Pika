<div align="left">
	{if $message}<div class="error">{$message|translate}</div>{/if}

	{*<form id="emailListForm" action="{$path}/MyAccount/EmailList" method="post" class="form form-horizontal">
	REMOVE_DEBUG do we need an action for form? test on many browsers. plb 10-14-2014
	*}
	<form id="emailListForm" class="form form-horizontal">
		<div class="form-group">
			<input type="hidden" name="listId" value="{$listId|escape}">
			<label for="to" class="control-label col-xs-2">{translate text='To'}</label>
			<div class="col-xs-10">
				<input type="text" name="to" id="to" size="40" class="required email form-control">
			</div>
		</div>
		<div class="form-group">
			<label for="from" class="control-label col-xs-2">{translate text='From'}</label>
			<div class="col-xs-10">
				<input type="text" name="from" id="from" size="40" class="required email form-control">
			</div>
		</div>
		<div class="form-group">
			<label for="message" class="control-label col-xs-2">{translate text='Message'}</label>
			<div class="col-xs-10">
				<textarea name="message" id="message" rows="3" cols="40" class="form-control"></textarea>
			</div>
		</div>
	</form>
</div>

<script type="text/javascript">
{* // to debug with validation off
	$("#emailListForm").submit(function(){
		VuFind.Lists.SendMyListEmail();
		return false;
	}); *}
	{literal}
	$("#emailListForm").validate({
		submitHandler: function(){
			VuFind.Lists.SendMyListEmail();
		}
	});
	{/literal}
</script>