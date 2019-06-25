<?php

/*


Step 1.

For an AFD author URI get  Wikispecies author link via sparql

If Wikispecies get Wikidata

Go back to Oz and get list of all DOIs for that author URI

For each DOI find Wikidata article
- if missing add to "list of DOIs to add"
- if found update author in Wikidata record for that article


Step 1a.

See if we have an ORCID match via Ozymandias

Step 2.

Add articles with DOis that are missing from Wikidata








*/


//----------------------------------------------------------------------------------------
// get
function get($url, $format = "application/json")
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format));

	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------
// QUERY, by default return as JSON
function sparql_query($sparql_endpoint, $query, $format='application/json')
{
	$url = $sparql_endpoint . '?query=' . urlencode($query);
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   
	curl_setopt($ch, CURLOPT_HTTPHEADER, 
		array(
			"Accept: " . $format,
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
		));

	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	if ($http_code != 200)
	{
		echo $response;	
		die ("Triple store returned $http_code\n");
	}
	
	curl_close($ch);

	return $response;
}


//----------------------------------------------------------------------------------------
// Given a Wikipsecies URL test whether it is a redirect, if it is return the final destination
// of that URL, otherwise return the original URL
function wikispecies_redirect($url)
{
	$redirect_url = $url;

	$page_name = preg_replace('/https?:\/\/species.wikimedia.org\/wiki\//', '', $url);
	
	$xml_url = 'https://species.wikimedia.org/w/index.php?title=Special:Export&pages=' . $page_name;

	$xml = get($xml_url);
	
	//echo $xml_url . "\n";
	
	//echo $xml;
	
	$xml = str_replace('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.10/ http://www.mediawiki.org/xml/export-0.10.xsd"', '', $xml);

	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);

	$xpath->registerNamespace("wiki", "http://www.mediawiki.org/xml/export-0.10/");
		
	//$nodeCollection = $xpath->query ("//wiki:page/wiki:title");
	$nodeCollection = $xpath->query ("//wiki:page/wiki:redirect/@title");
	foreach($nodeCollection as $node)
	{
		$redirect_url = 'https://species.wikimedia.org/wiki/' . str_replace(' ', '_', $node->firstChild->nodeValue);
	}

	return $redirect_url;
}


//----------------------------------------------------------------------------------------
function wikispecies_match($data_obj)
{
	$data_obj->wikispecies_urls = array();
	
	$sparql = 'SELECT DISTINCT ?name ?external_creator ?external_name ?roleName
	WHERE
	{
	  GRAPH <https://biodiversity.org.au/afd/publication> {
	  <' .  $data_obj->author_uri . '> <http://schema.org/name> ?name .
	?role <http://schema.org/creator> <' . $data_obj->author_uri . '>  .
	?role <http://schema.org/roleName> ?roleName  .

	?work <http://schema.org/creator> ?role  .

	?work <http://schema.org/identifier> ?identifier .
	?identifier <http://schema.org/value> ?identifier_value .
	}
  
	GRAPH <https://species.wikimedia.org>
	  {
		?external_identifier <http://schema.org/value> ?identifier_value .
		?external_work <http://schema.org/identifier> ?external_identifier .
	
		?external_work <http://schema.org/creator> ?external_role  . 
		?external_role <http://schema.org/roleName> ?external_roleName  .
	
		?external_role <http://schema.org/creator> ?external_creator  .
	
		?external_creator <http://schema.org/name> ?external_name .
	  }   
  
	  FILTER(?roleName = ?external_roleName)
	}';
	
	//echo $sparql . "\n";

	$url = 'http://kg-blazegraph.sloppy.zone/blazegraph/sparql?query=' . urlencode($sparql);

	//echo $url;

	$json = get($url);
	
	// echo $json;

	$obj = json_decode($json);
	
	//print_r($obj);
	//exit();
	
	$best_match = 0;
	
	if (isset($obj->results->bindings))
	{
	
		$n = count($obj->results->bindings);
		
		for ($i = 0; $i < $n; $i++)
		{	
			//echo $obj->results->bindings[$i]->name->value . ' ' . $obj->results->bindings[$i]->external_name->value . "\n";
			
			$s1 = $obj->results->bindings[$i]->name->value;
			$s2 = $obj->results->bindings[$i]->external_name->value;
			
			if (preg_match('/(.*),\s+(.*)/u', $s2, $m))
			{
				$s2 = $m[2] . ' ' . $m[1];
			}
			
			$l1 = strlen($s1);
			$l2 = strlen($s2);
			
			$lev = levenshtein($s1, $s2);
			
			$similarity = 1 - (2 * $lev)/($l1 + $l2) . "\n";
			
			//echo "$s1 $s2 $l1 $l2 lev=$lev $similarity\n";
			
			if ($similarity > 0.6)
			{			
				if ($similarity > $best_match)
				{
					$data_obj->wikispecies_urls = array();
					$data_obj->wikispecies_urls[] = $obj->results->bindings[$i]->external_creator->value;	
					$best_match = $similarity;			
				}
			}			
		}
	}
	
	//$data_obj->wikispecies_urls = array_unique($data_obj->wikispecies_urls);
	
	if (count($data_obj->wikispecies_urls) ==  1)
	{
		// Now we have Wikispecies links, make sure we resolve any redirects	
	
		// echo "Wikispecies URLs\n";
		// print_r($data_obj);
	
		$data_obj->wikispecies = wikispecies_redirect($data_obj->wikispecies_urls[0]);
		
		// echo "Cleaned\n";
		// print_r($data_obj);
		
		// test of having to encode
		if (1)
		{
			$data_obj->wikispecies = str_replace('https://species.wikimedia.org/wiki/', '', $data_obj->wikispecies);
			$data_obj->wikispecies = 'https://species.wikimedia.org/wiki/' . urlencode($data_obj->wikispecies);
			
			//print_r($data_obj);
		}
		
		
		// Now we query Wikidata for this Wikispecies person
		
		$sparql = 'SELECT *
		WHERE
		{
			VALUES ?article {<' . $data_obj->wikispecies . '>}
			?article schema:about ?item .
			?item wdt:P31 wd:Q5 .
		  OPTIONAL {
			   ?item wdt:P213 ?isni .
				}
			  OPTIONAL {
			   ?item wdt:P214 ?viaf .
				}    
			  OPTIONAL {
			   ?item wdt:P18 ?image .
				} 
			  OPTIONAL {
			   ?item wdt:P496 ?orcid .
				} 	
			  OPTIONAL {
			   ?item wdt:P2038 ?researchgate .
				} 					
			  OPTIONAL {
			   ?item wdt:P586 ?ipni .
				} 
			  OPTIONAL {
			   ?item wdt:P2006 ?zoobank .
				} 		
		}';

		//echo $sparql;

		$json = sparql_query('https://query.wikidata.org/bigdata/namespace/wdq/sparql', $sparql);

		//echo $url;
	
		//echo $json;

		$obj = json_decode($json);
	
		//print_r($obj);
	
		if (isset($obj->results->bindings))
		{
			if (isset($obj->results->bindings[0]->item))
			{
				$data_obj->wikidata = str_replace('http://www.wikidata.org/entity/', '', $obj->results->bindings[0]->item->value);
			}
		
			if (isset($obj->results->bindings[0]->ipni))
			{
				$data_obj->ipni = $obj->results->bindings[0]->ipni->value;
			}
		
			if (isset($obj->results->bindings[0]->researchgate))
			{
				$data_obj->researchgate = $obj->results->bindings[0]->researchgate->value;
			}
		
			if (isset($obj->results->bindings[0]->orcid))
			{
				$data_obj->orcid = $obj->results->bindings[0]->orcid->value;
			}
		
			if (isset($obj->results->bindings[0]->zoobank))
			{
				$data_obj->zoobank = $obj->results->bindings[0]->zoobank->value;
			}
		
		}
		
		// clean wikispecies
		$data_obj->wikispecies = urldecode(str_replace('https://species.wikimedia.org/wiki/', '', $data_obj->wikispecies));
	
		//print_r($data_obj);
	}
	
	return $data_obj;

}

