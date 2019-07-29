<?php

// Wikidata item to JSON-LD

require_once 'vendor/autoload.php';

//----------------------------------------------------------------------------------------
function get($url, $user_agent='', $content_type = '')
{	
	$data = null;

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);

	if ($content_type != '')
	{
		$opts[CURLOPT_HTTPHEADER] = array("Accept: " . $content_type);
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
// Cites
function get_wikidata_cites($qid)
{
	$list = array();
	
	// use group cat to handle cases whre we have multiple titles for an article
	$sparql = 'SELECT ?work (GROUP_CONCAT(?title;SEPARATOR="/") AS ?name) 
{
   wd:' . $qid . '  wdt:P2860 ?work .
  ?work wdt:P1476 ?title .
}
GROUP BY ?work';
	
	
	$url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($sparql);
	$json = get($url, '', 'application/json');
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if (isset($obj->results->bindings))
		{
			foreach ($obj->results->bindings as $binding)
			{
				$item = new stdclass;

				$id = $binding->work->value;
				$id = preg_replace('/https?:\/\/www.wikidata.org\/entity\//', '', $id);
				
				$item->id = $id;
				
				$item->name = $binding->name->value;
				
				$list[] = $item;
			}
		}
	}
	
	return $list;
}

//----------------------------------------------------------------------------------------
// Cited by
function get_wikidata_cited_by($qid)
{
	$list = array();
	
	$sparql = 'SELECT * WHERE {?work wdt:P2860 wd:' . $qid . ' . ?work wdt:P1476 ?title . }';
	
	$url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($sparql);
	$json = get($url, '', 'application/json');
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if (isset($obj->results->bindings))
		{
			foreach ($obj->results->bindings as $binding)
			{
				$item = new stdclass;

				$id = $binding->work->value;
				$id = preg_replace('/https?:\/\/www.wikidata.org\/entity\//', '', $id);
				
				$item->id = $id;
				
				$item->title = $binding->title->value;
				
				$list[] = $item;
			}
		}
	}
	
	return $list;
}


//----------------------------------------------------------------------------------------
// get Wikidata record for person
function get_wikidata_author_ld($qid)
{
	$url = 'https://www.wikidata.org/wiki/Special:EntityData/' . $qid . '.json';

	$json = get($url);

	$obj = json_decode($json);
	
	
	// Be careful not to assume that entity matches $qid as if it's a redirect
	// we won't have the same id (sigh)
	// Based on https://stackoverflow.com/a/5251971/9684
	foreach ($obj->entities as $key => $value)
	{
		$qid = $key;
		break;
	}
	
	$author = new stdclass;
	$author->id = 'http://www.wikidata.org/entity/' . $qid;
	
	$author->names = array();
	
	
	foreach ($obj->entities->{$qid}->labels as $language => $label)
	{
		$author->names[] = '"' . $label->value . '"@' . $language;	
	}

	foreach ($obj->entities->{$qid}->claims as $p => $claims)
	{
		switch ($p)
		{
				
			// ORCID
			case 'P496':
				foreach ($claims as $claim)
				{
					$author->orcid = $claim->mainsnak->datavalue->value;
				}			
				break;

			// Zoobank author
			case 'P2006':
				foreach ($claims as $claim)
				{
					$author->zoobank = $claim->mainsnak->datavalue->value;
				}			
				break;
				

			default:
				break;
		}
	}	
	
	return $author;
}	

//----------------------------------------------------------------------------------------
// get Wikidata record
function get_wikidata_container_ld(&$triples, $qid)
{
	$property_map = array(
		'P1476' => '<http://schema.org/name>',
		'P236' => '<http://schema.org/issn>',
	);

	$subject = '<http://www.wikidata.org/entity/' . $qid . '>';

	$url = 'https://www.wikidata.org/wiki/Special:EntityData/' . $qid . '.json';

	$json = get($url);

	$obj = json_decode($json);

	//echo $json;

	//echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	
	$triple = array();
	$triple[] = $subject;
	$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';					
	$triple[] = '<http://schema.org/Periodical>';
	
	$triples[] = $triple;
	
	
	foreach ($obj->entities->{$qid}->claims as $p => $claims)
	{
		//echo $p . "\n";
		
		switch ($p)
		{
			// title
			case 'P1476':			
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = $property_map[$p];					
					$triple[] = '"' . addcslashes($claim->mainsnak->datavalue->value->text, '"') . '"' . '@' . $claim->mainsnak->datavalue->value->language;
					
					$triples[] = $triple;
				}			
				break;
				
			// ISSN
			case 'P236':
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = $property_map[$p];				
					$triple[] = '"' . $claim->mainsnak->datavalue->value . '"';
					
					$triples[] = $triple;				
				}			
				break;
				

			default:
				break;
		}
	}	
	
}	


