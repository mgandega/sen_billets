<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Response;

class CorsListener
{
    private array $allowedOrigins = [
        'http://127.0.0.1:8081', // ton frontend
    ];

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // Préflight OPTIONS
        if ($request->isMethod('OPTIONS')) {
            $response = new Response();
            $origin = $request->headers->get('Origin');
            $response->headers->set('Access-Control-Allow-Origin', in_array($origin, $this->allowedOrigins) ? $origin : '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->setStatusCode(204);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $origin = $request->headers->get('Origin');

        if ($origin && in_array($origin, $this->allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }
}
