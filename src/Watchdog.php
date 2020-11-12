<?php

/**
 * Copyright (c) 2019. Paul Blacknell https://github.com/blacknell
 */

namespace Blacknell\Watchdog;

use Monolog\Logger;
use Monolog\Handler\NullHandler;

use Moment\Moment;

class Watchdog
{
    const LOG_DATE_FORMAT = 'Y-m-d H:i:s T';

    private $logger;

	public function __construct(\Monolog\Logger $logger = null)
	{
		if (isset($logger) && $logger) {
			@assert(is_a($logger, '\Monolog\Logger'));
			$this->logger = $logger;
		}
		else {
			$this->logger = new Logger('watchdog');
			$logHandler = new NullHandler();
			$this->logger->pushHandler($logHandler);
		}
		$this->logger->debug(sprintf("Watchdog '%s' starting.", $this->logger->getName()));
	}

	function __destruct()
	{
		$this->logger->debug(sprintf("Watchdog '%s' exiting.", $this->logger->getName()));
	}

    /**
     * @param $watchScript      full command to re-start script
     * @param $watchScriptGrep  grep'able string for the script we're watching
     * @param $watchdogFile     the file that the script keeps touching
     * @param $watchdogMaxAge   the interval in seconds at which it should always be touched
     * @param $watchfiles       list of filenames to be checked for changes since process started
     *
     * @throws \Moment\MomentException
     */
    public function watch($watchScript, $watchScriptGrep, $watchdogFile, $watchdogMaxAge, $watchfiles = [])
    {
        @assert(is_string($watchScript));
        @assert(is_string($watchScriptGrep));
        @assert(is_string($watchdogFile));
        @assert(is_int($watchdogMaxAge));
        @assert($watchdogMaxAge > 0);
        @assert(is_array($watchfiles));

        $watchdogDead = false;

        $hostName = gethostname();
        $ipAddress = gethostbyname($hostName);

        if (!@filemtime($watchdogFile)) {
            $watchdogDead = true;
            $this->logger->notice(sprintf("Watchdog file %s does not exist" . PHP_EOL, $watchdogFile), [$hostName, $ipAddress]);
        } else {
            $fileModificationTime = new Moment();
            $fileModificationTime->setTimestamp(filemtime($watchdogFile));
            $now = new Moment();
            $this->logger->debug(sprintf("Watchdog file last touched %s, %s", $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $fileModificationTime->fromNow()->getRelative()));

            if (($now->getTimestamp() - $fileModificationTime->getTimestamp()) > $watchdogMaxAge) {
                $this->logger->notice(sprintf("Watchdog file %s is more than %u seconds old. Last touched %s, %s", $watchdogFile, $watchdogMaxAge, $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $fileModificationTime->fromNow()->getRelative()), [$hostName, $ipAddress]);
                $watchdogDead = true;
            }
        }

        $watchFilesHaveChanged = false;
        if (count($watchfiles) > 0) {
            $this->logger->debug("Checking for changes to files since process was started", $watchfiles);
            $processes = array();
            exec(sprintf('ps -eo lstart,cmd|grep %s|grep -v grep', $watchScriptGrep), $processes);
            // for each matching process find it's start time
            // and compare to the files we've been asked to check against
            foreach ($processes as $process) {
                $processStartTime = new Moment(substr($process, 0, 24));
                foreach ($watchfiles as $watchfile) {
                    $fileModificationTime = new Moment();
                    $fileModificationTime->setTimestamp(filemtime($watchfile));
                    if (@filemtime($watchfile)) {
                        $fileModificationTime = new Moment();
                        $fileModificationTime->setTimestamp(filemtime($watchfile));
                        if ($fileModificationTime->isAfter($processStartTime)) {
                            $this->logger->notice(sprintf("Watch file %s changed %s since process started %s", $watchfile, $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $processStartTime->format(Watchdog::LOG_DATE_FORMAT)), [$hostName, $ipAddress]);
                            $watchFilesHaveChanged = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if ($watchdogDead || $watchFilesHaveChanged) {

            // first find processes and ask nicely
            $processes = array();
            exec(sprintf('ps ax|grep %s|grep -v grep', $watchScriptGrep), $processes);
            foreach ($processes as $process) {
                $item = Watchdog::getProcess($process);
                $this->logger->info(sprintf("Asking process %s to exit gracefully", $item));
                exec(sprintf("kill -1 %s", $item)); // 1 is equiv to HUP or SIGHUP
            }
            sleep(2);

            // now find stuck processes and just kill them
            $processes = array();
            exec(sprintf('ps ax|grep %s|grep -v grep', $watchScriptGrep), $processes);
            foreach ($processes as $process) {
                $item = Watchdog::getProcess($process);
                $this->logger->info(sprintf("Forcing process %s to exit", $item));
                exec(sprintf("kill -9 %s", $item));
            }
            sleep(2);

            // now restart the script

            $processScript = sprintf("%s > /dev/null &", $watchScript); # > /dev/null &
            $this->logger->info(sprintf("Starting a new process with '%s'", $processScript));
            echo exec($processScript);
            $processes = array();
            exec(sprintf('ps ax|grep %s|grep -v grep', $watchScriptGrep), $processes);
            foreach ($processes as $process) {
                $item = Watchdog::getProcess($process);
                $this->logger->debug(sprintf("Started process %s", $item));
            }

        } else {
            $this->logger->debug("Nothing to do");
        }

    }

    /**
     * @param $process single line of ps ax output
     * @return mixed pid of the process
     */
    private static function getProcess($process)
    {
        $process = trim(preg_replace('/\s\s+/', ' ', $process));
        $items = explode(' ', $process);
        return $items[0];
    }

}



