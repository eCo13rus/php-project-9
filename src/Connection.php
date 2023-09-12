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
            // Получаем текущую среду (development или production)
            $env = getenv('APP_ENV') ?: 'production';

            // Загружаем конфигурацию базы данных
            $dbConfig = parse_ini_file(__DIR__ . '/database.ini', true);

            // Проверяем наличие конфигурации для текущей среды
            if (!isset($dbConfig[$env])) {
                throw new \RuntimeException('Не найдена конфигурация БД для среды: ' . $env);
            }

            $config = $dbConfig[$env];

            // Получаем параметры подключения
            $username = $config['user'] ?? null;
            $password = $config['password'] ?? null;
            $host = $config['host'] ?? null;
            $dbname = $config['database'] ?? null;

            // Проверяем наличие всех необходимых параметров
            if (!$username || !$host || !$dbname) {
                throw new \RuntimeException('Не удалось получить данные для подключения к БД');
            }

            // Формируем строку подключения
            $conStr = "pgsql:host=$host;dbname=$dbname";

            // Создаем объект PDO для подключения к БД
            $pdo = new \PDO($conStr, $username, $password);
            return $pdo;

        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }    
}


