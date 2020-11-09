<?php

namespace Rmk\PackageManager;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Rmk\Application\Event\ApplicationInitEvent;
use Rmk\Container\ContainerInterface;
use Rmk\Event\EventDispatcherAwareInterface;
use Rmk\Event\EventInterface;
use Rmk\Event\Traits\EventDispatcherAwareTrait;
use Rmk\PackageManager\Events\BeforePackagesLoadEvent;
use Rmk\PackageManager\Events\ComposerDependencyCheckEvent;
use Rmk\PackageManager\Events\DependencyCheckEvent;
use Rmk\PackageManager\Events\PackageEvent;
use Rmk\PackageManager\Exception\ComposerPackageNotInstalledException;
use Rmk\PackageManager\Exception\ComposerPackageVersionException;
use Rmk\PackageManager\Exception\DependencyPackageNotExistsException;
use Rmk\PackageManager\Exception\DependencyVersionException;
use Rmk\PackageManager\Exception\InvalidPackageException;
use Rmk\PackageManager\Exception\PackageDoesNotExistsException;
use Rmk\PackageManager\Exception\PackageManagerException;
use Rmk\ServiceContainer\ServiceContainer;

/**
 * Class PackageManager
 *
 * @package Rmk\PackageManager
 */
class PackageManager implements EventDispatcherAwareInterface
{

    use EventDispatcherAwareTrait;

    /**
     * The config key of package list
     */
    public const PACKAGE_CONFIG_KEY = 'packages';

    /**
     * The main application event the manager is attached to
     *
     * @var ApplicationInitEvent
     */
    protected ApplicationInitEvent $applicationInitEvent;

    /**
     * The list with package names that should be loaded
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $packages;

    /**
     * A list with names and versions of the loaded packages
     *
     * @var array|string[]
     */
    protected array $packageVersions = [];

    /**
     * @var VersionParser
     */
    protected VersionParser $versionParser;

    public function __construct(EventDispatcherInterface $eventDispatcher, ContainerInterface $packages)
    {
        $this->setEventDispatcher($eventDispatcher);
        $this->setPackages($packages);
    }

    /**
     * @param ApplicationInitEvent $event
     *
     * @throws \Throwable
     */
    public function onApplicationInit(ApplicationInitEvent $event): void
    {
        $this->applicationInitEvent = $event;
        $this->loadPackages();
    }

    /**
     * Loads application packages
     *
     * @throws \Throwable
     */
    public function loadPackages(): void
    {
        /** @var ContainerInterface $config */
        $config = $this->applicationInitEvent->getServiceContainer()->get(ServiceContainer::CONFIG_KEY);
        if (!$config->has(self::PACKAGE_CONFIG_KEY)) {
            return;
        }
        $packageList = $config->get(self::PACKAGE_CONFIG_KEY);
        try {
            $this->dispatchPackageEvent(new BeforePackagesLoadEvent($this, [
                'config' => $config,
                'package_list' => $packageList,
                EventInterface::PARENT_EVENT => $this->applicationInitEvent,
            ]));
            $this->instantiatePackages($packageList);
            $this->versionParser = new VersionParser();
            $this->checkDependencies();
        } catch (PackageManagerException $exception) {
            $this->applicationInitEvent->setParam('exception', $exception);
            $this->applicationInitEvent->stopPropagation($exception->getMessage());
        }
    }

    /**
     * Creates objects for every package
     *
     * @param array $packageList
     */
    protected function instantiatePackages(array $packageList): void
    {
        foreach ($packageList as $packageName) {
            $packageClass = $packageName . '\\Package';
            if (!class_exists($packageClass)) {
                throw new PackageDoesNotExistsException($packageName . ' does not exists');
            }
            $package = new $packageClass();
            if (!($package instanceof PackageInterface)) {
                throw new InvalidPackageException($packageName . ' is not a valid package');
            }
            if (method_exists($package, 'setName')) {
                $package->setName($packageName);
            }
            $this->packages->add($package, $packageName);
            $this->packageVersions[$packageName] = $package->getVersion();
        }
    }

    /**
     * Checks all packages dependencies
     *
     * @return void
     *
     * @throws \Throwable
     */
    protected function checkDependencies(): void
    {
        foreach ($this->packages as $package) {
            /** @var PackageInterface $package */
            if ($package instanceof DependantPackageInterface) {
                // Check whether the required composer packages are installed
                $this->checkComposerDependencies($package);
                $this->dispatchPackageEvent(new ComposerDependencyCheckEvent($this, [
                    'package' => $package,
                    'composer_dependencies' => $package->getComposerDependencies(),
                ]));

                // Check whether the required application packages are installed
                $this->checkPackageDependencies($package);
                $this->dispatchPackageEvent(new DependencyCheckEvent($this, [
                    'package' => $package,
                    'dependencies' => $package->getDependencies(),
                ]));
            }
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
            if (!$this->packages->has($packageName)) {
                throw new DependencyPackageNotExistsException(sprintf($dependencyMissingErr, $packageName));
            }
            /** @var PackageInterface $dependency */
            $dependency = $this->packages->get($packageName);
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

    /**
     * Dispatch package event and checks whether it is stopped with exception
     *
     * @param PackageEvent $event
     *
     * @throws \Throwable
     */
    protected function dispatchPackageEvent(PackageEvent $event): void
    {
        $this->getEventDispatcher()->dispatch($event);
        if ($event->isPropagationStopped()) {
            $exception = $event->getParam('exception');
            if ($exception && $exception instanceof \Throwable) {
                throw $exception;
            }
        }
    }

    /**
     * @return ContainerInterface
     */
    public function getPackages(): ContainerInterface
    {
        return $this->packages;
    }

    /**
     * @param ContainerInterface $packages
     */
    public function setPackages(ContainerInterface $packages): void
    {
        $this->packages = $packages;
    }
}