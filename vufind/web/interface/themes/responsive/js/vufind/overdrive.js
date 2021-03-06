VuFind.OverDrive = (function(){
	return {
		cancelOverDriveHold: function(patronId, overdriveId){
			if (confirm("Are you sure you want to cancel this hold?")){
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=CancelOverDriveHold&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.ajax({
					url: ajaxUrl,
					cache: false,
					success: function(data){
						if (data.success){
							VuFind.showMessage("Hold Cancelled", data.message, true);
							//remove the row from the holds list
							$("#overDriveHold_" + overdriveId).hide();
						}else{
							VuFind.showMessage("Error Cancelling Hold", data.message, false);
						}
					},
					dataType: 'json',
					async: false,
					error: function(){
						VuFind.showMessage("Error Cancelling Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
					}
				});
			}
			return false;
		},

		getOverDriveCheckoutPrompts: function(overDriveId){
			var url = Globals.path + "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveCheckoutPrompts";
			var result = true;
			$.ajax({
				url: url,
				cache: false,
				success: function(data){
					result = data;
					if (data.promptNeeded){
						VuFind.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					}
				},
				dataType: 'json',
				async: false,
				error: function(){
					alert("An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
					VuFind.closeLightbox();
				}
			});
			return result;
		},

		checkOutOverDriveTitle: function(overDriveId){
			if (Globals.loggedIn){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var promptInfo = VuFind.OverDrive.getOverDriveCheckoutPrompts(overDriveId, 'hold');
				if (!promptInfo.promptNeeded){
					VuFind.OverDrive.doOverDriveCheckout(promptInfo.patronId, overDriveId);
				}
			}else{
				VuFind.Account.ajaxLogin(null, function(){
					VuFind.OverDrive.checkOutOverDriveTitle(overDriveId);
				});
			}
			return false;
		},

		processOverDriveCheckoutPrompts: function(){
			var overdriveCheckoutPromptsForm = $("#overdriveCheckoutPromptsForm");
			var patronId = $("#patronId").val();
			var overdriveId = overdriveCheckoutPromptsForm.find("input[name=overdriveId]").val();
			VuFind.OverDrive.doOverDriveCheckout(patronId, overdriveId);
		},

		doOverDriveCheckout: function(patronId, overdriveId){
			if (Globals.loggedIn){
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=CheckoutOverDriveItem&patronId=" + patronId + "&overDriveId=" + overdriveId;
				$.ajax({
					url: ajaxUrl,
					cache: false,
					success: function(data){
						if (data.success == true){
							VuFind.showMessageWithButtons("Title Checked Out Successfully", data.message, data.buttons);
						}else{
							if (data.noCopies == true){
								VuFind.closeLightbox();
								ret = confirm(data.message);
								if (ret == true){
									VuFind.OverDrive.placeOverDriveHold(overdriveId, null);
								}
							}else{
								VuFind.showMessage("Error Checking Out Title", data.message, false);
							}
						}
					},
					dataType: 'json',
					async: false,
					error: function(){
						alert("An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
						//alert("ajaxUrl = " + ajaxUrl);
						hideLightbox();
					}
				});
			}else{
				VuFind.Account.ajaxLogin(null, function(){
					// VuFind.OverDrive.checkoutOverDriveItemOneClick(overdriveId);
					//TODO: method above hasn't been defined
					VuFind.OverDrive.checkoutOverDriveItem(overdriveId);
				}, false);
			}
			return false;
		},

		doOverDriveHold: function(patronId, overDriveId, overdriveEmail, promptForOverdriveEmail){
			var url = Globals.path + "/OverDrive/AJAX?method=PlaceOverDriveHold&patronId=" + patronId + "&overDriveId=" + overDriveId + "&overdriveEmail=" + overdriveEmail + "&promptForOverdriveEmail=" + promptForOverdriveEmail;
			$.ajax({
				url: url,
				cache: false,
				success: function(data){
					if (data.availableForCheckout){
						VuFind.OverDrive.checkoutOverDriveItemOneClick(overdriveId);
					}else{
						VuFind.showMessage("Placed Hold", data.message, true);
					}
				},
				dataType: 'json',
				async: false,
				error: function(){
					VuFind.showMessage("Error Placing Hold", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.", false);
				}
			});
		},

		followOverDriveDownloadLink: function(patronId, overDriveId, formatId){
			var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=GetDownloadLink&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + formatId;
			$.ajax({
				url: ajaxUrl,
				cache: false,
				success: function(data){
					if (data.success){
						//Reload the page
						var win = window.open(data.downloadUrl, '_blank');
						win.focus();
						//window.location.href = data.downloadUrl ;
					}else{
						alert(data.message);
					}
				},
				dataType: 'json',
				async: false,
				error: function(){
					alert("An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
					hideLightbox();
				}
			});
		},

		forceUpdateFromAPI:function(overDriveId){
			var url = Globals.path + '/OverDrive/' + overDriveId + '/AJAX?method=forceUpdateFromAPI';
			$.getJSON(url, function (data){
					VuFind.showMessage("Success", data.message, true, true);
					setTimeout("VuFind.closeLightbox();", 3000);
				}
			);
			return false;
		},

		getOverDriveHoldPrompts: function(overDriveId){
			var url = Globals.path + "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveHoldPrompts";
			var result = true;
			$.ajax({
				url: url,
				cache: false,
				success: function(data){
					result = data;
					if (data.promptNeeded){
						VuFind.showMessageWithButtons(data.promptTitle, data.prompts, data.buttons);
					}
				},
				dataType: 'json',
				async: false,
				error: function(){
					alert("An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
					VuFind.closeLightbox();
				}
			});
			return result;
		},

		placeOverDriveHold: function(overDriveId){
			if (Globals.loggedIn){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var promptInfo = VuFind.OverDrive.getOverDriveHoldPrompts(overDriveId, 'hold');
				if (!promptInfo.promptNeeded){
					VuFind.OverDrive.doOverDriveHold(promptInfo.patronId, overDriveId, promptInfo.overdriveEmail, promptInfo.promptForOverdriveEmail);
				}
			}else{
				VuFind.Account.ajaxLogin(null, function(){
					VuFind.OverDrive.placeOverDriveHold(overDriveId);
				});
			}
			return false;
		},

		processOverDriveHoldPrompts: function(){
			var overdriveHoldPromptsForm = $("#overdriveHoldPromptsForm");
			var patronId = $("#patronId").val();
			var overdriveId = overdriveHoldPromptsForm.find("input[name=overdriveId]").val();
			var promptForOverdriveEmail;
			if (overdriveHoldPromptsForm.find("input[name=promptForOverdriveEmail]").is(":checked")){
				promptForOverdriveEmail = 0;
			}else{
				promptForOverdriveEmail = 1;
			}
			var overdriveEmail = overdriveHoldPromptsForm.find("input[name=overdriveEmail]").val();
			VuFind.OverDrive.doOverDriveHold(patronId, overdriveId, overdriveEmail, promptForOverdriveEmail);
		},

		returnOverDriveTitle: function (patronId, overDriveId, transactionId){
			if (confirm('Are you sure you want to return this title?')){
				VuFind.showMessage("Returning Title", "Returning your title in OverDrive.  This may take a minute.");
				var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=ReturnOverDriveItem&patronId=" + patronId + "&overDriveId=" + overDriveId + "&transactionId=" + transactionId;
				$.ajax({
					url: ajaxUrl,
					cache: false,
					success: function(data){
						VuFind.showMessage("Title Returned", data.message);
						if (data.success){
							//Reload the page
							setTimeout(function(){
								VuFind.closeLightbox();
								window.location.href = window.location.href ;
							}, 3000);
						}
					},
					dataType: 'json',
					async: false,
					error: function(){
						VuFind.showMessage("Error Returning Title", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
					}
				});
			}
			return false;
		},

		selectOverDriveDownloadFormat: function(patronId, overDriveId){
			var selectedOption = $("#downloadFormat_" + overDriveId + " option:selected");
			var selectedFormatId = selectedOption.val();
			var selectedFormatText = selectedOption.text();
			if (selectedFormatId == -1){
				alert("Please select a format to download.");
			}else{
				if (confirm("Are you sure you want to download the " + selectedFormatText + " format? You cannot change format after downloading.")){
					var ajaxUrl = Globals.path + "/OverDrive/AJAX?method=SelectOverDriveDownloadFormat&patronId=" + patronId + "&overDriveId=" + overDriveId + "&formatId=" + selectedFormatId;
					$.ajax({
						url: ajaxUrl,
						cache: false,
						success: function(data){
							if (data.success){
								//Reload the page
								window.location.href = data.downloadUrl;
							}else{
								VuFind.showMessage("Error Selecting Format", data.message);
							}
						},
						dataType: 'json',
						async: false,
						error: function(){
							VuFind.showMessage("Error Selecting Format", "An error occurred processing your request in OverDrive.  Please try again in a few minutes.");
						}
					});
				}
			}
			return false;
		}
	}
}(VuFind.OverDrive || {}));