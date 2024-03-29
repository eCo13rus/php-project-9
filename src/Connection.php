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
            // Определяем путь к директории, где находится файл .env
            $envPath = __DIR__ . '/../';

            // Проверяем, доступен ли файл .env для чтения и загружаем переменные окружения из файла .env,
            // если он доступен
            $envFilePath = $envPath . '.env';
            //var_dump(is_readable($envFilePath));
            if (is_readable($envFilePath)) {
                $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
                $dotenv->load();
                //var_dump($_ENV['DATABASE_URL']);

            }

            // Получаем строку подключения из переменной окружения DATABASE_URL
            $databaseUrl = parse_url($_ENV['DATABASE_URL'] ?? '');
            //var_dump($databaseUrl);

            // Распарсиваем строку подключения на составляющие
            $username = $databaseUrl['user'] ?? null;
            //var_dump($username);
            $password = isset($databaseUrl['pass']) ? urldecode($databaseUrl['pass']) : null;
            //var_dump($password);
            $host = $databaseUrl['host'] ?? null;
            //var_dump($host);
            $port = $databaseUrl['port'] ?? null;
            //var_dump($username);
            $dbName = ltrim($databaseUrl['path'] ?? '', '/');
            //var_dump($username);


            // Проверяем наличие всех необходимых параметров для подключения к БД
            if (!$username || !$password || !$host || !$port || !$dbName) {
                throw new \RuntimeException('Не удалось получить данные для подключения к БД');
            }

            // Формируем строку подключения
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";

            // Создаем объект PDO для подключения к БД
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $pdo;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }
}
