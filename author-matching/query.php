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


$zoobank = '38EC9B99-3180-4F77-984F-AEC2A43AB131';
$zoobank = 'B9B4322E-4550-46A7-A34B-3A8392156A7C';
$zoobank = 'E3F3B85F-4DB1-442C-9F05-61F90D5806F2';

$uuids=array(
'38EC9B99-3180-4F77-984F-AEC2A43AB131',
'B9B4322E-4550-46A7-A34B-3A8392156A7C',
'5DC9BC36-AD2C-48AE-9DED-E5D759D6E6BA',
'688BE888-50EF-4CFF-B174-7274F0387C01',
'C2C94DF9-2CE6-4C62-A5C9-91B77083F7E3',
'E3F3B85F-4DB1-442C-9F05-61F90D5806F2',
'CEDCAC99-8750-4F6E-973F-01B6F184582C',
'EF08C227-69BC-4843-82B3-4956E327FC00',
'CA1A5F10-7CBE-4C66-884A-C0E04A4D6B46',
'8960994D-A0C3-456C-8014-5C3DE34943CE',
'B8D6DBE1-9599-4328-A653-6B10532F5C48',
'C851A2E0-44F0-42A4-AC91-66A69DDD469A',
'B84A4EC4-47B2-4724-B9A0-94A312347BA8',
'B16902B8-81FB-4725-821D-09D49219B9E1',
'A6821FE8-6856-4854-B5D4-5AD02F361077',
'6A6D5028-A2E4-45D9-AA2E-7FD4EBED98FF',
'4CA7301C-29BC-403B-84F3-B24E7740A0BF',
);

