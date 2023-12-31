<?php

require_once __DIR__ . '/../vendor/autoload.php';

use function Helpers\getTagContent;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use Valitron\Validator;
use Carbon\Carbon;
use DiDom\Document;
use FastRoute\Route;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

session_start();

ini_set('error_log', __DIR__ . '/error.log');

// Подключение и инициализация DI-контейнера из файла container.php
$container = require_once __DIR__ . '/../src/container.php';

// Регистрация дополнительных сервисов
$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$routeParser = $app->getRouteCollector()->getRouteParser();

// Главная
$app->get('/', function (Request $request, Response $response) use ($routeParser) {
    $params = [
        'routeParser' => $routeParser,
        'currentRoute' => 'main'
    ];
    $content = $this->get('renderer')->fetch('index.phtml', $params);
    return $this->get('renderer')->render($response, 'layout.phtml', ['content' => $content] + $params);
})->setName('main');



//Маршрут для добавление и проверки Url
$app->post('/urls', function ($request, Response $response) use ($routeParser) {
    $db = $this->get('db');

    $urlData = $request->getParsedBody()['url'] ?? null;

    if (empty($urlData['name'])) {
        $params = [
            'errors' => ['name' => ['URL не должен быть пустым']],
            'url' => '',
            'routeParser' => $routeParser
        ];
        $content = $this->get('renderer')->fetch('index.phtml', $params);
        return $this->get('renderer')->render(
            $response->withStatus(422),
            'layout.phtml',
            ['content' => $content] + $params
        );
    }

    $urlData['name'] = strtolower($urlData['name']);

    $validator = new Validator($urlData);
    $validator->rule('lengthMax', 'name', 255)->message('URL слишком длинный');
    $validator->rule('url', 'name')->message('Некорректный URL');

    if (!$validator->validate()) {
        $params = [
            'errors' => $validator->errors(),
            'url' => $urlData['name'] ?? '',
            'flashMessages' => $this->get('flash')->getMessages(),
            'routeParser' => $routeParser
        ];
        $content = $this->get('renderer')->fetch('index.phtml', $params);
        return $this->get('renderer')->render(
            $response->withStatus(422),
            'layout.phtml',
            ['content' => $content] + $params
        );
    }

    $parsedUrl = parse_url($urlData['name']);
    $url = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

    try {
        // Проверяем, существует ли URL в базе данных
        $statement = $db->prepare("SELECT id FROM urls WHERE name = :name;");
        $statement->execute(['name' => $url]);
        $existingUrlId = $statement->fetch(\PDO::FETCH_COLUMN);

        if ($existingUrlId) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
        } else {
            $statement = $db->prepare('INSERT INTO urls (name, created_at) VALUES (:name, :created_at)');
            $statement->execute([
                'name' => $url,
                'created_at' => Carbon::now(),
            ]);

            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            $existingUrlId = $db->lastInsertId();
        }

        $urlRoute = $routeParser->urlFor('urls.show', ['id' => $existingUrlId]);
        return $response->withHeader('Location', $urlRoute)->withStatus(302);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $params = ['error' => 'Произошла ошибка при работе с базой данных.', 'routeParser' => $routeParser];

        $content = $this->get('renderer')->fetch('show.phtml', $params);

        return $this->get('renderer')->render(
            $response->withStatus(500),
            'layout.phtml',
            ['content' => $content] + $params
        );
    }
})->setName('urls.store');



// Маршрут для отображения списка всех URL-адресов с датой последней проверки (если она была проведена)
$app->get('/urls', function ($request, Response $response) use ($routeParser) {
    $db = $this->get('db');

    try {
        // SQL-запрос для получения данных URL и последней проверки (если она была проведена)
        // Используется LEFT JOIN для объединения таблиц urls и url_checks по полю id/ url_id
        $sql = "
            SELECT 
            urls.name, 
            urls.id, 
            url_checks.created_at, 
            url_checks.status_code 
        FROM 
            urls 
        LEFT JOIN 
            url_checks ON urls.id = url_checks.url_id 
        WHERE 
            url_checks.created_at = (SELECT MAX(created_at) FROM url_checks WHERE url_id = urls.id) 
            OR url_checks.created_at IS NULL
        GROUP BY 
            (urls.name, urls.id, url_checks.created_at, url_checks.status_code)
        ORDER BY 
            id DESC;
        ";
        $statement = $db->query($sql);

        // Получение всех данных из базы данных
        $urlsDataArray = $statement->fetchAll(\PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());

        $params = ['error' => 'Не удалось получить данные о URL.'];
        return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
    }

    $params = $urlsDataArray ? [
        'urls' => $urlsDataArray, 'routeParser' => $routeParser,
        'currentRoute' => 'urls.index'
    ] :
        ['message' => 'Нет данных для отображения', 'routeParser' => $routeParser, 'currentRoute' => 'urls.index'];

    $content = $this->get('renderer')->fetch('urls/show_urls.phtml', $params);

    return $this->get('renderer')->render(
        $response,
        'layout.phtml',
        ['content' => $content] + $params
    );
})->setName('urls.index');



