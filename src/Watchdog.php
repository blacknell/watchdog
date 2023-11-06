<?php

/**
 * Copyright (c) 2019. Paul Blacknell https://github.com/blacknell
 */

namespace Blacknell\Watchdog;

use Moment\MomentException;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

use Moment\Moment;

class Watchdog
{
    const LOG_DATE_FORMAT = 'Y-m-d H:i:s T';

    private Logger $logger;
    private string $hostName;
    private string $ipAddress;

    public function __construct(Logger $logger = null)
    {
        if (isset($logger) && $logger) {
            @assert(is_a($logger, '\Monolog\Logger'));
            $this->logger = $logger;
        } else {
            $this->logger = new Logger('watchdog');
            $logHandler = new NullHandler();
            $this->logger->pushHandler($logHandler);
        }

        $this->hostName = gethostname();
        $this->ipAddress = gethostbyname($this->hostName);
        $this->logger->debug("Watchdog starting");
    }

    function __destruct()
    {
        $this->logger->debug("Watchdog exiting");
    }

    /**
     * @param string $watchScript full command to re-start script
     * @param string $watchScriptGrep grep'able string for the script we're watching
     * @param string $watchdogFile the file that the script keeps touching
     * @param int $watchdogMaxAge the interval in seconds at which it should always be touched
     * @param array $watchFiles list of filenames to be checked for changes since process started
     *
     * @throws MomentException
     */
    public function watch(string $watchScript, string $watchScriptGrep, string $watchdogFile, int $watchdogMaxAge, array $watchFiles = [])
    {
        @assert($watchdogMaxAge > 0);
        @assert(is_array($watchFiles));

        $watchdogDead = false;

        if (!@filemtime($watchdogFile)) {
            $watchdogDead = true;
            $this->logger->notice(sprintf("Watchdog file %s does not exist", $watchdogFile));
        } else {
            $fileModificationTime = new Moment();
            $fileModificationTime->setTimestamp(filemtime($watchdogFile));
            $now = new Moment();
            $this->logger->debug(sprintf("Watchdog file last touched %s, %s", $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $fileModificationTime->fromNow()->getRelative()));

            if (($now->getTimestamp() - $fileModificationTime->getTimestamp()) > $watchdogMaxAge) {
                $this->logger->notice(sprintf("Watchdog file %s is more than %u seconds old. Last touched %s, %s. Restarting.", $watchdogFile, $watchdogMaxAge, $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $fileModificationTime->fromNow()->getRelative()));
                $watchdogDead = true;
            }
        }

        $processes = array();
        if (!$watchdogDead) {
            // get all running processes that match the grep string
            $processlist = array();
            exec(sprintf('ps -eo pid,lstart,cmd|grep "%s"|grep -v grep', $watchScriptGrep), $processlist);
            $processes = self::getProcesses($processlist);
            $this->logger->debug(sprintf("Running processes that match grep '%s'", $watchScriptGrep), [$processlist, $processes]);
            $watchFilesHaveChanged = false;
            if (count($watchFiles) > 0) {
                // for each matching process compare it's start time to the files we've been asked to check against
                $this->logger->debug("Checking for changes to files since process was started", $watchFiles);
                foreach ($processes as $process) {
                    foreach ($watchFiles as $watchFile) {
                        if (@filemtime($watchFile)) {
                            $fileModificationTime = new Moment();
                            $fileModificationTime->setTimestamp(filemtime($watchFile));
                            if ($fileModificationTime->isAfter($process['startTime'])) {
                                $this->logger->notice(sprintf("Watch file %s changed %s which is later than when process started (%s). Restarting.", $watchFile, $fileModificationTime->format(Watchdog::LOG_DATE_FORMAT), $process['startTime']->from($fileModificationTime)->getRelative()), [$this->hostName, $this->ipAddress, getmypid()]);
                                $watchFilesHaveChanged = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (!($watchdogDead || $watchFilesHaveChanged) && count($processes) == 0) {
            $this->logger->notice("No processes running that match grep string",[$watchScriptGrep]);
        }

        if ($watchdogDead || $watchFilesHaveChanged || count($processes) == 0) {

            // first find processes and ask them nicely
            $processlist = array();
            exec(sprintf('ps -eo pid,lstart,cmd|grep "%s"|grep -v grep', $watchScriptGrep), $processlist);
            $processes = self::getProcesses($processlist);


            foreach ($processes as $process) {
                $this->logger->info(sprintf("Asking process %s to exit gracefully", $process['pid']));
                $killCommand = sprintf("kill -15 %s > /dev/null 2>&1", $process['pid']); // 15 is SIGTERM
                $this->logger->debug('Executing', [$killCommand]);
                exec($killCommand);
            }
            sleep(2);

            // check again to find stuck processes and just kill them
            $processlist = array();
            exec(sprintf('ps -eo pid,lstart,cmd|grep "%s"|grep -v grep', $watchScriptGrep), $processlist);
            $processes = self::getProcesses($processlist);
            foreach ($processes as $process) {
                $this->logger->info(sprintf("Forcing process %s to exit", $process['pid']));
                $killCommand = sprintf("kill -9 %s > /dev/null 2>&1", $process['pid']);
                $this->logger->debug('Executing', [$killCommand]);
                exec($killCommand);
            }

            // now restart the script

            // check first once more. if they're not dead we'll have to abort
            $processlist = array();
            exec(sprintf('ps -eo pid,lstart,cmd|grep "%s"|grep -v grep', $watchScriptGrep), $processlist);
            $processes = self::getProcesses($processlist);
            if (count($processes) > 0) {
                foreach ($processes as $process) {
                    $this->logger->notice(sprintf("Was unable to kill process %s", $process['pid']), [$process['name']]);
                }
                $this->logger->critical("Abandoning watchdog as we couldn't kill existing processes");
                exit(1);
            }


            $processScript = sprintf("%s &", $watchScript);
            $this->logger->info(sprintf("Starting a new process with '%s'", $processScript));
            exec($processScript);
            sleep(1);

            $processlist = array();
            exec(sprintf('ps -eo pid,lstart,cmd|grep "%s"|grep -v grep', $watchScriptGrep), $processlist);
            $processes = self::getProcesses($processlist);
            $processPids = array();
            foreach ($processes as $process) {
                $processPids[] = $process['pid'];
            }
            $this->logger->info(sprintf("%d processes match grep '%s'", count($processes), $watchScriptGrep), $processPids);
            if (count($processes) == 0) {
                $this->logger->warning(sprintf("No processes restarted - none match grep '%s'", $watchScriptGrep));
            }
        } else {
            $this->logger->debug("Nothing to do");
        }

    }

    /**
     * @param $processlist
     *
     * @return array
     * @throws MomentException
     */
    public static function getProcesses($processlist): array
    {
        $processes = array();
        foreach ($processlist as $process) {
            $process = ltrim($process);
            $firstSpace = strpos($process, ' ');
            $processes[] = [
                'pid' => (int)substr($process, 0, $firstSpace),
                'name' => substr($process, 26 + $firstSpace),
                'startTime' => new Moment(substr($process, $firstSpace + 1, 24)),
            ];
        }

        return $processes;
    }

}
