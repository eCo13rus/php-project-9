<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use DI\Container;
use Hexlet\Code\Connection;
use Hexlet\Code\DbRepository;
use Valitron\Validator;
use Carbon\Carbon;

session_start();

ini_set('error_log', __DIR__ . '/error.log');

$container = new Container();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new Messages();
});

$container->set('db', function () {
    return Connection::connect();
});



$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

DbRepository::createTable($container->get('db'));

// Главная
$app->get('/', function ($requestuest, $response) {
    $url = '';
    return $this->get('renderer')->render($response, 'index.phtml', ['url' => $url]);
})->setName('main');




//Маршрут для добавление и проверки Url
$app->post('/urls', function ($request, $response) use ($router) {
    $db = $this->get('db');

    // Получение URL из запроса
    $urlData = $request->getParsedBodyParam('url');

    // Проверка на пустой URL
    if (empty($urlData['name'])) {
        $params = [
            'errors' => ['name' => ['URL не должен быть пустым']],
            'url' => ''
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
    }

    // Валидация URL
    $validator = new Validator($urlData);
    $validator->rule('lengthMax', 'name', 255)->message('URL слишком длинный');
    $validator->rule('url', 'name')->message('Некорректный URL');

    if ($validator->validate()) {
        // Парсинг URL для получения схемы и хоста
        $parsedUrl = parse_url($urlData['name']);
        $url = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

        try {
            // Проверка наличия URL в БД и получение его ID
            $statement = $db->prepare("SELECT id FROM urls WHERE name = :name;");
            $statement->execute(['name' => $url]);
            $existingUrlId = $statement->fetch(\PDO::FETCH_COLUMN);

            if ($existingUrlId) {
                $this->get('flash')->addMessage('success', 'Страница уже существует');
            } else {
                // Добавление нового URL в БД
                $statement = $db->prepare('INSERT INTO urls (name, created_at) VALUES (:name, :created_at)');
                $statement->execute([
                    'name' => $url,
                    'created_at' => Carbon::now()
                ]);

                $this->get('flash')->addMessage('success', 'Страница успешно добавлена');

                // Получение ID нового URL
                $existingUrlId = $db->lastInsertId();
            }

            // Перенаправление на страницу URL по ID
            $urlRoute = $router->urlFor('url', ['id' => $existingUrlId]);
            return $response->withRedirect($urlRoute);
        } catch (PDOException $e) {
            // Логирование ошибки и отображение сообщения пользователю
            error_log($e->getMessage());
            $params = ['error' => 'Произошла ошибка при работе с базой данных.'];
            return $this->get('renderer')->render($response->withStatus(500), 'index.phtml', $params);
        }
    }

    // Отображение ошибок валидации
    $params = [
        'errors' => $validator->errors(),
        'url' => $urlData['name'] ?? '',
        'flashMessages' => $this->get('flash')->getMessages(),
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
})->setName('add_url');



// Маршрут для отображение списка всех URL-адресов
$app->get('/urls', function ($request, $response) {
    $db = $this->get('db');

    try {
        // SQL-запрос для получения данных URL и последней проверки (если она была проведена)
        // Используется LEFT JOIN для объединения таблиц urls и url_checks по полю id/ url_id
        // Используется функция MAX для получения последней даты проверки
        $sql = "
            SELECT urls.name, urls.id, MAX(url_checks.created_at) AS created_at, url_checks.status_code 
            FROM urls 
            LEFT JOIN url_checks ON urls.id = url_checks.url_id
            GROUP BY (urls.name, urls.id, url_checks.status_code)
            ORDER BY id DESC;
        ";
        $statement = $db->query($sql);

        // Получение всех данных из базы данных
        $urlsData = $statement->fetchAll();
    } catch (PDOException $e) {
        // Логирование ошибки в случае неудачи
        error_log($e->getMessage());

        // Подготовка параметров с сообщением об ошибке для отображения пользователю
        $params = ['error' => 'Не удалось получить данные о URL.'];
        return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
    }

    // Подготовка параметров для отображения: либо данные URL, либо сообщение о том, что данных нет
    $params = $urlsData ? ['urls' => $urlsData] : ['message' => 'Нет данных для отображения'];

    // Рендеринг шаблона с передачей подготовленных параметров
    return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
})->setName('urls');




/**
 * Обработчик маршрута для отображения детальной информации о URL по его ID.
 * Маршрут принимает ID URL как параметр и извлекает соответствующую информацию из базы данных.
 * Также извлекаются все связанные проверки URL и текущие флэш-сообщения для передачи в шаблон.
 */
$app->get('/urls/{id}', function ($request, $response, array $args) {
    // Получаем ID URL из параметров маршрута
    $id = $args['id'];

    // Получаем объект базы данных из контейнера зависимостей
    $db = $this->get('db');

    // Формируем и выполняем SQL-запрос для получения информации о URL по ID
    // ПРИМЕЧАНИЕ: Этот код подвержен SQL-инъекциям, нужно использовать подготовленные запросы для защиты от них
    $statement = $db->query("SELECT * FROM urls WHERE id = $id;");
    $url = $statement->fetch();

    // Формируем и выполняем SQL-запрос для получения всех проверок URL по ID
    // ПРИМЕЧАНИЕ: Этот код подвержен SQL-инъекциям, нужно использовать подготовленные запросы для защиты от них
    $statement = $db->query("SELECT * FROM url_checks WHERE url_id = $id ORDER BY id DESC;");
    $urlChecks = $statement->fetchAll();

    // Получаем флэш-сообщения для отображения пользователю
    $messages = $this->get('flash')->getMessages();

    // Формируем параметры для передачи в шаблон
    $params = [
        'url' => $url,
        'flash' => $messages,
        'checks' => $urlChecks
    ];

    // Рендерим шаблон с передачей параметров и возвращаем результат как ответ
    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
})->setName('url'); // Устанавливаем имя маршрута




// Обработчик POST-запроса для создания новой проверки URL.
$app->post('/urls/{url_id}/checks', function ( $request,  $response, array $args) use ($router) {
    // Получаем ID URL из параметров маршрута
    $id = $args['url_id'];

    // Получаем объект базы данных из контейнера зависимостей
    $db = $this->get('db');

    // Подготавливаем SQL-запрос для добавления новой проверки URL
    $sql = "INSERT INTO url_checks (url_id, created_at) VALUES (:url_id, :created_at)";
    $sqlReqvest = $db->prepare($sql);

    try {
        // Пытаемся выполнить SQL-запрос с текущей датой и временем
        $sqlReqvest->execute([
            'url_id' => $id,
            'created_at' => Carbon::now()->toDateTimeString()
        ]);

        // Добавляем флэш-сообщение об успешной проверке
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (Exception $e) {
        // Логируем ошибку и добавляем флэш-сообщение об ошибке
        error_log($e->getMessage());
        $this->get('flash')->addMessage('error', 'Произошла ошибка при создании проверки');
    }

    // Формируем URL для редиректа и перенаправляем пользователя на страницу URL
    $urlRout = $router->urlFor('url', ['id' => $id]);
    return $response->withRedirect($urlRout);
})->setName('url_check_create');





$app->run();