//----------------------------------------------------------------------------------------
function orcid_match($data_obj)
{
	$sparql = 'SELECT DISTINCT ?name ?orcid_creator ?orcid_name
WHERE
{
  GRAPH <https://biodiversity.org.au/afd/publication> {
  <' . $data_obj->author_uri . '> <http://schema.org/name> ?name .
?role <http://schema.org/creator> <' . $data_obj->author_uri . '>  .
?role <http://schema.org/roleName> ?roleName  .

?work <http://schema.org/creator> ?role  .

?work <http://schema.org/identifier> ?identifier .
?identifier <http://schema.org/propertyID> "doi" .
?identifier <http://schema.org/value> ?doi .
}
  
  GRAPH <https://orcid.org>
  {
    ?orcid_identifier <http://schema.org/value> ?doi .
    ?orcid_work <http://schema.org/identifier> ?orcid_identifier .
    
	?orcid_work <http://schema.org/creator> ?orcid_role  . 
    ?orcid_role <http://schema.org/roleName> ?orcid_roleName  .
    
    ?orcid_role <http://schema.org/creator> ?orcid_creator  .
    
    ?orcid_creator <http://schema.org/name> ?orcid_name .
  } 
  
  FILTER(?roleName = ?orcid_roleName)
}';

	$url = 'http://kg-blazegraph.sloppy.zone/blazegraph/sparql?query=' . urlencode($sparql);

	//echo $url;

	$json = get($url);
	
	// echo $json;

	$obj = json_decode($json);
	
	//print_r($obj);
	
	// check matches
	
	$best_match = 0;
		
	if (isset($obj->results->bindings))
	{
		$n = count($obj->results->bindings);
		
		for ($i = 0; $i < $n; $i++)
		{	
			//echo $obj->results->bindings[$i]->name->value . " " . $obj->results->bindings[$i]->orcid_name->value . "\n";
			
			$s1 = strlen($obj->results->bindings[$i]->name->value);
			$s2 = strlen($obj->results->bindings[$i]->orcid_name->value);
			
			$lev = levenshtein($obj->results->bindings[$i]->name->value, $obj->results->bindings[$i]->orcid_name->value);
			
			$similarity = 1 - (2 * $lev)/($s1 + $s2) . "\n";
			
			if ($similarity > 0.6)
			{			
				if ($similarity > $best_match)
				{
					$data_obj->orcid = str_replace('https://orcid.org/', '', $obj->results->bindings[$i]->orcid_creator->value);	
			
					$data_obj->name = $obj->results->bindings[$i]->name->value;

					$best_match = $similarity;			
				}
			}			
		}
	}
	
	if (isset($data_obj->orcid))
	{
		// Now we have ORCID, get any other identifiers	
			
		$sparql = 'SELECT *
		WHERE
		{
			?item wdt:P496 "' . $data_obj->orcid . '"
		  OPTIONAL {
			   ?item wdt:P213 ?isni .
				}
			  OPTIONAL {
			   ?item wdt:P214 ?viaf .
				}    
			  OPTIONAL {
			   ?item wdt:P18 ?image .
				} 
			  OPTIONAL {
			  	?article schema:about ?item .
				?article schema:isPartOf <https://species.wikimedia.org/> .
				} 	
			  OPTIONAL {
			   ?item wdt:P2038 ?researchgate .
				} 					
			  OPTIONAL {
			   ?item wdt:P586 ?ipni .
				} 
			  OPTIONAL {
			   ?item wdt:P2006 ?zoobank .
				} 		
		}';

		//echo $sparql;

		$json = sparql_query('https://query.wikidata.org/bigdata/namespace/wdq/sparql', $sparql);

		//echo $url;
	
		//echo $json;

		$obj = json_decode($json);
	
		// print_r($obj);
	
		if (isset($obj->results->bindings))
		{
			if (isset($obj->results->bindings[0]->item))
			{
				$data_obj->wikidata = str_replace('http://www.wikidata.org/entity/', '', $obj->results->bindings[0]->item->value);
			}
		
			if (isset($obj->results->bindings[0]->ipni))
			{
				$data_obj->ipni = $obj->results->bindings[0]->ipni->value;
			}
		
			if (isset($obj->results->bindings[0]->researchgate))
			{
				$data_obj->researchgate = $obj->results->bindings[0]->researchgate->value;
			}

			if (isset($obj->results->bindings[0]->article))
			{
				$data_obj->wikispecies = $obj->results->bindings[0]->article->value;
				$data_obj->wikispecies =  str_replace('https://species.wikimedia.org/wiki/', '', $data_obj->wikispecies);
			}
		
		
			if (isset($obj->results->bindings[0]->zoobank))
			{
				$data_obj->zoobank = $obj->results->bindings[0]->zoobank->value;
			}
		
		}
	
		//print_r($data_obj);
	}
	
	
	return $data_obj;


}


//----------------------------------------------------------------------------------------

$author_uri = 'https://biodiversity.org.au/afd/publication/#creator/r-mesibov';

$author_uri = 'https://biodiversity.org.au/afd/publication/#creator/r-leijs';

// Wikidata has as female, has ORCID but not with any DOIs, see 
// https://github.com/rdmpage/australia/issues/7#issuecomment-504242197
//$author_uri = 'https://biodiversity.org.au/afd/publication/%23creator/m-e-shackleton';

$author_uri ='https://biodiversity.org.au/afd/publication/%23creator/g-theischinger';

$author_uri ='https://biodiversity.org.au/afd/publication/%23creator/c-h-dietrich';

//$author_uri = 'https://biodiversity.org.au/afd/publication/%23creator/m--c-lariviere';

$author_uri ='https://biodiversity.org.au/afd/publication/%23creator/t-j-artois';

