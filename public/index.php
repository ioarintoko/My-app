<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/../vendor/autoload.php';

try {
    /** @var \Illuminate\Foundation\Application $app */
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $app->handleRequest(\Illuminate\Http\Request::capture());

} catch (Throwable $e) {

    header('Content-Type: text/plain');

    echo "CLASS:\n";
    echo get_class($e)."\n\n";

    echo "MESSAGE:\n";
    echo $e->getMessage()."\n\n";

    echo "FILE:\n";
    echo $e->getFile().':'.$e->getLine()."\n\n";

    echo "TRACE:\n";
    echo $e->getTraceAsString();
}