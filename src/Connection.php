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
            $dbConfig = self::getDbConfig();

            $conStr = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s",
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database']
            );

            $pdo = new \PDO($conStr, $dbConfig['user'], $dbConfig['password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $pdo;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    private static function getDbConfig(): array
    {
        $iniFilePath = __DIR__ . '/../database.ini';
        
        $iniFilePath = realpath(__DIR__ . '/../database.ini');
        if ($iniFilePath === false) {
            throw new \RuntimeException('Не удалось прочитать файл database.ini');
        }


        $config = parse_ini_file($iniFilePath, true);
        $env = $_ENV['APP_ENV'] ?? 'development';

        if (!isset($config[$env])) {
            throw new \RuntimeException("Конфигурация для среды '{$env}' не найдена в файле database.ini");
        }

        $dbConfig = $config[$env];

        if (!$dbConfig['host'] || !$dbConfig['port'] || !$dbConfig['database'] || !$dbConfig['user'] || !$dbConfig['password']) {
            throw new \RuntimeException('Не удалось получить данные для подключения к БД');
        }

        return $dbConfig;
    }

}
