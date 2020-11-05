<?php

namespace Rmk\PackageManager\Events;

/**
 * Class ComposerDependencyCheckEvent
 *
 * @package Rmk\PackageManager\Events
 */
class ComposerDependencyCheckEvent extends PackageEvent
{

    use PackageAwareTrait;

    public function getComposerDependencies(): array
    {
        return $this->getParam('composer_dependencies');
    }
}