$author_uris = array(
'https://biodiversity.org.au/afd/publication/#creator/s-t-ahyong',
'https://biodiversity.org.au/afd/publication/#creator/h-paxton',
'https://biodiversity.org.au/afd/publication/#creator/g-cassis',
'https://biodiversity.org.au/afd/publication/#creator/m-j-fletcher',
'https://biodiversity.org.au/afd/publication/#creator/s-l-cameron',
'https://biodiversity.org.au/afd/publication/#creator/p-greenslade',
'https://biodiversity.org.au/afd/publication/#creator/a-i-camacho',
'https://biodiversity.org.au/afd/publication/#creator/c-a-car',
'https://biodiversity.org.au/afd/publication/#creator/a-slipinski',
'https://biodiversity.org.au/afd/publication/#creator/m-m-summers',
'https://biodiversity.org.au/afd/publication/#creator/i-beveridge',
'https://biodiversity.org.au/afd/publication/#creator/m-c-sands',
'https://biodiversity.org.au/afd/publication/#creator/d-jennings',
'https://biodiversity.org.au/afd/publication/#creator/g-s-taylor',
'https://biodiversity.org.au/afd/publication/#creator/p-jaloszynski',
'https://biodiversity.org.au/afd/publication/#creator/g-r-allen',
'https://biodiversity.org.au/afd/publication/#creator/h-j-weaver',
'https://biodiversity.org.au/afd/publication/#creator/j-m-wang',
'https://biodiversity.org.au/afd/publication/#creator/h-m-smith',
'https://biodiversity.org.au/afd/publication/#creator/t-r-c-lee',
'https://biodiversity.org.au/afd/publication/#creator/t--m-lee',
'https://biodiversity.org.au/afd/publication/#creator/m-c-yu',
'https://biodiversity.org.au/afd/publication/#creator/l-y-yang',
'https://biodiversity.org.au/afd/publication/#creator/r-j-ellis',
'https://biodiversity.org.au/afd/publication/#creator/a-d-austin',
'https://biodiversity.org.au/afd/publication/#creator/v-w-framenau',
'https://biodiversity.org.au/afd/publication/#creator/f-c-thompson',
'https://biodiversity.org.au/afd/publication/#creator/m-i--stevens',
'https://biodiversity.org.au/afd/publication/#creator/m-v-erdmann',
'https://biodiversity.org.au/afd/publication/#creator/d-a-craig',
'https://biodiversity.org.au/afd/publication/#creator/l-g-cook',
'https://biodiversity.org.au/afd/publication/#creator/y-ma',
'https://biodiversity.org.au/afd/publication/#creator/g-j-anderson',
'https://biodiversity.org.au/afd/publication/#creator/m-s-harvey',
'https://biodiversity.org.au/afd/publication/#creator/d-s-chandler',
'https://biodiversity.org.au/afd/publication/#creator/b-k-k-chan',
'https://biodiversity.org.au/afd/publication/#creator/m-a-alonso-zarazaga',
'https://biodiversity.org.au/afd/publication/#creator/k-a-davies',
'https://biodiversity.org.au/afd/publication/#creator/r-caldara',
'https://biodiversity.org.au/afd/publication/#creator/p-hutchings',
'https://biodiversity.org.au/afd/publication/#creator/y-m-xu',
'https://biodiversity.org.au/afd/publication/#creator/b-c-baehr',
'https://biodiversity.org.au/afd/publication/#creator/g-w-rouse',
'https://biodiversity.org.au/afd/publication/#creator/r-g-oberprieler',
'https://biodiversity.org.au/afd/publication/#creator/d-j-bray',
'https://biodiversity.org.au/afd/publication/#creator/d-p-a-sands',
'https://biodiversity.org.au/afd/publication/#creator/t-kondo',
'https://biodiversity.org.au/afd/publication/#creator/d-c-main',
'https://biodiversity.org.au/afd/publication/#creator/r-a-king',
'https://biodiversity.org.au/afd/publication/#creator/p-doughty',
'https://biodiversity.org.au/afd/publication/#creator/l-a-mound',
'https://biodiversity.org.au/afd/publication/#creator/j-k-moulton',
'https://biodiversity.org.au/afd/publication/#creator/t-pape',
'https://biodiversity.org.au/afd/publication/#creator/t-a-evans',
'https://biodiversity.org.au/afd/publication/#creator/p-hudson',
'https://biodiversity.org.au/afd/publication/#creator/p-alderslade',
'https://biodiversity.org.au/afd/publication/#creator/c-moritz',
'https://biodiversity.org.au/afd/publication/#creator/f-zhang',
'https://biodiversity.org.au/afd/publication/#creator/k-b-r-hill',
'https://biodiversity.org.au/afd/publication/#creator/m-janes',
'https://biodiversity.org.au/afd/publication/#creator/d-c-currie',
'https://biodiversity.org.au/afd/publication/#creator/s-c-donnellan',
'https://biodiversity.org.au/afd/publication/#creator/r-c-pratt',
'https://biodiversity.org.au/afd/publication/#creator/y-l-zhang',
'https://biodiversity.org.au/afd/publication/#creator/y-p-lin',
'https://biodiversity.org.au/afd/publication/#creator/d-e-hill',
'https://biodiversity.org.au/afd/publication/#creator/j-c-otto',
'https://biodiversity.org.au/afd/publication/#creator/h-pang',
'https://biodiversity.org.au/afd/publication/#creator/s-j-b-cooper',
'https://biodiversity.org.au/afd/publication/#creator/z-q-zhao',
'https://biodiversity.org.au/afd/publication/#creator/e-vanderduys',
'https://biodiversity.org.au/afd/publication/#creator/c-w-o\'brien',
'https://biodiversity.org.au/afd/publication/#creator/m-j-hillyer',
'https://biodiversity.org.au/afd/publication/#creator/s-y-w-ho',
'https://biodiversity.org.au/afd/publication/#creator/h-huang',
'https://biodiversity.org.au/afd/publication/#creator/g-hormiga',
'https://biodiversity.org.au/afd/publication/#creator/k-jensen',
'https://biodiversity.org.au/afd/publication/#creator/w-f-humphreys',
'https://biodiversity.org.au/afd/publication/#creator/j-m-mcrae',
'https://biodiversity.org.au/afd/publication/#creator/j--s-park',
'https://biodiversity.org.au/afd/publication/#creator/m-humphrey',
'https://biodiversity.org.au/afd/publication/#creator/j-a-huey',
'https://biodiversity.org.au/afd/publication/#creator/m-batley',
'https://biodiversity.org.au/afd/publication/#creator/g-daniels',
'https://biodiversity.org.au/afd/publication/#creator/y--l-zhou',
'https://biodiversity.org.au/afd/publication/#creator/m-meregalli',
'https://biodiversity.org.au/afd/publication/#creator/k-hogendoorn',
'https://biodiversity.org.au/afd/publication/#creator/r-leijs',
'https://biodiversity.org.au/afd/publication/#creator/h-tanaka',
'https://biodiversity.org.au/afd/publication/#creator/n-p-lord',
'https://biodiversity.org.au/afd/publication/#creator/n-lo',
'https://biodiversity.org.au/afd/publication/#creator/j-p-wallman',
'https://biodiversity.org.au/afd/publication/#creator/c-h-dietrich',
'https://biodiversity.org.au/afd/publication/#creator/t-a-weir',
'https://biodiversity.org.au/afd/publication/#creator/s-shamsi',
'https://biodiversity.org.au/afd/publication/#creator/p-j-suter',
'https://biodiversity.org.au/afd/publication/#creator/j-marin',
'https://biodiversity.org.au/afd/publication/#creator/t-l-finston',
'https://biodiversity.org.au/afd/publication/#creator/m-jin',
'https://biodiversity.org.au/afd/publication/#creator/b-c-bellini',
'https://biodiversity.org.au/afd/publication/#creator/l-deharveng',
'https://biodiversity.org.au/afd/publication/#creator/d-l-emery',
'https://biodiversity.org.au/afd/publication/#creator/p-j-gullan',
'https://biodiversity.org.au/afd/publication/#creator/i-agnarsson',
'https://biodiversity.org.au/afd/publication/#creator/a-ewart',
'https://biodiversity.org.au/afd/publication/#creator/k-s-herzog',
'https://biodiversity.org.au/afd/publication/#creator/j-wellington-de-morais',
'https://biodiversity.org.au/afd/publication/#creator/n-g-cipola',
'https://biodiversity.org.au/afd/publication/#creator/p-s-cranston',
'https://biodiversity.org.au/afd/publication/#creator/w-m--weiner',
'https://biodiversity.org.au/afd/publication/#creator/l-w-popple',
'https://biodiversity.org.au/afd/publication/#creator/f-moravec',
'https://biodiversity.org.au/afd/publication/#creator/j-h-mynott',
'https://biodiversity.org.au/afd/publication/#creator/r-de-keyze',
'https://biodiversity.org.au/afd/publication/#creator/e-p-fagan-jeffries',
'https://biodiversity.org.au/afd/publication/#creator/s-v-pinzon-navarro',
'https://biodiversity.org.au/afd/publication/#creator/r--mesibov',
'https://biodiversity.org.au/afd/publication/#creator/n-l-gunter',
'https://biodiversity.org.au/afd/publication/#creator/g-theischinger',
'https://biodiversity.org.au/afd/publication/#creator/k-j-tilbrook',
'https://biodiversity.org.au/afd/publication/#creator/r-mesibov',
'https://biodiversity.org.au/afd/publication/#creator/c-g-messing',
'https://biodiversity.org.au/afd/publication/#creator/z-y-ding',
'https://biodiversity.org.au/afd/publication/#creator/a-m-hosie',
'https://biodiversity.org.au/afd/publication/#creator/g-a-kolbasov',
'https://biodiversity.org.au/afd/publication/#creator/m-f-downes',
'https://biodiversity.org.au/afd/publication/#creator/a-broadley',
'https://biodiversity.org.au/afd/publication/#creator/k--voigtlander',
'https://biodiversity.org.au/afd/publication/#creator/w-e-r--xylander',
'https://biodiversity.org.au/afd/publication/#creator/j-moreira',
'https://biodiversity.org.au/afd/publication/#creator/g-bourke',
'https://biodiversity.org.au/afd/publication/#creator/v-m-gnezdilov',
'https://biodiversity.org.au/afd/publication/#creator/p--decker',
'https://biodiversity.org.au/afd/publication/#creator/m-javidkar',
'https://biodiversity.org.au/afd/publication/#creator/n-vidal',
'https://biodiversity.org.au/afd/publication/#creator/y-duan',
'https://biodiversity.org.au/afd/publication/#creator/j-little',
'https://biodiversity.org.au/afd/publication/#creator/r-whyte',
'https://biodiversity.org.au/afd/publication/#creator/j-parapar',
'https://biodiversity.org.au/afd/publication/#creator/a-a-namyatova',
'https://biodiversity.org.au/afd/publication/#creator/m-f-braby',
'https://biodiversity.org.au/afd/publication/#creator/r-hosseini',
'https://biodiversity.org.au/afd/publication/#creator/s-okajima',
'https://biodiversity.org.au/afd/publication/#creator/g-san-martin',
'https://biodiversity.org.au/afd/publication/#creator/r-jocque',
'https://biodiversity.org.au/afd/publication/#creator/p-alvarez-campos',
'https://biodiversity.org.au/afd/publication/#creator/a-j-trotter',
'https://biodiversity.org.au/afd/publication/#creator/a-n-ostrovsky',
'https://biodiversity.org.au/afd/publication/#creator/d-j-tree',
'https://biodiversity.org.au/afd/publication/#creator/m-masumoto',
'https://biodiversity.org.au/afd/publication/#creator/m-a-castalanelli',
'https://biodiversity.org.au/afd/publication/#creator/p-d-purtiwi',
'https://biodiversity.org.au/afd/publication/#creator/a-d-brescovit',
'https://biodiversity.org.au/afd/publication/#creator/s-pekar',
'https://biodiversity.org.au/afd/publication/#creator/j-h-skevington',
'https://biodiversity.org.au/afd/publication/#creator/d-l-rabosky',
'https://biodiversity.org.au/afd/publication/#creator/e-kauschke',
'https://biodiversity.org.au/afd/publication/#creator/w-mohrig',
'https://biodiversity.org.au/afd/publication/#creator/k-a-meiklejohn',
'https://biodiversity.org.au/afd/publication/#creator/a-bordoni',
'https://biodiversity.org.au/afd/publication/#creator/j-p-caceres-chamizo',
'https://biodiversity.org.au/afd/publication/#creator/j-sanner',
'https://biodiversity.org.au/afd/publication/#creator/v-michelsen',
'https://biodiversity.org.au/afd/publication/#creator/a-m-giroti'
);

