<?php


namespace Yosieu\React\Smtp\Io;

use Evenement\EventEmitter;
use Monolog\Logger;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Yosieu\React\Smtp\Authenticator;
use Yosieu\React\Smtp\SMTPRequest;
use Yosieu\React\Smtp\SMTPSocketServer;

class SMTPConnector extends EventEmitter
{

    const DELIMITER = "\r\n";

    /**
     * @var array
     */
    protected array $extendedCommands = [
        /*
         'AUTH PLAIN LOGIN',
        'STARTTLS',
        */
        'HELP',
    ];

    /**
     * @var string
     */
    protected string $dataBuffer = '';


    /**
     * @var SMTPRequest
     */
    protected SMTPRequest $request;

    /**
     * @var LoopInterface
     */
    protected LoopInterface $loop;

    /**
     * @var ConnectionInterface
     */
    protected ConnectionInterface $conn;

    /**
     * @var array
     */
    protected array $status;


    /**
     * @var SMTPSocketServer
     */
    protected SMTPSocketServer $socketServer;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * RequestParser constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop, SMTPSocketServer $socketServer)
    {
        $this->loop = $loop;
        $this->socketServer = $socketServer;
    }

    /**
     * @param ConnectionInterface $conn
     */
    public function handle(ConnectionInterface $conn) : void
    {

        $this->conn = $conn;

        $this->conn->write('220 Welcome to REACT SMTP server' . self::DELIMITER);

        $this->reset();
        $that = $this;

        $this->conn->on('data', function ($chunk) use ($conn, $that) {
            $that->handleData($chunk);
        });

    }


    protected function handleData($data) : void {

        $this->dataBuffer .= $data;

        $separatorPos = strpos($this->dataBuffer, self::DELIMITER);
        $separatorLen = strlen(self::DELIMITER);


        if(!$this->getStatus('hasData')) {
            $line = substr(
                $this->dataBuffer, 0, $separatorPos + $separatorLen
            );

            $this->dataBuffer = substr($this->dataBuffer, $separatorPos + $separatorLen);

            if(strlen(trim($line)) != 0) {
                $this->handleLine($line);
            }
        } else {
            if(preg_match('/--.*--\s*\.\s$/', $this->dataBuffer)) { // is end of data
                $this->request->appendMessage(substr($this->dataBuffer, 0, strlen($this->dataBuffer) - 1));
                $this->dataBuffer = '';
                $this->setStatus('hasData', false);
                $this->handleLine('NOOP');
            }
        }


    }


    /**
     * @param string $name
     * @param mixed $value
     */
    public function setStatus(string $name, $value)
    {
        $this->status[$name] = $value;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function getStatus(string $name)
    {
        if (array_key_exists($name, $this->status)) {
            return $this->status[$name];
        }
        return null;
    }

    /**
     * @param string $msg
     * @return string
     */
    private function dataSend(string $msg): string
    {
        $output = $msg . self::DELIMITER;
        $this->conn->write($output);
        return $output;
    }


    /**
     * @param string $line
     * @return string|null
     */
    public function handleLine(string $line): ?string
    {
        $str = new LineParser($line);
        $args = $str->parse();

        $command = array_shift($args);
        $commandCmp = strtolower($command);

        if ($commandCmp == 'helo') {

            $this->setStatus('hasHello', true);

            return $this->sendOk($this->getHostname());

        } elseif ($commandCmp == 'ehlo') {

            $this->setStatus('hasHello', true);
            $response = '250-' . $this->getHostname() . self::DELIMITER;
            $count = count($this->extendedCommands) - 1;

            for ($i = 0; $i < $count; $i++) {
                $response .= '250-' . $this->extendedCommands[$i] . self::DELIMITER;
            }

            $response .= '250 ' . end($this->extendedCommands);

            return $this->dataSend($response);

        } elseif ($commandCmp == 'mail') {
            if ($this->getStatus('hasHello')) {
                if (isset($args[0]) && $args[0]) {
                    $this->setStatus('hasMail', true);
                    $from = $args[0];
                    if (substr(strtolower($from), 0, 6) == 'from:<') {
                        $from = substr(substr($from, 6), 0, -1);
                    }
                    $this->request->setFrom($from);
                    //$this->mail = '';
                    $this->request->setMessage('');

                    return $this->sendOk();
                }
                return $this->sendSyntaxErrorInParameters();
            }
            return $this->sendSyntaxErrorCommandUnrecognized();
        } elseif ($commandCmp == 'rcpt') {
            if ($this->getStatus('hasHello')) {
                if (isset($args[0]) && $args[0]) {
                    $this->setStatus('hasMail', true);
                    $rcpt = $args[0];
                    if (substr(strtolower($rcpt), 0, 4) == 'to:<') {

                        $rcpt = substr(substr($rcpt, 4), 0, -1);
                        $this->request->addRecipient($rcpt);

                    }

                    return $this->sendOk();
                }
                return $this->sendSyntaxErrorInParameters();
            }
            return $this->sendSyntaxErrorCommandUnrecognized();
        } elseif ($commandCmp == 'data') {
            if ($this->getStatus('hasHello')) {
                $this->setStatus('hasData', true);

                return $this->sendDataResponse();
            }

            return $this->sendSyntaxErrorCommandUnrecognized();

        } elseif ($commandCmp == 'noop') {

            return $this->sendOk();

        } elseif ($commandCmp == 'quit') {

            $response = $this->sendQuit();
            $that = $this;
            $this->emit('request', [$this->request, $that]);

            return $response;

        /**
        *  switching to encryption connections is not implemented yet.
        *  will be added in future when reactPHP start support this function
        */
        /*
        } elseif ($commandCmp == 'auth') {
            $this->setStatus('hasAuth', true);

            if (empty($args)) {
                return $this->sendSyntaxErrorInParameters();
            }

            $authentication = strtolower($args[0]);

            if ($authentication == 'plain') {
                $this->setStatus('hasAuthPlain', true);

                if (isset($args[1])) {
                    $this->setStatus('hasAuthPlainUser', true);
                    $this->setCredentials([$args[1]]);

                    if ($this->authenticate('plain')) {
                        return $this->sendAuthSuccessResponse();
                    }

                    return $this->sendAuthInvalid();
                }

                return $this->sendAuthPlainResponse();
            } elseif ($authentication == 'login') {
                $this->setStatus('hasAuthLogin', true);

                return $this->sendAskForUserResponse();
            } elseif ($authentication == 'cram-md5') {
                return $this->sendCommandNotImplemented();
            }

            return $this->sendSyntaxErrorInParameters();

        } elseif ($commandCmp == 'starttls') {
            if (!empty($args)) {
                return $this->sendSyntaxErrorInParameters();
            }

            $this->sendReadyStartTls();


            try {
                $this->emit('debug', ['TRY SWITCH TO SECURED']);
                $this->socketServer->switchToSecured();
            } catch (RuntimeException $e) {
                $this->emit('debug', ['ERROR', $e]);
                return $this->sendTemporaryErrorStartTls();
            }
        */
        } elseif ($commandCmp == 'help') {
            return $this->sendOk('HELO, EHLO, MAIL FROM, RCPT TO, DATA, NOOP, QUIT');
        } elseif ($commandCmp == '.') {
            return $this->sendOk();
        } else {
            if ($this->getStatus('hasData')) {
                $this->request->appendMessage($line);
            } else {
                $tmp = [$this->id, $command, join('/ /', $args)];
                $this->emit('debug', [vsprintf('client %d not implemented: /%s/ - /%s/', $tmp), $this]);

                return $this->sendSyntaxErrorCommandUnrecognized();
            }
        }

        return '';
    }


    /**
     * @return string
     */
    public function sendQuit(): string
    {
        return $this->dataSend('221 ' . $this->getHostname() . ' Service closing transmission channel');
    }

    /**
     * @param string $text
     * @return string
     */
    private function sendOk(string $text = 'OK'): string
    {
        return $this->dataSend('250 ' . $text);
    }

    /**
     * @return string
     */
    private function sendDataResponse(): string
    {
        return $this->dataSend('354 Start mail input; end with <CRLF>.<CRLF>');
    }

    /**
     * @return string
     */
    private function sendAuthSuccessResponse(): string
    {
        return $this->dataSend('235 2.7.0 Authentication successful');
    }


    /**
     * @return string
     */
    private function sendAskForPasswordResponse(): string
    {
        return $this->dataSend('334 UGFzc3dvcmQ6');
    }


    /**
     * @return string
     */
    private function sendSyntaxErrorCommandUnrecognized(): string
    {
        return $this->dataSend('500 Syntax error, command unrecognized');
    }

    /**
     * @return string
     */
    private function sendSyntaxErrorInParameters(): string
    {
        return  $this->dataSend('501 Syntax error in parameters or arguments');
    }

    /**
     * @return string
     */
    private function sendCommandNotImplemented(): string
    {
        return $this->dataSend('502 Command not implemented');
    }

    /**
     * @return string
     */
    private function sendBadSequenceOrAuth(): string
    {
        return $this->dataSend('503 Bad sequence of commands');
    }

    /**
     * @return string
     */
    private function sendAuthInvalid(): string
    {
        return $this->dataSend('535 Authentication credentials invalid');
    }

    /**
     * @return string
     */
    private function sendUserUnknown(): string
    {
        return $this->dataSend('550 User unknown');
    }


    private function getHostname() : string {
        return $this->conn->getLocalAddress();
    }

    protected function reset() {
        $this->status = [];
        $this->status['hasHello'] = false;
        $this->status['hasMail'] = false;
        $this->status['hasShutdown'] = false;
        $this->request = new SMTPRequest();
    }





}