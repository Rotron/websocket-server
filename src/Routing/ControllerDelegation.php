<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 14:41
 */

namespace TS\WebSockets\Routing;


use TS\WebSockets\ControllerInterface;
use TS\WebSockets\WebSocket;


abstract class ControllerDelegation
{

    protected $serverParams;

    /** @var ControllerInterface */
    protected $controller;

    /** @var \SplObjectStorage */
    protected $connections;

    /** @var callable */
    protected $errorHandler;


    public function __construct(array $serverParams, ControllerInterface $controller, \SplObjectStorage $controllerConnections, callable $errorHandler)
    {
        $this->serverParams = $serverParams;
        $this->controller = $controller;
        $this->connections = $controllerConnections;
        $this->errorHandler = $errorHandler;
    }


    protected function passMethodCallError(\Throwable $error, string $method, ... $args): void
    {
        $this->passError(ControllerException::methodCall($this->controller, $method, $args, $error));
    }


    protected function passError(\Throwable $error): void
    {
        $fn = $this->errorHandler;
        $fn($error);
    }


    public function onInit(): void
    {
    }


    /**
     * Will be called *after* the socket is added to the controller connections.
     *
     * @param WebSocket $websocket
     */
    public function onOpen(WebSocket $websocket): void
    {
    }


    /**
     * Will be called *before* the socket is removed from the controller connections.
     *
     * @param WebSocket $websocket
     */
    public function onClose(WebSocket $websocket): void
    {
    }


    public function onMessage(WebSocket $websocket, string $payload, bool $binary): void
    {
    }


    public function onError(WebSocket $websocket, \Throwable $error): void
    {
    }


}