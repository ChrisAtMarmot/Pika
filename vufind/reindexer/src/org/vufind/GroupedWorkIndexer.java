package org.vufind;

import au.com.bytecode.opencsv.CSVWriter;
import org.apache.solr.client.solrj.SolrServer;
import org.apache.solr.client.solrj.impl.BinaryRequestWriter;
import org.apache.solr.client.solrj.impl.ConcurrentUpdateSolrServer;
import org.apache.solr.client.solrj.impl.HttpSolrServer;
import org.apache.solr.common.SolrInputDocument;
import org.ini4j.Ini;

import java.io.*;
import java.sql.*;
import java.text.SimpleDateFormat;
import java.util.*;
import java.util.Date;

import org.apache.log4j.Logger;

/**
 * Indexes records extracted from the ILS
 *
 * Pika
 * User: Mark Noble
 * Date: 11/25/13
 * Time: 2:26 PM
 */
public class GroupedWorkIndexer {
	private Ini configIni;
	private String serverName;
	private String solrPort;
	private Logger logger;
	private SolrServer solrServer;
	private Long indexStartTime;
	private ConcurrentUpdateSolrServer updateServer;
	private HashMap<String, MarcRecordProcessor> ilsRecordProcessors = new HashMap<>();
	private OverDriveProcessor overDriveProcessor;
	private EVokeProcessor evokeProcessor;
	private HashMap<String, HashMap<String, String>> translationMaps = new HashMap<>();
	private HashMap<String, LexileTitle> lexileInformation = new HashMap<>();
	private Long maxWorksToProcess = -1L;

	private PreparedStatement getRatingStmt;
	private Connection vufindConn;

	protected int availableAtLocationBoostValue = 50;
	protected int ownedByLocationBoostValue = 10;

	private boolean fullReindex = false;
	private long lastReindexTime;
	private Long lastReindexTimeVariableId;
	private boolean partialReindexRunning;
	private Long partialReindexRunningVariableId;
	private Long fullReindexRunningVariableId;
	private boolean okToIndex = true;


	private HashSet<String> worksWithInvalidLiteraryForms = new HashSet<>();
	private TreeSet<Scope> scopes = new TreeSet<>();

	private PreparedStatement getGroupedWorkPrimaryIdentifiers;
	private PreparedStatement getGroupedWorkIdentifiers;
	private PreparedStatement getDateFirstDetectedStmt;

