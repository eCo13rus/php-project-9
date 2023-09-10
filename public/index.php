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
use DiDom\Document;
use GuzzleHttp\Client;

session_start();

$container = new Container();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = [
        'errors' => $messages['error'] ?? [],
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('main');

$app->run();
