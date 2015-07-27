package org.vufind;

import com.sun.istack.internal.NotNull;

import java.util.HashMap;
import java.util.regex.Pattern;

/**
 * Required information to determine what records are owned directly by a library or location
 *
 * Pika
 * User: Mark Noble
 * Date: 7/10/2015
 * Time: 10:49 AM
 */
public class OwnershipRule {
	private String recordType;

	private Pattern locationCodePattern;
	private Pattern subLocationCodePattern;

	public OwnershipRule(String recordType, @NotNull String locationCode, @NotNull String subLocationCode){
		this.recordType = recordType;

		if (locationCode.length() == 0){
			locationCode = ".*";
		}
		this.locationCodePattern = Pattern.compile(locationCode);
		if (subLocationCode.length() == 0){
			subLocationCode = ".*";
		}
		this.subLocationCodePattern = Pattern.compile(subLocationCode);
	}

	HashMap<String, Boolean> ownershipResults = new HashMap<>();
	public boolean isItemOwned(@NotNull String recordType, @NotNull String locationCode, @NotNull String subLocationCode){
		String key = recordType + "-" + locationCode + "-" + subLocationCode;
		if (ownershipResults.containsKey(key)){
			return ownershipResults.get(key);
		}
		boolean isOwned = false;
		if (this.recordType.equals(recordType)){
			isOwned = locationCodePattern.matcher(locationCode).lookingAt() && subLocationCodePattern.matcher(subLocationCode).lookingAt();;
		}
		ownershipResults.put(key, isOwned);
		return  isOwned;
	}
}
