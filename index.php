<?php
/*
========= WIKIJOURNEY API - index.php =============

This is the main file of the API. To know how it works,
please refer to documentation on Github.

Source : https://github.com/WikiJourney/wikijourney_api

Copyright 2016 WikiJourney

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

*/

if(!file('config.php'))
	die('No file config.php found. Please move config.default.php to config.php and set your values.');

require 'multiCurl.php';
require 'getAndParseWikipediaPOI.php';
require 'getAndParseWikivoyageGuides.php';
require 'config.php';

if($CONFIG_debug_mode)
	error_reporting(E_ALL); 
else
	error_reporting(0); // No need error reporting, or else it will crash the JSON export

header('Content-Type: application/json'); // Set the header to UTF8

// ============> Connect to DB. If unreachable, the script will work anyway.
if($CONFIG_cache_enabled)
{
	try {
		$dbh = new PDO('mysql:host='.$CONFIG_DB_addr.';dbname='.$CONFIG_DB_name, $CONFIG_DB_user, $CONFIG_DB_password);
	} catch (PDOException $e) {
		$dbh = 0;
	}
}
else
	$dbh = 0;

// ============> INFO SECTION
$output['infos']['source'] = 'WikiJourney API';
$output['infos']['link'] = $CONFIG_link;
$output['infos']['api_version'] = $CONFIG_API_version;

// ============> FAKE ERROR
if (isset($_GET['fakeError']) && $_GET['fakeError'] == 'true') {
	$error = 'Error ! If you want to see all the error messages that can be sent by our API, please refer to the source code on our GitHub repository.';
} 
else {

// ============> REQUIRED INFORMATIONS
	if (isset($_GET['place'])) {
		// If it's a place
		$name = strval($_GET['place']);
		$osm_array_json = file_get_contents('http://nominatim.openstreetmap.org/search?format=json&q="'.urlencode($name).'"'); // Contacting Nominatim API to have coordinates
		$osm_array = json_decode($osm_array_json, true);

		if (!isset($osm_array[0]['lat'])) {
			$error = "Location doesn't exist";
		} else {
			$user_latitude = $osm_array[0]['lat'];
			$user_longitude = $osm_array[0]['lon'];
		}
	} else {
		// Else it's long/lat
		if (!(is_numeric($_GET['lat']) && is_numeric($_GET['long']))) {
			$error = 'Error : latitude and longitude should be numeric values.';
		}

		if (isset($_GET['lat'])) {
			$user_latitude = floatval($_GET['lat']);
		} else {
			$error = 'Latitude missing';
		}

		if (isset($_GET['long'])) {
			$user_longitude = floatval($_GET['long']);
		} else {
			$error = 'Longitude missing';
		}

	}

// ============> OPTIONAL PARAMETERS
	
	//==> Range, MaxPOI, WikiVoyage support and thumbnails width

	// Syntax : test if param is written, if yes apply its valeur, or else apply default

	$range = (isset($_GET['range'])) ? intval($_GET['range']) : $CONFIG_default_range;
	$maxPOI = (isset($_GET['maxPOI'])) ? intval($_GET['maxPOI']) : $CONFIG_default_maxPOI;
	$thumbnailWidth = (isset($_GET['thumbnailWidth']) && is_numeric($_GET['thumbnailWidth'])) ? intval($_GET['thumbnailWidth']) : $CONFIG_default_thumbnail_width;
	$wikivoyageSupport = (isset($_GET['wikivoyage'])) ? $_GET['wikivoyage'] : $CONFIG_default_enable_wikivoyage;
	$wikiVoyageRange = (isset($_GET['wikiVoyageRange'])) ? intval($_GET['wikiVoyageRange']) : $CONFIG_default_wikivoyage_range;

	if (!(is_numeric($range) && is_numeric($maxPOI) && is_numeric($thumbnailWidth))) {
		$error = 'Error : maxPOI, thumbnailWidth and range should be numeric values.';
	}

	//==> Languages 
	if(isset($_GET['lg']))
	{

		if (in_array($_GET['lg'], $CONFIG_wikiSupportedLanguages)) 
		{
			$language = $_GET['lg'];

			$table = 'cache_'.$language;

			if($dbh)
			{
				// ==> We create the table if it doesn't exist

				$stmt = $dbh->prepare("CREATE TABLE IF NOT EXISTS $table ("
					."`id` bigint(9) NOT NULL,"
					."`latitude` float NOT NULL,"
					."`longitude` float NOT NULL,"
					."`name` text COLLATE utf8_bin NOT NULL,"
					."`sitelink` text COLLATE utf8_bin NOT NULL,"
					."`type_name` text COLLATE utf8_bin NOT NULL,"
					."`type_id` bigint(9) NOT NULL,"
					."`image_url` text COLLATE utf8_bin NOT NULL,"
					."`lastupdate` date NOT NULL,"
					."PRIMARY KEY (`id`)"
					.") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin");

				if (!$stmt->execute()) {
				   print_r($stmt->errorInfo());
				}
				unset($stmt);
			}
		}
		else
			$error = "Error : language is not supported.";
	}
	else
		$language = "en";
	
}

// ============> INFO POINT OF INTEREST & WIKIVOYAGE GUIDES
if (!isset($error)) {
	// ==================================> Put in the output the user location (can be useful)
	$output['user_location']['latitude'] = $user_latitude;
	$output['user_location']['longitude'] = $user_longitude;

	// ==================================> Wikivoyage requests : find travel guides around
	if ($wikivoyageSupport == 1) {
			// Call the magic function, check for error, and push in output array
			$temp_WikiVoyage_output = getAndParseWikivoyageGuides($language, $user_latitude, $user_longitude,$wikiVoyageRange);
			if(!array_key_exists('error',$temp_WikiVoyage_output))
			{
				$output['guides']['nb_guides'] = count($temp_WikiVoyage_output);
				$output['guides']['guides_info'] = $temp_WikiVoyage_output;
			}
			else
			{
				$output['guides']['nb_guides'] = 0;
				$output['guides']['guides_info'] = array();
			}
	}
	// ==================================> End Wikivoyage requests

	// ==================================> Wikidata requests : find wikipedia pages around

	// Call the magic function, check for error, and push in output array
	$temp_POI_output = getAndParseWikipediaPOI($language, $user_latitude, $user_longitude, $range, $maxPOI, $thumbnailWidth);
	if(!array_key_exists('error',$temp_POI_output))
	{
		$output['poi']['nb_poi'] = count($temp_POI_output);	
		$output['poi']['poi_info'] = $temp_POI_output;
	}
	else
		$error = $temp_POI_output['error'];
	// ==================================> End Wikidata requests
}

if (isset($error)) {
	$output['err_check']['value'] = true;
	$output['err_check']['err_msg'] = $error;
} else {
	$output['err_check']['value'] = false;
}

echo json_encode($output); // Encode in JSON. (user will get it by file_get_contents, curl, wget, or whatever)

// Next line is a legacy, please don't touch.
/* yolo la police */