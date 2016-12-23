<?php

namespace yrc\components;

use Yii;
use yii\base\Object;
use Disque\Connection\Credentials;
use Disque\Client;

/**
 * Disque Manager
 * @class Queue
 */
class Queue extends Object
{
    /**
     * An array of Disque connections
     * @param array $nodes
     */
    private $nodes;

    /**
     * Adds client connections
     * @param array $clients
     * @return true
     */
    public function setClients($clients = [])
    {
        foreach ($clients as $client) {
            $this->nodes[] = new Credentials($client['host'], $client['port'], $client['password']);
        }

        return true;
    }

    /**
     * Retrieves the queue
     * @return Disque\Client
     */
    public function get()
    {
        return new Client($this->nodes);
    }

    /**
     * Adds a new job to a queue
     * @param array $jobProperties
     * @param string $queueName
     * return
     */
    public function addJob($jobProperties = [], $queueName = 'app')
    {
        $job = new \Disque\Queue\Job($jobProperties);
        return $this->get()->queue($queueName)->push($job);
    }
}