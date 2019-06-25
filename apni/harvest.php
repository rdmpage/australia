<?php

// Havest ALA plant data

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/couchsimple.php');

//----------------------------------------------------------------------------------------
// http://stackoverflow.com/a/5996888/9684
function translate_quoted($string) {
  $search  = array("\\t", "\\n", "\\r");
  $replace = array( "\t",  "\n",  "\r");
  return str_replace($search, $replace, $string);
}

$count = 0;

$stack = array();


//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HTTPHEADER =>  array(
	  	"Accept: application/json", 
	  	"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" )
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}



//----------------------------------------------------------------------------------------
// stack is where we start from (e.g., list of ids or subtree root)
// if force then replacer any existing 
// if follow then drill down by getting children, otherwise just add id
function go($stack, $force = false, $follow = true)
{
	global $couch;
	global $count;
	
	while (count($stack) > 0)
	{
		$id = array_pop($stack);
		
		// ensure https
		
		$id = preg_replace('/^http:/', 'https:', $id);
		
		//print_r($stack);
		echo "stack count=" . count($stack) . "\n";
		
	
		$exists = $couch->exists($id);
		//$exists = false;
	
		$go = true;
		if ($exists && !$force)
		{
			echo "Have already\n";
			$go = false;
		}
	
		if ($go)
		{
			$count++;
			
			$id_type = 'nsl';
						
			if (preg_match('/https:\/\/bie.ala.org.au\/species\/(?<id>.*)/', $id, $m))
			{
				$url = 'https://bie.ala.org.au/ws/species/' . $m['id'] . '.json';
				$id_type = 'ala'; // may want to get children to build out tree			
			}
			else
			{
				$url = $id;
				$id_type = 'nsl'; // may want to get records mentioned in links
			}
			
			$json = get($url);			
			if ($json)
			{
				$obj = json_decode($json);
				
				//print_r($obj);
				//$json = json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				//file_put_contents($filename, $json);
							
				$doc = new stdclass;
				$doc->_id = $id;
				
				$doc->{'message-type'} = 'unknown';
				
				// instances
				if (preg_match('/https?:\/\/id.biodiversity.org.au\/instance\/apni\//', $id))
				{
					$doc->{'message-type'} = 'instance';
					
					//print_r($obj);
					
					// for now don't do "reference" as sometimes the file gets very big
					$keys = array('name', 'cites', 'citedBy');
					
					foreach ($keys as $k)
					{
						if (isset($obj->instance->{$k}))
						{
							if (isset($obj->instance->{$k}->_links))
							{
								if (isset($obj->instance->{$k}->_links->permalink))
								{			
									$new_id = $obj->instance->{$k}->_links->permalink->link;
									if (!$couch->exists($new_id))
									{				
										$stack[] = $new_id;
									}
								}		
							}									
						}					
					}
				}

				// names
				if (preg_match('/https?:\/\/id.biodiversity.org.au\/name\/apni\//', $id))
				{
					$doc->{'message-type'} = 'name';
					
					if (isset($obj->name->instances))
					{
						foreach ($obj->name->instances as $instance)
						{
							if (isset($instance->_links))
							{
								if (isset($instance->_links->permalink))
								{		
									$new_id = $instance->_links->permalink->link;
									if (!$couch->exists($new_id))
									{				
										$stack[] = $new_id;
									}
								}		
							}	
						}								
					}					
					
					
					
				}

				// reference
				if (preg_match('/https?:\/\/id.biodiversity.org.au\/reference\/apni\//', $id))
				{
					$doc->{'message-type'} = 'reference';
				}
				
				// taxon
				if (preg_match('/https:\/\/bie.ala.org.au\/species\/(?<id>.*)/', $id, $m))
				{
					$doc->{'message-type'} = 'taxon';	
					
					// name
					if (isset($obj->taxonConcept))
					{
						$new_id = $obj->taxonConcept->scientificNameID;
						if (!$couch->exists($new_id))
						{				
							$stack[] = $new_id;
						}
					}
					
					// synonyms
					if (isset($obj->synonyms))
					{
						foreach ($obj->synonyms as $synonym)
						{
							$new_id = $synonym->nameGuid;
							if (!$couch->exists($new_id))
							{				
								$stack[] = $new_id;
							}
						}					
					}
					
						
				}								
				
				$doc->message = $obj;
			
				//print_r($doc);	
				
				if (!$exists)
				{
					$couch->add_update_or_delete_document($doc, $doc->_id, 'add');	
				}
				else
				{
					if ($force)
					{
						$couch->add_update_or_delete_document($doc, $doc->_id, 'update');
					}
				}
				
			
				// parent(s)
			
				// children
				if ($follow && $id_type == 'ala')
				{
					if (preg_match('/https:\/\/bie.ala.org.au\/species\/(?<id>.*)/', $id, $m))
					{
						$url = 'https://bie.ala.org.au/ws/childConcepts/' . $m['id'];
						$json = get($url);			
						if ($json)
						{
							$obj = json_decode($json);
				
							foreach ($obj as $child)
							{
								if (preg_match('/node\/apni/', $child->guid))
								{							
									$stack[] = 'https://bie.ala.org.au/species/' . $child->guid;
								}
							}
				
						}
					}
				}
				
			}
			
			// Give server a break every 10 items
			if (($count % 10) == 0)
			{
				$rand = rand(1000000, 3000000);
				echo "\n...sleeping for " . round(($rand / 1000000),2) . ' seconds' . "\n\n";
				usleep($rand);
			}
			
			
		}
	}
	
}

