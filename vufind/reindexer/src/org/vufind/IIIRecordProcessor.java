package org.vufind;

import au.com.bytecode.opencsv.CSVReader;
import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;

import java.io.File;
import java.io.FileReader;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * Record Processor to handle processing records from Millennium and Sierra
 * Pika
 * User: Mark Noble
 * Date: 7/9/2015
 * Time: 11:39 PM
 */
public abstract class IIIRecordProcessor extends IlsRecordProcessor{
	protected HashMap<String, ArrayList<OrderInfo>> orderInfoFromExport = new HashMap();
	private boolean loanRuleDataLoaded = false;
	protected ArrayList<Long> pTypes = new ArrayList<>();
	protected HashMap<String, HashSet<String>> pTypesByLibrary = new HashMap<>();
	protected HashMap<String, HashSet<String>> pTypesForSpecialLocationCodes = new HashMap<>();
	protected HashSet<String> allPTypes = new HashSet<>();
	private HashMap<Long, LoanRule> loanRules = new HashMap<>();
	private ArrayList<LoanRuleDeterminer> loanRuleDeterminers = new ArrayList<>();
	protected String exportPath;

	public IIIRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, configIni, indexingProfileRS, logger, fullReindex);
		try {
			exportPath = indexingProfileRS.getString("marcPath");
		}catch (Exception e){
			logger.error("Unable to load marc path from indexing profile");
		}
		loadLoanRuleInformation(vufindConn, logger);
	}

	private void loadLoanRuleInformation(Connection vufindConn, Logger logger) {
		if (!loanRuleDataLoaded){
			//Load loan rules
			try {
				PreparedStatement pTypesStmt = vufindConn.prepareStatement("SELECT pType from ptype", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet pTypesRS = pTypesStmt.executeQuery();
				while (pTypesRS.next()) {
					pTypes.add(pTypesRS.getLong("pType"));
					allPTypes.add(pTypesRS.getString("pType"));
				}

				PreparedStatement pTypesByLibraryStmt = vufindConn.prepareStatement("SELECT pTypes, ilsCode, econtentLocationsToInclude from library", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet pTypesByLibraryRS = pTypesByLibraryStmt.executeQuery();
				while (pTypesByLibraryRS.next()) {
					String ilsCode = pTypesByLibraryRS.getString("ilsCode");
					String pTypes = pTypesByLibraryRS.getString("pTypes");
					String econtentLocationsToIncludeStr = pTypesByLibraryRS.getString("econtentLocationsToInclude");
					if (pTypes != null && pTypes.length() > 0){
						String[] pTypeElements = pTypes.split(",");
						HashSet<String> pTypesForLibrary = new HashSet<>();
						Collections.addAll(pTypesForLibrary, pTypeElements);
						pTypesByLibrary.put(ilsCode, pTypesForLibrary);
						if (econtentLocationsToIncludeStr.length() > 0) {
							String[] econtentLocationsToInclude = econtentLocationsToIncludeStr.split(",");
							for (String econtentLocationToInclude : econtentLocationsToInclude) {
								econtentLocationToInclude = econtentLocationToInclude.trim();
								if (econtentLocationToInclude.length() > 0) {
									if (!pTypesForSpecialLocationCodes.containsKey(econtentLocationToInclude)) {
										pTypesForSpecialLocationCodes.put(econtentLocationToInclude, new HashSet<String>());
									}
									pTypesForSpecialLocationCodes.get(econtentLocationToInclude).addAll(pTypesForLibrary);
								}
							}
						}
					}
				}

				PreparedStatement loanRuleStmt = vufindConn.prepareStatement("SELECT * from loan_rules", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet loanRulesRS = loanRuleStmt.executeQuery();
				while (loanRulesRS.next()) {
					LoanRule loanRule = new LoanRule();
					loanRule.setLoanRuleId(loanRulesRS.getLong("loanRuleId"));
					loanRule.setName(loanRulesRS.getString("name"));
					loanRule.setHoldable(loanRulesRS.getBoolean("holdable"));
					loanRule.setBookable(loanRulesRS.getBoolean("bookable"));

					loanRules.put(loanRule.getLoanRuleId(), loanRule);
				}
				logger.debug("Loaded " + loanRules.size() + " loan rules");

				PreparedStatement loanRuleDeterminersStmt = vufindConn.prepareStatement("SELECT * from loan_rule_determiners where active = 1 order by rowNumber DESC", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet loanRuleDeterminersRS = loanRuleDeterminersStmt.executeQuery();
				while (loanRuleDeterminersRS.next()) {
					LoanRuleDeterminer loanRuleDeterminer = new LoanRuleDeterminer();
					loanRuleDeterminer.setRowNumber(loanRuleDeterminersRS.getLong("rowNumber"));
					loanRuleDeterminer.setLocation(loanRuleDeterminersRS.getString("location"));
					loanRuleDeterminer.setPatronType(loanRuleDeterminersRS.getString("patronType"));
					loanRuleDeterminer.setItemType(loanRuleDeterminersRS.getString("itemType"));
					loanRuleDeterminer.setLoanRuleId(loanRuleDeterminersRS.getLong("loanRuleId"));
					loanRuleDeterminer.setActive(loanRuleDeterminersRS.getBoolean("active"));

					loanRuleDeterminers.add(loanRuleDeterminer);
				}

				logger.debug("Loaded " + loanRuleDeterminers.size() + " loan rule determiner");
			} catch (SQLException e) {
				logger.error("Unable to load loan rules", e);
			}
			loanRuleDataLoaded = true;
		}
	}

	private HashMap<String, HashSet<RelevantLoanRule>> cachedRelevantLoanRules = new HashMap<>();
	private HashSet<RelevantLoanRule> getRelevantLoanRules(String iType, String locationCode, HashSet<Long> pTypesToCheck){
		//Look for ac cached value
		String key = iType + locationCode + pTypesToCheck.toString();
		HashSet<RelevantLoanRule> relevantLoanRules = cachedRelevantLoanRules.get(key);
		if (relevantLoanRules == null){
			relevantLoanRules = new HashSet<>();
		}else{
			return relevantLoanRules;
		}

		HashSet<Long> pTypesNotAccountedFor = new HashSet<>();
		pTypesNotAccountedFor.addAll(pTypesToCheck);
		Long iTypeLong = Long.parseLong(iType);
		boolean hasDefaultPType = pTypesToCheck.contains(-1L);
		for (LoanRuleDeterminer curDeterminer : loanRuleDeterminers) {
			if (curDeterminer.isActive()) {
				//Make sure the location matches
				if (curDeterminer.matchesLocation(locationCode)) {
					//logger.debug("    " + curDeterminer.getRowNumber() + " matches location");
					if (curDeterminer.getItemType().equals("999") || curDeterminer.getItemTypes().contains(iTypeLong)) {
						//logger.debug("    " + curDeterminer.getRowNumber() + " matches iType");
						if (hasDefaultPType || curDeterminer.getPatronType().equals("999") || isPTypeValid(curDeterminer.getPatronTypes(), pTypesNotAccountedFor)) {
							//logger.debug("    " + curDeterminer.getRowNumber() + " matches pType");
							LoanRule loanRule = loanRules.get(curDeterminer.getLoanRuleId());
							relevantLoanRules.add(new RelevantLoanRule(loanRule, curDeterminer.getPatronTypes()));

							//Stop once we have accounted for all ptypes
							if (curDeterminer.getPatronType().equals("999")) {
								//999 accounts for all pTypes
								break;
							} else {
								pTypesNotAccountedFor.removeAll(curDeterminer.getPatronTypes());
								if (pTypesNotAccountedFor.size() == 0) {
									break;
								}
							}

							//We want all relevant loan rules, do not break
							//break;
						}
					}
				}
			}
		}
		cachedRelevantLoanRules.put(key, relevantLoanRules);
		return relevantLoanRules;
	}

	private boolean isPTypeValid(HashSet<Long> determinerPatronTypes, HashSet<Long> pTypesToCheck) {
		//For our case,
		if (pTypesToCheck.size() == 0){
			return true;
		}
		for (Long determinerPType : determinerPatronTypes){
			for (Long pTypeToCheck : pTypesToCheck){
				if (pTypeToCheck.equals(determinerPType)) {
					return true;
				}
			}
		}
		return false;
	}

	@Override
	protected HoldabilityInformation isItemHoldable(ItemInfo itemInfo, Scope curScope) {
		//Check to make sure this isn't an unscoped record
		if (curScope.getRelatedNumericPTypes().contains(-1L)){
			//This is an unscoped scope (everything should be holdable unless the location/itype/status is not holdable
			return super.isItemHoldable(itemInfo, curScope);
		}else{
			String locationCode;
			if (loanRulesAreBasedOnCheckoutLocation()) {
				//Loan rule determiner by lending location
				locationCode = curScope.getIlsCode();
			}else{
				//Loan rule determiner by owning location
				locationCode = itemInfo.getLocationCode();
			}

			HashSet<RelevantLoanRule> relevantLoanRules = getRelevantLoanRules(itemInfo.getITypeCode(), locationCode, curScope.getRelatedNumericPTypes());
			HashSet<Long> holdablePTypes = new HashSet<>();
			//First check to see if the overall record is not holdable based on suppression rules
			HoldabilityInformation holdability = super.isItemHoldable(itemInfo, curScope);
			boolean holdable = false;
			if (holdability.isHoldable()) {
				//Set back to false and then prove true
				holdable = false;
				for (RelevantLoanRule loanRule : relevantLoanRules) {
					if (loanRule.getLoanRule().getHoldable()) {
						holdablePTypes.addAll(loanRule.getPatronTypes());
						holdable = true;
					}
				}
			}
			return new HoldabilityInformation(holdable, holdablePTypes);
		}

	}

	@Override
	protected BookabilityInformation isItemBookable(ItemInfo itemInfo, Scope curScope) {
		String locationCode;
		if (loanRulesAreBasedOnCheckoutLocation()) {
			//Loan rule determiner by lending location
			locationCode = curScope.getIlsCode();
		}else{
			//Loan rule determiner by owning location
			locationCode = itemInfo.getLocationCode();
		}
		HashSet<RelevantLoanRule> relevantLoanRules = getRelevantLoanRules(itemInfo.getITypeCode(), locationCode, curScope.getRelatedNumericPTypes());
		HashSet<Long> bookablePTypes = new HashSet<>();
		boolean isBookable = false;
		for (RelevantLoanRule loanRule : relevantLoanRules){
			if (loanRule.getLoanRule().getBookable()){
				bookablePTypes.addAll(loanRule.getPatronTypes());
				isBookable = true;
			}
		}
		return new BookabilityInformation(isBookable, bookablePTypes);
	}

	protected String getDisplayGroupedStatus(ItemInfo itemInfo, String identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, true);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			String statusCode = itemInfo.getStatusCode();
			if (statusCode.equals("-")) {
				//We need to override based on due date
				String dueDate = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();
				if (dueDate.length() == 0 || dueDate.trim().equals("-  -")) {
					return "On Shelf";
				} else {
					return "Checked Out";
				}
			} else {
				return translateValue("item_grouped_status", statusCode, identifier);
			}
		}
	}

	protected String getDisplayStatus(ItemInfo itemInfo, String identifier) {
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			return overriddenStatus;
		}else {
			String statusCode = itemInfo.getStatusCode();
			if (statusCode.equals("-")) {
				//We need to override based on due date
				String dueDate = itemInfo.getDueDate() == null ? "" : itemInfo.getDueDate();
				if (dueDate.length() == 0 || dueDate.trim().equals("-  -")) {
					return "On Shelf";
				} else {
					return "Checked Out";
				}
			} else {
				return translateValue("item_status", statusCode, identifier);
			}
		}
	}

	protected abstract boolean loanRulesAreBasedOnCheckoutLocation();

	protected void setDetailedStatus(ItemInfo itemInfo, DataField itemField, String itemStatus, String identifier) {
		//See if we need to override based on the last check in date
		String overriddenStatus = getOverriddenStatus(itemInfo, false);
		if (overriddenStatus != null) {
			itemInfo.setDetailedStatus(overriddenStatus);
		}else {
			if (itemStatus.equals("-") && !(itemInfo.getDueDate().length() == 0 || itemInfo.getDueDate().trim().equals("-  -"))) {
				itemInfo.setDetailedStatus("Due " + getDisplayDueDate(itemInfo.getDueDate(), identifier));
			} else {
				itemInfo.setDetailedStatus(translateValue("item_status", itemStatus, identifier));
			}
		}
	}

	SimpleDateFormat displayDateFormatter = new SimpleDateFormat("MMM d, yyyy");
	protected String getDisplayDueDate(String dueDate, String identifier){
		try {
			if (dateAddedFormatter == null) {
				dateAddedFormatter = new SimpleDateFormat(dateAddedFormat);
			}
			Date dateAdded = dateAddedFormatter.parse(dueDate);
			return displayDateFormatter.format(dateAdded);
		}catch (Exception e){
			logger.warn("Could not load display due date for dueDate " + dueDate + " for identifier " + identifier, e);
		}
		return "Unknown";
	}

	/**
	 * Calculates a check digit for a III identifier
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	protected String getCheckDigit(String basedId) {
		if (basedId.length() != 7){
			return "a";
		}else{
			int sumOfDigits = 0;
			for (int i = 0; i < 7; i++){
				sumOfDigits += (8 - i) * Integer.parseInt(basedId.substring(i, i+1));
			}
			int modValue = sumOfDigits % 11;
			if (modValue == 10){
				return "x";
			}else{
				return Integer.toString(modValue);
			}
		}
	}

	protected void loadOrderInformationFromExport() {
		File activeOrders = new File(this.exportPath + "/active_orders.csv");
		if (activeOrders.exists()){
			try{
				CSVReader reader = new CSVReader(new FileReader(activeOrders));
				//First line is headers
				reader.readNext();
				String[] orderData;
				while ((orderData = reader.readNext()) != null){
					OrderInfo orderRecord = new OrderInfo();
					String recordId = ".b" + orderData[0] + getCheckDigit(orderData[0]);
					orderRecord.setRecordId(recordId);
					String orderRecordId = ".o" + orderData[1] + getCheckDigit(orderData[1]);
					orderRecord.setOrderRecordId(orderRecordId);
					orderRecord.setStatus(orderData[3]);
					orderRecord.setNumCopies(Integer.parseInt(orderData[4]));
					//Get the order record based on the accounting unit
					orderRecord.setLocationCode(orderData[5]);
					if (orderInfoFromExport.containsKey(recordId)){
						orderInfoFromExport.get(recordId).add(orderRecord);
					}else{
						ArrayList<OrderInfo> orderRecordColl = new ArrayList<OrderInfo>();
						orderRecordColl.add(orderRecord);
						orderInfoFromExport.put(recordId, orderRecordColl);
					}
				}
			}catch(Exception e){
				logger.error("Error loading order records from active orders", e);
			}
		}
	}

	protected void loadOnOrderItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, Record record, boolean hasTangibleItems){
		if (orderInfoFromExport.size() > 0){
			ArrayList<OrderInfo> orderItems = orderInfoFromExport.get(recordInfo.getRecordIdentifier());
			if (orderItems != null) {
				for (OrderInfo orderItem : orderItems) {
					createAndAddOrderItem(recordInfo, orderItem);
					//For On Order Items, increment popularity based on number of copies that are being purchased.
					groupedWork.addPopularity(orderItem.getNumCopies());
				}
				if (recordInfo.getNumCopiesOnOrder() > 0 && !hasTangibleItems) {
					groupedWork.addKeywords("On Order");
					groupedWork.addKeywords("Coming Soon");
					HashSet<String> additionalOrderSubjects = new HashSet<>();
					additionalOrderSubjects.add("On Order");
					additionalOrderSubjects.add("Coming Soon");
					groupedWork.addTopic(additionalOrderSubjects);
					groupedWork.addTopicFacet(additionalOrderSubjects);
				}
			}
		}else{
			super.loadOnOrderItems(groupedWork, recordInfo, record, hasTangibleItems);
		}
	}

	protected void createAndAddOrderItem(RecordInfo recordInfo, OrderInfo orderItem) {
		ItemInfo itemInfo = new ItemInfo();
		String orderNumber = orderItem.getOrderRecordId();
		String location = orderItem.getLocationCode();
		itemInfo.setLocationCode(orderItem.getLocationCode());
		itemInfo.setItemIdentifier(orderNumber);
		itemInfo.setNumCopies(orderItem.getNumCopies());
		itemInfo.setIsEContent(false);
		itemInfo.setIsOrderItem(true);
		itemInfo.setCallNumber("ON ORDER");
		itemInfo.setSortableCallNumber("ON ORDER");
		itemInfo.setDetailedStatus("On Order");
		itemInfo.setCollection("On Order");
		//Since we don't know when the item will arrive, assume it will come tomorrow.
		Date tomorrow = new Date();
		tomorrow.setTime(tomorrow.getTime() + 1000 * 60 * 60 * 24);
		itemInfo.setDateAdded(tomorrow);

		//Format and Format Category should be set at the record level, so we don't need to set them here.

		//Shelf Location also include the name of the ordering branch if possible
		boolean hasLocationBasedShelfLocation = false;
		boolean hasSystemBasedShelfLocation = false;

		//Add the library this is on order for
		itemInfo.setShelfLocation("On Order");

		String status = orderItem.getStatus();

		if (isOrderItemValid(status, null)){
			recordInfo.addItem(itemInfo);
			for (Scope scope: indexer.getScopes()){
				if (scope.isItemPartOfScope(profileType, location, "", true, true, false)){
					ScopingInfo scopingInfo = itemInfo.addScope(scope);
					if (scope.isLocationScope()) {
						scopingInfo.setLibraryOwned(scope.getLibraryScope().isItemOwnedByScope(profileType, location, ""));
						scopingInfo.setLocallyOwned(scope.isItemOwnedByScope(profileType, location, ""));
					}
					if (scope.isLibraryScope()) {
						scopingInfo.setLibraryOwned(scope.isItemOwnedByScope(profileType, location, ""));
						if (itemInfo.getShelfLocation().equals("On Order")){
							itemInfo.setShelfLocation(scopingInfo.getScope().getFacetLabel() + " On Order");
						}
					}
					if (scopingInfo.isLocallyOwned()){
						if (scope.isLibraryScope() && !hasLocationBasedShelfLocation && !hasSystemBasedShelfLocation){
							hasSystemBasedShelfLocation = true;
						}
						if (scope.isLocationScope() && !hasLocationBasedShelfLocation){
							hasLocationBasedShelfLocation = true;
							if (itemInfo.getShelfLocation().equals("On Order")) {
								itemInfo.setShelfLocation(scopingInfo.getScope().getFacetLabel() + "On Order");
							}
						}
					}
					scopingInfo.setAvailable(false);
					scopingInfo.setHoldable(true);
					scopingInfo.setStatus("On Order");
					scopingInfo.setGroupedStatus("On Order");

				}
			}
		}
	}
}
