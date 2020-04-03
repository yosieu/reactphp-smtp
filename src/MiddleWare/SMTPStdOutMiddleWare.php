<?php

namespace Yosieu\React\Smtp\MiddleWare;

use Yosieu\React\Smtp\SMTPRequest;

class SMTPStdOutMiddleWare implements iSMTPMiddleWare
{

    /**
     * @param SMTPRequest $request
     * @param callable|null $next
     * @return mixed|null
     */
    public function __invoke(SMTPRequest $request, callable $next = null)
    {

        $stdout = fopen('php://stdout', 'w');
        fwrite($stdout, print_r($request, true));
        fclose($stdout);

        if($next !== null) {
             return $next($request);
        }else {
            return null;
        }

    }

}