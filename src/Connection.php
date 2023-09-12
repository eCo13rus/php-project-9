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
            $envPath = __DIR__ . '/../';
            $envFilePath = $envPath . '.env';
            
            if (is_readable($envFilePath)) {
                $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
                $dotenv->load();
            }

            $env = getenv('APP_ENV') ?: 'production';

            $dbConfig = parse_ini_file(__DIR__ . '/database.ini', true);

            if (!isset($dbConfig[$env])) {
                throw new \RuntimeException('Не найдена конфигурация БД для среды: ' . $env);
            }

            $config = $dbConfig[$env];

            $username = $config['user'] ?? null;
            $password = $config['password'] ?? null;
            $host = $config['host'] ?? null;
            $dbname = $config['database'] ?? null;

            if (!$username || !$host || !$dbname) {
                throw new \RuntimeException('Не удалось получить данные для подключения к БД');
            }

            $conStr = "pgsql:host=$host;dbname=$dbname";
            $pdo = new \PDO($conStr, $username, $password);
            
            return $pdo;

        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }
}

