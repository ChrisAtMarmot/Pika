/**
 * Created by mark on 1/14/14.
 */
VuFind.Account = (function(){

	return {
		ajaxCallback: null,
		closeModalOnAjaxSuccess: false,

		addAccountLink: function(){
			var url = Globals.path + "/MyAccount/AJAX?method=getAddAccountLinkForm";
			VuFind.Account.ajaxLightbox(url, true);
		},

		/**
		 * Creates a new list in the system for the active user.
		 *
		 * Called from list-form.tpl
		 * @returns {boolean}
		 */
		addList: function () {
			var form = $("#addListForm"),
					isPublic = form.find("#public").prop("checked"),
					recordId = form.find("input[name=recordId]").val(),
					source = form.find("input[name=source]").val(),
					title = form.find("input[name=title]").val(),
					desc = $("#listDesc").val(),
					url = Globals.path + "/MyAccount/AJAX",
					params = {
							'method':'AddList',
							title: title,
							public: isPublic,
							desc: desc,
							recordId: recordId
						};
			$.getJSON(url, params,function (data) {
					if (data.result) {
						VuFind.showMessage("Added Successfully", data.message, true);
					} else {
						VuFind.showMessage("Error", data.message);
					}
			}).fail(VuFind.ajaxFail);
			return false;
		},

		/**
		 * Do an ajax process, but only if the user is logged in.
		 * If the user is not logged in, force them to login and then do the process.
		 * Can also be called without the ajax callback to just login and not go anywhere
		 *
		 * @param trigger
		 * @param ajaxCallback
		 * @param closeModalOnAjaxSuccess
		 * @returns {boolean}
		 */
		ajaxLogin: function (trigger, ajaxCallback, closeModalOnAjaxSuccess) {
			if (Globals.loggedIn) {
				if (ajaxCallback != undefined && typeof(ajaxCallback) === "function") {
					ajaxCallback();
				} else if (VuFind.Account.ajaxCallback != null && typeof(VuFind.Account.ajaxCallback) === "function") {
					VuFind.Account.ajaxCallback();
					VuFind.Account.ajaxCallback = null;
				}
			} else {
				var multistep = false,
						loginLink = false;
				if (ajaxCallback != undefined && typeof(ajaxCallback) === "function") {
					multistep = true;
				}
				VuFind.Account.ajaxCallback = ajaxCallback;
				VuFind.Account.closeModalOnAjaxSuccess = closeModalOnAjaxSuccess;
				if (trigger != undefined && trigger != null) {
					var dialogTitle = trigger.attr("title") ? trigger.attr("title") : trigger.data("title");
					loginLink = trigger.data('login');
					/*
					  Set the trigger html element attribute data-login="true" to cause the pop-up login dialog
					  to act as if the only action is login, ie not a multi-step process.

					 */
				}
				var dialogDestination = Globals.path + '/MyAccount/AJAX?method=LoginForm';
				if (multistep && !loginLink){
					dialogDestination += "&multistep=true";
				}
				var modalDialog = $("#modalDialog");
				$('.modal-body').html("Loading...");
				//var modalBody = $(".modal-content");
				//modalBody.load(dialogDestination);
				$(".modal-content").load(dialogDestination);
				$(".modal-title").text(dialogTitle);
				modalDialog.modal("show");
			}
			return false;
		},

		followLinkIfLoggedIn: function (trigger, linkDestination) {
			if (trigger == undefined) {
				alert("You must provide the trigger to follow a link after logging in.");
			}
			var jqTrigger = $(trigger);
			if (linkDestination == undefined) {
				linkDestination = jqTrigger.attr("href");
			}
			this.ajaxLogin(jqTrigger, function () {
				document.location = linkDestination;
			}, true);
			return false;
		},

		preProcessLogin: function (){
			var username = $("#username").val(),
				password = $("#password").val(),
				loginErrorElem = $('#loginError');
			if (!username || !password) {
				loginErrorElem
						.text($("#missingLoginPrompt").text())
						.show();
				return false;
			}
			if (VuFind.hasLocalStorage()){
				//var rememberMeCtl = $("#rememberMe");
				var rememberMe = $("#rememberMe").prop('checked'),
						showPwd = $('#showPwd').prop('checked');
				if (rememberMe){
					window.localStorage.setItem('lastUserName', username);
					window.localStorage.setItem('lastPwd', password);
					window.localStorage.setItem('showPwd', showPwd);
					window.localStorage.setItem('rememberMe', rememberMe);
				}else{
					window.localStorage.removeItem('lastUserName');
					window.localStorage.removeItem('lastPwd');
					window.localStorage.removeItem('showPwd');
					window.localStorage.removeItem('rememberMe');
				}
			}
			return true;
		},

		processAddLinkedUser: function (){
			if(this.preProcessLogin()) {
				var username = $("#username").val(),
						password = $("#password").val(),
						loginErrorElem = $('#loginError'),
						url = Globals.path + "/MyAccount/AJAX?method=addAccountLink";
				loginErrorElem.hide();
				$.ajax({
					url: url,
					data: {username: username, password: password},
					success: function (response) {
						if (response.result == true) {
							VuFind.showMessage("Account to Manage", response.message ? response.message : "Successfully linked the account.", true, true);
						} else {
							loginErrorElem.text(response.message);
							loginErrorElem.show();
						}
					},
					error: function () {
						loginErrorElem.text("There was an error processing the account, please try again.")
								.show();
					},
					dataType: 'json',
					type: 'post'
				});
			}
			return false;
		},

		processAjaxLogin: function (ajaxCallback) {
			if(this.preProcessLogin()) {
				var username = $("#username").val(),
						password = $("#password").val(),
						rememberMe = $("#rememberMe").prop('checked'),
						loginErrorElem = $('#loginError'),
						url = Globals.path + "/AJAX/JSON?method=loginUser";
				loginErrorElem.hide();
				$.ajax({
					url: url,
					data: {username: username, password: password, rememberMe: rememberMe},
					success: function (response) {
						if (response.result.success == true) {
							// Hide "log in" options and show "log out" options:
							$('.loginOptions, #loginOptions').hide();
							$('.logoutOptions, #logoutOptions').show();

							// Show user name on page in case page doesn't reload
							var name = response.result.name.trim();
							$('#header-container #myAccountNameLink').html(name);
							name = 'Logged In As ' + name.slice(0, name.lastIndexOf(' ') + 2) + '.';
							$('#side-bar #myAccountNameLink').html(name);

							if (VuFind.Account.closeModalOnAjaxSuccess) {
								VuFind.closeLightbox();
							}

							Globals.loggedIn = true;
							if (ajaxCallback != undefined && typeof(ajaxCallback) === "function") {
								ajaxCallback();
							} else if (VuFind.Account.ajaxCallback != undefined && typeof(VuFind.Account.ajaxCallback) === "function") {
								VuFind.Account.ajaxCallback();
								VuFind.Account.ajaxCallback = null;
							}
						} else {
							loginErrorElem.text(response.result.message);
							loginErrorElem.show();
						}
					},
					error: function () {
						loginErrorElem.text("There was an error processing your login, please try again.")
								.show();
					},
					dataType: 'json',
					type: 'post'
				});
			}
			return false;
		},

		removeLinkedUser: function(idToRemove){
			if (confirm("Are you sure you want to stop managing this account?")){
				var url = Globals.path + "/MyAccount/AJAX?method=removeAccountLink&idToRemove=" + idToRemove;
				$.getJSON(url, function(data){
					if (data.result == true){
						VuFind.showMessage('Linked Account Removed', data.message, true, true);
						//setTimeout(function(){window.location.reload()}, 3000);
					}else{
						VuFind.showMessage('Unable to Remove Account Link', data.message);
					}
				});
			}
			return false;
		},

		removeTag: function(tag){
			if (confirm("Are you sure you want to remove the tag \"" + tag + "\" from all titles?")){
				var url = Globals.path + "/MyAccount/AJAX",
						params = {method:'removeTag', tag: tag};
				$.getJSON(url, params, function(data){
					if (data.result == true){
						VuFind.showMessage('Tag Deleted', data.message, true, true);
					}else{
						VuFind.showMessage('Tag Not Deleted', data.message);
					}
				});
			}
			return false;
		},

		renewTitle: function(patronId, recordId, renewIndicator) {
			if (Globals.loggedIn) {
				VuFind.loadingMessage();
				$.getJSON(Globals.path + "/MyAccount/AJAX?method=renewItem&patronId=" + patronId + "&recordId=" + recordId + "&renewIndicator="+renewIndicator, function(data){
					VuFind.showMessage(data.title, data.modalBody, data.success, data.success); // autoclose when successful
				}).fail(VuFind.ajaxFail)
			} else {
				this.ajaxLogin(null, function () {
					this.renewTitle(renewIndicator);
				}, false)
			}
			return false;
		},

		renewAll: function() {
			if (Globals.loggedIn) {
				if (confirm('Renew All Items?')) {
					VuFind.showMessage('Loading', 'Loading, please wait');
					$.getJSON(Globals.path + "/MyAccount/AJAX?method=renewAll", function (data) {
						VuFind.showMessage(data.title, data.modalBody, data.success);
						// autoclose when all successful
						if (data.success || data.renewed > 0) {
							// Refresh page on close when a item has been successfully renewed, otherwise stay
							$("#modalDialog").on('hidden.bs.modal', function (e) {
								location.reload(true);
							});
						}
					}).fail(VuFind.ajaxFail);
				}
			} else {
				this.ajaxLogin(null, this.renewAll, true);
				//auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			}
			return false;
		},

		renewSelectedTitles: function () {
			if (Globals.loggedIn) {
				var selectedTitles = VuFind.getSelectedTitles();
				if (selectedTitles) {
					if (confirm('Renew selected Items?')) {
						VuFind.loadingMessage();
						$.getJSON(Globals.path + "/MyAccount/AJAX?method=renewSelectedItems&" + selectedTitles, function (data) {
							var reload = data.success || data.renewed > 0;
							VuFind.showMessage(data.title, data.modalBody, data.success, reload);
						}).fail(VuFind.ajaxFail);
					}
				}
			} else {
				this.ajaxLogin(null, this.renewSelectedTitles, true);
				 //auto close so that if user opts out of renew, the login window closes; if the users continues, follow-up operations will reopen modal
			}
			return false
		},

		resetPin: function(){
			var barcode = $('#card_number').val();
			if (barcode.length == 0){
				alert("Please enter your library card number");
			}else{
				var url = path + '/MyAccount/AJAX?method=requestPinReset&barcode=' + barcode;
				$.getJSON(url, function(data){
					if (data.error == false){
						alert(data.message);
						if (data.result == true){
							hideLightbox();
						}
					}else{
						alert("There was an error requesting your pin reset information.  Please contact the library for additional information.");
					}
				});
			}
			return false;
		},

		ajaxLightbox: function (urlToDisplay, requireLogin) {
			if (requireLogin == undefined) {
				requireLogin = false;
			}
			if (requireLogin && !Globals.loggedIn) {
				VuFind.Account.ajaxLogin(null, function () {
					VuFind.Account.ajaxLightbox(urlToDisplay, requireLogin);
				}, false);
			} else {
				var modalDialog = $("#modalDialog");
				$('#myModalLabel').html("Loading, please wait");
				$('.modal-body').html("...");
				$.getJSON(urlToDisplay, function(data){
					if (data.success){
						data = data.result;
					}
					$('#myModalLabel').html(data.title);
					$('.modal-body').html(data.modalBody);
					$('.modal-buttons').html(data.modalButtons);
				});
				//modalDialog.load( );
				modalDialog.modal('show');
			}
			return false;
		},

		cancelHold: function(patronId, recordId, holdIdToCancel){
			if (confirm("Are you sure you want to cancel this hold?")){
				if (Globals.loggedIn) {
					VuFind.loadingMessage();
					$.getJSON(Globals.path + "/MyAccount/AJAX?method=cancelHold&patronId=" + patronId + "&recordId=" + recordId + "&cancelId="+holdIdToCancel, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success, data.success); // autoclose when successful
					}).fail(VuFind.ajaxFail)
				} else {
					this.ajaxLogin(null, function () {
						VuFind.Account.cancelHold(userId, holdIdToCancel)
					}, false);
				}
			}

			return false
		},

		cancelSelectedHolds: function() {
			if (Globals.loggedIn) {
				var selectedTitles = this.getSelectedTitles()
								.replace(/waiting|available/g, ''),// strip out of name for now.
						numHolds = $("input.titleSelect:checked").length;
				// if numHolds equals 0, quit because user has canceled in getSelectedTitles()
				if (numHolds > 0 && confirm('Cancel ' + numHolds + ' selected hold' + (numHolds > 1 ? 's' : '') + '?')) {
					VuFind.loadingMessage();
					$.getJSON(Globals.path + "/MyAccount/AJAX?method=cancelHolds&"+selectedTitles, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect:checked").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect:checked").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(function(){
						VuFind.ajaxFail();
					});
				}
			} else {
				this.ajaxLogin(null, function () {
					VuFind.Account.cancelSelectedHolds();
				}, false);
		}
		return false;
	},

		cancelBooking: function(patronId, cancelId){
			if (confirm("Are you sure you want to cancel this scheduled item?")){
				if (Globals.loggedIn) {
					VuFind.loadingMessage();
					var c = {};
					c[patronId] = cancelId;
					console.log(c);
					//$.getJSON(Globals.path + "/MyAccount/AJAX", {method:"cancelBooking", patronId:patronId, cancelId:cancelId}, function(data){
					$.getJSON(Globals.path + "/MyAccount/AJAX", {method:"cancelBooking", cancelId:c}, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled item from page
							var escapedId = cancelId.replace(/:/g, "\\:"); // needed for jquery selector to work correctly
							// first backslash for javascript escaping, second for css escaping (within jquery)
							$('div.result').has('#selected'+escapedId).remove();
						}
					}).fail(VuFind.ajaxFail)
				} else {
					this.ajaxLogin(null, function () {
						VuFind.Account.cancelBooking(cancelId)
					}, false);
				}
			}

			return false
		},

		cancelSelectedBookings: function(){
			if (Globals.loggedIn) {
				var selectedTitles = this.getSelectedTitles(),
						numBookings = $("input.titleSelect:checked").length;
				// if numBookings equals 0, quit because user has canceled in getSelectedTitles()
				if (numBookings > 0 && confirm('Cancel ' + numBookings + ' selected scheduled item' + (numBookings > 1 ? 's' : '') + '?')) {
					VuFind.loadingMessage();
					$.getJSON(Globals.path + "/MyAccount/AJAX?method=cancelBooking&"+selectedTitles, function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect:checked").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect:checked").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(VuFind.ajaxFail);
				}
			} else {
				this.ajaxLogin(null, VuFind.Account.cancelSelectedBookings, false);
			}
			return false;

		},

		cancelAllBookings: function(){
			if (Globals.loggedIn) {
				if (confirm('Cancel all of your scheduled items?')) {
					VuFind.loadingMessage();
					$.getJSON(Globals.path + "/MyAccount/AJAX?method=cancelBooking&cancelAll=1", function(data){
						VuFind.showMessage(data.title, data.modalBody, data.success); // autoclose when successful
						if (data.success) {
							// remove canceled items from page
							$("input.titleSelect").closest('div.result').remove();
						} else if (data.failed) { // remove items that didn't fail
							var searchArray = data.failed.map(function(ele){return ele.toString()});
							// convert any number values to string, this is needed bcs inArray() below does strict comparisons
							// & id will be a string. (sometimes the id values are of type number )
							$("input.titleSelect").each(function(){
								var id = $(this).attr('id').replace(/selected/g, ''); //strip down to just the id part
								if ($.inArray(id, searchArray) == -1) // if the item isn't one of the failed cancels, get rid of its containing div.
									$(this).closest('div.result').remove();
							});
						}
					}).fail(VuFind.ajaxFail);
				}
			} else {
				this.ajaxLogin(null, VuFind.Account.cancelAllBookings, false);
			}
			return false;
		},

		/* update the sort parameter and redirect the user back to the same page */
		changeAccountSort: function (newSort){
			// Get the current url
			var currentLocation = window.location.href;
			// Check to see if we already have a sort parameter. .
			if (currentLocation.match(/(accountSort=[^&]*)/)) {
				// Replace the existing sort with the new sort parameter
				currentLocation = currentLocation.replace(/accountSort=[^&]*/, 'accountSort=' + newSort);
			} else {
				// Add the new sort parameter
				if (currentLocation.match(/\?/)) {
					currentLocation += "&accountSort=" + newSort;
				}else{
					currentLocation += "?accountSort=" + newSort;
				}
			}
			// Redirect back to this page.
			window.location.href = currentLocation;
		},

		changeHoldPickupLocation: function (patronId, recordId, holdId){
			if (Globals.loggedIn){
				var modalDialog = $("#modalDialog");
				$('#myModalLabel').html('Loading');
				$('.modal-body').html('');
				$.getJSON(Globals.path + "/MyAccount/AJAX?method=getChangeHoldLocationForm&patronId=" + patronId + "&recordId=" + recordId + "&holdId=" + holdId, function(data){
					$('#myModalLabel').html(data.title);
					$('.modal-body').html(data.modalBody);
					$('.modal-buttons').html(data.modalButtons);
				});
				//modalDialog.load( );
				modalDialog.modal('show');
			}else{
				VuFind.Account.ajaxLogin(null, function (){
					return VuFind.Account.changeHoldPickupLocation(patronId, recordId, holdId);
				}, false);
			}
			return false;
		},

		deleteSearch: function(searchId){
			if (!Globals.loggedIn){
				VuFind.Account.ajaxLogin(null, function () {
					VuFind.Searches.saveSearch(searchId);
				}, false);
			}else{
				var url = Globals.path + "/MyAccount/AJAX";
				var params = "method=deleteSearch&searchId=" + encodeURIComponent(searchId);
				$.getJSON(url + '?' + params,
						function(data) {
							if (data.result) {
								VuFind.showMessage("Success", data.message);
							} else {
								VuFind.showMessage("Error", data.message);
							}
						}
				);
			}
			return false;
		},

		doChangeHoldLocation: function(){
			var //patronId = $('#patronId').val()
					//,recordId = $('#recordId').val()
					//,holdId = $('#holdId').val()
					//,newLocation = $('#newPickupLocation').val()
					url = Globals.path + "/MyAccount/AJAX"
					,params = {
						'method': 'changeHoldLocation'
						,patronId : $('#patronId').val()
						,recordId : $('#recordId').val()
						,holdId : $('#holdId').val()
						,newLocation : $('#newPickupLocation').val()
					};

			$.getJSON(url, params,
					function(data) {
						if (data.success) {
							VuFind.showMessage("Success", data.message, true, true);
						} else {
							VuFind.showMessage("Error", data.message);
						}
					}
			).fail(VuFind.ajaxFail);
		},

		freezeHold: function(patronId, recordId, holdId, promptForReactivationDate, caller){
			VuFind.loadingMessage();
			var url = Globals.path + '/MyAccount/AJAX',
					params = {
						patronId : patronId
						,recordId : recordId
						,holdId : holdId
					};
			if (promptForReactivationDate){
				//Prompt the user for the date they want to reactivate the hold
				params['method'] = 'getReactivationDateForm'; // set method for this form
				$.getJSON(url, params, function (data) {
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons)
				}).fail(VuFind.ajaxFail);

			}else{
				var popUpBoxTitle = $(caller).text() || "Freezing Hold"; // freezing terminology can be customized, so grab text from click button: caller
				VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
				params['method'] = 'freezeHold'; //set method for this ajax call
				$.getJSON(url, params, function(data){
					if (data.success) {
						VuFind.showMessage("Success", data.message, true, true);
					} else {
						VuFind.showMessage("Error", data.message);
					}
				}).fail(VuFind.ajaxFail);
			}
		},

