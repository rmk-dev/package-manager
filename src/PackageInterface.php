<?php

namespace Rmk\PackageManager;

use Rmk\Application\Event\ApplicationInitEvent;

/**
 * Interface PackageInterface
 *
 * @package Rmk\PackageManager
 */
interface PackageInterface
{

    public function getVersion(): string;

    public function init(ApplicationInitEvent $event): void;
}
