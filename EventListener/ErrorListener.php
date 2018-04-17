<?php

namespace Rollbar\Symfony\RollbarBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Rollbar\Symfony\RollbarBundle\DependencyInjection\RollbarExtension;
use Psr\Log\LoggerInterface;
use Rollbar\Symfony\RollbarBundle\Payload\Generator;

class ErrorListener
{
    private $container;
    private $logger;
    private $generator;
    
    /**
     * ErrorListener constructor.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Rollbar\Symfony\RollbarBundle\Payload\Generator $generator
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        Generator $generator
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->generator = $generator;

        // here only errors, so we have to setup handler here
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * Handle error
     *
     * @param int    $code
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @return void
     */
    public function handleError($code, $message, $file, $line)
    {
        if (!$this->isReportable($code)) {
            return;
        }

        list($message, $payload) = $this->generator->getErrorPayload($code, $message, $file, $line);

        $this->logger->error($message, [
            'payload' => $payload,
        ]);
    }

    /**
     * Process fatal errors
     */
    public function handleFatalError()
    {
        $error = $this->getLastError();
        if (empty($error)) {
            return;
        }

        // due to PHP docs we allways will have such structure for errors
        $code    = $error['type'];
        $message = $error['message'];
        $file    = $error['file'];
        $line    = $error['line'];

        $this->handleError($code, $message, $file, $line);
    }

    /**
     * Wrap php error_get_last() to get more testable code
     * @link: http://php.net/manual/en/function.error-get-last.php
     *
     * @return array|null
     * @codeCoverageIgnore
     */
    protected function getLastError()
    {
        return error_get_last();
    }

    /**
     * Check do we need to report error or skip
     *
     * @param $code
     *
     * @return int
     */
    protected function isReportable($code)
    {
        $code = (int)$code;
        $config = $this->container->getParameter(RollbarExtension::ALIAS . '.config');

        return true
            && $config['enable']
            && !(error_reporting() === 0 && $config['config']['report_suppressed'])
            && !(($config['config']['use_error_reporting'] && (error_reporting() & $code) === 0))
            && !($config['config']['included_errno'] != -1 && ($code & $config['config']['included_errno']) != $code);
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // dummy
    }
}
