<?php


namespace Yosieu\React\Smtp;


use Evenement\EventEmitter;

class SMTPRequest extends EventEmitter
{

    const DELIMITER = "\r\n";

    /**
     * @var string
     */
    protected string $from;

    /**
     * @var array [email=>name]
     */
    protected array $recipients = [];

    /**
     * @var string
     */
    public string $message;


    public function appendMessage($messagePart) {
        $this->message =  $this->message . $messagePart . self::DELIMITER;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    public function getMessage() {
        return $this->message;
    }

    /**
     * @param $from
     */
    public function setFrom($from) {
        $this->from = $from;
    }

    /**
     * @return string
     */
    public function getFrom() {
        return $this->from;
    }


    /**
     * @param $email
     * @param $name
     */
    public function addRecipient($email, $name = null) {
        $this->recipients[$email] = $name;
    }

    /**
     * @param array $recipients
     */
    public function addRecipients(array $recipients) {
        foreach($recipients as $email => $name) {
            $this->addRecipient($email, $name);
        }
    }

    /**
     * @return array|Array
     */
    public function getRecipients() {
        return $this->recipients;
    }



}