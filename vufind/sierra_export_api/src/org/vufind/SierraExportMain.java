package org.vufind;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;

import au.com.bytecode.opencsv.CSVReader;
import au.com.bytecode.opencsv.CSVWriter;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile.Section;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLSession;
import org.apache.commons.codec.binary.Base64;
import org.marc4j.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.MarcFactory;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.impl.SubfieldImpl;

/**
 * Export data to
 * VuFind-Plus
 * User: Mark Noble
 * Date: 10/15/13
 * Time: 8:59 AM
 */
public class SierraExportMain{
	private static Logger logger = Logger.getLogger(SierraExportMain.class);
	private static String serverName;

	private static IndexingProfile indexingProfile;
	private static GroupedWorkIndexer groupedWorkIndexer;
	private static MarcRecordGrouper recordGroupingProcessor;

	private static boolean exportItemHolds = true;
	private static boolean suppressOrderRecordsThatAreReceivedAndCatalogged = false;
	private static boolean suppressOrderRecordsThatAreCatalogged = false;
	private static String orderStatusesToExport;

	private static Long lastSierraExtractTime = null;
	private static Long lastSierraExtractTimeVariableId = null;
	private static String apiBaseUrl = null;

	private static TreeSet<String> allBibsToUpdate = new TreeSet<>();
	private static TreeSet<String> allDeletedIds = new TreeSet<>();

	//Reporting information
	private static long exportLogId;
	private static PreparedStatement addNoteToExportLogStmt;

