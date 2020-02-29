<?php


namespace Yosieu\React\Smtp\MiddleWare;


use Yosieu\React\Smtp\SMTPRequest;

interface iSMTPMiddleWare
{

    /**
     * @param SMTPRequest $request
     * @param callable|null $next
     * @return mixed|null
     */
    public function __invoke(SMTPRequest $request, callable $next = null) : ?mixed;

}