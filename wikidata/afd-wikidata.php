<?php

/*


Step 1.

For an AFD author URI get  Wikispecies author link via sparql

If Wikispecies get Wikidata

Go back to Oz and get list of all DOIs for that author URI

For each DOI find Wikidata article
- if missing add to "list of DOIs to add"
- if found update author in Wikidata record for that article


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

$author_uri = 'https://biodiversity.org.au/afd/publication/#creator/r-mesibov';

$author_uri = 'https://biodiversity.org.au/afd/publication/#creator/r-leijs';

// Wikidata has as female, has ORCID but not with any DOIs, see 
// https://github.com/rdmpage/australia/issues/7#issuecomment-504242197
//$author_uri = 'https://biodiversity.org.au/afd/publication/%23creator/m-e-shackleton';

$author_uri ='https://biodiversity.org.au/afd/publication/%23creator/g-theischinger';

$author_uri = str_replace('%23', '#', $author_uri);

echo $author_uri . "\n";

$data_obj = new stdclass;

$data_obj->wikispecies_urls = array();

$sparql = 'SELECT DISTINCT ?name ?external_creator ?external_name
WHERE
{
  GRAPH <https://biodiversity.org.au/afd/publication> {
  <' .  $author_uri . '> <http://schema.org/name> ?name .
?role <http://schema.org/creator> <' . $author_uri . '>  .
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


	$url = 'http://kg-blazegraph.sloppy.zone/blazegraph/sparql?query=' . urlencode($sparql);

	//echo $url;

	$json = get($url);
	
	// echo $json;

	$obj = json_decode($json);
	
	//print_r($obj);
	
	if (isset($obj->results->bindings))
	{
		foreach ($obj->results->bindings as $binding)
		{
			// print_r($binding);	
			$data_obj->wikispecies_urls[] = $binding->external_creator->value;	
			
			$data_obj->name = $binding->name->value;
		}
	}
	
	$data_obj->wikispecies_urls = array_unique($data_obj->wikispecies_urls);
	
	if (count($data_obj->wikispecies_urls) > 0)
	{
		// Now we have Wikispecies links, make sure we resolve any redirects	
	
		echo "Wikispecies URLs\n";
		print_r($data_obj);
	
		$data_obj->wikispecies_cleaned= array();
	
		if (count($data_obj->wikispecies_urls) == 1)
		{
			$data_obj->wikispecies_cleaned = $data_obj->wikispecies_urls;
		}
		else
		{
			foreach ($data_obj->wikispecies_urls as $wikispecies_url)
			{
				$data_obj->wikispecies_cleaned[] = wikispecies_redirect($wikispecies_url);
			}
			$data_obj->wikispecies_cleaned = array_unique($data_obj->wikispecies_cleaned);
		}
		
		echo "Cleaned\n";
		print_r($data_obj);
		
		// test of having to encode
		if (1)
		{
			$data_obj->wikispecies_cleaned[0] = str_replace('https://species.wikimedia.org/wiki/', '', $data_obj->wikispecies_cleaned[0]);
			$data_obj->wikispecies_cleaned[0] = 'https://species.wikimedia.org/wiki/' . urlencode($data_obj->wikispecies_cleaned[0]);
			
			print_r($data_obj);
		}
		
		
		// Now we query Wikidata for this Wikispecies person
		
		$sparql = 'SELECT *
WHERE
{
    VALUES ?article {<' . $data_obj->wikispecies_cleaned[0] . '>}
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
	
	print_r($obj);
	
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
	
	print_r($data_obj);
	

		
		
		
		
	}

?>

