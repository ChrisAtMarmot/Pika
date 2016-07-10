package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.*;

import java.io.File;
import java.io.FileReader;
import java.io.FilenameFilter;
import java.io.IOException;
import java.sql.*;
import java.util.*;
import java.util.Date;

/**
 * Description goes here
 * Rampart Marc Conversion
 * User: Mark Noble
 * Date: 10/17/13
 * Time: 9:26 AM
 */
public class RecordGroupingProcessor {
	protected Logger logger;
	protected String recordNumberTag = "";
	protected String recordNumberPrefix = "";
	protected String itemTag;
	protected boolean useEContentSubfield = false;
	protected char eContentDescriptor = ' ';
	protected PreparedStatement insertGroupedWorkStmt;
	protected PreparedStatement updateDateUpdatedForGroupedWorkStmt;
	protected PreparedStatement getExistingIdentifierStmt;
	protected PreparedStatement insertIdentifierStmt;
	protected PreparedStatement addIdentifierToGroupedWorkStmt;
	protected PreparedStatement addPrimaryIdentifierForWorkStmt;
	protected PreparedStatement removePrimaryIdentifierStmt;
	protected PreparedStatement removeIdentifiersForPrimaryIdentifierStmt;
	protected PreparedStatement removePrimaryIdentifiersForWorkStmt;
	protected PreparedStatement addPrimaryIdentifierToSecondaryIdentifierRefStmt;
	protected PreparedStatement getSecondaryIdentifiersForPrimaryIdentifier;
	protected PreparedStatement getSecondaryIdentifiersForGroupedWork;
	protected PreparedStatement removeSecondaryIdentifierFromPrimaryIdentifier;

	protected int numRecordsProcessed = 0;
	protected int numGroupedWorksAdded = 0;

	protected boolean fullRegrouping;
	protected long startTime = new Date().getTime();

	protected HashMap<String, HashMap<String, String>> translationMaps = new HashMap<>();

	//TODO: Determine if we can avoid this by simply using the ON DUPLICATE KEY UPDATE FUNCTIONALITY
	//Would also want to mark merged works as changed (at least once) to make sure they get reindexed.
	protected HashMap<String, Long> existingGroupedWorks = new HashMap<>();

	//A list of grouped works that have been manually merged.
	protected HashMap<String, String> mergedGroupedWorks = new HashMap<>();
	protected HashSet<String> recordsToNotGroup = new HashSet<>();

	/**
	 * Default constructor for use by subclasses
	 */
	protected RecordGroupingProcessor(Logger logger, boolean fullRegrouping){
		this.logger = logger;
		this.fullRegrouping = fullRegrouping;
	}
	/**
	 * Creates a record grouping processor that saves results to the database.
	 *
	 * @param dbConnection   - The Connection to the Pika database
	 * @param serverName     - The server we are grouping data for
	 * @param configIni      - The configuration information for the server we are grouping
	 * @param logger         - A logger to store debug and error messages to.
	 * @param fullRegrouping - Whether or not we are doing full regrouping or if we are only grouping changes.
	 *                         Determines if old works are loaded at the beginning.
	 */
	public RecordGroupingProcessor(Connection dbConnection, String serverName, Ini configIni, Logger logger, boolean fullRegrouping) {
		this.logger = logger;
		this.fullRegrouping = fullRegrouping;
		recordNumberTag = configIni.get("Reindex", "recordNumberTag");
		recordNumberPrefix = configIni.get("Reindex", "recordNumberPrefix");
		itemTag = configIni.get("Reindex", "itemTag");
		useEContentSubfield = Boolean.parseBoolean(configIni.get("Reindex", "useEContentSubfield"));
		eContentDescriptor = getSubfieldIndicatorFromConfig(configIni, "eContentSubfield");

		setupDatabaseStatements(dbConnection);

		loadTranslationMaps(serverName);

	}

