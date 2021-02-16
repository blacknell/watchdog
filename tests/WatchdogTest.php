<?php

use Blacknell\Watchdog\Watchdog;
use PHPUnit\Framework\TestCase;

class WatchdogTest extends TestCase
{
    public function testGetProcesses()
    {
        $processes = [
            ' 3061 Tue Feb 16 15:16:06 2021 /bin/ls',
            '19191 Tue Feb 16 15:00:05 2021 /usr/bin/python3'];

        $expected =
            array (
                0 =>
                    array (
                        'pid' => 3061,
                        'name' => '/bin/ls',
                        'startTime' =>
                            Moment\Moment::__set_state(array(
                                'rawDateTimeString' => 'Tue Feb 16 15:16:06 2021',
                                'timezoneString' => 'UTC',
                                'immutableMode' => false,
                                'date' => '2021-02-16 15:16:06.000000',
                                'timezone_type' => 3,
                                'timezone' => 'UTC',
                            )),
                    ),
                1 =>
                    array (
                        'pid' => 19191,
                        'name' => '/usr/bin/python3',
                        'startTime' =>
                            Moment\Moment::__set_state(array(
                                'rawDateTimeString' => 'Tue Feb 16 15:00:05 2021',
                                'timezoneString' => 'UTC',
                                'immutableMode' => false,
                                'date' => '2021-02-16 15:00:05.000000',
                                'timezone_type' => 3,
                                'timezone' => 'UTC',
                            )),
                    ),
            );

        $result = Watchdog::getProcesses($processes);
        self::assertEquals($expected, $result);
    }

}