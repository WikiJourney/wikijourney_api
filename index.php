<?php
/*
============================ WIKIJOURNEY API =========================
Version Beta 1.1.2
======================================================================

See documentation on http://api.wikijourney.eu/documentation.php
*/

    error_reporting(0); // No need error reporting, or else it will crash the JSON export
    header('Content-Type: application/json'); // Set the header to UTF8
    $dbh = new PDO('mysql:host=localhost;dbname=wikijourney_cache', 'wikijourney_web', '');

    require 'multiCurl.php';

    // ============> INFO SECTION
    $output['infos']['source'] = 'WikiJourney API';
    $output['infos']['link'] = 'http://wikijourney.eu/';
    $output['infos']['api_version'] = 'Beta 1.1.2';

    // ============> FAKE ERROR
    if (isset($_GET['fakeError']) && $_GET['fakeError'] == 'true') {
        $error = 'Error ! If you want to see all the error messages that can be sent by our API, please refer to the source code on our GitHub repository.';
    } else {

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

            if (!(is_numeric($user_longitude) && is_numeric($user_latitude))) {
                $error = 'Error : latitude and longitude should be numeric values.';
            }
        }

        // Not required
        if (isset($_GET['range'])) {
            $range = intval($_GET['range']);
        } else {
            $range = 1;
        }
        if (isset($_GET['maxPOI'])) {
            $maxPOI = intval($_GET['maxPOI']);
        } else {
            $maxPOI = 10;
        }

        $language = 'en';
        if (isset($_GET['lg']) && in_array($_GET['lg'], ['en', 'fr', 'zh','de','es'])) {
            $language = $_GET['lg'];
        }
        $table = 'cache_'.$language;
        $displayImg = (isset($_GET['displayImg']) && $_GET['displayImg'] === 1) ? 1 : 0;
        $wikivoyageSupport = (isset($_GET['wikivoyage']) && $_GET['wikivoyage'] === 1) ? 1 : 0;

        if (isset($_GET['thumbnailWidth'])) {
            $thumbnailWidth = intval($_GET['thumbnailWidth']);
        } else {
            $thumbnailWidth = 500;
        }

        if (!(is_numeric($range) && is_numeric($maxPOI) && is_numeric($thumbnailWidth))) {
            $error = 'Error : maxPOI, thumbnailWidth and range should be numeric values.';
        }
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
.'prop=coordinates|info|pageterms|pageimages|langlinks&' // Props list
.'piprop=thumbnail&pithumbsize=144&pilimit=50&inprop=url&wbptterms=description' // Properties dedicated to image, url and description
.'&llprop=url' // Properties dedicated to langlinks
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch
            } else {
                // Simplified request

                $wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
.'prop=coordinates|info|langlinks&' // Props list
.'inprop=url' // Properties dedicated to url
.'&llprop=url' // Properties dedicated to langlinks
."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=10000&ggslimit=50"; // Properties dedicated to geosearch
            }

            $wikivoyage_json = file_get_contents($wikivoyageRequest); // Request is sent to WikiVoyage API

            if ($wikivoyage_json == false) {
                $error = 'API Wikivoyage is not responding.';
            } else {
                $wikivoyage_array = json_decode($wikivoyage_json, true);

                if (isset($wikivoyage_array['query']['pages'])) {
                    // If there's guides around

                    $realCount = 0;

                    $wikivoyage_clean_array = array_values($wikivoyage_array['query']['pages']); // Reindexing the array (because it's initially indexed by pageid)

                    for ($i = 0; $i < count($wikivoyage_clean_array); ++$i) {
                        $j = 0;

                        while ($wikivoyage_clean_array[$i]['langlinks'][$j]['lang'] != $language && $j < count($wikivoyage_clean_array[$i]['langlinks']) - 1) {
                            $j++;
                        } // We walk in the array trying to find the user's language

                        if ($wikivoyage_clean_array[$i]['langlinks'][$j]['lang'] == $language || $language == 'en') {
                            // If we found it or if it's english

                            ++$realCount;

                            $wikivoyage_output_array[$i]['pageid'] = $wikivoyage_clean_array[$i]['pageid'];

                            if ($language == 'en') {
                                // Special for English

                                $wikivoyage_output_array[$i]['title'] = $wikivoyage_clean_array[$i]['title'];
                                $wikivoyage_output_array[$i]['sitelink'] = $wikivoyage_clean_array[$i]['fullurl'];
                            } else {
                                $wikivoyage_output_array[$i]['title'] = $wikivoyage_clean_array[$i]['langlinks'][$j]['*'];
                                $wikivoyage_output_array[$i]['sitelink'] = $wikivoyage_clean_array[$i]['langlinks'][$j]['url'];
                            }

                            if (isset($wikivoyage_clean_array[$i]['coordinates'][0]['lat'])) {
                                // If there are coordinates

                                $wikivoyage_output_array[$i]['latitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lat']; // Warning : could be null
                                $wikivoyage_output_array[$i]['longitude'] = $wikivoyage_clean_array[$i]['coordinates'][0]['lon']; // Warning : could be null
                            }

                            if (isset($wikivoyage_clean_array[$i]['thumbnail']['source'])) { // If we can find an image
                                $wikivoyage_output_array[$i]['thumbnail'] = $wikivoyage_clean_array[$i]['thumbnail']['source'];
                            }
                        }
                        // No else, because if we didn't found the language it means that there's no guide for the user's language
                    }
                    $output['guides']['nb_guides'] = $realCount;
                    if ($realCount != 0) {
                        $output['guides']['guides_info'] = array_values($wikivoyage_output_array);
                    }
                } else { // Case we're in the middle of Siberia
                    $output['guides']['nb_guides'] = 0;
                }
            }
        }

        // ==================================> End Wikivoyage requests

        // ==================================> Wikidata requests : find wikipedia pages around

        $poi_id_array_json = file_get_contents("http://wdq.wmflabs.org/api?q=around[625,$user_latitude,$user_longitude,$range]"); // Returns a $poi_id_array_clean array with a list of wikidata pages ID within a $range km range from user location
        if ($poi_id_array_json == false) {
            $error = "API WMFlabs isn't responding.";
        } else {
            $poi_id_array = json_decode($poi_id_array_json, true);
            $poi_id_array_clean = $poi_id_array['items'];
            $nb_poi = count($poi_id_array_clean);

            for ($i = 0; $i < min($nb_poi, $maxPOI); ++$i) {
                $id = $poi_id_array_clean[$i];

                // =============> We check if the db is online. If not, then bypass the cache.
                if ($dbh) {

                    // ==> We look in the cache to know if the POI is there
                    $stmt = $dbh->prepare('SELECT * FROM '.$table.' WHERE id = ?');
                    $stmt->execute([$id]);
                    $dataPOI = $stmt->fetch(PDO::FETCH_ASSOC);

                    // ==> If we have it we can display it
                    if ($stmt->rowCount != 0) {
                        $poi_array[$i] = $dataPOI;
                    }

                    unset($stmt);
                }

                // =============> If the POI is not in the cache, or if the database is unreachable, then contact APIs.
                if ($poi_array[$i] == null) {

                    // =============> First call, we're gonna fetch geoloc infos, type ID, description and sitelink

                    $URL_list = [
                        // Geoloc infos
                        'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&entity=Q'.$poi_id_array_clean["$i"].'&property=P625',
                        // Type ID
                        'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&entity=Q'.$poi_id_array_clean["$i"].'&property=P31',
                        // Description
                        'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q'.$poi_id_array_clean["$i"]."&props=labels&languages=$language",
                        // Sitelink
                        'https://www.wikidata.org/w/api.php?action=wbgetentities&ids=Q'.$poi_id_array_clean["$i"]."&sitefilter=$language&props=sitelinks/urls&format=json",
                    ];

                    $curl_return = reqMultiCurls($URL_list); // Using multithreading to fetch urls

                    // ==> Get geoloc infos
                        $temp_geoloc_array_json = $curl_return[0];
                    if ($temp_geoloc_array_json == false) {
                        $error = "API Wikidata isn't responding on request 1.";
                        break;
                    }
                    $temp_geoloc_array = json_decode($temp_geoloc_array_json, true);
                    $temp_latitude = $temp_geoloc_array['claims']['P625'][0]['mainsnak']['datavalue']['value']['latitude'];
                    $temp_longitude = $temp_geoloc_array['claims']['P625'][0]['mainsnak']['datavalue']['value']['longitude'];

                    // ==> Get type id
                        $temp_poi_type_array_json = $curl_return[1];
                    if ($temp_poi_type_array_json == false) {
                        $error = "API Wikidata isn't responding on request 2.";
                        break;
                    }
                    $temp_poi_type_array = json_decode($temp_poi_type_array_json, true);
                    $temp_poi_type_id = $temp_poi_type_array['claims']['P31'][0]['mainsnak']['datavalue']['value']['numeric-id'];

                    // ==> Get description
                        $temp_description_array_json = $curl_return[2];
                    if ($temp_description_array_json == false) {
                        $error = "API Wikidata isn't responding on request 3.";
                        break;
                    }
                    $temp_description_array = json_decode($temp_description_array_json, true);
                    $name = $temp_description_array['entities']['Q'.$poi_id_array_clean["$i"]]['labels']["$language"]['value'];

                    // ==> Get sitelink
                        $temp_sitelink_array_json = $curl_return[3];
                    if ($temp_sitelink_array_json == false) {
                        $error = "API Wikidata isn't responding on request 4.";
                        break;
                    }
                    $temp_sitelink_array = json_decode($temp_sitelink_array_json, true);
                    $temp_sitelink = $temp_sitelink_array['entities']['Q'.$poi_id_array_clean["$i"]]['sitelinks'][$language.'wiki']['url'];

                    // =============> Now we make a second call to fetch images and types' titles

                    // ==> With the sitelink, we make the image's url
                        $temp_url_explode = explode('/', $temp_sitelink);
                    $temp_url_end = $temp_url_explode[count($temp_url_explode) - 1];

                    // ==> Calling APIs
                        $URL_list = [
                            // Type
                            'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=Q'.$temp_poi_type_id."&props=labels&languages=$language",
                            // Images
                            'https://'.$language.'.wikipedia.org/w/api.php?action=query&prop=pageimages&format=json&pithumbsize='.$thumbnailWidth.'&pilimit=1&titles='.$temp_url_end,
                    ];

                    $curl_return = reqMultiCurls($URL_list);

                    // ==> Get type
                        $temp_description_type_array_json = $curl_return[0];
                    if ($temp_description_type_array_json == false) {
                        $error = "API Wikidata isn't responding on request 5.";
                        break;
                    }
                    $temp_description_type_array = json_decode($temp_description_type_array_json, true);
                    $type_name = $temp_description_type_array['entities']['Q'.$temp_poi_type_id]['labels']["$language"]['value'];

                    // ==> Get image
                        $temp_image_json = $curl_return[1];
                    if ($temp_image_json == false) {
                        $error = "API Wikidata isn't responding on request 6.";
                        break;
                    }
                        // We put an @ because it can be null (case there is no image for this article)
                        $image_url = @array_values(json_decode($temp_image_json, true)['query']['pages'])[0]['thumbnail']['source'];

                    // =============> And now we can make the output
                    if ($name != null) {
                        $poi_array[$i]['latitude'] = $temp_latitude;
                        $poi_array[$i]['longitude'] = $temp_longitude;
                        $poi_array[$i]['name'] = $name;
                        $poi_array[$i]['sitelink'] = $temp_sitelink;
                        $poi_array[$i]['type_name'] = $type_name;
                        $poi_array[$i]['type_id'] = $temp_poi_type_id;
                        $poi_array[$i]['id'] = $poi_id_array_clean[$i];
                        $poi_array[$i]['image_url'] = $image_url;

                        if ($dbh) {
                            // Insert this POI in the cache

                            $stmt = $dbh->prepare('INSERT INTO '.$table.' (id, latitude, longitude, name, sitelink, type_name, type_id, image_url, lastupdate)'
                                                 .'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                            $stmt->execute([$id, $temp_latitude, $temp_longitude, $name, $temp_sitelink, $type_name, $temp_poi_type_id, $image_url]);
                        }
                    }
                }
            }
        }
        $output['poi']['nb_poi'] = count($poi_array);
        $output['poi']['poi_info'] = array_values($poi_array); // Output
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
    /* yolo la police */;
