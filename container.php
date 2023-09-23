<?php

use DI\Container;
use Hexlet\Code\Connection;

$container = new Container();

$container->set('db', function () {
    return Connection::connect();
});

return $container;

