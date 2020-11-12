<?php

namespace Rmk\PackageManager;

use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Exception\InvalidPackageException;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;

/**
 * Class PackageInitiator
 *
 * @package Rmk\PackageManager
 */
class PackageInitiator implements EventDispatcherAwareInterface
{

    use EventDispatcherAwareTrait;

    use PackageEventDispatcherTrait;

    protected PackageManager $packageManager;

    protected ApplicationInitEvent $applicationEvent;

    /**
     * PackageInitiator constructor.
     * @param PackageManager $packageManager
     */
    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
        $this->setEventDispatcher($packageManager->getEventDispatcher());
        $this->applicationEvent = $packageManager->getApplicationInitEvent();
    }


    /**
     * Creates objects for every package
     *
     * @param string $packageName
     *
     * @return PackageInterface
     */
    public function instantiatePackage(string $packageName): PackageInterface
    {
        $packageClass = $packageName . '\\Package';
        if (!class_exists($packageClass)) {
            throw new PackageDoesNotExistsException($packageName . ' does not exists');
        }
        $package = new $packageClass();
        if (!($package instanceof PackageInterface)) {
            throw new InvalidPackageException($packageName . ' is not a valid package');
        }
        if (method_exists($package, 'setName')) {
            $package->setName($packageName);
        }

        return $package;
    }
}
