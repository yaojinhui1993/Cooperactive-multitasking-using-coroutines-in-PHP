<?php

require './Scheduler.php';

function task1()
{
    for ($i = 1; $i <=10; ++$i) {
        echo "This is task 1 iteration {$i}. \n";
        yield;
    }
}


function task2()
{
    for ($i = 1; $i <=5; ++$i) {
        echo "This is task 2 iteration {$i}. \n";
        yield;
    }
}

$schedular = new Scheduler();

$schedular->newTask(task1());
$schedular->newTask(task2());

$schedular->run();
