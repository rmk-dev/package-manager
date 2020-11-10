<?php

namespace Rmk\PackageManager;

/**
 * Interface RoutesProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface RoutesProviderInterface extends PackageInterface
{

    public function getRoutes(): array;
}
