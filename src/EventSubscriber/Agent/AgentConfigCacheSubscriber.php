<?php

declare(strict_types=1);

namespace App\EventSubscriber\Agent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AgentConfigCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->supports($request)) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!$response->isSuccessful() || !\is_string($content) || '' === $content) {
            return;
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded) || !\is_string($decoded['version'] ?? null)) {
            return;
        }

        $response->setPrivate();
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->setEtag($decoded['version']);
        $response->isNotModified($request);
    }

    private function supports(Request $request): bool
    {
        return 'GET' === $request->getMethod() && '/api/agent/config' === $request->getPathInfo();
    }
}
