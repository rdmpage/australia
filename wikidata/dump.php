<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once (dirname(__FILE__) . '/adodb5/adodb.inc.php');


// dump data

//----------------------------------------------------------------------------------------
$db = NewADOConnection('mysqli');
$db->Connect("localhost", 
	'root' , '' , 'afd');

// Ensure fields are (only) indexed by column name
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

$db->EXECUTE("set names 'utf8'"); 


$sql = 'SELECT * FROM afd_author_matching';

$result = $db->Execute($sql);
if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $sql);

$keys = array('author_uri', 'name', 'orcid', 'wikidata', 'wikispecies', 'researchgate', 'zoobank');

$keys_prefix = array
	(
	'author_uri' => 'https://ozymandias-demo.herokuapp.com/?uri=https://biodiversity.org.au/afd/publication/%23creator/', 
	'name' => '', 
	'orcid' => 'https://orcid.org/',
	'wikidata' => 'https://www.wikidata.org/wiki/', 
	'wikispecies' => 'https://species.wikimedia.org/wiki/', 
	'researchgate' => 'http://www.researchgate.net/profile/', 
	'zoobank' => 'http://zoobank.org/Authors/'
	);


$mode = 0;

if ($mode == 0)
{
	echo join(',', $keys) . "\n";
}

if ($mode == 1)
{
	echo '<html>
	<head>
	<style>
		td { border-bottom:1px solid orange; }
	</style>
	</head>
	<body>';
	echo '<table>' . "\n";
	echo '<tr><th>';
	echo join('</th><th>', $keys);
	echo '</th></tr>' . "\n";
}


while (!$result->EOF) 
{
	$obj = new stdclass;

	foreach ($keys as $k)
	{
		if ($result->fields[$k] != '')
		{
			$obj->{$k} = $result->fields[$k];
		}
		else
		{
			$obj->{$k} = '';
		}
	}
	
	//print_r($obj);
	
	$row = array();
	
	if ($mode == 0)
	{
		foreach ($keys as $k)
		{
			$row[] = $obj->{$k};
		}
		
		echo join(',', $row) . "\n";
	}
	else
	{
		foreach ($keys as $k)
		{
			if ($keys_prefix[$k] == '')
			{
				$row[] = $obj->{$k};
			}
			else
			{
				$row[] = '<a href="' . $keys_prefix[$k] . $obj->{$k} . '" target="_new">' . $obj->{$k} . '</a>';
			}
		}
		
		echo '<tr><td>';
		echo join('</td><td>', $row);
		echo '</td></tr>' . "\n";
	
	
	
	}
	



	$result->MoveNext();
}

if ($mode == 1)
{
	echo '</table>' . "\n";
	echo '</body></html>';
}

?>