// 2002
$uuids = array(
'ED27CB0F-48B5-4937-B67A-8BA0E9DA1B85',
'6249708B-2C33-43CC-8959-39403DB6AE90',
'71229E21-C346-4774-9419-1CA32CD6538C',
'74709F6D-C948-4193-9FCC-B5F50BC56853',
'760F89D6-50B7-4B51-B725-75F92EABB4D6',
'D0316893-564A-4B2B-AAE3-84CBA9B2FA73',
'AAF6B289-2C53-4E1D-81EF-69C7783D6624',
'70AE5EDF-0CB5-4FEF-819F-FEF9BB71F6EF',
'DB5D60C8-DA2E-40E9-A357-DE0C1F5A4948',
'4E01C550-8435-4DF2-A163-433836C53E5E',
'F83F92BE-CC8F-43AD-B597-54FEC142D98A',
'891FBD83-2857-4BC2-A93C-AA70FB2E9020',
'6D611503-EE3F-4CBD-A259-83D5DDCE99B3',
'45B52727-ABB6-4A2C-BAC1-8B8778D760E0',
'37204E22-215A-43ED-8B83-71A641B401EE',
'94019BF0-9364-4591-8CBD-D65119CAFE71',
'0DD39E40-EC92-4F3B-AF8B-8C49F6D52494',
'3D124B31-489A-4F6E-9D18-A3D5F9EA7CC9',
'E18EAAA9-D9EE-49BC-9596-3EEB6BFCE1B5',
'E425B881-6CB3-4A2D-910C-685C0B036701',
'B5CADB4E-0419-4591-A775-202F04C7EB25',
'B36587A1-248A-4194-8424-46C9BBA15606',
'B44B6DB3-31E1-4773-B400-E36D4C04F341',
'2EB3FDF9-76B5-44D9-90BD-AEEF4C1E94FC',
'5225D59B-32E3-40C9-B470-B781ABE842C5',
'61678D80-0D7C-4B4A-89C5-8E576B7FF383',
'E0FB45AC-BB7A-4094-A355-119CE3BD2189',
'39E52C82-B092-44F2-A6EC-A05D751DC1CE',
'920E0BDA-9021-463C-92AD-000F936E2E2A',
'8AA7E27D-C5B5-4C13-B314-9113C29A5884',
'6449FD75-F33F-425B-95E6-D4E262A02BF5',
'F3940AD5-CA47-4374-9654-F95C656EF48E',
'BE224007-1110-42EB-BC83-0485D77743AB',
'C12AB046-A75E-4EE1-8BE0-22DC53057EDB',
'004E590F-5359-4833-B050-1829A4E09E18',
'5F5FD547-8ECC-4CE7-BFEB-C6BAF01CCAB6',
'3D6F7282-2B51-45DD-829C-25A37C061376',
'85266CE9-43F7-46EE-AD5C-32B79837BE8B',
'82A36508-B4A6-487A-99DF-C084639849E1',
'254D267A-ABCB-41B2-BE61-33540152F510',
'F467BD8E-7274-4497-A94A-E3266DF6387A',
'B8C8A328-2262-4C9F-AD46-9C03B3DE02DA',
'4C17FA5D-A039-4B3C-A3A7-02FE007DD973',
'13173D1C-3972-426B-9B9A-6703E0A61633',
'97D7B307-B2C2-44DD-B7DB-1C363B48A16D',
'83EF0774-F1A0-430D-8E4D-E53AE54A8FD1',
'FC47A8C6-0E19-4D13-821D-4909A7D9F267',
'312A1FB1-38D7-4308-866F-E84762833992',
'A1F67A01-8374-4D40-B55A-F89E971F196E',
'3274F1EA-EDD2-4588-AE53-67BED2DA7308',
'095E36C4-C3EF-42D9-B1EE-F9C4314E9799',
'1B607B19-E448-42D5-85BD-ADC1AD157C93',
'2EE9E183-4F8E-4125-BEE0-BD1F29307B6C',
'6EECA224-63E1-4668-9FAF-7C0F5F1F0F7F',
'458A3369-2CBE-4A2B-9E6D-6647187897BF',
'CE66BE0C-1BDD-47E2-A6F0-6FFF7529ECC7',
'E9765D2C-06BD-4CC4-9705-14804248F4F3',
'4B0669C3-640F-4824-9791-67894D22CFF3',
'8DEF0C7C-EE85-411F-8CCD-5A91E6D99588',
'8BE58A27-02C7-489C-BD5E-CF70448F86BE',
'CCC02B95-39AA-4937-B26E-682AAB13BF07',
'5FF226EF-4214-4329-8EA0-94F9F8D2F17E',
'B8BEBA0F-C5E3-49A6-AC6D-EB4C8DBC756F',
'62575399-8CDD-4108-8722-71536ABE24D6',
'D2394C7C-3634-47CE-A0D0-E1CF6E2BC1C0',
'9A163792-F832-44F8-96D5-63DE7A299C67',
'424F5C93-0C54-4EC2-AE63-8BEC834C275C',
'6CD71FEF-8D2C-409D-827D-1E4291F375D5',
'EE640F25-A96B-4BD1-BB04-0626494476A6',
'B99CFC86-E49D-43C9-9036-15D438F36CAE',
'1EE82382-A68F-43C5-9969-81127E41F6C2',
'FE1A9129-E905-497F-AA70-5193AF6D231A',
'C25B76B3-853A-4B1C-83DF-E4DF625802B8',
'4AE89E74-CF73-4AC7-85AF-874500377C39',
'B537D7D3-FE78-4444-8A80-F1BC1F907F17',
'7D252345-780E-45EA-A565-B951E4FB1C9D',
'205A2D6C-2084-4C1F-AE20-B8AFEFA953A8',
'D5AD229B-8086-4A87-BDB7-D62264980392',
'A2421529-1EE2-42F4-8D57-54A67A1F0201',
'E7242D7B-1509-4353-B884-D01F98883D4F',
'90E6EDC2-CA27-451E-8C65-C248C6779E64',
'DB93409F-D7B3-48B6-8FFA-639CE44D9468',
'CB2C452E-C9CB-49A0-AB2F-639FC3ACA149',
'0088234D-9E9E-4408-AC3D-6DC71EC656C1',
'49C28420-CA4D-4C86-8233-800AC5212130',
'69FA01B5-8619-464F-8107-2FF4B932239C',
'1294E367-51CD-4F1E-8F1B-045221740A95',
'242291D4-C1B8-4084-BC69-66CC1F57E6B0',
'6A82DEDC-B561-45CA-A40B-6ACC4CC61360',
'A70E7202-E3E8-4042-A75E-25BF50152C43',
'513129EC-C65D-4DB7-8660-0E6E056794F0',
'AEF6ADB0-2AA5-4054-B3B3-6C30B9E78D21',
'BF89466E-C4C2-42AE-9489-F739A358E7BE',
'FECB90D9-4401-4645-BD2D-239D3C015B26',
'F91535C6-D2C9-4B32-9D16-A698A1EAD960',
'09693521-3889-4D26-9433-70036D5C34C1',
'7F5A15D4-55AA-421A-B6BB-E771E5ECB79B',
'4D166572-C361-4585-B065-4ED69A42447C',
'91D7D971-2AEF-4CC3-A8A1-C428E974AF65',
'A16D3A1A-9C98-4632-AA33-01BA523442AE',
'F1BD1E39-7E3A-41F2-835D-EFD1CFBD779D',
);

	

$statements = array();

$missing = array();

foreach ($uuids as $zoobank)
{

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
	
		echo "\n-----------------------------------------\n";
	
		// actions
	
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
						
						$missing_author = new stdclass;
						$missing_author->name = $author->zoobank_name;
						$missing_author->id = str_replace('urn:lsid:zoobank.org:author:', '', $author->zoobank_id);
						
						$missing[] = $missing_author;
						
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
}
	
//print_r($statements);

$quickstaments = '';

foreach ($statements as $st)
{		
	$quickstatments .= join("\t", $st) . "\n";
}
echo $quickstatments;

print_r($missing);

?>

