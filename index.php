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

function newTask(Generator $coroutine)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($coroutine) {
        $task->setSendValue($scheduler->newTask($coroutine));
        $scheduler->schedule($task);
    });
}

function killTask($tid)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($tid) {
        $task->setSendValue($scheduler->killTask($tid));
        $scheduler->schedule($task);
    });
}

function waitForRead($socket)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($socket) {
        $scheduler->waitForRead($socket, $task);
    });
}

function waitForWrite($socket)
{
    return new SystemCall(function (Task $task, Scheduler $scheduler) use ($socket) {
        $scheduler->waitForWrite($socket, $task);
    });
}

function server($port)
{
    echo "Starting server at port $port...\n";

    $socket = @stream_socket_server("tcp://localhost:$port", $errNo, $errStr);

    if (! $socket) {
        throw new Exception($errStr, $errNo);
    }

    while (true) {
        yield waitForRead($socket);
    }

    $clientSocket = stream_socket_accept($socket, 0);
    yield newTask(handleClient($clientSocket));
}

function handleClient($socket)
{
    yield waitForRead($socket);
    $data = fread($socket, 8192);

    $msg = "Received following request: \n\n $data";
    $msgLength = strlen($msg);

    $response = <<<RES
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
RES;

    yield waitForWrite($socket);

    fwrite($socket, $response);

    fclose($socket);
}


// Run
$scheduler = new Scheduler;
$scheduler->newTask(server(8000));
$scheduler->run();
