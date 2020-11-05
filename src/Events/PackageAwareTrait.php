<?php

namespace Rmk\PackageManager\Events;

use Rmk\PackageManager\PackageInterface;

/**
 * Trait PackageAwareTrait
 *
 * @package Rmk\PackageManager\Events
 */
trait PackageAwareTrait
{

    public function getPackage(): PackageInterface
    {
        return $this->getParam('package');
    }
}
