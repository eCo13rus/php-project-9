<?php

namespace Hexlet\Code;

class DbRepository
{
    /**
     * Создает таблицу в базе данных, если она еще не существует.
     *
     * @param \PDO $pdo Объект PDO для подключения к базе данных.
     * @throws \RuntimeException Если произошла ошибка при чтении файла или выполнении запроса к БД.
     */
    public static function createTable(\PDO $pdo): void
    {
        try {
            // Читаем SQL-запрос для создания таблицы из файла
            $data = file_get_contents('../database.sql');
            if ($data === false) {
                throw new \RuntimeException('Не удалось считать файл database.sql');
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Произошла ошибка при чтении файла: ' . $e->getMessage());
        }        

        // Подготавливаем и выполняем SQL-запрос для проверки существования таблицы 'urls' в БД
        $namesTables = $pdo->prepare("SELECT table_name
            FROM information_schema.tables
            WHERE table_schema='public'
            AND table_type='BASE TABLE'
            AND table_name='urls';");               
        $namesTables->execute();

        // Проверяем, существует ли таблица 'urls'
        $isCreatingTables = empty($namesTables->fetchAll(\PDO::FETCH_COLUMN, 0));

        // Если таблица 'urls' не существует, создаем ее, выполняя SQL-запрос из файла
        if ($isCreatingTables) {
            $pdo->exec($data);
        }
    }
}
