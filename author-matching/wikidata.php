<?php


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
		$opts[CURLOPT_HTTPHEADER] = array(
			"Accept: " . $content_type, 
			"User-agent: Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405" 
		);
	}
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
// Does wikidata have this DOI?
function wikidata_item_from_doi($doi)
{
	$item = '';
	
	$sparql = 'SELECT * WHERE { ?work wdt:P356 "' . strtoupper($doi) . '" }';
	
	$url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($sparql);
	$json = get($url, '', 'application/json');
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if (isset($obj->results->bindings))
		{
			if (count($obj->results->bindings) != 0)	
			{
				$item = $obj->results->bindings[0]->work->value;
				$item = preg_replace('/https?:\/\/www.wikidata.org\/entity\//', '', $item);
			}
		}
	}
	
	return $item;
}

//----------------------------------------------------------------------------------------
// Does wikidata have this ZooBank publication?
function wikidata_item_from_zoobank_publication($zb)
{
	$item = '';
	
	$sparql = 'SELECT * WHERE { ?work wdt:P2007 "' . strtoupper($zb) . '" }';
	
	//echo $sparql . "\n";
	
	$url = 'https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=' . urlencode($sparql);
	
	//echo $url . "\n";
	
	$json = get($url, '', 'application/json');
	
	//echo $json;
		
	if ($json != '')
	{
		$obj = json_decode($json);
		if (isset($obj->results->bindings))
		{
			if (count($obj->results->bindings) != 0)	
			{
				$item = $obj->results->bindings[0]->work->value;
				$item = preg_replace('/https?:\/\/www.wikidata.org\/entity\//', '', $item);
			}
		}
	}
	
	return $item;
}


$id = '4FF544AC-67B8-413A-A544-38A3F299FCF1';