/*
$author_uris = array(
'https://biodiversity.org.au/afd/publication/#creator/s-t-ahyong',
);
*/

/*
$author_uris = array(
'https://biodiversity.org.au/afd/publication/#creator/a-i-camacho',
'https://biodiversity.org.au/afd/publication/#creator/g-cassis',
'https://biodiversity.org.au/afd/publication/#creator/h-paxton',
'https://biodiversity.org.au/afd/publication/#creator/m-j-fletcher',
'https://biodiversity.org.au/afd/publication/#creator/p-greenslade',
'https://biodiversity.org.au/afd/publication/#creator/s-l-cameron',
'https://biodiversity.org.au/afd/publication/#creator/s-t-ahyong',
);
*/

/*
$author_uris = array(
'https://biodiversity.org.au/afd/publication/#creator/s-t-ahyong',
);
*/

$author_uris=array(
 "https://biodiversity.org.au/afd/publication/#creator/a-koehler" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-danis" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-t-ahyong" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-t-aguado" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-d-adlard" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-paxton" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-cassis" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-gillis" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-h-cribb" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-j-artois" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-de-ridder" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-d-edwards" ,
 "https://biodiversity.org.au/afd/publication/#creator/i--beveridge" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-adlard" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-masner" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-greenslade" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-m-gosliner" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-whiting" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-hall" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-fletcher" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-i-camacho" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-van-mulken" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-m-summers" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-van-dyck" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-t-jennings" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-h--cribb" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-jaloszynski" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-kontschan" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-jondelius" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-jennings" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-h-borneman" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-beveridge" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-edwards" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-k-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-slipinski" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-j-gilbert" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-davis" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-v-koehler" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-karanovic" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-casu" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-curini-galletti" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-e-billings" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-artois" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-w-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-huyse" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--c-lariviere" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-sikora" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-s-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-poorani" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-car" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-karanovic" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-e-hughes" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-edwards" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-s-hickman" ,
 "https://biodiversity.org.au/afd/publication/#creator/lauren-e-hughes" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-r-hamilton" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-i-jones" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-sato" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-d-whittington" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-palmer" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-ahyong" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-m-palmer" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-l-cameron" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-horak" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-a-thompson" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/c--y-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/s--g-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/t--m-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-scholtz" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-baker" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-m-baker" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-p-martens" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-y-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-r-c-lee" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-k-thompson" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-thomas" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-k--thomas" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-spencer" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-sands" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-taylor" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-thompson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-e-meyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/t--karanovic" ,
 "https://biodiversity.org.au/afd/publication/#creator/c--cassis" ,
 "https://biodiversity.org.au/afd/publication/#creator/g--cassis" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-gorczyca" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-d--adlard" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-richter" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-wallberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-d-f-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-t-schuh" ,
 "https://biodiversity.org.au/afd/publication/#creator/x--j-wang" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-s-melo" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-p-davis" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-r-allen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-r-wang" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-marquina" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-t-j-littlewood" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-a-hutchings" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-worsaae" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-g-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-c-thompson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-winston" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-winterbottom" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-thomson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-b-marion" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-s-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-w-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-e-jones" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-staples" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-l-miller" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-b-miller" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-j-weaver" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-wang" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-ward" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-schuh" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-l-searle" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-wang" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-schuh" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-pichelin" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-meyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-arnold" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-arnold" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-fauchald" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-baehr" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-whittington" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-k-wilson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-b-bennett" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-wilcox" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-f--anderson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h--baker" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-zwick" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-m-baker" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-bruce" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-norena" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-reid" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-rawlinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-ben-dov" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-bruce" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-gibbs" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-c-baehr" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-w-gibbs" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-murray" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-revis" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-m-reid" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-s-ceccarelli" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--f-landry" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-andres" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-f-davie" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-anderson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-reid" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-reid" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-neave" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-miller" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-mitchell" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-d-mitchell" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-g-matthews" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-b-miller" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-martens" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-l-bruce" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-fautin" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-h-colless" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-bray" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-h-choat" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-d-clements" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-p-schockaert" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-r-schockaert" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-p-o'connor" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-g-mitchell" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-n-reid" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-pullen" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-g-cogger" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-adams" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-g-nielsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-r-woodward" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-davies" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-m-collins" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-i-stevens" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-tessens" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-stevens" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-a-glover" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-bray" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-m-morrison" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-gomon" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-bruce" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-k-k-chan" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-g-booth" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-b-bruce" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-merkl" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-bouchet" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-day" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-i--stevens" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-schneider" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-d-schwartz" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-s-galil" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-schmidt" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-k-schmidt" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-stevens" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-i-gibson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-hutchings" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-holloway" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-dibattista" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-v-erdmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a--bell" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-griffiths" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-craig" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-g-cook" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-newman" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-schmidt" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-sasaki" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-b-stevens" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-robinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-davies" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-cox" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-davies" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-johnson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-c-heemstra" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-burn" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-v--erdmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-k-walker" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-walker" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-zwick" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-d-johnson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-last" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-kondo" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-main" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-warren" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-webster" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-watson" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-m-watson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-watson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-r-last" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-kessner" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-kohler" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-e-carpenter" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-l-webster" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-c-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-van-steenkiste" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-b-black" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-black" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-carpenter" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-johnson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-shaw" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-willems" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-s-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-t-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-y-main" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-f-hoese" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-johnson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-p-hobbs" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-b-hardy" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-hardy" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-alonso-zarazaga" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-blacket" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-f-rentz" ,
 "https://biodiversity.org.au/afd/publication/#creator/g--q-liu" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-k-larson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-mcdowell" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-main" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-b-martin" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-t-maddock" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-l-last" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-f-johnson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-mcmillan" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-fricke" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-scott" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-s-song" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-d-shaw" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-burghardt" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-liu" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-liu" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-liu" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-martin" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-martin" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-l-winterton" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-n-su" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-w-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-s-godfrey" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-gray" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-e-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-myers" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-mcdonald" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-m-shea" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-f-houston" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-martin" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-eeckhaut" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-di-domenico" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-randall" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-h-kuiter" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-j-tatarnic" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-r-barker" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-wiegmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-wishart" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-williams" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-t-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-potter" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-shaw" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-richards" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-f-ng" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-o-coleman" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-fukui" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-coleman" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-wells" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-bryce" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-s-chandler" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-edgar" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-myers" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-l-crowther" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-tatarnic" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-l--winterton" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-barker" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-m-barker" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j--blacket" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-shea" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-k-l-ng" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-g--brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-g-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-s-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-f-ponder" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-martinez" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-lanterbecq" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-caldara" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-glasby" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-m-linton" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-n-a-hooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-just" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-r-brown" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-g-bush" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-glasby" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-l-stewart" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-l-gray" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-m-hooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-r-rasmussen" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-e-escalona" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-escalona" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-j-richards" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-reader" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-nelson" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-kuschel" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-whisson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-c-wallace" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-harms" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-fromont" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-d-roberts" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-roberts" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-c-russell" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-j-richardson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-potter" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-m-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-j-richardson" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-b-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-l-merrin" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-r-smales" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-g-oberprieler" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/g--smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-stanisic" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-e-stoddart" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-shea" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-russell" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-w-rouse" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-lana" ,
 "https://biodiversity.org.au/afd/publication/#creator/x--s-chen" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-k-thomas" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-russell" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-yang" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-s-whisson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-yu" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-w--rouse" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-austin" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-a-marshall" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-k-oberprieler" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-marshall" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-marshall" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-ellis" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-f-smith-vaniz" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-harvey" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-p-a-sands" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-t-miglio" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-worheide" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-l-russell" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-c-poore" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-t-white" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-r-schultz" ,
 "https://biodiversity.org.au/afd/publication/#creator/h--wagele" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-w-framenau" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-smit" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-bieler" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-c-b-poore" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-wei" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-v-angel" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-harvey" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-b-renaud" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-f-wright" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-willan" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-fernholm" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-m-xu" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-v-wei" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-k-lowry" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-waeschenbach" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-rohner" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-e-walter" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-wagele" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-y-yang" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-s-wheeler" ,
 "https://biodiversity.org.au/afd/publication/#creator/l--f-yang" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-vacelet" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-huber" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-m-spratt" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-austin" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-morgan" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-w-mccallum" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-h-dijkstra" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-dyal" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-f-e-roper" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-g-wright" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-raadik" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-m-mincarone" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-m-jiang" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-j-smit" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-raven" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-anstis" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-bertozzi" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-aland" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-s-b-harvey" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-t-springthorpe" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-blake" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-v-parin" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-gon" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-dijkstra" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-raven" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-shackleton" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-shackleton" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-reimer" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-v-tkach" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-van-soest" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-b-hines" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-hoffman" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-spies" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-walsh" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-wong" ,
 "https://biodiversity.org.au/afd/publication/#creator/z--q-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-v--tkach" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kelly" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-benayahu" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-campbell" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-c-campbell" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-campbell" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-catullo" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-c-donnellan" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-clulow" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-donnellan" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-r-catalano" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-halajian" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-b-georgiev" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-ma" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-austin" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-hurley" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-alderslade" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-k-finn" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-gillett" ,
 "https://biodiversity.org.au/afd/publication/#creator/y--b-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-l-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-q-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-cairns" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-n-eschmeyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-iwatsuki" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--w-kim" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-short" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-r-peck" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-gofas" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-j-pusey" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-w-burrows" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-simon" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-robertson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-s-oliveira" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-f-lawrence" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-s-mcfadden" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-p-sharma" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-clarke" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-bain" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-clarke" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-pang" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-milledge" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-b-monteith" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-m-zhang" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-moore" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-d-moore" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-poliseno" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-t-reijnen" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-dean" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-svavarsson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-short" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-e-schnabel" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-gerken" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-h-fraser" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-(eds)-christidis" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-fisher" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-janes" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kottelat" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-hoskin" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-moritz" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-andreakis" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-e-boles" ,
 "https://biodiversity.org.au/afd/publication/#creator/s--p-huang" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-mahoney" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-p-hammer" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-c-price" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-price" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-wheaton" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-frankham" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-c-donnellan" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-p-van-ofwegen" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-miya" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-moore" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-moore" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-macpherson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-king" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-g-m-jamieson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-raxworthy" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-kraus" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-l-rowley" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-couper" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-doughty" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-couper" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-green" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-t-huber" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-kelly" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-christidis" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-c-markham" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-komai" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-elliot" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-b--cooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-cooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-zhao" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-price" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-q-zhao" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-rosenberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-pereyra" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-mutafchiev" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-l-palma" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-i-storey" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-o-wiley" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-vargas" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-mound" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-dooley" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-ryan" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-m-ryan" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-foster" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-elliot" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-t-mackie" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-m-oliver" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-oliver" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-mound" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a--mound" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-olsson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-k-moulton" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-t-chesser" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-n-j-chapple" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-w-low" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-huang" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-low" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-c-webb" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-prendini" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-g-fry" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-webb" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-vanderduys" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-pyron" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-sakai" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-douglas" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-koch" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-henderson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-halsey" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-douglas" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-k-dubey" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-stefani" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-pratt" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-d-price" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-g-kendrick" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-gillespie" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-lopez-gonzalez" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-s-gillespie" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-v-ramamurthy" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-owen" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-owen" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-amey" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-agassiz" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-s-watts" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-hunjan" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-hunter" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-cunningham" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-b-cooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-b-cooper" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-j-quicke" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-d-tennyson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-emmott" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-p-amey" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-zapata-guardiola" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-downie" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-trueman" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-simpson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-mccosker" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-rogers" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-f-nahrung" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-sorenson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-s-keogh" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-kealley" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-hudson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-hobson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-naumann" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-klompen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-n-bamber" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-marsden" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-b-hedges" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-jamieson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-bouchard" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-dimitrov" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-r-schram" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-giribet" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-hormiga" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-pearson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-n-andersen" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-ida" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-kobayashi" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-j-near" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-shiel" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-d-snyder" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-d--snyder" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-w-ho" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-y-w-ho" ,
 "https://biodiversity.org.au/afd/publication/#creator/h--s-ho" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-williamson" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-f-nowak" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-sakaue" ,
 "https://biodiversity.org.au/afd/publication/#creator/k--t-shao" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-t-shao" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-asahida" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-sado" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-maryan" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-mclean" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-guinot" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-d-seeman" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-b-halliday" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-h-mclean" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-d-mason" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-colloff" ,
 "https://biodiversity.org.au/afd/publication/#creator/x-li" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-li" ,
 "https://biodiversity.org.au/afd/publication/#creator/x-z-li" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-s-grutter" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-callan" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-scharff" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-halliday" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-kinnear" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-evans" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-s-gibb" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-evans" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-gunawardene" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-evans" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-w-spiegel" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-r-gunawardene" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-carpintero" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-peters" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-a--rose" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-b-chilton" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-silberman" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-pogonoski" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-i-platnick" ,
 "https://biodiversity.org.au/afd/publication/#creator/h--c-ho" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-c-ho" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-baba" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-g-rix" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-young" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-colloff" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-abbott" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-young" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-w-pietsch" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-j-page" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-f-humphreys" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-a-rose" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-m-rigby" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-j-kitching" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-wharton" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-wharton" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-budaeva" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-s-kent" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-melville" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-p-shoo" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-shoo" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-pepper" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-sass" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-hibino" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-moussalli" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-stuart-fox" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-lane" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-hartley" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-lane" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-ingram" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-b-mccormack" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-w-o'brien" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-marriott" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-w-hart" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-s-rossi" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-monod" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-postle" ,
 "https://biodiversity.org.au/afd/publication/#creator/d--tang" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-mckay" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-norman" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-lobl" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-kejval" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-tang" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-p-lin" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-alvarez" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-h-a-farache" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-dodd" ,
 "https://biodiversity.org.au/afd/publication/#creator/q-v-nguyen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-beard" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-beard" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-pape" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-n-hutchinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-b-gasser" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-mills" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-h-harris" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-m-kristensen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-g-hutchinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-b-malipatil" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-ochoa" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-k-c-churchill" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-m-hutchinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-bellis" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-curran" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-koenig" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-wynne" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-g-beu" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-d-hoffmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--y-rasplus" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-reitner" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-p-kristensen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-c-otto" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-gleeson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-gleeson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-hillyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-byrne" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-beaver" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-a-butcher" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-alvestad" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-boxshall" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-cabezas" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-engl" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-peterson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-s-curran" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-bellis" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-ireson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-james" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-b--malipatil" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-v-bochkov" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-cipriani" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-k-daley" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-h-harris" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-c-dickinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-a-harris" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-l-close" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-ferguson" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-tighe" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-g-foottit" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-f-newton" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-nguyen" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-currie" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-zhou" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-brice" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-batley" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-d-bennet" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-engel" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-groves" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-grove" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-l-s-garland" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-b-alexander" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-mcrae" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-le" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-de-barro" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-hill" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-e-hill" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-sak" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-jacobson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-castro" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-k-knihinicki" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-h-l-disney" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-hancock" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-hancock" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--cheng" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-cheng" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-maw" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--m-chavatte" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-ditrich" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-m-bauer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-mcevoy" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-pinkova" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-m-mcinnes" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-rius" ,
 "https://biodiversity.org.au/afd/publication/#creator/yu-i-kuzmin" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-jensen" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-b-r-hill" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-v-maynard" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-burks" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-w-heard" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-k--cantrell" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-r-pitcher" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-v-timms" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-jackson" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-q--x-wee" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-polaszek" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-hill" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hill" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-fitch" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-van-achterberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-lafontaine" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-fibiger" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-mutanen" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-wahlberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-zahiri" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-marcer" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-nascetti" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-paoletti" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-mattiucci" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-shamsi" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-cheng" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-d-o'hara" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-mcrae" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-j-sinclair" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-mulder" ,
 "https://biodiversity.org.au/afd/publication/#creator/x-shirley" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-scholz" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-hogendoorn" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-leijs" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-leys" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-ashe" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-r-sutcliffe" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-papp" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-heterick" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-woolley" ,
 "https://biodiversity.org.au/afd/publication/#creator/y--l-zhou" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-curletti" ,
 "https://biodiversity.org.au/afd/publication/#creator/h--p-aberlenc" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-f-hales" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-heraty" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-landau" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-stenger" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-constantine" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-l-cumming" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-eastwood" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-bellisario" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-knott" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-hadfield" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-jorgensen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-vitovec" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-chun" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-yeates" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-bird" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-fitch" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-borowiec" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-e-heterick" ,
 "https://biodiversity.org.au/afd/publication/#creator/y--kuzmin" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-vicente" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-m-vieira" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-i-luque" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-suter" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-m-o'loughlin" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-g-allsopp" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-belokobylskij" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-weir" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kemal" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-lavigne" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-lavigne" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-k-yeates" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-d--neville" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-d-neville" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-wagnerova" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-j-bott" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-wallach" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-nicholas" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-l-nicholas" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-kvetonova" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-kalinova" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kestranova" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kotkova" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kvac" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-o-shattuck" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-larochelle" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-dietrich" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-bellamy" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-v-glatz" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--l-cho" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-farrell" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-fayer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-hasenpusch" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-luque" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-g-hansen" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-stys" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-siddiqi" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-p-santos" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-f-duncan" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-c-borsboom" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-p-lord" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--l-justine" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-ivanov" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-a-ivanov" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-hodgson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-lord" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-lo" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-h-lawler" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-zeidler" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-r-teske" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-o-kocak" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-mcalister" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-daniels" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-macarisin" ,
 "https://biodiversity.org.au/afd/publication/#creator/k--j-ahn" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-elgueta" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-bousquet" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-l-justine" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-n-mathis" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-humphrey" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-humphrey" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-harcourt" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-hayashi" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-coughran" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-v-alves" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-bribiesca-contreras" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-m-vieira" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-l-mckenzie" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-irwin" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-irwin" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-weir" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j--vink" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-matsuda" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-blackledge" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-beatson" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-l-mullins" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-o'reilly" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-w-levi" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-m-joseph" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-t-duperre" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-santin" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-p-barton" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-jin" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-darragh" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-larsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-l-lymbery" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-skale" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-weigel" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-b-leschen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-n-kittel" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-how" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-grootaert" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-bartholomaeus" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--k-kuah" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l-ferreira" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-f-wayne" ,
 "https://biodiversity.org.au/afd/publication/#creator/q--h-fan" ,
 "https://biodiversity.org.au/afd/publication/#creator/c--symonds" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-symonds" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-symonds" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-ramsey" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-robin" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-hava" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kadej" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-perina" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-veera-singham" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-greaves" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-luter" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-e-pierce" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-d-pinto" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-saito" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-jaeger" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-p-weir" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-j-sharanowski" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j--sharkey" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-b-whitfield" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-joseph" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-eberhard" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-vink" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-j-finlay" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-beechey" ,
 "https://biodiversity.org.au/afd/publication/#creator/s--eberhard" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-m--eberhard" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-w-osborn" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-skinner" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-r-swanson" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-pakhomov" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-vanden-berghe" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-ozdikmen" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-finston" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-jin" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-l-finston" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-lorenz" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-whitfield" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-d-struthers" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-c-wainwright" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-m-tribull" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-o-azevedo" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-n-barbosa" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-w-greenfield" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-pace" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-emery" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-j-emery" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--p-emery" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-m-eberhard" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-vink" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-s-menke" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-l-edward" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-gullan" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--s-park" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-santana" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-s-sparks" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-e-stevenson" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-stoessel" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-dornburg" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-beardsley" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-amaoka" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-j--dumont" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-olesen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-pepperell" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-p-andrew" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-rawlings" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-imamura" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-l-semple" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-n-zahniser" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l-moir" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-d-b-eldridge" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-puthz" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-dayrat" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-fukuda" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-r-plant" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-m-giachino" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-koubbi" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l--moir" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-hallan" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-de-broyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-c-weeks" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-k-fujita" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-g-herbert" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-fujita" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-harding" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-burckhardt" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-kallies" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-nauendorf" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hodda" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-s-subias" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-pesic" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-aizawa" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-gullan" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-takagi" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-huey" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-de-grave" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-anker" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-waren" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-m-iliffe" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-okuno" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-cleva" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-w-ashelby" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-duris" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-m-gurr" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-k-mcalpine" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-paretas-martinez" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-pujade-villar" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-m-kay" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-e-golding" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-k-p-lim" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-sebastian" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-yoshino" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-riedel" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-senou" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-j-m-fransen" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-brandley" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-v-bogorodsky" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-l-iglesias" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-m-korovchinsky" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-rabet" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-marin" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-takeuchi" ,
 "https://biodiversity.org.au/afd/publication/#creator/q-kou" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-i-kukuev" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-k-lowry-" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-a-trunov" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-vecchione" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-horka" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-wanat" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-y--sinev" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-y-sinev" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-schwentner" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-jaschhof" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-dorchin" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-grund" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-adair" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-gagne" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-delean" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-jaschhof" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-j-duffy" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-roux" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-mcgrouther" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-piller" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-purcell" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-suzuki" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-d-n-hebert" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-mantilleri" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-mcalpine" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l-law" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-marin" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-stolarski" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-wilmer" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-tomaszewska" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-veenstra" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-g-manners" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-michalczyk" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-duhamel" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-eick" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-halse" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-l-sanders" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-m-overstreet" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-d-edgecombe" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-waldock" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-trieu" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-c-cutmore" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-lj-stiassny" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-barbosa" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-bradford" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-mesibov" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-dyce" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-l-dyce" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-gopurenko" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-s-bartlett" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-m-cohen" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-tanaka" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-uyeno" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-vilhelmsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-buffington" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-forshage" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-purcell" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-seitner" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-s-h-breure" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-cantacessi" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-s-cranston" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-mcgrouther" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-marquet" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-g-moolenbeek" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-vogiatzis" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-seret" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-fu" ,
 "https://biodiversity.org.au/afd/publication/#creator/o--lonsdale" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-lonsdale" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-f-donaldson" ,
 "https://biodiversity.org.au/afd/publication/#creator/r--mesibov" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-c-morse" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-s-loch" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-broadley" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-v-balushkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-c-murphy" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-buffington" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-e-diaz" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-n-murphy" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-strickman" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-m-fonseca" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-wilkerson" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-gerkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-akiyama" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-jay" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-putz" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-g-kirejtshuk" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-murdoch" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-jereb" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-nakano" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-waldock" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-n-krosch" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-a-saether" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-lambkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-marks" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l--lambkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-moulds" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-claremont" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-p-kenaley" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-f-wallman" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-d-mooi" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-nakabo" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-motomura" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-puckridge" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-g-messing" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-lambkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-y-ding" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-cerretti" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-kolesik" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-de-faveri" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-m-marris" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-nolan" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-meregalli" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-jager" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-mckinnon" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-levey" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-moulds" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-vahtera" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-p-liang" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-combefis" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-t-rapp" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-agnarsson" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-k-schliewen" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-c-ferrington-jr" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-drayson" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-wantiez" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kulbicki" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-chakrabarty" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-munari" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-davey" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-halse" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-j-van-nieukerken" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-cocking" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-rota" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-milla" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-fjellberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-gledhill" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-barlow" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-w-hoeksema" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-fehse" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-v-sorensen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-ott" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-dohrmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-de-chambrier" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-fyler" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-cielocha" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-s-herzog" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-g-mayoral" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-de-keyzer" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-fratini" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-hilton" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-bocak" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-leng" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-downes" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-p-l-marques" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-brabec" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-n-caira" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-brinkmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-c-schroeder" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-ueshima" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-f-novak" ,
 "https://biodiversity.org.au/afd/publication/#creator/b--lis" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-m-krishnankutty" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-endo" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-f-cavalcanti" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-j-tilbrook" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-will" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-hangay" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-zborowski" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-macdonald" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l-macdonald" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-malm" ,
 "https://biodiversity.org.au/afd/publication/#creator/y--man" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-santini" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hourston" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-m-warwick" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-dai" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-p-wallman" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-causse" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-farquharson" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-sidabalok" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-tanzler" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-p-fagan-jeffries" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-nakano" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-p-zakharov" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-hagiwara" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-malek" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-kuchta" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-ruhnke" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-melichar" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-skarzynski" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-wellington-de-morais" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-potapov" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-m--weiner" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-s-cocking" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-w-knudsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-c-schaeffner" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-r-mojica" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-m-fitzgerald" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-klautau" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-stevcic" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-d-lessard" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-burwell" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j-limpus" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-sun" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-limpus" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-c-bellini" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-deharveng" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-g-cipola" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-jordana" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-m-giblin-davis" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-ye" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-laver" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-hobbie" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-jackman" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-parkin" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-kanzaki" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-j-popic" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-r-ruhnke" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-c-marques" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-sattler" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-oosterbroek" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-bastien" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-d-mcmahan" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-novak" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-chisholm" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-k-diggles" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-m-blank" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-huynh" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-nguyen-duy-jacquemin" ,
 "https://biodiversity.org.au/afd/publication/#creator/p--decker" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-greenbaum" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-furuya" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-worthington-wilmer" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-p-aplin" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-b-potapov" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-hanger" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-stenner" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-dittrich-schroder" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-la-salle" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-harney" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-b-hoare" ,
 "https://biodiversity.org.au/afd/publication/#creator/k--voigtlander" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-e-r--xylander" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-wesener" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-d-schubart" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-zschoche" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-bickel" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-q--y-yong" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-q-yong" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-rican" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-c-shelly" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-f-foighil" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-m-hardman" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-de-chambrier" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-m-theiss" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-haukisalmi" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-j-petuch" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-sun" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-j--burwell" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-l-mackenzie" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-mackenzie" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-mont" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-de-keyze" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-moreira" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-m-hosie" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-valdes" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-a-kolbasov" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-zanol" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-l-zuparko" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-dos-s-c-da-silva" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-mather" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-p-maddison" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-n-r-forteath" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-s-nadein" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-bloechl" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-bopiah" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-resasco" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-koenemann" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-goldarazena" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-r-bauchan" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-hlavac" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-v-pinzon-navarro" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-lopardo" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-stepien" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-kohout" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-b-nascimento" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-h-fehlauer-ale" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-vandenspiegel" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-giblin-davis" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-m-lipp" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-hendrich" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-balke" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-michael-balke" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-olmi" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-schiffer" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-porch" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-hawlitschek" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-f-mcevey" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-ly" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-restuccia" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-duan" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-b-rowald" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-nagano" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-hassan" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-svenson" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--kolibac" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-severns" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-gerstmeier" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-fahey" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-lackner" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-l-deeleman-reinhold" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-zhadan" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-shibukawa" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-ameziane" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-g--messing" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-ewart" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-c-olive" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-popple" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-w-popple" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-geheber" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-r-dunz" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-dragova" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-i-ovtcharenko" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-jafaar" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-steinbauer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-p-tuttle" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-theischinger" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-suarez-morales" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-t-guzik" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-hori" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-r-delventhal" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-keith" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-buckle" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-kalfatak" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-herler" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-botero" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-simons" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-stahls" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-philippe" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-e-leung" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-n-kilgallen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-peart" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-steinbauer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-e-royer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-mccrae" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-abdo" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-e-pulis" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-m-kilgallen" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-peart" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-geiger" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-stastny" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-roudnew" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-kostro-ambroziak" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-moravec" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-kaila" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-criscione" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-teale" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-stankowski" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-p-prirodina" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-vigneux" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-taillebois" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-v-gorochov" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-f-braby" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-gladstone" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-h-thornhill" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-restrepo-ortiz" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-nordlander" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-l-f-magalhaes" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-selfa" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-n-schick" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-gershwin" ,
 "https://biodiversity.org.au/afd/publication/#creator/l--a-gershwin" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-dostine" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-v-(eds)-remsen-jr" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-michalik" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-klopfstein" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-cammack" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-lazuardi" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-rahardjo" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-pardede" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-yeeting" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-l-stehlik" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-ballantyne" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-jeffery" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-k-blend" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-p-pedler" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-sakurai" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-ferrer-suay" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-mata-cassanova" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-guerrero" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-scheffer" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-m-gnezdilov" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-s-thandar" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-macintosh" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-samyn" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-de-queiroz" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-nagasawa" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-elmberg" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-blias" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-r-copley" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-borovec" ,
 "https://biodiversity.org.au/afd/publication/#creator/a--penas" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-g-gocke" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-janussen" ,
 "https://biodiversity.org.au/afd/publication/#creator/e--rolan" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-triapitsyn" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-charlton-robb" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-i-cognato" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-l-doti" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-thormar" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-tachihara" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-alfaro" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-m-boesgaard" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-muljadi" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-f-a-toussaint" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-krapp-schickel" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-s-nyari" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-mckechnie" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hassan" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-siddall" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-tessler" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-rood-goldman" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-barmos" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-o-dronen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-b-bae" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-sellanes" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-j-bae" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-locker" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-locker" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-constant" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-b-emlet" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-k-a-mcnamara" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-v-kozlov" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-watharow" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-e-mcmaugh" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-ballerio" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-muricy" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-shamshev" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-fikacek" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-rocha" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-rocha" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-wall" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-gunter" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-x-qiao" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-heinrich" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-fernandez-pulpeiro" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-souto" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-reverter-gil" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-hoser" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-t-hoser" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-wuster" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-niedbala" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-j-salazar-vallejo" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-j-ter-poorten" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-c-kritsky" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-i-cartwright" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-e-seago" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-fiasca" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-dole-olivier" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-p-galassi" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-proszynski" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-a-jefferson" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-l-gunter" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-kundrata" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-s-edgerly" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-little" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-h-mynott" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-okamoto" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-tavares" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-d-b-ukuwela" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-a-burger" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-burger" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-barrio" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-borda" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-n-minoshima" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-capa" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-c-donellan" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-r-langlands" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-trondle" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-javidkar" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-maestrati" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-a-salisbury" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-olszanowski" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-w-wheat" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-shima" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-parslow" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-stiller" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-schweizer" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-g-storer" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-ten-have" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-g-rhind" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-baynes" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-e-migotto" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-iglikowska" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-koenders" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-schon" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-ordunna-martinez" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-bail" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-o'connell" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-haouchar" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-bunce" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-t-oberski" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-coblens" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-h-quay" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-hebron" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-r-popkin-hall" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-demir" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-g-brennan" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-gwiazdowicz" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-y-mutton" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-h-skevington" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-jager" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-vervaet" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-thoma" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-l-van-pel" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-recourt" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-whyte" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-parapar" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-v-martynov" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-c-rosenbaum" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-vidal" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-ruta" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-taggart" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-ehrmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-c-da-rocha-filho" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-s-engel-and-t-l-griswold-v-h-gonzalez" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-turco" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-bologna" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-a-grimaldi" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-okanishi" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-i-lauko" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-a-wiesner" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-skoracki" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-jabbar" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-difei-li" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-huby-chilton" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-h-burbidge" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-appleton" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-&-amey" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-bourke" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-s-sistrom" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-j-sistrom" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-hocknull" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-m-kurniasih" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-d-purtiwi" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-e-noack" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-h-moeseneder" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-bulirsch" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-bordoni" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-solodovnikov" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-jenkins-shaw" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-i-holwell" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-georges" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-kitchingman" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-steelcable" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-b-reardon" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-m-dekkers" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-p-berschauer" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-uiblein" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-buxton" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-fujii" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-zeng" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--c-durette-desset" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-digiani" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-c-durette-desset" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hoenemann" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-l-kwak" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-t-neiber" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-nyein" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-logiudice" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-j-prideaux" ,
 "https://biodiversity.org.au/afd/publication/#creator/p--l-ardisson" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-g-chavtur" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-carthew" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-pedraza" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-a-vanstone" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-wanjura" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-makinson" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-jakiel" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-rebecchi" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-k-kupriyanova" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-d-m-nogueira" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-tovar-hernandez" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-a-ten-hove" ,
 "https://biodiversity.org.au/afd/publication/#creator/k--handeler" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-stemmer" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-s-amorim" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-kurina" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-daneliya" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-sim-smith" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-e-shuttleworth" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-shuttleworth" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-san-martin" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-dixon-bridges" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-p-tinerella" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-bakken" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-zabka" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-r-s-ruiz" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-pekar" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-m-m-nogueira" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-kameda" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-iwan" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-darms" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-s-volschenk" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-shearn" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-a-boring" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-d-alpert" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-herrmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-p-marrow" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-l-boyer" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-r-moller" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-schwarzhans" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-w-schwarzhans" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-yabe" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a--namyatova" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-namyatova" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-weirauch" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-menard" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-wolski" ,
 "https://biodiversity.org.au/afd/publication/#creator/a--mututantri" ,
 "https://biodiversity.org.au/afd/publication/#creator/ch-weirauch" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-elias" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-tio" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-krogmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/y-sen-dunlop" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-blazewicz-paszkowycz" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-bertolani" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-t-drumm" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-blazewicz-paszkowyczb" ,
 "https://biodiversity.org.au/afd/publication/#creator/l--o-sanoamuang" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-trinh-dang" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-muuray" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-gomez-zurita" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-fitzhugh" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-faroni-perez" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-meiklejohn" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-scarabino" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-schuller" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-olah" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-a-johanson" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-kallsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-vilvens" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-g-ekins" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-marcroft" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-naro-maciel" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-p-mccord" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-pamungkas" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-j-garzon-orduna" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-purser" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-a-stange" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-trotter" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--tkoc" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-farnier" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-schirtzinger" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-r-curler" ,
 "https://biodiversity.org.au/afd/publication/#creator/j--jezek" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-v-penalba" ,
 "https://biodiversity.org.au/afd/publication/#creator/h--heiniger" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-heiniger" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-i-al-hakim" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-arrigoni" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-montano" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-k-bineesh" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-v-akhilesh" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-imai" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-e-rheindt" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-deveney" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-c-kearn" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-kearn" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-evans-gowing" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-l-talaba" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-j-lovette" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-k-rofle" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-keally" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-mecke" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-benzoni" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-m-abrams" ,
 "https://biodiversity.org.au/afd/publication/#creator/n-mobjerg" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-jozwiak" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-guidetti" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-serrano" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-cesari" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-carrerette" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-fontoura" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-a-korneyev" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-amato" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-vives" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-r-gustafsson" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-iwamoto" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hackel" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-l-mockford" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-p-arango" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-brenneis" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-maryam" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-p-strumpher" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-grimm" ,
 "https://biodiversity.org.au/afd/publication/#creator/c-taekul" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-kauschke" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-mineo" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-worthington-wlimer" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-rajmohana" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-mohrig" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-j-pivar" ,
 "https://biodiversity.org.au/afd/publication/#creator/h-segers" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-scarabino" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-a-valerio" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-masumoto" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-szymkowiak" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-runagall-mcnaull" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-guilbert" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-shofner" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-doganlar" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-j-sirvid" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-d-brescovit" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-m-patoleta" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-patoleta" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-a-lieschke" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-unno" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-kudlai" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-semeniuk" ,
 "https://biodiversity.org.au/afd/publication/#creator/f-cherot" ,
 "https://biodiversity.org.au/afd/publication/#creator/m--elias" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-hosseini" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-yato" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-m-giroti" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-crews" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-nichi" ,
 "https://biodiversity.org.au/afd/publication/#creator/o-lomholdt" ,
 "https://biodiversity.org.au/afd/publication/#creator/w-j-pulawski" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-ohl" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-h-dorfel" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-okajima" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-appleyard" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-reemer" ,
 "https://biodiversity.org.au/afd/publication/#creator/x-mengual" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-queiroz" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-ouvrard" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-r-askew" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-bromberek" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j-tree" ,
 "https://biodiversity.org.au/afd/publication/#creator/l--h-dang" ,
 "https://biodiversity.org.au/afd/publication/#creator/l--x-eow" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-j--tree" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-r-ulitzka" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-a-ryabov" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-eitschberger" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-v-zolotuhin" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-a-castalanelli" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-hollmann" ,
 "https://biodiversity.org.au/afd/publication/#creator/t-huelsken" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-alvarez-campos" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-lattig" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-osterhage" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-hulcr" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-w-breinholt" ,
 "https://biodiversity.org.au/afd/publication/#creator/b-mollet" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-l-rabosky" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-w-schuett" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-jindra" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-santos-silva" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-heffern" ,
 "https://biodiversity.org.au/afd/publication/#creator/d-w-de-little" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-silvestre" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-pabriks" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-ragionieri" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-venchi" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-krishna" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-p-bland" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-lyszkowski" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-krishna" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-p-caceres-chamizo" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-sanner" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-h-scheffrahn" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-michelsen" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-bloszyk" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-n-ostrovsky" ,
 "https://biodiversity.org.au/afd/publication/#creator/r-jocque" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-riesgo" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-goonan" ,
 "https://biodiversity.org.au/afd/publication/#creator/u-scheller" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-napierala" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-dylewska" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-vilasri" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-glowska" ,
 "https://biodiversity.org.au/afd/publication/#creator/i-laniecka" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-otley" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-koeda" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-shinomiya" ,
 "https://biodiversity.org.au/afd/publication/#creator/g-ogihara" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-matsunuma" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-bearez" ,
 "https://biodiversity.org.au/afd/publication/#creator/p-musikasinthorn" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-arbsuwan" ,
 "https://biodiversity.org.au/afd/publication/#creator/e-slipinska" ,
 "https://biodiversity.org.au/afd/publication/#creator/s-sanguansub" ,
 "https://biodiversity.org.au/afd/publication/#creator/z-komiya" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-anichtchenko" ,
 "https://biodiversity.org.au/afd/publication/#creator/k-meißner" ,
 "https://biodiversity.org.au/afd/publication/#creator/m-gotting" ,
 "https://biodiversity.org.au/afd/publication/#creator/v-i-radashevsky" ,
 "https://biodiversity.org.au/afd/publication/#creator/j-grego" ,
 "https://biodiversity.org.au/afd/publication/#creator/a-j-poustka" ,
 "https://biodiversity.org.au/afd/publication/#creator/l-l-moroz" ,
 );
 