// Обработчик маршрута для отображения детальной информации о URL по его ID.
$app->get('/urls/{id}', function (Request $request, Response $response, array $args) use ($routeParser) {
    $id = (int)$args['id'];
    $db = $this->get('db');

    try {
        $statement = $db->prepare('SELECT * FROM urls WHERE id = :id');
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $url = $statement->fetch();

        if (!$url) {
            $response->getBody()->write('URL не найден');
            return $response->withStatus(404);
        }

        $statement = $db->prepare('SELECT * FROM url_checks WHERE url_id = :url_id ORDER BY id DESC');
        $statement->bindParam(':url_id', $id, PDO::PARAM_INT);
        $statement->execute();
        $urlChecks = $statement->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $response->getBody()->write('Ошибка при работе с базой данных');
        return $response->withStatus(500);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'url' => $url,
        'flash' => $messages,
        'checks' => $urlChecks,
        'routeParser' => $routeParser
    ];
    $content = $this->get('renderer')->fetch('urls/show.phtml', $params);

    return $this->get('renderer')->render(
        $response,
        'layout.phtml',
        ['content' => $content] + $params
    );
})->setName('urls.show');



// Обработчик POST-запроса для создания новой проверки URL.
$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($routeParser) {

    $id = (int)$args['url_id'];
    $db = $this->get('db');

    $statement = $db->prepare("SELECT name FROM urls WHERE id = :id");
    $statement->execute(['id' => $id]);
    $urlToCheck = $statement->fetch(\PDO::FETCH_COLUMN);

    if (!$urlToCheck) {
        $this->get('flash')->addMessage('error', 'URL не найден');
        return $response->withRedirect($routeParser->urlFor('urls'));
    }

    $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at) 
            VALUES (:url_id, :status_code, :h1, :title, :description, :created_at)";
    $insertCheckStmt = $db->prepare($sql);

    try {
        $client = new Client();
        try {
            $responseFromUrl = $client->request('GET', $urlToCheck);
        } catch (RequestException $e) {
            $responseFromUrl = $e->getResponse();
            if (is_null($responseFromUrl)) {
                $this->get('flash')->addMessage('error', 'Ошибка при проверке страницы: ' . $e->getMessage());
                return $response->withRedirect($routeParser->urlFor('url', ['id' => (string) $id]));
            }
        }

        $statusCode = $responseFromUrl->getStatusCode();

        if ($statusCode === 500) {
            $params = ['errorMessage' => 'Ошибка 500. Что-то пошло не так.'];
            $content = $this->get('renderer')->fetch('urls/error.phtml', $params);
            return $this->get('renderer')->render($response, 'layout.phtml', ['content' => $content] + $params);
        }

        $body = (string)$responseFromUrl->getBody();
        if (empty($body)) {
            $this->get('flash')->addMessage('error', 'Получен пустой ответ от сервера');
            return $response->withRedirect($routeParser->urlFor('urls.show', ['id' => (string)$id]));
        }

        $document = new Document($body);
        $h1 = getTagContent($document, 'h1');
        $title = getTagContent($document, 'title');
        $description = getTagContent($document, 'meta[name=description]', 'content');

        $insertCheckStmt->execute([
            'url_id' => $id,
            'status_code' => $statusCode,
            'h1' => $h1,
            'title' => $title,
            'description' => $description ?? null,
            'created_at' => Carbon::now(),
        ]);

        if ($statusCode >= 400) {
            $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил с ошибкой');
        } else {
            $this->get('flash')->addMessage('success', 'Страница успешно проверена');
        }
    } catch (Exception $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
    }

    return $response->withRedirect($routeParser->urlFor('urls.show', ['id' => (string)$id]));
})->setName('urls.checks');

$app->run();
