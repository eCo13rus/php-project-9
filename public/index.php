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
        $sql = "
            SELECT urls.name, urls.id, MAX(url_checks.created_at) AS created_at, url_checks.status_code 
            FROM urls 
            LEFT JOIN url_checks ON urls.id = url_checks.url_id
            GROUP BY (urls.name, urls.id, url_checks.status_code)
            ORDER BY id DESC;
        ";
        $statement = $db->query($sql);

        $urlsData = $statement->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());

        $params = ['error' => 'Не удалось получить данные о URL.'];
        return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
    }

    $params = $urlsData ? ['urls' => $urlsData] : ['message' => 'Нет данных для отображения'];

    return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
})->setName('urls');



$app->get('/urls/{id}', function ($request, $res, array $args) {
    $id = $args['id'];
    $db = $this->get('db');

    $statement = $db->prepare("SELECT * FROM urls WHERE id = :id;");
    $statement->bindValue(':id', $id, \PDO::PARAM_INT);
    $statement->execute();
    $url = $statement->fetch();

    $messages = $this->get('flash')->getMessages();
    $params = [
        'url' => $url,
        'flash' => $messages,
    ];

    return $this->get('renderer')->render($res, 'urls/show.phtml', $params);
})->setName('url');

$app->run();
