<?php
/**
 * User: NaeemM
 * Date: 7/03/2016
 */

require_once('helpers/GuzzleRest.php');
require_once('helpers/ImageData.php');
require_once('helpers/Utils.php');
require_once('helpers/KLogger.php');

$log = new Klogger("log/",KLogger::DEBUG);

if(sizeof($argv) >= 4){
    $systemName = $argv[1];
    $inputFile = $argv[2];
    $action = $argv[3];
    echo "<<Information provided>>\n";
    echo "Systems: $systemName\n";
    echo "Input file: $inputFile\n";
    echo "Action: $action\n";

    $utils = new Utils();

    /* Check if input file exists. */
    $inputFilePath = "data/".$inputFile;
    if (!file_exists($inputFilePath))
        die("Input file '$inputFilePath' does not exists. Check data directory of this script.\n");

    /* Check if it is a valid action. */
    $supportedActions = array("ADD", "UPDATE", "DELETE", "DUPLICATEDELETE");
    if (!in_array(strtoupper($action), $supportedActions))
        die("Action '$action' is not supported. Supported actions are:".implode(',', $supportedActions)."\n");

    /* Load configurations. */
    if (!$configurations = parse_ini_file("helpers/config/config.ini", $process_sections = true))
        die("Configuration file (helpers/config/config.ini) does not exist.\n");

    if(!array_key_exists($systemName, $configurations))
        die("Configurations do not exist for '$systemName'. Check configuration file (helpers/config/config.ini)\n");

    $targetConfig = $configurations[$systemName];
    if(empty($targetConfig['userid']) || empty($targetConfig['paswoord']) || empty($targetConfig['host'])
        || empty($targetConfig['base'] ))
        die("Incomplete configurations for '$systemName'. Check configuration file (helpers/config/config.ini)\n");

    /* Input file exists, action supported, configurations provided therefore ready for linking. */
    $log->logInfo("............ ");
    $log->logInfo($systemName . " Image Linking Started");
    $log->logInfo(print_r(array('system' => $systemName, 'input file' => $inputFile, 'action' => $action), true));
    $log->logInfo("http://".$targetConfig['host']."/".$targetConfig['base']);
    echo "\r\nhttp://".$targetConfig['host']."/".$targetConfig['base']."\r\n";

    $imagesToLink = $utils->prepareImagesData($inputFilePath, $action, $log);

    $skippedFile = "data/skipped/" . $systemName . "_". $action . "_skipped.csv";
    if(file_exists($skippedFile) && filesize($skippedFile) > 0){
        $skippedImagesToLink = $utils->prepareImagesData($skippedFile, $action, $log);
        $imagesToLink = array_merge($imagesToLink, $skippedImagesToLink);

        $log->logInfo("\t\tReading previously skipped images from file " . $skippedFile . ".");
        echo "Reading previously skipped images from file.\n";
    }
    else{
        $log->logInfo("\t\tNo previously skipped images available.");
        echo "No previously skipped images available.\n";
    }

    $imagesToLink = $utils->removeDuplicateImageData($imagesToLink);

    if(!sizeof($imagesToLink)){
        $log->logError("No image to be processed.");
        die("No image to be processed. \n");
    }

    echo "Number of images to be processed: ".sizeof($imagesToLink)."\n";
    $log->logInfo("Number of images to be processed: ".sizeof($imagesToLink));

    $guzzle = new GuzzleRest($targetConfig, $log);
    $imagesSkipped = array();
    $imagesLinked = array();
    $localeId = 'nl_NL';
    $imageField = 'digitoolUrl';

    $newObject = false;
    $newObjectsFile = "log/" . $systemName . "_". $action . "_new_records.csv";
    $newObjectList = array();
    if (file_exists($newObjectsFile)){
	$log->logInfo("\t\tDeleting existing New Records file.");
        unlink($newObjectsFile);
    }

    foreach($imagesToLink as $imageData){

        if(strtoupper($action) === "DUPLICATEDELETE"){
            if(empty($imageData->recordId)){
                $log->logError("Skipping image: ".$imageData->original." Record id not found.");
                echo "Skipping image: ".$imageData->original." Record id.\n";
                $imagesSkipped [] = $imageData->original;
                continue;
            }
        }else{
            if(empty($imageData->recordId) || empty($imageData->pid)){
                $log->logError("Skipping image: ".$imageData->original." Record id or pid is not found.");
                echo "Skipping image: ".$imageData->original." Record id or pid is not found.\n";
                $imagesSkipped [] = $imageData->original;
                continue;
            }
        }

        $log->logInfo("Processing image (".$imageData->pid.") for record: ".$imageData->recordId.")");
        echo "\n<Processing image (".$imageData->pid.") for record: ".$imageData->recordId.">\n";
        switch(strtoupper($action)){
            case "ADD":
                /* Trim empty space and zeroes at the start of the identifier. */
                $imageData->recordId = trim($imageData->recordId);
                $imageData->recordId = ltrim($imageData->recordId, '0');
                $query = "ca_objects.idno:'".$imageData->recordId."'";

                $response = $guzzle->findObject($query, 'ca_objects');
                $validResponse = $utils->isFindResponseValid($response);
                if($validResponse['isValid']){
                    $log->logInfo("\t\tObject found (for record id ".$imageData->recordId." ): object_id = " . $validResponse['object_id']);
                    echo "Object found (for record id ".$imageData->recordId." ): " . $validResponse['object_id'].".\n";
                    $objectId = $validResponse['object_id'];
                    $toUpdate = array(
                        "attributes" => (array($imageField => array(
                            array(
                            'locale'      => $localeId,
                            $imageField   =>  $imageData->pid))))
                    );
                }
                else{
                    $log->logError("\t\tObject not found for (".$imageData->original.")");
                    echo "Object not found for (".$imageData->original.").\n";

                    /*Object creation*/
                    /* object type id  or object collection relationship id is not given, therefore skip it. */
                    if(empty($targetConfig['object_type_id'])){
                        echo "Object type id not provided. Skip object creation.\n";
                        $log->logInfo("\t\tObject type id not provided. Skip object creation.");
                        continue;
                    }

                    /*Basic object information and digitool attribute information to add.*/
                    $data = array(
                        "intrinsic_fields" => array(
                            "idno" => $imageData->recordId,
                            "type_id" => $targetConfig['object_type_id']
                        ),
                        "attributes" => (array($imageField => array(
                            array(
                                'locale'      => $localeId,
                                $imageField   =>  $imageData->pid))))
                    );

                    if (strpos($systemName, 'crkc') !== false) {
                        echo "It is CRKC, retrieve collection information.\n";
                        $log->logInfo("\t\tIt is CRKC, retrieve collection information");

                        if(empty($targetConfig['object_collection_relationship_id'])){
                            echo "Object collection relationship id not provided. Skip object creation.\n";
                            $log->logInfo("\t\tObject collection relationship id not provided. Skip object creation");
                            continue;
                        }
                        /*It is a CRKC system and object collection relationship id is provide. Retrieve Collection idno
                        from the object idno and check if object exists. Retrieve collection id. */
                        $collectionName = current(explode('_', $imageData->original));
                        $collQuery = "ca_collections.idno:'".$collectionName."'";
                        $collResponse = $guzzle->findObject($collQuery, 'ca_collections');
                        $collection = $utils->isCollectionFindResponseValid($collResponse);
                        if (empty($collection)){
                            echo "Collection does not exist. Skip object creation.\n";
                            $log->logInfo("\t\tCollection (" . $collectionName . ") does not exist. Skip object creation");
                            continue;
                        }
                        /*Required information about collection is available, add it to object creation iformation.*/
                        $data["related"] = array(
                            "ca_collections" => array(
                                array(
                                    "type_id" => $targetConfig['object_collection_relationship_id'],
                                    "collection_id" => $collection->collection_id)
                            )
                        );
                        echo "Object will be added to collection:".$collection->collection_id."\n";
                        /*echo "Object will be added to collection:".$collection->idno."\n";*/
                        $log->logInfo("\t\tObject will be added to collection (collection_id): " . $collection->collection_id);

                    }
                    /*Create Object*/
                    $response = $guzzle->createObject($data, 'ca_objects');
                    if(!empty($response->errors) && sizeof($response->errors) > 0 || empty($response->object_id)){
                        echo "Error in creating object. Skip object creation.\n";
                        $log->logError("\t\tError in creating object. Skip object creation.");
                        continue;
                    }

                    if(!empty($response->object_id))
                    {
                        echo "Object created. New object id:".$response->object_id.".\n";
                        $log->logInfo("\t\tObject created. New object id: " . $response->object_id);
                        $newObjectList[]  = $response->object_id;
                        $newObject = true;
                    }
                    else{
                        echo "Object creation failed.\n";
                        $log->logError("\t\tObject creation failed.");
                    }

                }

                break;

            case "UPDATE":
                /*
                    Update operation is now based on IDNO (previously it was based on object_id). Therefore we use IDNO
                    to get the object_id.
                    The update operation now removes all existing PID/IEs and adds the one given in the input file. Multiple PID/IEs are added at once.
                    In case there exists no PID/IEs the pid, the given PID will be added to the object.

                 */

                // Convert the IDNO given in the input file into correct format
                $object_idno = $utils->normalizeIdentifier($imageData->recordId);
                $query = "ca_objects.idno:'".$object_idno."'";
                $response = $guzzle->findObject($query, 'ca_objects');  // Retrieve object detail with IDNO
                $validResponse = $utils->isFindResponseValid($response);
                if($validResponse['isValid']){  //  Check if object is valid
                    $log->logInfo("\t\tObject found (for record id ".$object_idno." ): object_id = " . $validResponse['object_id']);
                    echo "Object found (for record id ".$object_idno." ): " . $validResponse['object_id'].".\n";
                    $pidToReplaceWith = array();

                    $pidList = explode(' ', $imageData->pid);
                    foreach($pidList as $pid){
                        $log->logInfo("Processing image ".$pid.":");
                        $pidToReplaceWith['digitoolUrl'][] =
                            array(
                                'locale'      => $localeId,
                                $imageField   =>  $pid);
                    }
                    
                    $objectId = $validResponse['object_id'];
                    $toUpdate = array(
                        "remove_attributes" => array($imageField),
                        "attributes" => ($pidToReplaceWith)
                    );

                }else{
                    $log->logInfo("\t\tInvalid identifier (" . $imageData->recordId . ") for action (" . $action.")");
                    echo "Object not found for (".$imageData->original.").\n";
                    continue;
                }

            break;

            case "DELETE":
                /* Deletion is always based on object_id, which is a numeric identifier. */
                if(!is_numeric($imageData->recordId)){
                    $log->logInfo("\t\tInvalid identifier (" . $imageData->recordId . ") for action (" . $action.")");
                    continue;
                }
                $log->logInfo("\t\tValid identifier (" . $imageData->recordId . ") for action (" . $action.")");
                $response = $guzzle->getFullObject($imageData->recordId, 'ca_objects');
                $validResponse = $utils->isGetItemResponseValid($response);
                if($validResponse['isValid']){
                    $log->logInfo("\t\tObject found: object_id = " . $imageData->recordId);
                    echo "Object found: " . $imageData->recordId . ".\n";
                    $existingPids = array();

                    if(!empty($validResponse['object_data']->attributes->digitoolUrl)){
                        $existingPids = $validResponse['object_data']->attributes->digitoolUrl;
                        $log->logInfo("\t\tNumber of pids before deletion = " . sizeof($existingPids));
                        $pidsToRetain = array();
                        foreach($existingPids as $item){
                            $normalizedPid = $utils->normalizePid($item->$imageField);
                            if($normalizedPid === $imageData->pid){
                                $log->logInfo("\t\tPid (" . $imageData->pid . ") to delete is found. Deleting it.");
                                continue;
                            }

                            $pidsToRetain['digitoolUrl'][] =
                                array(
                                    'locale'      => $localeId,
                                    $imageField   =>  $item->$imageField) ;
                        }

                        if(!empty($pidsToRetain['digitoolUrl']))
                            $sizeOfPidsToRetain = sizeof($pidsToRetain['digitoolUrl']);
                        else    // objects has no more pid
                            $sizeOfPidsToRetain = 0;

                        $log->logInfo("\t\tNumber of pids after deletion = " . $sizeOfPidsToRetain);
                        if($sizeOfPidsToRetain === sizeof($existingPids)){
                            $log->logInfo("\t\tObject does not contain the pid (" . $imageData->pid . ") to be deleted");
                            echo "Object does not contain pid (" . $imageData->pid . ") to be deleted.\n";
                        }
                        else{
                            $objectId = $imageData->recordId;
                            $toUpdate = array(
                                "remove_attributes" => array($imageField),
                                "attributes" => ($pidsToRetain)
                            );
                        }
                    }
                    else{
                        $log->logInfo("\t\tObject has no pids.");
                    }

                }
                else{
                    $log->logError("\t\tObject not found for (".$imageData->original.")");
                    echo "Object not found for (".$imageData->original.").\n";
                }
                break;

            case "DUPLICATEDELETE":
                /*
                Duplicate deletion is always based on object_id, which is a numeric identifier.
                Input file for duplicatedelete should only contain object_id. Pids for each object_id are fetched and
                processed to find duplicate pids. If duplicate pids exists, only the first pid is retained, rest of
                the similar pids are discarded.
                */
                if(!is_numeric($imageData->recordId)){
                    $log->logInfo("\t\tInvalid identifier (" . $imageData->recordId . ") for action (" . $action.")");
                    continue;
                }
                $log->logInfo("\t\tValid identifier (" . $imageData->recordId . ") for action (" . $action.")");
                $response = $guzzle->getFullObject($imageData->recordId, 'ca_objects');
                $validResponse = $utils->isGetItemResponseValid($response);
                if($validResponse['isValid']){
                    $log->logInfo("\t\tObject found: object_id = " . $imageData->recordId);
                    echo "Object found: " . $imageData->recordId . ".\n";
                    $existingPids = array();

                    if(!empty($validResponse['object_data']->attributes->digitoolUrl)){
                        $existingPids = $validResponse['object_data']->attributes->digitoolUrl;
                        $log->logInfo("\t\tNumber of pids before duplicate removal = " . sizeof($existingPids));
                        $pidsToRetain = array();
                        $uniquePids = array();

                        foreach($existingPids as $item){
                            $normalizedPid = $utils->normalizePid($item->$imageField);
                            if(!empty($uniquePids) && in_array($normalizedPid, $uniquePids)){
                                echo $normalizedPid. " is duplicate. Deleting it.\r\n";
                                $log->logInfo("\t\tPid (" .$normalizedPid . ") is duplicate. Deleting it.");
                                continue;
                            }
                            $log->logInfo("\t\tPid (" .$normalizedPid . ") is not duplicate.");

                            $uniquePids[] = $normalizedPid;
                            $pidsToRetain['digitoolUrl'][] =
                                array(
                                    'locale'      => $localeId,
                                    $imageField   =>  $item->$imageField) ;
                        }

                        if(!empty($pidsToRetain['digitoolUrl']))
                            $sizeOfPidsToRetain = sizeof($pidsToRetain['digitoolUrl']);
                        else    // objects has no more pid
                            $sizeOfPidsToRetain = 0;

                        $log->logInfo("\t\tNumber of pids after duplicate removal = " . $sizeOfPidsToRetain);
                        if($sizeOfPidsToRetain === sizeof($existingPids)){
                            $log->logInfo("\t\tObject does not contain duplicate images.");
                            echo "Object does not contain duplicate images.\n";
                        }
                        else{
                            $objectId = $imageData->recordId;
                            $toUpdate = array(
                                "remove_attributes" => array($imageField),
                                "attributes" => ($pidsToRetain)
                            );
                        }
                    }
                    else{
                        $log->logInfo("\t\tObject has no pids.");
                    }

                }
                else{
                    $log->logError("\t\tObject not found for (".$imageData->original.")");
                    echo "Object not found for (".$imageData->original.").\n";
                }
                break;
        }

        if(!empty($toUpdate) && !empty($objectId)){
            $updateResponse = $guzzle->updateObject($toUpdate, $objectId, 'ca_objects');
            $isSuccessfull  = $utils->isActionSuccessfull($updateResponse);
            if($isSuccessfull){
                $imagesLinked [] = $imageData->pid;
                $log->logInfo("\t\t" . strtoupper($action). " successful");
                echo $action." action done for image " . $imageData->pid . "\n";
            }
            else{
                $imagesSkipped [] = $imageData->original;
                $log->logInfo("\t\t" . $imageData->pid . " skipped");
                $log->logInfo("\t\t".print_r($updateResponse,true));
                echo $imageData->pid." skipped\n";
            }

            unset($toUpdate);
            unset($objectId);
        }
        /*else{*/
        elseif($newObject === false){
            $imagesSkipped [] = $imageData->original;
            $log->logInfo("\t\t" . $imageData->pid . " skipped");
            echo "skipped\n";
        }
    }

    /*$log->logInfo("\t\tNumber of images processed successfully: " . sizeof($imagesLinked));*/
    $log->logInfo("\t\tNumber of images processed successfully for existing records: " . sizeof($imagesLinked));
    $log->logInfo("\t\tNumber of images skipped: " . sizeof($imagesSkipped));

    /*echo "\r\nNumber of images processed successfully: " . sizeof($imagesLinked);*/
    echo "\r\nNumber of images processed successfully for existing records: " . sizeof($imagesLinked);
    echo "\r\nNumber of images skipped: " . sizeof($imagesSkipped)."\r\n";
    echo "\r\n";

    /*
        Store skipped file in the skipped file for each supported system. Entries in the skipped file are included in
        the next image linking execution.
        Skipped file is stored in directory data/skipped.
        Sipped file name: system_action_skipped.csv
        Example:
            crkc_add_skipped.csv
            crkc_update_skipped.csv
            cag_add_skipped.csv
            cag_delete_skipped.csv
    */
    file_put_contents($skippedFile, implode("\n",$imagesSkipped));

    if(sizeof($newObjectList) > 0){
/*        $newObjectList = array_unshift($newObjectList, "object_id");
        var_dump($newObjectList);*/
        file_put_contents($newObjectsFile, implode("\n",$newObjectList));
        echo "\r\nNumber of objects created: " . sizeof($newObjectList)."\r\n";
        $log->logInfo("\t\tNumber of objects created: " . sizeof($newObjectList). ". See file: " . $newObjectsFile);
    }

    file_put_contents("log/linked.txt", print_r($imagesLinked, true));
    $log->logInfo($systemName . " Linking End");

}
elseif(isset($argv[1]) && strtoupper($argv[1]) ===  "HELP"){
    echo "\n-----------------help--------------\n";
    echo "To link images use the following command):\n";
    echo "\t 'php imagelinker.php system_name input_file_name action'\n";
    echo "\t Supported systems: crkc, cag \n";
    echo "\t Supported actions: Add, Delete, Update, Duplicatedelete\n";
    echo "\t Input file name, with extension. Input file should be placed in the data directory of this script.\n";
    echo "\t For example: php imagelinker.php cag cag_20160406231010.csv add\n";
    echo "\n";
}
else
    echo "Incorrect parameters. Use 'help' (php imagelinker.php help) for more information.\n";
