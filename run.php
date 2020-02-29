<?php
require_once __DIR__ . '/vendor/autoload.php';

define('__ROOT__', __DIR__);


$loop = \React\EventLoop\Factory::create();

$logger = new \Monolog\Logger('logger');
$logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
$logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/log/log.log'));

$server = new \Yosieu\React\Smtp\Server([
                    new \Yosieu\React\Smtp\Middleware\SMTPStdOutMiddleWare()
            ], $loop);

//$socket = new \React\Socket\Server('tls://127.0.0.1:8725', $loop);
$socket = new \Yosieu\React\Smtp\SMTPSocketServer(8725, $loop);
$server->listen($socket);

$logger->debug('SERVER RUNNING ...');

$loop->run();