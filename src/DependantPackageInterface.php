<?php

namespace Rmk\PackageManager;

/**
 * Interface DependendPackageInterface
 *
 * @package Rmk\PackageManager
 */
interface DependantPackageInterface
{

    /**
     * Gives an array with packages which the current depends on
     *
     * The return value be must in form of ['Package\Name' => '<semver>'],
     * for example - ['Rmk\Users' => 'v1.2.0']
     *
     * @return array
     */
    public function getDependencies(): array;

    /**
     * Gives an array with composer packages which the current depends on
     *
     * The return value must be in form, like the ones, added to composer.json ( ['vendor/package-name' => '<semver>'] ),
     * for example - ['rmk/package-manager' => '^v1.2.0']
     *
     * @return array
     */
    public function getComposerDependencies(): array;
}
