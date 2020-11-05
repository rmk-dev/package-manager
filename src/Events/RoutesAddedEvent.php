<?php

namespace Rmk\PackageManager\Events;

/**
 * Class DependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class RoutesAddedEvent extends PackageEvent
{
    use PackageAwareTrait;

    public function getRoutes(): array
    {
        return $this->getParam('routes');
    }
}
