<?php

// Query for authors



//----------------------------------------------------------------------------------------
// get
function get($url, $format = "application/json")
{
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: " . $format, "User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405"));

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
// Does wikidata have author with this ZooBank id?
function wikidata_author_from_zoobank($zb)
{
	$item = '';
	
	$sparql = 'SELECT * WHERE { ?person wdt:P2006 "' . strtoupper($zb) . '" }';
	
	//echo $sparql . "\n";
	
	$url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($sparql);
	$json = get($url);
	
	//echo $json;
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if (isset($obj->results->bindings))
		{
			if (count($obj->results->bindings) != 0)	
			{
				$item = $obj->results->bindings[0]->person->value;
				$item = preg_replace('/https?:\/\/www.wikidata.org\/entity\//', '', $item);
			}
		}
	}
	
	return $item;
}

//----------------------------------------------------------------------------------------


//$zoobank = '148429B0-C477-4B3C-B24A-DDC55BD2769D'; // Four new Mouse Spider species (Araneae, Mygalomorphae, Actinopodidae, <i>Missulena</i>) from Western Australia

$zoobank = '36fa265f-22a5-4f5e-b9b0-5d14e23be297'; // A replacement name for Dayus Gerken, 2001 

//$zoobank = '054D5B51-29BA-4349-8B0A-70DDEA289DC9';

//$zoobank = 'CC398EF9-0438-4889-8B86-5D2336FF2883';

//$zoobank = '15337CC0-0F00-4682-97C0-DAAE0D5CC2BE'; // Wikidata originally had authorship around the wrong way

$zoobank = '4e3d030d-ea64-4822-8aa1-1427ebedc8db';
$zoobank = '6c2358f0-cee9-4473-8abe-15dc6770fcda';
$zoobank = '9851aa0d-0c52-4b9a-8d9b-0f2e31228ab9';
$zoobank = '30728fef-8d76-4c05-8f30-e3249532b64d';
$zoobank = '14400153-ac4e-4385-b257-1468a2fd81be';
$zoobank = '596fd364-e33a-4269-868e-ca6275e95d38';
$zoobank = '3b4d7966-762d-4d7d-accc-1a31f51fbd73';
$zoobank = 'b84880c8-a25d-4468-bbfe-eb995482fad8';
$zoobank = '84da1384-4a02-4131-a6a0-ff5fe73aa920';
$zoobank = 'e50b29dd-baef-4404-9901-5a45da1b6837';
$zoobank = '93905706-b88d-40d0-92d0-1c38d2124db7';
$zoobank = 'fb76ac3b-d8f6-4d4e-b738-c43da62199b1';
$zoobank = '0e70926f-abe7-42b0-87b8-15ca1a15d0a5';
$zoobank = '7760e22b-e679-4821-9993-e90be9cd1fb2';
$zoobank = 'b7b2e7b5-6796-4972-a342-349dafb62541';
$zoobank = '39ba47f8-dab0-44c6-a603-5656007cc629';
$zoobank = 'f133f221-1574-4df4-b178-4797037920b6';


$sparql = 'SELECT *
WHERE 
{
  GRAPH <http://zoobank.org> {
  VALUES ?work { <urn:lsid:zoobank.org:pub:' . strtoupper($zoobank) . '> } .
  ?work <http://schema.org/name> ?title .
  ?work <http://schema.org/creator> ?role . 
  ?role <http://schema.org/roleName> ?roleName . 
  ?role <http://schema.org/creator> ?person . 
  ?person <http://schema.org/familyName> ?familyName .
  ?person <http://schema.org/name> ?name .

  OPTIONAL {
    ?work <http://schema.org/identifier> ?identifier .
	?identifier <http://schema.org/propertyID> "doi" .
	?identifier <http://schema.org/value> ?doi .      
   }  
} 
        
  GRAPH <https://wikidata.org> {
    ?wiki_identifier <http://schema.org/value> "urn:lsid:zoobank.org:pub:' . strtoupper($zoobank) . '" .
    ?wiki_work <http://schema.org/identifier> ?wiki_identifier .
     ?wiki_work <http://schema.org/name> ?wiki_title .
    
   ?wiki_work <http://schema.org/creator> ?wiki_role . 
    ?wiki_role <http://schema.org/roleName> ?wiki_roleName . 
    ?wiki_role <http://schema.org/creator> ?wiki_person . 
    ?wiki_person <http://schema.org/name> ?wiki_name .

    OPTIONAL {
      ?wiki_person <http://schema.org/identifier> ?wiki_person_identifier .
      ?wiki_person_identifier <http://schema.org/propertyID> "orcid" .
      ?wiki_person_identifier <http://schema.org/value> ?orcid .
      }
    
   OPTIONAL {
      ?wiki_person <http://schema.org/identifier> ?wiki_person_identifier .
      ?wiki_person_identifier <http://schema.org/propertyID> "zoobank" .
      ?wiki_person_identifier <http://schema.org/value> ?wiki_zoobank_person .
      }
        
    
  }

