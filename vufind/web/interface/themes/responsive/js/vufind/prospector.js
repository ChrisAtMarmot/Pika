VuFind.Prospector = (function(){
	return {
		getProspectorResults: function(prospectorNumTitlesToLoad, prospectorSavedSearchId){
			var url = Globals.path + "/Search/AJAX";
			var params = "method=getProspectorResults&prospectorNumTitlesToLoad=" + encodeURIComponent(prospectorNumTitlesToLoad) + "&prospectorSavedSearchId=" + encodeURIComponent(prospectorSavedSearchId);
			var fullUrl = url + "?" + params;
			$.ajax({
				url: fullUrl,
				success: function(data) {
					var prospectorSearchResults = $(data).find("ProspectorSearchResults").text();
					if (prospectorSearchResults) {
						if (prospectorSearchResults.length > 0){
							$("#prospectorSearchResultsPlaceholder").html(prospectorSearchResults);
						}
					}
				}
			});
		},

		loadRelatedProspectorTitles: function (id) {
			var url;
			url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX";
			var params = "method=getProspectorInfo";
			var fullUrl = url + "?" + params;
			$.getJSON(fullUrl, function(data) {
				if (data.numTitles == 0){
					$("#prospectorPanel").hide();
				}else{
					$("#inProspectorPlaceholder").html(data.formattedData);
				}
			});
		}
	}
}(VuFind.Prospector || {}));