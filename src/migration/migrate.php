<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$container = require_once __DIR__ . '/../container.php';

use Hexlet\Code\DbTableCreator;

try {
    // Запуск миграций
    DbTableCreator::createTables($container->get('db'));
    echo "Миграции успешно выполнены.\n";
} catch (Exception $e) {
    echo "Произошла ошибка при выполнении миграций: " . $e->getMessage() . "\n";
}
