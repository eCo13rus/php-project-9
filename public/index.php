<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    return $renderer->render($response, "index.phtml");
})->setName('home');

$app->run();
