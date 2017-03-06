package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.marc.Record;

import java.io.ByteArrayInputStream;
import java.io.File;
import java.io.InputStream;
import java.util.Date;
import java.util.HashSet;
import java.util.Set;

/**
 * Extracts data from Hoopla Marc records to fill out information within the work to be indexed.
 *
 * Pika
 * User: Mark Noble
 * Date: 12/17/2014
 * Time: 10:30 AM
 */
public class HooplaProcessor extends MarcRecordProcessor {
	private String individualMarcPath;
	public HooplaProcessor(GroupedWorkIndexer indexer, Ini configIni, Logger logger) {
		super(indexer, logger);

		individualMarcPath = configIni.get("Hoopla", "individualMarcPath");
	}

	@Override
	public void processRecord(GroupedWorkSolr groupedWork, String identifier) {
		Record record = loadMarcRecordFromDisk(identifier);

		if (record != null) {
			try {
				updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier);
			} catch (Exception e) {
				logger.error("Error updating solr based on hoopla marc record", e);
			}
		}
	}

	public Record loadMarcRecordFromDisk(String identifier){
		Record record = null;
		//Load the marc record from disc
		String firstChars = identifier.substring(0, 7);
		String basePath = individualMarcPath + "/" + firstChars;
		String individualFilename = basePath + "/" + identifier + ".mrc";
		File individualFile = new File(individualFilename);
		try {
			byte[] fileContents = Util.readFileBytes(individualFilename);
			InputStream inputStream = new ByteArrayInputStream(fileContents);
			//FileInputStream inputStream = new FileInputStream(individualFile);
			MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
			if (marcReader.hasNext()){
				record = marcReader.next();
			}
			inputStream.close();
		} catch (Exception e) {
			logger.error("Error reading data from hoopla file " + individualFile.toString(), e);
		}
		return record;
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		//First get format
		String format = getFirstFieldVal(record, "099a");
		format = format.replace(" hoopla", "");

		//Do updates based on the overall bib (shared regardless of scoping)
		updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, null, identifier, format);

		//Do special processing for Hoopla which does not have individual items within the record
		//Instead, each record has essentially unlimited items that can be used at one time.
		//There are also not multiple formats within a record that we would need to split out.

		String formatCategory = indexer.translateSystemValue("format_category_hoopla", format, identifier);
		String formatBoostStr = indexer.translateSystemValue("format_boost_hoopla", format, identifier);
		Long formatBoost = Long.parseLong(formatBoostStr);

		String fullDescription = Util.getCRSeparatedString(getFieldList(record, "520a"));
		groupedWork.addDescription(fullDescription, format);

		//Load editions
		Set<String> editions = getFieldList(record, "250a");
		String primaryEdition = null;
		if (editions.size() > 0) {
			primaryEdition = editions.iterator().next();
		}
		groupedWork.addEditions(editions);

		//Load publication details
		//Load publishers
		Set<String> publishers = this.getPublishers(record);
		groupedWork.addPublishers(publishers);
		String publisher = null;
		if (publishers.size() > 0){
			publisher = publishers.iterator().next();
		}

		//Load publication dates
		Set<String> publicationDates = this.getPublicationDates(record);
		groupedWork.addPublicationDates(publicationDates);
		String publicationDate = null;
		if (publicationDates.size() > 0){
			publicationDate = publicationDates.iterator().next();
		}

		//Load physical description
		Set<String> physicalDescriptions = getFieldList(record, "300abcefg:530abcd");
		String physicalDescription = null;
		if (physicalDescriptions.size() > 0){
			physicalDescription = physicalDescriptions.iterator().next();
		}
		groupedWork.addPhysical(physicalDescriptions);

		//Setup the per Record information
		RecordInfo recordInfo = groupedWork.addRelatedRecord("hoopla", identifier);
		recordInfo.setFormatBoost(formatBoost);
		recordInfo.setEdition(primaryEdition);
		recordInfo.setPhysicalDescription(physicalDescription);
		recordInfo.setPublicationDate(publicationDate);
		recordInfo.setPublisher(publisher);

		//Load Languages
		HashSet<RecordInfo> records = new HashSet<>();
		records.add(recordInfo);
		loadLanguageDetails(groupedWork, record, records, identifier);

		//For Hoopla, we just have a single item always
		ItemInfo itemInfo = new ItemInfo();
		itemInfo.setIsEContent(true);
		itemInfo.setNumCopies(1);
		itemInfo.setFormat(format);
		itemInfo.setFormatCategory(formatCategory);
		itemInfo.seteContentSource("Hoopla");
		itemInfo.seteContentProtectionType("Always Available");
		itemInfo.setShelfLocation("Online Hoopla Collection");
		itemInfo.setCallNumber("Online Hoopla");
		itemInfo.setSortableCallNumber("Online Hoopla");
		itemInfo.seteContentSource("Hoopla");
		itemInfo.seteContentProtectionType("Always Available");
		itemInfo.setDetailedStatus("Available Online");
		loadEContentUrl(record, itemInfo);
		Date dateAdded = indexer.getDateFirstDetected(identifier);
		itemInfo.setDateAdded(dateAdded);

		recordInfo.addItem(itemInfo);

		//Figure out ownership information
		for (Scope curScope: indexer.getScopes()){
			if (curScope.isItemPartOfScope("hoopla", "", "", false, false, true)){
				ScopingInfo scopingInfo = itemInfo.addScope(curScope);
				scopingInfo.setAvailable(true);
				scopingInfo.setStatus("Available Online");
				scopingInfo.setGroupedStatus("Available Online");
				scopingInfo.setHoldable(false);
				if (curScope.isLocationScope()) {
					scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope("hoopla", "", ""));
					if (curScope.getLibraryScope() != null) {
						scopingInfo.setLibraryOwned(curScope.getLibraryScope().isItemOwnedByScope("hoopla", "", ""));
					}
				}
				if (curScope.isLibraryScope()) {
					 scopingInfo.setLibraryOwned(curScope.isItemOwnedByScope("hoopla", "", ""));
				}
			}
		}

		//TODO: Determine how to find popularity for Hoopla titles.
		//Right now the information is not exported from Hoopla.  We could load based on clicks
		//From Pika to Hoopla, but that wouldn't count plays directly within the app
		//(which may be ok).
		groupedWork.addPopularity(1);

		//Related Record
		groupedWork.addRelatedRecord("hoopla", identifier);
	}
}
