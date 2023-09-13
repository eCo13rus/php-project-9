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
        
        // Проверяем, доступен ли файл .env для чтения
        $envFilePath = $envPath . '.env';
        if (is_readable($envFilePath)) {
            // Загружаем переменные окружения из файла .env
            $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
            $dotenv->load();
        }

        // Получаем текущую среду (development или production)
        $env = getenv('APP_ENV') ?: 'development';

        // Получаем параметры подключения из переменных окружения
        $username = $_ENV['PGUSER'] ?? null;
        $password = $_ENV['PGPASSWORD'] ?? null;
        $host = $_ENV['PGHOST'] ?? null;
        $port = $_ENV['PGPORT'] ?? null;
        $dbname = $_ENV['PGDATABASE'] ?? null;
       
        // Проверяем наличие всех необходимых параметров
        if (!$username || !$password || !$host || !$port || !$dbname) {
            throw new \RuntimeException('Не удалось получить данные для подключения к БД');
        }

        // Формируем строку подключения
        $conStr = "pgsql:host=$host;port=$port;dbname=$dbname";

        // Создаем объект PDO для подключения к БД
        $pdo = new \PDO($conStr, $username, $password);
        return $pdo;
    } catch (\Exception $e) {
        error_log($e->getMessage());
        throw $e;
    }
}


}