	protected void setupDatabaseStatements(Connection dbConnection) {
		try{
				insertGroupedWorkStmt = dbConnection.prepareStatement("INSERT INTO " + RecordGrouperMain.groupedWorkTableName + " (full_title, author, grouping_category, permanent_id, date_updated) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_updated = VALUES(date_updated), id=LAST_INSERT_ID(id) ", Statement.RETURN_GENERATED_KEYS) ;
				updateDateUpdatedForGroupedWorkStmt = dbConnection.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = ?");
				getExistingIdentifierStmt = dbConnection.prepareStatement("SELECT id FROM " + RecordGrouperMain.groupedWorkIdentifiersTableName + " where type = ? and identifier = ?",  ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				insertIdentifierStmt = dbConnection.prepareStatement("INSERT INTO " + RecordGrouperMain.groupedWorkIdentifiersTableName + " (type, identifier) VALUES (?, ?)", Statement.RETURN_GENERATED_KEYS);
				addIdentifierToGroupedWorkStmt = dbConnection.prepareStatement("INSERT IGNORE INTO " + RecordGrouperMain.groupedWorkIdentifiersRefTableName + " (grouped_work_id, identifier_id) VALUES (?, ?)");
				addPrimaryIdentifierForWorkStmt = dbConnection.prepareStatement("INSERT INTO grouped_work_primary_identifiers (grouped_work_id, type, identifier) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), grouped_work_id = VALUES(grouped_work_id)", Statement.RETURN_GENERATED_KEYS);
				removePrimaryIdentifierStmt = dbConnection.prepareStatement("DELETE FROM grouped_work_primary_identifiers WHERE type = ? and identifier = ?");
				removeIdentifiersForPrimaryIdentifierStmt = dbConnection.prepareStatement("DELETE FROM grouped_work_primary_to_secondary_id_ref where primary_identifier_id = ?");
				removePrimaryIdentifiersForWorkStmt = dbConnection.prepareStatement("DELETE FROM grouped_work_primary_identifiers where grouped_work_id = ?");
				addPrimaryIdentifierToSecondaryIdentifierRefStmt = dbConnection.prepareStatement("INSERT INTO grouped_work_primary_to_secondary_id_ref (primary_identifier_id, secondary_identifier_id) VALUES (?, ?) ");

				getSecondaryIdentifiersForPrimaryIdentifier = dbConnection.prepareStatement("SELECT grouped_work_identifiers.id, type, identifier from grouped_work_identifiers inner join grouped_work_primary_to_secondary_id_ref on grouped_work_identifiers.id = secondary_identifier_id where primary_identifier_id = ?",  ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				getSecondaryIdentifiersForGroupedWork = dbConnection.prepareStatement("SELECT identifier_id from grouped_work_identifiers_ref WHERE grouped_work_id = ?");
				removeSecondaryIdentifierFromPrimaryIdentifier = dbConnection.prepareStatement("DELETE FROM grouped_work_primary_to_secondary_id_ref WHERE secondary_identifier_id = ? and primary_identifier_id = ?");
				if (!fullRegrouping){
					PreparedStatement loadExistingGroupedWorksStmt = dbConnection.prepareStatement("SELECT id, permanent_id from grouped_work");
					ResultSet loadExistingGroupedWorksRS = loadExistingGroupedWorksStmt.executeQuery();
					while (loadExistingGroupedWorksRS.next()){
						existingGroupedWorks.put(loadExistingGroupedWorksRS.getString("permanent_id"), loadExistingGroupedWorksRS.getLong("id"));
					}
					loadExistingGroupedWorksRS.close();
					loadExistingGroupedWorksStmt.close();
				}
				PreparedStatement loadMergedWorksStmt = dbConnection.prepareStatement("SELECT * from merged_grouped_works");
				ResultSet mergedWorksRS = loadMergedWorksStmt.executeQuery();
				while (mergedWorksRS.next()){
					mergedGroupedWorks.put(mergedWorksRS.getString("sourceGroupedWorkId"), mergedWorksRS.getString("destinationGroupedWorkId"));
				}
				mergedWorksRS.close();
				PreparedStatement recordsToNotGroupStmt = dbConnection.prepareStatement("SELECT * from nongrouped_records");
				ResultSet nonGroupedRecordsRS = recordsToNotGroupStmt.executeQuery();
				while (nonGroupedRecordsRS.next()){
					String identifier = nonGroupedRecordsRS.getString("source") + ":" + nonGroupedRecordsRS.getString("recordId");
					recordsToNotGroup.add(identifier.toLowerCase());
				}
				nonGroupedRecordsRS.close();

			}catch (Exception e){
				logger.error("Error setting up prepared statements", e);
		}
	}

	private char getSubfieldIndicatorFromConfig(Ini configIni, String subfieldName) {
		String subfieldString = configIni.get("Reindex", subfieldName);
		char subfield = ' ';
		if (subfieldString.length() > 0)  {
			subfield = subfieldString.charAt(0);
		}
		return subfield;
	}

	protected RecordIdentifier getPrimaryIdentifierFromMarcRecord(Record marcRecord, String recordType){
		RecordIdentifier identifier = null;
		List<VariableField> recordNumberFields = marcRecord.getVariableFields(recordNumberTag);
		//Make sure we only get one ils identifier
		for (VariableField curVariableField : recordNumberFields){
			if (curVariableField instanceof DataField) {
				DataField curRecordNumberField = (DataField)curVariableField;
				Subfield subfieldA = curRecordNumberField.getSubfield('a');
				if (subfieldA != null && (recordNumberPrefix.length() == 0 || subfieldA.getData().length() > recordNumberPrefix.length())) {
					if (curRecordNumberField.getSubfield('a').getData().substring(0, recordNumberPrefix.length()).equals(recordNumberPrefix)) {
						String recordNumber = curRecordNumberField.getSubfield('a').getData().trim();
						identifier = new RecordIdentifier();
						identifier.setValue(recordType, recordNumber);
						break;
					}
				}
			}else{
				//It's a control field
				ControlField curRecordNumberField = (ControlField)curVariableField;
				String recordNumber = curRecordNumberField.getData().trim();
				identifier = new RecordIdentifier();
				identifier.setValue(recordType, recordNumber);
				break;
			}
		}

		//Check to see if the record is an overdrive record
		if (useEContentSubfield){
			boolean allItemsSuppressed = true;

			List<DataField> itemFields = getDataFields(marcRecord, itemTag);
			int numItems = itemFields.size();
			for (DataField itemField : itemFields){
				if (itemField.getSubfield(eContentDescriptor) != null){
					//Check the protection types and sources
					String eContentData = itemField.getSubfield(eContentDescriptor).getData();
					if (eContentData.indexOf(':') >= 0){
						String[] eContentFields = eContentData.split(":");
						String sourceType = eContentFields[0].toLowerCase().trim();
						if (!sourceType.equals("overdrive") && !sourceType.equals("hoopla")){
							allItemsSuppressed = false;
						}
					}else{
						allItemsSuppressed = false;
					}
				}else{
					allItemsSuppressed = false;
				}
			}
			if (numItems == 0){
				allItemsSuppressed = false;
			}
			if (allItemsSuppressed && identifier != null){
				//Don't return a primary identifier for this record (we will suppress the bib and just use OverDrive APIs)
				identifier.setSuppressed(true);
				identifier.setSuppressionReason("All Items suppressed");
			}
		}else{
			//Check the 856 for an overdrive url
			if (identifier != null) {
				List<DataField> linkFields = getDataFields(marcRecord, "856");
				for (DataField linkField : linkFields) {
					if (linkField.getSubfield('u') != null) {
						//Check the url to see if it is from OverDrive or Hoopla
						String linkData = linkField.getSubfield('u').getData().trim();
						if (linkData.matches("(?i)^http://.*?lib\\.overdrive\\.com/ContentDetails\\.htm\\?id=[\\da-f]{8}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{4}-[\\da-f]{12}$")) {
							identifier.setSuppressed(true);
							identifier.setSuppressionReason("OverDrive Title");
						} else if (linkData.matches("(?i)^https://www\\.hoopladigital\\.com/title/\\d+$")) {
							identifier.setSuppressionReason("Hoopla Title");
						}
					}
				}
			}
		}

		if (identifier != null && identifier.isValid()){
			return identifier;
		}else{
			return null;
		}
	}
	protected HashSet<RecordIdentifier> getIdentifiersFromMarcRecord(Record marcRecord) {
		HashSet<RecordIdentifier> identifiers = new HashSet<>();
		//Load identifiers
		List<DataField> identifierFields = getDataFields(marcRecord, new String[]{"020", "024"});
		for (DataField identifierField : identifierFields){
			if (identifierField.getSubfield('a') != null){
				String identifierValue = identifierField.getSubfield('a').getData().trim();
				//Get rid of any extra data at the end of the identifier
				if (identifierValue.indexOf(' ') > 0){
					identifierValue = identifierValue.substring(0, identifierValue.indexOf(' '));
				}
				String identifierType;
				if (identifierField.getTag().equals("020")){
					identifierType = "isbn";
					identifierValue = identifierValue.replaceAll("\\D", "");
					if (identifierValue.length() == 10){
						identifierValue = convertISBN10to13(identifierValue);
					}
				}else{
					identifierType = "upc";
				}
				RecordIdentifier identifier = new RecordIdentifier();
				if (identifierValue == null || identifierValue.length() > 20){
					continue;
				}else if (identifierValue.length() == 0){
					continue;
				}
				identifier.setValue(identifierType, identifierValue);
				if (identifier.isValid()){
					identifiers.add(identifier);
				}
			}
		}
		return identifiers;
	}

	protected List<DataField> getDataFields(Record marcRecord, String tag) {
		List variableFields = marcRecord.getVariableFields(tag);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}

	private List<DataField> getDataFields(Record marcRecord, String[] tags) {
		List variableFields = marcRecord.getVariableFields(tags);
		List<DataField> variableFieldsReturn = new ArrayList<>();
		for (Object variableField : variableFields){
			if (variableField instanceof DataField){
				variableFieldsReturn.add((DataField)variableField);
			}
		}
		return variableFieldsReturn;
	}

	public GroupedWorkBase setupBasicWorkForIlsRecord(Record marcRecord, String loadFormatFrom, char formatSubfield, String specifiedFormatCategory) {
		GroupedWorkBase workForTitle = GroupedWorkFactory.getInstance(-1);

		//Title
		DataField field245 = setWorkTitleBasedOnMarcRecord(marcRecord, workForTitle);
		String groupingFormat = setGroupingCategoryForWork(marcRecord, loadFormatFrom, formatSubfield, specifiedFormatCategory, workForTitle);


		//Author
		setWorkAuthorBasedOnMarcRecord(marcRecord, workForTitle, field245, groupingFormat);
		return workForTitle;
	}

	protected String setGroupingCategoryForWork(Record marcRecord, String loadFormatFrom, char formatSubfield, String specifiedFormatCategory, GroupedWorkBase workForTitle) {
		//Format
		String groupingFormat;
		switch (loadFormatFrom) {
			case "bib":
				String format = getFormatFromBib(marcRecord);
				groupingFormat = categoryMap.get(formatsToGroupingCategory.get(format));
				break;
			case "specified":
				//Use specified format
				groupingFormat = categoryMap.get(specifiedFormatCategory.toLowerCase());
				break;
			default:
				//get format from item
				groupingFormat = getFormatFromItems(marcRecord, formatSubfield);
				break;
		}
		workForTitle.setGroupingCategory(groupingFormat);
		return groupingFormat;
	}

	public void setWorkAuthorBasedOnMarcRecord(Record marcRecord, GroupedWorkBase workForTitle, DataField field245, String groupingFormat) {
		String author = null;
		DataField field100 = (DataField)marcRecord.getVariableField("100");
		DataField field110 = (DataField)marcRecord.getVariableField("110");
		DataField field260 = (DataField)marcRecord.getVariableField("260");
		DataField field710 = (DataField)marcRecord.getVariableField("710");

		//Depending on the format we will promote the use of the 245c
		if (field100 != null && field100.getSubfield('a') != null){
			author = field100.getSubfield('a').getData();
		}else if (field110 != null && field110.getSubfield('a') != null){
			author = field110.getSubfield('a').getData();
			if (field110.getSubfield('b') != null){
				author += " " + field110.getSubfield('b').getData();
			}
		}else if (groupingFormat.equals("book") && field245 != null && field245.getSubfield('c') != null){
			author = field245.getSubfield('c').getData();
			if (author.indexOf(';') > 0){
				author = author.substring(0, author.indexOf(';') -1);
			}
		}else if (field710 != null && field710.getSubfield('a') != null){
			author = field710.getSubfield('a').getData();
		}else if (field260 != null && field260.getSubfield('b') != null){
			author = field260.getSubfield('b').getData();
		}else if (!groupingFormat.equals("book") && field245 != null && field245.getSubfield('c') != null){
			author = field245.getSubfield('c').getData();
			if (author.indexOf(';') > 0){
				author = author.substring(0, author.indexOf(';') -1);
			}
		}
		if (author != null){
			workForTitle.setAuthor(author);
		}
	}

	private DataField setWorkTitleBasedOnMarcRecord(Record marcRecord, GroupedWorkBase workForTitle) {
		DataField field245 = (DataField)marcRecord.getVariableField("245");
		if (field245 != null && field245.getSubfield('a') != null){
			String fullTitle = field245.getSubfield('a').getData();

			char nonFilingCharacters = field245.getIndicator2();
			if (nonFilingCharacters == ' ') nonFilingCharacters = '0';
			int numNonFilingCharacters = 0;
			if (nonFilingCharacters >= '0' && nonFilingCharacters <= '9'){
				numNonFilingCharacters = Integer.parseInt(Character.toString(nonFilingCharacters));
			}

			//Add in subtitle (subfield b as well to avoid problems with gov docs, etc)
			StringBuilder groupingSubtitle = new StringBuilder();
			if (field245.getSubfield('b') != null){
				groupingSubtitle.append(field245.getSubfield('b').getData());
			}

			//Group volumes, seasons, etc. independently
			if (field245.getSubfield('n') != null){
				if (groupingSubtitle.length() > 0) groupingSubtitle.append(" ");
				groupingSubtitle.append(field245.getSubfield('n').getData());
			}
			if (field245.getSubfield('p') != null){
				if (groupingSubtitle.length() > 0) groupingSubtitle.append(" ");
				groupingSubtitle.append(field245.getSubfield('p').getData());
			}

			workForTitle.setTitle(fullTitle, numNonFilingCharacters, groupingSubtitle.toString());
		}
		return field245;
	}

	/**
	 * Add a work to the database
	 *
	 * @param primaryIdentifier The primary identifier we are updating the work for
	 * @param groupedWork       Information about the work itself
	 */
	protected void addGroupedWorkToDatabase(RecordIdentifier primaryIdentifier, GroupedWorkBase groupedWork, boolean primaryDataChanged) {
		//Check to see if we need to ungroup this
		if (recordsToNotGroup.contains(primaryIdentifier.toString().toLowerCase())){
			groupedWork.makeUnique(primaryIdentifier.toString());
		}

		String groupedWorkPermanentId = groupedWork.getPermanentId();

		//Check to see if we are doing a manual merge of the work
		if (mergedGroupedWorks.containsKey(groupedWorkPermanentId)){
			groupedWorkPermanentId = handleMergedWork(groupedWork, groupedWorkPermanentId);
		}

		//Add the work to the database
		numRecordsProcessed++;
		long groupedWorkId = -1;
		try{
			if (existingGroupedWorks.containsKey(groupedWorkPermanentId)){
				//There is an existing grouped record
				groupedWorkId = existingGroupedWorks.get(groupedWorkPermanentId);

				//Mark that the work has been updated
				//Only mark it as updated if the data for the primary identifier has changed
				if (primaryDataChanged) {
					markWorkUpdated(groupedWorkId);
				}

			} else {
				//Need to insert a new grouped record
				insertGroupedWorkStmt.setString(1, groupedWork.getTitle());
				insertGroupedWorkStmt.setString(2, groupedWork.getAuthor());
				insertGroupedWorkStmt.setString(3, groupedWork.getGroupingCategory());
				insertGroupedWorkStmt.setString(4, groupedWorkPermanentId);
				insertGroupedWorkStmt.setLong(5, new Date().getTime() / 1000);

				insertGroupedWorkStmt.executeUpdate();
				ResultSet generatedKeysRS = insertGroupedWorkStmt.getGeneratedKeys();
				if (generatedKeysRS.next()){
					groupedWorkId = generatedKeysRS.getLong(1);
				}
				generatedKeysRS.close();
				numGroupedWorksAdded++;

				//Add to the existing works so we can optimize performance later
				existingGroupedWorks.put(groupedWorkPermanentId, groupedWorkId);
				updatedAndInsertedWorksThisRun.add(groupedWorkId);
			}

			//Update identifiers
			addPrimaryIdentifierForWorkToDB(groupedWorkId, primaryIdentifier);
			//We no longer utilize secondary identifiers for works. We can skip calling this now
			//addIdentifiersForRecordToDB(groupedWorkId, groupedWork.getIdentifiers(), primaryIdentifier);
		}catch (Exception e){
			logger.error("Error adding grouped record to grouped work ", e);
		}

	}

	private String handleMergedWork(GroupedWorkBase groupedWork, String groupedWorkPermanentId) {
		//Handle the merge
		String originalGroupedWorkPermanentId = groupedWorkPermanentId;
		//Override the work id
		groupedWorkPermanentId = mergedGroupedWorks.get(groupedWorkPermanentId);
		groupedWork.overridePermanentId(groupedWorkPermanentId);

		logger.debug("Overriding grouped work " + originalGroupedWorkPermanentId + " with " + groupedWorkPermanentId);

		//Mark that the original was updated
		if (existingGroupedWorks.containsKey(originalGroupedWorkPermanentId)) {
			//There is an existing grouped record
			long originalGroupedWorkId = existingGroupedWorks.get(originalGroupedWorkPermanentId);

			//Make sure we mark the original work as updated so it can be removed from the index next time around
			markWorkUpdated(originalGroupedWorkId);

			//Remove the identifiers for the work.
			//TODO: If we have multiple identifiers for this work, we'll call the delete once for each work.
			//Should we optimize to just call it once and remember that we removed it already?
			try {
				removePrimaryIdentifiersForWorkStmt.setLong(1, originalGroupedWorkId);
				removePrimaryIdentifiersForWorkStmt.executeUpdate();
			} catch (SQLException e) {
				logger.error("Error removing primary identifiers for merged work " + originalGroupedWorkPermanentId + "(" + originalGroupedWorkId + ")");
			}
		}
		return groupedWorkPermanentId;
	}

	private HashSet<Long> updatedAndInsertedWorksThisRun = new HashSet<>();
	private void markWorkUpdated(long groupedWorkId) {
		//Optimize to not continually mark the same works as updateed
		if (!updatedAndInsertedWorksThisRun.contains(groupedWorkId)) {
			try {
				updateDateUpdatedForGroupedWorkStmt.setLong(1, new Date().getTime() / 1000);
				updateDateUpdatedForGroupedWorkStmt.setLong(2, groupedWorkId);
				updateDateUpdatedForGroupedWorkStmt.executeUpdate();
				updatedAndInsertedWorksThisRun.add(groupedWorkId);
			} catch (Exception e) {
				logger.error("Error updating date updated for grouped work ", e);
			}
		}
	}

	private void addPrimaryIdentifierForWorkToDB(long groupedWorkId, RecordIdentifier primaryIdentifier) {
		//Optimized to not delete and remove the primary identifier if it hasn't changed.  Just updates the grouped_work_id.
		try {
			//This statement will either add the primary key or update the work id if it already exits
			addPrimaryIdentifierForWorkStmt.setLong(1, groupedWorkId);
			addPrimaryIdentifierForWorkStmt.setString(2, primaryIdentifier.getType());
			addPrimaryIdentifierForWorkStmt.setString(3, primaryIdentifier.getIdentifier());
			addPrimaryIdentifierForWorkStmt.executeUpdate();
			ResultSet primaryIdentifierRS = addPrimaryIdentifierForWorkStmt.getGeneratedKeys();
			primaryIdentifierRS.next();
			primaryIdentifier.setIdentifierId(primaryIdentifierRS.getLong(1));
			primaryIdentifierRS.close();
		} catch (SQLException e) {
			logger.error("Error adding primary identifier to grouped work " + groupedWorkId + " " + primaryIdentifier.toString(), e);
		}
	}

	/*private void addIdentifiersForRecordToDB(long groupedWorkId, HashSet<RecordIdentifier> identifiers, RecordIdentifier primaryIdentifier) throws SQLException {
		//Get a list of all secondary identifiers for the primary identifier
		getSecondaryIdentifiersForPrimaryIdentifier.setLong(1, primaryIdentifier.getIdentifierId());
		ResultSet secondaryIdentifiersForPrimaryIdentifier = getSecondaryIdentifiersForPrimaryIdentifier.executeQuery();
		HashMap<String, Long> existingSecondaryIdentifiers = new HashMap<String, Long>();
		while (secondaryIdentifiersForPrimaryIdentifier.next()){
			existingSecondaryIdentifiers.put(secondaryIdentifiersForPrimaryIdentifier.getString("type") + ":" + secondaryIdentifiersForPrimaryIdentifier.getString("identifier").toUpperCase(), secondaryIdentifiersForPrimaryIdentifier.getLong("id"));
		}

		//Get a list of all secondary identifiers for the grouped work
		getSecondaryIdentifiersForGroupedWork.setLong(1, groupedWorkId);
		ResultSet secondaryIdentifiersForGroupedWork = getSecondaryIdentifiersForGroupedWork.executeQuery();
		ArrayList<Long> existingSecondaryIdentifiersForWork = new ArrayList<Long>();
		while (secondaryIdentifiersForGroupedWork.next()){
			existingSecondaryIdentifiersForWork.add(secondaryIdentifiersForGroupedWork.getLong("identifier_id"));
		}

		//Loop through currently detected secondary identifiers
		for (RecordIdentifier secondaryIdentifier : identifiers) {
			String key = secondaryIdentifier.toString();
			if (!existingSecondaryIdentifiers.containsKey(key)){
				//If the identifier is not in the list of identifiers for the primary identifier add it to the database
				insertNewSecondaryIdentifier(secondaryIdentifier);
				addPrimaryToSecondaryReferences(primaryIdentifier, secondaryIdentifier);
			}else{
				//If the identifier is in the list of identifiers, remove it.
				secondaryIdentifier.setIdentifierId(existingSecondaryIdentifiers.get(key));
				existingSecondaryIdentifiers.remove(key);
			}
			//If the secondary identifier is not attached to the work, do so now.
			if (!existingSecondaryIdentifiersForWork.contains(secondaryIdentifier.getIdentifierId())){
				addSecondaryIdentifierToGroupedWork(groupedWorkId, secondaryIdentifier);
			}
		}
		//After processing all identifiers, delete any remaining in the list loaded from the database for this primary id
		//Do not delete from grouped works because it could be valid based on another primary id
		for (Long curIdentifierId : existingSecondaryIdentifiers.values()){
			removeSecondaryIdentifierFromPrimaryIdentifier.setLong(1, curIdentifierId);
			removeSecondaryIdentifierFromPrimaryIdentifier.setLong(2, primaryIdentifier.getIdentifierId());
			removeSecondaryIdentifierFromPrimaryIdentifier.executeUpdate();
		}
	}*/

	/*private void addPrimaryToSecondaryReferences(RecordIdentifier primaryIdentifier, RecordIdentifier curIdentifier) throws SQLException {
		//add a reference between the primary identifier and secondary identifiers.
		addPrimaryIdentifierToSecondaryIdentifierRefStmt.setLong(1, primaryIdentifier.getIdentifierId());
		addPrimaryIdentifierToSecondaryIdentifierRefStmt.setLong(2, curIdentifier.getIdentifierId());
		addPrimaryIdentifierToSecondaryIdentifierRefStmt.executeUpdate();
	}*/

	/*private void addSecondaryIdentifierToGroupedWork(long groupedWorkId, RecordIdentifier curIdentifier) {
		//Add the identifier reference
		try{
			addIdentifierToGroupedWorkStmt.setLong(1, groupedWorkId);
			addIdentifierToGroupedWorkStmt.setLong(2, curIdentifier.getIdentifierId());
			addIdentifierToGroupedWorkStmt.executeUpdate();
			curIdentifier.addRelatedGroupedWork(groupedWorkId);
		}catch (SQLException e){
			logger.error("Error adding identifier " + curIdentifier.getType() + " - " + curIdentifier.getIdentifier() + " identifierId " + curIdentifier.getIdentifierId() + " to grouped work " + groupedWorkId, e);
		}
	}*/

	/*private void insertNewSecondaryIdentifier(RecordIdentifier curIdentifier) throws SQLException {
		//This is a brand new identifier
		insertIdentifierStmt.setString(1, curIdentifier.getType());
		insertIdentifierStmt.setString(2, curIdentifier.getIdentifier());
		try{
			insertIdentifierStmt.executeUpdate();
			ResultSet generatedKeys = insertIdentifierStmt.getGeneratedKeys();
			generatedKeys.next();
			long identifierId = generatedKeys.getLong(1);
			generatedKeys.close();
			curIdentifier.setIdentifierId(identifierId);
		}catch (SQLException e){
			if (fullRegrouping){
				logger.warn("Tried to insert a duplicate identifier " + curIdentifier.toString());
			}
			//Get the id of the identifier
			getExistingIdentifierStmt.setString(1, curIdentifier.getType());
			getExistingIdentifierStmt.setString(2, curIdentifier.getIdentifier());
			ResultSet identifierIdRs = getExistingIdentifierStmt.executeQuery();
			if (identifierIdRs.next()){
				curIdentifier.setIdentifierId(identifierIdRs.getLong(1));
			}
			identifierIdRs.close();
		}
	}*/

	public void processRecord(RecordIdentifier primaryIdentifier, String title, String subtitle, String author, String format, HashSet<RecordIdentifier>identifiers, boolean primaryDataChanged){
		GroupedWorkBase groupedWork = GroupedWorkFactory.getInstance(-1);

		//Replace & with and for better matching
		groupedWork.setTitle(title, 0, subtitle);

		if (author != null){
			groupedWork.setAuthor(author);
		}

		if (format.equalsIgnoreCase("audiobook")){
			groupedWork.setGroupingCategory("book");
		}else if (format.equalsIgnoreCase("ebook")){
			groupedWork.setGroupingCategory("book");
		}else if (format.equalsIgnoreCase("music")){
			groupedWork.setGroupingCategory("music");
		}else if (format.equalsIgnoreCase("video")){
			groupedWork.setGroupingCategory("movie");
		}

		groupedWork.setIdentifiers(identifiers);

		addGroupedWorkToDatabase(primaryIdentifier, groupedWork, primaryDataChanged);
	}

	private String getFormatFromItems(Record record, char formatSubfield) {
		List<DataField> itemFields = getDataFields(record, itemTag);
		for (DataField itemField : itemFields) {
			if (itemField.getSubfield(formatSubfield) != null) {
				String originalFormat = itemField.getSubfield(formatSubfield).getData().toLowerCase();
				String format = translateValue("format_group", originalFormat);
				if (format != null && !format.equals(originalFormat)){
					return format;
				}
			}
		}
		//We didn't get a format from the items, check the bib as backup
		String format = getFormatFromBib(record);
		format = categoryMap.get(formatsToGroupingCategory.get(format));
		return format;
	}
	protected String getFormatFromBib(Record record) {
		//Check to see if the title is eContent based on the 989 field
		if (useEContentSubfield) {
			List<DataField> itemFields = getDataFields(record, itemTag);
			for (DataField itemField : itemFields) {
				if (itemField.getSubfield(eContentDescriptor) != null) {
					//The record is some type of eContent.  For this purpose, we don't care what type.
					return "eContent";
				}
			}
		}

		String leader = record.getLeader().toString();
		char leaderBit;
		ControlField fixedField = (ControlField) record.getVariableField("008");
		char formatCode;

		// check for music recordings quickly so we can figure out if it is music
		// for category (need to do here since checking what is on the Compact
		// Disc/Phonograph, etc is difficult).
		if (leader.length() >= 6) {
			leaderBit = leader.charAt(6);
			switch (Character.toUpperCase(leaderBit)) {
				case 'J':
					return "MusicRecording";
			}
		}

		// check for playaway in 260|b
		DataField sysDetailsNote = (DataField) record.getVariableField("260");
		if (sysDetailsNote != null) {
			if (sysDetailsNote.getSubfield('b') != null) {
				String sysDetailsValue = sysDetailsNote.getSubfield('b').getData().toLowerCase();
				if (sysDetailsValue.contains("playaway")) {
					return "Playaway";
				}
			}
		}

		// Check for formats in the 538 field
		DataField sysDetailsNote2 = (DataField) record.getVariableField("538");
		if (sysDetailsNote2 != null) {
			if (sysDetailsNote2.getSubfield('a') != null) {
				String sysDetailsValue = sysDetailsNote2.getSubfield('a').getData().toLowerCase();
				if (sysDetailsValue.contains("playaway")) {
					return "Playaway";
				} else if (sysDetailsValue.contains("bluray")
						|| sysDetailsValue.contains("blu-ray")) {
					return "Blu-ray";
				} else if (sysDetailsValue.contains("dvd")) {
					return "DVD";
				} else if (sysDetailsValue.contains("vertical file")) {
					return "VerticalFile";
				}
			}
		}

		// Check for formats in the 500 tag
		DataField noteField = (DataField) record.getVariableField("500");
		if (noteField != null) {
			if (noteField.getSubfield('a') != null) {
				String noteValue = noteField.getSubfield('a').getData().toLowerCase();
				if (noteValue.contains("vertical file")) {
					return "VerticalFile";
				}
			}
		}

		// Check for large print book (large format in 650, 300, or 250 fields)
		// Check for blu-ray in 300 fields
		DataField edition = (DataField) record.getVariableField("250");
		if (edition != null) {
			if (edition.getSubfield('a') != null) {
				if (edition.getSubfield('a').getData().toLowerCase().contains("large type")) {
					return "LargePrint";
				}
			}
		}

		List<DataField> physicalDescription = getDataFields(record, "300");
		if (physicalDescription != null) {
			Iterator<DataField> fieldsIter = physicalDescription.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				@SuppressWarnings("unchecked")
				List<Subfield> subFields = field.getSubfields();
				for (Subfield subfield : subFields) {
					if (subfield.getData().toLowerCase().contains("large type")) {
						return "LargePrint";
					} else if (subfield.getData().toLowerCase().contains("bluray")
							|| subfield.getData().toLowerCase().contains("blu-ray")) {
						return "Blu-ray";
					}
				}
			}
		}
		List<DataField> topicalTerm = getDataFields(record, "650");
		if (topicalTerm != null) {
			Iterator<DataField> fieldsIter = topicalTerm.iterator();
			DataField field;
			while (fieldsIter.hasNext()) {
				field = fieldsIter.next();
				@SuppressWarnings("unchecked")
				List<Subfield> subfields = field.getSubfields();
				for (Subfield subfield : subfields) {
					if (subfield.getData().toLowerCase().contains("large type")) {
						return "LargePrint";
					}
				}
			}
		}

		List<DataField> localTopicalTerm = getDataFields(record, "690");
		if (localTopicalTerm != null) {
			Iterator<DataField> fieldsIterator = localTopicalTerm.iterator();
			DataField field;
			while (fieldsIterator.hasNext()) {
				field = fieldsIterator.next();
				Subfield subfieldA = field.getSubfield('a');
				if (subfieldA != null) {
					if (subfieldA.getData().toLowerCase().contains("seed library")) {
						return "SeedPacket";
					}
				}
			}
		}

		// check the 007 - this is a repeating field
		List<DataField> fields = getDataFields(record, "007");
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
								return "Atlas";
							default:
								return "Map";
						}
					case 'C':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'A':
								return "TapeCartridge";
							case 'B':
								return "ChipCartridge";
							case 'C':
								return "DiscCartridge";
							case 'F':
								return "TapeCassette";
							case 'H':
								return "TapeReel";
							case 'J':
								return "FloppyDisk";
							case 'M':
							case 'O':
								return "CDROM";
							case 'R':
								// Do not return - this will cause anything with an
								// 856 field to be labeled as "Electronic"
								break;
							default:
								return "Software";
						}
						break;
					case 'D':
						return "Globe";
					case 'F':
						return "Braille";
					case 'G':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
							case 'D':
								return "Filmstrip";
							case 'T':
								return "Transparency";
							default:
								return "Slide";
						}
					case 'H':
						return "Microfilm";
					case 'K':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
								return "Collage";
							case 'D':
								return "Drawing";
							case 'E':
								return "Painting";
							case 'F':
								return "Print";
							case 'G':
								return "Photonegative";
							case 'J':
								return "Print";
							case 'L':
								return "Drawing";
							case 'O':
								return "FlashCard";
							case 'N':
								return "Chart";
							default:
								return "Photo";
						}
					case 'M':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'F':
								return "VideoCassette";
							case 'R':
								return "Filmstrip";
							default:
								return "MotionPicture";
						}
					case 'O':
						return "Kit";
					case 'Q':
						return "MusicalScore";
					case 'R':
						return "SensorImage";
					case 'S':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'D':
								if (formatField.getData().length() >= 4) {
									char speed = formatField.getData().toUpperCase().charAt(3);
									if (speed >= 'A' && speed <= 'E') {
										return "Phonograph";
									} else if (speed == 'F') {
										return "CompactDisc";
									} else if (speed >= 'K' && speed <= 'R') {
										return "TapeRecording";
									} else {
										return "SoundDisc";
									}
								} else {
									return "SoundDisc";
								}
							case 'S':
								return "SoundCassette";
							default:
								return "SoundRecording";
						}
					case 'T':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'A':
								return "Book";
							case 'B':
								return "LargePrint";
						}
					case 'V':
						switch (formatField.getData().toUpperCase().charAt(1)) {
							case 'C':
								return "VideoCartridge";
							case 'D':
								return "VideoDisc";
							case 'F':
								return "VideoCassette";
							case 'R':
								return "VideoReel";
							default:
								return "Video";
						}
				}
			}
		}

		// check the Leader at position 6
		if (leader.length() >= 6) {
			leaderBit = leader.charAt(6);
			switch (Character.toUpperCase(leaderBit)) {
				case 'C':
				case 'D':
					return "MusicalScore";
				case 'E':
				case 'F':
					return "Map";
				case 'G':
					// We appear to have a number of items without 007 tags marked as G's.
					// These seem to be Videos rather than Slides.
					// return "Slide");
					return "Video";
				case 'I':
					return "SoundRecording";
				case 'J':
					return "MusicRecording";
				case 'K':
					return "Photo";
				case 'M':
					return "Electronic";
				case 'O':
				case 'P':
					return "Kit";
				case 'R':
					return "PhysicalObject";
				case 'T':
					return "Manuscript";
			}
		}

		if (leader.length() >= 7) {
			// check the Leader at position 7
			leaderBit = leader.charAt(7);
			switch (Character.toUpperCase(leaderBit)) {
				// Monograph
				case 'M':
					return "Book";
				// Serial
				case 'S':
					// Look in 008 to determine what type of Continuing Resource
					if (fixedField != null && fixedField.getData().length() >= 22) {
						formatCode = fixedField.getData().toUpperCase().charAt(21);
						switch (formatCode) {
							case 'N':
								return "Newspaper";
							case 'P':
								return "Journal";
							default:
								return "Serial";
						}
					}
			}
		}
		// Nothing worked!
		return "Unknown";
	}

	public static String convertISBN10to13(String isbn10){
		if (isbn10.length() != 10){
			return null;
		}
		String isbn = "978" + isbn10.substring(0, 9);
		//Calculate the 13 digit checksum
		int sumOfDigits = 0;
		for (int i = 0; i < 12; i++){
			int multiplier = 1;
			if (i % 2 == 1){
				multiplier = 3;
			}
			int curDigit = Integer.parseInt(Character.toString(isbn.charAt(i)));
			sumOfDigits += multiplier * curDigit;
		}
		int modValue = sumOfDigits % 10;
		int checksumDigit;
		if (modValue == 0){
			checksumDigit = 0;
		}else{
			checksumDigit = 10 - modValue;
		}
		return  isbn + Integer.toString(checksumDigit);
	}

	private static HashMap<String, String> formatsToGroupingCategory = new HashMap<>();
	static {
		formatsToGroupingCategory.put("Atlas", "other");
		formatsToGroupingCategory.put("Map", "other");
		formatsToGroupingCategory.put("TapeCartridge", "other");
		formatsToGroupingCategory.put("ChipCartridge", "other");
		formatsToGroupingCategory.put("DiscCartridge", "other");
		formatsToGroupingCategory.put("TapeCassette", "other");
		formatsToGroupingCategory.put("TapeReel", "other");
		formatsToGroupingCategory.put("FloppyDisk", "other");
		formatsToGroupingCategory.put("CDROM", "other");
		formatsToGroupingCategory.put("Software", "other");
		formatsToGroupingCategory.put("Globe", "other");
		formatsToGroupingCategory.put("Braille", "book");
		formatsToGroupingCategory.put("Filmstrip", "movie");
		formatsToGroupingCategory.put("Transparency", "other");
		formatsToGroupingCategory.put("Slide", "other");
		formatsToGroupingCategory.put("Microfilm", "other");
		formatsToGroupingCategory.put("Collage", "other");
		formatsToGroupingCategory.put("Drawing", "other");
		formatsToGroupingCategory.put("Painting", "other");
		formatsToGroupingCategory.put("Print", "other");
		formatsToGroupingCategory.put("Photonegative", "other");
		formatsToGroupingCategory.put("FlashCard", "other");
		formatsToGroupingCategory.put("Chart", "other");
		formatsToGroupingCategory.put("Photo", "other");
		formatsToGroupingCategory.put("MotionPicture", "movie");
		formatsToGroupingCategory.put("Kit", "other");
		formatsToGroupingCategory.put("MusicalScore", "book");
		formatsToGroupingCategory.put("SensorImage", "other");
		formatsToGroupingCategory.put("SoundDisc", "audio");
		formatsToGroupingCategory.put("SoundCassette", "audio");
		formatsToGroupingCategory.put("SoundRecording", "audio");
		formatsToGroupingCategory.put("VideoCartridge", "movie");
		formatsToGroupingCategory.put("VideoDisc", "movie");
		formatsToGroupingCategory.put("VideoCassette", "movie");
		formatsToGroupingCategory.put("VideoReel", "movie");
		formatsToGroupingCategory.put("Video", "movie");
		formatsToGroupingCategory.put("MusicalScore", "book");
		formatsToGroupingCategory.put("MusicRecording", "music");
		formatsToGroupingCategory.put("Electronic", "other");
		formatsToGroupingCategory.put("PhysicalObject", "other");
		formatsToGroupingCategory.put("Manuscript", "book");
		formatsToGroupingCategory.put("eBook", "ebook");
		formatsToGroupingCategory.put("Book", "book");
		formatsToGroupingCategory.put("Newspaper", "book");
		formatsToGroupingCategory.put("Journal", "book");
		formatsToGroupingCategory.put("Serial", "book");
		formatsToGroupingCategory.put("Unknown", "other");
		formatsToGroupingCategory.put("Playaway", "audio");
		formatsToGroupingCategory.put("LargePrint", "book");
		formatsToGroupingCategory.put("Blu-ray", "movie");
		formatsToGroupingCategory.put("DVD", "movie");
		formatsToGroupingCategory.put("VerticalFile", "other");
		formatsToGroupingCategory.put("CompactDisc", "audio");
		formatsToGroupingCategory.put("TapeRecording", "audio");
		formatsToGroupingCategory.put("Phonograph", "audio");
		formatsToGroupingCategory.put("pdf", "ebook");
		formatsToGroupingCategory.put("epub", "ebook");
		formatsToGroupingCategory.put("jpg", "other");
		formatsToGroupingCategory.put("gif", "other");
		formatsToGroupingCategory.put("mp3", "audio");
		formatsToGroupingCategory.put("plucker", "ebook");
		formatsToGroupingCategory.put("kindle", "ebook");
		formatsToGroupingCategory.put("externalLink", "ebook");
		formatsToGroupingCategory.put("externalMP3", "audio");
		formatsToGroupingCategory.put("interactiveBook", "ebook");
		formatsToGroupingCategory.put("overdrive", "ebook");
		formatsToGroupingCategory.put("external_web", "ebook");
		formatsToGroupingCategory.put("external_ebook", "ebook");
		formatsToGroupingCategory.put("external_eaudio", "audio");
		formatsToGroupingCategory.put("external_emusic", "music");
		formatsToGroupingCategory.put("external_evideo", "movie");
		formatsToGroupingCategory.put("text", "ebook");
		formatsToGroupingCategory.put("gifs", "other");
		formatsToGroupingCategory.put("itunes", "audio");
		formatsToGroupingCategory.put("Adobe_EPUB_eBook", "ebook");
		formatsToGroupingCategory.put("Kindle_Book", "ebook");
		formatsToGroupingCategory.put("Microsoft_eBook", "ebook");
		formatsToGroupingCategory.put("OverDrive_WMA_Audiobook", "audio");
		formatsToGroupingCategory.put("OverDrive_MP3_Audiobook", "audio");
		formatsToGroupingCategory.put("OverDrive_Music", "music");
		formatsToGroupingCategory.put("OverDrive_Video", "movie");
		formatsToGroupingCategory.put("OverDrive_Read", "ebook");
		formatsToGroupingCategory.put("OverDrive_Listen", "audio");
		formatsToGroupingCategory.put("Adobe_PDF_eBook", "ebook");
		formatsToGroupingCategory.put("Palm", "ebook");
		formatsToGroupingCategory.put("Mobipocket_eBook", "ebook");
		formatsToGroupingCategory.put("Disney_Online_Book", "ebook");
		formatsToGroupingCategory.put("Open_PDF_eBook", "ebook");
		formatsToGroupingCategory.put("Open_EPUB_eBook", "ebook");
		formatsToGroupingCategory.put("Nook_Periodicals", "ebook");
		formatsToGroupingCategory.put("eContent", "ebook");
		formatsToGroupingCategory.put("SeedPacket", "other");
	}

	private static HashMap<String, String> categoryMap = new HashMap<>();
	static {
		categoryMap.put("other", "book");
		categoryMap.put("book", "book");
		categoryMap.put("ebook", "book");
		categoryMap.put("audio", "book");
		categoryMap.put("music", "music");
		categoryMap.put("movie", "movie");
	}


	public void dumpStats() {
		long totalElapsedTime = new Date().getTime() - startTime;
		long totalElapsedMinutes = totalElapsedTime / (60 * 1000);
		logger.debug("-----------------------------------------------------------");
		logger.debug("Processed " + numRecordsProcessed + " records in " + totalElapsedMinutes + " minutes");
		logger.debug("Created a total of " + numGroupedWorksAdded + " grouped works");
	}

	public void deletePrimaryIdentifier(RecordIdentifier primaryIdentifier) {
		if (fullRegrouping) return;
		try {
			//Delete the previous primary identifiers as needed
			removePrimaryIdentifierStmt.setString(1, primaryIdentifier.getType());
			removePrimaryIdentifierStmt.setString(2, primaryIdentifier.getIdentifier());
			removePrimaryIdentifierStmt.executeUpdate();

			//Also remove the links to the secondary identifiers
			removeIdentifiersForPrimaryIdentifierStmt.setLong(1, primaryIdentifier.getIdentifierId());
			removeIdentifiersForPrimaryIdentifierStmt.executeUpdate();
		} catch (SQLException e) {
			logger.error("Error removing primary identifier from old grouped works " + primaryIdentifier.toString(), e);
		}
	}

	private void loadTranslationMaps(String serverName){
		//Load all translationMaps, first from default, then from the site specific configuration
		File defaultTranslationMapDirectory = new File("../../sites/default/translation_maps");
		File[] defaultTranslationMapFiles = defaultTranslationMapDirectory.listFiles(new FilenameFilter() {
			@Override
			public boolean accept(File dir, String name) {
				return name.endsWith("properties");
			}
		});

		File serverTranslationMapDirectory = new File("../../sites/" + serverName + "/translation_maps");
		File[] serverTranslationMapFiles = serverTranslationMapDirectory.listFiles(new FilenameFilter() {
			@Override
			public boolean accept(File dir, String name) {
				return name.endsWith("properties");
			}
		});

		for (File curFile : defaultTranslationMapFiles){
			String mapName = curFile.getName().replace(".properties", "");
			mapName = mapName.replace("_map", "");
			translationMaps.put(mapName, loadTranslationMap(curFile));
		}
		if (serverTranslationMapFiles != null) {
			for (File curFile : serverTranslationMapFiles) {
				String mapName = curFile.getName().replace(".properties", "");
				mapName = mapName.replace("_map", "");
				translationMaps.put(mapName, loadTranslationMap(curFile));
			}
		}
	}

	private HashMap<String, String> loadTranslationMap(File translationMapFile) {
		Properties props = new Properties();
		try {
			props.load(new FileReader(translationMapFile));
		} catch (IOException e) {
			logger.error("Could not read translation map, " + translationMapFile.getAbsolutePath(), e);
		}
		HashMap<String, String> translationMap = new HashMap<>();
		for (Object keyObj : props.keySet()){
			String key = (String)keyObj;
			translationMap.put(key.toLowerCase(), props.getProperty(key));
		}
		return translationMap;
	}

	HashSet<String> unableToTranslateWarnings = new HashSet<>();
	public String translateValue(String mapName, String value){
		value = value.toLowerCase();
		HashMap<String, String> translationMap = translationMaps.get(mapName);
		String translatedValue;
		if (translationMap == null){
			if (!unableToTranslateWarnings.contains("unable_to_find_" + mapName)){
				logger.error("Unable to find translation map for " + mapName);
				unableToTranslateWarnings.add("unable_to_find_" + mapName);
			}

			translatedValue = value;
		}else{
			if (translationMap.containsKey(value)){
				translatedValue = translationMap.get(value);
			}else{
				if (translationMap.containsKey("*")){
					translatedValue = translationMap.get("*");
				}else{
					String concatenatedValue = mapName + ":" + value;
					if (!unableToTranslateWarnings.contains(concatenatedValue)){
						logger.warn("Could not translate '" + concatenatedValue + "'");
						unableToTranslateWarnings.add(concatenatedValue);
					}
					translatedValue = value;
				}
			}
		}
		if (translatedValue != null){
			translatedValue = translatedValue.trim();
			if (translatedValue.length() == 0){
				translatedValue = null;
			}
		}
		return translatedValue;
	}
}
