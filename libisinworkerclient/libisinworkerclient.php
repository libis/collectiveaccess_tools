<?php

require_once("helpers/integrationQueue.php");

echo "Starting ...\n";
$queuing_server = new integrationQueue();
$queuing_server->loadLibisInConfigurations();

$mappingFilePath = "helpers/mappings/example_mappingrules.csv";
$dataFilePath = "helpers/data/example_input_data.json";

if (!file_exists($mappingFilePath))
	die ("Mapping rules file '$mappingFilePath' does not exists.\n");

if (!file_exists($dataFilePath))
	die ("Input data file '$dataFilePath' does not exists.\n");

$mapping_rules =  file_get_contents($mappingFilePath);
$data =  file_get_contents($dataFilePath);

$set_info[] = array(
	'set_name'		=> 'myset',
	'set_id'    	=> 100,
	'record_type'	=> 'objects',
	'bundle'    	=> null,
	'mapping'   	=> $mapping_rules,
	'data'			=> $data,
	'collective_access_call' => false
);	
$msg_body = array(
	'set_info' 			=> $set_info,
	'user_info' 		=> array('name' => 'Naeem', 'email' => 'Naeem.Muhammad@libis.kuleuven.be')
);

$queuing_server->queuingRequest($msg_body);

echo "Finished.\n";
