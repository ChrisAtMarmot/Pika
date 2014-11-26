VuFind.Browse = (function(){
	return {
		curPage: 1,
		curCategory: '',
		addToHomePage: function(searchId){
			VuFind.Account.ajaxLightbox(Globals.path + '/Browse/AJAX?method=getAddBrowseCategoryForm&searchId=' + searchId, true);
			return false;
		},

		changeBrowseCategory: function(categoryTextId){
			var url = Globals.path + '/Browse/AJAX?method=getBrowseCategoryInfo&textId=' + categoryTextId;
			$.getJSON(url, function(data){
				if (data.result == false){
					VuFind.showMessage("Error loading browse information", "Sorry, we were not able to find titles for that category");
				}else{
					var label = data.label;
					$('.selected-browse-label-text').html(label);
					$('.selected-browse-label-search-text').html(label);
					$('#home-page-browse-thumbnails').html(data.records);
					console.log(data.records);
					$('#selected-browse-search-link').attr('href', data.searchUrl);
					$('.browse-category').removeClass('selected');
					VuFind.Browse.curPage = 1;
					VuFind.Browse.curCategory = data.textId;
					$('#browse-category-' + VuFind.Browse.curCategory).addClass('selected');
				}
			});
			return false;
		},

		createBrowseCategory: function(){
			var url = Globals.path + "/Browse/AJAX?method=createBrowseCategory" ;
			url += "&searchId=" + $('#searchId').val();
			url += "&categoryName=" + $('#categoryName').val();
			$.getJSON(url, function(data){
				if (data.result == false){
					VuFind.showMessage("Unable to create category", data.message);
				}else{
					VuFind.showMessage("Successfully added", "This search was added to the homepage successfully.", true);
				}
			});
			return false;
		},


		getMoreResults: function(){
			var url = Globals.path + '/Browse/AJAX?method=getMoreBrowseResults&textId=' + this.curCategory + "&pageToLoad=" + (this.curPage + 1);
			$.getJSON(url, function(data){
				if (data.result == false){
					VuFind.showMessage("Error loading browse information", "Sorry, we were not able to find titles for that category");
				}else{
					$('#home-page-browse-thumbnails').append(data.records);
					VuFind.Browse.curPage++;
				}
			});
			return false;
		}
	}
}(VuFind.Browse || {}));

$(document).ready(function(){
	var browseCategoryCarousel = $("#browse-category-carousel");
	browseCategoryCarousel.on('jcarousel:create jcarousel:reload', function() {
		var element = $(this), width = element.innerWidth();

		if (width > 700) {
			width = width / 4;
		} else if (width > 5500) {
			width = width / 3;
		} else if (width > 400) {
			width = width / 2;
		}

		element.jcarousel('items').css('width', width + 'px');
	});
	browseCategoryCarousel.on('jcarousel:targetin', 'li', function(){
		var categoryId = $(this).data('category-id');
		VuFind.Browse.changeBrowseCategory(categoryId);
	});


	// Incorporate swiping gestures into the jcarousel. pascal 11-26-2014
	var scrollFactor = 15; // swipe size per item to scroll.
	$('.jcarousel').touchwipe({
		wipeLeft : function(dx){
			var scrollInterval = Math.round(dx / scrollFactor); // vary scroll interval based on wipe length
			$('.jcarousel').jcarousel('scroll', '-='+scrollInterval);
		},
		wipeRight: function(dx) {
			var scrollInterval = Math.round(dx / scrollFactor); // vary scroll interval based on wipe length
			$('.jcarousel').jcarousel('scroll', '+='+scrollInterval);
		}
	});
});