// called by ReactivationDateForm when fn freezeHold above has promptForReactivationDate is set
		doFreezeHoldWithReactivationDate: function(caller){
			var popUpBoxTitle = $(caller).text() || "Freezing Hold" // freezing terminology can be customized, so grab text from click button: caller
					,patronId = $('#patronId').val()
					,recordId = $('#recordId').val()
					,holdId = $("#holdId").val()
					,reactivationDate = $("#reactivationDate").val()
					,url = Globals.path + '/MyAccount/AJAX?method=freezeHold&patronId=' + patronId + "&recordId=" + recordId + '&holdId=' + holdId + '&reactivationDate=' + reactivationDate;
			VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			$.getJSON(url, function(data){
				if (data.success) {
					VuFind.showMessage("Success", data.message, true, true);
				} else {
					VuFind.showMessage("Error", data.message);
				}
			}).fail(VuFind.ajaxFail);
		},

		/* Hide this code for now. I should be to re-enable when re-enable selections for Holds
		plb 9-14-2015

		freezeSelectedHolds: function (){
			var selectedTitles = this.getSelectedTitles();
			if (selectedTitles.length == 0){
				return false;
			}
			var suspendDate = '',
					suspendDateTop = $('#suspendDateTop'),
					url = '',
					queryParams = '';
			if (suspendDateTop.length) { //Check to see whether or not we are using a suspend date.
				if (suspendDateTop.val().length > 0) {
					suspendDate = suspendDateTop.val();
				} else {
					suspendDate = $('#suspendDateBottom').val();
				}
				if (suspendDate.length == 0) {
					alert("Please select the date when the hold should be reactivated.");
					return false;
				}
			}
			url = Globals.path + '/MyAccount/Holds?multiAction=freezeSelected&patronId=' + patronId + '&recordId=' + recordId + '&' + selectedTitles + '&suspendDate=' + suspendDate;
			queryParams = VuFind.getQuerystringParameters();
			if ($.inArray('section', queryParams)){
				url += '&section=' + queryParams['section'];
			}
			window.location = url;
			return false;
		},
		*/


		getSelectedTitles: function(promptForSelectAll){
			if (promptForSelectAll == undefined){
				promptForSelectAll = true;
			}
			var selectedTitles = $("input.titleSelect:checked ");
			if (selectedTitles.length == 0 && promptForSelectAll && confirm('You have not selected any items, process all items?')) {
				selectedTitles = $("input.titleSelect")
					.attr('checked', 'checked');
			}
			var queryString = selectedTitles.map(function() {
				return $(this).attr('name') + "=" + $(this).val();
			}).get().join("&");

			return queryString;
		},

		saveSearch: function(searchId){
			if (!Globals.loggedIn){
				VuFind.Account.ajaxLogin(null, function () {
					VuFind.Account.saveSearch(searchId);
				}, false);
			}else{
				var url = Globals.path + "/MyAccount/AJAX";
				var params = {method :'saveSearch', searchId :searchId};
				//$.getJSON(url + '?' + params,
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

		showCreateListForm: function(id){
			if (Globals.loggedIn){
				var url = Globals.path + "/MyAccount/AJAX",
						params = {method:"getCreateListForm"};
				if (id != undefined){
					params.recordId= id;
				}
				$.getJSON(url, params, function(data){
					VuFind.showMessageWithButtons(data.title, data.modalBody, data.modalButtons);
				}).fail(VuFind.ajaxFail);
			}else{
				VuFind.Account.ajaxLogin($trigger, function (){
					return VuFind.GroupedWork.showEmailForm(trigger, id);
				}, false);
			}
			return false;
		},

		thawHold: function(patronId, recordId, holdId, caller){
			var popUpBoxTitle = $(caller).text() || "Thawing Hold";  // freezing terminology can be customized, so grab text from click button: caller
			VuFind.showMessage(popUpBoxTitle, "Updating your hold.  This may take a minute.");
			var url = Globals.path + '/MyAccount/AJAX',
					params = {
						'method' : 'thawHold'
						,patronId : patronId
						,recordId : recordId
						,holdId : holdId
					};
			$.getJSON(url, params, function(data){
				if (data.success) {
					VuFind.showMessage("Success", data.message, true, true);
				} else {
					VuFind.showMessage("Error", data.message);
				}
			}).fail(VuFind.ajaxFail);
		}

	};
}(VuFind.Account || {}));