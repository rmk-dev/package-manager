<?php

namespace Rmk\PackageManager;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\ComposerDependencyCheckEvent;
use Rmk\PackageManager\Events\DependencyCheckEvent;
use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
use Rmk\PackageManager\Exception\ComposerPackageVersionException;
use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
use Rmk\PackageManager\Exception\DependencyVersionException;
use Throwable;

/**
 * Class PackageDependencyChecker
 *
 * @package Rmk\PackageManager
 */
class PackageDependencyChecker implements EventDispatcherAwareInterface
{

    use EventDispatcherAwareTrait;

    use PackageEventDispatcherTrait;

    /**
     * Version parser for composer packages
     *
     * @var VersionParser
     */
    protected VersionParser $versionParser;

    /**
     * @var PackageManager
     */
    protected PackageManager $packageManager;

    /**
     * PackageDependencyChecker constructor.
     *
     * @param PackageManager $packageManager
     */
    public function __construct(PackageManager $packageManager) {
        $this->packageManager = $packageManager;
        $this->setEventDispatcher($packageManager->getEventDispatcher());
        $this->versionParser = new VersionParser();
    }


    /**
     * Checks all packages dependencies
     *
     * @param PackageInterface $package
     *
     * @return void
     *
     * @throws Throwable
     */
    public function checkDependencies(PackageInterface $package): void
    {
        if ($package instanceof DependantPackageInterface) {
            // Check whether the required composer packages are installed
            $this->checkComposerDependencies($package);
            $this->dispatchPackageEvent(new ComposerDependencyCheckEvent($this, [
                'package' => $package,
                'composer_dependencies' => $package->getComposerDependencies(),
                EventInterface::PARENT_EVENT => $this->packageManager->getApplicationInitEvent()
            ]));

            // Check whether the required application packages are installed
            $this->checkPackageDependencies($package);
            $this->dispatchPackageEvent(new DependencyCheckEvent($this, [
                'package' => $package,
                'dependencies' => $package->getDependencies(),
                EventInterface::PARENT_EVENT => $this->packageManager->getApplicationInitEvent()
            ]));
        }
    }

    /**
     * Checks whether required packages are installed with the required versions
     *
     * @param DependantPackageInterface $package
     */
    protected function checkPackageDependencies(DependantPackageInterface $package): void
    {
        $dependencies = $package->getDependencies();
        $dependencyMissingErr = '%s is required as dependency, but is not loaded';
        $versionErrMessage = '%s is required in version constraint %s, version %s is installed';
        foreach ($dependencies as $packageName => $packageVersion) {
            if (!$this->packageManager->hasPackage($packageName)) {
                throw new DependencyPackageNotExistsException(sprintf($dependencyMissingErr, $packageName));
            }
            $dependency = $this->packageManager->getPackage($packageName);
            $dependencyVersion = $dependency->getVersion();
            if (!Semver::satisfies($dependencyVersion, $packageVersion)) {
                throw new DependencyVersionException(sprintf($versionErrMessage, $packageName, $packageVersion, $dependencyVersion));
            }
        }
    }

    /**
     * Checks whether composer packages are installed in the required versions
     *
     * @param DependantPackageInterface $package
     */
    protected function checkComposerDependencies(DependantPackageInterface $package): void
    {
        $composerDependencies = $package->getComposerDependencies();
        $composerPackageErr = 'Composer package %s is required, but not installed. Try running \'composer require %s\'';
        $composerVersionErr = 'Composer package %s in version %s is required, but version %s is installed';
        foreach ($composerDependencies as $packageName => $packageVersion) {
            if (!InstalledVersions::isInstalled($packageName)) {
                throw new ComposerPackageNotInstalledException(sprintf($composerPackageErr, $packageName, $packageName));
            }
            if (!InstalledVersions::satisfies($this->versionParser, $packageName, $packageVersion)) {
                $installedVersion = InstalledVersions::getVersion($packageName);
                throw new ComposerPackageVersionException(sprintf($composerVersionErr, $packageName, $packageVersion, $installedVersion));
            }
        }
    }
}