//----------------------------------------------------------------------------------------
function wikidata_identifier(&$triples, $subject, $namespace, $value)
{
	if (0)
	{
		$bnode = '_:' . $namespace;
	}
	else
	{	
		$subject_id = $subject;
		$subject_id = str_replace('<', '', $subject_id);
		$subject_id = str_replace('>', '', $subject_id);
		
		$bnode = '<' . $subject_id . '#'  . $namespace . '>';
	}
	
	$triple = array();
	$triple[] = $subject;
	$triple[] = '<http://schema.org/identifier>';					
	$triple[] = $bnode;
	$triples[] = $triple;
	
	
	switch ($namespace)
	{
		case 'zoobankauthor':
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/propertyID>';					
			$triple[] = '"zoobank"';
	
			$triples[] = $triple;
	
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/value>';

			$triple[] = '"urn:lsid:zoobank.org:author:' . strtoupper($value) . '"';
			$triples[] = $triple;
			break;
	
		case 'zoobankpub':
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/propertyID>';					
			$triple[] = '"zoobank"';
	
			$triples[] = $triple;
	
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/value>';

			$triple[] = '"urn:lsid:zoobank.org:pub:' . strtoupper($value) . '"';
			$triples[] = $triple;
			break;
	
		default:
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/propertyID>';					
			$triple[] = '"' . $namespace . '"';
	
			$triples[] = $triple;
	
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/value>';

			$triple[] = '"' . strtolower($value) . '"';
			$triples[] = $triple;
			break;
	}		
	
	
	
	switch ($namespace)
	{
		case 'doi':
			$triple = array();
			$triple[] = $subject;
			$triple[] = '<http://schema.org/sameAs>';					
			$triple[] = '"https://doi.org/' . strtolower($value) . '"';
			
			$triples[] = $triple;
			break;					

		case 'jstor':
			$triple = array();
			$triple[] = $subject;
			$triple[] = '<http://schema.org/sameAs>';					
			$triple[] = '"https://www.jstor.org/stable/' . $value . '"';
			
			$triples[] = $triple;
			break;					


		case 'zoobankpub':
			$triple = array();
			$triple[] = $subject;
			$triple[] = '<http://schema.org/sameAs>';					
			$triple[] = '"urn:lsid:zoobank.org:pub:' . strtoupper($value) . '"';
			
			$triples[] = $triple;
			break;					
			
		default:
			break;
	}
}					

