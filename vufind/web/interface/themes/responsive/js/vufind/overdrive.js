VuFind.OverDrive = (function(){
	return {
		cancelOverDriveHold: function(overdriveId){
			var ajaxUrl = Globals.path + "/EcontentRecord/AJAX?method=CancelOverDriveHold&overDriveId=" + overdriveId;
			$.ajax({
				url: ajaxUrl,
				cache: false,
				success: function(data){
					if (data.result){
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
			return false;
		},

		checkoutOverDriveItemOneClick: function(overdriveId){
			if (Globals.loggedIn){
				var ajaxUrl = Globals.path + "/EcontentRecord/AJAX?method=CheckoutOverDriveItem&overDriveId=" + overdriveId;
				$.ajax({
					url: ajaxUrl,
					cache: false,
					success: function(data){
						if (data.result == true){
							VuFind.showMessage("Title Checked Out Successfully", data.message, true);
							window.location.href = Globals.path + "/MyAccount/CheckedOut";
						}else{
							if (data.noCopies == true){
								VuFind.closeLightbox();
								ret = confirm(data.message);
								if (ret == true){
									VuFind.OverDrive.placeOverDriveHold(overdriveId, formatId);
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
					VuFind.OverDrive.checkoutOverDriveItemOneClick(overdriveId);
				}, false);
			}
			return false;
		},

		doOverDriveHold: function(overDriveId, formatId, overdriveEmail, promptForOverdriveEmail){
			var url = Globals.path + "/EcontentRecord/AJAX?method=PlaceOverDriveHold&overDriveId=" + overDriveId + "&formatId=" + formatId + "&overdriveEmail=" + overdriveEmail + "&promptForOverdriveEmail=" + promptForOverdriveEmail;
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

		followOverDriveDownloadLink: function(overDriveId, formatId){
			var ajaxUrl = Globals.path + "/EcontentRecord/AJAX?method=GetDownloadLink&overDriveId=" + overDriveId + "&formatId=" + formatId;
			$.ajax({
				url: ajaxUrl,
				cache: false,
				success: function(data){
					if (data.result){
						//Reload the page
						window.location.href = data.downloadUrl ;
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

		getOverDriveHoldPrompts: function(overDriveId, formatId){
			var url = Globals.path + "/OverDrive/" + overDriveId + "/AJAX?method=GetOverDriveHoldPrompts";
			if (formatId != undefined){
				url += "&formatId=" + formatId;
			}
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

		placeOverDriveHold: function(overDriveId, formatId){
			if (Globals.loggedIn){
				//Get any prompts needed for placing holds (e-mail and format depending on the interface.
				var promptInfo = VuFind.OverDrive.getOverDriveHoldPrompts(overDriveId, formatId, 'hold');
				if (!promptInfo.promptNeeded){
					VuFind.OverDrive.doOverDriveHold(overDriveId, formatId, promptInfo.overdriveEmail, promptInfo.promptForOverdriveEmail);
				}
			}else{
				VuFind.Account.ajaxLogin(null, function(){
					VuFind.OverDrive.placeOverDriveHold(overDriveId, formatId);
				});
			}
			return false;
		},

		processOverDriveHoldPrompts: function(){
			var overdriveHoldPromptsForm = $("#overdriveHoldPromptsForm");
			var overdriveId = overdriveHoldPromptsForm.find("input[name=overdriveId]").val();
			var formatId = -1;
			if (overdriveHoldPromptsForm.find("input[name=formatId]") && overdriveHoldPromptsForm.find("input[name=formatId]").val() != undefined){
				formatId = overdriveHoldPromptsForm.find("input[name=formatId]").val();
				if (formatId == undefined){
					formatId = "";
				}
			}else if($('#formatId').find(':selected') && $('#formatId').find(':selected').val() != undefined){
				formatId = $('#formatId').find(':selected').val();
			}
			var promptForOverdriveEmail;
			if (overdriveHoldPromptsForm.find("input[name=promptForOverdriveEmail]").is(":checked")){
				promptForOverdriveEmail = 0;
			}else{
				promptForOverdriveEmail = 1;
			}
			var overdriveEmail = overdriveHoldPromptsForm.find("input[name=overdriveEmail]").val();
			VuFind.OverDrive.doOverDriveHold(overdriveId, formatId, overdriveEmail, promptForOverdriveEmail);
		},

		returnOverDriveTitle: function (overDriveId, transactionId){
			if (confirm('Are you sure you want to return this title?')){
				VuFind.showMessage("Returning Title", "Returning your title in OverDrive.  This may take a minute.");
				var ajaxUrl = Globals.path + "/EcontentRecord/AJAX?method=ReturnOverDriveItem&overDriveId=" + overDriveId + "&transactionId=" + transactionId;
				$.ajax({
					url: ajaxUrl,
					cache: false,
					success: function(data){
						VuFind.showMessage("Title Returned", data.message);
						if (data.result){
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

		selectOverDriveDownloadFormat: function(overDriveId){
			var selectedOption = $("#downloadFormat_" + overDriveId + " option:selected");
			var selectedFormatId = selectedOption.val();
			var selectedFormatText = selectedOption.text();
			if (selectedFormatId == -1){
				alert("Please select a format to download.");
			}else{
				if (confirm("Are you sure you want to download the " + selectedFormatText + " format? You cannot change format after downloading.")){
					var ajaxUrl = Globals.path + "/EcontentRecord/AJAX?method=SelectOverDriveDownloadFormat&overDriveId=" + overDriveId + "&formatId=" + selectedFormatId;
					$.ajax({
						url: ajaxUrl,
						cache: false,
						success: function(data){
							if (data.result){
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