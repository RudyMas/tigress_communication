<?php

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tigress\Smartschool;

require_once 'vendor/autoload.php';

$logger = new Logger('smartschool');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/smartschool.log', Level::Error));

$ss = new Smartschool('school.example.be', 'supersecret', $logger);
