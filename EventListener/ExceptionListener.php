<?php

namespace Rollbar\Symfony\RollbarBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Class ExceptionListener
 *
 * @package Rollbar\Symfony\RollbarBundle\EventListener
 */
class ExceptionListener extends AbstractListener
{
    /**
     * Process exception
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof \Exception
            || (version_compare(PHP_VERSION, '7.0.0') >= 0 && $exception instanceof \Error)
        ) {
            $this->handleException($exception);
        }
    }

    /**
     * Handle provided exception
     *
     * @param \Exception $exception
     */
    public function handleException($exception)
    {
        // generate payload and log data
        list($message, $payload) = $this->getGenerator()->getExceptionPayload($exception);

        $this->getLogger()->error($message, [
            'payload' => $payload,
            'exception' => $exception,
        ]);
    }
}