//----------------------------------------------------------------------------------------
// get Wikidata record
function get_wikidata_work_ld(&$triples, $qid, $use_role = true)
{
	$property_map = array(
		'P1476' => '<http://schema.org/name>',
		
		'P304' => '<http://schema.org/pagination>',
		'P433' => '<http://schema.org/issueNumber>',
		'P478' => '<http://schema.org/volumeNumber>',
		
		'P577' => '<http://schema.org/datePublished>',
	
	);

	$subject_id = 'http://www.wikidata.org/entity/' . $qid;

	$subject = '<' . $subject_id . '>';


	$url = 'https://www.wikidata.org/wiki/Special:EntityData/' . $qid . '.json';

	$json = get($url);

	$obj = json_decode($json);

	//echo $json;

	//echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	
	//print_r($obj);
	
	$triple = array();
	$triple[] = $subject;
	$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';					
	$triple[] = '<http://schema.org/ScholarlyArticle>';
	
	$triples[] = $triple;
	
	$authors = array();
	
	foreach ($obj->entities->{$qid}->claims as $p => $claims)
	{
		//echo $p . "\n";
		
		switch ($p)
		{
			// title
			case 'P1476':			
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = $property_map[$p];					
					$triple[] = '"' . addcslashes($claim->mainsnak->datavalue->value->text, '"') . '"' . '@' . $claim->mainsnak->datavalue->value->language;
					
					$triples[] = $triple;
				}			
				break;
				
			// journal
			case 'P1433':
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = '<http://schema.org/isPartOf>';					
					$triple[] = '<http://www.wikidata.org/entity/' . $claim->mainsnak->datavalue->value->id . '>';	
					
					$triples[] = $triple;				
				
					get_wikidata_container_ld($triples, $claim->mainsnak->datavalue->value->id);
				}			
				break;
			
			// authors and author strings
			case 'P2093':
				foreach ($claims as $claim)
				{
					$author_string = $claim->mainsnak->datavalue->value;
					if (isset($claim->qualifiers->P1545))
					{
						$order = $claim->qualifiers->P1545[0]->datavalue->value;
						$authors[$order] = $author_string;
					}
				}			
				break;
				
			case 'P50':
				foreach ($claims as $claim)
				{
					$author = get_wikidata_author_ld($claim->mainsnak->datavalue->value->id);
					
					if (isset($claim->qualifiers->P1545))
					{
						$order = $claim->qualifiers->P1545[0]->datavalue->value;
						$authors[$order] = $author;
					}
						
				}			
				break;

			// date
			case 'P577':
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = $property_map[$p];	
					
					$value = $claim->mainsnak->datavalue->value->time;
					
					$value = preg_replace('/^\+/', '', $value);
					$value = preg_replace('/T.*$/', '', $value);
					
									
					$triple[] = '"' . $value . '"';
					
					$triples[] = $triple;
				}
				break;			
			
			// simple properties			
			case 'P304':
			case 'P433':
			case 'P478':			
				foreach ($claims as $claim)
				{
					$triple = array();
					$triple[] = $subject;
					$triple[] = $property_map[$p];					
					$triple[] = '"' . addcslashes($claim->mainsnak->datavalue->value, '"') . '"';
					
					$triples[] = $triple;
				}			
				break;
				
			// identifiers
			case 'P356':
				foreach ($claims as $claim)
				{
					wikidata_identifier($triples, $subject, 'doi', $claim->mainsnak->datavalue->value);
				}			
				break;
				
			case 'P698':
				foreach ($claims as $claim)
				{
					wikidata_identifier($triples, $subject, 'pmid', $claim->mainsnak->datavalue->value);
				}			
				break;

			case 'P888':
				foreach ($claims as $claim)
				{
					wikidata_identifier($triples, $subject, 'jstor', $claim->mainsnak->datavalue->value);					
				}			
				break;				

			case 'P2007':
				foreach ($claims as $claim)
				{
					wikidata_identifier($triples, $subject, 'zoobankpub', $claim->mainsnak->datavalue->value);					
				}			
				break;				
		
			default:
				break;
		}
	
	}
	
	ksort($authors);
	//print_r($authors);
	
	$index = 0;
	
	foreach ($authors as $k => $v)
	{
		$index++;
		
		if (is_object($v))
		{
			$id = '<' . $v->id . '>';
	
			$triple = array();
			$triple[] = $id;
			$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';					
			$triple[] = '<http://schema.org/Person>';
				
			$triples[] = $triple;
		
			foreach ($v->names as $name)
			{
				$triple = array();
				$triple[] = $id;
				$triple[] = '<http://schema.org/name>';					
				$triple[] = $name;
			
				$triples[] = $triple;
			}
			
			if (isset($v->orcid))
			{
				// identifier
				wikidata_identifier($triples, $id, 'orcid', $v->orcid);
			
				// same as(?)
				$triple = array();
				$triple[] = $id;
				$triple[] = '<http://schema.org/sameAs>';					
				$triple[] = '"https://orcid.org/' . $v->orcid . '"';
			
				$triples[] = $triple;
				
			}

			if (isset($v->zoobank))
			{
				// identifier
				wikidata_identifier($triples, $id, 'zoobankauthor', $v->zoobank);
			}

			if ($use_role)
			{
				// Role to hold author position
				$role_id = '<' . $subject_id . '#role/' . $index . '>';
				
				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';
				$triple[] = '<http://schema.org/Role>';
				$triples[] = $triple;

				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://schema.org/roleName>';
				$triple[] = '"' . $index . '"';
				$triples[] = $triple;

				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://schema.org/creator>';
				$triple[] = $id;
				$triples[] = $triple;

				$triple = array();
				$triple[] = $subject;
				$triple[] = '<http://schema.org/creator>';
				$triple[] = $role_id;
				$triples[] = $triple;

				
			}
			else
			{
				$triple = array();
				$triple[] = $subject;
				$triple[] = '<http://schema.org/creator>';					
				$triple[] = $id;
	
				$triples[] = $triple;			
			}
			
			
		}
		else
		{
			if (0)
			{
				$bnode = '_:creator' . $k;
			}
			else
			{
				$bnode = '<' . $subject_id . '#creator_' . $index . '>';
			}
	
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';					
			$triple[] = '<http://schema.org/Person>';
	
			$triples[] = $triple;
		
			$triple = array();
			$triple[] = $bnode;
			$triple[] = '<http://schema.org/name>';					
			$triple[] = '"' . addcslashes($v, '"') . '"';
			
			$triples[] = $triple;
			
			if ($use_role)
			{
				// Role to hold author position
				$role_id = '<' . $subject_id . '#role/' . $index . '>';
				
				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>';
				$triple[] = '<http://schema.org/Role>';
				$triples[] = $triple;

				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://schema.org/roleName>';
				$triple[] = '"' . $index . '"';
				$triples[] = $triple;

				$triple = array();
				$triple[] = $role_id;
				$triple[] = '<http://schema.org/creator>';
				$triple[] = $bnode;
				$triples[] = $triple;

				$triple = array();
				$triple[] = $subject;
				$triple[] = '<http://schema.org/creator>';
				$triple[] = $role_id;
				$triples[] = $triple;

				
			}
			else
			{
				$triple = array();
				$triple[] = $subject;
				$triple[] = '<http://schema.org/creator>';					
				$triple[] = $bnode;
	
				$triples[] = $triple;			
			}			
		}
	}
	
	
	// literature cited
	$list = get_wikidata_cites($qid);
		
	$n = count($list);
	for ($i = 0; $i < $n; $i++)
	{
		$cited_id = '<http://www.wikidata.org/entity/' . $list[$i]->id . '>';
	
	
		$triple = array();
		$triple[] = $subject;
		$triple[] = '<http://schema.org/citation>';					
		$triple[] = $cited_id ;
		
		$triples[] = $triple;		
		
		// Add a name (which may be a concatentation of multiple languages)
		// so that we can make the citation more infomrative than just an id
		$triple = array();
		$triple[] = $cited_id ;
		$triple[] = '<http://schema.org/alternateName>';					
		$triple[] = '"' . addcslashes($list[$i]->name, '"') . '"';
		
		$triples[] = $triple;		
		
	}


}