	public static void main(String[] args){
		serverName = args[0];

		Date startTime = new Date();
		File log4jFile = new File("../../sites/" + serverName + "/conf/log4j.sierra_extract.properties");
		if (log4jFile.exists()){
			PropertyConfigurator.configure(log4jFile.getAbsolutePath());
		}else{
			System.out.println("Could not find log4j configuration " + log4jFile.toString());
		}
		logger.info(startTime.toString() + ": Starting Sierra Extract");

		// Read the base INI file to get information about the server (current directory/cron/config.ini)
		Ini ini = loadConfigFile("config.ini");
		String exportPath = ini.get("Reindex", "marcPath");
		if (exportPath.startsWith("\"")){
			exportPath = exportPath.substring(1, exportPath.length() - 1);
		}
		String exportItemHoldsStr = ini.get("Catalog", "exportItemHolds");
		if (exportItemHoldsStr != null){
			exportItemHolds = exportItemHoldsStr.equalsIgnoreCase("true");
		}
		String suppressOrderRecordsThatAreReceivedAndCataloggedStr = ini.get("Catalog", "suppressOrderRecordsThatAreReceivedAndCatalogged");
		if (suppressOrderRecordsThatAreReceivedAndCataloggedStr != null){
			suppressOrderRecordsThatAreReceivedAndCatalogged = suppressOrderRecordsThatAreReceivedAndCataloggedStr.equalsIgnoreCase("true");
		}
		String suppressOrderRecordsThatAreCataloggedStr = ini.get("Catalog", "suppressOrderRecordsThatAreCatalogged");
		if (suppressOrderRecordsThatAreCataloggedStr != null){
			suppressOrderRecordsThatAreCatalogged = suppressOrderRecordsThatAreCataloggedStr.equalsIgnoreCase("true");
		}

		//Connect to the vufind database
		Connection vufindConn = null;
		try{
			String databaseConnectionInfo = cleanIniValue(ini.get("Database", "database_vufind_jdbc"));
			vufindConn = DriverManager.getConnection(databaseConnectionInfo);
		}catch (Exception e){
			System.out.println("Error connecting to vufind database " + e.toString());
			System.exit(1);
		}
		//Connect to the vufind database
		Connection econtentConn = null;
		try{
			String databaseConnectionInfo = cleanIniValue(ini.get("Database", "database_econtent_jdbc"));
			econtentConn = DriverManager.getConnection(databaseConnectionInfo);
		}catch (Exception e){
			System.out.println("Error connecting to econtent database " + e.toString());
			System.exit(1);
		}

		String profileToLoad = "ils";
		if (args.length > 1){
			profileToLoad = args[1];
		}
		indexingProfile = IndexingProfile.loadIndexingProfile(vufindConn, profileToLoad, logger);

		//Setup other systems we will use
		recordGroupingProcessor = new MarcRecordGrouper(vufindConn, indexingProfile, logger, false);
		groupedWorkIndexer = new GroupedWorkIndexer(serverName, vufindConn, econtentConn, ini, false, false, logger);

		//Start an export log entry
		try {
			logger.info("Creating log entry for index");
			PreparedStatement createLogEntryStatement = vufindConn.prepareStatement("INSERT INTO sierra_api_export_log (startTime, lastUpdate, notes) VALUES (?, ?, ?)", PreparedStatement.RETURN_GENERATED_KEYS);
			createLogEntryStatement.setLong(1, startTime.getTime() / 1000);
			createLogEntryStatement.setLong(2, startTime.getTime() / 1000);
			createLogEntryStatement.setString(3, "Initialization complete");
			createLogEntryStatement.executeUpdate();
			ResultSet generatedKeys = createLogEntryStatement.getGeneratedKeys();
			if (generatedKeys.next()){
				exportLogId = generatedKeys.getLong(1);
			}

			addNoteToExportLogStmt = vufindConn.prepareStatement("UPDATE sierra_api_export_log SET notes = ?, lastUpdate = ? WHERE id = ?");
		} catch (SQLException e) {
			logger.error("Unable to create log entry for record grouping process", e);
			System.exit(0);
		}

		//Process MARC record changes
		getBibsAndItemUpdatesFromSierra(ini, vufindConn);

		//Connect to the sierra database
		String url = ini.get("Catalog", "sierra_db");
		if (url.startsWith("\"")){
			url = url.substring(1, url.length() - 1);
		}
		Connection conn = null;
		try{
			//Open the connection to the database
			conn = DriverManager.getConnection(url);
			orderStatusesToExport = cleanIniValue(ini.get("Reindex", "orderStatusesToExport"));
			if (orderStatusesToExport == null){
				orderStatusesToExport = "o|1";
			}
			exportActiveOrders(exportPath, conn);
			exportDueDates(exportPath, conn);

			exportHolds(conn, vufindConn);

		}catch(Exception e){
			System.out.println("Error: " + e.toString());
			e.printStackTrace();
		}

		boolean updateSucceeded = updateBibs(ini);

		if (updateSucceeded){
			updateLastExportTime(vufindConn, startTime.getTime());
		}

		addNoteToExportLog("Finished exporting sierra data " + new Date().toString());
		long endTime = new Date().getTime();
		long elapsedTime = endTime - startTime.getTime();
		addNoteToExportLog("Elapsed Minutes " + (elapsedTime / 60000));

		try {
			PreparedStatement finishedStatement = vufindConn.prepareStatement("UPDATE sierra_api_export_log SET endTime = ? WHERE id = ?");
			finishedStatement.setLong(1, endTime / 1000);
			finishedStatement.setLong(2, exportLogId);
			finishedStatement.executeUpdate();
		} catch (SQLException e) {
			logger.error("Unable to update hoopla export log with completion time.", e);
		}

		if (conn != null){
			try{
				//Close the connection
				conn.close();
			}catch(Exception e){
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}

		try{
			//Close the connection
			vufindConn.close();
		}catch(Exception e){
			System.out.println("Error closing connection: " + e.toString());
			e.printStackTrace();
		}
		Date currentTime = new Date();
		logger.info(currentTime.toString() + ": Finished Sierra Extract");
	}

	private static void updateLastExportTime(Connection vufindConn, long exportStartTime) {
		try{
			//Update the last extract time
			if (lastSierraExtractTimeVariableId != null) {
				PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setLong(1, exportStartTime);
				updateVariableStmt.setLong(2, lastSierraExtractTimeVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else {
				PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('last_sierra_extract_time', ?)");
				insertVariableStmt.setString(1, Long.toString(exportStartTime));
				insertVariableStmt.executeUpdate();
				insertVariableStmt.close();
			}
			PreparedStatement setRemainingRecordsStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('remaining_sierra_records', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
			setRemainingRecordsStmt.setString(1, "0");
			setRemainingRecordsStmt.executeUpdate();
			setRemainingRecordsStmt.close();
		}catch (Exception e){
			logger.error("There was an error updating the database, not setting last extract time.", e);
		}
	}

	private static void getBibsAndItemUpdatesFromSierra(Ini ini, Connection vufindConn) {
		try {
			PreparedStatement loadLastSierraExtractTimeStmt = vufindConn.prepareStatement("SELECT * from variables WHERE name = 'last_sierra_extract_time'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet lastSierraExtractTimeRS = loadLastSierraExtractTimeStmt.executeQuery();
			if (lastSierraExtractTimeRS.next()) {
				lastSierraExtractTime = lastSierraExtractTimeRS.getLong("value");
				lastSierraExtractTimeVariableId = lastSierraExtractTimeRS.getLong("id");
			}
		}catch (Exception e){
			logger.error("Unable to load last_sierra_extract_time from variables", e);
			return;
		}

		String apiVersion = cleanIniValue(ini.get("Catalog", "api_version"));
		if (apiVersion == null || apiVersion.length() == 0){
			return;
		}
		apiBaseUrl = ini.get("Catalog", "url") + "/iii/sierra-api/v" + apiVersion;

		//Last Update in UTC
		//Add a small buffer to be
		Date lastExtractDate = new Date((lastSierraExtractTime - 120) * 1000);

		Date now = new Date();
		Date yesterday = new Date(now.getTime() - 24 * 60 * 60 * 1000);

		if (lastExtractDate.before(yesterday)){
			logger.warn("Last Extract date was more than 24 hours ago.  Just getting the last 24 hours since we should have a full extract.");
			lastExtractDate = yesterday;
		}

		SimpleDateFormat dateTimeFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
		dateTimeFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		String lastExtractDateTimeFormatted = dateTimeFormatter.format(lastExtractDate);
		SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd");
		dateFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
		String lastExtractDateFormatted = dateFormatter.format(lastExtractDate);
		long updateTime = new Date().getTime() / 1000;
		logger.info("Loading records changed since " + lastExtractDateTimeFormatted);

		try{
			getWorkForPrimaryIdentifierStmt = vufindConn.prepareStatement("SELECT id, grouped_work_id from grouped_work_primary_identifiers where type = ? and identifier = ?");
			deletePrimaryIdentifierStmt = vufindConn.prepareStatement("DELETE from grouped_work_primary_identifiers where id = ?");
			getAdditionalPrimaryIdentifierForWorkStmt = vufindConn.prepareStatement("SELECT * from grouped_work_primary_identifiers where grouped_work_id = ?");
			markGroupedWorkAsChangedStmt = vufindConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = ?");
			deleteGroupedWorkStmt = vufindConn.prepareStatement("DELETE from grouped_work where id = ?");
			getPermanentIdByWorkIdStmt = vufindConn.prepareStatement("SELECT permanent_id from grouped_work WHERE id = ?");
		}catch (Exception e){
			logger.error("Error setting up prepared statements for deleting bibs", e);
		}
		processDeletedBibs(ini, lastExtractDateFormatted, updateTime);
		getNewRecordsFromAPI(ini, lastExtractDateTimeFormatted, updateTime);
		getChangedRecordsFromAPI(ini, lastExtractDateTimeFormatted, updateTime);
		getNewItemsFromAPI(ini, lastExtractDateTimeFormatted);
		getChangedItemsFromAPI(ini, lastExtractDateTimeFormatted);
		getDeletedItemsFromAPI(ini, lastExtractDateFormatted);

	}

	private static boolean updateBibs(Ini ini) {
		boolean hadErrors = false;
		//This section uses the batch method which doesn't work in Sierra because we are limited to 100 exports per hour
	/*	int batchSize = 25;
		boolean hasMoreIdsToProcess = true;
		while (hasMoreIdsToProcess) {
			hasMoreIdsToProcess = false;
			String idsToProcess = "";
			int maxIndex = Math.min(allBibsToUpdate.size(), batchSize);
			for (int i = 0; i < maxIndex; i++) {
				if (idsToProcess.length() > 0){
					idsToProcess += ",";
				}
				String lastId = allBibsToUpdate.last();
				idsToProcess += lastId;
				allBibsToUpdate.remove(lastId);
			}
			if (!updateMarcAndRegroupRecordIds(ini, idsToProcess)){
				hadErrors = true;
			}
			if (allBibsToUpdate.size() > 0){
				hasMoreIdsToProcess = true;
			}
		}*/
		logger.info("Found " + allBibsToUpdate.size() + " bib records that need to be updated with data from Sierra.");
		for (String id : allBibsToUpdate) {
			if (!updateMarcAndRegroupRecordId(ini, id)){
				hadErrors = true;
			}
		}
		return !hadErrors;
	}

	private static void exportHolds(Connection sierraConn, Connection vufindConn) {
		Savepoint startOfHolds = null;
		try {
			logger.info("Starting export of holds");

			//Start a transaction so we can rebuild an entire table
			startOfHolds = vufindConn.setSavepoint();
			vufindConn.setAutoCommit(false);
			vufindConn.prepareCall("TRUNCATE TABLE ils_hold_summary").executeQuery();

			PreparedStatement addIlsHoldSummary = vufindConn.prepareStatement("INSERT INTO ils_hold_summary (ilsId, numHolds) VALUES (?, ?)");

			HashMap<String, Long> numHoldsByBib = new HashMap<>();
			HashMap<String, Long> numHoldsByVolume = new HashMap<>();
			//Export bib level holds
			PreparedStatement bibHoldsStmt = sierraConn.prepareStatement("select count(hold.id) as numHolds, record_type_code, record_num from sierra_view.hold left join sierra_view.record_metadata on hold.record_id = record_metadata.id where record_type_code = 'b' and (status = '0' OR status = 't') GROUP BY record_type_code, record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet bibHoldsRS = bibHoldsStmt.executeQuery();
			while (bibHoldsRS.next()){
				String bibId = bibHoldsRS.getString("record_num");
				bibId = ".b" + bibId + getCheckDigit(bibId);
				Long numHolds = bibHoldsRS.getLong("numHolds");
				numHoldsByBib.put(bibId, numHolds);
			}
			bibHoldsRS.close();

			if (exportItemHolds) {
				//Export item level holds
				PreparedStatement itemHoldsStmt = sierraConn.prepareStatement("select count(hold.id) as numHolds, record_num\n" +
						"from sierra_view.hold \n" +
						"inner join sierra_view.bib_record_item_record_link ON hold.record_id = item_record_id \n" +
						"inner join sierra_view.record_metadata on bib_record_item_record_link.bib_record_id = record_metadata.id \n" +
						"WHERE status = '0' OR status = 't' " +
						"group by record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
				ResultSet itemHoldsRS = itemHoldsStmt.executeQuery();
				while (itemHoldsRS.next()) {
					String bibId = itemHoldsRS.getString("record_num");
					bibId = ".b" + bibId + getCheckDigit(bibId);
					Long numHolds = itemHoldsRS.getLong("numHolds");
					if (numHoldsByBib.containsKey(bibId)) {
						numHoldsByBib.put(bibId, numHolds + numHoldsByBib.get(bibId));
					} else {
						numHoldsByBib.put(bibId, numHolds);
					}
				}
				itemHoldsRS.close();
			}

			//Export volume level holds
			PreparedStatement volumeHoldsStmt = sierraConn.prepareStatement("select count(hold.id) as numHolds, bib_metadata.record_num as bib_num, volume_metadata.record_num as volume_num\n" +
					"from sierra_view.hold \n" +
					"inner join sierra_view.bib_record_volume_record_link ON hold.record_id = volume_record_id \n" +
					"inner join sierra_view.record_metadata as volume_metadata on bib_record_volume_record_link.volume_record_id = volume_metadata.id \n" +
					"inner join sierra_view.record_metadata as bib_metadata on bib_record_volume_record_link.bib_record_id = bib_metadata.id \n" +
					"WHERE status = '0' OR status = 't'\n" +
					"GROUP BY bib_metadata.record_num, volume_metadata.record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet volumeHoldsRS = volumeHoldsStmt.executeQuery();
			while (volumeHoldsRS.next()) {
				String bibId = volumeHoldsRS.getString("bib_num");
				bibId = ".b" + bibId + getCheckDigit(bibId);
				String volumeId = volumeHoldsRS.getString("volume_num");
				volumeId = ".j" + volumeId + getCheckDigit(volumeId);
				Long numHolds = volumeHoldsRS.getLong("numHolds");
				//Do not count these in
				if (numHoldsByBib.containsKey(bibId)) {
					numHoldsByBib.put(bibId, numHolds + numHoldsByBib.get(bibId));
				} else {
					numHoldsByBib.put(bibId, numHolds);
				}
				if (numHoldsByVolume.containsKey(volumeId)) {
					numHoldsByVolume.put(volumeId, numHolds + numHoldsByVolume.get(bibId));
				} else {
					numHoldsByVolume.put(volumeId, numHolds);
				}
			}
			volumeHoldsRS.close();


			for (String bibId : numHoldsByBib.keySet()){
				addIlsHoldSummary.setString(1, bibId);
				addIlsHoldSummary.setLong(2, numHoldsByBib.get(bibId));
				addIlsHoldSummary.executeUpdate();
			}

			for (String volumeId : numHoldsByVolume.keySet()){
				addIlsHoldSummary.setString(1, volumeId);
				addIlsHoldSummary.setLong(2, numHoldsByVolume.get(volumeId));
				addIlsHoldSummary.executeUpdate();
			}

			try {
				vufindConn.commit();
				vufindConn.setAutoCommit(true);
			}catch (Exception e){
				logger.warn("error committing hold updates rolling back", e);
				vufindConn.rollback(startOfHolds);
			}

		} catch (Exception e) {
			logger.error("Unable to export holds from Sierra", e);
			if (startOfHolds != null) {
				try {
					vufindConn.rollback(startOfHolds);
				}catch (Exception e1){
					logger.error("Unable to rollback due to exception", e1);
				}
			}
		}
		logger.info("Finished exporting holds");
	}



	private static PreparedStatement getWorkForPrimaryIdentifierStmt;
	private static PreparedStatement getAdditionalPrimaryIdentifierForWorkStmt;
	private static PreparedStatement deletePrimaryIdentifierStmt;
	private static PreparedStatement markGroupedWorkAsChangedStmt;
	private static PreparedStatement deleteGroupedWorkStmt;
	private static PreparedStatement getPermanentIdByWorkIdStmt;
	private static void processDeletedBibs(Ini ini, String lastExtractDateFormatted, long updateTime) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process deleted records since " + lastExtractDateFormatted);

		int bufferSize = 250;
		boolean hasMoreRecords = true;
		long offset = 0;
		int numDeletions = 0;
		while (hasMoreRecords){
			hasMoreRecords = false;
			String url = apiBaseUrl + "/bibs/?deletedDate=[" + lastExtractDateFormatted + ",]&fields=id&deleted=true&limit=" + bufferSize;
			if (offset > 0){
				url += "&offset=" + offset;
			}
			JSONObject deletedRecords = callSierraApiURL(ini, apiBaseUrl, url, false);

			if (deletedRecords != null) {
				try {
					JSONArray entries = deletedRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						String id = curBib.getString("id");
						allDeletedIds.add(id);
					}
					if (deletedRecords.getLong("total") >= bufferSize){
						offset += deletedRecords.getLong("total");
						hasMoreRecords = true;
					}
				}catch (Exception e){
					logger.error("Error processing deleted bibs", e);
				}
			}
		}


		if (allDeletedIds.size() > 0){
			for (String id : allDeletedIds) {
				id = ".b" + id + getCheckDigit(id);
				deleteRecord(updateTime, id);
				numDeletions++;
			}
			addNoteToExportLog("Finished processing deleted records, deleted " + numDeletions);
		}else{
			addNoteToExportLog("No deleted records found");
		}
	}

	private static void deleteRecord(long updateTime, String id) {
		try {
			//Check to see if the identifier is in the grouped work primary identifiers table
			getWorkForPrimaryIdentifierStmt.setString(1, indexingProfile.name);
			getWorkForPrimaryIdentifierStmt.setString(2, id);
			ResultSet getWorkForPrimaryIdentifierRS = getWorkForPrimaryIdentifierStmt.executeQuery();
			if (getWorkForPrimaryIdentifierRS.next()) {
				Long groupedWorkId = getWorkForPrimaryIdentifierRS.getLong("grouped_work_id");
				Long primaryIdentifierId = getWorkForPrimaryIdentifierRS.getLong("id");
				//Delete the primary identifier
				deletePrimaryIdentifierStmt.setLong(1, primaryIdentifierId);
				deletePrimaryIdentifierStmt.executeUpdate();
				//Check to see if there are other identifiers for this work
				getAdditionalPrimaryIdentifierForWorkStmt.setLong(1, groupedWorkId);
				ResultSet getAdditionalPrimaryIdentifierForWorkRS = getAdditionalPrimaryIdentifierForWorkStmt.executeQuery();
				if (getAdditionalPrimaryIdentifierForWorkRS.next()) {
					//There are additional records for this work, just need to mark that it needs indexing again
					markGroupedWorkAsChangedStmt.setLong(1, updateTime);
					markGroupedWorkAsChangedStmt.setLong(2, groupedWorkId);
					markGroupedWorkAsChangedStmt.executeUpdate();
				} else {
					//The grouped work no longer exists
					//Get the permanent id
					getPermanentIdByWorkIdStmt.setLong(1, groupedWorkId);
					ResultSet getPermanentIdByWorkIdRS = getPermanentIdByWorkIdStmt.executeQuery();
					if (getPermanentIdByWorkIdRS.next()) {
						String permanentId = getPermanentIdByWorkIdRS.getString("permanent_id");
						//Delete the work from solr
						groupedWorkIndexer.deleteRecord(permanentId);

						//Delete the work from the database?
						//TODO: Should we do this or leave a record if it was linked to lists, reading history, etc?
						//regular indexer deletes them too
						deleteGroupedWorkStmt.setLong(1, groupedWorkId);
						deleteGroupedWorkStmt.executeUpdate();
					}

				}
			}//If not true, already deleted skip this
		} catch (Exception e) {
			logger.error("Error processing deleted bibs", e);
		}
	}

	private static void getChangedRecordsFromAPI(Ini ini, String lastExtractDateFormatted, long updateTime) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process records changed since " + lastExtractDateFormatted);
		int bufferSize = 1000;
		boolean hasMoreRecords = true;
		int numChangedRecords = 0;
		int numSuppressedRecords = 0;
		int recordOffset = 50000;
		long firstRecordIdToLoad = 1;
		while (hasMoreRecords) {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/bibs/?updatedDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,suppressed&limit=" + bufferSize;
			if (firstRecordIdToLoad > 1){
				url += "&id=[" + firstRecordIdToLoad + ",]";
			}
			JSONObject createdRecords = callSierraApiURL(ini, apiBaseUrl, url, false);
			if (createdRecords != null){
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					int lastId = 0;
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						boolean isSuppressed = false;
						if (curBib.has("suppressed")){
							isSuppressed = curBib.getBoolean("suppressed");
						}
						lastId = curBib.getInt("id");
						if (isSuppressed){
							String id = curBib.getString("id");
							allDeletedIds.add(id);
							id = ".b" + id + getCheckDigit(id);
							deleteRecord(updateTime, id);
							numSuppressedRecords++;
						}else {
							allBibsToUpdate.add(curBib.getString("id"));
							numChangedRecords++;
						}
					}
					if (createdRecords.getLong("total") >= bufferSize){
						hasMoreRecords = true;
					}
					if (entries.length() >= bufferSize){
						firstRecordIdToLoad = lastId + 1;
					}else{
						firstRecordIdToLoad += recordOffset;
					}
					//Get the grouped work id for the new bib
				}catch (Exception e){
					logger.error("Error processing changed bibs", e);
				}
			}else{
				addNoteToExportLog("No changed records found");
			}
		}
		addNoteToExportLog("Finished processing changed records, there were " + numChangedRecords + " changed records and " + numSuppressedRecords + " suppressed records");
	}

	private static void getNewRecordsFromAPI(Ini ini, String lastExtractDateFormatted, long updateTime) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process records created since " + lastExtractDateFormatted);
		int bufferSize = 1000;
		boolean hasMoreRecords = true;
		long offset = 0;
		int numNewRecords = 0;
		int numSuppressedRecords = 0;

		while (hasMoreRecords) {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/bibs/?createdDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,suppressed&limit=" + bufferSize;
			if (offset > 0){
				url += "&offset=" + offset;
			}
			JSONObject createdRecords = callSierraApiURL(ini, apiBaseUrl, url, false);
			if (createdRecords != null){
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						boolean isSuppressed = false;
						if (curBib.has("suppressed")){
							isSuppressed = curBib.getBoolean("suppressed");
						}
						if (isSuppressed){
							String id = curBib.getString("id");
							allDeletedIds.add(id);
							id = ".b" + id + getCheckDigit(id);
							deleteRecord(updateTime, id);
							numSuppressedRecords++;
						}else {
							allBibsToUpdate.add(curBib.getString("id"));
							numNewRecords++;
						}
					}
					if (createdRecords.getLong("total") >= bufferSize){
						offset += createdRecords.getLong("total");
						hasMoreRecords = true;
					}
					//Get the grouped work id for the new bib
				}catch (Exception e){
					logger.error("Error processing newly created bibs", e);
				}
			}else{
				addNoteToExportLog("No newly created records found");
			}
		}
		addNoteToExportLog("Finished processing newly created records " + numNewRecords + " were new and " + numSuppressedRecords + " were suppressed");
	}

