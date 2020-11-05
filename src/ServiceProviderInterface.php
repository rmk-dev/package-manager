<?php

namespace Rmk\PackageManager;

/**
 * Interface ServiceProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface ServiceProviderInterface
{

    public function getServices(): array;
}
