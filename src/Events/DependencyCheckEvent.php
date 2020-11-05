<?php

namespace Rmk\PackageManager\Events;

/**
 * Class DependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class DependencyCheckEvent extends PackageEvent
{
    use PackageAwareTrait;

    public function getDependencies(): array
    {
        return $this->getParam('dependencies');
    }
}
