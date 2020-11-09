<?php

namespace Rmk\PackageManager;

/**
 * Interface EventListenersProviderInterface
 *
 * @package Rmk\PackageManager
 */
interface EventListenersProviderInterface extends PackageInterface
{

    public function getEventListeners(): array;
}
