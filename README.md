# Watchdog
[![Latest Stable Version](https://poser.pugx.org/blacknell/watchdog/v/stable)](https://packagist.org/packages/blacknell/watchdog)
[![Latest Unstable Version](https://poser.pugx.org/blacknell/watchdog/v/unstable)](https://packagist.org/packages/blacknell/watchdog)
[![License](https://poser.pugx.org/blacknell/watchdog/license)](https://packagist.org/packages/blacknell/watchdog)

A simple watchdog to monitor and keep alive a process
## Installation

Install the latest version with
```
$ composer require blacknell/watchdog
```
## Sample code
See [example/wrapper.php](https://github.com/blacknell/watchdog/blob/master/example/wrapper.php)
## Logging
PSR-3 logging is supported via [monolog/monolog](https://github.com/Seldaek/monolog) by passing 
an optional `Logger` object to the API constructor.