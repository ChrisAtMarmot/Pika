<textarea name='{$propName}' id='{$propName}' rows='{$property.rows}' cols='{$property.cols}' title='{$property.description}' class='form-control {if $property.required}required{/if}'>{$propValue|escape}</textarea>
{if $property.type == 'html'}
	<script type="text/javascript">
	{literal}
	$(document).ready(function(){
		CKEDITOR.replace( '{/literal}{$propName}{literal}',
		{
			toolbar : 'Full'
		});
	});
	{/literal}
	</script>
{/if}
