VuFind.Account.ReadingHistory = (function(){
	return {
		deletedMarkedAction: function (){
			if (confirm('The marked items will be irreversibly deleted from your reading history.  Proceed?')){
				$('#readingHistoryAction').val('deleteMarked');
				$('#readingListForm').submit();
			}
			return false;
		},

		deleteAllAction: function (){
			if (confirm('Your entire reading history will be irreversibly deleted.  Proceed?')){
				$('#readingHistoryAction').val('deleteAll');
				$('#readingListForm').submit();
			}
			return false;
		},

		optOutAction: function (){
			if (confirm('Opting out of Reading History will also delete your entire reading history irreversibly.  Proceed?')){
				$('#readingHistoryAction').val('optOut');
				$('#readingListForm').submit();
			}
			return false;
		},

		optInAction: function (){
			$('#readingHistoryAction').val('optIn');
			$('#readingListForm').submit();
			return false;
		},

		exportListAction: function (){
			$('#readingHistoryAction').val('exportToExcel');
			$('#readingListForm').submit();
			return false;
		}
	};
}(VuFind.Account.ReadingHistory || {}));
