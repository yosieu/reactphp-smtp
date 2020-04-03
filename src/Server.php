<?php


namespace Yosieu\React\Smtp;


use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use Yosieu\React\Smtp\Io\MiddlewareRunner;
use React\Socket\ServerInterface;
use Yosieu\React\Smtp\Io\SMTPConnector;

final class Server extends EventEmitter
{


    /**
     * @var array|callable|MiddlewareRunner
     */
    private MiddlewareRunner $callback;


    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;


    /**
     * Server constructor.
     * @param $requestHandler
     * @param LoopInterface $loop
     */
    public function __construct($requestHandler, LoopInterface $loop)
    {
        if (!\is_callable($requestHandler) && !\is_array($requestHandler)) {
            throw new \InvalidArgumentException('Invalid request handler given');
        } elseif (!\is_callable($requestHandler)) {
            $requestHandler = new MiddlewareRunner($requestHandler);
        }

        $this->callback = $requestHandler;
        $this->loop = $loop;

    }

    /**
     * @param ServerInterface $socket
     */
    public function listen(ServerInterface $socket) : void
    {

        $connector = new SMTPConnector($this->loop, $socket);

        $that = $this;
        $connector->on('request', function(SMTPRequest $request, SMTPConnector $connector) use ($that) {
            $that->handleRequest($request, $connector);
        });

        $connector->on('debug', function($chunk){
            $stdout = fopen('php://stdout', 'w');
            fwrite($stdout, '[ RAW DATA ]' . $chunk);
            fclose($stdout);
        });

        $socket->on('connection', [$connector, 'handle']);

    }


    /**
     * @param SMTPRequest $request
     * @throws \Throwable
     */
    public function handleRequest(SMTPRequest $request) {

        $callback = $this->callback;

        try {
            $callback($request);
        }catch (\Throwable $e) {
            //TODO: exception solve
            throw $e;
        }

    }

}