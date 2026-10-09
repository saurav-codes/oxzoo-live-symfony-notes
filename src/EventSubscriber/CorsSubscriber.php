<?php

namespace App\EventSubscriber;

use App\Zoo\Zoo;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** CORS for the panel on /_zoo/health and /_zoo/probe, from ZOO_PANEL_ORIGIN. */
final class CorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 250],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || 'OPTIONS' !== $request->getMethod() || !self::covered($request)) {
            return;
        }
        $response = new Response('', 204);
        if (null !== $origin = self::allowedOrigin($request)) {
            $response->headers->add([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'Access-Control-Max-Age' => '600',
            ]);
            $response->setVary('Origin', false);
        }
        $event->setResponse($response);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !self::covered($request) || null === $origin = self::allowedOrigin($request)) {
            return;
        }
        $event->getResponse()->headers->set('Access-Control-Allow-Origin', $origin);
        $event->getResponse()->setVary('Origin', false);
    }

    private static function covered(Request $request): bool
    {
        return \in_array($request->getPathInfo(), ['/_zoo/health', '/_zoo/probe'], true);
    }

    private static function allowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (null === $origin || !\in_array($origin, Zoo::panelOrigins(Zoo::env('ZOO_PANEL_ORIGIN')), true)) {
            return null;
        }

        return $origin;
    }
}
