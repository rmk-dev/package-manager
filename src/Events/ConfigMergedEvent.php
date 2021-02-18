<?php

namespace Rmk\PackageManager\Events;

/**
 * Class DependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class ConfigMergedEvent extends PackageEvent
{
    use PackageAwareTrait;

    public function getConfig(): array
    {
        return $this->getParam('config');
    }

    public function getMergedConfig(): array
    {
        return $this->getParam('merged_config');
    }
}
