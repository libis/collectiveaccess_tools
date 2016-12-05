<?php
/**
 * Created by PhpStorm.
 * User: AnitaR
 * Date: 11/06/14
 * Time: 11:59
 */
require_once 'vendor/autoload.php';

use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\Cookie;
use Guzzle\Plugin\Cookie\CookiePlugin;
use Guzzle\Plugin\Cookie\CookieJar\ArrayCookieJar;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;

class GuzzleRest {

    private $userid = null;
    private $paswoord = null;
    private $host = null;
    private $base = null;
    private $header = null;
    private $uri = null;
    private $client = null;
    private $log = null;

    # -------------------------------------------------------
    /**
     * Constructor
     */
    public function __construct($configurations, $log) {

        $this->log = $log;
        $this->userid = $configurations['userid'];
        $this->paswoord = $configurations['paswoord'];
        $this->host = $configurations['host'];
        $this->base = $configurations['base'];

        $this->uri = 'http://'.$this->userid.':'.$this->paswoord.'@'.$this->host ;
        $this->header = array(
            'Content-Type'  => 'application/json',
        );

        $this->createCookies();
    }
    # -------------------------------------------------------
    /**
     * Destructor
     */

    public function __destruct() {

    }
    # -------------------------------------------------------

    /**
     * Create cookies
     */
    public function createCookies(){
        $cookie = new CookiePlugin(new ArrayCookieJar());
        $this->post_data = array(
            'username'  =>  $this->userid,
            'password'  =>  $this->paswoord
        );

        $this->Client = new Client($this->uri);

        $this->Client->addSubscriber($cookie);
        $this->log->logInfo("Creating cookies ");
        /* This call is only being made to setup cookies. */
        try {
        $this->Client->get('/'.$this->base .'/service.php/model/ca_entities?pretty=1', $this->header)
            ->setAuth($this->userid, $this->paswoord)->send();
        } catch (Exception $ex) {
            $this->log->logInfo("Error in connecting to the server.");
            $this->log->logInfo(print_r($ex->getMessage(), true));
            $this->log->logInfo("Exiting.");
            die ("Error in connecting to the server. Exiting.\n");
        }

        $this->log->logInfo("Cookies created successfully.");
    }


    /**
     * Update object for pids
     * @param $update
     * @param $object_id
     * @param $table
     * @return mixed
     */
    function updateObject($update, $object_id, $table) {
        $json_update = json_encode($update);
        $request = $this->Client->put("/".$this->base."/service.php/item/".$table."/id/".$object_id, $this->header, $json_update);
        $this->log->logInfo("\t\tUpdate object URL ".$request->getUrl());
        $response = $request->send();
        $data = (String)$response->getBody();
        return json_decode($data);
    }

    /**
     * Find object with rest call
     * @param $query
     * @param $table
     * @return mixed
     */
    function findObject($query, $table) {
        $request = $this->Client->get('/'.$this->base.'/service.php/find/'.$table.'?q='.$query.'&pretty=1&start=0&rows=100');
        $this->log->logInfo("\t\tFind object URL ".$request->getUrl());
        $response = $request->send();
        $data = (String)$response->getBody();
        return json_decode($data);

    }

    /**
     * Get full details of the object
     * @param $id
     * @param $table
     * @return mixed
     */
    function getFullObject($id, $table) {
        $request = $this->Client->get('/'.$this->base.'/service.php/item/'.$table.'/id/'.$id.'?pretty=1&format=edit');
        $this->log->logInfo("\t\tGet full object URL ".$request->getUrl());
        $response = $request->send();
        $data = (String)$response->getBody();
        return json_decode($data);
    }

    function createObject($data, $table){
        $json_data = json_encode($data);
        $request = $this->Client->put("/".$this->base."/service.php/item/".$table, $this->header, $json_data);
        $this->log->logInfo("\t\tCreate object URL ".$request->getUrl());
        $response = $request->send();
        $data = (String)$response->getBody();
        return json_decode($data);
    }

    function findCollection($query, $table) {
        $request = $this->Client->get('/'.$this->base.'/service.php/find/'.$table.'?q='.$query.'&pretty=1&start=0&rows=100');
        $this->log->logInfo("\t\tFind object URL ".$request->getUrl());
        $response = $request->send();
        $data = (String)$response->getBody();
        return json_decode($data);

    }

} 