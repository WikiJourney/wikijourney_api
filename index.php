<?php
/*
============================ WIKIJOURNEY API =========================
Version Beta 1.1.2
======================================================================

See documentation on http://api.wikijourney.eu/documentation.php
*/

require 'multiCurl.php';
require 'getAndParseWikipediaPOI.php';

error_reporting(E_ALL); // No need error reporting, or else it will crash the JSON export
header('Content-Type: application/json'); // Set the header to UTF8
$wikiSupportedLanguages = array('aa','ab','ace','ady','af','ak','als','am','an','ang','ar','arc','arz','as','ast','av','ay','az','azb','ba','bar','bat-smg','bcl','be','be-x-old','bg','bh','bi','bjn','bm','bn','bo','bpy','br','bs','bug','bxr','ca','cbk-zam','cdo','ce','ceb','ch','cho','chr','chy','ckb','co','cr','crh','cs','csb','cu','cv','cy','da','de','diq','dsb','dv','dz','ee','el','eml','en','eo','es','et','eu','ext','fa','ff','fi','fiu-vor','fj','fo','fr','frp','frr','fur','fy','ga','gag','gan','gd','gl','glk','gn','gom','got','gu','gv','ha','hak','haw','he','hi','hif','ho','hr','hsb','ht','hu','hy','hz','ia','id','ie','ig','ii','ik','ilo','io','is','it','iu','ja','jbo','jv','ka','kaa','kab','kbd','kg','ki','kj','kk','kl','km','kn','ko','koi','kr','krc','ks','ksh','ku','kv','kw','ky','la','lad','lb','lbe','lez','lg','li','lij','lmo','ln','lo','lrc','lt','ltg','lv','mai','map-bms','mdf','mg','mh','mhr','mi','min','mk','ml','mn','mo','mr','mrj','ms','mt','mus','mwl','my','myv','mzn','na','nah','nap','nds','nds-nl','ne','new','ng','nl','nn','no','nov','nrm','nso','nv','ny','oc','om','or','os','pa','pag','pam','pap','pcd','pdc','pfl','pi','pih','pl','pms','pnb','pnt','ps','pt','qu','rm','rmy','rn','ro','roa-rup','roa-tara','ru','rue','rw','sa','sah','sc','scn','sco','sd','se','sg','sh','si','simple','sk','sl','sm','sn','so','sq','sr','srn','ss','st','stq','su','sv','sw','szl','ta','te','tet','tg','th','ti','tk','tl','tn','to','tpi','tr','ts','tt','tum','tw','ty','tyv','udm','ug','uk','ur','uz','ve','vec','vep','vi','vls','vo','wa','war','wo','wuu','xal','xh','xmf','yi','yo','za','zea','zh','zh-classical','zh-min-nan','zh-yue','zu');

// ============> Connect to DB. If unreachable, the script will work anyway.
try {
	$dbh = new PDO('mysql:host=localhost;dbname=wikijourney_cache', 'wikijourney_web', '');
} catch (PDOException $e) {
	$dbh = 0;
}

// ============> INFO SECTION
$output['infos']['source'] = 'WikiJourney API';
$output['infos']['link'] = 'http://wikijourney.eu/';
$output['infos']['api_version'] = 'Beta 1.1.2';

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

// ============> OPTIONNAL PARAMETERS
	
	//==> Range
	if (isset($_GET['range'])) {
		$range = intval($_GET['range']);
	} else {
		$range = 1;
	}

	//==> Max POI
	if (isset($_GET['maxPOI'])) {
		$maxPOI = intval($_GET['maxPOI']);
	} else {
		$maxPOI = 10;
	}
	
	//==> Display images, wikivoyage support and thumbnail width
	$displayImg = (isset($_GET['displayImg']) && $_GET['displayImg'] == 1) ? 1 : 0;
	$wikivoyageSupport = (isset($_GET['wikivoyage']) && $_GET['wikivoyage'] == 1) ? 1 : 0;
	if (isset($_GET['thumbnailWidth'])) {
		$thumbnailWidth = intval($_GET['thumbnailWidth']);
	} else {
		$thumbnailWidth = 500;
	}

	if (!(is_numeric($range) && is_numeric($maxPOI) && is_numeric($thumbnailWidth))) {
		$error = 'Error : maxPOI, thumbnailWidth and range should be numeric values.';
	}

	//==> Languages 
	if(isset($_GET['lg']))
	{

		if (in_array($_GET['lg'], $wikiSupportedLanguages)) 
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
		if ($displayImg == 1) {
			// We add description and image

			$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
.'prop=coordinates|info|pageterms|pageimages&' // Props list
.'piprop=thumbnail&pithumbsize=144&pilimit=50&inprop=url&wbptterms=description' // Properties dedicated to image, url and description
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch
		} else {
			// Simplified request

			$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
.'prop=coordinates|info&' // Props list
.'inprop=url' // Properties dedicated to url
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch

		}

		$wikivoyage_json = file_get_contents($wikivoyageRequest); // Request is sent to WikiVoyage API

		if ($wikivoyage_json == false) {
			$error = 'API Wikivoyage is not responding.';
		} 
		else 
		{
			$wikivoyage_array = json_decode($wikivoyage_json, true);

			if (isset($wikivoyage_array['query']['pages'])) 
			{
				// If there's guides around
				$realCount = 0;

				$wikivoyage_clean_array = array_values($wikivoyage_array['query']['pages']); // Reindexing the array (because it's initially indexed by pageid)

				for ($i = 0; $i < count($wikivoyage_clean_array); ++$i) 
				{
					++$realCount;

					$wikivoyage_output_array[$i]['pageid'] = $wikivoyage_clean_array[$i]['pageid'];
					$wikivoyage_output_array[$i]['title'] = $wikivoyage_clean_array[$i]['title'];
					$wikivoyage_output_array[$i]['sitelink'] = $wikivoyage_clean_array[$i]['fullurl'];

					if (isset($wikivoyage_clean_array[$i]['coordinates'][0]['lat'])) {
						// If there are coordinates
						$wikivoyage_output_array[$i]['latitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lat']; // Warning : could be null
						$wikivoyage_output_array[$i]['longitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lon']; // Warning : could be null
					}

					if (isset($wikivoyage_clean_array[$i]['thumbnail']['source'])) 
					{ // If we can find an image
						$wikivoyage_output_array[$i]['thumbnail'] = $wikivoyage_clean_array[$i]['thumbnail']['source'];
					}
				}
				$output['guides']['nb_guides'] = $realCount;
				if ($realCount != 0) 
				{
					$output['guides']['guides_info'] = array_values($wikivoyage_output_array);
				}
			} 
			else 
			{ // Case we're in the middle of Siberia
				$output['guides']['nb_guides'] = 0;
			}
		}
	}

	// ==================================> End Wikivoyage requests

	// ==================================> Wikidata requests : find wikipedia pages around

	$temp_POI_output = getAndParseWikipediaPOI($language, $user_latitude, $user_longitude, $range, $maxPOI);
	if(!in_array($temp_POI_output,'error'))
	{
		$output['poi']['poi_info'] = $temp_POI_output;
		$output['poi']['nb_poi'] = count($output['poi']['poi_info']);	
	}
	else
		$error = $temp_POI_output['error'];
	
}

if (isset($error)) {
	$output['err_check']['value'] = true;
	$output['err_check']['err_msg'] = $error;
} else {
	$output['err_check']['value'] = false;
}

echo json_encode($output); // Encode in JSON. (user will get it by file_get_contents, curl, wget, or whatever)

unset($dbh); // Close the database.

// Next line is a legacy, please don't touch.
/* yolo la police */