if (0)
{
	foreach ($author_uris as $author_uri)
	{
		$author_uri = str_replace('%23', '#', $author_uri);
		$author_uri = str_replace('https://biodiversity.org.au/afd/publication/#creator/', '', $author_uri);
		
		$name = $author_uri;
		$name = str_replace('%23', '#', $name);
		$name = str_replace('https://biodiversity.org.au/afd/publication/#creator/', '', $name);	
		$name = mb_convert_case($name, MB_CASE_TITLE);
		$name = str_replace('-',' ', $name);
		
	
		$sql = 'INSERT IGNORE INTO afd_author_matching(author_uri, name) VALUES("' . addcslashes($author_uri, '"') . '", "' . addcslashes($name, '"') . '");';
		echo $sql . "\n";	
	}
	exit();
}


foreach ($author_uris as $author_uri)
{
	// clean
	$author_uri = str_replace('%23', '#', $author_uri);

	//echo $author_uri . "\n";

	// data object
	$data_obj = new stdclass;
	$data_obj->author_uri = $author_uri;
	
	if (0)
	{
		$data_obj = wikispecies_match($data_obj);
	}
	else
	{
		$data_obj = orcid_match($data_obj);
	}

	//print_r($data_obj);
	
	
	$keys = array();
	$values = array();
	
	foreach ($data_obj as $k => $v)
	{
		switch ($k)
		{
			case 'name':
			case 'orcid':
			case 'researchgate':
			case 'wikidata':
			case 'wikispecies':
			case 'zoobank':
				//$keys[] = $k;
				$values[] = $k . '=' . '"' . addcslashes($v, '"') . '"';
				break;
		
			default:
				break;
		}
	
	}
	
	//print_r($keys);
	//print_r($values);
	
	if (count($values) > 0)
	{
		//$sql = 'REPLACE INTO afd_author_matching (' . join(",", $keys) . ') VALUES (' . join(",", $values) . ') WHERE author_uri="' . str_replace('https://biodiversity.org.au/afd/publication/#creator/', '', $author_uri) . '";';
		
		$sql = 'UPDATE afd_author_matching SET ' . join(',', $values) . ' WHERE author_uri="' . str_replace('https://biodiversity.org.au/afd/publication/#creator/', '', $author_uri) . '";';		
		
		echo $sql . "\n";
	
	}
	
	
	
}

?>

