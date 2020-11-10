<?php

namespace Rmk\PackageManager\Events;

use Rmk\Container\ContainerInterface;

/**
 * Class AfterPackagesLoadedEvent
 *
 * @package Rmk\PackageManager\Events
 */
class AfterPackagesLoadedEvent extends PackageEvent
{

    public function getLoadedPackages(): ContainerInterface
    {
        return $this->getParam('packages');
    }
}
