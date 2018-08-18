<?php

namespace Rollbar\Symfony\RollbarBundle\EventListener;

use Psr\Log\LoggerInterface;
use Rollbar\Symfony\RollbarBundle\DependencyInjection\RollbarExtension;
use Rollbar\Symfony\RollbarBundle\Payload\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ErrorListener
 *
 * @package Rollbar\Symfony\RollbarBundle\EventListener
 */
class ErrorListener extends AbstractListener
{
    /**
     * ErrorListener constructor.
     *
     * @param ContainerInterface $container
     * @param LoggerInterface    $logger
     * @param Generator          $generator
     */
    public function __construct(
        ContainerInterface $container,
        LoggerInterface $logger,
        Generator $generator
    ) {
        parent::__construct($container, $logger, $generator);

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

        list($message, $payload) = $this->getGenerator()->getErrorPayload($code, $message, $file, $line);

        $this->getLogger()->error($message, [
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
     *
     * @link: http://php.net/manual/en/function.error-get-last.php
     *
     * @return array|null
     *
     * @codeCoverageIgnore
     */
    protected function getLastError()
    {
        return error_get_last();
    }

    /**
     * Check do we need to report error or skip
     *
     * @param mixed $code
     *
     * @return int
     */
    protected function isReportable($code)
    {
        $code = (int) $code;
        $config = $this->getContainer()->getParameter(RollbarExtension::ALIAS . '.config');

        return true
            && $config['enabled']
            && !(error_reporting() === 0 && $config['report_suppressed'])
            && !(($config['use_error_reporting'] && (error_reporting() & $code) === 0))
            && !($config['included_errno'] != -1 && ($code & $config['included_errno']) != $code);
    }
}
