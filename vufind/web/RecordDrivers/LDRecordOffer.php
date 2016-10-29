<?php
/**
 * Created by PhpStorm.
 * User: jabedo
 * Date: 10/29/2016
 * Time: 8:10 AM
 * Handles an offer for a related record
 */

class LDRecordOffer
{
    private $relatedRecord;
    public function __construct($record)
    {
        $this->relatedRecord = $record;
    }

    public function getOffers()
    {
        $offers = array();
        $offers[] = array(
            "availableAtOrFrom" => $this->getBranchUrl(), //Branch that owns the work(),
            "availability" => $this->getOfferAvailability(),
            'availableDeliveryMethod' => $this->getDeliveryMethod(),
            "itemOffered" => $this->getOfferLinkUrl(), //URL to the record
            "offeredBy" => $this->getLibraryUrl(), //URL to the library that owns the item
            "price" => '0',
            "@bookFormat" => $this->getBookFormat()
        );

        return $offers;
    }

    public function  getWorkType(){
        return $this->relatedRecord['format'];
    }
    function getOfferLinkUrl(){
        global $configArray;
        return $configArray['Site']['url'] . $this->relatedRecord['url'];
    }

    function getLibraryUrl()
    {
        global $configArray;
        $offerBy = array();
        global $library;
        $offerBy[] = array(
            "@type" => "Library",
            "@id" => $configArray['Site']['url'] . "Library/{$library->libraryId}/System",
            "name" => $library->displayName
        );
        return $offerBy;
    }

    function getBranchUrl()
    {
        global $configArray;
        global $library;
        $locations = new Location();
        $locations->libraryId = $library->libraryId;
        $locations->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
        $locations->find();
        $subLocations = array();
        while ($locations->fetch()){

            $subLocations[] = array(
                '@type' => 'Organization',
                'name' => $locations->displayName,
                '@id' => $configArray['Site']['url'] . "Library/{$locations->locationId}/Branch"

            );
        }
        return $subLocations;

    }

    function getDeliveryMethod()
    {
        if ($this->relatedRecord['isEContent']) {
            return 'DeliveryModeDirectDownload';
        } else {
            return 'DeliveryModePickUp';
        }

    }
    function getBookFormat()
    {
        /* AudiobookFormat
            EBook
            Hardcover
            Paperback */        if ( strcmp($this->relatedRecord['format'],'eAudiobook' ) )
            return "AudiobookFormat";
        if (strcmp($this->relatedRecord['formatCategory'],'eBook'))
            return "EBook";
        else {

        }
        return "Hardcover";
    }
    function getOfferAvailability()
    {
        if ($this->relatedRecord['inLibraryUseOnly']) {
            return 'InStoreOnly';
        }
        if ($this->relatedRecord['availableOnline']) {
            return 'OnlineOnly';
        }
        if ($this->relatedRecord['localAvailableCopies'] > 0) {
            return 'InStock';
        }

        if ($this->relatedRecord['groupedStatus'] != '') {
            switch (strtolower($this->relatedRecord['groupedStatus'])) {
                case 'checked out':
                    $availability = 'OutOfStock';
                    break;
                case 'on order':
                case 'in processing':
                    $availability = 'PreOrder';
                    break;
                case 'currently unavailable':
                    $availability = 'Discontinued';
                    break;
            }
            return $availability;
        }
        return "";
    }
}