/**
 * Created by pbrammeier on 12/16/2015.
 */

// Animate Menu Side Bar
//$("#slide").animate({width:'toggle'},350);

VuFind.Menu = (function(){
	$(function(){
		// Page Initializations
		VuFind.Menu.AllSideBarSelectors = VuFind.Menu.SideBarSearchSelectors + ',' + VuFind.Menu.SideBarAccountSelectors + ',' + VuFind.Menu.SideBarMenuSelectors;
		// TODO: Add ExploreMoreSelectors; + ',' + VuFind.Menu.ExploreMoreSelectors

		//// Highlight Selected Menu Icon
		//$('.menu-icon').click(function(){
		//	$('.menu-icon').removeClass('menu-icon-selected');
		//	$(this).addClass('menu-icon-selected')
		//});

		// Set up Sticky Menus
		VuFind.Menu.stickyMenu('#horizontal-menu-bar-container', 'sticky-menu-bar');
		VuFind.Menu.stickyMenu('#vertical-menu-bar', 'sticky-sidebar');

		// Trigger Sidebar collapse on resize from horizontal menu to vertical menu
		//$(window).resize(function(){
		//	console.log($(window).width(), $(this).width());
		//
		//});

		if ($('#horizontal-menu-bar-container').is(':visible')) {
			VuFind.Menu.hideAllFast();
			$('#refineSearch').hide();

		}
	});
	return {
		SideBarSearchSelectors: '#home-page-search,#horizontal-search-container,#narrow-search-label,#facet-accordion,#results-sort-label,#results-sort-label+div.row,#remove-search-label,#remove-search-label+.applied-filters',
		SideBarAccountSelectors: '#home-page-login,#home-account-links',
		SideBarMenuSelectors: '#home-page-login,#home-page-library-section',
		ExploreMoreSelectors: '',
		AllSideBarSelectors: '', // Set above

		stickyMenu: function(menuContainerSelector, stickyMenuClass){
			var menu = $(menuContainerSelector),
					viewportHeight = $(window).height(),
					switchPosition; // Meant to remain constant for the event handler below
			if (menu.is(':visible')) switchPosition = menu.offset().top;
			$(window).resize(function(){
				viewportHeight = $(this).height()
			});
			$(window).scroll(function(){
				if (menu.is(':visible') && viewportHeight < $('#main-content-with-sidebar').height()) { // only do this if the menu is visible & the page is larger than the viewport
					if (typeof switchPosition == 'undefined') {
						switchPosition = menu.offset().top
					}
					var fixedOffset = menu.offset().top,
							notFixedScrolledPosition = $(this).scrollTop();
					//console.log('Selector :', menuContainerSelector, 'fixedOffset : ', fixedOffset, ' notFixedScrolledPosition : ', notFixedScrolledPosition, 'switch position : ', switchPosition);

					/*Toggle into an embedded mode*/
					if (menu.is('.' + stickyMenuClass) && fixedOffset <= switchPosition) {
						menu.removeClass(stickyMenuClass)
					}
					/*Toggle into a fixed mode*/
					if (!menu.is('.' + stickyMenuClass) && notFixedScrolledPosition >= switchPosition) {
						menu.addClass(stickyMenuClass)
					}
				}
			})
		},

		hideAll: function(){
			return $(VuFind.Menu.AllSideBarSelectors).filter(':visible').slideUp() // return of object is needed for $when(VuFind.Menu.hideAll()).done() calls
		},

		// This version is for hiding content without using an animation.
		// This is important for the initial page load, putting content in the necessary state with out be distracting
		hideAllFast: function(){
			return $(VuFind.Menu.AllSideBarSelectors).filter(':visible').hide() // return of object is needed for $when(VuFind.Menu.hideAll()).done() calls
		},

		collapseSideBar: function(){
			if ($(VuFind.Menu.AllSideBarSelectors).filter(':visible').length == 0) {
				$('#side-bar,#vertical-menu-bar-container').addClass('collapsedSidebar');
				$('#main-content-with-sidebar').addClass('mainContentWhenSiderbarCollapsed');
			}
		},

		openSideBar: function(){
			$('#main-content-with-sidebar').removeClass('mainContentWhenSiderbarCollapsed');
			$('#side-bar,#vertical-menu-bar-container').removeClass('collapsedSidebar');
		},

		showMenuSection: function(sectionSelector, clickedElement){
			$.when( this.hideAll() ).done(function(){
				var elem = $(clickedElement),
						parent = elem.parent('.menu-bar-option'); // For Vertical Menu Bar only
				// Vertical Menu Bar
				if (parent.length > 0) {
					if (parent.is('.menu-icon-selected')) {
						parent.removeClass('menu-icon-selected');
						VuFind.Menu.collapseSideBar()
					} else {
						$('.menu-bar-option').removeClass('menu-icon-selected');
						parent.addClass('menu-icon-selected');
						VuFind.Menu.openSideBar();
						$(sectionSelector).slideDown()
					}
				}

				// Horizontal Menu Bar
				else { // un-selecting current option
					VuFind.Menu.openSideBar();
					if ( elem.is('.menu-icon-selected')){
						elem.removeClass('menu-icon-selected');
						$(sectionSelector).slideUp();

					}else { // selecting an option
						$('.menu-icon-selected', '#horizontal-menu-bar-container').removeClass('menu-icon-selected');
						 elem.addClass('menu-icon-selected');
						$(sectionSelector).slideDown();
					}
				}
			})
		},

		showSearch: function(clickedElement){
			var parent = $(clickedElement).parent('.menu-bar-option'); // For Vertical Menu Bar only
			if (parent && !parent.is('.menu-icon-selected') &&  $('#home-page-search,#narrow-search-label,#facet-accordion,#results-sort-label').length == 0) {
				// When Sidebar is empty and we are opening the horizontal search bar, don't open the sidebar dib.
				$.when( this.hideAll() ).done(function(){
					VuFind.Menu.collapseSideBar();  // close sidebar in case it is open
					$('.menu-bar-option').removeClass('menu-icon-selected');
					parent.addClass('menu-icon-selected');
					$('#horizontal-search-container').slideDown()
				})
			} else {
				this.showMenuSection(this.SideBarSearchSelectors, clickedElement)
			}
		},

		showMenu: function(clickedElement){
			this.showMenuSection(this.SideBarMenuSelectors, clickedElement)
		},

		showAccount: function(clickedElement){
			this.showMenuSection(this.SideBarAccountSelectors, clickedElement)
		},

		showExploreMore: function(clickedElement){
			this.showMenuSection(this.ExploreMoreSelectors, clickedElement)
		},

		showSearchFacets: function(){
			$('#refineSearch').toggle();
			var btn = $('#refineSearchButton');
			btn.text( btn.text() == 'Refine Search' ? 'Hide Refine Search' : 'Refine Search' );
		}
	}
}(VuFind.Menu || {}));