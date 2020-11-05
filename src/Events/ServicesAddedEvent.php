<?php

namespace Rmk\PackageManager\Events;

/**
 * Class DependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class ServicesAddedEvent extends PackageEvent
{
    use PackageAwareTrait;

    public function getServices(): array
    {
        return $this->getParam('services');
    }
}
