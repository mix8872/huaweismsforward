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

$processor = new ModemProcessor($_SERVER['MODEM_API_ADDR']);
$processor->processSms();
