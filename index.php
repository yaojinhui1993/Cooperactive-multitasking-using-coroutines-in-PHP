<?php

require_once './Scheduler.php';
require_once './Task.php';
require_once './SystemCall.php';

function getTaskId()
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) {
        $task->setSendValue($task->getTaskId());
        $scheduler->schedule($task);
    });
}

function task($max)
{
    // var_dump(1);
    $tid = (yield getTaskId());
    // var_dump("$tid: 2");

    for ($i = 1; $i <= $max; ++$i) {
        echo "This is task $tid iteration $i. \n";
        yield;
        // var_dump("$tid - $i - 3");
    }
}

$schedular = new Scheduler();

$schedular->newTask(task(10));
$schedular->newTask(task(5));

$schedular->run();
