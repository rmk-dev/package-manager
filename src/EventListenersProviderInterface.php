<?php

namespace Rmk\PackageManager;

/**
 * Interface EventListenersProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface EventListenersProviderInterface
{

    public function getEventListeners(): array;
}
