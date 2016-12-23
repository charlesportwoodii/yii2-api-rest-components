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

    /**
     * Mark the event as handled, then tell the queue it's been handled
     * @return true
     */
    public function handled()
    {
        $this->handled = true;
        $this->queue->processed($this->job);
        return true;
    }
    
    /**
     * Requeue the event
     * @return true
     */
    public function retry()
    {
        $this->handled = true;
        $this->queue->failed($this->job);
        return true;
    }
}