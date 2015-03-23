package org.vufind;

import java.util.HashSet;

/**
 * A representation of an Item defined in the ILS system
 * VuFind-Plus
 * User: Mark Noble
 * Date: 6/16/2014
 * Time: 9:17 AM
 */
public class IlsItem {
	protected String location;
	private String iType;

	private String dateCreated;
	private String callNumberPreStamp;
	private String callNumber;
	private String callNumberCutter;
	private String itemRecordNumber;
	private String collection;

	private HashSet<Scope> relatedScopes = new HashSet<Scope>();
	private HashSet<String> directScopeNames = new HashSet<String>();
	private String recordIdentifier;
	private String volume;

	public String getDateCreated() {
		return dateCreated;
	}

	public void setDateCreated(String dateCreated) {
		this.dateCreated = dateCreated;
	}

	public String getLocation() {
		return location;
	}

	public void setLocation(String location) {
		this.location = location;
	}

	public String getiType() {
		return iType;
	}

	public void setiType(String iType) {
		if (iType != null && iType.equals("null")){
			iType = null;
		}
		this.iType = iType;
	}

	public String getCallNumberPreStamp() {
		return callNumberPreStamp;
	}

	public void setCallNumberPreStamp(String callNumberPreStamp) {
		this.callNumberPreStamp = callNumberPreStamp;
	}

	public String getCallNumber() {
		return callNumber;
	}

	public void setCallNumber(String callNumber) {
		this.callNumber = callNumber;
	}

	public String getCallNumberCutter() {
		return callNumberCutter;
	}

	public void setCallNumberCutter(String callNumberCutter) {
		this.callNumberCutter = callNumberCutter;
	}

	public String getFullCallNumber() {
		StringBuilder fullCallNumber = new StringBuilder();
		if (this.callNumberPreStamp != null) {
			fullCallNumber.append(this.callNumberPreStamp);
		}
		if (this.callNumber != null){
			if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
				fullCallNumber.append(' ');
			}
			fullCallNumber.append(this.callNumber);
		}
		if (this.callNumberCutter != null){
			if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
				fullCallNumber.append(' ');
			}
			fullCallNumber.append(this.callNumberCutter);
		}
		if (this.volume != null){
			if (fullCallNumber.length() > 0 && fullCallNumber.charAt(fullCallNumber.length() - 1) != ' '){
				fullCallNumber.append(' ');
			}
			fullCallNumber.append(this.volume);
		}
		return fullCallNumber.toString().trim();
	}

	public String getItemRecordNumber() {
		return itemRecordNumber;
	}

	public void setItemRecordNumber(String itemRecordNumber) {
		this.itemRecordNumber = itemRecordNumber;
	}

	public HashSet<Scope> getRelatedScopes() {
		return relatedScopes;
	}

	public void addRelatedScope(Scope scope){
		relatedScopes.add(scope);
	}

	public HashSet<String> getScopesThisItemIsDirectlyIncludedIn() {
		return directScopeNames;
	}

	public void addScopeThisItemIsDirectlyIncludedIn(String scopeName){
		directScopeNames.add(scopeName);
	}

	public String getRecordIdentifier() {
		return recordIdentifier;
	}

	public void setRecordIdentifier(String recordIdentifier) {
		this.recordIdentifier = recordIdentifier;
	}

	public HashSet<String> getCompatiblePTypes() {
		HashSet<String> compatiblePTypes = new HashSet<String>();
		for (Scope scope : relatedScopes)       {
			compatiblePTypes.addAll(scope.getRelatedPTypes());
		}
		return compatiblePTypes;
	}

	public HashSet<String> getValidSubdomains() {
		HashSet<String> subdomains = new HashSet<String>();
		for (Scope curScope : relatedScopes){
			subdomains.add(curScope.getScopeName());
		}
		return subdomains;
	}

	public HashSet<String> getValidLibraryFacets() {
		HashSet<String> subdomains = new HashSet<String>();
		for (Scope curScope : relatedScopes){
			subdomains.add(curScope.getFacetLabel());
		}
		return subdomains;
	}

	public String getCollection() {
		return collection;
	}

	public void setCollection(String collection) {
		this.collection = collection;
	}

	public void setVolume(String volume) {
		this.volume = volume;
	}
}
