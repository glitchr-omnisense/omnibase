<?php

namespace Base;

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Kernel extends HttpCache
{
    public function __construct(string $environment, bool $debug)
    {
        // Load default kernel
        $kernel = new \App\Kernel($environment, $debug);
        $_SERVER["APP_TIMER"] = microtime(true);

        // Forward to HttpCache
        parent::__construct($kernel);
    }


    protected function invalidate(Request $request, bool $catch = false): Response
    {
        if ('PURGE' !== $request->getMethod())
            return parent::invalidate($request, $catch);

        if ('127.0.0.1' !== $request->getClientIp())
            return new Response('Invalid HTTP method', Response::HTTP_BAD_REQUEST);

        $response = new Response();
        if ($this->getStore()->purge($request->getUri()))
            $response->setStatusCode(Response::HTTP_OK, 'Purged');
        else
            $response->setStatusCode(Response::HTTP_NOT_FOUND, 'Not found');

        return $response;
    }
}