$zoobank_pubs = array(
'f365ecc1-1271-4740-b3fd-867d1c6a9532',
'054d5b51-29ba-4349-8b0a-70ddea289dc9',
'148429b0-c477-4b3c-b24a-ddc55bd2769d',
'cc398ef9-0438-4889-8b86-5d2336ff2883',
'72de7257-4a32-4079-8758-cc16e8fc948d',
'1ea048fe-0ebe-4686-a3f8-8c5555b19d0e',
'66595057-9c2c-4aef-ad29-9e2f52bf99fd',
'adcacc88-6c78-4386-8e33-3f98234ece92',
'ce1c5067-967c-409c-a683-2cbe76653210',
'63cca658-3f99-44a0-b982-0cb4e221eff7',
'636265d7-db86-4fde-987b-a0bb59e78327',
'29098223-1a1c-48e1-b607-c0ba37ba66b3',
'a9e0ab39-5f41-4992-9dd4-796d7b090e0b',
'2540d0cd-28b4-4079-a63d-4a6156615b8e',
'512d9577-292a-4142-af43-a85b259b2e14',
'29203872-d82c-4d2b-8c1b-1f3f756717bc',
'dbbc0b8e-2b97-4c66-bc6f-3fb29c89d2db',
'f865473c-0337-4fd2-915a-0e3dd2299e66',
'da644208-89a6-45cf-b488-7400b237f48d',
'4ed41545-bca4-4f84-b4c6-647f7de849eb',
'ab4dd4d2-ad71-4a91-8206-a8d77914eebd',
'90084a21-1988-455f-ab04-d6bfe3e38d5c',
'90084a21-1988-455f-ab04-d6bfe3e38d5c',
'eaa376b7-6783-4fe4-8b33-bc0a0418b819',
'15337cc0-0f00-4682-97c0-daae0d5cc2be',
'8f4c743c-dad0-4d89-ae53-46e1711e91ce',
'9068f500-995e-4d18-93a4-a79ecb9a4abb',
'a87fe544-bd80-47d1-a1e3-0b8169d8a240',
'3da884b8-57e4-42a5-95cc-0778e809f1a5',
'233a4bbc-3bc6-4094-ba60-9dc210e4640c',
'78d3460c-1c64-4fe4-b4b4-e6621c565e5e',
'36fa265f-22a5-4f5e-b9b0-5d14e23be297',
'3376a343-c4e4-4660-b9d3-07b7113ff93e',
'ffa73bf5-1aa3-4bf0-85b8-1c44f838b040',
'fd7c0c76-4293-4b42-80fa-376d021bbfac',
'c7747a4a-db5e-4b84-8d18-634662bb0a0c',
'67b4d2ec-2c6d-4226-847d-0ba2b2777ae3',
'a3b9317a-69d9-4803-a2df-d07736193677',
'2c6bd020-b54a-4119-9693-3231c9fcefa6',
'702bcae3-5b0a-49ea-8603-d60826241f2c',
'f9b50465-8a19-4c5a-b9e8-3c728af49e67',
'99e9d244-588d-4a68-aef5-0129a8411449',
'3804bb73-6460-434f-91c8-225173ef2fda',
'38f22a3f-811e-4785-bd7f-8a2c50efba5f',
'2cb669b1-1979-4e48-9c4e-41188f126478',
'b6a7723e-a29e-4003-8dac-87d92cedeff3',
'b0475eb4-33fe-4a4e-a23e-3cb0f44aa280',
'744e44d0-5655-48d8-a824-3fbb5ed6b95d',
'da65a87e-e571-429a-bd9b-0f207eb7fc61',
'0471f063-053d-424f-bd82-459a234865ab',
'ded73206-da8f-435e-a717-b96590ca9e56',
'71183707-8c6f-43f8-9ab6-54ea012e676e',
'd7588a4e-d06e-4524-bb49-ac16c3fec849',
'b9a8f09e-0b63-434c-8c33-49f3a784f852',
'41390a60-77d6-457d-bc1c-6841717f6b21',
'8a1bc92f-79a9-4440-835e-037e98020ec2',
'a97034cc-c4e2-4749-afe1-e2eef6f7a88f',
'8c475fab-25e0-44ce-a2fb-c3b83f316d8c',
'3a6234a8-f3a5-4f43-b4fb-89722d121684',
'06dcc96f-d25e-4dff-b87e-3ce8025cc4c0',
'ce362981-651b-4633-a64f-19cd1d128ab5',
'7e07e64f-2ebd-4798-88b8-11ffcb43f6fb',
'2b4327ec-2677-4c28-8d91-ec73f74b6d51',
'252d5f9c-86d3-42ea-834e-e4b516c7dd9d',
'091f6c64-9e8d-45da-9f31-f5127dfa10cb',
'a4b534e9-d5f7-4584-944d-53dab48451dc',
'a4b534e9-d5f7-4584-944d-53dab48451dc',
'a4b534e9-d5f7-4584-944d-53dab48451dc',
'feb87bd7-fb36-4e96-b8b1-9701c0b880d2',
'89b16417-0bee-4939-b571-6b207decb7b4',
'3f8c1798-ec66-45a6-8e39-b2c3e3c38c95',
'58dfd146-00ae-4b6e-be23-df258375273c',
'fdfe14e4-6c7c-4e7d-ba41-5ddbd8f62e2a',
'a99e90d4-41f3-419a-9081-0ae370d09cd5',
'372d3ecf-7ceb-497a-a18e-e841d70d49f3',
'2f7e0a3e-2dfe-4ec1-b706-8867fd210d76',
'b96d0f7d-ccc1-488d-bf14-4111c90ff9cc',
'cec20942-144f-47a2-a672-e1221e0210f7',
'2dc68952-8896-4e71-a4fe-15a3d25d9d47',
'a97034cc-c4e2-4749-afe1-e2eef6f7a88f',
'5345e5e7-ea0a-4090-99da-553d7870b010',
'a8926908-7d99-452d-bfd3-a8970561f317',
'84988008-5b6e-4fbd-8fd7-c67db8e92f4e',
'cdafd834-2eed-49a9-a24b-6cf72c6f1ff4',
'5cee32a8-1c9a-492f-bcf9-a9767d1dcd9f',
'e12da5db-2b56-428f-9b9d-414a3263359e',
'2fab4423-3174-4ea0-a5ca-0e9606a71ad6',
'44c7888b-dde0-4471-b8f7-084d0c207d3b',
'569812e9-82b5-4bcf-84e0-d4c43aee8273',
'f598074f-9804-4e94-8039-c048c6ed5307',
'7ffa7dc5-e246-487d-a643-7b412dc4015b',
'f456c9b2-3a15-4520-a0a2-6208722b52b0',
'7ffa7dc5-e246-487d-a643-7b412dc4015b',
'0a9acad9-498f-48f0-a9d8-7f05134641ba',
'2c04598a-a971-4423-9e43-f8fa592ae709',
'2c04598a-a971-4423-9e43-f8fa592ae709',
'd042031a-bf67-42b4-a5de-dd48fdf36fca',
'87b9757a-986d-4ccc-8276-146a617fc905',
'3c3956f9-1565-4c0f-b3e7-9fecd0de6cef',
'805abd44-ddda-4aa3-9923-022b2e908525',
'93c85819-f400-42b0-84a0-21e5d0cfdfdc',
'5c730675-4a39-43d7-9363-d008215cb56b',
'f361ef98-af30-4073-aa8f-ecd0254efc22',
'799eb812-36c3-4326-9c02-0af077776471',
'f133f221-1574-4df4-b178-4797037920b6',
'39ba47f8-dab0-44c6-a603-5656007cc629',
'b7b2e7b5-6796-4972-a342-349dafb62541',
'7760e22b-e679-4821-9993-e90be9cd1fb2',
'0e70926f-abe7-42b0-87b8-15ca1a15d0a5',
'fb76ac3b-d8f6-4d4e-b738-c43da62199b1',
'93905706-b88d-40d0-92d0-1c38d2124db7',
'e50b29dd-baef-4404-9901-5a45da1b6837',
'84da1384-4a02-4131-a6a0-ff5fe73aa920',
'b84880c8-a25d-4468-bbfe-eb995482fad8',
'3b4d7966-762d-4d7d-accc-1a31f51fbd73',
'596fd364-e33a-4269-868e-ca6275e95d38',
'14400153-ac4e-4385-b257-1468a2fd81be',
'30728fef-8d76-4c05-8f30-e3249532b64d',
'9851aa0d-0c52-4b9a-8d9b-0f2e31228ab9',
'6c2358f0-cee9-4473-8abe-15dc6770fcda',
'4e3d030d-ea64-4822-8aa1-1427ebedc8db',
);

foreach ($zoobank_pubs as $id)
{
	$item = wikidata_item_from_zoobank_publication($id);
	echo $id . ' ' . $item . "\n";
}


?>
