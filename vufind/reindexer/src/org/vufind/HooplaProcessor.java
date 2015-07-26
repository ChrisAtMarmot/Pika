package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.MarcPermissiveStreamReader;
import org.marc4j.marc.Record;

import java.io.File;
import java.io.FileInputStream;
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
		//Load the marc record from disc
		String firstChars = identifier.substring(0, 7);
		String basePath = individualMarcPath + "/" + firstChars;
		String individualFilename = basePath + "/" + identifier + ".mrc";
		File individualFile = new File(individualFilename);
		try {
			FileInputStream inputStream = new FileInputStream(individualFile);
			MarcPermissiveStreamReader marcReader = new MarcPermissiveStreamReader(inputStream, true, true, "UTF-8");
			if (marcReader.hasNext()){
				try{
					Record record = marcReader.next();
					updateGroupedWorkSolrDataBasedOnMarc(groupedWork, record, identifier);
				}catch (Exception e) {
					logger.error("Error updating solr based on hoopla marc record", e);
				}
			}
			inputStream.close();
		} catch (Exception e) {
			logger.error("Error reading data from hoopla file " + individualFile.toString(), e);
		}
	}

	@Override
	protected void updateGroupedWorkSolrDataBasedOnMarc(GroupedWorkSolr groupedWork, Record record, String identifier) {
		//Do updates based on the overall bib (shared regardless of scoping)
		updateGroupedWorkSolrDataBasedOnStandardMarcData(groupedWork, record, null);

		//Do special processing for Hoopla which does not have individual items within the record
		//Instead, each record has essentially unlimited items that can be used at one time.
		//There are also not multiple formats within a record that we would need to split out.

		//First get format
		String format = getFirstFieldVal(record, "099a");
		format = format.replace(" hoopla", "");
		String formatCategory = indexer.translateSystemValue("format_category_hoopla", format);
		String formatBoostStr = indexer.translateSystemValue("format_boost_hoopla", format);
		Long formatBoost = Long.parseLong(formatBoostStr);

		//Load editions
		Set<String> editions = getFieldList(record, "250a");
		String primaryEdition = null;
		if (editions.size() > 0) {
			primaryEdition = editions.iterator().next();
		}
		groupedWork.addEditions(editions);

		//Load Languages
		Set <String> languages = getFieldList(record, "008[35-37]:041a:041d:041j");
		HashSet<String> translatedLanguages = indexer.translateSystemCollection("language", languages);
		String primaryLanguage = null;
		for (String language : languages){
			if (primaryLanguage == null){
				primaryLanguage = indexer.translateSystemValue("language", language);
			}
			String languageBoost = indexer.translateSystemValue("language_boost", language);
			if (languageBoost != null){
				Long languageBoostVal = Long.parseLong(languageBoost);
				groupedWork.setLanguageBoost(languageBoostVal);
			}
			String languageBoostEs = indexer.translateSystemValue("language_boost_es", language);
			if (languageBoostEs != null){
				Long languageBoostVal = Long.parseLong(languageBoostEs);
				groupedWork.setLanguageBoostSpanish(languageBoostVal);
			}
		}
		groupedWork.setLanguages(translatedLanguages);

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
		recordInfo.setPrimaryLanguage(primaryLanguage);

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
		itemInfo.seteContentSharing("library");
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
				scopingInfo.setLocallyOwned(curScope.isItemOwnedByScope("hoopla", "", ""));
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
