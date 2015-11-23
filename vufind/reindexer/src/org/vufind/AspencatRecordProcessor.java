package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * ILS Indexing with customizations specific to Aspencat
 * Pika
 * User: Mark Noble
 * Date: 2/21/14
 * Time: 3:00 PM
 */
public class AspencatRecordProcessor extends IlsRecordProcessor {
	private HashSet<String> inTransitItems = new HashSet<>();
	public AspencatRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, ResultSet indexingProfileRS, Logger logger, boolean fullReindex) {
		super(indexer, vufindConn, configIni, indexingProfileRS, logger, fullReindex);

		//Connect to the AspenCat database
		Connection kohaConn;
		try {
			String kohaConnectionJDBC = "jdbc:mysql://" +
					Util.cleanIniValue(configIni.get("Catalog", "db_host")) +
					"/" + Util.cleanIniValue(configIni.get("Catalog", "db_name") +
					"?user=" + Util.cleanIniValue(configIni.get("Catalog", "db_user")) +
					"&password=" + Util.cleanIniValue(configIni.get("Catalog", "db_pwd")) +
					"&useUnicode=yes&characterEncoding=UTF-8");
			kohaConn = DriverManager.getConnection(kohaConnectionJDBC);

			//Get a list of all items that are in transit
			PreparedStatement getInTransitItemsStmt = kohaConn.prepareStatement("SELECT itemnumber from reserves WHERE found = 'T'");
			ResultSet inTransitItemsRS = getInTransitItemsStmt.executeQuery();
			while (inTransitItemsRS.next()){
				inTransitItems.add(inTransitItemsRS.getString("itemnumber"));
			}
		} catch (Exception e) {
			logger.error("Error connecting to koha database ", e);
			//System.exit(1);
		}

	}

	@Override
	protected boolean isItemAvailable(ItemInfo itemInfo) {
		if (inTransitItems.contains(itemInfo.getItemIdentifier())){
			return false;
		}
		return itemInfo.getStatusCode().equals("On Shelf") ||
				itemInfo.getStatusCode().equals("Library Use Only");
	}

	@Override
	public void loadPrintFormatInformation(RecordInfo recordInfo, Record record) {
		Set<String> printFormatsRaw = getFieldList(record, itemTag + iTypeSubfield);
		HashSet<String> printFormats = new HashSet<>();
		for (String curFormat : printFormatsRaw){
			printFormats.add(curFormat.toLowerCase());
		}

		HashSet<String> translatedFormats = translateCollection("format", printFormats, recordInfo.getRecordIdentifier());

		if (translatedFormats.size() == 0){
			//We didn't get any formats from the collections, get formats from the base method (007, 008, etc).
			super.loadPrintFormatInformation(recordInfo, record);
		} else{
			HashSet<String> translatedFormatCategories = translateCollection("format_category", printFormats, recordInfo.getRecordIdentifier());
			recordInfo.addFormats(translatedFormats);
			recordInfo.addFormatCategories(translatedFormatCategories);
			Long formatBoost = 0L;
			HashSet<String> formatBoosts = translateCollection("format_boost", printFormats, recordInfo.getRecordIdentifier());
			for (String tmpFormatBoost : formatBoosts){
				if (Util.isNumeric(tmpFormatBoost)) {
					Long tmpFormatBoostLong = Long.parseLong(tmpFormatBoost);
					if (tmpFormatBoostLong > formatBoost) {
						formatBoost = tmpFormatBoostLong;
					}
				}
			}
			recordInfo.setFormatBoost(formatBoost);
		}
	}

	private HashSet<String> additionalStatuses = new HashSet<>();
	protected String getItemStatus(DataField itemField){
		String itemIdentifier = getItemSubfieldData(itemRecordNumberSubfieldIndicator, itemField);
		if (inTransitItems.contains(itemIdentifier)){
			return "In Transit";
		}
		//Determining status for Koha relies on a number of different fields
		String status = getStatusFromSubfield(itemField, '0', "Withdrawn");
		if (status != null) return status;

		status = getStatusFromSubfield(itemField, '1', "Lost");
		if (status != null) return status;

		status = getStatusFromSubfield(itemField, '4', "Damaged");
		if (status != null) return status;

		status = getStatusFromSubfield(itemField, 'q', "Checked Out");
		if (status != null) return status;

		status = getStatusFromSubfield(itemField, '7', "Library Use Only");
		if (status != null) return status;

		status = getStatusFromSubfield(itemField, 'k', null);
		if (status != null) return status;

		return "On Shelf";
	}

	private String getStatusFromSubfield(DataField itemField, char subfield, String defaultStatus) {
		if (itemField.getSubfield(subfield) != null){
			String fieldData = itemField.getSubfield(subfield).getData();
			if (!fieldData.equals("0")) {
				if (fieldData.equals("1")) {
					return defaultStatus;
				}else{
					if (subfield == 'q'){
						if (fieldData.matches("\\d{4}-\\d{2}-\\d{2}")){
							return "Checked Out";
						}
					}else if (subfield == '1'){
						switch (fieldData) {
							case "lost":
								return "Lost";
							case "missing":
								return "Missing";
							case "longoverdue":
								return "Long Overdue";
							case "trace":
								return "Trace";
						}
					}else if (subfield == '7') {
						switch (fieldData) {
							case "-1":
								return "On Order";
						}
						//There are several library use only statuses that we do not care about right now.
						return null;
					}else if (subfield == 'k') {
						switch (fieldData) {
							case "CATALOGED":
							case "READY":
								return null;
							case "BINDERY":
								return "Bindery";
							case "IN REPAIRS":
								return "Repair";
							case "trace":
								return "Trace";
							default:
								//There are several reserve statuses that we don't care about, just ignore silently.
								return null;
						}
					}
					String status = "|" + subfield + "-" + fieldData;
					if (!additionalStatuses.contains(status)){
						logger.warn("Found new status " + status);
						additionalStatuses.add(status);
					}
				}
			}
		}
		return null;
	}

	protected void loadUnsuppressedPrintItems(GroupedWorkSolr groupedWork, RecordInfo recordInfo, String identifier, Record record){
		List<DataField> itemRecords = getDataFields(record, itemTag);
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField)){
				//Check to see if the item has an eContent indicator
				boolean isEContent = false;
				if (itemField.getSubfield(iTypeSubfield) != null){
					String iType = itemField.getSubfield(iTypeSubfield).getData().toLowerCase();
					if (iType.equals("ebook") || iType.equals("eaudio") || iType.equals("online") || iType.equals("oneclick")){
						isEContent = true;
					}
				}
				if (!isEContent){
					getPrintIlsItem(groupedWork, recordInfo, record, itemField);
				}
			}
		}
	}

	protected List<RecordInfo> loadUnsuppressedEContentItems(GroupedWorkSolr groupedWork, String identifier, Record record){
		List<DataField> itemRecords = getDataFields(record, itemTag);
		List<RecordInfo> unsuppressedEcontentRecords = new ArrayList<>();
		for (DataField itemField : itemRecords){
			if (!isItemSuppressed(itemField)){
				//Check to see if the item has an eContent indicator
				boolean isEContent = false;
				boolean isOverDrive = false;
				boolean isHoopla = false;
				if (itemField.getSubfield(iTypeSubfield) != null){
					String iType = itemField.getSubfield(iTypeSubfield).getData().toLowerCase();
					if (iType.equals("ebook") || iType.equals("eaudio") || iType.equals("online") || iType.equals("oneclick")){
						isEContent = true;
						String sourceType = getSourceType(record, itemField);
						if (sourceType != null){
							sourceType = sourceType.toLowerCase().trim();
							if (sourceType.contains("overdrive")) {
								isOverDrive = true;
							} else if (sourceType.contains("hoopla")) {
								isHoopla = true;
							} else {
								logger.debug("Found eContent Source " + sourceType);
							}
						}else {
							//Need to figure out how to load a source
							logger.warn("Did not find an econtent source for " + identifier);
						}
					}
				}
				if (!isOverDrive && !isHoopla && isEContent){
					RecordInfo eContentRecord = getEContentIlsRecord(groupedWork, record, identifier, itemField);
					if (eContentRecord != null) {
						unsuppressedEcontentRecords.add(eContentRecord);
					}
				}
			}
		}
		return unsuppressedEcontentRecords;
	}

	@Override
	protected String getEContentSharing(ItemInfo ilsEContentItem, DataField itemField) {
		if (ilsEContentItem.getLocationCode().equals("ONLINE")) {
			return "shared";
		}else{
			return "library";
		}
	}

	@Override
	protected void loadEContentFormatInformation(Record record, RecordInfo econtentRecord, ItemInfo econtentItem) {
		if (econtentItem.getITypeCode() != null) {
			String iType = econtentItem.getITypeCode().toLowerCase();
			String translatedFormat = translateValue("format", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatCategory = translateValue("format_category", iType, econtentRecord.getRecordIdentifier());
			String translatedFormatBoost = translateValue("format_boost", iType, econtentRecord.getRecordIdentifier());
			econtentItem.setFormat(translatedFormat);
			econtentItem.setFormatCategory(translatedFormatCategory);
			econtentRecord.setFormatBoost(Long.parseLong(translatedFormatBoost));
		}
	}

	protected String getSourceType(Record record, DataField itemField) {
		//Try to figure out the source
		//Try |e
		String sourceType = null;
		if (itemField.getSubfield('e') != null){
			sourceType = itemField.getSubfield('e').getData();
		}else{
			//Try 949a
			DataField field949 = (DataField)record.getVariableField("949");
			if (field949 != null && field949.getSubfield('a') != null){
				sourceType = field949.getSubfield('a').getData();
			}else{
				DataField field037 = (DataField)record.getVariableField("037");
				if (field037 != null && field037.getSubfield('b') != null){
					sourceType = field037.getSubfield('b').getData();
				}else{
					List<VariableField> urlFields = record.getVariableFields("856");
					for (VariableField urlField : urlFields){
						DataField urlDataField = (DataField)urlField;
						if (urlDataField.getSubfield('3') != null) {
							if (urlDataField.getIndicator1() == '4' || urlDataField.getIndicator1() == ' ') {
								//Technically, should not include indicator 2 of 2, but AspenCat has lots of records with an indicator 2 of 2 that are valid.
								if (urlDataField.getIndicator2() == ' ' || urlDataField.getIndicator2() == '0' || urlDataField.getIndicator2() == '1' || urlDataField.getIndicator2() == '2') {
									sourceType = urlDataField.getSubfield('3').getData().trim();
									break;
								}
							}
						}
					}

					//If the source type is still null, try the location of the item
					if (sourceType == null){
						//Try the location for the item
						if (itemField.getSubfield('a') != null){
							sourceType = itemField.getSubfield('a').getData();
						}
					}
				}
			}
		}
		return sourceType;
	}

	protected boolean isItemSuppressed(DataField curItem) {
		boolean suppressed = false;
		if (curItem.getSubfield('i') != null) {
			suppressed = curItem.getSubfield('i').getData().equals("1");
		}
		if (!suppressed && curItem.getSubfield(iTypeSubfield) != null){
			suppressed = curItem.getSubfield(iTypeSubfield).getData().equalsIgnoreCase("ill");
		}
		if (suppressed){
			return true;
		} else {
			return super.isItemSuppressed(curItem);
		}
	}

	protected String getShelfLocationForItem(ItemInfo itemInfo, DataField itemField, String identifier) {
		/*String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
		String location = translateValue("location", locationCode);*/
		String location = "";
		String subLocationCode = getItemSubfieldData(subLocationSubfield, itemField);
		if (subLocationCode != null && subLocationCode.length() > 0){
			location += translateValue("sub_location", subLocationCode, identifier);
		}else{
			String locationCode = getItemSubfieldData(locationSubfieldIndicator, itemField);
			location = translateValue("location", locationCode, identifier);
		}
		String shelvingLocation = getItemSubfieldData(shelvingLocationSubfield, itemField);
		if (shelvingLocation != null && shelvingLocation.length() > 0){
			if (location.length() > 0){
				location += " - ";
			}
			location += translateValue("shelf_location", shelvingLocation, identifier);
		}
		return location;
	}

}
