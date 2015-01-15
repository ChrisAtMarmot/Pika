package org.vufind;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;

import au.com.bytecode.opencsv.CSVWriter;
import org.apache.log4j.Logger;
import org.apache.log4j.PropertyConfigurator;
import org.ini4j.Ini;
import org.ini4j.InvalidFileFormatException;
import org.ini4j.Profile.Section;
import org.json.JSONArray;
import org.json.JSONObject;

import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLSession;
import org.apache.commons.codec.binary.Base64;
import org.marc4j.*;
import org.marc4j.marc.DataField;
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
	private static Long sierraExtractRunningVariableId = null;
	private static String itemTag;
	private static char itemRecordNumberSubfield;
	private static char locationSubfield;
	private static char statusSubfield;
	private static char dueDateSubfield;

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

		//Connect to the vufind database
		Connection vufindConn = null;
		try{
			String databaseConnectionInfo = cleanIniValue(ini.get("Database", "database_vufind_jdbc"));
			vufindConn = DriverManager.getConnection(databaseConnectionInfo);
		}catch (Exception e){
			System.out.println("Error connecting to vufind database " + e.toString());
			System.exit(1);
		}

		boolean sierraExtractRunning = false;
		try{
			PreparedStatement loadSierraExtractRunning = vufindConn.prepareStatement("SELECT * from variables WHERE name = 'sierra_extract_running'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet loadPartialExtractRunningRS = loadSierraExtractRunning.executeQuery();
			if (loadPartialExtractRunningRS.next()){
				sierraExtractRunning = loadPartialExtractRunningRS.getBoolean("value");
				sierraExtractRunningVariableId = loadPartialExtractRunningRS.getLong("id");
			}
			loadPartialExtractRunningRS.close();
			loadSierraExtractRunning.close();

			if (sierraExtractRunning){
				//Oops, a reindex is already running.
				logger.warn("A sierra extract is already running, verify that multiple extracts are not running currently");
				//return;
			}else{
				updateSierraExtractRunning(vufindConn, true);
			}
		} catch (Exception e){
			logger.error("Could not load last index time from variables table ", e);
		}

		//Get a list of works that have changed since the last index
		getChangedRecordsFromApi(ini, vufindConn);

		//Connect to the sierra database
		String url = ini.get("Catalog", "sierra_db");
		if (url.startsWith("\"")){
			url = url.substring(1, url.length() - 1);
		}
		Connection conn = null;
		try{
			//Open the connection to the database
			conn = DriverManager.getConnection(url);

			exportActiveOrders(exportPath, conn);

			exportHolds(conn, vufindConn);

		}catch(Exception e){
			System.out.println("Error: " + e.toString());
			e.printStackTrace();
		}

		updateSierraExtractRunning(vufindConn, false);

		if (conn != null){
			try{
				//Close the connection
				conn.close();
			}catch(Exception e){
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}

		if (vufindConn != null){
			try{
				//Close the connection
				vufindConn.close();
			}catch(Exception e){
				System.out.println("Error closing connection: " + e.toString());
				e.printStackTrace();
			}
		}
		Date currentTime = new Date();
		logger.info(currentTime.toString() + ": Finished Sierra Extract");
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

			HashMap<String, Long> numHoldsByBib = new HashMap<String, Long>();
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

			//Export item level holds
			PreparedStatement itemHoldsStmt = sierraConn.prepareStatement("select count(hold.id) as numHolds, record_num\n" +
					"from sierra_view.hold \n" +
					"inner join sierra_view.bib_record_item_record_link ON hold.record_id = item_record_id \n" +
					"inner join sierra_view.record_metadata on bib_record_item_record_link.bib_record_id = record_metadata.id \n" +
					"WHERE status = '0' OR status = 't' " +
					"group by record_num", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet itemHoldsRS = itemHoldsStmt.executeQuery();
			while (itemHoldsRS.next()){
				String bibId = itemHoldsRS.getString("record_num");
				bibId = ".b" + bibId + getCheckDigit(bibId);
				Long numHolds = itemHoldsRS.getLong("numHolds");
				if (numHoldsByBib.containsKey(bibId)){
					numHoldsByBib.put(bibId, numHolds + numHoldsByBib.get(bibId));
				}else{
					numHoldsByBib.put(bibId, numHolds);
				}
			}
			itemHoldsRS.close();

			for (String bibId : numHoldsByBib.keySet()){
				addIlsHoldSummary.setString(1, bibId);
				addIlsHoldSummary.setLong(2, numHoldsByBib.get(bibId));
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

	private static void getChangedRecordsFromApi(Ini ini, Connection vufindConn) {
		//Get the time the last extract was done
		try{
			logger.info("Starting to load changed records from Sierra using the API");
			Long lastSierraExtractTime = null;
			Long lastSierraExtractTimeVariableId = null;

			String individualMarcPath = ini.get("Reindex", "individualMarcPath");
			itemTag = ini.get("Reindex", "itemTag");
			itemRecordNumberSubfield = getSubfieldIndicatorFromConfig(ini, "itemRecordNumberSubfield");
			locationSubfield = getSubfieldIndicatorFromConfig(ini, "locationSubfield");
			statusSubfield = getSubfieldIndicatorFromConfig(ini, "statusSubfield");
			dueDateSubfield = getSubfieldIndicatorFromConfig(ini, "dueDateSubfield");

			PreparedStatement loadLastSierraExtractTimeStmt = vufindConn.prepareStatement("SELECT * from variables WHERE name = 'last_sierra_extract_time'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
			ResultSet lastSierraExtractTimeRS = loadLastSierraExtractTimeStmt.executeQuery();
			if (lastSierraExtractTimeRS.next()){
				lastSierraExtractTime = lastSierraExtractTimeRS.getLong("value");
				lastSierraExtractTimeVariableId = lastSierraExtractTimeRS.getLong("id");
			}

			String maxRecordsToUpdateDuringExtractStr = ini.get("Sierra", "maxRecordsToUpdateDuringExtract");
			int maxRecordsToUpdateDuringExtract = 100000;
			if (maxRecordsToUpdateDuringExtractStr != null){
				maxRecordsToUpdateDuringExtract = Integer.parseInt(maxRecordsToUpdateDuringExtractStr);
			}

			//Only mark records as changed
			boolean errorUpdatingDatabase = false;
			if (lastSierraExtractTime != null){
				String apiVersion = cleanIniValue(ini.get("Catalog", "api_version"));
				if (apiVersion == null || apiVersion.length() == 0){
					return;
				}
				String apiBaseUrl = ini.get("Catalog", "url") + "/iii/sierra-api/v" + apiVersion;

				//Last Update in UTC
				Date lastExtractDate = new Date(lastSierraExtractTime * 1000);

				SimpleDateFormat dateFormatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'");
				dateFormatter.setTimeZone(TimeZone.getTimeZone("UTC"));
				String dateUpdated = dateFormatter.format(lastExtractDate);
				long updateTime = new Date().getTime() / 1000;

				SimpleDateFormat marcDateFormat = new SimpleDateFormat("yyMMdd");

				//Extract the ids of all records that have changed.  That will allow us to mark
				//That the grouped record has changed which will force the work to be indexed
				//In reality, this will only update availability unless we pull the full marc record
				//from the API since we only have updated availability, not location data or metadata
				long offset = 0;
				boolean moreToRead = true;
				PreparedStatement markGroupedWorkForBibAsChangedStmt = vufindConn.prepareStatement("UPDATE grouped_work SET date_updated = ? where id = (SELECT grouped_work_id from grouped_work_primary_identifiers WHERE type = 'ils' and identifier = ?)") ;
				boolean firstLoad = true;
				HashMap<String, ArrayList<ItemChangeInfo>> changedBibs = new HashMap<String, ArrayList<ItemChangeInfo>>();
				while (moreToRead){
					JSONObject changedRecords = callSierraApiURL(ini, apiBaseUrl, apiBaseUrl + "/items/?updatedDate=[" + dateUpdated + ",]&limit=2000&fields=id,bibIds,location,status,fixedFields&deleted=false&suppressed=false&offset=" + offset, false);
					int numChangedIds = 0;
					if (changedRecords != null && changedRecords.has("entries")){
						if (firstLoad){
							int numUpdates = changedRecords.getInt("total");
							logger.info("A total of " + numUpdates + " items have been updated since " + dateUpdated);
							firstLoad = false;
							if (numUpdates > maxRecordsToUpdateDuringExtract){
								logger.warn("Too many records to extract from Sierra, aborting extract until next full record load");
								break;
							}
						}
						JSONArray changedIds = changedRecords.getJSONArray("entries");
						numChangedIds = changedIds.length();
						for(int i = 0; i < numChangedIds; i++){
							JSONObject curItem = changedIds.getJSONObject(i);
							String itemId = curItem.getString("id");
							String location;
							if (curItem.has("location")) {
								location = curItem.getJSONObject("location").getString("code");
							}else{
								location = "";
							}
							String status;
							if (curItem.has("status")){
								status = curItem.getJSONObject("status").getString("code");
							}else{
								status = "";
							}

							String dueDateMarc = null;
							if (curItem.getJSONObject("fixedFields").has("65")){
								String dueDateStr = curItem.getJSONObject("fixedFields").getJSONObject("65").getString("value");
								//The due date is in the format 2014-10-16T10:00:00Z, convert to what the marc record shows which is just yymmdd
								Date dueDate = dateFormatter.parse(dueDateStr);
								dueDateMarc = marcDateFormat.format(dueDate);
							}

							ItemChangeInfo changeInfo = new ItemChangeInfo();
							changeInfo.setItemId(".i" + itemId + getCheckDigit(itemId));
							changeInfo.setLocation(location);
							changeInfo.setStatus(status);

							changeInfo.setDueDate(dueDateMarc);

							JSONArray bibIds = curItem.getJSONArray("bibIds");
							for (int j = 0; j < bibIds.length(); j++){
								String curId = bibIds.getString(j);
								String fullId = ".b" + curId + getCheckDigit(curId);
								ArrayList<ItemChangeInfo> itemChanges;
								if (changedBibs.containsKey(fullId)) {
									itemChanges = changedBibs.get(fullId);
								}else{
									itemChanges = new ArrayList<ItemChangeInfo>();
									changedBibs.put(fullId, itemChanges);
								}
								itemChanges.add(changeInfo);
							}
						}
					}
					moreToRead = (numChangedIds >= 2000);
					offset += 2000;
					/*if (offset > 10000){
						logger.warn("There are an abnormally large number of changed records, breaking");
						break;
					}*/
				}

				vufindConn.setAutoCommit(false);
				logger.info("A total of " + changedBibs.size() + " bibs were updated");
				int numUpdates = 0;
				for (String curBibId : changedBibs.keySet()){
					//Update the marc record
					updateMarc(individualMarcPath, curBibId, changedBibs.get(curBibId));
					//Update the database
					try {
						markGroupedWorkForBibAsChangedStmt.setLong(1, updateTime);
						markGroupedWorkForBibAsChangedStmt.setString(2, curBibId);
						markGroupedWorkForBibAsChangedStmt.executeUpdate();

						numUpdates++;
						if (numUpdates % 50 == 0){
							vufindConn.commit();
						}
					}catch (SQLException e){
						logger.error("Could not mark that " + curBibId + " was changed due to error ", e);
						errorUpdatingDatabase = true;
					}
				}
				//Turn auto commit back on
				vufindConn.commit();
				vufindConn.setAutoCommit(true);

				//TODO: Process deleted records as well?
			}

			if (!errorUpdatingDatabase) {
				//Update the last extract time
				Long finishTime = new Date().getTime() / 1000;
				if (lastSierraExtractTimeVariableId != null) {
					PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
					updateVariableStmt.setLong(1, finishTime);
					updateVariableStmt.setLong(2, lastSierraExtractTimeVariableId);
					updateVariableStmt.executeUpdate();
					updateVariableStmt.close();
				} else {
					PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('last_sierra_extract_time', ?)");
					insertVariableStmt.setString(1, Long.toString(finishTime));
					insertVariableStmt.executeUpdate();
					insertVariableStmt.close();
				}
			}else{
				logger.error("There was an error updating the database, not setting last extract time.");
			}
		} catch (Exception e){
			logger.error("Error loading changed records from Sierra API", e);
			System.exit(1);
		}
		logger.info("Finished loading changed records from Sierra API");
	}

	private static void updateMarc(String individualMarcPath, String curBibId, ArrayList<ItemChangeInfo> itemChangeInfo) {
		//Load the existing marc record from file
		try {
			File marcFile = getFileForIlsRecord(individualMarcPath, curBibId);
			if (marcFile.exists()) {
				FileInputStream inputStream = new FileInputStream(marcFile);
				MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
				if (marcReader.hasNext()) {
					Record marcRecord = marcReader.next();
					inputStream.close();

					//Loop through all item fields to see what has changed
					List<VariableField> itemFields = marcRecord.getVariableFields(itemTag);
					for (VariableField itemFieldVar : itemFields) {
						DataField itemField = (DataField) itemFieldVar;
						if (itemField.getSubfield(itemRecordNumberSubfield) != null) {
							String itemRecordNumber = itemField.getSubfield(itemRecordNumberSubfield).getData();
							//Update the items
							for (ItemChangeInfo curItem : itemChangeInfo) {
								//Find the correct item
								if (itemRecordNumber.equals(curItem.getItemId())) {
									itemField.getSubfield(locationSubfield).setData(curItem.getLocation());
									itemField.getSubfield(statusSubfield).setData(curItem.getStatus());
									if (curItem.getDueDate() == null) {
										if (itemField.getSubfield(dueDateSubfield) != null) {
											itemField.getSubfield(dueDateSubfield).setData("      ");
										}
									} else {
										if (itemField.getSubfield(dueDateSubfield) == null) {
											itemField.addSubfield(new SubfieldImpl(dueDateSubfield, curItem.getDueDate()));
										} else {
											itemField.getSubfield(dueDateSubfield).setData(curItem.getDueDate());
										}
									}
								}
							}
						}
					}

					//Write the new marc record
					MarcWriter writer = new MarcStreamWriter(new FileOutputStream(marcFile, false));
					writer.write(marcRecord);
					writer.close();
				} else {
					logger.warn("Could not read marc record for " + curBibId);
				}
			}else{
				logger.debug("Marc Record does not exist for " + curBibId + " it is not part of the main extract yet.");
			}
		}catch (Exception e){
			logger.error("Error updating marc record for bib " + curBibId, e);
		}
	}

	private static File getFileForIlsRecord(String individualMarcPath, String recordNumber) {
		String shortId = getFileIdForRecordNumber(recordNumber);
		String firstChars = shortId.substring(0, 4);
		String basePath = individualMarcPath + "/" + firstChars;
		String individualFilename = basePath + "/" + shortId + ".mrc";
		File individualFile = new File(individualFilename);
		return individualFile;
	}

	private static String getFileIdForRecordNumber(String recordNumber) {
		String shortId = recordNumber.replace(".", "");
		while (shortId.length() < 9){
			shortId = "0" + shortId;
		}
		return shortId;
	}

	/*private static void exportAvailability(String exportPath, Connection conn) throws SQLException, IOException {
		logger.info("Starting export of available items");
		char[] availableStatuses = new char[]{'-', 'o', 'd', 'w', 'j', 'u'};
		File availableItemsFile = new File(exportPath + "/available_items_temp.csv");
		CSVWriter availableItemWriter = new CSVWriter(new FileWriter(availableItemsFile));
		boolean loadError = false;
		for(char curStatus : availableStatuses){
			PreparedStatement getAvailableItemsStmt = conn.prepareStatement("SELECT barcode " +
							"from sierra_view.item_view " +
							"WHERE " +
							"item_status_code = '" + curStatus + "'" +
							"AND icode2 != 'n' AND icode2 != 'x' " +
							"AND is_suppressed = 'f' " +
							"AND BARCODE != ''"
			);
			ResultSet activeOrdersRS = null;
			try{
				activeOrdersRS = getAvailableItemsStmt.executeQuery();
			}catch (SQLException e1){
				logger.error("Error loading available items for status " + curStatus, e1);
				loadError = true;
			}
			if (!loadError){
				availableItemWriter.writeAll(activeOrdersRS, false);
				activeOrdersRS.close();
			}
		}
		availableItemWriter.close();

		if (!loadError){
			//Copy the file
			File availableItems = new File(exportPath + "/available_items.csv");
			if (availableItems.exists()) {
				if (!availableItems.delete()){
					logger.error("Could not delete available items file");
					loadError = true;
				}
			}
			if (!loadError){
				if (!availableItemsFile.renameTo(availableItems)){
					logger.error("Could not rename available_items_temp.csv to available_items.csv");
				}
			}
		}

		//Also export items with checkouts
		logger.info("Starting export of checkouts");
		PreparedStatement allCheckoutsStmt = conn.prepareStatement("SELECT barcode " +
				"FROM sierra_view.checkout " +
				"INNER JOIN sierra_view.item_view on item_view.id = checkout.item_record_id"
		);
		ResultSet checkoutsRS = null;
		loadError = false;
		try{
			checkoutsRS = allCheckoutsStmt.executeQuery();
		}catch (SQLException e1){
			logger.error("Error loading checkouts", e1);
			loadError = true;
		}
		if (!loadError){
			File checkoutsFile = new File(exportPath + "/checkouts.csv");
			CSVWriter checkoutsWriter = new CSVWriter(new FileWriter(checkoutsFile));
			checkoutsWriter.writeAll(checkoutsRS, false);
			checkoutsWriter.close();
			checkoutsRS.close();
		}
	}*/

	private static void exportActiveOrders(String exportPath, Connection conn) throws SQLException, IOException {
		logger.info("Starting export of active orders");
		PreparedStatement getActiveOrdersStmt = conn.prepareStatement("select bib_view.record_num as bib_record_num, order_view.record_num as order_record_num, accounting_unit_code_num, order_status_code, copies, location_code " +
				"from sierra_view.order_view " +
				"inner join sierra_view.bib_record_order_record_link on bib_record_order_record_link.order_record_id = order_view.record_id " +
				"inner join sierra_view.bib_view on sierra_view.bib_view.id = bib_record_order_record_link.bib_record_id " +
				"inner join sierra_view.order_record_cmf on order_record_cmf.order_record_id = order_view.id " +
				"where (order_status_code = 'o' or order_status_code = '1') and order_view.is_suppressed = 'f'", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		ResultSet activeOrdersRS = null;
		boolean loadError = false;
		try{
			activeOrdersRS = getActiveOrdersStmt.executeQuery();
		} catch (SQLException e1){
			logger.error("Error loading active orders", e1);
			loadError = true;
		}
		if (!loadError){
			File orderRecordFile = new File(exportPath + "/active_orders.csv");
			CSVWriter orderRecordWriter = new CSVWriter(new FileWriter(orderRecordFile));
			orderRecordWriter.writeAll(activeOrdersRS, true);
			orderRecordWriter.close();
			activeOrdersRS.close();
		}
		logger.info("Finished exporting active orders");
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

	public static String cleanIniValue(String value) {
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

	private static char getSubfieldIndicatorFromConfig(Ini configIni, String subfieldName) {
		String subfieldString = configIni.get("Reindex", subfieldName);
		char subfield = ' ';
		if (subfieldString != null && subfieldString.length() > 0)  {
			subfield = subfieldString.charAt(0);
		}
		return subfield;
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
				JSONObject parser = new JSONObject(response.toString());
				sierraAPIToken = parser.getString("access_token");
				sierraAPITokenType = parser.getString("token_type");
				//logger.debug("Token expires in " + parser.getLong("expires_in") + " seconds");
				sierraAPIExpiration = new Date().getTime() + (parser.getLong("expires_in") * 1000) - 10000;
				//logger.debug("Sierra token is " + sierraAPIToken);
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

	private static JSONObject callSierraApiURL(Ini configIni, String baseUrl, String sierraUrl, boolean logErrors) {
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
				conn.setRequestProperty("Authorization", sierraAPITokenType + " " + sierraAPIToken);

				StringBuilder response = new StringBuilder();
				if (conn.getResponseCode() == 200) {
					// Get the response
					BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
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
						BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getErrorStream()));
						String line;
						while ((line = rd.readLine()) != null) {
							response.append(line);
						}
						logger.error("  Finished reading response");
						logger.error(response.toString());

						rd.close();
					}else{
						logger.debug("Received error " + conn.getResponseCode() + " calling sierra API " + sierraUrl);
					}
				}

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
	public static String getCheckDigit(String basedId) {
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

	private static void updateSierraExtractRunning(Connection vufindConn, boolean running) {
		//Update the last grouping time in the variables table
		try {
			if (sierraExtractRunningVariableId != null) {
				PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setString(1, Boolean.toString(running));
				updateVariableStmt.setLong(2, sierraExtractRunningVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else {
				PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('sierra_extract_running', ?)", Statement.RETURN_GENERATED_KEYS);
				insertVariableStmt.setString(1, Boolean.toString(running));
				insertVariableStmt.executeUpdate();
				ResultSet generatedKeys = insertVariableStmt.getGeneratedKeys();
				if (generatedKeys.next()){
					sierraExtractRunningVariableId = generatedKeys.getLong(1);
				}
				insertVariableStmt.close();
			}
		} catch (Exception e) {
			logger.error("Error setting partial extract running", e);
		}
	}
}
