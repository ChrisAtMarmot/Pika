/**
 * @file
 * Behaviours for the Nice Menus module. This uses Superfish 1.4.8.
 *
 * @link http://users.tpg.com.au/j_birch/plugins/superfish
 */

$(document).ready(function(){
	$('ul.nice-menu:not(.nice-menus-processed)').addClass('nice-menus-processed').each(function () {
		$(this).superfish({
			// Apply a generic hover class.
			hoverClass: 'over',
			// Disable generation of arrow mark-up.
			autoArrows: false,
			// Disable drop shadows.
			dropShadows: false,
			// Mouse delay.
			delay: 800,
			// Animation speed.
			speed: 'slow'
		});

		// Add in Brandon Aaronâ€™s bgIframe plugin for IE select issues.
		// http://plugins.jquery.com/node/46/release
		$(this).find('ul').bgIframe({opacity:false});

		$('ul.nice-menu ul').css('display', 'none');
	});
});