package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.Record;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.*;

/**
 * Description goes here
 * Pika
 * User: Mark Noble
 * Date: 12/15/2015
 * Time: 3:03 PM
 */
public class SideLoadedEContentProcessor extends IlsRecordProcessor{
	private PreparedStatement getDateAddedStmt;
	public SideLoadedEContentProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, configIni, indexingProfileRS, logger, fullReindex);

		try{
			getDateAddedStmt = vufindConn.prepareStatement("SELECT dateFirstDetected FROM ils_marc_checksums WHERE ilsId = ?", ResultSet.TYPE_FORWARD_ONLY, ResultSet.CONCUR_READ_ONLY);
		}catch (Exception e){
			logger.error("Unable to setup prepared statement for date added to catalog");
		}
	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		return true;
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		//For ILS Records, we can create multiple different records, one for print and order items,
		//and one or more for eContent items.
		HashSet<RecordInfo> allRelatedRecords = new HashSet<>();

		try{
			//Now look for eContent items
			RecordInfo recordInfo = loadEContentRecord(groupedWork, identifier, record);
			allRelatedRecords.add(recordInfo);

			//Do updates based on the overall bib (shared regardless of scoping)
			String primaryFormat = null;
			for (RecordInfo ilsRecord : allRelatedRecords) {
				primaryFormat = ilsRecord.getPrimaryFormat();
				if (primaryFormat != null){
					break;
				}
			}
			if (primaryFormat == null) primaryFormat = "Unknown";
			updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, recordInfo.getRelatedItems(), identifier, primaryFormat);

			//Special processing for ILS Records
			String fullDescription = Util.getCRSeparatedString(getFieldList(record, "520a"));
			for (RecordInfo ilsRecord : allRelatedRecords) {
				String primaryFormatForRecord = ilsRecord.getPrimaryFormat();
				if (primaryFormatForRecord == null){
					primaryFormatForRecord = "Unknown";
				}
				groupedWork.addDescription(fullDescription, primaryFormatForRecord);
			}
			loadEditions(groupedWork, record, allRelatedRecords);
			loadPhysicalDescription(groupedWork, record, allRelatedRecords);
			loadLanguageDetails(groupedWork, record, allRelatedRecords, identifier);
			loadPublicationDetails(groupedWork, record, allRelatedRecords);
			loadSystemLists(groupedWork, record);

			if (record.getControlNumber() != null){
				groupedWork.addKeywords(record.getControlNumber());
			}

			//Do updates based on items
			loadPopularity(groupedWork, identifier);

			groupedWork.addHoldings(1);
		}catch (Exception e){
			logger.error("Error updating grouped work for MARC record with identifier " + identifier, e);
		}
	}

	protected RecordInfo loadEContentRecord(GroupedWorkSolr groupedWork, String identifier, Record record){
		//We will always have a single record
		return getEContentIlsRecord(groupedWork, record, identifier);
	}

	private RecordInfo getEContentIlsRecord(GroupedWorkSolr groupedWork, Record record, String identifier) {
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);

		loadDateAdded(identifier, itemInfo);
		String itemLocation = profileType;
		itemInfo.setLocationCode(profileType);
		//No itypes for Side loaded econtent
		//itemInfo.setITypeCode();
		//itemInfo.setIType();
		itemInfo.setCallNumber("Online " + profileType);
		itemInfo.setItemIdentifier(identifier);
		itemInfo.setShelfLocation(profileType);

		//No Collection for Side loaded eContent
		//itemInfo.setCollection(translateValue("collection", getItemSubfieldData(collectionSubfield, itemField), identifier));

		itemInfo.seteContentSource(profileType);
		itemInfo.seteContentProtectionType("external");

		RecordInfo relatedRecord = groupedWork.addRelatedRecord(profileType, identifier);
		relatedRecord.addItem(itemInfo);
		loadEContentUrl(record, itemInfo);

		loadEContentFormatInformation(record, relatedRecord, itemInfo);

		itemInfo.setDetailedStatus("Available Online");
		//Determine which scopes this title belongs to
		for (Scope curScope : indexer.getScopes()){
			if (curScope.isItemPartOfScope(profileType, itemLocation, "", false, false, true)){
				ScopingInfo scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(true);
				scopingInfo.setStatus("Available Online");
				scopingInfo.setGroupedStatus("Available Online");
				scopingInfo.setHoldable(false);
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope(profileType, itemLocation, ""));
				}
				if (curScope.isLibraryScope()) {
					scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope(profileType, itemLocation, ""));
				}
			}
		}

		return relatedRecord;
	}

	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		if (formatSource.equals("specified")){
			HashSet<String> translatedFormats = new HashSet<>();
			translatedFormats.add(specifiedFormat);
			HashSet<String> translatedFormatCategories = new HashSet<>();
			translatedFormatCategories.add(specifiedFormatCategory);
			econtentRecord.addFormats(translatedFormats);
			econtentRecord.addFormatCategories(translatedFormatCategories);
			econtentRecord.setFormatBoost(specifiedFormatBoost);
		} else {
			LinkedHashSet<String> printFormats = getFormatsFromBib(record, econtentRecord);
			if (this.translationMaps.size() > 0){
				String firstFormat = printFormats.iterator().next();
				econtentItem.setFormat(translateValue("format", firstFormat, econtentRecord.getFullIdentifier()));
				econtentItem.setFormatCategory(translateValue("format_category", firstFormat, econtentRecord.getFullIdentifier()));
				String formatBoostStr = translateValue("format_boost", firstFormat, econtentRecord.getFullIdentifier());
				try {
					Long formatBoost = Long.parseLong(formatBoostStr);
					econtentRecord.setFormatBoost(formatBoost);
				}catch (Exception e){
					logger.warn("Unable to parse format boost " + formatBoostStr + " for format " + firstFormat + " " + econtentRecord.getFullIdentifier());
					econtentRecord.setFormatBoost(1);
				}
			}
			//Convert formats from print to eContent version
			for (String format : printFormats) {
				if (format.equalsIgnoreCase("Book") || format.equalsIgnoreCase("LargePrint") || format.equalsIgnoreCase("GraphicNovel")) {
					econtentItem.setFormat("eBook");
					econtentItem.setFormatCategory("Books");
					econtentRecord.setFormatBoost(10);
				} else if (format.equalsIgnoreCase("SoundRecording") || format.equalsIgnoreCase("SoundDisc")) {
					econtentItem.setFormat("eAudiobook");
					econtentItem.setFormatCategory("Audio Books");
					econtentRecord.setFormatBoost(8);
				} else if (format.equalsIgnoreCase("MusicRecording")) {
					econtentItem.setFormat("eMusic");
					econtentItem.setFormatCategory("Music");
					econtentRecord.setFormatBoost(5);
				} else if (format.equalsIgnoreCase("Movies")) {
					econtentItem.setFormat("eVideo");
					econtentItem.setFormatCategory("Movies");
					econtentRecord.setFormatBoost(10);
				} else {
					logger.warn("Could not find appropriate eContent format for " + format + " while side loading eContent " + econtentRecord.getFullIdentifier());
				}
			}
		}
	}

	protected void loadDateAdded(String identfier, ItemInfo itemInfo) {
		try {
			getDateAddedStmt.setString(1, identfier);
			ResultSet getDateAddedRS = getDateAddedStmt.executeQuery();
			if (getDateAddedRS.next()) {
				long timeAdded = getDateAddedRS.getLong(1);
				Date curDate = new Date(timeAdded * 1000);
				itemInfo.setDateAdded(curDate);
				getDateAddedRS.close();
			}else{
				logger.debug("Could not determine date added for " + identfier);
			}
		}catch (Exception e){
			logger.error("Unable to load date added for " + identfier);
		}
	}
}
