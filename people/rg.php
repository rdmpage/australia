<?php

// Harvest ResearchGate profile image and metadata

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/simplehtmldom_1_5/simple_html_dom.php');


//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_HTTPHEADER =>  array(
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


$id = 'Julia_Caceres-Chamizo';
$id = 'Roger_De_Keyzer';
$id = 'Ingi_Agnarsson';
$id = 'Jeff_Webb2'; // no image, seems to have no linked data
$id = 'Michael_Batley'; 

$url = 'https://www.researchgate.net/profile/' . $id;

$html = get($url);

echo $html;

$dom = str_get_html($html);

// Image from meta tag
$metas = $dom->find('meta[property=og:image]');
foreach ($metas as $meta)
{
	echo $meta->content . "\n";
}

// JSON-LD
$scripts = $dom->find('div[class=profile-detail] script[type=application/ld+json]');
foreach ($scripts as $script)
{
	$json = $script->innertext;
	
	$obj = json_decode($json);
	
	if ($obj->{'@type'} == 'Person')
	{
		print_r($obj);
		
		echo json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
}


?>