$use_role = true;
	


$qid = 'Q58837514';

$qid = 'Q42258926';

//$qid = 'Q58676985';

//$qid = 'Q47164672'; // 毛尾足螨屬3新種(蜱螨亞綱:尾足螨股)

$qid = 'Q29035814';

//$qid = 'Q28944234';

$qid = 'Q49877817';

//$qid = 'Q58838447';

$qid = 'Q58837522';

$qid = 'Q28937258';

$qid = 'Q21191815';

//$qid = 'Q47164672'; // 毛尾足螨屬3新種(蜱螨亞綱:尾足螨股)


$qids = array(

'Q14405740',
'Q21090281',
'Q21090285',
'Q21090286',
'Q21090314',
'Q21090345',
'Q21090394',
'Q21090398',
'Q21127869',
'Q21127884',
'Q21127909',
'Q21127919',
'Q21128254',
'Q21128390',
'Q21128392',
'Q21185247',
'Q21185248',
'Q21185249',
'Q21185346',
'Q21185362',
'Q21185379',
'Q21185393',
'Q21185425',
'Q21185433',
'Q21186024',
'Q21186054',
'Q21187253',
'Q21188366',
'Q21188410',
'Q21188410',
'Q21188436',
'Q21188444',
'Q21189080',
'Q21189466',
'Q21189487',
'Q21191353',
'Q21191373',
'Q21191377',
'Q21191438',
'Q21191466',
'Q21191473',
'Q21191485',
'Q21191485',
'Q21191495',
'Q21191497',
'Q21191499',
'Q21191502',
'Q21191535',
'Q21191535',
'Q21191573',
'Q21191583',
'Q21191739',
'Q21191775',
'Q21191781',
'Q21191804',
'Q21191813',
'Q21191815',
'Q21191816',
'Q21191816',
'Q21191816',
'Q21191855',
'Q21191856',
'Q21191857',
'Q21191866',
'Q21191916',
'Q21191921',
'Q21191941',
'Q21191965',
'Q21191966',
'Q21191967',
'Q21191968',
'Q21191969',
'Q21191989',
'Q21192034',
'Q21192044',
'Q21192044',
'Q21192067',
'Q21192088',
'Q21192105',
'Q21192122',
'Q21192137',
'Q21192144',
'Q21192147',
'Q21192167',
'Q21192186',
'Q21192214',
'Q21558339',
'Q22221738',
'Q22675033',
'Q22675053',
'Q22675402',
'Q22675404',
'Q22676045',
'Q22676075',
'Q22676278',
'Q22676289',
'Q22676479',
'Q22678188',
'Q22678304',
'Q22678307',
'Q22678730',
'Q22680967',
'Q22681047',
'Q24199942',
'Q24200112',
'Q24290328',
'Q27048148',
'Q28818058',
'Q28818103',
'Q28818415',
'Q28821776',
'Q28937258',
'Q28937284',
'Q28937286',
'Q28937291',
'Q33579132',
'Q42253793',
'Q42254284',
);

