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
    $supportedActions = array("ADD", "UPDATE", "DELETE");
    if (!in_array(strtoupper($action), $supportedActions))
        die("Action '$action' is not supported. Supported actions are:".implode(',', $supportedActions)."\n");

    /* Load configurations. */
    if (!$configurations = parse_ini_file("helpers/config/config.ini", $process_sections = true))
        die("Configuration file (helpers/config/config.ini) does not exist.\n");

    if(!array_key_exists($systemName, $configurations))
        die("Configurations do not exist for '$systemName'. Check configuration file (helpers/config/config.ini)\n");

    $targetConfig = $configurations[$systemName];
    if(empty($targetConfig['userid']) || empty($targetConfig['paswoord']) || empty($targetConfig['host'])
        || empty($targetConfig['base']))
        die("Incomplete configurations for '$systemName'. Check configuration file (helpers/config/config.ini)\n");

    /* Input file exists, action supported, configurations provided therefore ready for linking. */
    $log->logInfo("............ ");
    $log->logInfo($systemName . " Image Linking Started");
    $log->logInfo(print_r(array('system' => $systemName, 'input file' => $inputFile, 'action' => $action), true));
    $log->logInfo($targetConfig['host']."/".$targetConfig['base']);
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
    file_put_contents("log/imagestolink.txt", print_r($imagesToLink, true));

    $guzzle = new GuzzleRest($targetConfig, $log);
    $imagesSkipped = array();
    $imagesLinked = array();
    $localeId = 'nl_NL';
    $imageField = 'digitoolUrl';

    foreach($imagesToLink as $imageData){
        if(empty($imageData->recordId) || empty($imageData->pid)){
            $log->logError("Skipping image: ".$imageData->original." Record id or pid is not found.");
            echo "Skipping image: ".$imageData->original." Record id or pid is not found.\n";
            $imagesSkipped [] = $imageData->original;
            continue;
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
                }

                break;

            case "UPDATE":
                /* Update is always based on object_id, which is a numeric identifier. */
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
                        $log->logInfo("\t\tNumber of pids before replacement = " . sizeof($existingPids));
                        $pidsAfterReplacement = array();
                        $pidReplacedCounter = 0;
                        foreach($existingPids as $item){
                            $normalizedPid = $utils->normalizePid($item->$imageField); // remove url from pid
                            if($normalizedPid === $imageData->pid){
				$log->logInfo("\t\tPid (" . $imageData->pid . ") to replace is found. Replacing it with " . $imageData->replacementPid. ".");
                                $normalizedPid = $imageData->replacementPid;
                                $pidReplacedCounter ++;
                            }

                            $pidsAfterReplacement['digitoolUrl'][] =
                                array(
                                    'locale'      => $localeId,
                                    $imageField   =>  $normalizedPid) ;
                        }

                        $log->logInfo("\t\tNumber of pids after replacement = " . sizeof($pidsAfterReplacement['digitoolUrl']));
                        if($pidReplacedCounter === 0){
                            $log->logInfo("\t\tObject does not contain the pid (" . $imageData->pid . ") to be replaced.");
                            echo "Object does not contain pid (" . $imageData->pid . ") to be replaced.\n";
                        }
                        else{
                            $objectId = $imageData->recordId;
                            $toUpdate = array(
                                "remove_attributes" => array($imageField),
                                "attributes" => ($pidsAfterReplacement)
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
        else{
            $imagesSkipped [] = $imageData->original;
            $log->logInfo("\t\t" . $imageData->pid . " skipped");
            echo "skipped\n";
        }

    }

    $log->logInfo("\t\tNumber of images processed successfully: " . sizeof($imagesLinked));
    $log->logInfo("\t\tNumber of images skipped: " . sizeof($imagesSkipped));

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

    file_put_contents("log/linked.txt", print_r($imagesLinked, true));
    $log->logInfo($systemName . " Linking End");

}
elseif(isset($argv[1]) && strtoupper($argv[1]) ===  "HELP"){
    echo "\n-----------------help--------------\n";
    echo "To link images use the following command):\n";
    echo "\t 'php imagelinker.php system_name input_file_name action'\n";
    echo "\t Supported systems: crkc, cag \n";
    echo "\t Supported actions: Add, Delete, Update\n";
    echo "\t Input file name, with extension. Input file should be placed in the data directory of this script.\n";
    echo "\t For example: php imagelinker.php cag cag_20160406231010.csv add\n";
    echo "\n";
}
else
    echo "Incorrect parameters. Use 'help' (php imagelinker.php help) for more information.\n";