	private static void getNewItemsFromAPI(Ini ini, String lastExtractDateFormatted) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process items created since " + lastExtractDateFormatted);
		int bufferSize = 1000;
		boolean hasMoreRecords = true;
		long offset = 0;
		int numNewRecords = 0;
		while (hasMoreRecords) {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/items/?createdDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize;
			if (offset > 0){
				url += "&offset=" + offset;
			}
			JSONObject createdRecords = callSierraApiURL(ini, apiBaseUrl, url, false);
			if (createdRecords != null){
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						JSONArray bibIds = curBib.getJSONArray("bibIds");
						for (int j = 0; j < bibIds.length(); j++){
							String id = bibIds.getString(j);
							if (!allDeletedIds.contains(id) && !allBibsToUpdate.contains(id)) {
								allBibsToUpdate.add(id);
							}
							numNewRecords++;
						}
					}
					if (createdRecords.getLong("total") >= bufferSize){
						offset += createdRecords.getLong("total");
						hasMoreRecords = true;
					}
					//Get the grouped work id for the new bib
				}catch (Exception e){
					logger.error("Error processing newly created items", e);
				}
			}else{
				addNoteToExportLog("No newly created items found");
			}
		}
		addNoteToExportLog("Finished processing newly created items " + numNewRecords);
	}

	private static void getChangedItemsFromAPI(Ini ini, String lastExtractDateFormatted) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process items updated since " + lastExtractDateFormatted);
		int bufferSize = 1000;
		boolean hasMoreRecords = true;
		int numChangedItems = 0;
		int numNewBibs = 0;
		long firstRecordIdToLoad = 1;
		int recordOffset = 50000;
		while (hasMoreRecords) {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/items/?updatedDate=[" + lastExtractDateFormatted + ",]&deleted=false&fields=id,bibIds&limit=" + bufferSize;
			if (firstRecordIdToLoad > 1){
				url += "&id=[" + firstRecordIdToLoad + ",]";
			}
			JSONObject createdRecords = callSierraApiURL(ini, apiBaseUrl, url, false);
			if (createdRecords != null){
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					int lastId = 0;
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curItem = entries.getJSONObject(i);
						lastId = curItem.getInt("id");
						if (curItem.has("bibIds")) {
							JSONArray bibIds = curItem.getJSONArray("bibIds");
							for (int j = 0; j < bibIds.length(); j++) {
								String id = bibIds.getString(j);
								if (!allDeletedIds.contains(id) && !allBibsToUpdate.contains(id)) {
									allBibsToUpdate.add(id);
									numNewBibs++;
								}
								numChangedItems++;
							}
						}
					}
					if (createdRecords.getLong("total") >= bufferSize){
						hasMoreRecords = true;
					}
					if (entries.length() >= bufferSize){
						firstRecordIdToLoad = lastId + 1;
					}else{
						firstRecordIdToLoad += recordOffset;
					}
					//Get the grouped work id for the new bib
				}catch (Exception e){
					logger.error("Error processing updated items", e);
				}
			}else{
				addNoteToExportLog("No updated items found");
			}
		}
		addNoteToExportLog("Finished processing updated items " + numChangedItems + " this added " + numNewBibs + " bibs to process");
	}

	private static void getDeletedItemsFromAPI(Ini ini, String lastExtractDateFormatted) {
		//Get a list of deleted bibs
		addNoteToExportLog("Starting to process items deleted since " + lastExtractDateFormatted);
		int bufferSize = 1000;
		boolean hasMoreRecords = true;
		long offset = 0;
		int numDeletedItems = 0;
		while (hasMoreRecords) {
			hasMoreRecords = false;
			String url = apiBaseUrl + "/items/?deletedDate=[" + lastExtractDateFormatted + ",]&deleted=true&fields=id,bibIds&limit=" + bufferSize;
			if (offset > 0){
				url += "&offset=" + offset;
			}
			JSONObject createdRecords = callSierraApiURL(ini, apiBaseUrl, url, false);
			if (createdRecords != null){
				try {
					JSONArray entries = createdRecords.getJSONArray("entries");
					for (int i = 0; i < entries.length(); i++) {
						JSONObject curBib = entries.getJSONObject(i);
						JSONArray bibIds = curBib.getJSONArray("bibIds");
						for (int j = 0; j < bibIds.length(); j++){
							String id = bibIds.getString(j);
							if (!allDeletedIds.contains(id) && !allBibsToUpdate.contains(id)) {
								allBibsToUpdate.add(id);
							}
						}
					}
					if (createdRecords.getLong("total") >= bufferSize){
						offset += createdRecords.getLong("total");
						hasMoreRecords = true;
					}
					//Get the grouped work id for the new bib
				}catch (Exception e){
					logger.error("Error processing deleted items", e);
				}
			}else{
				addNoteToExportLog("No deleted items found");
			}
		}
		addNoteToExportLog("Finished processing deleted items found " + numDeletedItems);
	}

	private static MarcFactory marcFactory = MarcFactory.newInstance();
	private static boolean updateMarcAndRegroupRecordId(Ini ini, String id) {
		try {
			JSONObject marcResults = getMarcJSONFromSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/bibs/" + id + "/marc", true);
			if (marcResults != null){

				String leader = marcResults.has("leader") ? marcResults.getString("leader") : "";
				Record marcRecord = marcFactory.newRecord(leader);
				JSONArray fields = marcResults.getJSONArray("fields");
				for (int i = 0; i < fields.length(); i++){
					JSONObject fieldData = fields.getJSONObject(i);
					@SuppressWarnings("unchecked") Iterator<String> tags = (Iterator<String>)fieldData.keys();
					while (tags.hasNext()){
						String tag = tags.next();
						if (fieldData.get(tag) instanceof JSONObject){
							JSONObject fieldDataDetails = fieldData.getJSONObject(tag);
							char ind1 = fieldDataDetails.getString("ind1").charAt(0);
							char ind2 = fieldDataDetails.getString("ind2").charAt(0);
							DataField dataField = marcFactory.newDataField(tag, ind1, ind2);
							JSONArray subfields = fieldDataDetails.getJSONArray("subfields");
							for (int j = 0; j < subfields.length(); j++){
								JSONObject subfieldData = subfields.getJSONObject(j);
								String subfieldIndicatorStr = (String)subfieldData.keys().next();
								char subfieldIndicator = subfieldIndicatorStr.charAt(0);
								String subfieldValue = subfieldData.getString(subfieldIndicatorStr);
								dataField.addSubfield(marcFactory.newSubfield(subfieldIndicator, subfieldValue));
							}
							marcRecord.addVariableField(dataField);
						}else{
							String fieldValue = fieldData.getString(tag);
							marcRecord.addVariableField(marcFactory.newControlField(tag, fieldValue));
						}
					}
				}

				//Add the identifier
				marcRecord.addVariableField(marcFactory.newDataField(indexingProfile.recordNumberTag, ' ', ' ',  "a", ".b" + id + getCheckDigit(id)));
				//Get Items for the bib record
				getItemsForBib(ini, id, marcRecord);
				RecordIdentifier identifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(marcRecord, indexingProfile.name, indexingProfile.doAutomaticEcontentSuppression);
				File marcFile = indexingProfile.getFileForIlsRecord(identifier.getIdentifier());
				if (!marcFile.getParentFile().exists()) {
					if (!marcFile.getParentFile().mkdirs()) {
						logger.error("Could not create directories for " + marcFile.getAbsolutePath());
					}
				}
				MarcWriter marcWriter = new MarcStreamWriter(new FileOutputStream(marcFile));
				marcWriter.write(marcRecord);
				marcWriter.close();

				//Setup the grouped work for the record.  This will take care of either adding it to the proper grouped work
				//or creating a new grouped work
				if (!recordGroupingProcessor.processMarcRecord(marcRecord, true)) {
					logger.warn(identifier.getIdentifier() + " was suppressed");
				}
			}else{
				logger.error("Error exporting marc record for " + id + " call returned null");
				return false;
			}
		}catch (Exception e){
			logger.error("Error processing newly created bibs", e);
			return false;
		}
		return true;
	}


	private static SimpleDateFormat sierraAPIDateFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
	private static void getItemsForBib(Ini ini, String id, Record marcRecord) {
		//Get a list of all items
		long startTime = new Date().getTime();
		JSONObject itemIds = callSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/items?limit=1000&deleted=false&suppressed=false&fields=id,updatedDate,createdDate,location,status,barcode,callNumber,itemType,fixedFields,varFields&bibIds=" + id, true);
		if (itemIds != null){
			try {
				JSONArray entries = itemIds.getJSONArray("entries");
				logger.debug("finished getting items for " + id + " elapsed time " + (new Date().getTime() - startTime) + "ms found " + entries.length());
				for (int i = 0; i < entries.length(); i++) {
					JSONObject curItem = entries.getJSONObject(i);
					JSONObject fixedFields = curItem.getJSONObject("fixedFields");
					JSONArray varFields = curItem.getJSONArray("varFields");
					String itemId = curItem.getString("id");
					DataField itemField = marcFactory.newDataField(indexingProfile.itemTag, ' ', ' ');
					//Record Number
					if (indexingProfile.itemRecordNumberSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.itemRecordNumberSubfield, ".i" + itemId + getCheckDigit(itemId)));
					}
					//barcode
					if (curItem.has("barcode")){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.barcodeSubfield, curItem.getString("barcode")));
					}
					//location
					if (curItem.has("location") && indexingProfile.locationSubfield != ' '){
						String locationCode = curItem.getJSONObject("location").getString("code");
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.locationSubfield, locationCode));
					}
					//call number (can we get prestamp cutter, poststamp?
					if (curItem.has("callNumber") && indexingProfile.callNumberSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.callNumberSubfield, curItem.getString("callNumber")));
					}
					//status
					if (curItem.has("status")){
						String statusCode = curItem.getJSONObject("status").getString("code");
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.itemStatusSubfield, statusCode));
						if (curItem.getJSONObject("status").has("duedate")){
							Date createdDate = sierraAPIDateFormatter.parse(curItem.getJSONObject("status").getString("duedate"));
							itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, indexingProfile.dueDateFormatter.format(createdDate)));
						}else{
							itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, ""));
						}
					}else{
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dueDateSubfield, ""));
					}
					//total checkouts
					if (fixedFields.has("76") && indexingProfile.totalCheckoutsSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.totalCheckoutsSubfield, fixedFields.getJSONObject("76").getString("value")));
					}
					//last year checkouts
					if (fixedFields.has("110") && indexingProfile.lastYearCheckoutsSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.lastYearCheckoutsSubfield, fixedFields.getJSONObject("110").getString("value")));
					}
					//year to date checkouts
					if (fixedFields.has("109") && indexingProfile.yearToDateCheckoutsSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.yearToDateCheckoutsSubfield, fixedFields.getJSONObject("109").getString("value")));
					}
					//total renewals
					if (fixedFields.has("77") && indexingProfile.totalRenewalsSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.totalRenewalsSubfield, fixedFields.getJSONObject("77").getString("value")));
					}
					//iType
					if (fixedFields.has("61") && indexingProfile.iTypeSubfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.iTypeSubfield, fixedFields.getJSONObject("61").getString("value")));
					}
					//date created
					if (curItem.has("createdDate") && indexingProfile.dateCreatedSubfield != ' '){
						Date createdDate = sierraAPIDateFormatter.parse(curItem.getString("createdDate"));
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.dateCreatedSubfield, indexingProfile.dateCreatedFormatter.format(createdDate)));
					}
					//last check in date
					if (fixedFields.has("68") && indexingProfile.lastCheckinDateSubfield != ' '){
						Date lastCheckin = sierraAPIDateFormatter.parse(fixedFields.getString("68"));
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.lastCheckinDateSubfield, indexingProfile.lastCheckinFormatter.format(lastCheckin)));
					}
					//icode2
					if (fixedFields.has("60") && indexingProfile.iCode2Subfield != ' '){
						itemField.addSubfield(marcFactory.newSubfield(indexingProfile.iCode2Subfield, fixedFields.getJSONObject("60").getString("value")));
					}

					//Process variable fields
					for (int j = 0; j < varFields.length(); j++){
						JSONObject curVarField = varFields.getJSONObject(j);
						String fieldTag = curVarField.getString("fieldTag");
						//volume

						//url

					}
					marcRecord.addVariableField(itemField);
				}

			}catch (Exception e){
				logger.error("Error getting information about items", e);
			}
		}else{
			logger.debug("finished getting items for " + id + " elapsed time " + (new Date().getTime() - startTime) + "ms found none");
		}
	}

	private static boolean updateMarcAndRegroupRecordIds(Ini ini, String ids) {
		try {
			JSONObject marcResults = callSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/bibs/marc?id=" + ids, true);
			if (marcResults != null && marcResults.has("file")){
				String dataFileUrl = marcResults.getString("file");
				String marcData = getMarcFromSierraApiURL(ini, apiBaseUrl, dataFileUrl, true);
				MarcReader marcReader = new MarcPermissiveStreamReader(new ByteArrayInputStream(marcData.getBytes(StandardCharsets.UTF_8)), true, true);
				while (marcReader.hasNext()){
					try {
						Record marcRecord = marcReader.next();
						RecordIdentifier identifier = recordGroupingProcessor.getPrimaryIdentifierFromMarcRecord(marcRecord, indexingProfile.name, indexingProfile.doAutomaticEcontentSuppression);
						File marcFile = indexingProfile.getFileForIlsRecord(identifier.getIdentifier());
						if (!marcFile.getParentFile().exists()) {
							if (!marcFile.getParentFile().mkdirs()) {
								logger.error("Could not create directories for " + marcFile.getAbsolutePath());
							}
						}
						MarcWriter marcWriter = new MarcStreamWriter(new FileOutputStream(marcFile));
						marcWriter.write(marcRecord);
						marcWriter.close();

						//Setup the grouped work for the record.  This will take care of either adding it to the proper grouped work
						//or creating a new grouped work
						if (!recordGroupingProcessor.processMarcRecord(marcRecord, true)) {
							logger.warn(identifier.getIdentifier() + " was suppressed");
						}
					}catch (MarcException mre){
						logger.error("Error loading marc file", mre);
					}
				}
			}else{
				logger.error("Error exporting marc records for " + ids + " marc results did not have a file");
				return false;
			}
		}catch (Exception e){
			logger.error("Error processing newly created bibs", e);
			return false;
		}
		return true;
	}

	private static void getChangedItemsFromAPI(Ini ini, Connection vufindConn, String exportPath, String dateUpdated, SimpleDateFormat dateFormatter, long updateTime) {
		//Get the time the last extract was done
		try{
			addNoteToExportLog("Starting to load changed records from Sierra using the API");

			Long exportStartTime = new Date().getTime() / 1000;

			HashSet<String> itemsThatNeedToBeProcessed = new HashSet<>();
			File changedItemsFile = new File(exportPath + "/changed_items_to_process.csv");
			if (changedItemsFile.exists()){
				BufferedReader changedItemsReader = new BufferedReader(new FileReader(changedItemsFile));
				String curLine = changedItemsReader.readLine();
				while (curLine != null){
					itemsThatNeedToBeProcessed.add(curLine);
					curLine = changedItemsReader.readLine();
				}
				changedItemsReader.close();
			}

			//Only mark records as changed
			boolean errorUpdatingDatabase = false;
			if (lastSierraExtractTime != null){
				//Extract the ids of all records that have changed.
				long firstRecordIdToLoad = 1;
				boolean moreToRead = true;
				int bufferSize = 1000;

				//Get a list of everything that has changed, loading a minimum of data so we can get the ids as quickly as possible
				int recordOffset = 50000;
				while (moreToRead){
					//long lastRecord = firstRecordIdToLoad + recordOffset;
					logger.info("Loading items with changes from " + firstRecordIdToLoad);
					JSONObject changedRecords = null;
					int numTries = 0;
					while ((numTries == 0 || lastCallTimedOut) && numTries < 5){
						numTries++;
						//Try loading again
						if (lastCallTimedOut) {
							logger.info(" - timed out, retrying");
							Thread.sleep(2500);
						}
						//changedRecords = callSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/items/?updatedDate=[" + dateUpdated + ",]&limit=" + bufferSize + "&fields=id,bibIds,location,status,fixedFields&deleted=false&suppressed=false&id=[" + firstRecordIdToLoad + "," + (lastRecord > 999999999 ? "" : lastRecord) + "]", false);
						changedRecords = callSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/items/?updatedDate=[" + dateUpdated + ",]&limit=" + bufferSize + "&fields=id,bibIds&deleted=false&id=[" + firstRecordIdToLoad + ",]", false);
					}
					if (lastCallTimedOut){
						logger.error(" - call " + numTries + " timed out, data will be lost!");
					}
					int numChangedIds = 0;
					if (changedRecords != null && changedRecords.has("entries")){
						//int numUpdates = changedRecords.getInt("total");
						JSONArray changedIds = changedRecords.getJSONArray("entries");
						numChangedIds = changedIds.length();
						logger.info(" - Found " + numChangedIds + " changes");
						int lastId = 0;
						for(int i = 0; i < numChangedIds; i++){
							JSONObject curItem = changedIds.getJSONObject(i);
							String itemId = curItem.getString("id");
							itemsThatNeedToBeProcessed.add(itemId);
							lastId = Integer.parseInt(itemId) + 1;
							logger.debug("   item " + itemId + " changed");
						}
						if (numChangedIds >= bufferSize){
							firstRecordIdToLoad = lastId + 1;
						}else{
							firstRecordIdToLoad += recordOffset;
						}
					}else{
						logger.info(" - Found no changes");
						firstRecordIdToLoad += recordOffset;
					}
					//If we have the same number of records as the buffer that is ok.  Sierra does not return the correct total anymore
					moreToRead = (numChangedIds >= bufferSize); // || firstRecordIdToLoad <= 999999999;
				}

			}

			//Write any records that still haven't been processed
			BufferedWriter itemsToProcessWriter = new BufferedWriter(new FileWriter(changedItemsFile, false));
			for (String changedItem : itemsThatNeedToBeProcessed){
				itemsToProcessWriter.write(changedItem + "\r\n");
			}
			itemsToProcessWriter.flush();
			itemsToProcessWriter.close();
			//logger.warn(itemsThatNeedToBeProcessed.size() + " items remain to be processed");

			if (!errorUpdatingDatabase) {
				//Update the last extract time
				if (lastSierraExtractTimeVariableId != null) {
					PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
					updateVariableStmt.setLong(1, exportStartTime);
					updateVariableStmt.setLong(2, lastSierraExtractTimeVariableId);
					updateVariableStmt.executeUpdate();
					updateVariableStmt.close();
				} else {
					PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('last_sierra_extract_time', ?)");
					insertVariableStmt.setString(1, Long.toString(exportStartTime));
					insertVariableStmt.executeUpdate();
					insertVariableStmt.close();
				}
				PreparedStatement setRemainingRecordsStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('remaining_sierra_records', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
				setRemainingRecordsStmt.setString(1, Long.toString(itemsThatNeedToBeProcessed.size()));
				setRemainingRecordsStmt.executeUpdate();
				setRemainingRecordsStmt.close();
			}else{
				logger.error("There was an error updating the database, not setting last extract time.");
			}
		} catch (Exception e){
			logger.error("Error loading changed records from Sierra API", e);
			System.exit(1);
		}
		addNoteToExportLog("Finished loading changed records from Sierra API");
	}

	private static void updateMarc(String curBibId, ArrayList<ItemChangeInfo> itemChangeInfo) {
		//Load the existing marc record from file
		try {
			File marcFile = indexingProfile.getFileForIlsRecord(curBibId);
			if (marcFile.exists()) {
				FileInputStream inputStream = new FileInputStream(marcFile);
				MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
				if (marcReader.hasNext()) {
					Record marcRecord = marcReader.next();
					inputStream.close();

					//Loop through all item fields to see what has changed
					List<VariableField> itemFields = marcRecord.getVariableFields(indexingProfile.itemTag);
					for (VariableField itemFieldVar : itemFields) {
						DataField itemField = (DataField) itemFieldVar;
						if (itemField.getSubfield(indexingProfile.itemRecordNumberSubfield) != null) {
							String itemRecordNumber = itemField.getSubfield(indexingProfile.itemRecordNumberSubfield).getData();
							//Update the items
							for (ItemChangeInfo curItem : itemChangeInfo) {
								//Find the correct item
								if (itemRecordNumber.equals(curItem.getItemId())) {
									itemField.getSubfield(indexingProfile.locationSubfield).setData(curItem.getLocation());
									itemField.getSubfield(indexingProfile.itemStatusSubfield).setData(curItem.getStatus());
									if (curItem.getDueDate() == null) {
										if (itemField.getSubfield(indexingProfile.dueDateSubfield) != null) {
											if (indexingProfile.dueDateFormat.contains("-")){
												itemField.getSubfield(indexingProfile.dueDateSubfield).setData("  -  -  ");
											} else {
												itemField.getSubfield(indexingProfile.dueDateSubfield).setData("      ");
											}
										}
									} else {
										if (itemField.getSubfield(indexingProfile.dueDateSubfield) == null) {
											itemField.addSubfield(new SubfieldImpl(indexingProfile.dueDateSubfield, curItem.getDueDate()));
										} else {
											itemField.getSubfield(indexingProfile.dueDateSubfield).setData(curItem.getDueDate());
										}
									}
									if (indexingProfile.lastCheckinDateSubfield != ' ') {
										if (curItem.getLastCheckinDate() == null) {
											if (itemField.getSubfield(indexingProfile.lastCheckinDateSubfield) != null) {
												if (indexingProfile.lastCheckinFormat.contains("-")) {
													itemField.getSubfield(indexingProfile.lastCheckinDateSubfield).setData("  -  -  ");
												} else {
													itemField.getSubfield(indexingProfile.lastCheckinDateSubfield).setData("      ");
												}
											}
										} else {
											if (itemField.getSubfield(indexingProfile.lastCheckinDateSubfield) == null) {
												itemField.addSubfield(new SubfieldImpl(indexingProfile.lastCheckinDateSubfield, curItem.getLastCheckinDate()));
											} else {
												itemField.getSubfield(indexingProfile.lastCheckinDateSubfield).setData(curItem.getLastCheckinDate());
											}
										}
									}
								}
							}
						}
					}

					//Write the new marc record
					MarcWriter writer = new MarcStreamWriter(new FileOutputStream(marcFile, false), true);
					writer.write(marcRecord);
					writer.close();
				} else {
					logger.info("Could not read marc record for " + curBibId + " the bib was empty");
				}
			}else{
				logger.debug("Marc Record does not exist for " + curBibId + " it is not part of the main extract yet.");
			}
		}catch (Exception e){
			logger.error("Error updating marc record for bib " + curBibId, e);
		}
	}

	private static void exportDueDates(String exportPath, Connection conn) throws SQLException, IOException {
		addNoteToExportLog("Starting export of due dates");
		String dueDatesSQL = "select record_num, due_gmt from sierra_view.checkout inner join sierra_view.item_view on item_record_id = item_view.id where due_gmt is not null";
		PreparedStatement getDueDatesStmt = conn.prepareStatement(dueDatesSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		ResultSet dueDatesRS = null;
		boolean loadError = false;
		try{
			dueDatesRS = getDueDatesStmt.executeQuery();
		} catch (SQLException e1){
			logger.error("Error loading active orders", e1);
			loadError = true;
		}
		if (!loadError){
			File dueDateFile = new File(exportPath + "/due_dates.csv");
			CSVWriter dueDateWriter = new CSVWriter(new FileWriter(dueDateFile));
			while (dueDatesRS.next()){
				try {
					String recordNum = dueDatesRS.getString("record_num");
					if (recordNum != null){
						String dueDateRaw = dueDatesRS.getString("due_gmt");
						String itemId = ".i" + recordNum + getCheckDigit(recordNum);
						Date dueDate = dueDatesRS.getDate("due_gmt");
						dueDateWriter.writeNext(new String[]{itemId, Long.toString(dueDate.getTime()), dueDateRaw});
					}else{
						logger.warn("No record number found while exporting due dates");
					}
				}catch (Exception e){
					logger.error("Error writing due dates", e);
				}
			}
			dueDateWriter.close();
			dueDatesRS.close();
		}
		addNoteToExportLog("Finished exporting due dates");
	}

	private static void exportActiveOrders(String exportPath, Connection conn) throws SQLException, IOException {
		addNoteToExportLog("Starting export of active orders");
		//Load the orders we had last time
		File orderRecordFile = new File(exportPath + "/active_orders.csv");
		HashMap<String, Integer> existingBibsWithOrders = new HashMap<>();
		readOrdersFile(orderRecordFile, existingBibsWithOrders);

		String[] orderStatusesToExportVals = orderStatusesToExport.split("\\|");
		String orderStatusCodesSQL = "";
		for (String orderStatusesToExportVal : orderStatusesToExportVals){
			if (orderStatusCodesSQL.length() > 0){
				orderStatusCodesSQL += " or ";
			}
			orderStatusCodesSQL += " order_status_code = '" + orderStatusesToExportVal + "'";
		}
		String activeOrderSQL = "select bib_view.record_num as bib_record_num, order_view.record_num as order_record_num, accounting_unit_code_num, order_status_code, copies, location_code, catalog_date_gmt, received_date_gmt " +
				"from sierra_view.order_view " +
				"inner join sierra_view.bib_record_order_record_link on bib_record_order_record_link.order_record_id = order_view.record_id " +
				"inner join sierra_view.bib_view on sierra_view.bib_view.id = bib_record_order_record_link.bib_record_id " +
				"inner join sierra_view.order_record_cmf on order_record_cmf.order_record_id = order_view.id " +
				"where (" + orderStatusCodesSQL + ") and order_view.is_suppressed = 'f' and location_code != 'multi' and ocode4 != 'n'";
		if (suppressOrderRecordsThatAreReceivedAndCatalogged){
			activeOrderSQL += " and (catalog_date_gmt IS NULL or received_date_gmt IS NULL) ";
		}else if (suppressOrderRecordsThatAreCatalogged){
			activeOrderSQL += " and (catalog_date_gmt IS NULL) ";
		}
		PreparedStatement getActiveOrdersStmt = conn.prepareStatement(activeOrderSQL, ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		ResultSet activeOrdersRS = null;
		boolean loadError = false;
		try{
			activeOrdersRS = getActiveOrdersStmt.executeQuery();
		} catch (SQLException e1){
			logger.error("Error loading active orders", e1);
			loadError = true;
		}
		if (!loadError){
			CSVWriter orderRecordWriter = new CSVWriter(new FileWriter(orderRecordFile));
			orderRecordWriter.writeAll(activeOrdersRS, true);
			orderRecordWriter.close();
			activeOrdersRS.close();

			HashMap<String, Integer> updatedBibsWithOrders = new HashMap<>();
			readOrdersFile(orderRecordFile, updatedBibsWithOrders);

			//Check to see which bibs either have new or deleted orders
			for (String bibId : updatedBibsWithOrders.keySet()){
				if (!existingBibsWithOrders.containsKey(bibId)){
					//We didn't have a bib with an order before, update it
					allBibsToUpdate.add(bibId);
				}else{
					if (!updatedBibsWithOrders.get(bibId).equals(existingBibsWithOrders.get(bibId))){
						//Number of orders has changed, we should reindex.
						allBibsToUpdate.add(bibId);
					}
					existingBibsWithOrders.remove(bibId);
				}
			}
			//Now that all updated bibs are processed, look for any that we used to have that no longer exits
			for (String bibId : existingBibsWithOrders.keySet()){
				allBibsToUpdate.add(bibId);
			}
		}
		addNoteToExportLog("Finished exporting active orders");
	}

	private static void readOrdersFile(File orderRecordFile, HashMap<String, Integer> bibsWithOrders) throws IOException {
		if (orderRecordFile.exists()){
			CSVReader orderReader = new CSVReader(new FileReader(orderRecordFile));
			//Skip the header
			orderReader.readNext();
			String[] recordData = orderReader.readNext();
			while (recordData != null){
				if (bibsWithOrders.containsKey(recordData[0])){
					bibsWithOrders.put(recordData[0], bibsWithOrders.get(recordData[0]) + 1);
				}else{
					bibsWithOrders.put(recordData[0], 1);
				}

				recordData = orderReader.readNext();
			}
			orderReader.close();
		}
	}

	private static Ini loadConfigFile(String filename){
		//First load the default config file
		String configName = "../../sites/default/conf/" + filename;
		logger.info("Loading configuration from " + configName);
		File configFile = new File(configName);
		if (!configFile.exists()) {
			logger.error("Could not find configuration file " + configName);
			System.exit(1);
		}

		// Parse the configuration file
		Ini ini = new Ini();
		try {
			ini.load(new FileReader(configFile));
		} catch (InvalidFileFormatException e) {
			logger.error("Configuration file is not valid.  Please check the syntax of the file.", e);
		} catch (FileNotFoundException e) {
			logger.error("Configuration file could not be found.  You must supply a configuration file in conf called config.ini.", e);
		} catch (IOException e) {
			logger.error("Configuration file could not be read.", e);
		}

		//Now override with the site specific configuration
		String siteSpecificFilename = "../../sites/" + serverName + "/conf/" + filename;
		logger.info("Loading site specific config from " + siteSpecificFilename);
		File siteSpecificFile = new File(siteSpecificFilename);
		if (!siteSpecificFile.exists()) {
			logger.error("Could not find server specific config file");
			System.exit(1);
		}
		try {
			Ini siteSpecificIni = new Ini();
			siteSpecificIni.load(new FileReader(siteSpecificFile));
			for (Section curSection : siteSpecificIni.values()){
				for (String curKey : curSection.keySet()){
					//logger.debug("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					//System.out.println("Overriding " + curSection.getName() + " " + curKey + " " + curSection.get(curKey));
					ini.put(curSection.getName(), curKey, curSection.get(curKey));
				}
			}
			//Also load password files if they exist
			String siteSpecificPassword = "../../sites/" + serverName + "/conf/config.pwd.ini";
			logger.info("Loading password config from " + siteSpecificPassword);
			File siteSpecificPasswordFile = new File(siteSpecificPassword);
			if (siteSpecificPasswordFile.exists()) {
				Ini siteSpecificPwdIni = new Ini();
				siteSpecificPwdIni.load(new FileReader(siteSpecificPasswordFile));
				for (Section curSection : siteSpecificPwdIni.values()){
					for (String curKey : curSection.keySet()){
						ini.put(curSection.getName(), curKey, curSection.get(curKey));
					}
				}
			}
		} catch (InvalidFileFormatException e) {
			logger.error("Site Specific config file is not valid.  Please check the syntax of the file.", e);
		} catch (IOException e) {
			logger.error("Site Specific config file could not be read.", e);
		}

		return ini;
	}

	private static String cleanIniValue(String value) {
		if (value == null) {
			return null;
		}
		value = value.trim();
		if (value.startsWith("\"")) {
			value = value.substring(1);
		}
		if (value.endsWith("\"")) {
			value = value.substring(0, value.length() - 1);
		}
		return value;
	}

	private static String sierraAPIToken;
	private static String sierraAPITokenType;
	private static long sierraAPIExpiration;
	private static boolean connectToSierraAPI(Ini configIni, String baseUrl){
		//Check to see if we already have a valid token
		if (sierraAPIToken != null){
			if (sierraAPIExpiration - new Date().getTime() > 0){
				//logger.debug("token is still valid");
				return true;
			}else{
				logger.debug("Token has expired");
			}
		}
		//Connect to the API to get our token
		HttpURLConnection conn;
		try {
			URL emptyIndexURL = new URL(baseUrl + "/token");
			conn = (HttpURLConnection) emptyIndexURL.openConnection();
			if (conn instanceof HttpsURLConnection){
				HttpsURLConnection sslConn = (HttpsURLConnection)conn;
				sslConn.setHostnameVerifier(new HostnameVerifier() {

					@Override
					public boolean verify(String hostname, SSLSession session) {
						//Do not verify host names
						return true;
					}
				});
			}
			conn.setReadTimeout(30000);
			conn.setConnectTimeout(30000);
			conn.setRequestMethod("POST");
			conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8");
			String clientKey = cleanIniValue(configIni.get("Catalog", "clientKey"));
			String clientSecret = cleanIniValue(configIni.get("Catalog", "clientSecret"));
			String encoded = Base64.encodeBase64String((clientKey + ":" + clientSecret).getBytes());
			conn.setRequestProperty("Authorization", "Basic "+encoded);
			conn.setDoOutput(true);
			OutputStreamWriter wr = new OutputStreamWriter(conn.getOutputStream(), "UTF8");
			wr.write("grant_type=client_credentials");
			wr.flush();
			wr.close();

			StringBuilder response = new StringBuilder();
			if (conn.getResponseCode() == 200) {
				// Get the response
				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
				String line;
				while ((line = rd.readLine()) != null) {
					response.append(line);
				}
				rd.close();
				try {
					JSONObject parser = new JSONObject(response.toString());
					sierraAPIToken = parser.getString("access_token");
					sierraAPITokenType = parser.getString("token_type");
					//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
					sierraAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
					//logger.debug("Sierra token is " + sierraAPIToken);
				}catch (JSONException jse){
					logger.error("Error parsing response to json " + response.toString(), jse);
					return false;
				}

			} else {
				logger.error("Received error " + conn.getResponseCode() + " connecting to sierra authentication service" );
				// Get any errors
				BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
				String line;
				while ((line = rd.readLine()) != null) {
					response.append(line);
				}
				logger.debug("  Finished reading response\r\n" + response);

				rd.close();
				return false;
			}

		} catch (Exception e) {
			logger.error("Error connecting to sierra API", e );
			return false;
		}
		return true;
	}

	private static boolean lastCallTimedOut = false;

	private static JSONObject callSierraApiURL(Ini configIni, String baseUrl, String sierraUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToSierraAPI(configIni, baseUrl)){
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				if (conn instanceof HttpsURLConnection){
					HttpsURLConnection sslConn = (HttpsURLConnection)conn;
					sslConn.setHostnameVerifier(new HostnameVerifier() {

						@Override
						public boolean verify(String hostname, SSLSession session) {
							//Do not verify host names
							return true;
						}
					});
				}
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response = new StringBuilder();
				if (conn.getResponseCode() == 200) {
					// Get the response
					BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream(), "UTF-8"));
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
					//logger.debug("  Finished reading response");
					rd.close();
					try{
						return new JSONObject(response.toString());
					}catch (JSONException jse){
						logger.error("Error parsing response \n" + response.toString(), jse);
						return null;
					}

				} else {
					if (logErrors) {
						logger.error("Received error " + conn.getResponseCode() + " calling sierra API " + sierraUrl);
						// Get any errors
						BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream(), "UTF-8"));
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						logger.error("  Finished reading response");
						logger.error(response.toString());

						rd.close();
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.debug("Socket timeout talking to to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.debug("Timeout connecting to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.debug("Error loading data from sierra API ", e );
			}
		}
		return null;
	}

	private static String getMarcFromSierraApiURL(Ini configIni, String baseUrl, String sierraUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToSierraAPI(configIni, baseUrl)){
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				if (conn instanceof HttpsURLConnection){
					HttpsURLConnection sslConn = (HttpsURLConnection)conn;
					sslConn.setHostnameVerifier(new HostnameVerifier() {

						@Override
						public boolean verify(String hostname, SSLSession session) {
							//Do not verify host names
							return true;
						}
					});
				}
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/marc-json");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response = new StringBuilder();
				if (conn.getResponseCode() == 200) {
					// Get the response
					BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream(), "UTF-8"));
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
					//logger.debug("  Finished reading response");
					rd.close();
					return response.toString();
				} else {
					if (logErrors) {
						logger.error("Received error " + conn.getResponseCode() + " calling sierra API " + sierraUrl);
						// Get any errors
						BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream(), "UTF-8"));
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						logger.error("  Finished reading response");
						logger.error(response.toString());

						rd.close();
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.debug("Socket timeout talking to to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.debug("Timeout connecting to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.debug("Error loading data from sierra API ", e );
			}
		}
		return null;
	}

	private static JSONObject getMarcJSONFromSierraApiURL(Ini configIni, String baseUrl, String sierraUrl, boolean logErrors) {
		lastCallTimedOut = false;
		if (connectToSierraAPI(configIni, baseUrl)){
			//Connect to the API to get our token
			HttpURLConnection conn;
			try {
				URL emptyIndexURL = new URL(sierraUrl);
				conn = (HttpURLConnection) emptyIndexURL.openConnection();
				if (conn instanceof HttpsURLConnection){
					HttpsURLConnection sslConn = (HttpsURLConnection)conn;
					sslConn.setHostnameVerifier(new HostnameVerifier() {

						@Override
						public boolean verify(String hostname, SSLSession session) {
							//Do not verify host names
							return true;
						}
					});
				}
				conn.setRequestMethod("GET");
				conn.setRequestProperty("Accept-Charset", "UTF-8");
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);
				conn.setRequestProperty("Accept", "application/marc-in-json");
				conn.setReadTimeout(20000);
				conn.setConnectTimeout(5000);

				StringBuilder response = new StringBuilder();
				if (conn.getResponseCode() == 200) {
					// Get the response
					BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream(), "UTF-8"));
					String line;
					while ((line = rd.readLine()) != null) {
						response.append(line);
					}
					//logger.debug("  Finished reading response");
					rd.close();
					return new JSONObject(response.toString());
				} else {
					if (logErrors) {
						logger.error("Received error " + conn.getResponseCode() + " calling sierra API " + sierraUrl);
						// Get any errors
						BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream(), "UTF-8"));
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						logger.error("  Finished reading response");
						logger.error(response.toString());

						rd.close();
					}
				}

			} catch (java.net.SocketTimeoutException e) {
				logger.debug("Socket timeout talking to to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (java.net.ConnectException e) {
				logger.debug("Timeout connecting to sierra API " + e.toString() );
				lastCallTimedOut = true;
			} catch (Exception e) {
				logger.debug("Error loading data from sierra API ", e );
			}
		}
		return null;
	}

	/**
	 * Calculates a check digit for a III identifier
	 * @param basedId String the base id without checksum
	 * @return String the check digit
	 */
	private static String getCheckDigit(String basedId) {
		int sumOfDigits = 0;
		for (int i = 0; i < basedId.length(); i++){
			int multiplier = ((basedId.length() +1 ) - i);
			sumOfDigits += multiplier * Integer.parseInt(basedId.substring(i, i+1));
		}
		int modValue = sumOfDigits % 11;
		if (modValue == 10){
			return "x";
		}else{
			return Integer.toString(modValue);
		}
	}

	private static StringBuffer notes = new StringBuffer();
	private static SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");
	private static void addNoteToExportLog(String note) {
		try {
			Date date = new Date();
			notes.append("<br>").append(dateFormat.format(date)).append(": ").append(note);
			addNoteToExportLogStmt.setString(1, trimTo(65535, notes.toString()));
			addNoteToExportLogStmt.setLong(2, new Date().getTime() / 1000);
			addNoteToExportLogStmt.setLong(3, exportLogId);
			addNoteToExportLogStmt.executeUpdate();
			logger.info(note);
		} catch (SQLException e) {
			logger.error("Error adding note to Export Log", e);
		}
	}

	private static String trimTo(int maxCharacters, String stringToTrim) {
		if (stringToTrim == null) {
			return null;
		}
		if (stringToTrim.length() > maxCharacters) {
			stringToTrim = stringToTrim.substring(0, maxCharacters);
		}
		return stringToTrim.trim();
	}
}