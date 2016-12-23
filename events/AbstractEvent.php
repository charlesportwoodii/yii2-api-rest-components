<?php

namespace yrc\events;

abstract class AbstractEvent extends \yii\base\Event
{
    protected $queue;
    protected $job;

    /**
     * Overloaded constructor
     * @param array $config
     * @param Disque\Queue $queue
     * @param Disque\Queue\Job $job
     */
    public function __construct($config = [], \Disque\Queue\Queue $queue, \Disque\Queue\Job $job)
    {
        parent::__construct($config);
        $this->queue = $queue;
        $this->job = $job;
    }
}