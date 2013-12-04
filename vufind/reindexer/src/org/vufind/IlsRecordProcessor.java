package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.marc.*;
import org.solrmarc.tools.Utils;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileReader;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Processes data that was exported from the ILS.
 *
 * VuFind-Plus
 * User: Mark Noble
 * Date: 11/26/13
 * Time: 9:30 AM
 */
public class IlsRecordProcessor {
	private String marcRecordPath;
	private String individualMarcPath;
	private Logger logger;
	private GroupedWorkIndexer indexer;

	private HashMap<String, String> libraryMap = new HashMap<String, String>();
	private HashMap<String, String> locationMap = new HashMap<String, String>();
	private HashMap<String, String> subdomainMap = new HashMap<String, String>();

	private ArrayList<Long> pTypes = new ArrayList<Long>();
	private HashMap<Long, LoanRule> loanRules = new HashMap<Long, LoanRule>();
	private ArrayList<LoanRuleDeterminer> loanRuleDeterminers = new ArrayList<LoanRuleDeterminer>();

	private boolean getAvailabilityFromMarc = true;
	private HashSet<String> availableItemBarcodes = new HashSet<String>();

	public IlsRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, Logger logger) {
		marcRecordPath = configIni.get("Reindex", "marcPath");
		individualMarcPath = configIni.get("Reindex", "individualMarcPath");
		this.logger = logger;
		this.indexer = indexer;

		//Setup translation maps for system and location
		try {
			PreparedStatement libraryInformationStmt = vufindConn.prepareStatement("SELECT ilsCode, subdomain, facetLabel FROM library", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			ResultSet libraryInformationRS = libraryInformationStmt.executeQuery();
			while (libraryInformationRS.next()){
				String code = libraryInformationRS.getString("ilsCode");
				String facetLabel = libraryInformationRS.getString("facetLabel");
				String subdomain = libraryInformationRS.getString("subdomain");
				libraryMap.put(code, facetLabel);
				subdomainMap.put(code, subdomain);
			}

			PreparedStatement locationInformationStmt = vufindConn.prepareStatement("SELECT code, facetLabel FROM location", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			ResultSet locationInformationRS = locationInformationStmt.executeQuery();
			while (locationInformationRS.next()){
				String code = locationInformationRS.getString("code");
				String facetLabel = locationInformationRS.getString("facetLabel");
				locationMap.put(code, facetLabel);
			}
		} catch (SQLException e) {
			logger.error("Error setting up system maps", e);
		}

		loadAvailableItemBarcodes();
		loadLoanRuleInformation(vufindConn);
	}

	private void loadLoanRuleInformation(Connection vufindConn) {
		//Load loan rules
		try {
			PreparedStatement pTypesStmt = vufindConn.prepareStatement("SELECT pType from ptype", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet pTypesRS = pTypesStmt.executeQuery();
			while (pTypesRS.next()) {
				pTypes.add(pTypesRS.getLong("pType"));
			}

			PreparedStatement loanRuleStmt = vufindConn.prepareStatement("SELECT * from loan_rules", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet loanRulesRS = loanRuleStmt.executeQuery();
			while (loanRulesRS.next()) {
				LoanRule loanRule = new LoanRule();
				loanRule.setLoanRuleId(loanRulesRS.getLong("loanRuleId"));
				loanRule.setName(loanRulesRS.getString("name"));
				loanRule.setHoldable(loanRulesRS.getBoolean("holdable"));

				loanRules.put(loanRule.getLoanRuleId(), loanRule);
			}
			logger.debug("Loaded " + loanRules.size() + " loan rules");

			PreparedStatement loanRuleDeterminersStmt = vufindConn.prepareStatement("SELECT * from loan_rule_determiners where active = 1 order by rowNumber DESC", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet loanRuleDeterminersRS = loanRuleDeterminersStmt.executeQuery();
			while (loanRuleDeterminersRS.next()) {
				LoanRuleDeterminer loanRuleDeterminer = new LoanRuleDeterminer();
				loanRuleDeterminer.setLocation(loanRuleDeterminersRS.getString("location"));
				loanRuleDeterminer.setPatronType(loanRuleDeterminersRS.getString("patronType"));
				loanRuleDeterminer.setItemType(loanRuleDeterminersRS.getString("itemType"));
				loanRuleDeterminer.setLoanRuleId(loanRuleDeterminersRS.getLong("loanRuleId"));
				loanRuleDeterminer.setRowNumber(loanRuleDeterminersRS.getLong("rowNumber"));

				loanRuleDeterminers.add(loanRuleDeterminer);
			}

			logger.debug("Loaded " + loanRuleDeterminers.size() + " loan rule determiner");
		} catch (SQLException e) {
			logger.error("Unable to load loan rules", e);
		}
	}

	private void loadAvailableItemBarcodes() {
		File availableItemsFile = new File(marcRecordPath + "/available_items.csv");
		if (!availableItemsFile.exists()){
			return;
		}
		File checkoutsFile = new File(marcRecordPath + "/checkouts.csv");
		try{
			logger.debug("Loading availability for barcodes");
			getAvailabilityFromMarc = false;
			BufferedReader availableItemsReader = new BufferedReader(new FileReader(availableItemsFile));
			String availableBarcode;
			while ((availableBarcode = availableItemsReader.readLine()) != null){
				if (availableBarcode.length() > 0){
					availableItemBarcodes.add(Util.cleanIniValue(availableBarcode));
				}
			}
			availableItemsReader.close();

			//Remove any items that were checked out
			logger.debug("removing availability for checked out barcodes");
			BufferedReader checkoutsReader = new BufferedReader(new FileReader(checkoutsFile));
			String checkedOutBarcode;
			while ((checkedOutBarcode = checkoutsReader.readLine()) != null){
				availableItemBarcodes.remove(Util.cleanIniValue(checkedOutBarcode));
			}
			checkoutsReader.close();

		}catch(Exception e){
			logger.error("Error loading available items", e);
		}
	}

	public void processRecord(GroupedWorkSolr groupedWork, String identifier){
		String shortId = identifier.replace(".", "");
		String firstChars = shortId.substring(0, 4);
		String basePath = individualMarcPath + "/" + firstChars;
		String individualFilename = basePath + "/" + shortId + ".mrc";
		File individualFile = new File(individualFilename);
		if (individualFile.exists()){
			try {
				FileInputStream inputStream = new FileInputStream(individualFile);
				MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
				if (marcReader.hasNext()){
					Record record = marcReader.next();
					updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier);
				}
				inputStream.close();
			} catch (Exception e) {
				logger.error("Error loading data from ils", e);
			}
		}else{
			groupedWork.addRelatedRecord("ils:" + identifier);
		}
	}

	private void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		List<DataField> itemRecords = getDataFields(record, "989");
		List<DataField> unsuppressedItemRecords = new ArrayList<DataField>();
		for (DataField curItem : itemRecords){
			if (!isItemSuppressed(curItem)){
				unsuppressedItemRecords.add(curItem);
			}
		}

		loadRelatedRecordsAndSources(groupedWork, record, unsuppressedItemRecords, identifier);

		//Do updates based on the overall bib
		loadTitles(groupedWork, record);
		loadAuthors(groupedWork, record);

		loadFormatDetails(groupedWork, record);
		groupedWork.addTopic(getFieldList(record, "600abcdefghjklmnopqrstuvxyz:610abcdefghjklmnopqrstuvxyz:611acdefghklnpqstuvxyz:630abfghklmnoprstvxyz:650abcdevxyz:651abcdevxyz:690a"));
		groupedWork.addTopicFacet(getFieldList(record, "600a:600x:600a:610x:611x:611x:630a:630x:648x:650a:650x:651x:655x"));
		groupedWork.addSeries(getFieldList(record, "440ap:800pqt:830ap"));
		groupedWork.addSeries2(getFieldList(record, "490a"));
		groupedWork.addPhysical(getFieldList(record, "300abcefg:530abcd"));
		groupedWork.addDateSpan(getFieldList(record, "362a"));
		groupedWork.addEditions(getFieldList(record, "250a"));
		groupedWork.addContents(getFieldList(record, "505a:505t"));
		groupedWork.addGenre(getFieldList(record, "655abcvxyz"));
		groupedWork.addGenreFacet(getFieldList(record, "600v:610v:611v:630v:648v:650v:651v:655a:655v"));
		groupedWork.addGeographic(getFieldList(record, "651avxyz"));
		groupedWork.addGeographicFacet(getFieldList(record, "600z:610z:611z:630z:648z:650z:651a:651z:655z"));
		groupedWork.addEra(getFieldList(record, "600d:610y:611y:630y:648a:648y:650y:651y:655y"));

		//Do updates based on items
		loadOwnershipInformation(groupedWork, unsuppressedItemRecords);
		loadAvailability(groupedWork, unsuppressedItemRecords);
		loadUsability(groupedWork, unsuppressedItemRecords);
		loadPopularity(groupedWork, unsuppressedItemRecords);

		groupedWork.addHoldings(unsuppressedItemRecords.size());
	}

	private void loadPopularity(GroupedWorkSolr groupedWork, List<DataField> unsuppressedItemRecords) {
		float popularity = 0;
		for (DataField itemField : unsuppressedItemRecords){
			//Get number of times the title has been checked out
			Subfield totalCheckoutsField = itemField.getSubfield('h');
			int totalCheckouts = 0;
			if (totalCheckoutsField != null){
				totalCheckouts = Integer.parseInt(totalCheckoutsField.getData());
			}
			Subfield ytdCheckoutsField = itemField.getSubfield('t');
			int ytdCheckouts = 0;
			if (ytdCheckoutsField != null){
				ytdCheckouts = Integer.parseInt(ytdCheckoutsField.getData());
			}
			Subfield lastYearCheckoutsField = itemField.getSubfield('x');
			int lastYearCheckouts = 0;
			if (lastYearCheckoutsField != null){
				lastYearCheckouts = Integer.parseInt(lastYearCheckoutsField.getData());
			}
			double itemPopularity = ytdCheckouts + .5 * (lastYearCheckouts) + .1 * (totalCheckouts - lastYearCheckouts - ytdCheckouts);
			//logger.debug("Popularity for item " + itemPopularity + " ytdCheckouts=" + ytdCheckouts + " lastYearCheckouts=" + lastYearCheckouts + " totalCheckouts=" + totalCheckouts);
			popularity += itemPopularity;
		}
		groupedWork.addPopularity(popularity);
	}

	private void loadFormatDetails(GroupedWorkSolr groupedWork, Record record) {
		Set<String> formats = loadFormats(record, false);
		HashSet<String> translatedFormats = new HashSet<String>();
		HashSet<String> formatCategories = new HashSet<String>();
		Long formatBoost = 1L;
		for (String format : formats){
			translatedFormats.add(indexer.translateValue("format", format));
			formatCategories.add(indexer.translateValue("format_category", format));
			String formatBoostStr = indexer.translateValue("format_boost", format);
			try{
				Long curFormatBoost = Long.parseLong(formatBoostStr);
				if (curFormatBoost > formatBoost){
					formatBoost = curFormatBoost;
				}
			}catch (NumberFormatException e){
				logger.warn("Could not parse format_boost " + formatBoostStr);
			}
		}
		groupedWork.addFormats(translatedFormats);
		groupedWork.addFormatCategories(formatCategories);
		groupedWork.setFormatBoost(formatBoost);
	}


	private void loadAuthors(GroupedWorkSolr groupedWork, Record record) {
		//auth_author = 100abcd, first
		groupedWork.setAuthAuthor(this.getFirstFieldVal(record, "100abcd"));
		//author = a, first
		groupedWork.setAuthor(this.getFirstFieldVal(record, "100abcdq:110a:710a"));
		//author-letter = 100a, first
		groupedWork.setAuthorLetter(this.getFirstFieldVal(record, "100a"));
		//auth_author2 = 700abcd
		groupedWork.addAuthAuthor2(this.getFieldList(record, "700abcd"));
		//author2 = 110ab:111ab:700abcd:710ab:711ab:800a
		groupedWork.addAuthor2(this.getFieldList(record, "110ab:111ab:700abcd:710ab:711ab:800a"));
		//author2-role = 700e:710e
		groupedWork.addAuthor2Role(this.getFieldList(record, "700e:710e"));
		//author_additional = 505r:245c
		groupedWork.addAuthorAdditional(this.getFieldList(record, "505r:245c"));
	}

	private void loadTitles(GroupedWorkSolr groupedWork, Record record) {
		//title (full title done by index process by concatenating short and subtitle

		//title short
		groupedWork.setTitle(this.getFirstFieldVal(record, "245a"));
		//title sub
		groupedWork.setSubTitle(this.getFirstFieldVal(record, "245b"));
		//title full
		groupedWork.addFullTitles(this.getAllSubfields(record, "245", " "));
		//title sort
		groupedWork.setSortableTitle(this.getSortableTitle(record));
		//title alt
		groupedWork.addAlternateTitles(this.getFieldList(record, "130adfgklnpst:240a:246a:730adfgklnpst:740a"));
		//title old
		groupedWork.addOldTitles(this.getFieldList(record, "780ast"));
		//title new
		groupedWork.addNewTitles(this.getFieldList(record, "785ast"));
	}

	private String getFirstFieldVal(Record record, String fieldSpec) {
		Set<String> result = getFieldList(record, fieldSpec);
		if (result.size() == 0){
			return null;
		}else{
			return result.iterator().next();
		}
	}

	private void loadUsability(GroupedWorkSolr groupedWork, List<DataField> unsuppressedItemRecords) {
		//Load a list of ptypes that can use this record based on loan rules
		for (DataField curItem : unsuppressedItemRecords){
			Subfield iTypeSubfield = curItem.getSubfield('j');
			Subfield locationCodeSubfield = curItem.getSubfield('d');
			if (iTypeSubfield != null && locationCodeSubfield != null){
				String iType = iTypeSubfield.getData().trim();
				String locationCode = locationCodeSubfield.getData().trim();
				groupedWork.addCompatiblePTypes(getCompatiblePTypes(iType, locationCode));
			}
		}
	}

	private boolean isItemSuppressed(DataField curItem) {
		Subfield icode2Subfield = curItem.getSubfield('o');
		if (icode2Subfield == null){
			return false;
		}
		String icode2 = icode2Subfield.getData().toLowerCase().trim();
		String locationCode = curItem.getSubfield('d').getData().trim();

		return icode2.equals("n") || icode2.equals("x") || locationCode.equals("zzzz");
	}

	private void loadAvailability(GroupedWorkSolr groupedWork, List<DataField> itemRecords) {
		//Calculate availability based on the record
		HashSet<String> availableAt = new HashSet<String>();
		HashSet<String> availabilityToggleGlobal = new HashSet<String>();
		HashMap<String, HashSet<String>> availableAtBySystemOrLocation = new HashMap<String, HashSet<String>>();

		for (DataField curItem : itemRecords){
			Subfield statusSubfield = curItem.getSubfield('g');
			Subfield dueDateField = curItem.getSubfield('m');
			Subfield locationCodeField = curItem.getSubfield('d');
			if (locationCodeField != null){
				String locationCode = locationCodeField.getData().trim();
				boolean available = false;
				if (getAvailabilityFromMarc){
					if (statusSubfield != null) {
						String status = statusSubfield.getData();
						String dueDate = dueDateField == null ? "" : dueDateField.getData().trim();
						String availableStatus = "-dowju";
						if (availableStatus.indexOf(status.charAt(0)) >= 0) {
							if (dueDate.length() == 0) {
								available = true;
							}
						}
					}
				}else{
					//Check icode2 to see if the item is suppressed
					if (curItem.getSubfield('b') != null){
						String barcode = curItem.getSubfield('b').getData();
						available = availableItemBarcodes.contains(barcode);
					}
				}

				if (available) {
					availableAt.addAll(getLocationFacetsForLocationCode(locationCode));
				}
			}
		}
		groupedWork.addAvailableLocations(availableAt);

	}

	private void loadOwnershipInformation(GroupedWorkSolr groupedWork, List<DataField> itemRecords) {
		HashSet<String> owningLibraries = new HashSet<String>();
		HashSet<String> owningLocations = new HashSet<String>();
		for (DataField curItem : itemRecords){
			Subfield locationSubfield = curItem.getSubfield('d');
			if (locationSubfield != null){
				String locationCode = locationSubfield.getData();
				owningLibraries.addAll(getLibraryFacetsForLocationCode(locationCode));

				owningLocations.addAll(getLocationFacetsForLocationCode(locationCode));
				ArrayList<String> subdomainsForLocation = getLibrarySubdomainsForLocationCode(locationCode);

				groupedWork.addCollectionGroup(indexer.translateValue("collection_group", locationCode));
				//TODO: Make collections by library easier to define (in VuFind interface
				groupedWork.addCollectionAdams(indexer.translateValue("collection_adams", locationCode));
				groupedWork.addCollectionMsc(indexer.translateValue("collection_msc", locationCode));
				groupedWork.addCollectionWestern(indexer.translateValue("collection_western", locationCode));
				groupedWork.addDetailedLocation(indexer.translateValue("detailed_location", locationCode), subdomainsForLocation);

			}
		}
		groupedWork.addOwningLibraries(owningLibraries);
		groupedWork.addOwningLocations(owningLocations);
	}

	private ArrayList<String> getLibraryFacetsForLocationCode(String locationCode) {
		ArrayList<String> libraryFacets = new ArrayList<String>();
		for(String libraryCode : libraryMap.keySet()){
			if (locationCode.startsWith(libraryCode)){
				libraryFacets.add(libraryMap.get(libraryCode));
			}
		}
		if (libraryFacets.size() == 0){
			logger.warn("Did not find any library facets for " + locationCode);
		}
		return libraryFacets;
	}

	private ArrayList<String> getLibrarySubdomainsForLocationCode(String locationCode) {
		ArrayList<String> librarySubdomains = new ArrayList<String>();
		for(String libraryCode : subdomainMap.keySet()){
			if (locationCode.startsWith(libraryCode)){
				librarySubdomains.add(subdomainMap.get(libraryCode));
			}
		}
		if (librarySubdomains.size() == 0){
			logger.warn("Did not find any library subdomains for " + locationCode);
		}
		return librarySubdomains;
	}

	private HashSet<String> locationCodesWithoutFacets = new HashSet<String>();
	private ArrayList<String> getLocationFacetsForLocationCode(String locationCode) {
		ArrayList<String> locationFacets = new ArrayList<String>();
		for(String ilsCode : locationMap.keySet()){
			if (locationCode.startsWith(ilsCode)){
				locationFacets.add(locationMap.get(ilsCode));
			}
		}
		if (locationFacets.size() == 0){
			if (!locationCodesWithoutFacets.contains(locationCode)){
				logger.warn("Did not find any location facets for " + locationCode);
				locationCodesWithoutFacets.add(locationCode);
			}
		}
		return locationFacets;
	}

	private void loadRelatedRecordsAndSources(GroupedWorkSolr groupedWork, Record record, List<DataField> itemRecords, String identifier) {
		HashSet<String> relatedRecords = new HashSet<String>();
		HashSet<String> sources = new HashSet<String>();
		HashSet<String> alternateIds = new HashSet<String>();
		boolean allItemsAreEContent = true;
		for (DataField curItem : itemRecords){
			//Check subfield w to get the source
			if (curItem.getSubfield('w') != null){
				String subfieldW = curItem.getSubfield('w').getData();
				String[] econtentData = subfieldW.split("\\s?:\\s?");
				String eContentSource = econtentData[0].toLowerCase();
				if (eContentSource.equals("gutenberg")){
					//TODO: Check for the id from gutenberg.  Will be in the url (either in the item or 856)
					relatedRecords.add("gutenberg:" + identifier);
					sources.add("gutenberg");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("co state gov docs")){
					//TODO: See if we can get the id for the original file
					relatedRecords.add("co_state_gov_doc:" + identifier);
					sources.add("co_state_gov_doc");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("us gov docs")){
					//TODO: See if we can get the id for the original file
					relatedRecords.add("us_gov_doc:" + identifier);
					sources.add("co_state_gov_doc");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("free")){
					//TODO: See if we can get the id for the original file
					relatedRecords.add("public_econtent:" + identifier);
					sources.add("public_econtent");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("acs")){
					//TODO: See if we can get the id for the original file
					relatedRecords.add("drm_econtent:" + identifier);
					sources.add("drm_econtent");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("overdrive")){
					//TODO: Get the id for the original file. Will be in the url (either in the item or 856
					relatedRecords.add("overdrive:" + identifier);
					sources.add("overdrive");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("oneclick digital")){
					//TODO: Get the id for the original file. Will be in the url (either in the item or 856
					relatedRecords.add("overdrive:" + identifier);
					sources.add("overdrive");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("database")){
					//TODO: Get the id for the original file. Will be in the url (either in the item or 856
					relatedRecords.add("external_econtent:" + identifier);
					sources.add("external_econtent");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("freading")){
					//TODO: Get the id for the original file. Will be in the url (either in the item or 856
					relatedRecords.add("external_econtent:" + identifier);
					sources.add("external_econtent");
					alternateIds.add(identifier);
				}else if (eContentSource.equals("ebsco") || eContentSource.equals("lion") || eContentSource.equals("ebsco academic") || eContentSource.equals("biblioboard") || eContentSource.equals("ebrary") || eContentSource.equals("oxford") || eContentSource.equals("oxford reference") || eContentSource.equals("prospector") || eContentSource.equals("ebl") || eContentSource.equals("springerlink")){
					relatedRecords.add("external_econtent:" + identifier);
					sources.add("external_econtent");
					alternateIds.add(identifier);
				}else{
					logger.warn("Unknown source " + eContentSource);
					relatedRecords.add("external_econtent:" + identifier);
					sources.add("external_econtent");
					alternateIds.add(identifier);
				}
			}else{
				allItemsAreEContent = false;
			}
		}
		if (!allItemsAreEContent){
			relatedRecords.add("ils:" + identifier);
			sources.add("ils");
			alternateIds.add(identifier);
		}
		groupedWork.addRelatedRecords(relatedRecords);
		groupedWork.addRecordSources(sources);
		groupedWork.addAlternateIds(alternateIds);
	}

	private List<DataField> getDataFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<DataField>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}

	private List<DataField> getDataFields(Record marcRecord, String[] tags) {
		List variableFields = marcRecord.getVariableFields(tags);
		List<DataField> variableFieldsReturn = new ArrayList<DataField>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}

	private HashMap<String, LinkedHashSet<String>> ptypesByItypeAndLocation = new HashMap<String, LinkedHashSet<String>>();
	public LinkedHashSet<String> getCompatiblePTypes(String iType, String locationCode) {
		String cacheKey = iType + ":" + locationCode;
		if (ptypesByItypeAndLocation.containsKey(cacheKey)){
			return ptypesByItypeAndLocation.get(cacheKey);
		}
		//logger.debug("getCompatiblePTypes for " + cacheKey);
		LinkedHashSet<String> result = new LinkedHashSet<String>();
		Long iTypeLong = Long.parseLong(iType);
		//Loop through all patron types to see if the item is holdable
		for (Long pType : pTypes){
			//logger.debug("  Checking pType " + pType);
			//Loop through the loan rules to see if this itype can be used based on the location code
			for (LoanRuleDeterminer curDeterminer : loanRuleDeterminers){
				//logger.debug("   Checking determiner " + curDeterminer.getRowNumber() + " " + curDeterminer.getLocation());
				//Make sure the location matchs
				if (curDeterminer.matchesLocation(locationCode)){
					//logger.debug("    " + curDeterminer.getRowNumber() + " matches location");
					if (curDeterminer.getItemType().equals("999") || curDeterminer.getItemTypes().contains(iTypeLong)){
						//logger.debug("    " + curDeterminer.getRowNumber() + " matches iType");
						if (curDeterminer.getPatronType().equals("999") || curDeterminer.getPatronTypes().contains(pType)){
							//logger.debug("    " + curDeterminer.getRowNumber() + " matches pType");
							LoanRule loanRule = loanRules.get(curDeterminer.getLoanRuleId());
							if (loanRule.getHoldable().equals(Boolean.TRUE)){
								result.add(pType.toString());
							}
							//We got a match, stop processig
							//logger.debug("    using determiner " + curDeterminer.getRowNumber() + " for ptype " + pType);
							break;
						}
					}
				}
			}
		}
		//logger.debug("  " + result.size() + " ptypes can use this");
		ptypesByItypeAndLocation.put(cacheKey, result);
		return result;
	}

	/**
	 * Get Set of Strings as indicated by tagStr. For each field spec in the
	 * tagStr that is NOT about bytes (i.e. not a 008[7-12] type fieldspec), the
	 * result string is the concatenation of all the specific subfields.
	 *
	 * @param record
	 *          - the marc record object
	 * @param tagStr
	 *          string containing which field(s)/subfield(s) to use. This is a
	 *          series of: marc "tag" string (3 chars identifying a marc field,
	 *          e.g. 245) optionally followed by characters identifying which
	 *          subfields to use. Separator of colon indicates a separate value,
	 *          rather than concatenation. 008[5-7] denotes bytes 5-7 of the 008
	 *          field (0 based counting) 100[a-cf-z] denotes the bracket pattern
	 *          is a regular expression indicating which subfields to include.
	 *          Note: if the characters in the brackets are digits, it will be
	 *          interpreted as particular bytes, NOT a pattern. 100abcd denotes
	 *          subfields a, b, c, d are desired.
	 * @return the contents of the indicated marc field(s)/subfield(s), as a set
	 *         of Strings.
	 */
	public Set<String> getFieldList(Record record, String tagStr) {
		String[] tags = tagStr.split(":");
		Set<String> result = new LinkedHashSet<String>();
		for (String tag1 : tags) {
			// Check to ensure tag length is at least 3 characters
			if (tag1.length() < 3) {
				System.err.println("Invalid tag specified: " + tag1);
				continue;
			}

			// Get Field Tag
			String tag = tag1.substring(0, 3);
			boolean linkedField = false;
			if (tag.equals("LNK")) {
				tag = tag1.substring(3, 6);
				linkedField = true;
			}
			// Process Subfields
			String subfield = tag1.substring(3);
			boolean havePattern = false;
			int subend = 0;
			// brackets indicate parsing for individual characters or as pattern
			int bracket = tag1.indexOf('[');
			if (bracket != -1) {
				String sub[] = tag1.substring(bracket + 1).split("[\\]\\[\\-, ]+");
				try {
					// if bracket expression is digits, expression is treated as character
					// positions
					int substart = Integer.parseInt(sub[0]);
					subend = (sub.length > 1) ? Integer.parseInt(sub[1]) + 1
							: substart + 1;
					String subfieldWObracket = subfield.substring(0, bracket - 3);
					result.addAll(getSubfieldDataAsSet(record, tag, subfieldWObracket,
							substart, subend));
				} catch (NumberFormatException e) {
					// assume brackets expression is a pattern such as [a-z]
					havePattern = true;
				}
			}
			if (subend == 0) // don't want specific characters.
			{
				String separator = null;
				if (subfield.indexOf('\'') != -1) {
					separator = subfield.substring(subfield.indexOf('\'') + 1,
							subfield.length() - 1);
					subfield = subfield.substring(0, subfield.indexOf('\''));
				}

				if (havePattern)
					if (linkedField)
						result.addAll(getLinkedFieldValue(record, tag, subfield, separator));
					else
						result.addAll(getAllSubfields(record, tag + subfield, separator));
				else if (linkedField)
					result.addAll(getLinkedFieldValue(record, tag, subfield, separator));
				else
					result.addAll(getSubfieldDataAsSet(record, tag, subfield, separator));
			}
		}
		return result;
	}

	/**
	 * Get the specified substring of subfield values from the specified MARC
	 * field, returned as a set of strings to become lucene document field values
	 *
	 * @param record
	 *          - the marc record object
	 * @param fldTag
	 *          - the field name, e.g. 008
	 * @param subfield
	 *          - the string containing the desired subfields
	 * @param beginIx
	 *          - the beginning index of the substring of the subfield value
	 * @param endIx
	 *          - the ending index of the substring of the subfield value
	 * @return the result set of strings
	 */
	@SuppressWarnings("unchecked")
	protected Set<String> getSubfieldDataAsSet(Record record, String fldTag, String subfield, int beginIx, int endIx) {
		Set<String> resultSet = new LinkedHashSet<String>();

		// Process Leader
		if (fldTag.equals("000")) {
			resultSet.add(record.getLeader().toString().substring(beginIx, endIx));
			return resultSet;
		}

		// Loop through Data and Control Fields
		List<VariableField> varFlds = record.getVariableFields(fldTag);
		for (VariableField vf : varFlds) {
			if (!isControlField(fldTag) && subfield != null) {
				// Data Field
				DataField dfield = (DataField) vf;
				if (subfield.length() > 1) {
					// automatic concatenation of grouped subFields
					StringBuilder buffer = new StringBuilder("");
					List<Subfield> subFields = dfield.getSubfields();
					for (Subfield sf : subFields) {
						if (subfield.indexOf(sf.getCode()) != -1
								&& sf.getData().length() >= endIx) {
							if (buffer.length() > 0)
								buffer.append(" ");
							buffer.append(sf.getData().substring(beginIx, endIx));
						}
					}
					resultSet.add(buffer.toString());
				} else {
					// get all instances of the single subfield
					List<Subfield> subFlds = dfield.getSubfields(subfield.charAt(0));
					for (Subfield sf : subFlds) {
						if (sf.getData().length() >= endIx)
							resultSet.add(sf.getData().substring(beginIx, endIx));
					}
				}
			} else // Control Field
			{
				String cfldData = ((ControlField) vf).getData();
				if (cfldData.length() >= endIx)
					resultSet.add(cfldData.substring(beginIx, endIx));
			}
		}
		return resultSet;
	}

	/**
	 * Get the specified subfields from the specified MARC field, returned as a
	 * set of strings to become lucene document field values
	 *
	 * @param fldTag
	 *          - the field name, e.g. 245
	 * @param subfieldsStr
	 *          - the string containing the desired subfields
	 * @param separator
	 *          - the separator string to insert between subfield items (if null,
	 *          a " " will be used)
	 * @return a Set of String, where each string is the concatenated contents of
	 *          all the desired subfield values from a single instance of the
	 *          fldTag
	 */
	@SuppressWarnings("unchecked")
	protected Set<String> getSubfieldDataAsSet(Record record, String fldTag, String subfieldsStr, String separator) {
		Set<String> resultSet = new LinkedHashSet<String>();

		// Process Leader
		if (fldTag.equals("000")) {
			resultSet.add(record.getLeader().toString());
			return resultSet;
		}

		// Loop through Data and Control Fields
		// int iTag = new Integer(fldTag).intValue();
		List<VariableField> varFlds = record.getVariableFields(fldTag);
		for (VariableField vf : varFlds) {
			if (!isControlField(fldTag) && subfieldsStr != null) {
				// DataField
				DataField dfield = (DataField) vf;

				if (subfieldsStr.length() > 1 || separator != null) {
					// concatenate subfields using specified separator or space
					StringBuilder buffer = new StringBuilder("");
					List<Subfield> subFields = dfield.getSubfields();
					for (Subfield sf : subFields) {
						if (subfieldsStr.indexOf(sf.getCode()) != -1) {
							if (buffer.length() > 0) {
								buffer.append(separator != null ? separator : " ");
							}
							buffer.append(sf.getData().trim());
						}
					}
					if (buffer.length() > 0){
						resultSet.add(buffer.toString());
					}
				} else if (subfieldsStr.length() == 1) {
					// get all instances of the single subfield
					List<Subfield> subFields = dfield.getSubfields(subfieldsStr.charAt(0));
					for (Subfield sf : subFields) {
						resultSet.add(sf.getData().trim());
					}
				} else {
					logger
							.warn("No subfield provided when getting getSubfieldDataAsSet for "
									+ fldTag);
				}
			} else {
				// Control Field
				resultSet.add(((ControlField) vf).getData().trim());
			}
		}
		return resultSet;
	}

	protected boolean isControlField(String fieldTag) {
		if (fieldTag.matches("00[0-9]")) {
			return (true);
		}
		return (false);
	}

	/**
	 * Given a tag for a field, and a list (or regex) of one or more subfields get
	 * any linked 880 fields and include the appropriate subfields as a String
	 * value in the result set.
	 *
	 * @param tag
	 *          - the marc field for which 880s are sought.
	 * @param subfield
	 *          - The subfield(s) within the 880 linked field that should be
	 *          returned [a-cf-z] denotes the bracket pattern is a regular
	 *          expression indicating which subfields to include from the linked
	 *          880. Note: if the characters in the brackets are digits, it will
	 *          be interpreted as particular bytes, NOT a pattern 100abcd denotes
	 *          subfields a, b, c, d are desired from the linked 880.
	 * @param separator
	 *          - the separator string to insert between subfield items (if null,
	 *          a " " will be used)
	 *
	 * @return set of Strings containing the values of the designated 880
	 *         field(s)/subfield(s)
	 */
	@SuppressWarnings("unchecked")
	public Set<String> getLinkedFieldValue(Record record, String tag, String subfield, String separator) {
		// assume brackets expression is a pattern such as [a-z]
		Set<String> result = new LinkedHashSet<String>();
		boolean havePattern = false;
		Pattern subfieldPattern = null;
		if (subfield.indexOf('[') != -1) {
			havePattern = true;
			subfieldPattern = Pattern.compile(subfield);
		}
		List<VariableField> fields = record.getVariableFields("880");
		for (VariableField vf : fields) {
			DataField dfield = (DataField) vf;
			Subfield link = dfield.getSubfield('6');
			if (link != null && link.getData().startsWith(tag)) {
				List<Subfield> subList = dfield.getSubfields();
				StringBuilder buf = new StringBuilder("");
				for (Subfield subF : subList) {
					boolean addIt = false;
					if (havePattern) {
						Matcher matcher = subfieldPattern.matcher("" + subF.getCode());
						// matcher needs a string, hence concat with empty
						// string
						if (matcher.matches())
							addIt = true;
					} else
					// a list a subfields
					{
						if (subfield.indexOf(subF.getCode()) != -1)
							addIt = true;
					}
					if (addIt) {
						if (buf.length() > 0)
							buf.append(separator != null ? separator : " ");
						buf.append(subF.getData().trim());
					}
				}
				if (buf.length() > 0)
					result.add(Utils.cleanData(buf.toString()));
			}
		}
		return (result);
	}

	/**
	 * extract all the subfields requested in requested marc fields. Each instance
	 * of each marc field will be put in a separate result (but the subfields will
	 * be concatenated into a single value for each marc field)
	 *
	 * @param fieldSpec
	 *          - the desired marc fields and subfields as given in the
	 *          xxx_index.properties file
	 * @param separator
	 *          - the character to use between subfield values in the solr field
	 *          contents
	 * @return Set of values (as strings) for solr field
	 */
	@SuppressWarnings("unchecked")
	public Set<String> getAllSubfields(Record record, String fieldSpec, String separator) {
		Set<String> result = new LinkedHashSet<String>();

		String[] fldTags = fieldSpec.split(":");
		for (String fldTag1 : fldTags) {
			// Check to ensure tag length is at least 3 characters
			if (fldTag1.length() < 3) {
				System.err.println("Invalid tag specified: " + fldTag1);
				continue;
			}

			String fldTag = fldTag1.substring(0, 3);

			String subfldTags = fldTag1.substring(3);

			List<VariableField> marcFieldList = record.getVariableFields(fldTag);
			if (!marcFieldList.isEmpty()) {
				Pattern subfieldPattern = Pattern
						.compile(subfldTags.length() == 0 ? "." : subfldTags);
				for (VariableField vf : marcFieldList) {
					DataField marcField = (DataField) vf;
					StringBuilder buffer = new StringBuilder("");
					List<Subfield> subFields = marcField.getSubfields();
					for (Subfield subfield : subFields) {
						Matcher matcher = subfieldPattern.matcher("" + subfield.getCode());
						if (matcher.matches()) {
							if (buffer.length() > 0)
								buffer.append(separator != null ? separator : " ");
							buffer.append(subfield.getData().trim());
						}
					}
					if (buffer.length() > 0)
						result.add(Utils.cleanData(buffer.toString()));
				}
			}
		}

		return result;
	}

	/**
	 * Get the title (245ab) from a record, without non-filing chars as specified
	 * in 245 2nd indicator, and lower cased.
	 *
	 * @return 245a and 245b values concatenated, with trailing punctuation removed, and
	 *         with non-filing characters omitted. Null returned if no title can
	 *         be found.
	 */
	public String getSortableTitle(Record record) {
		DataField titleField = (DataField) record.getVariableField("245");
		if (titleField == null)
			return "";

		int nonFilingInt = getInd2AsInt(titleField);

		String title = titleField.getSubfield('a').getData();
		title = title.toLowerCase();

		// Skip non-filing chars, if possible.
		if (title.length() > nonFilingInt) {
			title = title.substring(nonFilingInt);
		}

		if (title.length() == 0) {
			return null;
		}

		return title;
	}

	/**
	 * @param df
	 *          a DataField
	 * @return the integer (0-9, 0 if blank or other) in the 2nd indicator
	 */
	protected int getInd2AsInt(DataField df) {
		char ind2char = df.getIndicator2();
		int result = 0;
		if (Character.isDigit(ind2char))
			result = Integer.valueOf(String.valueOf(ind2char));
		return result;
	}

	/**
	 * Determine Record Format(s)
	 *
	 * @return Set format of record
	 */
	public Set<String> loadFormats(Record record, boolean returnFirst) {
		Set<String> result = new LinkedHashSet<String>();
		String leader = record.getLeader().toString();
		char leaderBit;
		ControlField fixedField = (ControlField) record.getVariableField("008");
		//DataField title = (DataField) record.getVariableField("245");
		char formatCode;

		// check for music recordings quickly so we can figure out if it is music
		// for category (needto do here since checking what is on the Compact
		// Disc/Phonograph, etc is difficult).
		if (leader.length() >= 6) {
			leaderBit = leader.charAt(6);
			switch (Character.toUpperCase(leaderBit)) {
				case 'J':
					result.add("MusicRecording");
					break;
			}
		}
		if (result.size() >= 1 && returnFirst)
			return result;

		// check for playaway in 260|b
		DataField sysDetailsNote = (DataField) record.getVariableField("260");
		if (sysDetailsNote != null) {
			if (sysDetailsNote.getSubfield('b') != null) {
				String sysDetailsValue = sysDetailsNote.getSubfield('b').getData()
						.toLowerCase();
				if (sysDetailsValue.contains("playaway")) {
					result.add("Playaway");
					if (returnFirst)
						return result;
				}
			}
		}

		// Check for formats in the 538 field
		DataField sysDetailsNote2 = (DataField) record.getVariableField("538");
		if (sysDetailsNote2 != null) {
			if (sysDetailsNote2.getSubfield('a') != null) {
				String sysDetailsValue = sysDetailsNote2.getSubfield('a').getData()
						.toLowerCase();
				if (sysDetailsValue.contains("playaway")) {
					result.add("Playaway");
					if (returnFirst)
						return result;
				} else if (sysDetailsValue.contains("bluray")
						|| sysDetailsValue.contains("blu-ray")) {
					result.add("Blu-ray");
					if (returnFirst)
						return result;
				} else if (sysDetailsValue.contains("dvd")) {
					result.add("DVD");
					if (returnFirst)
						return result;
				} else if (sysDetailsValue.contains("vertical file")) {
					result.add("VerticalFile");
					if (returnFirst)
						return result;
				}
			}
		}

		// Check for formats in the 500 tag
		DataField noteField = (DataField) record.getVariableField("500");
		if (noteField != null) {
			if (noteField.getSubfield('a') != null) {
				String noteValue = noteField.getSubfield('a').getData().toLowerCase();
				if (noteValue.contains("vertical file")) {
					result.add("VerticalFile");
					if (returnFirst)
						return result;
				}
			}
		}

		// check if there's an h in the 245
		/*if (title != null) {
			if (title.getSubfield('h') != null) {
				if (title.getSubfield('h').getData().toLowerCase()
						.contains("[electronic resource]")) {
					result.add("Electronic");
					if (returnFirstValue)
						return result;
				}
			}
		}*/

		// Check for large print book (large format in 650, 300, or 250 fields)
		// Check for blu-ray in 300 fields
		DataField edition = (DataField) record.getVariableField("250");
		if (edition != null) {
			if (edition.getSubfield('a') != null) {
				if (edition.getSubfield('a').getData().toLowerCase()
						.contains("large type")) {
					result.add("LargePrint");
					if (returnFirst)
						return result;
				}
			}
		}

		@SuppressWarnings("unchecked")
		List<DataField> physicalDescription = record.getVariableFields("300");
		if (physicalDescription != null) {
			Iterator<DataField> fieldsIter = physicalDescription.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				@SuppressWarnings("unchecked")
				List<Subfield> subFields = field.getSubfields();
				for (Subfield subfield : subFields) {
					if (subfield.getData().toLowerCase().contains("large type")) {
						result.add("LargePrint");
						if (returnFirst)
							return result;
					} else if (subfield.getData().toLowerCase().contains("bluray")
							|| subfield.getData().toLowerCase().contains("blu-ray")) {
						result.add("Blu-ray");
						if (returnFirst)
							return result;
					}
				}
			}
		}
		@SuppressWarnings("unchecked")
		List<DataField> topicalTerm = record.getVariableFields("650");
		if (topicalTerm != null) {
			Iterator<DataField> fieldsIter = topicalTerm.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				@SuppressWarnings("unchecked")
				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					if (subfield.getData().toLowerCase().contains("large type")) {
						result.add("LargePrint");
						if (returnFirst)
							return result;
					}
				}
			}
		}

		@SuppressWarnings("unchecked")
		List<DataField> localTopicalTerm = record.getVariableFields("690");
		if (localTopicalTerm != null) {
			Iterator<DataField> fieldsIterator = localTopicalTerm.iterator();
			DataField field;
			while (fieldsIterator.hasNext()) {
				field = fieldsIterator.next();
				Subfield subfieldA = field.getSubfield('a');
				if (subfieldA != null) {
					if (subfieldA.getData().toLowerCase().contains("seed library")) {
						result.add("SeedPacket");
						if (returnFirst)
							return result;
					}
				}
			}
		}

		// check the 007 - this is a repeating field
		@SuppressWarnings("unchecked")
		List<DataField> fields = record.getVariableFields("007");
		if (fields != null) {
			Iterator<DataField> fieldsIter = fields.iterator();
			ControlField formatField;
			while (fieldsIter.hasNext()) {
				formatField = (ControlField) fieldsIter.next();
				if (formatField.getData() == null || formatField.getData().length() < 2) {
					continue;
				}
				// Check for blu-ray (s in position 4)
				// This logic does not appear correct.
				/*
				 * if (formatField.getData() != null && formatField.getData().length()
				 * >= 4){ if (formatField.getData().toUpperCase().charAt(4) == 'S'){
				 * result.add("Blu-ray"); break; } }
				 */
				formatCode = formatField.getData().toUpperCase().charAt(0);
				switch (formatCode) {
					case 'A':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'D':
								result.add("Atlas");
								break;
							default:
								result.add("Map");
								break;
						}
						break;
					case 'C':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'A':
								result.add("TapeCartridge");
								break;
							case 'B':
								result.add("ChipCartridge");
								break;
							case 'C':
								result.add("DiscCartridge");
								break;
							case 'F':
								result.add("TapeCassette");
								break;
							case 'H':
								result.add("TapeReel");
								break;
							case 'J':
								result.add("FloppyDisk");
								break;
							case 'M':
							case 'O':
								result.add("CDROM");
								break;
							case 'R':
								// Do not return - this will cause anything with an
								// 856 field to be labeled as "Electronic"
								break;
							default:
								result.add("Software");
								break;
						}
						break;
					case 'D':
						result.add("Globe");
						break;
					case 'F':
						result.add("Braille");
						break;
					case 'G':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
							case 'D':
								result.add("Filmstrip");
								break;
							case 'T':
								result.add("Transparency");
								break;
							default:
								result.add("Slide");
								break;
						}
						break;
					case 'H':
						result.add("Microfilm");
						break;
					case 'K':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
								result.add("Collage");
								break;
							case 'D':
								result.add("Drawing");
								break;
							case 'E':
								result.add("Painting");
								break;
							case 'F':
								result.add("Print");
								break;
							case 'G':
								result.add("Photonegative");
								break;
							case 'J':
								result.add("Print");
								break;
							case 'L':
								result.add("Drawing");
								break;
							case 'O':
								result.add("FlashCard");
								break;
							case 'N':
								result.add("Chart");
								break;
							default:
								result.add("Photo");
								break;
						}
						break;
					case 'M':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'F':
								result.add("VideoCassette");
								break;
							case 'R':
								result.add("Filmstrip");
								break;
							default:
								result.add("MotionPicture");
								break;
						}
						break;
					case 'O':
						result.add("Kit");
						break;
					case 'Q':
						result.add("MusicalScore");
						break;
					case 'R':
						result.add("SensorImage");
						break;
					case 'S':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'D':
								if (formatField.getData().length() >= 4) {
									char speed = formatField.getData().toUpperCase().charAt(3);
									if (speed >= 'A' && speed <= 'E') {
										result.add("Phonograph");
									} else if (speed == 'F') {
										result.add("CompactDisc");
									} else if (speed >= 'K' && speed <= 'R') {
										result.add("TapeRecording");
									} else {
										result.add("SoundDisc");
									}
								} else {
									result.add("SoundDisc");
								}
								break;
							case 'S':
								result.add("SoundCassette");
								break;
							default:
								result.add("SoundRecording");
								break;
						}
						break;
					case 'T':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'A':
								result.add("Book");
								break;
							case 'B':
								result.add("LargePrint");
								break;
						}
						break;
					case 'V':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
								result.add("VideoCartridge");
								break;
							case 'D':
								result.add("VideoDisc");
								break;
							case 'F':
								result.add("VideoCassette");
								break;
							case 'R':
								result.add("VideoReel");
								break;
							default:
								result.add("Video");
								break;
						}
						break;
				}
				if (returnFirst && !result.isEmpty()) {
					return result;
				}
			}
			if (!result.isEmpty() && returnFirst) {
				return result;
			}
		}

		// check the Leader at position 6
		if (leader.length() >= 6) {
			leaderBit = leader.charAt(6);
			switch (Character.toUpperCase(leaderBit)) {
				case 'C':
				case 'D':
					result.add("MusicalScore");
					break;
				case 'E':
				case 'F':
					result.add("Map");
					break;
				case 'G':
					// We appear to have a number of items without 007 tags marked as G's.
					// These seem to be Videos rather than Slides.
					// result.add("Slide");
					result.add("Video");
					break;
				case 'I':
					result.add("SoundRecording");
					break;
				case 'J':
					result.add("MusicRecording");
					break;
				case 'K':
					result.add("Photo");
					break;
				case 'M':
					result.add("Electronic");
					break;
				case 'O':
				case 'P':
					result.add("Kit");
					break;
				case 'R':
					result.add("PhysicalObject");
					break;
				case 'T':
					result.add("Manuscript");
					break;
			}
		}
		if (!result.isEmpty() && returnFirst) {
			return result;
		}

		if (leader.length() >= 7) {
			// check the Leader at position 7
			leaderBit = leader.charAt(7);
			switch (Character.toUpperCase(leaderBit)) {
				// Monograph
				case 'M':
					if (result.isEmpty()) {
						result.add("Book");
					}
					break;
				// Serial
				case 'S':
					// Look in 008 to determine what type of Continuing Resource
					formatCode = fixedField.getData().toUpperCase().charAt(21);
					switch (formatCode) {
						case 'N':
							result.add("Newspaper");
							break;
						case 'P':
							result.add("Journal");
							break;
						default:
							result.add("Serial");
							break;
					}
			}
		}

		// Nothing worked!
		if (result.isEmpty()) {
			result.add("Unknown");
		}

		return result;
	}
}
