<?php

namespace Rmk\PackageManager\Events;

use Rmk\PackageManager\PackageInterface;

/**
 * Class LoadPackageEvent
 *
 * @package Rmk\PackageManager\Events
 */
class PackageLoadedEvent extends PackageEvent
{
    use PackageAwareTrait;
}
