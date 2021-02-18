<?php

namespace Rmk\PackageManager;

/**
 * Interface ServiceProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface ServiceProviderInterface extends PackageInterface
{

    public function getServices(): array;
}
