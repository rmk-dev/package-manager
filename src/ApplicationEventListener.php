<?php

namespace Rmk\PackageManager;

use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\PackageManager\Exception\PackageManagerException;
use Throwable;

/**
 * Class ApplicationEventListener
 *
 * @package Rmk\PackageManager
 */
class ApplicationEventListener
{

    /**
     * @var PackageManager
     */
    protected PackageManager $packageManager;

    /**
     * ApplicationEventListener constructor.
     * @param PackageManager $packageManager
     */
    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    /**
     * @param ApplicationInitEvent $event
     *
     * @throws Throwable
     */
    public function onApplicationInit(ApplicationInitEvent $event): void
    {
        try {
            $this->packageManager->loadPackages($event);
        } catch (PackageManagerException $exception) {
            if (!$event->isPropagationStopped()) {
                $event->setParam('exception', $exception);
                $event->stopPropagation($exception->getMessage());
            }
        }
    }
}
