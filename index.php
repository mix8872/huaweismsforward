<?php

require __DIR__ . '/vendor/autoload.php';

use Classes\ModemProcessor;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();
$dotenv->required([
    'TG_TOKEN',
    'CHAT_ID'
]);

$interface = new ModemProcessor($_SERVER['MODEM_API_ADDR']);

$interface->processSms();