FILTER (?roleName = ?wiki_roleName)
 
}
ORDER BY ?roleName

';


	$url = 'http://localhost:32775/blazegraph/namespace/alec/sparql?query=' . urlencode($sparql);
	
	//echo $sparql . "\n";
	

	//echo $url;

	$json = get($url);
	
	//echo $json;

	$obj = json_decode($json);
	
	//print_r($obj);
	
	
	$work = new stdclass;
	$work->authors = array();
	
	foreach ($obj->results as $results)
	{
		foreach ($results as $binding)
		{
			//print_r($binding);
			
			if (!isset($work->authors[$binding->person->value]))
			{
				$work->authors[$binding->person->value] = new stdclass;
				$work->authors[$binding->person->value]->wiki_name = array();
				
				$work->authors[$binding->person->value]->ok = true;
			}				
		
			foreach ($binding as $k => $v)
			{				
								
				switch ($k)
				{
					case 'title':
						$work->title = $v->value;
						break;
					case 'wiki_title':
						$work->wiki_title = $v->value;
						break;
				
						
					case 'roleName':
						$work->authors[$binding->person->value]->roleName = $v->value;
						break;

					case 'name':
						$work->authors[$binding->person->value]->zoobank_name = $v->value;
						break;

					case 'person':
						$work->authors[$binding->person->value]->zoobank_id = $v->value;
						break;

					case 'wiki_name':
						if (isset($v->{'xml:lang'}))
						{					
							$work->authors[$binding->person->value]->wiki_name[$v->{'xml:lang'}] = $v->value;
						}
						else
						{
							$work->authors[$binding->person->value]->wiki_name[] = $v->value;
						}
						break;

					case 'orcid':
						$work->authors[$binding->person->value]->wiki_orcid = $v->value;
						break;
						
					case 'wiki_zoobank_person':
						$work->authors[$binding->person->value]->wiki_zoobank_id = $v->value;
						break;
						
					case 'wiki_work':
						$work->qid = str_replace('http://www.wikidata.org/entity/', '', $v->value);
						break;
						
					default:
						break;
				
				
				}
			}
			
			
			
			
			
		}
	}
	
	print_r($work);
	
	echo "\n-------\n";
	
	// actions
	
	$quickstaments = '';
	$statements = array();
	
	// Sanity checks
	// Make sure ZooBank and Wikidata are talking about the same person
	foreach ($work->authors as $author)
	{
		$wiki_name = '';
		if (isset($author->wiki_name[0]))
		{
			$wiki_name = $author->wiki_name[0];
		}
		else
		{
			$wiki_name = $author->wiki_name['en'];
		}
		if ($wiki_name == '')
		{
			$author->ok = false;							
		}
		if ($author->ok)
		{
			// same/similar name?
			$d = levenshtein($wiki_name, $author->zoobank_name);
			
			if ($d > 3)
			{
				// names look different as strings			
				$author->ok = false;	
				
				// try and handle initials
				$p1 = explode(' ', $wiki_name);
				$p2 = explode(' ', $author->zoobank_name);
				
				//print_r($p1);
				//print_r($p2);
				
				$common = array_intersect($p1, $p2);
				//print_r($common);
				if (count($common) >= 2)
				{
					$author->ok = true;
				}
				
			}
			
			
		}
	}	
	
	foreach ($work->authors as $author)
	{
		if (!$author->ok)
		{
			echo "*** Badness: names don't match ***\n";
			print_r($author);
		}
		else
		{
			if (isset($author->wiki_name[0]))
			{
				// string
				echo $author->wiki_name[0] . "\n";
			
			
				$found = false;
			
				// 
				if (isset($author->zoobank_id))
				{
					// 
					$author_qid = wikidata_author_from_zoobank(str_replace('urn:lsid:zoobank.org:author:', '', $author->zoobank_id));
				
					if ($author_qid != '')
					{
						echo "Author=$author_qid\n";
					
						$found = true;
					
						// Add author 
						$statements[] = array($work->qid, 'P50', $author_qid, 'P1545', '"' . $author->roleName . '"', 'P1932', '"' . addcslashes($author->wiki_name[0], '"') . '"');
				
						// Delete author string
						$statements[] = array('-' . $work->qid, 'P2093', '"' . $author->wiki_name[0] . '"');

					
					
					}
				}
			
				if (!$found)
				{
					echo "Not found!\n";
				}
			}
			else
			{
				// thing
			
			
				// do we have ORCID?
			
				// do ZooBank ids match?
			
			
			
		
			}
		}
	
	}
	
print_r($statements);

foreach ($statements as $st)
{		
	$quickstatments .= join("\t", $st) . "\n";
}
echo $quickstatments;

?>

