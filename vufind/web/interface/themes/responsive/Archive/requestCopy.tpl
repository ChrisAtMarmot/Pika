{strip}
	<h3>Request Archive Copies of Materials</h3>
	<div class="page">
		{if $requestSubmitted}
			{if $error}
				<p>There was an error submitting your request.</p>
				<p>{$error}</p>
			{else}
				<p>Your request was submitted successfully.  The library will contact you with more information soon.</p>
			{/if}
		{else}
			<p>
				Please fill out this form to request copies of materials in the archive in physical or digital form.
				The owning library will contact you to confirm the details of your request and detail any fees associated with your request.
			</p>
			<p>
				For the best and most immediate service, please include your email address.
			</p>

			{if $captchaMessage}
				<div id="selfRegFail" class="alert alert-warning">
					{$captchaMessage}
				</div>
			{/if}
			<div id="archiveCopyRequestFormContainer">
				{$requestForm}
			</div>
		{/if}

	</div>

{/strip}