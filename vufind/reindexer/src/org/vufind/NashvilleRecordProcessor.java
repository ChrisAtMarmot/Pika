package org.vufind;

import org.apache.log4j.Logger;
import org.ini4j.Ini;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;

import java.sql.Connection;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * ILS Indexing with customizations specific to Nashville
 * VuFind-Plus
 * User: Mark Noble
 * Date: 2/21/14
 * Time: 3:00 PM
 */
public class NashvilleRecordProcessor extends IlsRecordProcessor {
	public NashvilleRecordProcessor(GroupedWorkIndexer indexer, Connection vufindConn, Ini configIni, Logger logger) {
		super(indexer, vufindConn, configIni, logger);
	}

	@Override
	public void loadPrintFormatInformation(IlsRecord ilsRecord, Record record) {
		Set<String> printFormatsRaw = getFieldList(record, "998d");
		Set<String> printFormats = new HashSet<String>();
		for (String curFormat : printFormatsRaw){
			printFormats.add(curFormat.toLowerCase());
		}



		HashSet<String> translatedFormats = indexer.translateCollection("format", printFormats);
		HashSet<String> translatedFormatCategories = indexer.translateCollection("format_category", printFormats);
		ilsRecord.addFormats(translatedFormats);
		ilsRecord.addFormatCategories(translatedFormatCategories);
		Long formatBoost = 0L;
		HashSet<String> formatBoosts = indexer.translateCollection("format_boost", printFormats);
		for (String tmpFormatBoost : formatBoosts){
			if (Util.isNumeric(tmpFormatBoost)) {
				Long tmpFormatBoostLong = Long.parseLong(tmpFormatBoost);
				if (tmpFormatBoostLong > formatBoost) {
					formatBoost = tmpFormatBoostLong;
				}
			}
		}
		ilsRecord.setFormatBoost(formatBoost);
	}

	@Override
	protected boolean isItemAvailable(PrintIlsItem ilsRecord) {
		boolean available = false;
		String status = ilsRecord.getStatus();
		String dueDate = ilsRecord.getDateDue() == null ? "" : ilsRecord.getDateDue();
		String availableStatus = "-do";
		if (availableStatus.indexOf(status.charAt(0)) >= 0) {
			if (dueDate.length() == 0 || dueDate.trim().equals("-  -")) {
				available = true;
			}
		}
		return available;
	}

	protected boolean isItemSuppressed(DataField curItem) {
		Subfield locationCodeSubfield = curItem.getSubfield(locationSubfieldIndicator);
		if (locationCodeSubfield == null)                                                 {
			return false;
		}
		String locationCode = locationCodeSubfield.getData().trim();

		return locationCode.matches(".*sup");
	}

	@Override
	protected boolean isOrderItemValid(String status, String code3) {
		if (code3 == null || code3.equals("s")) {
			return false;
		}else{
			return status.equals("o") || status.equals("1") || status.equals("a");
		}
	}
}
