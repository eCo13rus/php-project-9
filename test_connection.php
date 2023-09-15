<?php

require_once __DIR__ . '/vendor/autoload.php'; // путь должен вести к вашему автозагрузчику composer

use Hexlet\Code\Connection;

try {
    $pdo = Connection::connect();
    echo "Подключение успешно установлено.\n";

    $stmt = $pdo->query('SELECT NOW()');
    $dateTime = $stmt->fetchColumn();
    echo "Текущее время на сервере базы данных: $dateTime\n";
} catch (\Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
