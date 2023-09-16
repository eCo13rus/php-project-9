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
        // Подгрузка переменных окружения из .env файла для локальной среды
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
        }

        $host = $_ENV['PGHOST'];
        $port = $_ENV['PGPORT'];
        $dbname = $_ENV['PGDATABASE'];
        $username = $_ENV['PGUSER'];
        $password = $_ENV['PGPASSWORD'];

        // Проверка наличия всех необходимых параметров для подключения к БД
        if (!$host || !$port || !$dbname || !$username || !$password) {
            throw new \RuntimeException('Не удалось получить данные для подключения к БД');
        }

        // Формирование строки подключения
        $conStr = "pgsql:host=$host;port=$port;dbname=$dbname";

        // Создание объекта PDO для подключения к БД
        $pdo = new \PDO($conStr, $username, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;

    } catch (\Exception $e) {
        error_log($e->getMessage());
        throw $e;
    }
}

}
