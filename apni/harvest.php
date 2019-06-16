<?php

// Havest ALA plant data

error_reporting(E_ALL);

// require_once('couchsimple.php');

$count = 0;


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
	
		echo "stack count=" . count($stack) . "\n";
	
		//$exists = $couch->exists($id);
		$exists = false;
	
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
				
				print_r($obj);
				//$json = json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				//file_put_contents($filename, $json);
			
				/*
				$doc = new stdclass;
				$doc->_id = $id;
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
				if ($follow)
				{
					$url = 'https://bie.ala.org.au/ws/childConcepts/' . $id;
					$json = get($url);			
					if ($json)
					{
						$obj = json_decode($json);
				
						foreach ($obj as $child)
						{
							$stack[] = $child->guid;
						}
				
					}
				}
				*/
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


$url = 'https://bie.ala.org.au/species/http://id.biodiversity.org.au/node/apni/2920348';

$stack = array();
$stack[] = $url;

$stack[] = 'http://id.biodiversity.org.au/instance/apni/857883';
$stack[] = 'https://id.biodiversity.org.au/name/apni/81525';

// add everything rooted at a subtree
go($stack, true, true);

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
