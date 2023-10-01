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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

session_start();

ini_set('error_log', __DIR__ . '/error.log');

// Подключение и инициализация DI-контейнера из файла container.php
$container = require_once __DIR__ . '/../container.php';

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
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
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
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
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
        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
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
        $params = ['error' => 'Произошла ошибка при работе с базой данных.'];
        return $this->get('renderer')->render($response->withStatus(500), 'index.phtml', $params);
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

    $params = $urlsDataArray ? ['urls' => $urlsDataArray, 'routeParser' => $routeParser] :
        ['message' => 'Нет данных для отображения', 'routeParser' => $routeParser];

    return $this->get('renderer')->render($response, 'urls/show_urls.phtml', $params);
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

    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
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
            // Пытаемся выполнить GET запрос к URL
            $responseFromUrl = $client->request('GET', $urlToCheck);
        } catch (RequestException $e) {
            // Если возникла ошибка, получаем ответ от сервера, если он есть
            $responseFromUrl = $e->getResponse();

            if (is_null($responseFromUrl)) {
                // Если ответа нет, добавляем сообщение об ошибке и перенаправляем пользователя
                $this->get('flash')->addMessage('error', 'Ошибка при проверке страницы: ' . $e->getMessage());
                return $response->withRedirect($routeParser->urlFor('url', ['id' => (string) $id]));
            }
        }

        $statusCode = $responseFromUrl->getStatusCode();
        $body = (string)$responseFromUrl->getBody();
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

    return $response->withRedirect($routeParser->urlFor('urls.show', ['id' => $id]));
})->setName('urls.checks');

$app->run();
