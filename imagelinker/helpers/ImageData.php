<?php

/**
 * User: NaeemM
 * Date: 7/03/2016
 */
class ImageData
{
    public $recordId;
    public $pid;
    public $replacementPid;
    public $original;

    /**
     * ImageData constructor.
     */
    public function __construct()
    {
    }


    /**
     * @return mixed
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * @param mixed $recordId
     */
    public function setRecordId($recordId)
    {
        $this->recordId = $recordId;
    }

    /**
     * @return mixed
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param mixed $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @return mixed
     */
    public function getReplacementPid()
    {
        return $this->replacementPid;
    }

    /**
     * @param mixed $replacementPid
     */
    public function setReplacementPid($replacementPid)
    {
        $this->replacementPid = $replacementPid;
    }

    /**
     * @return mixed
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * @param mixed $original
     */
    public function setOriginal($original)
    {
        $this->original = $original;
    }


}