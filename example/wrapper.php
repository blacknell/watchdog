<?php

/**
 * Copyright (c) 2019. Paul Blacknell https://github.com/blacknell
 */

require __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\NullHandler;
use Monolog\Formatter\LineFormatter;

use Moment\Moment;

use Blacknell\Watchdog\Watchdog;

// sample file demonstrating how to call and use the watchdog

if (php_sapi_name() != "cli") {
    echo "Command line only, please" . PHP_EOL;
    die;
}

\Moment\Moment::setLocale('en_GB');
\Moment\Moment::setDefaultTimezone('Europe/London');
date_default_timezone_set('Europe/London');

$logfile = 'php://STDOUT';
$log = new Logger("watchdog-wrapper");

try {
    // first check the log file is writable - unfortunately the logger class
    // doesn't check this until it's first actually written to
    $f = @fopen($logfile, 'a+');
    if (!$f) {
        throw new \LogicException ('Could not open log file for writing');
    }
    fclose($f);
    $logHandler = new StreamHandler($logfile, Logger::INFO);    // change to DEBUG for testing
    $logHandler->setFormatter(new LineFormatter(null, Watchdog::LOG_DATE_FORMAT));
    $log->pushHandler($logHandler);

} catch (\LogicException $e) {
    $logHandler = new NullHandler(Logger::INFO);
} catch (\Exception $e) {
    $logHandler = new NullHandler(Logger::INFO);
}

$wrapper = new Blacknell\Watchdog\Watchdog($log);

$wrapper->watch(
    '/bin/ls',                     // replace this with the command that starts your long lived process
    "mygrepstring",            // replace this with a script that will be grep'd to see if it is still running
    '/tmp/mywatchdog.watchdog',   // your script should touch this file regularly
    15,                       // and this is how regularly (worst case) in seconds
    ['/home/pi/myscript.php']);             // changing any of these files will force a restart