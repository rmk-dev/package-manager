<?php

namespace Rmk\PackageManager\Events;

use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventTrait;
use Rmk\PackageManager\PackageManager;

/**
 * Class PackageManagerEvent
 *
 * @package Rmk\PackageManager\Events
 */
abstract class PackageEvent implements EventInterface
{

    use EventTrait;

    /**
     * Returns the package manager
     *
     * @return mixed|null
     */
    public function getPackageManager(): PackageManager
    {
        return $this->getEmitter();
    }
}
