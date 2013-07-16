<div id="page-content" class="content">
	<div id="sidebar">
		{include file="MyResearch/menu.tpl"}

		{include file="Admin/menu.tpl"}
	</div>

	<div id="main-content">
		<h1>{$shortPageTitle}</h1>
		<a class="button" href='/Admin/LoanRuleDeterminers?objectAction=list'>Return to List</a>
		<div class="helpTextUnsized"><p>To reload loan rule determiners:
		<ol>
		<li>Open Millennium</li>
		<li>Go to the Loan Rules configuration page (In Circulation module go to Admin &gt; Parameters &gt; Circulation &gt; Loan Rule Determiner.)</li>
		<li>Copy the entire table by highlighting it and pressing Ctrl+C.</li>
		<li>Paste the data in the text area below.</li>
		<li>Select the Reload Data button.</li>
		</ol>
		</p></div>
		<form name="importLoanRules" action="/Admin/LoanRuleDeterminers" method="post">
			<div>
				<input type="hidden" name="objectAction" value="doLoanRuleDeterminerReload" />
				<textarea rows="20" cols="80" name="loanRuleDeterminerData"></textarea>
				<input type="submit" name="reload" value="Reload Data"/>
			</div>
		</form>

	</div>
</div>