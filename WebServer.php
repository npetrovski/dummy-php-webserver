<?php
include_once 'Task.php';
include_once 'Scheduler.php';
include_once 'IOScheduler.php';
include_once 'SystemCall.php';

class WebServer
{
    private $host;

    private $port;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function start()
    {
        $scheduler = new IOScheduler;
        $scheduler->newTask(
            $this->_server($this->host, $this->port)
        );
        $scheduler->run();
    }

    private function _server($host, $port)
    {
        echo sprintf("Starting server at %s port %d...\n", $host, $port);

        $socket = @stream_socket_server("tcp://$host:$port", $errNo, $errStr);
        if (!$socket) {
            throw new \Exception($errStr, $errNo);
        }

        stream_set_read_buffer($socket, 0);
        stream_set_write_buffer($socket, 0);

        stream_set_blocking($socket, 0);

        while (true) {
            yield $this->_waitForRead($socket);
            $clientSocket = stream_socket_accept($socket, 0);
            yield $this->_newTask($this->handleClient($clientSocket));
        }
    }

    private function _newTask(Generator $coroutine)
    {
        return new SystemCall(
            function (Task $task, Scheduler $scheduler) use ($coroutine) {
                $task->setSendValue($scheduler->newTask($coroutine));
                $scheduler->schedule($task);
            }
        );
    }

    private function _killTask($tid)
    {
        return new SystemCall(
            function (Task $task, Scheduler $scheduler) use ($tid) {
                $task->setSendValue($scheduler->killTask($tid));
                $scheduler->schedule($task);
            }
        );
    }

    private function _waitForRead($socket)
    {
        return new SystemCall(
            function (Task $task, IOScheduler $scheduler) use ($socket) {
                $scheduler->waitForRead($socket, $task);
            }
        );
    }

    private function _waitForWrite($socket)
    {
        return new SystemCall(
            function (Task $task, IOScheduler $scheduler) use ($socket) {
                $scheduler->waitForWrite($socket, $task);
            }
        );
    }

    private function handleClient($socket)
    {
        yield $this->_waitForRead($socket);
        $data = fread($socket, 8192);

        $msg = "Received following request:\n\n$data";
        $msgLength = strlen($msg);

        $response = <<<RES
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$msg
RES;

        yield $this->_waitForWrite($socket);
        fwrite($socket, $response);
        fclose($socket);
    }
}
