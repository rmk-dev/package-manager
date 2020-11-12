<?php

namespace Rmk\PackageManager;

use Psr\EventDispatcher\EventDispatcherInterface;
use Rmk\PackageManager\Events\PackageEvent;
use Throwable;

/**
 * Trait PackageEventDispatcherTrait
 *
 * @package Rmk\PackageManager
 *
 * @method ?EventDispatcherInterface getEventDispatcher()
 */
trait PackageEventDispatcherTrait
{

    /**
     * Dispatch package event and checks whether it is stopped with exception
     *
     * @param PackageEvent $event
     *
     * @throws Throwable
     */
    public function dispatchPackageEvent(PackageEvent $event): void
    {
        $this->getEventDispatcher()->dispatch($event);
        if ($event->isPropagationStopped()) {
            $exception = $event->getParam('exception');
            if ($exception && $exception instanceof Throwable) {
                throw $exception;
            }
        }
    }
}
