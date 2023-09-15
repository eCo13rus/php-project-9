<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use DI\Container;
use Hexlet\Code\Connection;
use Hexlet\Code\DbTableCreator ;
use Valitron\Validator;
use Carbon\Carbon;
use DiDom\Document;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

session_start();

ini_set('error_log', __DIR__ . '/error.log');

$container = new Container();

// Регистрация сервисов
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

DbTableCreator::createTables($container->get('db'));

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
$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router) {
    // Получаем ID URL из параметров маршрута
    $id = (int)$args['url_id'];

    // Получаем объект PDO для взаимодействия с БД
    $db = $this->get('db');

    // Подготавливаем и выполняем запрос для получения имени URL по ID
    $statement = $db->prepare("SELECT name FROM urls WHERE id = :id");
    $statement->execute(['id' => $id]);
    $urlName = $statement->fetch(\PDO::FETCH_COLUMN);

    // Если URL не найден, отправляем сообщение об ошибке и перенаправляем пользователя
    if (!$urlName) {
        $this->get('flash')->addMessage('error', 'URL не найден');
        return $response->withRedirect($router->urlFor('urls'));
    }

    // Подготавливаем SQL запрос для вставки данных проверки в БД
    $sql = "INSERT INTO url_checks (url_id, status_code, h1, title, description, created_at) 
            VALUES (:url_id, :status_code, :h1, :title, :description, :created_at)";
    $sqlRequest = $db->prepare($sql);

    try {
        // Создаем новый HTTP клиент и отправляем GET запрос к URL для получения содержимого страницы
        $client = new Client();
        $httpResponse  = $client->request('GET', $urlName);

        // Получаем HTTP статус код ответа и тело ответа
        $statusCode = $httpResponse ->getStatusCode();
        $body = (string)$httpResponse ->getBody();

        // Парсим тело ответа для извлечения данных SEO анализа
        $document = new Document($body);
        $h1 = getTagContent($document, 'h1');
        $title = getTagContent($document, 'title');
        $description = getTagContent($document, 'meta[name=description]', 'content');

        // Выполняем SQL запрос для сохранения данных проверки в БД
        $sqlRequest->execute([
            'url_id' => $id,
            'status_code' => $statusCode,
            'h1' => $h1,
            'title' => $title,
            'description' => $description,
            'created_at' => Carbon::now()->toDateTimeString(),
        ]);

        // Сообщаем пользователю об успешной проверке
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (RequestException $e) {
        // Обрабатываем исключения, возникшие при отправке HTTP запроса
        $this->get('flash')->addMessage('error', 'Ошибка при проверке страницы: ' . $e->getMessage());
        return $response->withRedirect($router->urlFor('url', ['id' => $id]));
    } catch (Exception $e) {
        // Обрабатываем все остальные исключения
        $this->get('flash')->addMessage('error', 'Неожиданная ошибка: ' . $e->getMessage());
    }

    // Перенаправляем пользователя на страницу с деталями URL после завершения проверки
    return $response->withRedirect($router->urlFor('url', ['id' => $id]));
})->setName('url_check_create');


$app->run();
