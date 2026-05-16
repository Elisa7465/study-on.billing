<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (null !== $event->getResponse()) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = 500;

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        
        if (401 === $statusCode && str_contains($exception->getMessage(), 'Invalid credentials')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'code' => $statusCode,
            'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Internal Server Error',
        ], $statusCode));
    }
}