//----------------------------------------------------------------------------------------

if (0)
{

	$url = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2920348';

	$stack = array();
	$stack[] = $url;

	$stack[] = 'http://id.biodiversity.org.au/instance/apni/857883';
	$stack[] = 'https://id.biodiversity.org.au/name/apni/81525';

	// Banksia scabrella
	$stack = array();
	$stack[] = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2910413';

	$stack[] = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2920348';


	$stack[] = 'https://id.biodiversity.org.au/name/apni/98247';

	// Pterostylis (orcid)
	$stack = array();
	$stack[] = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2901128';

	$stack = array();
	$stack[] = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/name/apni/101168';

	// add everything rooted at a subtree
	go($stack, true, true);
}

if (1)
{
	// Add from file
	
	
	$filename = 'APNI-names-2019-06-14-1229.csv';

	$headings = array();

	$row_count = 0;
	
	$skip = false;
	$skip = true;
	

	$file = @fopen($filename, "r") or die("couldn't open $filename");
		
	$file_handle = fopen($filename, "r");
	while (!feof($file_handle)) 
	{
		$row = fgetcsv(
			$file_handle, 
			0, 
			translate_quoted(','),
			translate_quoted('"') 
			);
		
		$go = is_array($row);
	
		if ($go)
		{
			if ($row_count == 0)
			{
				$headings = $row;		
			}
			else
			{
				$obj = new stdclass;
		
				foreach ($row as $k => $v)
				{
					if ($v != '')
					{
						$obj->{$headings[$k]} = $v;
					}
				}
		
				//print_r($obj);	
				
				//echo $obj->scientificNameID . "\n";
				
				if ($skip)
				{
					$start_id = 'https://id.biodiversity.org.au/name/apni/73785';
					$start_id = 'https://id.biodiversity.org.au/name/apni/91724';
					$start_id = 'https://id.biodiversity.org.au/name/apni/612295';
					$start_id = 'https://id.biodiversity.org.au/name/apni/92943';
					$start_id = 'https://id.biodiversity.org.au/name/apni/107831';
					$start_id = 'https://id.biodiversity.org.au/name/apni/76255';
					
					if ($obj->scientificNameID == $start_id)
					{
						$skip = false;						
					}
				}
				
				
				if (!$skip)
				{		
					//echo "go\n";		
					$stack = array($obj->scientificNameID);
					go($stack, true, true);
				}
				
			}
		}	
		$row_count++;
	}	

}
/*
if (0)
{
	// add from file
	$filename = 'MOLLUSCA.csv';
	$filename = 'ARTHROPODA.csv';
	$filename = 'species.csv';
	$file_handle = fopen($filename, "r");

	$row_count = 0;
	$count = 1;

	while (!feof($file_handle)) 
	{
		$line = trim(fgets($file_handle));
	
		if ($line != '')
		{	
			$parts = explode(",", $line);
		
			if ($row_count++ > 1)
			{		
				$stack = array($parts[0]);
				go($stack, false, false);
			}
			
			
			
		}
	}
}
*/


?>
