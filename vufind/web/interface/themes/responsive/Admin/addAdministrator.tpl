{strip}
<div id="main-content" class="col-tn-12 col-xs-12">
	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{/if}
		<fieldset>
			<legend>Setup a new administrator</legend>
			<div class="row form-group">
				<div class="col-sm-10">
				</div>
			</div>

			<div class="form-group">
				{assign var=property value=$structure.roles}
				{assign var=propName value=$property.property}
				<label for='{$propName}' class='control-label'>Roles</label>
				<div class="controls">
					{* Display the list of roles to add *}
					{if isset($property.listStyle) && $property.listStyle == 'checkbox'}
						{foreach from=$property.values item=propertyName key=propertyValue}
							<label class="checkbox">
								<input name='{$propName}[{$propertyValue}]' type="checkbox" value='{$propertyValue}' {if is_array($propValue) && in_array($propertyValue, array_keys($propValue))}checked='checked'{/if} >{$propertyName}
							</label>
						{/foreach}
					{else}
						<select name='{$propName}' id='{$propName}' multiple="multiple">
						{foreach from=$property.values item=propertyName key=propertyValue}
							<option value='{$propertyValue}' {if $propValue == $propertyValue}selected='selected'{/if}>{$propertyName}</option>
						{/foreach}
						</select>
					{/if}
				</div>
			</div>
			<div class="form-group">
				<div class="controls">
					<input type="submit" name="submit" value="Update User" class="btn btn-primary">  <a href='{$path}/Admin/{$toolName}?objectAction=list' class="btn btn-default">Return to List</a>
				</div>
			</div>
		</fieldset>
	</form>
</div>
{/strip}