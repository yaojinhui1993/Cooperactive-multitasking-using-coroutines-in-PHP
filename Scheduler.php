<?php

require_once './Task.php';
require_once './SystemCall.php';

class Scheduler
{
    protected $maxTaskId = 0;
    protected $taskMap = [];
    protected $taskQueue;
    protected $waitingForRead = [];
    protected $waitingForWrite = [];

    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    public function newTask(Generator $coroutine)
    {
        $tid = ++$this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);

        return $tid;
    }

    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
    }

    public function run()
    {
        $this->ioPollTask();

        while (! $this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $retValue = $task->run();

            if ($retValue instanceof SystemCall) {
                $retValue($task, $this);
                continue;
            }

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
    }

    public function killTask($tid)
    {
        if (! isset($this->taskMap[$tid])) {
            return false;
        }

        foreach ($this->taskQueue as $i => $task) {
            if ($task->getTaskId() === $tid) {
                unset($this->taskQueue[$i]);
                break;
            }
        }

        return true;
    }

    public function waitForRead($socket, Task $task)
    {
        if (isset($this->waitingForRead[(int)$socket])) {
            $this->waitingForRead[(int)$socket][1][] = $task;
        } else {
            $this->waitingForRead[(int)$socket] = [$socket, [$task]];
        }
    }

    public function waitForWrite($socket, Task $task)
    {
        if (isset($this->waitingForWrite[(int)$socket])) {
            $this->waitingForWrite[(int)$socket][1][] = $task;
        } else {
            $this->waitingForWrite[(int)$socket] = [$socket, [$task]];
        }
    }

    protected function ioPoll($timeout)
    {
        $rSocks = [];
        foreach ($this->waitingForRead as list($socket)) {
            $rSocks[] = $socket;
        }

        $wSocks = [];
        foreach ($this->waitingForWrite as list($socket)) {
            $wSocks = $socket;
        }

        $eSocks = [];

        if (! stream_select($rSocks, $wSocks, $eSocks, $timeout)) {
            return;
        }

        foreach ($rSocks as $socks) {
            list(, $tasks) = $this->waitingForRead[(int)$socks];
            unset($this->waitingForRead[(int)$socks]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }


        foreach ($wSocks as $socks) {
            list(, $tasks) = $this->waitingForWrite[(int)$socks];
            unset($this->waitingForWrite[(int)$socks]);

            foreach ($tasks as $task) {
                $this->schedule($task);
            }
        }
    }

    protected function ioPollTask()
    {
        if ($this->taskQueue->isEmpty()) {
            $this->ioPoll(null);
        } else {
            $this->ioPoll(0);
        }
        yield;
    }
}
