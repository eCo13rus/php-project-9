<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$container = require_once __DIR__ . '/../../container.php';

use Hexlet\Code\DbTableCreator;

// Запуск миграций
DbTableCreator::createTables($container->get('db'));
