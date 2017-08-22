<?php

include_once 'WebServer.php';

$server = new WebServer('localhost', 8000);
$server->start();

