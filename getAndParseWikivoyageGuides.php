<?php

function getAndParseWikivoyageGuides($language, $user_latitude, $user_longitude)
{
	// ===> Make the URL 
	$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
	.'prop=coordinates|info|pageterms|pageimages&' // Props list
	.'piprop=thumbnail&pithumbsize=144&pilimit=50&inprop=url&wbptterms=description' // Properties dedicated to image, url and description
	."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch

	// ===> Make the call and check
	if(!($wikivoyage_json = @file_get_contents($wikivoyageRequest))) 
		return array("error" => "WikiVoyage API is unreachable");

	// ===> Parse the json in an array
	$wikivoyage_array = json_decode($wikivoyage_json, true);

	// ===> Case there is no guide around (in the selected language)
	if (!isset($wikivoyage_array['query']['pages'])) 
		return array(); //Return an empty array

	// ===> Reindexing the array (because it's initially indexed by pageid)
	$wikivoyage_clean_array = array_values($wikivoyage_array['query']['pages']); 

	// ===> Copy the data we need in the output
	foreach ($wikivoyage_clean_array as $currentGuide => $currentGuideValue) {

		$output[$currentGuide]['pageid'] = $currentGuideValue['pageid'];
		$output[$currentGuide]['title'] = $currentGuideValue['title'];
		$output[$currentGuide]['sitelink'] = $currentGuideValue['fullurl'];

		// The next three can be null, so we put an @
		$output[$currentGuide]['latitude'] = @$currentGuideValue['coordinates'][0]['lat'];
		$output[$currentGuide]['longitude'] = @$currentGuideValue['coordinates'][0]['lon'];
		$output[$currentGuide]['thumbnail'] = @$currentGuideValue['thumbnail']['source'];

	}
	
	return $output;
}
