/**
 * Created by mark on 1/24/14.
 */
VuFind.GroupedWork = (function(){
	return {
		hasTableOfContentsInRecord: false,

		clearUserRating: function (groupedWorkId){
			var url = Globals.path + '/GroupedWork/' + groupedWorkId + '/AJAX?method=clearUserRating';
			$.getJSON(url, function(data){
				if (data.result == true){
					$('.rate' + groupedWorkId).find('.ui-rater-starsOn').width(0);
					$('#myRating' + groupedWorkId).hide();
					VuFind.showMessage('Success', data.message, true);
				}else{
					VuFind.showMessage('Sorry', data.message);
				}
			});
			return false;
		},

		clearNotInterested: function (notInterestedId){
			var url = Globals.path + '/GroupedWork/' + notInterestedId + '/AJAX?method=clearNotInterested';
			$.getJSON(
					url, function(data){
						if (data.result == false){
							VuFind.showMessage('Sorry', "There was an error updating the title.");
						}else{
							$("#notInterested" + notInterestedId).hide();
						}
					}
			);
		},

		deleteReview: function(id, reviewId){
			if (confirm("Are you sure you want to delete this review?")){
				var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=deleteUserReview';
				$.getJSON(url, function(data){
					if (data.result == true){
						$('#review_' + reviewId).hide();
						VuFind.showMessage('Success', data.message, true);
					}else{
						VuFind.showMessage('Sorry', data.message);
					}
				});
			}
			return false;
		},

		forceRegrouping: function (id){
			var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=forceRegrouping';
			$.getJSON(url, function (data){
						VuFind.showMessage("Success", data.message, true, true);
						setTimeout("VuFind.closeLightbox();", 3000);
					}
			);
			return false;
		},

		forceReindex: function (id){
			var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=forceReindex';
			$.getJSON(url, function (data){
						VuFind.showMessage("Success", data.message, true, true);
						setTimeout("VuFind.closeLightbox();", 3000);
					}
			);
			return false;
		},

		getGoDeeperData: function (id, dataType){
			var placeholder;
			if (dataType == 'excerpt') {
				placeholder = $("#excerptPlaceholder");
			} else if (dataType == 'avSummary') {
				placeholder = $("#avSummaryPlaceholder");
			} else if (dataType == 'tableOfContents') {
				placeholder = $("#tableOfContentsPlaceholder");
			} else if (dataType == 'authornotes') {
				placeholder = $("#authornotesPlaceholder");
			}
			if (placeholder.hasClass("loaded")) return;
			placeholder.show();
			var url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method': 'GetGoDeeperData', dataType:dataType};
			$.getJSON(url, params, function(data) {
				placeholder.html(data.formattedData).addClass('loaded');
			});
		},

		getGoodReadsComments: function (isbn){
			$("#goodReadsPlaceHolder").replaceWith(
				"<iframe id='goodreads_iframe' class='goodReadsIFrame' src='https://www.goodreads.com/api/reviews_widget_iframe?did=DEVELOPER_ID&format=html&isbn=" + isbn + "&links=660&review_back=fff&stars=000&text=000' width='100%' height='400px' frameborder='0'></iframe>"
			);
		},

		loadEnrichmentInfo: function (id, forceReload) {
			var url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
					params = {'method':'getEnrichmentInfo'};
			if (forceReload != undefined){
				params['reload'] = true;
			}
			$.getJSON(url, params, function(data) {
					try{
						var seriesData = data.seriesInfo;
						if (seriesData && seriesData.titles.length > 0) {
							seriesScroller = new TitleScroller('titleScrollerSeries', 'Series', 'seriesList');
							$('#seriesInfo').show();
							seriesScroller.loadTitlesFromJsonData(seriesData);
							$('#seriesPanel').show();
						}else{
							$('#seriesPanel').hide();
						}
						var similarTitleData = data.similarTitles;
						if (similarTitleData && similarTitleData.titles.length > 0) {
							morelikethisScroller = new TitleScroller('titleScrollerMoreLikeThis', 'MoreLikeThis', 'morelikethisList');
							$('#moreLikeThisInfo').show();
							morelikethisScroller.loadTitlesFromJsonData(similarTitleData);
						}
						var showGoDeeperData = data.showGoDeeper;
						if (showGoDeeperData) {
							//$('#goDeeperLink').show();
							var goDeeperOptions = data.goDeeperOptions;
							//add a tab before citation for each item
							for (var option in goDeeperOptions){
								if (option == 'excerpt') {
									$("#excerptPanel").show();
								} else if (option == 'avSummary') {
									$("#avSummaryPlaceholder,#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
								} else if (option == 'tableOfContents') {
									$("#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
								} else if (option == 'authorNotes') {
									$('#authornotesPlaceholder,#authornotesPanel').show();
								}
							}
						}
						if (VuFind.GroupedWork.hasTableOfContentsInRecord){
							$("#tableofcontentstab_label,#tableOfContentsPlaceholder,#tableOfContentsPanel").show();
						}
						var relatedContentData = data.relatedContent;
						if (relatedContentData && relatedContentData.length > 0) {
							$("#relatedContentPlaceholder").html(relatedContentData);
						}
						var similarTitlesNovelist = data.similarTitlesNovelist;
						if (similarTitlesNovelist && similarTitlesNovelist.length > 0){
							$("#novelisttitlesPlaceholder").html(similarTitlesNovelist);
							$("#novelisttab_label,#similarTitlesPanel").show()
									;
						}

						var similarAuthorsNovelist = data.similarAuthorsNovelist;
						if (similarAuthorsNovelist && similarAuthorsNovelist.length > 0){
							$("#novelistauthorsPlaceholder").html(similarAuthorsNovelist);
							$("#novelisttab_label,#similarAuthorsPanel").show();
						}

						var similarSeriesNovelist = data.similarSeriesNovelist;
						if (similarSeriesNovelist && similarSeriesNovelist.length > 0){
							$("#novelistseriesPlaceholder").html(similarSeriesNovelist);
							$("#novelisttab_label,#similarSeriesPanel").show();
						}

						// Show Explore More Sidebar Section loaded above
						$('.ajax-carousel', '#explore-more-body').parents('.jcarousel-wrapper').show()
								.prev('.sectionHeader').show();
						// Initiate Any Explore More JCarousels
						VuFind.initCarousels('.ajax-carousel');

					} catch (e) {
						alert("error loading enrichment: " + e);
					}
				}
			);
		},

		loadReviewInfo: function (id) {
			var url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getReviewInfo";
			$.getJSON(url, function(data) {
				if (data.numSyndicatedReviews == 0){
					$("#syndicatedReviewsPanel").hide();
				}else{
					var syndicatedReviewsData = data.syndicatedReviewsHtml;
					if (syndicatedReviewsData && syndicatedReviewsData.length > 0) {
						$("#syndicatedReviewPlaceholder").html(syndicatedReviewsData);
					}
				}
				if (data.numEditorialReviews == 0){
					$("#editorialReviewsPanel").hide();
				}else{
					var editorialReviewsData = data.editorialReviewsHtml;
					if (editorialReviewsData && editorialReviewsData.length > 0) {
						$("#editorialReviewPlaceholder").html(editorialReviewsData);
					}
				}

				if (data.numCustomerReviews == 0){
					$("#borrowerReviewsPanel").hide();
				}else{
					var customerReviewsData = data.customerReviewsHtml;
					if (customerReviewsData && customerReviewsData.length > 0) {
						$("#customerReviewPlaceholder").html(customerReviewsData);
					}
				}
			});
		},

		markNotInterested: function (recordId){
			if (Globals.loggedIn){
				var url = Globals.path + '/GroupedWork/' + recordId + '/AJAX?method=markNotInterested';
				$.getJSON(
						url, function(data){
							if (data.result == true){
								VuFind.showMessage('Success', data.message);
							}else{
								VuFind.showMessage('Sorry', data.message);
							}
						}
				);
				return false;
			}else{
				return VuFind.Account.ajaxLogin(null, function(){markNotInterested(source, recordId)}, false);
			}
		},

		reloadCover: function (id){
			var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=reloadCover';
			$.getJSON(url, function (data){
						VuFind.showMessage("Success", data.message, true, true);
						//setTimeout("VuFind.closeLightbox();", 3000);
					}
			);
			return false;
		},

		reloadEnrichment: function (id){
			VuFind.GroupedWork.loadEnrichmentInfo(id, true);
		},

		reloadIslandora: function(id){
			var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=reloadIslandora';
			$.getJSON(url, function (data){
					VuFind.showMessage("Success", data.message, true, true);
					//setTimeout("VuFind.closeLightbox();", 3000);
				}
			);
			return false;
		},

		removeTag:function(id, tag){
			if (confirm("Are you sure you want to remove the tag \"" + tag + "\" from this title?")){
				var url = Globals.path + '/GroupedWork/' + id + '/AJAX?method=removeTag';
				url += "&tag=" + encodeURIComponent(tag);
				$.getJSON(
						url, function(data){
							if (data.result == true){
								VuFind.showMessage('Success', data.message);
							}else{
								VuFind.showMessage('Sorry', data.message);
							}
						}
				);
				return false;
			}
			return false;
		},

		saveReview: function(id){
			if (!Globals.loggedIn){
				VuFind.Account.ajaxLogin(null, function(){
					this.saveReview(id)
				})
			} else {
				var comment = $('#comment' + id).val(),
						rating = $('#rating' + id).val(),
						url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params =  {
							method : 'saveReview'
							,comment : comment
							,rating : rating
						};
				$.getJSON(url, params,
					function(data) {
						if (data.success) {
							if (data.newReview){
								$("#customerReviewPlaceholder").append(data.reviewHtml);
							}else{
								$("#review_" + data.reviewId).replaceWith(data.reviewHtml);
							}
							VuFind.closeLightbox();
						} else {
							VuFind.showMessage("Error", data.message);
						}
					}
				).fail(VuFind.ajaxFail);
			}
			return false;
		},

		saveTag: function(id){
			var tag = $("#tags_to_apply").val();
			$("#saveToList-button").prop('disabled', true);

			var url = Globals.path + "/GroupedWork/" + id + "/AJAX";
			var params = "method=SaveTag&" +
					"tag=" + encodeURIComponent(tag);
			$.ajax({
				url: url+'?'+params,
				dataType: "json",
				success: function(data) {
					if (data.success) {
						VuFind.showMessage("Success", data.message);
						setTimeout("VuFind.closeLightbox();", 3000);
					} else {
						VuFind.showMessage("Error adding tags", "There was an unexpected error adding tags to this title.<br/>" + data.message);
					}

				},
				error: function(jqXHR, textStatus) {
					VuFind.showMessage("Error adding tags", "There was an unexpected error adding tags to this title.<br/>" + textStatus);
				}
			});
		},

		saveToList: function(id){
			if (Globals.loggedIn){
				var listId = $('#addToList-list').val(),
						notes  = $('#addToList-notes').val(),
						url    = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
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

		sendEmail: function(id){
			if (Globals.loggedIn){
				var from = $('#from').val(),
						to = $('#to').val(),
						message = $('#message').val(),
						related_record = $('#related_record').val(),
						url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method' : 'sendEmail',
							from : from,
							to : to,
							message : message,
							related_record : related_record
						};
				$.getJSON(url, params,
						function(data) {
							if (data.result) {
								VuFind.showMessage("Success", data.message);
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				).fail(VuFind.ajaxFail);
			}
			return false;
		},

		sendSMS: function(id){
			if (Globals.loggedIn){
				var phoneNumber = $('#sms_phone_number').val(),
						provider = $('#provider').val(),
						related_record = $('#related_record').val(),
						url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX",
						params = {
							'method' : 'sendSMS',
							provider : provider,
							sms_phone_number : phoneNumber,
							related_record : related_record
						};
				$.getJSON(url, params,
						function(data) {
							if (data.result) {
								VuFind.showMessage("Success", data.message);
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				).fail(VuFind.ajaxFail);
			}
			return false;
		},

		showEmailForm: function(trigger, id){
			if (Globals.loggedIn){
				VuFind.loadingMessage();
				$.getJSON(Globals.path + "/GroupedWork/" + id + "/AJAX?method=getEmailForm", function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					return VuFind.GroupedWork.showEmailForm(trigger, id);
				}, false);
			}
			return false;
		},


		showGroupedWorkInfo:function(id, browseCategoryId){
			//var url = Globals.path + "/GroupedWork" + encodeURIComponent(id) + "/AJAX?method=getWorkInfo&id=" + id;
			var url = Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getWorkInfo";
			if (browseCategoryId != undefined){
				url += "&browseCategoryId=" + browseCategoryId;
			}
			VuFind.loadingMessage();
			$.getJSON(url, function(data){
				VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
			}).fail(VuFind.ajaxFail);
			return false;
		},

		showReviewForm: function(trigger, id){
			if (Globals.loggedIn){
				VuFind.loadingMessage();
				$.getJSON(Globals.path + "/GroupedWork/" + encodeURIComponent(id) + "/AJAX?method=getReviewForm", function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					return VuFind.GroupedWork.showReviewForm(trigger, id);
				}, false);
			}
			return false;
		},

		showSaveToListForm: function (trigger, id){
			if (Globals.loggedIn){
				VuFind.loadingMessage();
				var url = Globals.path + "/GroupedWork/" + id + "/AJAX?method=getSaveToListForm";
				$.getJSON(url, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					VuFind.GroupedWork.showSaveToListForm(trigger, id);
				});
			}
			return false;
		},

		showSmsForm: function(trigger, id){
			if (Globals.loggedIn){
				VuFind.loadingMessage();
				$.getJSON(Globals.path + "/GroupedWork/" + id + "/AJAX?method=getSMSForm", function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					return VuFind.GroupedWork.showSmsForm(trigger, id);
				}, false);
			}
			return false;
		},

		showTagForm: function(trigger, id, source){
			if (Globals.loggedIn){
				$.getJSON(Globals.path + "/GroupedWork/" + id + "/AJAX?method=getAddTagForm", function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				});
			}else{
				VuFind.Account.ajaxLogin($(trigger), function (){
					VuFind.GroupedWork.showTagForm(trigger, id, source);
				}, false);
			}
			return false;
		}
	};
}(VuFind.GroupedWork || {}));