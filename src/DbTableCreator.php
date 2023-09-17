<?php

namespace Hexlet\Code;

class DbTableCreator
{
    /**
     * Создает таблицы в базе данных, если они еще не существуют.
     *
     * @param \PDO $pdo Объект PDO для подключения к базе данных.
     * @throws \RuntimeException Если произошла ошибка при чтении файла или выполнении запроса к БД.
     */
    public static function createTables(\PDO $pdo): void
    {
        try {
            $sqlFilePath = __DIR__ . '/../database.sql';
            
            $sqlQueries = file_get_contents($sqlFilePath);
            if ($sqlQueries === false) {
                throw new \RuntimeException('Не удалось считать файл ' . $sqlFilePath);
            }

            // Выполнение SQL запросов для создания таблиц
            $result = $pdo->exec($sqlQueries);

            // Проверка на наличие ошибок при выполнении запросов
            if ($result === false) {
                $errorInfo = $pdo->errorInfo();

                throw new \RuntimeException('Ошибка при создании таблиц: ' . $errorInfo[2]);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Произошла ошибка: ' . $e->getMessage());
        }
    }
}
