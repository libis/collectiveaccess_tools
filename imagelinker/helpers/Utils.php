<?php

/**
 * User: NaeemM
 * Date: 9/03/2016
 */
class Utils
{

    /**
     * Utils constructor.
     */
    public function __construct()
    {
    }

    public function prepareImagesData($inputFilePath, $action, $log){
        $imagesToLink = array();
        $lines = file($inputFilePath);
        if(empty($lines)){
            $log->logError($inputFilePath . " file is empty.");
            die($inputFilePath . " file is empty. \n");
        }

        /* Gather information to link. */

        /* Process the input file to prepare images to be linked. */
        // ADD, DELETE input file has only two columns (collective access id and pid to add or delete)
        if (in_array(strtoupper($action), array("ADD","DELETE"))){
            foreach($lines as $line){
                $record = explode("\t", trim($line));
                if(sizeof($record) != 2){
                    $log->logError("Invalid format provided for ". $action." action: " .$line);
                    continue;
                }

                $image = new ImageData();
                $normalizedIdentifier = trim($this->normalizeIdentifier($record[0]));
                $image->setRecordId($normalizedIdentifier);
                $image->setPid(trim($record[1]));
                $image->setOriginal(trim($line));
                $imagesToLink [] = $image;
                $image = null;
            }
        }
        elseif(strtoupper($action) === "UPDATE"){
            foreach($lines as $line){
                $record = explode("\t", trim($line));
                if(sizeof($record) != 3){
                    $log->logError("Invalid format provided for ". $action." action: " .$line);
                    continue;
                }

                $image = new ImageData();
                $image->setRecordId(trim($record[0]));
                $image->setPid(trim($record[1]));               //pid to be replaced
                $image->setReplacementPid(trim($record[2]));    //pid to replace with
                $image->setOriginal(trim($line));
                $imagesToLink [] = $image;
                $image = null;
            }
        }

        return $imagesToLink;
    }

    public function removeDuplicateImageData($imagesToLink){
        $imagesToLink = array_unique($imagesToLink,  SORT_REGULAR);
        return $imagesToLink;

    }

    public function isFindResponseValid($response){
        $result = array('isValid' => false);
        if(!empty($response) && !empty($response->results)){
            $objectData = $response->results;
            /* Image will only be linked if only one record is found. Therefore is invalid for all other cases. */
            if(sizeof($objectData) === 1 && !empty(current($objectData)->object_id) ){
                $result = array(
                    'isValid' => true,
                    'object_id' => current($objectData)->object_id,
                    'object_data' => current($objectData)
                );
            }
        }
        return $result;
    }

    public function isGetItemResponseValid($response){
        $result = array('isValid' => false);
        if(!empty($response) && empty($response->errors)){
            unset($response->ok);
            $result = array(
                'isValid' => true,
                'object_data' => $response
            );
        }
        return $result;
    }

    public function normalizePid($pidIn){
        return str_replace("_", '', current(explode("_", $pidIn)));
    }

    public function isActionSuccessfull($response){
        if(!empty($response) && !empty($response->ok) && !empty($response->object_id))
            return true;
        else
            return false;
    }

    public function normalizeIdentifier($identifier){
        $pos = strrpos ($identifier, "_");
        if ($pos === false)
            return $identifier;
        else
            return str_replace("_",".",substr($identifier, 0, $pos));
    }

}