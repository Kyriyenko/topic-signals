<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootDir = dirname(__DIR__);

if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->load();
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions($rootDir . '/config/dependencies.php');

return $containerBuilder->build();
