# Watchdog
[![Latest Stable Version](https://poser.pugx.org/blacknell/watchdog/v/stable)](https://packagist.org/packages/blacknell/watchdog)
[![Latest Unstable Version](https://poser.pugx.org/blacknell/watchdog/v/unstable)](https://packagist.org/packages/blacknell/watchdog)
[![License](https://poser.pugx.org/blacknell/watchdog/license)](https://packagist.org/packages/blacknell/watchdog)

A simple watchdog to monitor and keep alive a process. Your process should update a watch file such as `/var/tmp/myprocess.watchdog` at regular intervals. The watchdog checks this is being updated and if not assumes it is hung. The watchdog then attempts to kill any matching prcoesses and restart a new one.
## Installation

Install the latest version with
```
$ composer require blacknell/watchdog
```
## Sample code
See [example/wrapper.php](https://github.com/blacknell/watchdog/blob/master/example/wrapper.php)
## Usage
Run from within crontab at an interval less frequent than the watchdog file update rate.
`0,5,10,15,20,25,30,35,40,45,50,55 * * * * sudo /usr/bin/php /home/pi/heating/watchdog.php > /dev/null 2>&1`
## Logging
PSR-3 logging is supported via [monolog/monolog](https://github.com/Seldaek/monolog) by passing 
an optional `Logger` object to the API constructor.