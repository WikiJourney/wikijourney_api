<pre>
			Documentation - WikiJourney API


This documentation refers to the API of Wikijourney's website.
You can enter a position (latitude and longitude), it will return
the point of interest around, with informations, including POI
type, description, and link to the Wikipedia's page when available.
Since the Alpha 0.0.3 version, this API is also able to look for
WikiVoyage guides around the user's position.

This API is based on datas from Wikipedia, Wikidata, Wikivoyage,
and use the OSM Nominatim.

-------------> VERSIONS :


RELEASE

2.0 : Complete refactoring making the API way more efficient.
1.0 : API fully fonctionnal.

BETA

1.2.0 : Changed cache requests to prepared statements. Better langugages support.
1.1.1 : Minor bugfix and error gestion.
1.1.0 : Added Cache support. The API is now a lot quicker if POI have already been visited.
1.0.0 : Added thumbnail for POIs. New technology implemented, making the API faster.

ALPHA

0.0.5 : Added the fake error function.
0.0.4 : Added Nominatim support
0.0.3 : Added WikiVoyage informations.
0.0.2 : Error gestion. More information in the output.
0.0.1 : Creation of the API. Export in JSON.


-------------> INPUT :

Use this link : http://api.wikijourney.eu/?PARAMETERS

Parameters could be (INS is for If Not Specified) :

		- [REQUIRED]	lat : 		user's latitude
		- [REQUIRED]	long : 		user's longitude
		- [OPTIONNAL]	place :		If you want to do a request with a place name instead of coordinates. Uses OSM nominatim system.
		- [INS 5   ]	range : 	Range around we're gonna find POI in kilometers
		- [INS 50  ]	maxPOI : 	number max of POI
		- [INS en  ] 	lg :		language used
		- [INS 0   ] 	wikivoyage :		contact or no WikiVoyage API. Value 0 or 1.
		- [INS 20  ] 	wikiVoyageRange :	range to look for wikivoyage guides around, in kilometers.
		- [INS 500 ]	thumbnailWidth : 	maximum width of thumbnails from Wikipedia's article. Value is in px, and has to be numeric.
		- [OPTIONNAL]	fakeError : 		use it if you need to test error on your device. It will simulate an error during the process.

Example : 	http://api.wikijourney.eu/?lat=2&lon=2&lg=fr
Example :	http://api.wikijourney.eu/?place=Washington&lg=en&wikivoyage=1

-------------> OUTPUT :

The output is a JSON array. You can obtain it using curl, file_get_contents, wget or whatever.

Structure :
- infos
	- source
	- link
	- api_version
- user_location
	- latitude
	- longitude
- guides ==>  Available only if wikivoyage=1
	- nb_guides
	- guides_info ==> Contains the array with informations on WikiVoyage's guides
		- pageid
		- title
		- sitelink
		- latitude
		- longitude
- poi
	- nb_poi
	- poi_info ==> Contains the array with informations on POIs
		- id (Wikipedia ID)
		- latitude
		- longitude
		- distance (Distance in meters from user's position)
		- name
		- sitelink (could be null)
		- image_url (link to thumbnail - could be null)
		- type_name
		- type_id
		- wikidata_id (id of the Wikidata page)
- err_check
	- value (true if there's an error)
	- msg (defined only if value is set on true) : contains the error message

-------------> EXAMPLE :

Input : http://api.wikijourney.eu/?wikivoyage=1&place=Lille&lg=en&maxPOI=1

Output : 

{
   "infos":{
      "source":"WikiJourney API",
      "link":"https:\/\/www.wikijourney.eu\/",
      "api_version":"v2.0"
   },
   "user_location":{
      "latitude":"50.6305089",
      "longitude":"3.0706414"
   },
   "guides":{
      "nb_guides":1,
      "guides_info":[
         {
            "pageid":19684,
            "title":"Lille",
            "sitelink":"https:\/\/en.wikivoyage.org\/wiki\/Lille",
            "latitude":50.6372,
            "longitude":3.0633,
            "thumbnail":"https:\/\/upload.wikimedia.org\/wikipedia\/commons\/thumb\/7\/77\/Lille-Place-du-General-de-Gaulle.jpg\/144px-Lille-Place-du-General-de-Gaulle.jpg"
         }
      ]
   },
   "poi":{
      "nb_poi":1,
      "poi_info":[
         {
            "id":24374320,
            "name":"Siege of Lille (1792)",
            "latitude":50.627777777778,
            "longitude":3.0583333333333,
            "distance":919.7,
            "sitelink":"https:\/\/en.wikipedia.org\/wiki\/Siege_of_Lille_(1792)",
            "wikidata_id":"Q3485925",
            "image_url":"https:\/\/upload.wikimedia.org\/wikipedia\/commons\/thumb\/b\/b6\/Si%C3%A8ge_de_Lille_1792.JPG\/500px-Si%C3%A8ge_de_Lille_1792.JPG",
            "type_id":188055,
            "type_name":"siege"
         }
      ]
   },
   "err_check":{
      "value":false
   }
}
</pre>