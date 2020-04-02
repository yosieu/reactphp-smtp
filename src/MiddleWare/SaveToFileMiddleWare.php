<?php


namespace Yosieu\React\Smtp\MiddleWare;

use Yosieu\React\Smtp\SMTPRequest;

class SaveToFileMiddleWare
{

    /**
     * @var string|null
     */
    protected ?string $dirPath;

    /**
     * @param string $path
     */
    public function setDirectory(string $path) {
        $this->dirPath = $path;
    }

    /**
     * @return string
     */
    public function getDirectory() {

        if($this->dirPath === null) {
            return __DIR__;
        }

        return $this->dirPath;

    }

    /**
     * SaveToFileMiddleWare constructor.
     * @param ?string $outputDirectory
     */
    public function __construct($outputDirectory = null)
    {

        if($outputDirectory !== null) {
            $this->setDirectory($outputDirectory);
        }

    }

    public function getPath() {
        return $this->getDirectory() . '/mail-' . (new \DateTime())->format('Y-m-d-H:i:s.u') . '.txt';
    }

    /**
     * @param SMTPRequest $request
     * @param callable|null $next
     * @return mixed|null
     */
    public function __invoke(SMTPRequest $request, callable $next = null) {

        $file = fopen($this->getPath(), 'w+');

        fwrite($file, 'FROM: ' .$request->getFrom() . PHP_EOL );

        foreach($request->getRecipients() as $recipient_email => $recipient_name) {
            fwrite($file, 'TO: '.$recipient_email . '<' .$recipient_name. '>' . PHP_EOL);
        }

        fwrite($file, $request->getMessage());

        fclose($file);

        if($next !== null) {
            return $next($request);
        }else {
            return null;
        }

    }

}