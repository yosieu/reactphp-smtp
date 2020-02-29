<?php

namespace Yosieu\React\Smtp;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use Exception;
use React\Socket\ServerInterface;
use React\Socket\UnixServer;
use React\Socket\TcpServer;
use React\Socket\SecureServer;
use React\Socket\ConnectionInterface;

final class SMTPSocketServer extends EventEmitter implements ServerInterface
{
    /**
     * @var SecureServer|ServerInterface|TcpServer|UnixServer
     */
    private ServerInterface $server;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var array
     */
    private array $context;

    /**
     * SMTPSocketServer constructor.
     * @param $uri
     * @param LoopInterface $loop
     * @param array $context
     */
    public function __construct($uri, LoopInterface $loop, array $context = array())
    {
        // sanitize TCP context options if not properly wrapped
        if ($context && (!isset($context['tcp']) && !isset($context['tls']) && !isset($context['unix']))) {
            $context = array('tcp' => $context);
        }

        // apply default options if not explicitly given
        $context += array(
            'tcp' => array(),
            'tls' => array(),
            'unix' => array()
        );

        $scheme = 'tcp';
        $pos = \strpos($uri, '://');
        if ($pos !== false) {
            $scheme = \substr($uri, 0, $pos);
        }

        if ($scheme === 'unix') {
            $server = new UnixServer($uri, $loop, $context['unix']);
        } else {
            $server = new TcpServer(str_replace('tls://', '', $uri), $loop, $context['tcp']);

            if ($scheme === 'tls') {
                $server = new SecureServer($server, $loop, $context['tls']);
            }
        }

        $this->server = $server;
        $this->loop = $loop;
        $this->context = $context;

        $that = $this;
        $server->on('connection', function (ConnectionInterface $conn) use ($that) {
            $that->emit('connection', array($conn));
        });
        $server->on('error', function (Exception $error) use ($that) {
            $that->emit('error', array($error));
        });
    }

    /**
     * @return string|string[]|null
     */
    public function getAddress()
    {
        return $this->server->getAddress();
    }

    /**
     *
     */
    public function pause() : void
    {
        $this->server->pause();
    }

    /**
     *
     */
    public function resume() : void
    {
        $this->server->resume();
    }

    /**
     *
     */
    public function close() : void
    {
        $this->server->close();
    }
}
