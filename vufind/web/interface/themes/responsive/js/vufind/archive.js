/**
 * Created by mark on 12/10/2015.
 */
VuFind.Archive = (function(){
	return {
		archive_map: null,
		archive_info_window: null,
		curPage: 1,
		markers: [],
		sort: 'title',
		openSeaDragonViewer: null,
		pageDetails: [],
		multiPage: false,
		allowPDFView: true,
		activeBookViewer: 'jp2',
		activeBookPage: null,
		activeBookPid: null,
		openSeadragonViewerSettings: function(){
			return {
				"id": "pika-openseadragon",
				"prefixUrl": Globals.encodedRepositoryUrl + "\/sites\/all\/libraries\/openseadragon\/images\/",
				"debugMode": false,
				"djatokaServerBaseURL": Globals.encodedRepositoryUrl + "\/AJAX\/DjatokaResolver",
				"tileSize": 256,
				"tileOverlap": 0,
				"animationTime": 1.5,
				"blendTime": 0.1,
				"alwaysBlend": false,
				"autoHideControls": 1,
				"immediateRender": true,
				"wrapHorizontal": false,
				"wrapVertical": false,
				"wrapOverlays": false,
				"panHorizontal": 1,
				"panVertical": 1,
				"minZoomImageRatio": 0.35,
				"maxZoomPixelRatio": 2,
				"visibilityRatio": 0.5,
				"springStiffness": 5,
				"imageLoaderLimit": 5,
				"clickTimeThreshold": 300,
				"clickDistThreshold": 5,
				"zoomPerClick": 2,
				"zoomPerScroll": 1.2,
				"zoomPerSecond": 2,
				"showNavigator": 1,
				"defaultZoomLevel": 0,
				"homeFillsViewer": false
			}
		},

		changeActiveBookViewer: function(viewerName, pagePid){
			this.activeBookViewer = viewerName;
			// $('#view-toggle').children(".btn .active").removeClass('active');
			if (viewerName == 'pdf' && this.allowPDFView){
				$('#view-toggle-pdf').prop('checked', true);
						// .parent('.btn').addClass('active');
				$("#view-pdf").show();
				$("#view-image").hide();
				$("#view-transcription").hide();
			}else if (viewerName == 'image' || (viewerName == 'pdf' && !this.allowPDFView)){
				$('#view-toggle-image').prop('checked', true);
						// .parent('.btn').addClass('active');
				$("#view-image").show();
				$("#view-pdf").hide();
				$("#view-transcription").hide();
				this.activeBookViewer = 'image';
			}else if (viewerName == 'transcription'){
				$('#view-toggle-transcription').prop('checked', true);
					// .parent('.btn').addClass('active');
				$("#view-transcription").show();
				$("#view-pdf").hide();
				$("#view-image").hide();

			}
			return this.loadPage(pagePid);
		},

		initializeOpenSeadragon: function(viewer){
			viewer.addHandler("open", this.update_clip);
			viewer.addHandler("animationfinish", this.update_clip);
		},

		getMoreExhibitResults: function(exhibitPid){
			this.curPage = this.curPage +1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort;
			url = url + "&reloadHeader=0";

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		getMoreMapResults: function(exhibitPid, placePid){
			this.curPage = this.curPage +1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid + "&page=" + this.curPage + "&sort=" + this.sort;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter[]="+$(this).val();
			});
			url = url + "&reloadHeader=0";

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		getMoreTimelineResults: function(exhibitPid){
			this.curPage = this.curPage +1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter[]="+$(this).val();
			});
			url = url + "&reloadHeader=0";

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		getMoreScrollerResults: function(pid){
			this.curPage = this.curPage +1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid + "&page=" + this.curPage + "&sort=" + this.sort;

			$.getJSON(url, function(data){
				if (data.success){
					$("#nextInsertPoint").replaceWith(data.relatedObjects);
				}
			});
		},

		handleMapClick: function(markerIndex, exhibitPid, placePid, label, redirect){
			$("#exhibit-results-loading").show();
			this.archive_info_window.setContent(label);
			if (markerIndex >= 0){
				this.archive_info_window.open(this.archive_map, this.markers[markerIndex]);
			}

			if (redirect != "undefined" && redirect === true){
				var newUrl = VuFind.buildUrl(document.location.origin + document.location.pathname, 'placePid', placePid);
				var newUrl = VuFind.buildUrl(newUrl, 'style', 'map');
				document.location.href = newUrl;
			}
			$.getJSON(Globals.path + "/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			var stateObj = {
				marker: markerIndex,
				exhibitPid: exhibitPid,
				placePid: placePid,
				label: label,
				page: "MapExhibit"
			};
			var newUrl = VuFind.buildUrl(document.location.origin + document.location.pathname, 'placePid', placePid);
			var currentParameters = VuFind.getQuerystringParameters();
			if (currentParameters["style"] != undefined){
				var newUrl = VuFind.buildUrl(newUrl, 'style', currentParameters["style"]);
			}
			//Push the new url, but only if we aren't going back where we just were.
			if (document.location.href != newUrl){
				history.pushState(stateObj, label, newUrl);
			}
			return false;
		},

		handleTimelineClick: function(exhibitPid){
			$("#exhibit-results-loading").show();

			$.getJSON(Globals.path + "/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			return false;
		},

		handleCollectionScrollerClick: function(pid){
			$("#exhibit-results-loading").show();

			$.getJSON(Globals.path + "/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid, function(data){
				if (data.success){
					$("#related-objects-for-exhibit").html(data.relatedObjects);
					$("#exhibit-results-loading").hide();
				}
			});
			return false;
		},

		handleBookClick: function(bookPid, pagePid, bookViewer) {
			// Load specified page & viewer
			//Loading message
			//Load Page  set-up
			VuFind.Archive.activeBookPid = bookPid;
			VuFind.Archive.changeActiveBookViewer(bookViewer, pagePid);

			// store in browser history
			var stateObj = {
				bookPid: bookPid,
				pagePid: pagePid,
				viewer: bookViewer,
				page: 'Book'
			},
					newUrl = VuFind.buildUrl(document.location.origin + document.location.pathname, 'bookPid', bookPid),
					newUrl = VuFind.buildUrl(newUrl, 'pagePid', pagePid),
					newUrl = VuFind.buildUrl(newUrl, 'viewer', bookViewer);
			//Push the new url, but only if we aren't going back where we just were.
			if (document.location.href != newUrl){
				history.pushState(stateObj, '', newUrl);
			}
			return false;

		},

		reloadMapResults: function(exhibitPid, placePid, reloadHeader){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForMappedCollection&collectionId=" + exhibitPid + "&placeId=" + placePid + "&page=" + this.curPage + "&sort=" + this.sort;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter[]="+$(this).val();
			});
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		reloadTimelineResults: function(exhibitPid, reloadHeader){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForTimelineExhibit&collectionId=" + exhibitPid + "&page=" + this.curPage + "&sort=" + this.sort;
			$("input[name=dateFilter]:checked").each(function(){
				url = url + "&dateFilter[]="+$(this).val();
			});
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		reloadScrollerResults: function(pid, reloadHeader){
			$("#exhibit-results-loading").show();
			this.curPage = 1;
			var url = Globals.path + "/Archive/AJAX?method=getRelatedObjectsForScroller&pid=" + pid + "&page=" + this.curPage + "&sort=" + this.sort;
			url = url + "&reloadHeader=" + reloadHeader;

			$.getJSON(url, function(data){
				if (data.success){
					if (reloadHeader){
						$("#related-objects-for-exhibit").html(data.relatedObjects);
					}else{
						$("#results").html(data.relatedObjects);
					}
					$("#exhibit-results-loading").hide();
				}
			});
		},

		loadExploreMore: function(pid){
			$.getJSON(Globals.path + "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getExploreMoreContent", function(data){
				if (data.success){
					$("#explore-more-body").html(data.exploreMore);
					VuFind.initCarousels("#explore-more-body .jcarousel");
				}
			}).fail(VuFind.ajaxFail);
		},

		loadMetadata: function(pid, secondaryId){
			var url = Globals.path + "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getMetadata";
			if (secondaryId !== undefined){
				url += "&secondaryId=" + secondaryId;
			}
			var metadataTarget = $('#archive-metadata');
			metadataTarget.html("Please wait while we load information about this object...")
			$.getJSON(url, function(data) {
				if (data.success) {
					metadataTarget.html(data.metadata);
				}
			}).fail(
					function(){metadataTarget.html("Could not load metadata.")}
			);
		},

		/**
		 * Load a new page into the active viewer
		 *
		 * @param pid
		 */
		loadPage: function(pid){
			if (pid == null){
				return false;
			}
			var pageChanged = false;
			if (this.activeBookPage != pid){
				pageChanged = true;
				this.curPage = this.pageDetails[pid]['index'];
			}
			this.activeBookPage = pid;
			// console.log('Page: '+ this.activeBookPage, 'Active Viewer : '+ this.activeBookViewer);
			if (this.pageDetails[pid]['transcript'] == ''){
				$('#view-toggle-transcription').parent().hide();
				if (this.activeBookViewer == 'transcription') {
					this.changeActiveBookViewer('image', pid);
					return false;
				}
			}else{
				$('#view-toggle-transcription').parent().show();
			}

			if (this.activeBookViewer == 'pdf') {
				// console.log('PDF View called');
				$('#view-pdf').html(
						$('<object />').attr({
							type: 'application/pdf',
							data: this.pageDetails[pid]['pdf'],
							class: 'book-pdf' // Class that styles/sizes the PDF page
						})
				);
			}else if(this.activeBookViewer == 'transcription') {
				// console.log('Transcript Viewer called');
				var transcriptIdentifier = this.pageDetails[pid]['transcript'];
				var url = Globals.path + "/Archive/AJAX?transcriptId=" + encodeURI(transcriptIdentifier) + "&method=getTranscript";
				var transcriptionTarget = $('#view-transcription');
				transcriptionTarget.html("Loading Transcript, please wait.");
				$.getJSON(url, function(data) {
					if (data.success) {
						transcriptionTarget.html(data.transcript);
					}
				}).fail(
					function(){transcriptionTarget.html("Could not load Transcript.")}
				);

				// var islandoraURL = this.pageDetails[pid]['transcript'];
				// var reverseProxy = islandoraURL.replace(/([^\/]*)(?=\/islandora\/)/, location.host);
				// // reverseProxy = reverseProxy.replace('https', 'http'); // TODO: remove, for local instance only (no https)
				// // console.log('Fetching: '+reverseProxy);
				//
				// $('#view-transcription').load(reverseProxy);
			}else if (this.activeBookViewer == 'image'){
				var tile = new OpenSeadragon.DjatokaTileSource(
						Globals.url + "/AJAX/DjatokaResolver",
						this.pageDetails[pid]['jp2'],
						VuFind.Archive.openSeadragonViewerSettings()
				);
				if (!$('#pika-openseadragon').hasClass('processed')) {
					$('#pika-openseadragon').addClass('processed');
					settings = VuFind.Archive.openSeadragonViewerSettings();
					settings.tileSources = new Array();
					settings.tileSources.push(tile);
					VuFind.Archive.openSeaDragonViewer = new OpenSeadragon(settings);
				}else{
					//VuFind.Archive.openSeadragonViewerSettings.tileSources = new Array();
					//VuFind.Archive.openSeaDragonViewer.close();
					VuFind.Archive.openSeaDragonViewer.open(tile);
				}
				//VuFind.Archive.openSeaDragonViewer.viewport.fitVertically(true);
			}
			if (pageChanged && this.multiPage){
				if (this.pageDetails[pid]['transcript'] == ''){
					$('#view-toggle-transcription').parent().hide();
				}else{
					$('#view-toggle-transcription').parent().show();
				}
				if (this.pageDetails[pid]['pdf'] == ''){
					$('#view-toggle-pdf').parent().hide();
				}else{
					$('#view-toggle-pdf').parent().show();
				}

				this.loadMetadata(this.activeBookPid, pid);
				//$("#downloadPageAsPDF").href = Globals.path + "/Archive/" + pid + "/DownloadPDF";
				url = Globals.path + "/Archive/AJAX?method=getAdditionalRelatedObjects&id=" + pid;
				var additionalRelatedObjectsTarget = $("#additional-related-objects");
				additionalRelatedObjectsTarget.html("");
				$.getJSON(url, function(data) {
					if (data.success) {
						additionalRelatedObjectsTarget.html(data.additionalObjects);
					}
				});

				var pageScroller = $("#book-sections .jcarousel");
				if (pageScroller){
					pageScroller.jcarousel('scroll', this.curPage - 1, true);
					$('#book-sections li').removeClass('active');
					$('#book-sections .jcarousel li:eq(' + (this.curPage - 1) + ')').addClass('active');
				}
			}
			//alert("Changing display to pid " + pid + " active viewer is " + this.activeBookViewer)
			return false;
		},

		nextRandomObject: function(pid){
			var url = Globals.path + "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getNextRandomObject";
			$.getJSON(url, function(data){
				$('#randomImagePlaceholder').html(data.image);
			}).fail(VuFind.ajaxFail);
			return false;
		},

		setForExhibitNavigation : function (recordIndex, page) {
			var date = new Date();
			date.setTime(date.getTime() + (1 /*days*/ * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
			if (typeof recordIndex != 'undefined') {
				document.cookie = encodeURIComponent('recordIndex') + "=" + encodeURIComponent(recordIndex) + expires + "; path=/";
			}
			if (typeof page != 'undefined') {
				document.cookie = encodeURIComponent('page') + "=" + encodeURIComponent(page) + expires + "; path=/";
			}
		},

		showObjectInPopup: function(pid, recordIndex, page){
			var url = Globals.path + "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getObjectInfo";
					// (typeof collectionSearchId == 'undefined' ? '' : '&collectionSearchId=' + encodeURI(collectionSearchId)) +
					// (typeof recordIndex == 'undefined' ? '' : '&recordIndex=' + encodeURI(recordIndex));
			VuFind.loadingMessage();
			this.setForExhibitNavigation(recordIndex, page);

			$.getJSON(url, function(data){
				VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
			}).fail(VuFind.ajaxFail);
			return false;
		},

		// showObjectInPopup: function(pid, returnId){
		// 	var url = Globals.path + "/Archive/AJAX?id=" + encodeURI(pid) + "&method=getObjectInfo" +
		// 			(typeof returnId == 'undefined' ? '' : '&returnTo=' + encodeURI(returnId));
		// 	VuFind.loadingMessage();
		// 	$.getJSON(url, function(data){
		// 		VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
		// 	}).fail(VuFind.ajaxFail);
		// 	return false;
		// },
		//
		/**
		 * All this is doing is updating a URL so the patron can download a clipped portion of the image
		 * not needed for our basic implementation
		 *
		 * @param viewer
		 */
		update_clip: function(viewer) {
			var fitWithinBoundingBox = function(d, max) {
				if (d.width/d.height > max.x/max.y) {
					return new OpenSeadragon.Point(max.x, parseInt(d.height * max.x/d.width));
				} else {
					return new OpenSeadragon.Point(parseInt(d.width * max.y/d.height),max.y);
				}
			};
			var getDisplayRegion = function(viewer, source) {
				// Determine portion of scaled image that is being displayed.
				var box = new OpenSeadragon.Rect(0, 0, source.x, source.y);
				var container = viewer.viewport.getContainerSize();
				var bounds = viewer.viewport.getBounds();
				// If image is offset to the left.
				if (bounds.x > 0){
					box.x = box.x - viewer.viewport.pixelFromPoint(new OpenSeadragon.Point(0,0)).x;
				}
				// If full image doesn't fit.
				if (box.x + source.x > container.x) {
					box.width = container.x - viewer.viewport.pixelFromPoint(new OpenSeadragon.Point(0,0)).x;
					if (box.width > container.x) {
						box.width = container.x;
					}
				}
				// If image is offset up.
				if (bounds.y > 0) {
					box.y = box.y - viewer.viewport.pixelFromPoint(new OpenSeadragon.Point(0,0)).y;
				}
				// If full image doesn't fit.
				if (box.y + source.y > container.y) {
					box.height = container.y - viewer.viewport.pixelFromPoint(new OpenSeadragon.Point(0,0)).y;
					if (box.height > container.y) {
						box.height = container.y;
					}
				}
				return box;
			};
			var source = viewer.source;
			var zoom = viewer.viewport.getZoom();
			var size = new OpenSeadragon.Rect(0, 0, source.dimensions.x, source.dimensions.y);
			var container = viewer.viewport.getContainerSize();
			var fit_source = fitWithinBoundingBox(size, container);
			var total_zoom = fit_source.x/source.dimensions.x;
			var container_zoom = fit_source.x/container.x;
			var level = (zoom * total_zoom) / container_zoom;
			var box = getDisplayRegion(viewer, new OpenSeadragon.Point(parseInt(source.dimensions.x*level), parseInt(source.dimensions.y*level)));
			var scaled_box = new OpenSeadragon.Rect(parseInt(box.x/level), parseInt(box.y/level), parseInt(box.width/level), parseInt(box.height/level));
			var params = {
				'url_ver': 'Z39.88-2004',
				'rft_id': source.imageID,
				'svc_id': 'info:lanl-repo/svc/getRegion',
				'svc_val_fmt': 'info:ofi/fmt:kev:mtx:jpeg2000',
				'svc.format': 'image/jpeg',
				'svc.region': scaled_box.y + ',' + scaled_box.x + ',' + (scaled_box.getBottomRight().y - scaled_box.y) + ',' + (scaled_box.getBottomRight().x - scaled_box.x),
			};
			var dimensions = (zoom <= 1) ? source.dimensions.x + ',' + source.dimensions.y : container.x + ',' + container.y;
			jQuery("#clip").attr('href',  Globals.repositoryUrl + '/islandora/object/' + settings.islandoraOpenSeadragon.pid + '/print?' + jQuery.param({
						'clip': source.baseURL + '?' + jQuery.param(params),
						'dimensions': dimensions,
					}));
		},

		showSaveToListForm: function (trigger, id){
			if (Globals.loggedIn){
				VuFind.loadingMessage();
				var url = Globals.path + "/Archive/" + id + "/AJAX?method=getSaveToListForm";
				$.getJSON(url, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					VuFind.Archive.showSaveToListForm(trigger, id);
				});
			}
			return false;
		},

		saveToList: function(id){
			if (Globals.loggedIn){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = Globals.path + "/Archive/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method':'saveToList'
							,notes:notes
							,listId:listId
						};
				$.getJSON(url, params,
						function(data) {
							if (data.success) {
								VuFind.showMessage("Added Successfully", data.message, 2000); // auto-close after 2 seconds.
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				).fail(VuFind.ajaxFail);
			}
			return false;
		},

	}



}(VuFind.Archive || {}));