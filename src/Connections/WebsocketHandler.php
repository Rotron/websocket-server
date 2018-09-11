<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.09.18
 * Time: 10:52
 */

namespace TS\Websockets\Connections;


use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\DataInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\Socket\ConnectionInterface;
use TS\Websockets\Websocket;


/**
 *
 * Treats a TCP connection as a Websocket connection.
 *
 */
class WebsocketHandler
{


    /** @var MessageBuffer */
    protected $buffer;

    /** @var ConnectionInterface */
    protected $tcpConnection;

    /** @var Websocket */
    protected $webSocket;

    protected $closed = false;
    protected $closing = false;


    public function __construct(
        ServerRequestInterface $request,
        ConnectionInterface $tcpConnection,
        CloseFrameChecker $closeFrameChecker,
        callable $exceptionFactory = null
    )
    {
        $this->webSocket = new Websocket($this, $request);

        $this->tcpConnection = $tcpConnection;
        $tcpConnection->on('data', [$this, 'onTcpData']);
        $tcpConnection->on('error', [$this, 'onTcpError']);
        $tcpConnection->once('close', [$this, 'onTcpClose']);
        $tcpConnection->once('end', [$this, 'onTcpEnd']);

        $this->buffer = new MessageBuffer(
            $closeFrameChecker,
            [$this, 'onMessage'],
            [$this, 'onControlFrame'],
            $exceptionFactory
        );
    }


    public function getWebSocket(): Websocket
    {
        return $this->webSocket;
    }


    public function send(string $payload, bool $binary): void
    {
        if ($this->closed) {
            throw new \BadMethodCallException('Cannot send data, socket is closed.');
        }
        if ($this->closing) {
            throw new \BadMethodCallException('Cannot send data, socket is closing.');
        }
        $op = $binary ? Frame::OP_BINARY : Frame::OP_TEXT;
        $frame = $this->buffer->newFrame($payload, true, $op);
        $this->tcpConnection->write($frame->getContents());
    }


    public function sendData(DataInterface $data): void
    {
        if ($this->closed) {
            throw new \BadMethodCallException('Cannot send data, socket is closed.');
        }
        if ($this->closing) {
            throw new \BadMethodCallException('Cannot send data, socket is closing.');
        }
        $this->tcpConnection->write($data->getContents());
    }


    /*
    public function sendPing($payload): void
    {
        $frame = $this->buffer->newFrame($payload, true, Frame::OP_PING);
        $this->tcpConnection->write($frame->getContents());
    }
    */


    public function startClose(int $code): void
    {
        if ($this->closed) {
            throw new \BadMethodCallException('Already closed.');
        }
        if ($this->closing) {
            throw new \BadMethodCallException('Already closing.');
        }

        $this->closing = true;

        $frame = $this->buffer->newCloseFrame($code);
        $this->tcpConnection->write($frame->getContents());

        $this->webSocket->emit('close');
    }


    public function isClosed(): bool
    {
        return $this->closed;
    }


    public function isClosing(): bool
    {
        return $this->closing;
    }


    protected function setClosed(): void
    {
        $this->closing = false;
        $this->closed = true;
        $this->tcpConnection->removeListener('error', [$this, 'onTcpError']);
        $this->tcpConnection->removeListener('data', [$this, 'onTcpData']);
        $this->tcpConnection->removeListener('close', [$this, 'onTcpClose']);
        $this->tcpConnection->removeListener('end', [$this, 'onTcpEnd']);
    }


    public function onTcpData($data): void
    {
        $this->buffer->onData($data);
    }


    public function onTcpError(\Throwable $throwable): void
    {
        $this->webSocket->emit('error', [$throwable]);
        $this->setClosed();
    }


    public function onTcpEnd(): void
    {
        if (!$this->closed && !$this->closing) {
            $this->webSocket->emit(new \RuntimeException('TCP connection ended without close.'));
        }
        $this->setClosed();
    }


    public function onTcpClose(): void
    {
        if (!$this->closed && !$this->closing) {
            $this->webSocket->emit(new \RuntimeException('TCP connection closed without close.'));
        }
        $this->setClosed();
    }


    public function onMessage(MessageInterface $message): void
    {
        if ($this->closing) {
            // There should not be any messages at this point
            return;
        }
        $payload = $message->getPayload();
        $binary = $message->isBinary();
        $this->webSocket->emit('message', [$payload, $binary]);
    }


    public function onControlFrame(FrameInterface $frame): void
    {
        switch ($frame->getOpCode()) {

            case Frame::OP_CLOSE:

                // The ratchet MessageBuffer has already processed the close!

                $this->closing = true;
                $this->tcpConnection->end($frame->getContents());

                break;

            case Frame::OP_PING:

                // TODO only if previously sent a ping!

                $frame = $this->buffer->newFrame($frame->getPayload(), true, Frame::OP_PONG);
                $this->tcpConnection->write($frame->getContents());

                break;

            case Frame::OP_PONG:

                // TODO
                //var_dump("connMan got pong");

                //$pongReceiver = $this->pongReceiver;
                //$pongReceiver($frame, $conn);
                break;
        }
    }


}