	public GroupedWorkIndexer(String serverName, Connection vufindConn, Connection econtentConn, Ini configIni, boolean fullReindex, Logger logger) {
		indexStartTime = new Date().getTime() / 1000;
		this.serverName = serverName;
		this.logger = logger;
		this.vufindConn = vufindConn;
		this.fullReindex = fullReindex;
		this.configIni = configIni;
		solrPort = configIni.get("Reindex", "solrPort");

		availableAtLocationBoostValue = Integer.parseInt(configIni.get("Reindex", "availableAtLocationBoostValue"));
		ownedByLocationBoostValue = Integer.parseInt(configIni.get("Reindex", "ownedByLocationBoostValue"));

		String maxWorksToProcessStr = Util.cleanIniValue(configIni.get("Reindex", "maxWorksToProcess"));
		if (maxWorksToProcessStr != null && maxWorksToProcessStr.length() > 0){
			try{
				maxWorksToProcess = Long.parseLong(maxWorksToProcessStr);
				logger.warn("Processing a maximum of " + maxWorksToProcess + " works");
			}catch (NumberFormatException e){
				logger.warn("Unable to parse max works to process " + maxWorksToProcessStr);
			}
		}

		//Load the last Index time
		try{
			PreparedStatement loadLastGroupingTime = vufindConn.prepareStatement("SELECT * from variables WHERE name = 'last_reindex_time'");
			ResultSet lastGroupingTimeRS = loadLastGroupingTime.executeQuery();
			if (lastGroupingTimeRS.next()){
				lastReindexTime = lastGroupingTimeRS.getLong("value");
				lastReindexTimeVariableId = lastGroupingTimeRS.getLong("id");
			}
			lastGroupingTimeRS.close();
			loadLastGroupingTime.close();
		} catch (Exception e){
			logger.error("Could not load last index time from variables table ", e);
		}

		//Check to see if a partial reindex is running
		try{
			PreparedStatement loadPartialReindexRunning = vufindConn.prepareStatement("SELECT * from variables WHERE name = 'partial_reindex_running'");
			ResultSet loadPartialReindexRunningRS = loadPartialReindexRunning.executeQuery();
			if (loadPartialReindexRunningRS.next()){
				partialReindexRunning = loadPartialReindexRunningRS.getBoolean("value");
				partialReindexRunningVariableId = loadPartialReindexRunningRS.getLong("id");
			}
			loadPartialReindexRunningRS.close();
			loadPartialReindexRunning.close();
		} catch (Exception e){
			logger.error("Could not load last index time from variables table ", e);
		}

		//Load a few statements we will need later
		try{
			getGroupedWorkPrimaryIdentifiers = vufindConn.prepareStatement("SELECT * FROM grouped_work_primary_identifiers where grouped_work_id = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//MDN 4/14 - Do not restrict by valid for enrichment since many popular titles
			//Wind up with different work id's due to differences in cataloging.
			getGroupedWorkIdentifiers = vufindConn.prepareStatement("SELECT * FROM grouped_work_identifiers inner join grouped_work_identifiers_ref on identifier_id = grouped_work_identifiers.id where grouped_work_id = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			//TODO: Restore functionality to not include any identifiers that aren't tagged as valid for enrichment
			//getGroupedWorkIdentifiers = vufindConn.prepareStatement("SELECT * FROM grouped_work_identifiers inner join grouped_work_identifiers_ref on identifier_id = grouped_work_identifiers.id where grouped_work_id = ? and valid_for_enrichment = 1", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

			getDateFirstDetectedStmt = vufindConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE ilsId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		} catch (Exception e){
			logger.error("Could not load statements to get identifiers ", e);
		}

		//Initialize the updateServer and solr server
		if (fullReindex){
			updateServer = new ConcurrentUpdateSolrServer("http://localhost:" + solrPort + "/solr/grouped2", 500, 8);
			updateServer.setRequestWriter(new BinaryRequestWriter());
			solrServer = new HttpSolrServer("http://localhost:" + solrPort + "/solr/grouped2");
			updateFullReindexRunning(true);
		}else{
			//Check to make sure that at least a couple of minutes have elapsed since the last index
			//Periodically in the middle of the night we get indexes every minute or multiple times a minute
			//which is annoying especially since it generally means nothing is changing.
			long elapsedTime = indexStartTime - lastReindexTime;
			long minIndexingInterval = 4 * 60;
			if (elapsedTime < minIndexingInterval) {
				try {
					logger.debug("Pausing between indexes, last index ran " + Math.ceil(elapsedTime / 60) + " minutes ago");
					logger.debug("Pausing for " + (minIndexingInterval - elapsedTime) + " seconds");
					GroupedReindexMain.addNoteToReindexLog("Pausing between indexes, last index ran " + Math.ceil(elapsedTime / 60) + " minutes ago");
					GroupedReindexMain.addNoteToReindexLog("Pausing for " + (minIndexingInterval - elapsedTime) + " seconds");
					Thread.sleep((minIndexingInterval - elapsedTime) * 1000);
				} catch (InterruptedException e) {
					logger.warn("Pause was interrupted while pausing between indexes");
				}
			}else{
				GroupedReindexMain.addNoteToReindexLog("Index last ran " + (elapsedTime) + " seconds ago");
			}

			if (partialReindexRunning){
				//Oops, a reindex is already running.
				//No longer really care about this since it doesn't happen and there are other ways of finding a stuck process
				//logger.warn("A partial reindex is already running, check to make sure that reindexes don't overlap since that can cause poor performance");
				GroupedReindexMain.addNoteToReindexLog("A partial reindex is already running, check to make sure that reindexes don't overlap since that can cause poor performance");
			}else{
				updatePartialReindexRunning(true);
			}
			updateServer = new ConcurrentUpdateSolrServer("http://localhost:" + solrPort + "/solr/grouped", 500, 8);
			solrServer = new HttpSolrServer("http://localhost:" + solrPort + "/solr/grouped");
		}

		loadScopes();

		//Initialize processors based on our indexing profiles and the primary identifiers for the records.
		try {
			PreparedStatement uniqueIdentifiersStmt = vufindConn.prepareStatement("SELECT DISTINCT type FROM grouped_work_primary_identifiers");
			PreparedStatement getIndexingProfile = vufindConn.prepareStatement("SELECT * from indexing_profiles where name = ?");
			ResultSet uniqueIdentifiersRS = uniqueIdentifiersStmt.executeQuery();

			while (uniqueIdentifiersRS.next()){
				String curIdentifier = uniqueIdentifiersRS.getString("type");
				getIndexingProfile.setString(1, curIdentifier);
				ResultSet indexingProfileRS = getIndexingProfile.executeQuery();
				if (indexingProfileRS.next()){
					String ilsIndexingClassString =    indexingProfileRS.getString("indexingClass");
					switch (ilsIndexingClassString) {
						case "Marmot":
							ilsRecordProcessors.put(curIdentifier, new MarmotRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "Nashville":
							ilsRecordProcessors.put(curIdentifier, new NashvilleRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "NashvilleSchools":
							ilsRecordProcessors.put(curIdentifier, new NashvilleSchoolsRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "WCPL":
							ilsRecordProcessors.put(curIdentifier, new WCPLRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "Anythink":
							ilsRecordProcessors.put(curIdentifier, new AnythinkRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "Aspencat":
							ilsRecordProcessors.put(curIdentifier, new AspencatRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "Flatirons":
							ilsRecordProcessors.put(curIdentifier, new FlatironsRecordProcessor(this, vufindConn, configIni, indexingProfileRS, logger, fullReindex));
							break;
						case "Hoopla":
							ilsRecordProcessors.put(curIdentifier, new HooplaProcessor(this, configIni, logger));
							break;
						default:
							logger.error("Unknown indexing class " + ilsIndexingClassString);
							okToIndex = false;
							return;
					}
				}else{
					logger.debug("Could not find indexing profile for type " + curIdentifier);
				}
			}

			setupIndexingStats();

		}catch (Exception e){
			logger.error("Error loading record processors for ILS records", e);
		}
		overDriveProcessor = new OverDriveProcessor(this, econtentConn, logger);
		evokeProcessor = new EVokeProcessor(this, configIni, logger);
		//Load translation maps
		loadSystemTranslationMaps();

		//Setup prepared statements to load local enrichment
		try {
			getRatingStmt = vufindConn.prepareStatement("SELECT AVG(rating) as averageRating from user_work_review where groupedRecordPermanentId = ? and rating > 0");
		} catch (SQLException e) {
			logger.error("Could not prepare statements to load local enrichment", e);
		}

		String lexileExportPath = configIni.get("Reindex", "lexileExportPath");
		loadLexileData(lexileExportPath);

		if (fullReindex){
			clearIndex();
		}
	}

	protected void setupIndexingStats() {
		ArrayList<String> recordProcessorNames = new ArrayList<>();
		recordProcessorNames.addAll(ilsRecordProcessors.keySet());
		recordProcessorNames.add("overdrive");

		for (Scope curScope : scopes){
			ScopedIndexingStats scopedIndexingStats = new ScopedIndexingStats(curScope.getScopeName(), recordProcessorNames);
			indexingStats.put(curScope.getScopeName(), scopedIndexingStats);
		}
	}

	public boolean isOkToIndex(){
		return okToIndex;
	}

	private boolean libraryAndLocationDataLoaded = false;

	//Keep track of what we are indexing for validation purposes
	public TreeMap<String, TreeSet<String>> ilsRecordsIndexed = new TreeMap<>();
	public TreeSet<String> overDriveRecordsIndexed = new TreeSet<>();
	public TreeMap<String, TreeSet<String>> ilsRecordsSkipped = new TreeMap<>();
	public TreeSet<String> overDriveRecordsSkipped = new TreeSet<>();
	public TreeMap<String, ScopedIndexingStats> indexingStats = new TreeMap<>();

	private void loadScopes() {
		if (!libraryAndLocationDataLoaded){
			//Setup translation maps for system and location
			try {
				loadLibraryScopes();

				loadLocationScopes();
			} catch (SQLException e) {
				logger.error("Error setting up system maps", e);
			}
			libraryAndLocationDataLoaded = true;
			logger.info("Loaded " + scopes.size() + " scopes");
		}
	}

	private void loadLocationScopes() throws SQLException {
		PreparedStatement locationInformationStmt = vufindConn.prepareStatement("SELECT library.libraryId, locationId, code, subLocation, ilsCode, " +
				"library.subdomain, location.facetLabel, location.displayName, library.pTypes, library.restrictOwningBranchesAndSystems, " +
				"library.enableOverdriveCollection as enableOverdriveCollectionLibrary, " +
				"location.enableOverdriveCollection as enableOverdriveCollectionLocation " +
				"FROM location INNER JOIN library on library.libraryId = location.libraryId ORDER BY code ASC",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationOwnedRecordRulesStmt = vufindConn.prepareStatement("SELECT location_records_owned.*, indexing_profiles.name FROM location_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		PreparedStatement locationRecordInclusionRulesStmt = vufindConn.prepareStatement("SELECT location_records_to_include.*, indexing_profiles.name FROM location_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE locationId = ?",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);

		ResultSet locationInformationRS = locationInformationStmt.executeQuery();
		while (locationInformationRS.next()){
			String code = locationInformationRS.getString("code").toLowerCase();
			String subLocation = locationInformationRS.getString("subLocation");
			String facetLabel = locationInformationRS.getString("facetLabel");
			String displayName = locationInformationRS.getString("displayName");
			if (facetLabel.length() == 0){
				facetLabel = displayName;
			}

			//Determine if we need to build a scope for this location
			Long libraryId = locationInformationRS.getLong("libraryId");
			Long locationId = locationInformationRS.getLong("locationId");
			String pTypes = locationInformationRS.getString("pTypes");
			if (pTypes == null) pTypes = "";
			boolean includeOverDriveCollectionLibrary = locationInformationRS.getBoolean("enableOverdriveCollectionLibrary");
			boolean includeOverDriveCollectionLocation = locationInformationRS.getBoolean("enableOverdriveCollectionLocation");

			Scope locationScopeInfo = new Scope();
			locationScopeInfo.setIsLibraryScope(false);
			locationScopeInfo.setIsLocationScope(true);
			String scopeName = code;
			if (subLocation != null && subLocation.length() > 0){
				scopeName = subLocation.toLowerCase();
			}
			locationScopeInfo.setScopeName(scopeName);
			locationScopeInfo.setLibraryId(libraryId);
			locationScopeInfo.setRelatedPTypes(pTypes.split(","));
			locationScopeInfo.setFacetLabel(facetLabel);
			locationScopeInfo.setIncludeOverDriveCollection(includeOverDriveCollectionLibrary && includeOverDriveCollectionLocation);
			locationScopeInfo.setRestrictOwningLibraryAndLocationFacets(locationInformationRS.getBoolean("restrictOwningBranchesAndSystems"));
			locationScopeInfo.setIlsCode(code);

			//Load information about what should be included in the scope
			locationOwnedRecordRulesStmt.setLong(1, locationId);
			ResultSet locationOwnedRecordRulesRS = locationOwnedRecordRulesStmt.executeQuery();
			while (locationOwnedRecordRulesRS.next()){
				locationScopeInfo.addOwnershipRule(new OwnershipRule(locationOwnedRecordRulesRS.getString("name"), locationOwnedRecordRulesRS.getString("location"), locationOwnedRecordRulesRS.getString("subLocation")));
			}

			locationRecordInclusionRulesStmt.setLong(1, locationId);
			ResultSet locationRecordInclusionRulesRS = locationRecordInclusionRulesStmt.executeQuery();
			while (locationRecordInclusionRulesRS.next()){
				locationScopeInfo.addInclusionRule(new InclusionRule(locationRecordInclusionRulesRS.getString("name"),
						locationRecordInclusionRulesRS.getString("location"),
						locationRecordInclusionRulesRS.getString("subLocation"),
						locationRecordInclusionRulesRS.getBoolean("includeHoldableOnly"),
						locationRecordInclusionRulesRS.getBoolean("includeItemsOnOrder"),
						locationRecordInclusionRulesRS.getBoolean("includeEContent")
				));
			}

			if (!scopes.contains(locationScopeInfo)){
				//Connect this scope to the library scopes
				for (Scope curScope : scopes){
					if (curScope.isLibraryScope() && Objects.equals(curScope.getLibraryId(), libraryId)){
						curScope.addLocationScope(locationScopeInfo);
						locationScopeInfo.setLibraryScope(curScope);
						break;
					}
				}
				scopes.add(locationScopeInfo);
			}else{
				logger.debug("Not adding location scope because a library scope with the name " + locationScopeInfo.getScopeName() + " exists already.");
				for (Scope existingLibraryScope : scopes){
					if (existingLibraryScope.getScopeName().equals(locationScopeInfo.getScopeName())){
						existingLibraryScope.setIsLocationScope(true);
						break;
					}
				}
			}
		}
	}

	private void loadLibraryScopes() throws SQLException {
		PreparedStatement libraryInformationStmt = vufindConn.prepareStatement("SELECT libraryId, ilsCode, subdomain, " +
				"displayName, facetLabel, pTypes, enableOverdriveCollection, restrictOwningBranchesAndSystems " +
				"FROM library ORDER BY ilsCode ASC",
				ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement libraryOwnedRecordRulesStmt = vufindConn.prepareStatement("SELECT library_records_owned.*, indexing_profiles.name from library_records_owned INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		PreparedStatement libraryRecordInclusionRulesStmt = vufindConn.prepareStatement("SELECT library_records_to_include.*, indexing_profiles.name from library_records_to_include INNER JOIN indexing_profiles ON indexingProfileId = indexing_profiles.id WHERE libraryId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		ResultSet libraryInformationRS = libraryInformationStmt.executeQuery();
		while (libraryInformationRS.next()){
			String facetLabel = libraryInformationRS.getString("facetLabel");
			String subdomain = libraryInformationRS.getString("subdomain");
			String displayName = libraryInformationRS.getString("displayName");
			if (facetLabel.length() == 0){
				facetLabel = displayName;
			}
			//These options determine how scoping is done
			Long libraryId = libraryInformationRS.getLong("libraryId");
			String pTypes = libraryInformationRS.getString("pTypes");
			if (pTypes == null) {pTypes = "";}
			boolean includeOverdrive = libraryInformationRS.getBoolean("enableOverdriveCollection");

			//Determine if we need to build a scope for this library
			//MDN 10/1/2014 always build scopes because it makes coding more consistent elsewhere.
			//We need to build a scope
			Scope newScope = new Scope();
			newScope.setIsLibraryScope(true);
			newScope.setIsLocationScope(false);
			newScope.setScopeName(subdomain);
			newScope.setLibraryId(libraryId);
			newScope.setFacetLabel(facetLabel);
			newScope.setRelatedPTypes(pTypes.split(","));
			newScope.setIncludeOverDriveCollection(includeOverdrive);
			newScope.setRestrictOwningLibraryAndLocationFacets(libraryInformationRS.getBoolean("restrictOwningBranchesAndSystems"));
			newScope.setIlsCode(libraryInformationRS.getString("ilsCode"));

			//Load information about what should be included in the scope
			libraryOwnedRecordRulesStmt.setLong(1, libraryId);
			ResultSet libraryOwnedRecordRulesRS = libraryOwnedRecordRulesStmt.executeQuery();
			while (libraryOwnedRecordRulesRS.next()){
				newScope.addOwnershipRule(new OwnershipRule(libraryOwnedRecordRulesRS.getString("name"), libraryOwnedRecordRulesRS.getString("location"), libraryOwnedRecordRulesRS.getString("subLocation")));
			}

			libraryRecordInclusionRulesStmt.setLong(1, libraryId);
			ResultSet libraryRecordInclusionRulesRS = libraryRecordInclusionRulesStmt.executeQuery();
			while (libraryRecordInclusionRulesRS.next()){
				newScope.addInclusionRule(new InclusionRule(libraryRecordInclusionRulesRS.getString("name"),
						libraryRecordInclusionRulesRS.getString("location"),
						libraryRecordInclusionRulesRS.getString("subLocation"),
						libraryRecordInclusionRulesRS.getBoolean("includeHoldableOnly"),
						libraryRecordInclusionRulesRS.getBoolean("includeItemsOnOrder"),
						libraryRecordInclusionRulesRS.getBoolean("includeEContent")
				));
			}


			scopes.add(newScope);
		}
	}

	private void loadLexileData(String lexileExportPath) {
		try{
			File lexileData = new File(lexileExportPath);
			BufferedReader lexileReader = new BufferedReader(new FileReader(lexileData));
			//Skip over the header
			lexileReader.readLine();
			String lexileLine = lexileReader.readLine();
			while (lexileLine != null){
				String[] lexileFields = lexileLine.split("\\t");
				LexileTitle titleInfo = new LexileTitle();
				if (lexileFields.length >= 11){
					titleInfo.setTitle(lexileFields[0]);
					titleInfo.setAuthor(lexileFields[1]);
					String isbn = lexileFields[3];
					titleInfo.setLexileCode(lexileFields[4]);
					titleInfo.setLexileScore(lexileFields[5]);
					titleInfo.setSeries(lexileFields[9]);
					titleInfo.setAwards(lexileFields[10]);
					titleInfo.setDescription(lexileFields[11]);
					lexileInformation.put(isbn, titleInfo);
				}
				lexileLine = lexileReader.readLine();
			}
			logger.info("Read " + lexileInformation.size() + " lines of lexile data");
		}catch (Exception e){
			logger.error("Error loading lexile data", e);
		}
	}

	private void clearIndex() {
		//Check to see if we should clear the existing index
		logger.info("Clearing existing marc records from index");
		try {
			updateServer.deleteByQuery("recordtype:grouped_work", 10);
			updateServer.commit(true, true);
		} catch (Exception e) {
			logger.error("Error deleting from index", e);
		}
	}

	public void finishIndexing(){
		logger.info("Finishing indexing");
		try {
			logger.info("Calling commit");
			updateServer.commit(true, true);
		} catch (Exception e) {
			logger.error("Error calling final commit", e);
		}
		//Solr now optimizes itself.  No need to force an optimization.
		try {
			//Optimize to trigger improved performance.  If we're doing a full reindex, need to wait for the searcher since
			// we are going to swap in a minute.
			logger.info("Optimizing index");
			if (fullReindex) {
				updateServer.optimize(true, true);
			}
			logger.info("Finished Optimizing index");
		} catch (Exception e) {
			logger.error("Error optimizing index", e);
		}
		try {
			logger.info("Shutting down the update server");
			updateServer.shutdown();
		} catch (Exception e) {
			logger.error("Error shutting down update server", e);
		}
		//Swap the indexes
		if (fullReindex)  {
			try {
				Util.getURL("http://localhost:" + solrPort + "/solr/admin/cores?action=SWAP&core=grouped2&other=grouped", logger);
			} catch (Exception e) {
				logger.error("Error shutting down update server", e);
			}
		}
		writeWorksWithInvalidLiteraryForms();
		updateLastReindexTime();

		//Write validation information
		if (fullReindex) {
			writeValidationInformation();
			writeStats();
			updateFullReindexRunning(false);
		}else{
			updatePartialReindexRunning(false);
		}
	}

	private void writeStats() {
		try {
			File dataDir = new File(configIni.get("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date curDate = new Date();
			String curDateFormatted = dayFormatter.format(curDate);
			File recordsFile = new File(dataDir.getAbsolutePath() + "/reindex_stats_" + curDateFormatted + ".csv");
			CSVWriter recordWriter = new CSVWriter(new FileWriter(recordsFile));
			ArrayList<String> headers = new ArrayList<>();
			headers.add("Scope Name");
			headers.add("Owned works");
			headers.add("Total works");
			TreeSet<String> recordProcessorNames = new TreeSet<>();
			recordProcessorNames.addAll(ilsRecordProcessors.keySet());
			recordProcessorNames.add("overdrive");
			for (String processorName : recordProcessorNames){
				headers.add("Owned " + processorName + " records");
				headers.add("Owned " + processorName + " physical items");
				headers.add("Owned " + processorName + " on order items");
				headers.add("Owned " + processorName + " e-content items");
				headers.add("Total " + processorName + " records");
				headers.add("Total " + processorName + " physical items");
				headers.add("Total " + processorName + " on order items");
				headers.add("Total " + processorName + " e-content items");
			}
			recordWriter.writeNext(headers.toArray(new String[headers.size()]));

			//Write custom scopes
			for (String curScope: indexingStats.keySet()){
				ScopedIndexingStats stats = indexingStats.get(curScope);
				recordWriter.writeNext(stats.getData());
			}
			recordWriter.flush();
			recordWriter.close();
		} catch (IOException e) {
			logger.error("Unable to write statistics", e);
		}
	}

	private void writeValidationInformation() {
		for (String recordType : ilsRecordsIndexed.keySet()){
			writeExistingRecordsFile(ilsRecordsIndexed.get(recordType), "reindexer_" + recordType + "_records_processed");
		}
		for (String recordType : ilsRecordsSkipped.keySet()){
			writeExistingRecordsFile(ilsRecordsSkipped.get(recordType), "reindexer_" + recordType + "_records_skipped");
		}

		writeExistingRecordsFile(overDriveRecordsIndexed, "reindexer_overdrive_records_processed");
		writeExistingRecordsFile(overDriveRecordsSkipped, "reindexer_overdrive_records_skipped");
	}

	private static SimpleDateFormat dayFormatter = new SimpleDateFormat("yyyy-MM-dd");
	private void writeExistingRecordsFile(TreeSet<String> recordNumbersInExport, String filePrefix) {
		try {
			File dataDir = new File(configIni.get("Reindex", "marcPath"));
			dataDir = dataDir.getParentFile();
			//write the records in CSV format to the data directory
			Date curDate = new Date();
			String curDateFormatted = dayFormatter.format(curDate);
			File recordsFile = new File(dataDir.getAbsolutePath() + "/" + filePrefix + "_" + curDateFormatted + ".csv");
			CSVWriter recordWriter = new CSVWriter(new FileWriter(recordsFile));
			for (String curRecord: recordNumbersInExport){
				recordWriter.writeNext(new String[]{curRecord});
			}
			recordWriter.flush();
			recordWriter.close();
		} catch (IOException e) {
			logger.error("Unable to write existing records to " + filePrefix, e);
		}
	}

	private void updatePartialReindexRunning(boolean running) {
		if (!fullReindex) {
			logger.info("Updating partial reindex running");
			//Update the last grouping time in the variables table
			try {
				if (partialReindexRunningVariableId != null) {
					PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
					updateVariableStmt.setString(1, Boolean.toString(running));
					updateVariableStmt.setLong(2, partialReindexRunningVariableId);
					updateVariableStmt.executeUpdate();
					updateVariableStmt.close();
				} else {
					PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('partial_reindex_running', ?)", Statement.RETURN_GENERATED_KEYS);
					insertVariableStmt.setString(1, Boolean.toString(running));
					insertVariableStmt.executeUpdate();
					ResultSet generatedKeys = insertVariableStmt.getGeneratedKeys();
					if (generatedKeys.next()){
						partialReindexRunningVariableId = generatedKeys.getLong(1);
					}
					insertVariableStmt.close();
				}
			} catch (Exception e) {
				logger.error("Error setting last grouping time", e);
			}
		}
	}

	private void updateFullReindexRunning(boolean running) {
		logger.info("Updating full reindex running");
		//Update the last grouping time in the variables table
		try {
			if (fullReindexRunningVariableId != null) {
				PreparedStatement updateVariableStmt = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setString(1, Boolean.toString(running));
				updateVariableStmt.setLong(2, fullReindexRunningVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else {
				PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('full_reindex_running', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", Statement.RETURN_GENERATED_KEYS);
				insertVariableStmt.setString(1, Boolean.toString(running));
				insertVariableStmt.executeUpdate();
				ResultSet generatedKeys = insertVariableStmt.getGeneratedKeys();
				if (generatedKeys.next()){
					fullReindexRunningVariableId = generatedKeys.getLong(1);
				}
				insertVariableStmt.close();
			}
		} catch (Exception e) {
			logger.error("Error setting that full index is running", e);
		}
	}

	private void writeWorksWithInvalidLiteraryForms() {
		logger.info("Writing works with invalid literary forms");
		File worksWithInvalidLiteraryFormsFile = new File ("/var/log/vufind-plus/" + serverName + "/worksWithInvalidLiteraryForms.txt");
		try {
			if (worksWithInvalidLiteraryForms.size() > 0) {
				FileWriter writer = new FileWriter(worksWithInvalidLiteraryFormsFile, false);
				logger.debug("Found " + worksWithInvalidLiteraryForms.size() + " grouped works with invalid literary forms\r\n");
				writer.write("Found " + worksWithInvalidLiteraryForms.size() + " grouped works with invalid literary forms\r\n");
				writer.write("Works with inconsistent literary forms\r\n");
				for (String curId : worksWithInvalidLiteraryForms){
					writer.write(curId + "\r\n");
				}
			}
		}catch(Exception e){
			logger.error("Error writing works with invalid literary forms", e);
		}
	}

	private void updateLastReindexTime() {
		//Update the last grouping time in the variables table.  This needs to be the time the index started to catch anything that changes during the index
		try{
			if (lastReindexTimeVariableId != null){
				PreparedStatement updateVariableStmt  = vufindConn.prepareStatement("UPDATE variables set value = ? WHERE id = ?");
				updateVariableStmt.setLong(1, indexStartTime);
				updateVariableStmt.setLong(2, lastReindexTimeVariableId);
				updateVariableStmt.executeUpdate();
				updateVariableStmt.close();
			} else{
				PreparedStatement insertVariableStmt = vufindConn.prepareStatement("INSERT INTO variables (`name`, `value`) VALUES ('last_reindex_time', ?)");
				insertVariableStmt.setString(1, Long.toString(indexStartTime));
				insertVariableStmt.executeUpdate();
				insertVariableStmt.close();
			}
		}catch (Exception e){
			logger.error("Error setting last grouping time", e);
		}
	}

	public Long processGroupedWorks() {
		Long numWorksProcessed = 0L;
		try {
			PreparedStatement getAllGroupedWorks;
			PreparedStatement getNumWorksToIndex;
			PreparedStatement setLastUpdatedTime = vufindConn.prepareStatement("UPDATE grouped_work set date_updated = ? where id = ?");
			if (fullReindex){
				getAllGroupedWorks = vufindConn.prepareStatement("SELECT * FROM grouped_work", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getNumWorksToIndex = vufindConn.prepareStatement("SELECT count(id) FROM grouped_work", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
			}else{
				//Load all grouped works that have changed since the last time the index ran
				getAllGroupedWorks = vufindConn.prepareStatement("SELECT * FROM grouped_work WHERE date_updated IS NULL OR date_updated >= ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getAllGroupedWorks.setLong(1, lastReindexTime);
				getNumWorksToIndex = vufindConn.prepareStatement("SELECT count(id) FROM grouped_work WHERE date_updated IS NULL OR date_updated >= ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
				getNumWorksToIndex.setLong(1, lastReindexTime);
			}

			//Get the number of works we will be processing
			ResultSet numWorksToIndexRS = getNumWorksToIndex.executeQuery();
			numWorksToIndexRS.next();
			Long numWorksToIndex = numWorksToIndexRS.getLong(1);
			GroupedReindexMain.addNoteToReindexLog("Starting to process " + numWorksToIndex + " grouped works");

			ResultSet groupedWorks = getAllGroupedWorks.executeQuery();
			while (groupedWorks.next()){
				Long id = groupedWorks.getLong("id");
				String permanentId = groupedWorks.getString("permanent_id");
				String grouping_category = groupedWorks.getString("grouping_category");
				Long lastUpdated = groupedWorks.getLong("date_updated");
				if (groupedWorks.wasNull()){
					lastUpdated = null;
				}
				processGroupedWork(id, permanentId, grouping_category);

				numWorksProcessed++;
				if (numWorksProcessed % 5000 == 0){
					//Testing shows that regular commits do seem to improve performance.
					//However, we can't do it too often or we get errors with too many searchers warming. n
					//Leave in for now.
					try {
						updateServer.commit(true, false);
					}catch (Exception e){
						logger.warn("Error committing changes", e);
					}
					logger.info("Processed " + numWorksProcessed + " grouped works processed.");
				}
				if (maxWorksToProcess != -1 && numWorksProcessed >= maxWorksToProcess){
					logger.warn("Stopping processing now because we've reached the max works to process.");
					break;
				}
				if (lastUpdated == null){
					setLastUpdatedTime.setLong(1, indexStartTime - 1); //Set just before the index started so we don't index multiple times
					setLastUpdatedTime.setLong(2, id);
					setLastUpdatedTime.executeUpdate();
				}
			}
		} catch (SQLException e) {
			logger.error("Unexpected SQL error", e);
		}
		logger.info("Finished processing grouped works.  Processed a total of " + numWorksProcessed + " grouped works");
		return numWorksProcessed;
	}

	public void processGroupedWork(Long id, String permanentId, String grouping_category) throws SQLException {
		//Create a solr record for the grouped work
		GroupedWorkSolr groupedWork = new GroupedWorkSolr(this, logger);
		groupedWork.setId(permanentId);
		groupedWork.setGroupingCategory(grouping_category);

		getGroupedWorkPrimaryIdentifiers.setLong(1, id);
		ResultSet groupedWorkPrimaryIdentifiers = getGroupedWorkPrimaryIdentifiers.executeQuery();
		int numPrimaryIdentifiers = 0;
		while (groupedWorkPrimaryIdentifiers.next()){
			String type = groupedWorkPrimaryIdentifiers.getString("type");
			String identifier = groupedWorkPrimaryIdentifiers.getString("identifier");
			//This does the bulk of the work building fields for the solr document
			updateGroupedWorkForPrimaryIdentifier(groupedWork, type, identifier);
			numPrimaryIdentifiers++;
		}
		groupedWorkPrimaryIdentifiers.close();

		if (numPrimaryIdentifiers > 0) {
			//Add a grouped work to any scopes that are relevant
			groupedWork.updateIndexingStats(indexingStats);

			//Update the grouped record based on data for each work
			getGroupedWorkIdentifiers.setLong(1, id);
			ResultSet groupedWorkIdentifiers = getGroupedWorkIdentifiers.executeQuery();
			//This just adds isbns, issns, upcs, and oclc numbers to the index
			while (groupedWorkIdentifiers.next()) {
				String type = groupedWorkIdentifiers.getString("type");
				String identifier = groupedWorkIdentifiers.getString("identifier");
				updateGroupedWorkForSecondaryIdentifier(groupedWork, type, identifier);
			}
			groupedWorkIdentifiers.close();

			//Load local (VuFind) enrichment for the work
			loadLocalEnrichment(groupedWork);
			//Load lexile data for the work
			loadLexileDataForWork(groupedWork);

			//Write the record to Solr.
			try {
				SolrInputDocument inputDocument = groupedWork.getSolrDocument(availableAtLocationBoostValue, ownedByLocationBoostValue);
				updateServer.add(inputDocument);

			} catch (Exception e) {
				logger.error("Error adding record to solr", e);
			}
		}else{
			//Log that this record did not have primary identifiers after
			logger.debug("Grouped work " + permanentId + " did not have any primary identifiers for it, suppressing");
		}
	}

	private void loadLexileDataForWork(GroupedWorkSolr groupedWork) {
		for(String isbn : groupedWork.getIsbns()){
			if (lexileInformation.containsKey(isbn)){
				LexileTitle lexileTitle = lexileInformation.get(isbn);
				String lexileCode = lexileTitle.getLexileCode();
				if (lexileCode.length() > 0){
					groupedWork.setLexileCode(this.translateSystemValue("lexile_code", lexileCode));
				}
				groupedWork.setLexileScore(lexileTitle.getLexileScore());
				groupedWork.addAwards(lexileTitle.getAwards());
				if (lexileTitle.getSeries().length() > 0){
					groupedWork.addSeries(lexileTitle.getSeries());
				}
				break;
			}
		}
	}

	private void loadLocalEnrichment(GroupedWorkSolr groupedWork) {
		//Load rating
		try{
			getRatingStmt.setString(1, groupedWork.getId());
			ResultSet ratingsRS = getRatingStmt.executeQuery();
			if (ratingsRS.next()){
				Float averageRating = ratingsRS.getFloat("averageRating");
				if (!ratingsRS.wasNull()){
					groupedWork.setRating(averageRating);
				}
			}
			ratingsRS.close();
		}catch (Exception e){
			logger.error("Unable to load local enrichment", e);
		}
	}

	private void updateGroupedWorkForPrimaryIdentifier(GroupedWorkSolr groupedWork, String type, String identifier) {
		groupedWork.addAlternateId(identifier);
		type = type.toLowerCase();
		switch (type) {
			case "overdrive":
				overDriveProcessor.processRecord(groupedWork, identifier);
				break;
			case "evoke":
				evokeProcessor.processRecord(groupedWork, identifier);
				break;
			default:
				if (ilsRecordProcessors.containsKey(type)) {
					ilsRecordProcessors.get(type).processRecord(groupedWork, identifier);
				}else{
					logger.debug("Could not find a record processor for type " + type);
				}
				break;

		}
	}

	private void updateGroupedWorkForSecondaryIdentifier(GroupedWorkSolr groupedWork, String type, String identifier) {
		type = type.toLowerCase();
		if (type.equals("isbn")){
			groupedWork.addIsbn(identifier);
		}else if (type.equals("upc")){
			groupedWork.addUpc(identifier);
		}else if (type.equals("order")){
			//Add as an alternate id
			groupedWork.addAlternateId(identifier);
		}else if (!type.equals("issn") && !type.equals("oclc")){
			logger.warn("Unknown identifier type " + type);
		}
	}

	/**
	 * System translation maps are used for things that are not customizable (or that shouldn't be customized)
	 * by library.  For example, translations of language codes, or things where MARC standards define the values.
	 *
	 * We can also load translation maps that are specific to an indexing profile.  That is done within
	 * the record processor itself.
	 */
	private void loadSystemTranslationMaps(){
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
			translationMaps.put(mapName, loadSystemTranslationMap(curFile));
		}
		for (File curFile : serverTranslationMapFiles){
			String mapName = curFile.getName().replace(".properties", "");
			mapName = mapName.replace("_map", "");
			translationMaps.put(mapName, loadSystemTranslationMap(curFile));
		}
	}

	private HashMap<String, String> loadSystemTranslationMap(File translationMapFile) {
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
	HashSet<String> missingTranslationMaps = new HashSet<>();
	public String translateSystemValue(String mapName, String value){
		if (value == null){
				return null;
			}
		HashMap<String, String> translationMap = translationMaps.get(mapName);
		String translatedValue;
		if (translationMap == null){
			if (!missingTranslationMaps.contains(mapName)) {
				missingTranslationMaps.add(mapName);
				logger.error("Unable to find system translation map for " + mapName);
			}
			translatedValue = value;
		}else{
			String lowerCaseValue = value.toLowerCase();
			if (translationMap.containsKey(lowerCaseValue)){
				translatedValue = translationMap.get(lowerCaseValue);
			}else{
				if (translationMap.containsKey("*")){
					translatedValue = translationMap.get("*");
				}else{
					String concatenatedValue = mapName + ":" + value;
					if (!unableToTranslateWarnings.contains(concatenatedValue)){
						if (fullReindex) {
							logger.warn("Could not translate '" + concatenatedValue + "'");
						}
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

	public LinkedHashSet<String> translateSystemCollection(String mapName, Set<String> values) {
		LinkedHashSet<String> translatedCollection = new LinkedHashSet<>();
		for (String value : values){
				String translatedValue = translateSystemValue(mapName, value);
				if (translatedValue != null) {
						translatedCollection.add(translatedValue);
					}
			}
		return  translatedCollection;
	}



	public void addWorkWithInvalidLiteraryForms(String id) {
		this.worksWithInvalidLiteraryForms.add(id);
	}

	public TreeSet<Scope> getScopes() {
		return this.scopes;
	}

	public Date getDateFirstDetected(String recordId){
		Long dateFirstDetected = null;
		try {
			getDateFirstDetectedStmt.setString(1, recordId);
			ResultSet dateFirstDetectedRS = getDateFirstDetectedStmt.executeQuery();
			if (dateFirstDetectedRS.next()) {
				dateFirstDetected = dateFirstDetectedRS.getLong("dateFirstDetected");
			}
		}catch (Exception e){
			logger.error("Error loading date first detected for " + recordId);
		}
		if (dateFirstDetected != null){
			return new Date(dateFirstDetected * 1000);
		}else {
			return null;
		}
	}

	public long processPublicUserLists() {
		UserListProcessor listProcessor = new UserListProcessor(this, vufindConn, logger, fullReindex, availableAtLocationBoostValue, ownedByLocationBoostValue);
		return listProcessor.processPublicUserLists(lastReindexTime, updateServer, solrServer);
	}
}
