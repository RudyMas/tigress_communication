<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tigress\Smartschool;

require_once 'vendor/autoload.php';

$logger = new Logger('smartschool');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/smartschool.log', Logger::ERROR));

$ss = new Smartschool('school.example.be', 'supersecret', $logger);
