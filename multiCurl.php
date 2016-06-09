<?php
/*
========= WIKIJOURNEY API - multiCurl.php =============

This function makes several API calls in the same time
using curl.

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

function reqMultiCurls($urls) {
	
	// for storing cUrl handlers
	$chs = array();
	// for storing the reponses strings
	$contents = array();
 
	// loop through an array of URLs to initiate
	// one cUrl handler for each URL (request)
	foreach ($urls as $key => $url) {
		$ch = curl_init($url);
		// tell cUrl option to return the response
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		$chs[$key] = $ch;
	}
 
	// initiate a multi handler
	$mh = curl_multi_init();
 
	// add all the single handler to a multi handler
	foreach($chs as $key => $ch){
		curl_multi_add_handle($mh,$ch);
	}
 
	// execute the multi cUrl handler
	do {
		  $mrc = curl_multi_exec($mh, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM  || $active);
 
	// retrieve the reponse from each single handler
	foreach($chs as $key => $ch){
		if(curl_errno($ch) == CURLE_OK){
				$contents[$key] = json_decode(curl_multi_getcontent($ch),true);
		}
		else{
			echo "Err>>> ".curl_error($ch)."\n";
		}
	}
 
	curl_multi_close($mh);
	
	return $contents;
}

?>