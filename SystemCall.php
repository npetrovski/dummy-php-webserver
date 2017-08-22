<?php

include_once 'Task.php';
include_once 'Scheduler.php';


class SystemCall
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, Scheduler $scheduler)
    {
        $callback = $this->callback; // Can't call it directly in PHP :/
        return $callback($task, $scheduler);
    }
}
