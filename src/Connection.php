<?php

namespace Hexlet\Code;

final class Connection
{
    /**
     * Устанавливает соединение с базой данных через PDO и возвращает объект PDO.
     *
     * @throws \RuntimeException Если не удалось получить необходимые данные для подключения к БД.
     * @throws \Exception Если произошла другая ошибка при подключении к БД.
     * @return \PDO Объект PDO для взаимодействия с базой данных.
     */
    public static function connect(): \PDO
    {
        try {
            // Определяем путь к директории, где находится файл database.ini
            $envPath = __DIR__ . '/../';
            
            // Загружаем параметры подключения из файла database.ini
            $iniFilePath = $envPath . 'database.ini';
            if (!is_readable($iniFilePath)) {
                throw new \RuntimeException('Не удалось прочитать файл database.ini');
            }

            $config = parse_ini_file($iniFilePath, true);
            $env = $_ENV['APP_ENV'] ?? 'development';

            if (!isset($config[$env])) {
                throw new \RuntimeException("Конфигурация для среды '{$env}' не найдена в файле database.ini");
            }

            $dbConfig = $config[$env];

            $host = $dbConfig['host'] ?? null;
            $port = $dbConfig['port'] ?? null;
            $dbname = $dbConfig['database'] ?? null;
            $username = $dbConfig['user'] ?? null;
            $password = $dbConfig['password'] ?? null;

            // Проверяем наличие всех необходимых параметров для подключения к БД
            if (!$host || !$port || !$dbname || !$username || !$password) {
                throw new \RuntimeException('Не удалось получить данные для подключения к БД');
            }

            // Формируем строку подключения
            $conStr = "pgsql:host=$host;port=$port;dbname=$dbname";

            // Создаем объект PDO для подключения к БД
            $pdo = new \PDO($conStr, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;

        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }
}
