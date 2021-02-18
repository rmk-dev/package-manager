<?php

namespace Rmk\PackageManager;

/**
 * Interface ConfigProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface ConfigProviderInterface
{

    public function getConfig(): array;
}
