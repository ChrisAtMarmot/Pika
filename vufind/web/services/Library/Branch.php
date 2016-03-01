<?php

/**
 * Displays information about a particular library branch
 * Library in Schema.org terminology
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 2/27/2016
 * Time: 2:30 PM
 */
class Branch extends Action{

	function launch() {
		global $interface;
		global $configArray;

		$location = new Location();
		$location->locationId = $_REQUEST['id'];
		if ($location->find(true)){
			$interface->assign('location', $location);

			$mapAddress = urlencode(preg_replace('/\r\n|\r|\n/', '+', $location->address));
			$hours = $location->getHours();
			$hoursSemantic = array();
			foreach ($hours as $key => $hourObj){
				if (!$hourObj->closed){
					$hourString = $hourObj->open;
					list($hour, $minutes) = explode(':', $hourString);
					if ($hour < 12){
						$hourObj->open .= ' AM';
					}elseif ($hour == 12){
						$hourObj->open = 'Noon';
					}elseif ($hour == 24){
						$hourObj->open = 'Midnight';
					}else{
						$hour -= 12;
						$hourObj->open = "$hour:$minutes PM";
					}
					$hourString = $hourObj->close;
					list($hour, $minutes) = explode(':', $hourString);
					if ($hour < 12){
						$hourObj->close .= ' AM';
					}elseif ($hour == 12){
						$hourObj->close = 'Noon';
					}elseif ($hour == 24){
						$hourObj->close = 'Midnight';
					}else{
						$hour -= 12;
						$hourObj->close = "$hour:$minutes PM";
					}
					$hoursSemantic[] = array(
						'@type' => 'OpeningHoursSpecification',
						'opens' => $hourObj->open,
						'closes' => $hourObj->close,
						'dayOfWeek' => 'http://purl.org/goodrelations/v1#' . $hourObj->day
					);
				}
				$hours[$key] = $hourObj;
			}
			$mapLink = "http://maps.google.com/maps?f=q&hl=en&geocode=&q=$mapAddress&ie=UTF8&z=15&iwloc=addr&om=1&t=m";
			$locationInfo = array(
				'id' => $location->locationId,
				'name' => $location->displayName,
				'address' => preg_replace('/\r\n|\r|\n/', '<br>', $location->address),
				'phone' => $location->phone,
				'map_image' => "http://maps.googleapis.com/maps/api/staticmap?center=$mapAddress&zoom=15&size=200x200&sensor=false&markers=color:red%7C$mapAddress",
				'map_link' => $mapLink,
				'hours' => $hours
			);
			$interface->assign('locationInfo', $locationInfo);

			//Schema.org
			$semanticData = array(
				'@context' => 'http://schema.org',
				'@type' => 'Library',
				'name' => $location->displayName,
				'branchCode' => $location->code,
				'parentOrganization' => $configArray['Site']['url'] . "/Library/{$location->libraryId}/System"
			);

			if ($location->address){
				$semanticData['address'] = $location->address;
				$semanticData['hasMap'] = $mapLink;
			}
			if ($location->phone){
				$semanticData['telephone'] = $location->phone;
			}
			if (!empty($hoursSemantic)){
				$semanticData['openingHoursSpecification'] = $hoursSemantic;
			}

			$interface->assign('semanticData', json_encode($semanticData));
		}

		$this->display('branch.tpl', $location->displayName);
	}
}