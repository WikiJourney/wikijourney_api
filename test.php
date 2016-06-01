<?php
error_reporting(E_ALL);
ini_set('display_error', 1);
$language = 'fr';

$wikipedia_pagesid = "3276043|816362";
echo '<html><pre>';

$apidata_request_wikipedia_info = "https://fr.wikipedia.org/w/api.php?format=json&action=query&prop=pageprops|info|pageimages&inprop=url&pilimit=1&pageids=".$wikipedia_pagesid;
$apidata_json_wikipedia_info = file_get_contents($apidata_request_wikipedia_info);
$apidata_array_wikipedia_info = json_decode($apidata_json_wikipedia_info,1);


//Parse wikipedia return
foreach ($apidata_array_wikipedia_info['query']['pages'] as $currentPOI => $currentPOIdata) {
	$output_array[$currentPOI]['name'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["title"];
	$output_array[$currentPOI]['sitelink'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["fullurl"];
	$output_array[$currentPOI]['image_url'] = @$apidata_array_wikipedia_info['query']['pages'][$currentPOI]["thumbnail"]["source"];
	$output_array[$currentPOI]['wikipedia_id'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["pageid"];
	$output_array[$currentPOI]['wikidata_id'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["pageprops"]["wikibase_item"];
}

$type_id_list = "";

//For each page, call wikidata to get the type id
foreach ($output_array as $currentPOI => $currentPOIdata) {
	//TODO : CURL
	$apidata_request_wikidata_type_id = 'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&property=P31&entity='.$output_array[$currentPOI]['wikidata_id'];
	$apidata_json_wikidata_type_id = file_get_contents($apidata_request_wikidata_type_id);
	$apidata_array_wikidata_type_id = json_decode($apidata_json_wikidata_type_id,true);
	$output_array[$currentPOI]['type_id'] = $apidata_array_wikidata_type_id['claims']['P31'][0]['mainsnak']['datavalue']['value']['numeric-id'];
	$type_id_list .= '|Q'.$output_array[$currentPOI]['type_id'];
}

$type_id_list = substr($type_id_list, 1);

$apidata_request_wikidata_type_name = 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages='.$language.'&ids='.$type_id_list;
$apidata_json_wikidata_type_name = file_get_contents($apidata_request_wikidata_type_name);
$apidata_array_wikidata_type_name = json_decode($apidata_json_wikidata_type_name,true);

foreach ($output_array as $currentPOI => $currentPOIdata) {
	$output_array[$currentPOI]['type_name'] = $apidata_array_wikidata_type_name['entities']['Q'.$output_array[$currentPOI]['type_id']]['labels'][$language]['value'];
}

print_r($output_array);



echo '</pre></html>';