/*
$qids = array(
'Q33579132'
);
*/

// Zootaxa 2001
$qids=array(

'Q28938177',
'Q28938178',
'Q28938179',
'Q28938180',
'Q28938182',
'Q28938183',
'Q28938184',
'Q28938186',
'Q28938187',
'Q28938188',
'Q28938189',
'Q28938190',
'Q28938191',
'Q28938192',
'Q28938193',
'Q28938194',
'Q28938195',
);

// Zootaxa 2002
$qids=array(
'Q28938199',
'Q28938200',
'Q28938201',
'Q28938202',
'Q28938203',
'Q28938204',
'Q28938205',
'Q28938207',
'Q28938208',
'Q28938209',
'Q28938210',
'Q28938211',
'Q28938212',
'Q28938213',
'Q28938214',
'Q28938215',
'Q28938216',
'Q28938217',
'Q28938219',
'Q28938221',
'Q28938222',
'Q28938223',
'Q28938224',
'Q28938225',
'Q28938226',
'Q28938227',
'Q28938228',
'Q28938229',
'Q28938230',
'Q28938231',
'Q28938232',
'Q28938233',
'Q28938234',
'Q28938235',
'Q28938237',
'Q28938238',
'Q28938240',
'Q28938241',
'Q28938242',
'Q28938243',
'Q28938244',
'Q28938245',
'Q28938246',
'Q28938247',
'Q28938248',
'Q28938249',
'Q28938250',
'Q28938251',
'Q28938252',
'Q28938254',
'Q28938255',
'Q28938256',
'Q28938259',
'Q28938260',
'Q28938261',
'Q28938264',
'Q28938265',
'Q28938266',
'Q28938269',
'Q28938270',
'Q28938271',
'Q28938272',
'Q28938273',
'Q28938275',
'Q28938276',
'Q28938278',
'Q28938279',
'Q28938280',
'Q28938281',
'Q28938282',
'Q28938283',
'Q28938284',
'Q28938285',
'Q28938286',
'Q28938287',
'Q28938288',
'Q28938289',
'Q28938290',
'Q28938291',
'Q28938292',
'Q28938293',
'Q28938294',
'Q28938295',
'Q28938296',
'Q28938297',
'Q28938299',
'Q28938300',
'Q28938301',
'Q28938302',
'Q28938303',
'Q28938305',
'Q28938306',
'Q28938307',
'Q28938308',
'Q28938309',
'Q28938310',
'Q28938311',
'Q28938312',
'Q28938313',
'Q28938314',
'Q28938315',
);

foreach ($qids as $qid)
{

	$triples = array();

	get_wikidata_work_ld($triples, $qid);

	//print_r($triples);

	$nt = '';

	foreach ($triples as $triple)
	{
		$nt .= join(' ', $triple) . ' .' . "\n";
	}

	if (1)
	{
		echo $nt;
		echo "\n";
	}
	else
	{

		$doc = jsonld_from_rdf($nt, array('format' => 'application/nquads'));

		// Frame it-------------------------------------------------------------------------------

		// Identifier is always an array
		$identifier = new stdclass;
		$identifier->{'@id'} = "http://schema.org/identifier";
		$identifier->{'@container'} = "@set";
	
		if ($use_role)
		{
			$creator = new stdclass;
			$creator->{'@id'} = "http://schema.org/creator";
		}
		else
		{
			// Creator is always an array
			$creator = new stdclass;
			$creator->{'@id'} = "http://schema.org/creator";
			$creator->{'@container'} = "@set";
		}
	
		// sameAs is always an array
		$sameAs = new stdclass;
		$sameAs->{'@id'} = "http://schema.org/sameAs";
		$sameAs->{'@container'} = "@set";

		// citation is always an array
		$citation = new stdclass;
		$citation->{'@id'} = "http://schema.org/citation";
		$citation->{'@container'} = "@set";

		// Context to set vocab to schema
		$context = new stdclass;
		$context->{'@vocab'} = "http://schema.org/";

		$context->creator = $creator;
		$context->identifier = $identifier;
		$context->sameAs = $sameAs;

		$frame = (object)array(
			'@context' => $context,

			// Root on article
			'@type' => 'http://schema.org/ScholarlyArticle',

		);	

		$framed = jsonld_frame($doc, $frame);

		// Note JSON_UNESCAPED_UNICODE so that, for example, Chinese characters are not escaped
		echo json_encode($framed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		echo "\n";
	}
}

?>
