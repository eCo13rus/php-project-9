<?php

namespace Hexlet\Code;

class DbTableCreator
{
    /**
     * Создает таблицу в базе данных, если она еще не существует.
     *
     * @param \PDO $pdo Объект PDO для подключения к базе данных.
     * @throws \RuntimeException Если произошла ошибка при чтении файла или выполнении запроса к БД.
     */
    public static function createTables(\PDO $pdo): void
    {
        try {
            $data = file_get_contents(__DIR__ . '/../database.sql');
            if ($data === false) {
                throw new \RuntimeException('Не удалось считать файл database.sql');
            }

            $pdo->exec($data);
        } catch (\Exception $e) {
            throw new \RuntimeException('Произошла ошибка: ' . $e->getMessage());
        }
    }
}
