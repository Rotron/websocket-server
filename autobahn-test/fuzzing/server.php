<?php

use TS\Websockets\WebsocketServer;
use TS\Websockets\ControllerInterface;
use TS\Websockets\Websocket;

require_once __DIR__ . '/../../vendor/autoload.php';


$port = $argc > 1 ? $argv[1] : 8000;
$impl = sprintf('React\EventLoop\%sLoop', $argc > 2 ? $argv[2] : 'StreamSelect');

$loop = new $impl;

$server = new WebsocketServer($loop, [
    'uri' => '127.0.0.1:' . $port
]);

print 'Websocket server listening on ' . $server->getAddress() . PHP_EOL;

$server->addRoute('*', new class implements ControllerInterface {
    function onMessage(Websocket $from, string $payload, bool $binary): void
    {
        $from->send($payload, $binary);
    }
    function onOpen(Websocket $connection): void {}
    function onClose(Websocket $connection): void {}
    function onError(Websocket $connection, \Throwable $error): void {}
});

//$wsServer = new Ratchet\WebSocket\WsServer(new BinaryEcho);
// This is enabled to test https://github.com/ratchetphp/Ratchet/issues/430
// The time is left at 10 minutes so that it will not try to every ping anything
// This causes the Ratchet server to crash on test 2.7
//$wsServer->enableKeepAlive($loop, 600);

//$app = new Ratchet\Http\HttpServer($wsServer);

//$server = new Ratchet\Server\IoServer($app, $sock, $loop);

$loop->run();