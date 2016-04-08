<?php
/**
 * User: NaeemM
 * Date: 24/07/14
 */

require_once("rabbitmq/vendor/autoload.php");

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class integrationQueue {

    private $rmq_server;
    private $rmq_port;
    private $rmq_uid;
    private $rmq_pwd;
    private $rmq_vhost;
    private $queue_name;
    private $config_file;


    # Constructor
    public function __construct()
    {
        $this->config_file = "helpers/config/libisin.ini";
    }

    public function queuingRequest($msg_body) {
        $connection = new AMQPConnection($this->rmq_server, $this->rmq_port, $this->rmq_uid, $this->rmq_pwd, $this->rmq_vhost);

        $channel = $connection->channel();
        $channel->queue_declare($this->queue_name, false, false, false, false);

        $msg = new AMQPMessage(json_encode($msg_body));
        $channel->basic_publish($msg, '', $this->queue_name);

        $channel->close();
        $connection->close();
        echo "Task posted to queue '". $this->queue_name."'\n";
    }

    public function loadLibisInConfigurations($remote = true){
        /* Load configurations. */
        if (!$queuingConfig = parse_ini_file($this->config_file, $process_sections = true))
            die("Configuration file ($this->config_file) does not exist.\n");

        $config = $remote ? $queuingConfig['queue_remote'] : $queuingConfig['queue_local'];

        $this->rmq_server = $config['rmq_server'];
        $this->rmq_port = $config['rmq_port'];
        $this->rmq_uid = $config['rmq_uid'];
        $this->rmq_pwd = $config['rmq_pwd'];
        $this->rmq_vhost = $config['rmq_vhost'];
        $this->queue_name = trim($config['queue_name']);

    }

} 