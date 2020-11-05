<?php

namespace Rmk\PackageManager\Events;

use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventTrait;

/**
 * Class PackageManagerEvent
 *
 * @package Rmk\PackageManager\Events
 */
abstract class PackageEvent implements EventInterface
{

    use EventTrait;

    /**
     * @todo Add return type once the PackageManager is ready
     *
     * @return mixed|null
     */
    public function getPackageManager()
    {
        return $this->getParam('package_manager');
    }
}
